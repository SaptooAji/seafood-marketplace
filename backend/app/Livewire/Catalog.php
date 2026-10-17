<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\ProductCondition;
use App\Enums\SalesMode;
use App\Enums\StoreStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Store;
use App\View\Components\Layouts\App as Layout;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The product catalog at `/produk`: search, filters, and sort, all kept in
 * the URL so a filtered list can be shared. Every URL value is untrusted:
 * unknown values give an empty list, invalid numbers and sorts are ignored.
 *
 * The buyer's location is the exception: it lives only in the component
 * state, never in the URL, so it does not end up in access logs, browser
 * history, or a Referer header. It is rounded to about 110 m.
 */
class Catalog extends Component
{
    use WithPagination;

    public const int PER_PAGE = 24;

    public const int MAX_QUERY_LENGTH = 100;

    /**
     * Sort options as they appear in the URL, with their labels.
     */
    public const array SORTS = [
        'terbaru' => 'Terbaru',
        'termurah' => 'Harga termurah',
        'termahal' => 'Harga termahal',
    ];

    /**
     * Radius options in km for the nearest stores filter.
     */
    public const array RADII = [5, 10, 25, 50];

    /**
     * The most stores the store filter takes from the URL at once.
     */
    public const int MAX_STORES = 10;

    public const int MAX_SLUG_LENGTH = 100;

    /**
     * Bars in the price histogram.
     */
    public const int PRICE_BARS = 8;

    private const array FILTERS = ['q', 'category', 'condition', 'preOrder', 'store', 'minPrice', 'maxPrice', 'sort', 'radius'];

    /**
     * The filters a chip can remove: all but the sort, which is a choice of
     * order rather than a filter.
     */
    private const array REMOVABLE_FILTERS = ['q', 'category', 'condition', 'preOrder', 'store', 'minPrice', 'maxPrice', 'radius'];

    #[Url(except: '')]
    public string $q = '';

    #[Url(as: 'kategori', except: '')]
    public string $category = '';

    #[Url(as: 'kondisi', except: '')]
    public string $condition = '';

    /**
     * Only `1` turns the filter on; any other value is ignored.
     */
    #[Url(as: 'pre_order', except: '')]
    public string $preOrder = '';

    /**
     * Store slugs separated by commas. An old link with a single slug still
     * works; unknown slugs are ignored.
     */
    #[Url(as: 'toko', except: '')]
    public string $store = '';

    #[Url(as: 'harga_min', except: '')]
    public string $minPrice = '';

    #[Url(as: 'harga_maks', except: '')]
    public string $maxPrice = '';

    #[Url(as: 'urut', except: 'terbaru')]
    public string $sort = 'terbaru';

    #[Url(except: '')]
    public string $radius = '';

    /**
     * The buyer's latitude, rounded; deliberately not in the URL.
     */
    public ?string $lat = null;

    /**
     * The buyer's longitude, rounded; deliberately not in the URL.
     */
    public ?string $lng = null;

    /**
     * The ids of the chosen stores that are active, found again on each render.
     *
     * @var list<int>
     */
    private array $storeIds = [];

    public function mount(): void
    {
        $this->q = $this->cutQuery($this->q);

        // Livewire reads "true" in the URL as 1; only a literal 1 turns the
        // pre-order filter on.
        if (request()->query('pre_order') !== '1') {
            $this->preOrder = '';
        }

        // A shared or refreshed link carries no location to sort by.
        if ($this->sort === 'terdekat') {
            $this->sort = 'terbaru';
        }
    }

    /**
     * Take the buyer's location from the browser and sort by distance. A value
     * that is not a point on earth is ignored.
     */
    public function locate(mixed $lat, mixed $lng): void
    {
        if (! is_numeric($lat) || ! is_numeric($lng) || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
            return;
        }

        $this->lat = number_format((float) $lat, 3, '.', '');
        $this->lng = number_format((float) $lng, 3, '.', '');
        $this->sort = 'terdekat';
        $this->resetPage();
    }

    /**
     * Remove one filter, from its chip. Any other name is ignored, so the
     * request cannot reset the location, the sort, or the page.
     */
    public function removeFilter(string $filter): void
    {
        if (! in_array($filter, self::REMOVABLE_FILTERS, true)) {
            return;
        }

        $this->{$filter} = '';
        $this->resetPage();
    }

    /**
     * Remove every filter and go back to the default sort. The location
     * stays, so the buyer can still sort by distance without asking again.
     */
    public function clearFilters(): void
    {
        foreach (self::REMOVABLE_FILTERS as $filter) {
            $this->{$filter} = '';
        }

        $this->sort = 'terbaru';
        $this->resetPage();
    }

    /**
     * Filter by a root category from its card, or remove the filter when the
     * card is already chosen. Any other slug is ignored.
     */
    public function toggleCategory(string $slug): void
    {
        if ($this->category === $slug) {
            $this->category = '';
            $this->resetPage();

            return;
        }

        if (! Category::query()->whereNull('parent_id')->where('slug', $slug)->exists()) {
            return;
        }

        $this->category = $slug;
        $this->resetPage();
    }

    /**
     * Turn the pre-order filter on or off.
     */
    public function togglePreOrder(): void
    {
        $this->preOrder = $this->preOrder === '1' ? '' : '1';
        $this->resetPage();
    }

    /**
     * Add an active store to the store filter, or take it out when it is
     * already there. Any other slug is ignored, and so is a store past the
     * tenth.
     */
    public function toggleStore(string $slug): void
    {
        $slugs = $this->storeSlugs();

        if (in_array($slug, $slugs, true)) {
            $slugs = array_values(array_diff($slugs, [$slug]));
        } elseif (count($slugs) < self::MAX_STORES && Store::query()->where('status', StoreStatus::Active)->where('slug', $slug)->exists()) {
            $slugs[] = $slug;
        } else {
            return;
        }

        $this->store = implode(',', $slugs);
        $this->resetPage();
    }

    public function updatedQ(): void
    {
        $this->q = $this->cutQuery($this->q);
    }

    /**
     * Any filter change starts again from the first page.
     */
    public function updated(string $property): void
    {
        if (in_array($property, self::FILTERS, true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $category = $this->selectedCategory();
        $stores = Store::query()
            ->where('status', StoreStatus::Active)
            ->orderBy('is_demo')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'is_demo']);

        $slugs = $this->storeSlugs();
        $this->storeIds = $stores->filter(fn (Store $store) => in_array($store->slug, $slugs, true))->pluck('id')->values()->all();

        return view('livewire.catalog', [
            'products' => $this->products($category),
            'located' => $this->isLocated(),
            'radii' => self::RADII,
            'categoryCards' => $this->categoryCards(),
            'conditionOptions' => $this->conditionOptions($category),
            'storeOptions' => $this->storeOptions($category, $stores),
            'priceBars' => $this->priceBars($category),
            'activeFilters' => $this->activeFilters($category, $stores),
            'filtered' => $this->isFiltered() || $this->radius !== '',
            'sorts' => $this->isLocated() ? [...self::SORTS, 'terdekat' => 'Terdekat'] : self::SORTS,
        ])->layout(Layout::class, [
            'title' => $category->name ?? 'Katalog',
            'description' => 'Cari hasil laut segar dari penjual terverifikasi: kepiting, cumi, tiram, dan ikan, dengan harga dan stok terbaru.',
            'noindex' => $this->isFiltered(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    private function products(?Category $category): LengthAwarePaginator
    {
        $query = Product::query()
            ->visibleInCatalog()
            ->withCardDetails()
            ->select(['products.id', 'products.store_id', 'products.name', 'products.slug', 'products.created_at'])
            ->addSelect(['min_price' => $this->cheapestActivePrice()]);

        if (! $this->applyFilters($query, $category)) {
            $query->whereRaw('false');
        }

        $this->applyDistance($query);

        $query->realStoresFirst();

        match (true) {
            $this->sort === 'termurah' => $query->orderBy('min_price'),
            $this->sort === 'termahal' => $query->orderByDesc('min_price'),
            $this->sort === 'terdekat' && $this->isLocated() => $query->orderBy('store_distances.distance_km'),
            default => $query->latest('products.created_at'),
        };

        return $query->orderByDesc('products.id')->paginate(self::PER_PAGE);
    }

    /**
     * Narrow the query by each filter that is set, but for the ones named in
     * `$except` (category, condition, store, price), so a filter can count
     * its own options.
     *
     * @param  Builder<Product>  $query
     * @param  list<string>  $except
     * @return bool false when a filter names something unknown, so nothing matches
     */
    private function applyFilters(Builder $query, ?Category $category, array $except = []): bool
    {
        if ($this->q !== '') {
            $query->whereLike('products.name', '%'.$this->escapeLike($this->q).'%');
        }

        if (! in_array('category', $except, true) && $this->category !== '') {
            if ($category === null) {
                return false;
            }

            $query->whereIn('products.category_id', [$category->id, ...$category->descendantIds()]);
        }

        if (! in_array('condition', $except, true) && $this->condition !== '') {
            $condition = ProductCondition::tryFrom($this->condition);

            if ($condition === null) {
                return false;
            }

            $query->where('products.condition', $condition);
        }

        if ($this->preOrder === '1') {
            $query->where('products.sales_mode', SalesMode::PreOrder);
        }

        if (! in_array('store', $except, true) && $this->storeIds !== []) {
            $query->whereIn('products.store_id', $this->storeIds);
        }

        if (in_array('price', $except, true)) {
            return true;
        }

        $minPrice = $this->price($this->minPrice);
        $maxPrice = $this->price($this->maxPrice);

        // The starting price is the cheapest active variant: it is at least the
        // minimum when no active variant is cheaper, and at most the maximum
        // when some active variant is not dearer.
        if ($minPrice !== null) {
            $query->whereDoesntHave('variants', fn (Builder $variant) => $variant->where('is_active', true)->where('price', '<', $minPrice));
        }

        if ($maxPrice !== null) {
            $query->whereHas('variants', fn (Builder $variant) => $variant->where('is_active', true)->where('price', '<=', $maxPrice));
        }

        return true;
    }

    /**
     * With a known location, add each product's store distance and keep only
     * the stores within the chosen radius.
     *
     * @param  Builder<Product>  $query
     */
    private function applyDistance(Builder $query): void
    {
        if ($this->lat === null || $this->lng === null) {
            return;
        }

        $distances = Store::query()->select('stores.id')->withDistanceFrom($this->lat, $this->lng);

        $query->joinSub($distances, 'store_distances', 'store_distances.id', '=', 'products.store_id')
            ->addSelect('store_distances.distance_km');

        $radius = $this->radiusKm();

        if ($radius !== null) {
            $query->where('store_distances.distance_km', '<=', $radius);
        }
    }

    /**
     * Keep only the products of stores within the chosen radius, without
     * adding the distance to the selected columns.
     *
     * @param  Builder<Product>  $query
     */
    private function applyRadius(Builder $query): void
    {
        $radius = $this->radiusKm();

        if ($this->lat === null || $this->lng === null || $radius === null) {
            return;
        }

        $nearby = DB::query()
            ->fromSub(Store::query()->select('stores.id')->withDistanceFrom($this->lat, $this->lng), 'store_distances')
            ->where('distance_km', '<=', $radius)
            ->select('id');

        $query->whereIn('products.store_id', $nearby);
    }

    /**
     * The chosen radius in km, or null when it is not one of the options.
     */
    private function radiusKm(): ?int
    {
        return ctype_digit($this->radius) && in_array((int) $this->radius, self::RADII, true) ? (int) $this->radius : null;
    }

    private function isLocated(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }

    /**
     * The price of the product's cheapest active variant, as a subquery.
     *
     * @return Builder<ProductVariant>
     */
    private function cheapestActivePrice(): Builder
    {
        return ProductVariant::query()
            ->selectRaw('min(price)')
            ->whereColumn('product_variants.product_id', 'products.id')
            ->where('is_active', true);
    }

    private function selectedCategory(): ?Category
    {
        if ($this->category === '') {
            return null;
        }

        return Category::query()->where('slug', $this->category)->first(['id', 'name', 'slug']);
    }

    /**
     * The visible products matching every filter but those in `$except`,
     * within the chosen radius; null when a filter names something unknown.
     *
     * @param  list<string>  $except
     * @return Builder<Product>|null
     */
    private function matching(?Category $category, array $except): ?Builder
    {
        $query = Product::query()->visibleInCatalog();

        if (! $this->applyFilters($query, $category, $except)) {
            return null;
        }

        $this->applyRadius($query);

        return $query;
    }

    /**
     * The number of matching products for each value of a column, with every
     * filter but those in `$except`. One query.
     *
     * @param  list<string>  $except
     * @return array<int|string, int>
     */
    private function countBy(string $column, ?Category $category, array $except): array
    {
        $matching = $this->matching($category, $except);

        if ($matching === null) {
            return [];
        }

        return $matching->toBase()
            ->select($column)
            ->selectRaw('count(*) as product_count')
            ->groupBy($column)
            ->pluck('product_count', str_replace('products.', '', $column))
            ->map(fn (mixed $count) => (int) $count)
            ->all();
    }

    /**
     * "Semua" and each condition, with the number of products it would show.
     *
     * @return list<array{value: string, label: string, count: int}>
     */
    private function conditionOptions(?Category $category): array
    {
        $counts = $this->countBy('products.condition', $category, ['condition']);
        $options = [['value' => '', 'label' => 'Semua', 'count' => array_sum($counts)]];

        foreach (ProductCondition::cases() as $condition) {
            $options[] = ['value' => $condition->value, 'label' => $condition->label(), 'count' => $counts[$condition->value] ?? 0];
        }

        return $options;
    }

    /**
     * Each active store, demo stores last, with the number of products it
     * would add and whether it is chosen.
     *
     * @param  Collection<int, Store>  $stores
     * @return list<array{store: Store, count: int, checked: bool}>
     */
    private function storeOptions(?Category $category, Collection $stores): array
    {
        $counts = $this->countBy('products.store_id', $category, ['store']);

        return $stores
            ->map(fn (Store $store) => [
                'store' => $store,
                'count' => $counts[$store->id] ?? 0,
                'checked' => in_array($store->id, $this->storeIds, true),
            ])
            ->values()
            ->all();
    }

    /**
     * The number of matching products in each of eight equal price ranges,
     * from the lowest to the highest starting price, by every filter but the
     * price. One bar when every product costs the same, none when there are
     * no products. One query; ranges are in whole rupiah.
     *
     * @return list<array{from: int, to: int, count: int}>
     */
    private function priceBars(?Category $category): array
    {
        $matching = $this->matching($category, ['price']);

        if ($matching === null) {
            return [];
        }

        $prices = $matching->toBase()->selectSub($this->cheapestActivePrice(), 'price');

        $ranged = DB::query()
            ->fromSub($prices, 'prices')
            ->selectRaw('price, min(price) over () as low, max(price) over () as high');

        // high + 1 keeps the highest price inside the last bar and a single
        // price from dividing by zero.
        $rows = DB::query()
            ->fromSub($ranged, 'ranged')
            ->selectRaw('width_bucket(price::numeric, low::numeric, (high + 1)::numeric, '.self::PRICE_BARS.') as bar, count(*) as product_count, min(low) as low, min(high) as high')
            ->groupBy('bar')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $low = (int) data_get($rows->first(), 'low');
        $high = (int) data_get($rows->first(), 'high');
        $counts = $rows->pluck('product_count', 'bar')->map(fn (mixed $count) => (int) $count);

        if ($low === $high) {
            return [['from' => $low, 'to' => $high, 'count' => $counts->sum()]];
        }

        // A price is in bar i (from 0) when i <= (price - low) * bars / span < i + 1.
        $span = $high + 1 - $low;
        $start = fn (int $bar): int => $low + intdiv($span * $bar + self::PRICE_BARS - 1, self::PRICE_BARS);
        $bars = [];

        foreach (range(0, self::PRICE_BARS - 1) as $bar) {
            $bars[] = ['from' => $start($bar), 'to' => $start($bar + 1) - 1, 'count' => $counts->get($bar + 1, 0)];
        }

        return $bars;
    }

    /**
     * A card for each root category with products matching every filter but
     * the category itself, in admin order: the number of those products,
     * subcategories included, and the first photo of the newest one that has
     * a photo. Three queries, however many categories there are; the
     * category tree is small enough to walk in memory.
     *
     * @return list<array{category: Category, count: int, photo: ProductImage|null}>
     */
    private function categoryCards(): array
    {
        $matching = $this->matching(null, ['category']);

        if ($matching === null) {
            return [];
        }

        /** @var array<int, int> $counts */
        $counts = (clone $matching)->toBase()
            ->select('products.category_id')
            ->selectRaw('count(*) as product_count')
            ->groupBy('products.category_id')
            ->pluck('product_count', 'category_id')
            ->all();

        if ($counts === []) {
            return [];
        }

        // The first photo of the newest product with a photo in each category.
        $photos = ProductImage::query()
            ->join('products', 'products.id', '=', 'product_images.product_id')
            ->whereIn('product_images.product_id', $matching->select('products.id'))
            ->selectRaw('distinct on (products.category_id) product_images.*, products.category_id as card_category_id, products.created_at as card_created_at')
            ->orderBy('products.category_id')
            ->orderByDesc('products.created_at')
            ->orderByDesc('products.id')
            ->orderBy('product_images.sort_order')
            ->orderBy('product_images.id')
            ->get()
            ->sortByDesc('card_created_at');

        $categories = Category::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'parent_id', 'name', 'slug'])
            ->keyBy('id');

        $rootOf = function (int $id) use ($categories): ?Category {
            $category = $categories->get($id);
            $seen = [];

            while ($category !== null && $category->parent_id !== null && ! isset($seen[$category->id])) {
                $seen[$category->id] = true;
                $category = $categories->get($category->parent_id);
            }

            return $category;
        };

        $countByRoot = [];

        foreach ($counts as $categoryId => $count) {
            $root = $rootOf((int) $categoryId);

            if ($root !== null) {
                $countByRoot[$root->id] = ($countByRoot[$root->id] ?? 0) + (int) $count;
            }
        }

        $photoByRoot = [];

        foreach ($photos as $photo) {
            $root = $rootOf((int) $photo->getAttribute('card_category_id'));

            if ($root !== null && ! isset($photoByRoot[$root->id])) {
                $photoByRoot[$root->id] = $photo;
            }
        }

        $cards = [];

        foreach ($categories as $category) {
            if ($category->parent_id === null && isset($countByRoot[$category->id])) {
                $cards[] = ['category' => $category, 'count' => $countByRoot[$category->id], 'photo' => $photoByRoot[$category->id] ?? null];
            }
        }

        return $cards;
    }

    /**
     * The filters in effect, each with the label of its chip. A value that
     * names nothing known (it already empties the list) has no chip.
     *
     * @param  Collection<int, Store>  $stores  the active stores offered in the filter
     * @return list<array{name: string, label: string}>
     */
    private function activeFilters(?Category $category, Collection $stores): array
    {
        $minPrice = $this->price($this->minPrice);
        $maxPrice = $this->price($this->maxPrice);
        $radius = $this->radiusKm();

        $labels = [
            'q' => $this->q !== '' ? '“'.$this->q.'”' : null,
            'category' => $category?->name,
            'condition' => ProductCondition::tryFrom($this->condition)?->label(),
            'preOrder' => $this->preOrder === '1' ? 'Hanya pre order' : null,
            'store' => $this->storeIds !== [] ? $stores->whereIn('id', $this->storeIds)->pluck('name')->join(', ') : null,
            'minPrice' => $minPrice !== null ? 'Mulai Rp'.number_format($minPrice, 0, ',', '.') : null,
            'maxPrice' => $maxPrice !== null ? 'Sampai Rp'.number_format($maxPrice, 0, ',', '.') : null,
            'radius' => $this->isLocated() && $radius !== null ? "Dalam {$radius} km" : null,
        ];

        $filters = [];

        foreach ($labels as $name => $label) {
            if ($label !== null) {
                $filters[] = ['name' => $name, 'label' => $label];
            }
        }

        return $filters;
    }

    /**
     * A whole rupiah amount, or null when the value is not one.
     */
    private function price(string $value): ?int
    {
        return ctype_digit($value) && strlen($value) <= 10 ? (int) $value : null;
    }

    private function isFiltered(): bool
    {
        return $this->q !== ''
            || $this->category !== ''
            || $this->condition !== ''
            || $this->preOrder !== ''
            || $this->store !== ''
            || $this->minPrice !== ''
            || $this->maxPrice !== ''
            || $this->sort !== 'terbaru';
    }

    /**
     * The store slugs from the URL: trimmed, without blanks, repeats, or
     * slugs longer than 100 characters, and at most the first ten.
     *
     * @return list<string>
     */
    private function storeSlugs(): array
    {
        $slugs = [];

        foreach (explode(',', $this->store) as $slug) {
            $slug = trim($slug);

            if ($slug !== '' && mb_strlen($slug) <= self::MAX_SLUG_LENGTH && ! in_array($slug, $slugs, true)) {
                $slugs[] = $slug;
            }
        }

        return array_slice($slugs, 0, self::MAX_STORES);
    }

    private function cutQuery(string $value): string
    {
        return mb_substr(trim($value), 0, self::MAX_QUERY_LENGTH);
    }

    /**
     * Make `%`, `_`, and `\` match themselves instead of acting as wildcards.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
