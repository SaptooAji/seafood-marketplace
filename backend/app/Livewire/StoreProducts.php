<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The products of a store on its page, newest first, with a tab per root
 * category. Choosing a tab or a page swaps only this list; both stay in the
 * URL (`?kategori=`, `?page=`) so the link can be shared, and every tab and
 * page is a real link that works without JavaScript. The rest of the store
 * page is not rendered again.
 *
 * @property-read list<array{slug: string, name: string, count: int, categoryIds: list<int>}> $tabs
 */
class StoreProducts extends Component
{
    use WithPagination;

    public const int PER_PAGE = 24;

    #[Locked]
    public Store $store;

    /**
     * The slug of the chosen root category, or empty for every product.
     */
    #[Url(as: 'kategori', except: '')]
    public string $category = '';

    public function mount(): void
    {
        $this->category = $this->offered($this->category);
    }

    /**
     * Show the products of one root category, or of all with an empty slug.
     * A slug the store does not sell as a root category shows all.
     */
    public function chooseCategory(string $slug): void
    {
        $this->category = $this->offered($slug);
        $this->resetPage();
    }

    /**
     * A tab per root category the store sells in, in admin order, with its
     * number of products buyers can see and the ids of the category and its
     * subcategories. Two queries; the category tree is small enough to walk
     * in memory.
     *
     * @return list<array{slug: string, name: string, count: int, categoryIds: list<int>}>
     */
    #[Computed]
    public function tabs(): array
    {
        /** @var array<int, int> $counts */
        $counts = $this->store->products()
            ->visibleInCatalog()
            ->toBase()
            ->select('category_id')
            ->selectRaw('count(*) as product_count')
            ->groupBy('category_id')
            ->pluck('product_count', 'category_id')
            ->all();

        if ($counts === []) {
            return [];
        }

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

        $idsByRoot = [];

        foreach ($categories as $category) {
            $root = $rootOf($category->id);

            if ($root !== null) {
                $idsByRoot[$root->id][] = $category->id;
            }
        }

        $tabs = [];

        foreach ($categories as $category) {
            if ($category->parent_id === null && isset($countByRoot[$category->id])) {
                $tabs[] = ['slug' => $category->slug, 'name' => $category->name, 'count' => $countByRoot[$category->id], 'categoryIds' => $idsByRoot[$category->id]];
            }
        }

        return $tabs;
    }

    public function render(): View
    {
        $tabs = $this->tabs;

        return view('livewire.store-products', [
            'tabs' => $tabs,
            'total' => array_sum(array_column($tabs, 'count')),
            'products' => $this->products($tabs),
        ]);
    }

    /**
     * @param  list<array{slug: string, name: string, count: int, categoryIds: list<int>}>  $tabs
     * @return LengthAwarePaginator<int, Product>
     */
    private function products(array $tabs): LengthAwarePaginator
    {
        $chosen = collect($tabs)->firstWhere('slug', $this->category);

        return $this->store->products()
            ->visibleInCatalog()
            ->withCardDetails()
            ->when($chosen !== null, fn ($query) => $query->whereIn('category_id', $chosen['categoryIds'] ?? []))
            ->latest()
            ->latest('id')
            ->paginate(self::PER_PAGE, ['id', 'store_id', 'name', 'slug', 'created_at'])
            // Livewire's path is relative to its own request; the links must
            // still lead to this store page without JavaScript.
            ->withPath(route('stores.show', $this->store->slug))
            ->appends(array_filter(['kategori' => $this->category]));
    }

    /**
     * The slug when it names a tab, else empty.
     */
    private function offered(string $slug): string
    {
        return collect($this->tabs)->contains('slug', $slug) ? $slug : '';
    }
}
