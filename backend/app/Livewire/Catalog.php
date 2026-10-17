<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\ProductCondition;
use App\Enums\StoreStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\View\Components\Layouts\App as Layout;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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

    private const array FILTERS = ['q', 'category', 'condition', 'store', 'minPrice', 'maxPrice', 'sort', 'radius'];

    /**
     * The filters a chip can remove: all but the sort, which is a choice of
     * order rather than a filter.
     */
    private const array REMOVABLE_FILTERS = ['q', 'category', 'condition', 'store', 'minPrice', 'maxPrice', 'radius'];

    #[Url(except: '')]
    public string $q = '';

    #[Url(as: 'kategori', except: '')]
    public string $category = '';

    #[Url(as: 'kondisi', except: '')]
    public string $condition = '';

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

    public function mount(): void
    {
        $this->q = $this->cutQuery($this->q);

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
        $stores = Store::query()->where('status', StoreStatus::Active)->orderBy('name')->get(['id', 'name', 'slug']);

        return view('livewire.catalog', [
            'products' => $this->products($category),
            'located' => $this->isLocated(),
            'radii' => self::RADII,
            'categories' => $this->categoryOptions(),
            'stores' => $stores,
            'activeFilters' => $this->activeFilters($category, $stores),
            'filtered' => $this->isFiltered() || $this->radius !== '',
            'conditions' => ProductCondition::cases(),
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
     * Narrow the query by each filter that is set.
     *
     * @param  Builder<Product>  $query
     * @return bool false when a filter names something unknown, so nothing matches
     */
    private function applyFilters(Builder $query, ?Category $category): bool
    {
        if ($this->q !== '') {
            $query->whereLike('products.name', '%'.$this->escapeLike($this->q).'%');
        }

        if ($this->category !== '') {
            if ($category === null) {
                return false;
            }

            $query->whereIn('products.category_id', [$category->id, ...$category->descendantIds()]);
        }

        if ($this->condition !== '') {
            $condition = ProductCondition::tryFrom($this->condition);

            if ($condition === null) {
                return false;
            }

            $query->where('products.condition', $condition);
        }

        if ($this->store !== '') {
            $storeId = Store::query()->where('status', StoreStatus::Active)->where('slug', $this->store)->value('id');

            if ($storeId === null) {
                return false;
            }

            $query->where('products.store_id', $storeId);
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

        if (in_array((int) $this->radius, self::RADII, true) && ctype_digit($this->radius)) {
            $query->where('store_distances.distance_km', '<=', (int) $this->radius);
        }
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
     * Root categories with their children for the category filter.
     *
     * @return Collection<int, Category>
     */
    private function categoryOptions(): Collection
    {
        return Category::query()
            ->whereNull('parent_id')
            ->with(['children' => fn ($children) => $children->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
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
        $radius = (int) $this->radius;

        $labels = [
            'q' => $this->q !== '' ? '“'.$this->q.'”' : null,
            'category' => $category?->name,
            'condition' => ProductCondition::tryFrom($this->condition)?->label(),
            'store' => $this->store !== '' ? $stores->firstWhere('slug', $this->store)?->name : null,
            'minPrice' => $minPrice !== null ? 'Mulai Rp'.number_format($minPrice, 0, ',', '.') : null,
            'maxPrice' => $maxPrice !== null ? 'Sampai Rp'.number_format($maxPrice, 0, ',', '.') : null,
            'radius' => $this->isLocated() && ctype_digit($this->radius) && in_array($radius, self::RADII, true) ? "Dalam {$radius} km" : null,
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
            || $this->store !== ''
            || $this->minPrice !== ''
            || $this->maxPrice !== ''
            || $this->sort !== 'terbaru';
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
