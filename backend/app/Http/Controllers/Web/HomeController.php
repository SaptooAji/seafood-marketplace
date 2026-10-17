<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Data\Recipe;
use App\Enums\SalesMode;
use App\Enums\StoreStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Support\StockCheckTime;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

class HomeController extends Controller
{
    public const int LATEST_PRODUCTS = 8;

    public const int ACTIVE_STORES = 6;

    public const int RECIPE_PICKS = 4;

    public const int PRE_ORDER_DAYS = 3;

    public const int PRE_ORDERS_PER_DAY = 6;

    /**
     * Pre-order products looked at for the band; enough to fill each day.
     */
    public const int PRE_ORDER_CANDIDATES = 24;

    /**
     * The home page:
     * - a hero with the highlights from config/home.php, each over a photo
     *   with a card of its product, and up to four featured recipes;
     * - the products buyers can see, most recently checked first, each with
     *   its first photo, cheapest active variant, and root category for the
     *   chips;
     * - photo tiles for shopping needs, from config/home.php;
     * - the pre-order products deliverable on each of the next three days;
     * - the most recently verified stores with their number of products,
     *   never a demo store.
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
            ->withCount(['products as product_count' => fn (Builder $product) => $product->whereIn('products.id', Product::query()->visibleInCatalog()->select('id'))])
            ->limit(self::ACTIVE_STORES)
            ->get(['id', 'name', 'slug', 'city', 'logo']);

        return view('home', [
            'products' => $products,
            'highlights' => $this->highlights(),
            'fallbackPhoto' => asset(config()->string('home.fallback_photo')),
            'recipes' => array_slice(Recipe::featured(), 0, self::RECIPE_PICKS),
            'needs' => config()->array('home.needs'),
            'preOrderDays' => $this->preOrderDays(),
            'rootSlugs' => $rootSlugs,
            'chips' => $chips,
            'stores' => $stores,
        ]);
    }

    /**
     * The next three days, tomorrow first, each with up to six pre-order
     * products deliverable that day and when to order them by. Empty when no
     * pre-order product can be delivered in that time. One query.
     *
     * @return list<array{date: CarbonImmutable, label: string, products: list<array{product: Product, deadline: string}>}>
     */
    private function preOrderDays(): array
    {
        $now = now();
        $today = CarbonImmutable::now(StockCheckTime::TIMEZONE)->startOfDay();

        $products = Product::query()
            ->select(['id', 'store_id', 'name', 'slug', 'sales_mode', 'daily_quota', 'lead_time_days', 'cutoff_time', 'created_at'])
            ->visibleInCatalog()
            ->where('sales_mode', SalesMode::PreOrder)
            ->withCardDetails()
            ->realStoresFirst()
            ->latest()
            ->latest('id')
            ->limit(self::PRE_ORDER_CANDIDATES)
            ->get();

        $days = [];

        foreach (range(1, self::PRE_ORDER_DAYS) as $offset) {
            $date = $today->addDays($offset);
            $deliverable = [];

            foreach ($products as $product) {
                $earliest = $product->earliestDeliveryDate($now);

                if ($earliest !== null && $earliest->lte($date) && count($deliverable) < self::PRE_ORDERS_PER_DAY) {
                    $deliverable[] = ['product' => $product, 'deadline' => self::deadline($product->orderDeadlineFor($date), $today)];
                }
            }

            $days[] = [
                'date' => $date,
                'label' => match ($offset) {
                    1 => 'Besok',
                    2 => 'Lusa',
                    default => $date->locale('id')->translatedFormat('l'),
                },
                'products' => $deliverable,
            ];
        }

        $hasAny = array_filter($days, fn (array $day): bool => $day['products'] !== []) !== [];

        return $hasAny ? $days : [];
    }

    /**
     * "15.00 hari ini", "15.00 besok", or "15.00 Sabtu".
     */
    private static function deadline(CarbonImmutable $deadline, CarbonImmutable $today): string
    {
        $day = match ((int) $today->diffInDays($deadline->startOfDay())) {
            0 => 'hari ini',
            1 => 'besok',
            default => $deadline->locale('id')->translatedFormat('l'),
        };

        return $deadline->format('H.i').' '.$day;
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
