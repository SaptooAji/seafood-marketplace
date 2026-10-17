<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\ProductCondition;
use App\Enums\StoreStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

class HomeController extends Controller
{
    public const int LATEST_PRODUCTS = 8;

    public const int HERO_PRODUCTS = 5;

    public const int ACTIVE_STORES = 6;

    /**
     * The home page:
     * - a hero with the recently checked products that have a photo, and
     *   counts of freshly checked products, active stores, and products per
     *   condition;
     * - the products buyers can see, most recently checked first, each with
     *   its first photo, cheapest active variant, and root category for the
     *   chips;
     * - the most recently verified stores, never a demo store.
     *
     * Products of real stores come before those of demo stores.
     *
     * Only the public store columns are loaded, so bank details never reach
     * the view.
     */
    public function __invoke(): View
    {
        $products = Product::query()
            ->select(['id', 'store_id', 'category_id', 'name', 'slug', 'created_at'])
            ->visibleInCatalog()
            ->withCardDetails()
            ->withMax(['variants as last_checked_at' => fn (Builder $variant) => $variant->where('is_active', true)], 'stock_confirmed_at')
            ->withCasts(['last_checked_at' => 'datetime'])
            ->realStoresFirst()
            ->orderByRaw('last_checked_at desc nulls last')
            ->latest()
            ->latest('id')
            ->limit(self::LATEST_PRODUCTS)
            ->get();

        [$rootSlugs, $chips] = $this->rootCategories($products);

        $stores = Store::query()
            ->where('status', StoreStatus::Active)
            ->real()
            ->latest('verified_at')
            ->latest('id')
            ->limit(self::ACTIVE_STORES)
            ->get(['id', 'name', 'slug', 'city']);

        return view('home', [
            'products' => $products,
            'heroProducts' => $products->filter(fn (Product $product) => $product->images->isNotEmpty())->take(self::HERO_PRODUCTS)->values(),
            'rootSlugs' => $rootSlugs,
            'chips' => $chips,
            'freshCount' => $this->freshlyCheckedCount(),
            'activeStoreCount' => Store::query()->where('status', StoreStatus::Active)->count(),
            'conditionCounts' => $this->conditionCounts(),
            'stores' => $stores,
        ]);
    }

    /**
     * The root category slug of each listed product, and the chips: the root
     * categories of those products in their admin order. One query; the
     * category tree is small enough to walk in memory.
     *
     * @param  Collection<int, Product>  $products
     * @return array{0: array<int, string>, 1: BaseCollection<int, Category>}
     */
    private function rootCategories(Collection $products): array
    {
        if ($products->isEmpty()) {
            return [[], collect()];
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

        $slugs = [];
        $rootIds = [];

        foreach ($products as $product) {
            $root = $rootOf($product->category_id);

            if ($root !== null) {
                $slugs[$product->id] = $root->slug;
                $rootIds[$root->id] = true;
            }
        }

        $chips = $categories->filter(fn (Category $category) => isset($rootIds[$category->id]))->values();

        // Chips only help when there is more than one category to choose from.
        return [$slugs, $chips->count() > 1 ? $chips : collect()];
    }

    /**
     * Visible products with an active variant whose stock was checked within
     * `catalog.stale_stock_hours`, the same window that decides the stock
     * warning on the product page.
     */
    private function freshlyCheckedCount(): int
    {
        $since = now()->subHours(config()->integer('catalog.stale_stock_hours'));

        return Product::query()
            ->visibleInCatalog()
            ->whereHas('variants', fn (Builder $variant) => $variant->where('is_active', true)->where('stock_confirmed_at', '>=', $since))
            ->count();
    }

    /**
     * The number of visible products per condition, in the enum's order,
     * leaving out conditions without products.
     *
     * @return array<string, array{condition: ProductCondition, count: int}>
     */
    private function conditionCounts(): array
    {
        $counts = Product::query()
            ->visibleInCatalog()
            ->toBase()
            ->selectRaw('condition, count(*) as aggregate')
            ->groupBy('condition')
            ->pluck('aggregate', 'condition');

        $result = [];

        foreach (ProductCondition::cases() as $condition) {
            $count = (int) ($counts[$condition->value] ?? 0);

            if ($count > 0) {
                $result[$condition->value] = ['condition' => $condition, 'count' => $count];
            }
        }

        return $result;
    }
}
