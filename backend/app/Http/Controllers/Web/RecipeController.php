<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Data\Recipe;
use App\Enums\StoreStatus;
use App\Enums\Unit;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecipeController extends Controller
{
    public const int MAX_QUERY_LENGTH = 100;

    public const int STORES = 3;

    public const int OTHER_RECIPES = 3;

    /**
     * The recipe list: every recipe in its group, or the recipes matching
     * `?q=` by name or ingredient. The static recipes are searched in
     * memory, so there is no query to the database.
     */
    public function index(Request $request): View
    {
        $query = $request->query('q');
        $term = is_string($query) ? mb_substr(trim($query), 0, self::MAX_QUERY_LENGTH) : '';

        $recipes = $term === '' ? Recipe::all() : Recipe::search($term);

        $groups = [];

        foreach (Recipe::GROUPS as $id => $group) {
            $inGroup = array_values(array_filter($recipes, fn (Recipe $recipe): bool => $recipe->group === $id));

            if ($inGroup !== []) {
                $groups[$id] = [...$group, 'recipes' => $inGroup];
            }
        }

        return view('recipes.index', [
            'term' => $term,
            'groups' => $groups,
            'total' => count($recipes),
        ]);
    }

    /**
     * A recipe with the stores selling the seafood it needs and what that
     * costs for the servings the buyer picks, swaps for the seafood, and other
     * recipes of the same kind. Amounts and totals for 1 to 12 servings are
     * worked out here, in whole numbers, so the page only switches between
     * them. Only public store columns are loaded.
     */
    public function show(string $slug): View
    {
        $recipe = Recipe::find($slug) ?? abort(404);

        $categories = Category::query()
            ->whereIn('slug', [$recipe->needs['category'], ...$recipe->swaps])
            ->get(['id', 'name', 'slug'])
            ->keyBy('slug');

        $needed = $categories->get($recipe->needs['category']);
        $stores = $needed === null ? new Collection : $this->stores($needed);
        $cheapestRealId = $stores->where('is_demo', false)->sortBy('from_price')->first()?->id;

        return view('recipes.show', [
            'recipe' => $recipe,
            'needed' => $needed,
            'stores' => $stores->map(fn (Store $store): array => [
                'store' => $store,
                'cheapest' => $store->id === $cheapestRealId,
                'totals' => Recipe::byServings(fn (int $servings): string => self::rupiah($recipe->seafoodCost((int) $store->getAttribute('from_price'), $servings))),
            ]),
            'swaps' => $this->swaps($recipe, $categories->reject(fn (Category $category): bool => $category->slug === $recipe->needs['category'])),
            'others' => array_slice(array_values(array_filter(
                Recipe::inCategory($recipe->category),
                fn (Recipe $other): bool => $other->slug !== $recipe->slug,
            )), 0, self::OTHER_RECIPES),
            'jsonLd' => $this->jsonLd($recipe),
        ]);
    }

    /**
     * Up to three active stores selling a visible product in the category,
     * each with its cheapest active variant sold by the kilo there as
     * `from_price`, `stock_left`, and `checked_at`: real stores first, then
     * cheapest first. One query, however many stores sell it.
     *
     * @return Collection<int, Store>
     */
    private function stores(Category $category): Collection
    {
        // One row per store: its cheapest kilo variant in this category (Postgres DISTINCT ON).
        $cheapest = ProductVariant::query()
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('product_variants.is_active', true)
            ->where('product_variants.unit', Unit::Kg)
            ->where('products.category_id', $category->id)
            ->whereIn('products.id', Product::query()->visibleInCatalog()->select('id'))
            ->selectRaw('distinct on (products.store_id) products.store_id, product_variants.price, product_variants.stock, product_variants.stock_confirmed_at')
            ->orderBy('products.store_id')
            ->orderBy('product_variants.price')
            ->orderBy('product_variants.id');

        return Store::query()
            ->joinSub($cheapest, 'cheapest', 'cheapest.store_id', '=', 'stores.id')
            ->where('stores.status', StoreStatus::Active)
            ->select(['stores.id', 'stores.name', 'stores.slug', 'stores.city', 'stores.is_demo', 'cheapest.price as from_price', 'cheapest.stock as stock_left', 'cheapest.stock_confirmed_at as checked_at'])
            ->withCasts(['from_price' => 'integer', 'checked_at' => 'datetime'])
            ->orderBy('stores.is_demo')
            ->orderBy('cheapest.price')
            ->orderBy('stores.id')
            ->limit(self::STORES)
            ->get();
    }

    /**
     * Each swap category someone sells by the kilo, with the starting price
     * for the same amount of seafood. One query for all of them.
     *
     * @param  Collection<string, Category>  $categories
     * @return list<array{name: string, slug: string, price: string}>
     */
    private function swaps(Recipe $recipe, Collection $categories): array
    {
        if ($categories->isEmpty()) {
            return [];
        }

        $prices = ProductVariant::query()
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('product_variants.is_active', true)
            ->where('product_variants.unit', Unit::Kg)
            ->whereIn('products.category_id', $categories->pluck('id'))
            ->whereIn('products.id', Product::query()->visibleInCatalog()->select('id'))
            ->groupBy('products.category_id')
            ->selectRaw('products.category_id, min(product_variants.price) as from_price')
            ->pluck('from_price', 'category_id');

        $swaps = [];

        foreach ($categories as $category) {
            if ($prices->has($category->id)) {
                $swaps[] = [
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'price' => self::rupiah($recipe->seafoodCost((int) $prices[$category->id], $recipe->servings)),
                ];
            }
        }

        return $swaps;
    }

    /**
     * The schema.org Recipe for search results.
     *
     * @return array<string, mixed>
     */
    private function jsonLd(Recipe $recipe): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Recipe',
            'name' => $recipe->name,
            'description' => $recipe->description,
            'image' => asset($recipe->photo),
            'totalTime' => 'PT'.$recipe->minutes.'M',
            'recipeYield' => $recipe->servings.' porsi',
            'recipeIngredient' => array_map(
                fn (array $ingredient): string => trim(($ingredient['amount'] === null ? '' : Recipe::formatAmount($ingredient['amount']).' '.$ingredient['unit'].' ').$ingredient['name']),
                $recipe->ingredients,
            ),
            'recipeInstructions' => array_map(fn (string $step): array => ['@type' => 'HowToStep', 'text' => $step], $recipe->steps),
        ];
    }

    /**
     * Whole rupiah as on the price tags: "Rp185.000".
     */
    private static function rupiah(int $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
