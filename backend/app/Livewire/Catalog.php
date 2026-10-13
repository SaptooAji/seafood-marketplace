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

    private const array FILTERS = ['q', 'category', 'condition', 'store', 'minPrice', 'maxPrice', 'sort'];

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

    public function mount(): void
    {
        $this->q = $this->cutQuery($this->q);
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

        return view('livewire.catalog', [
            'products' => $this->products($category),
            'categories' => $this->categoryOptions(),
            'stores' => Store::query()->where('status', StoreStatus::Active)->orderBy('name')->get(['id', 'name', 'slug']),
            'conditions' => ProductCondition::cases(),
            'sorts' => self::SORTS,
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

        match ($this->sort) {
            'termurah' => $query->orderBy('min_price'),
            'termahal' => $query->orderByDesc('min_price'),
            default => $query->latest(),
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
