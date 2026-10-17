<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Data\Recipe;
use App\Enums\StoreStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

class HomeController extends Controller
{
    public const int LATEST_PRODUCTS = 8;

    public const int ACTIVE_STORES = 6;

    public const int RECIPE_PICKS = 4;

    /**
     * The home page:
     * - a hero with the highlights from config/home.php, each over a photo
     *   with a card of its product, and up to four featured recipes;
     * - the products buyers can see, most recently checked first, each with
     *   its first photo, cheapest active variant, and root category for the
     *   chips;
     * - the most recently verified stores, never a demo store.
     *
     * Products of real stores come before those of demo stores. Only the
     * public store columns are loaded, so bank details never reach the view.
     */
    public function __invoke(): View
    {
        $products = Product::query()
            ->select(['id', 'store_id', 'category_id', 'name', 'slug', 'created_at'])
            ->visibleInCatalog()
            ->withCardDetails()
            ->withLastCheckedAt()
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
            'highlights' => $this->highlights(),
            'fallbackPhoto' => asset(config()->string('home.fallback_photo')),
            'recipes' => array_slice(Recipe::featured(), 0, self::RECIPE_PICKS),
            'rootSlugs' => $rootSlugs,
            'chips' => $chips,
            'stores' => $stores,
        ]);
    }

    /**
     * The highlights whose product buyers can see, in config order, each with
     * its product and background photo. One query for all of their products.
     *
     * @return list<array{title: string, text: string, button: string, photo: string, product: Product}>
     */
    private function highlights(): array
    {
        /** @var list<array{product: string, title: string, text: string, button: string, photo: string|null}> $highlights */
        $highlights = config()->array('home.highlights');

        if ($highlights === []) {
            return [];
        }

        $products = Product::query()
            ->select(['id', 'store_id', 'name', 'slug'])
            ->visibleInCatalog()
            ->withCardDetails()
            ->withLastCheckedAt()
            ->whereIn('slug', array_column($highlights, 'product'))
            ->get()
            ->keyBy('slug');

        $shown = [];

        foreach ($highlights as $highlight) {
            $product = $products->get($highlight['product']);
            $photo = $highlight['photo'] !== null ? asset($highlight['photo']) : $product?->images->first()?->url();

            if ($product !== null && $photo !== null) {
                $shown[] = [...$highlight, 'photo' => $photo, 'product' => $product];
            }
        }

        return $shown;
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
}
