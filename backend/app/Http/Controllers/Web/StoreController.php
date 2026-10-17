<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\ShippingMethodType;
use App\Enums\StoreStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\StoreShippingMethod;
use App\Support\StockCheckTime;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class StoreController extends Controller
{
    public const int PER_PAGE = 24;

    public const int NEARBY_STORES = 3;

    public const int NEARBY_PRODUCTS = 3;

    /**
     * The public page of an active store: a cover from its newest product
     * photo, the identity card with how fresh its stock is, the products
     * newest first with a tab per root category (`?kategori=`), and up to
     * three other stores nearest to this one.
     *
     * Any other store is a 404, and only public columns are loaded, so bank
     * details never reach the view. Distances are from this store, never
     * from the buyer.
     */
    public function __invoke(Request $request, string $slug): View
    {
        $store = Store::query()
            ->where('status', StoreStatus::Active)
            ->where('slug', $slug)
            ->firstOrFail(['id', 'name', 'slug', 'description', 'phone', 'address', 'city', 'lat', 'lng', 'logo', 'is_demo']);

        $tabs = $this->categoryTabs($store);
        $chosen = $request->query('kategori');
        $chosenTab = is_string($chosen) ? collect($tabs)->firstWhere('slug', $chosen) : null;

        $products = $store->products()
            ->visibleInCatalog()
            ->withCardDetails()
            ->when($chosenTab !== null, fn (Builder $query) => $query->whereIn('category_id', $chosenTab['categoryIds'] ?? []))
            ->latest()
            ->latest('id')
            ->paginate(self::PER_PAGE, ['id', 'store_id', 'name', 'slug', 'created_at'])
            ->withQueryString();

        $nearby = $this->nearbyStores($store);

        return view('stores.show', [
            'store' => $store,
            'products' => $products,
            'tabs' => $tabs,
            'chosenTab' => $chosenTab['slug'] ?? null,
            'productCount' => array_sum(array_column($tabs, 'count')),
            'cover' => $this->cover($store),
            'checkedAt' => $this->lastCheckedAt($store),
            'receives' => $this->receives($store),
            'directionsUrl' => "https://www.google.com/maps/dir/?api=1&destination={$store->lat},{$store->lng}",
            'nearby' => $nearby,
            'nearbyProducts' => $this->nearbyProducts($nearby),
            'jsonLd' => $this->jsonLd($store),
        ]);
    }

    /**
     * A tab per root category the store sells in, in admin order, with its
     * number of products buyers can see and the ids of the category and its
     * subcategories. Two queries; the category tree is small enough to walk
     * in memory.
     *
     * @return list<array{slug: string, name: string, count: int, categoryIds: list<int>}>
     */
    private function categoryTabs(Store $store): array
    {
        /** @var array<int, int> $counts */
        $counts = $store->products()
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

    /**
     * The first photo of the newest product buyers can see, or null for the
     * plain band.
     */
    private function cover(Store $store): ?ProductImage
    {
        return ProductImage::query()
            ->join('products', 'products.id', '=', 'product_images.product_id')
            ->whereIn('product_images.product_id', $store->products()->visibleInCatalog()->select('products.id'))
            ->orderByDesc('products.created_at')
            ->orderByDesc('products.id')
            ->orderBy('product_images.sort_order')
            ->orderBy('product_images.id')
            ->first(['product_images.*']);
    }

    /**
     * When the store last confirmed a stock: "06.30" today in WIB, "14 Okt"
     * before; null when it never did. Only active variants of products
     * buyers can see count.
     */
    private function lastCheckedAt(Store $store): ?string
    {
        $latest = ProductVariant::query()
            ->where('is_active', true)
            ->whereIn('product_id', $store->products()->visibleInCatalog()->select('products.id'))
            ->max('stock_confirmed_at');

        return $latest === null ? null : StockCheckTime::format(Carbon::parse($latest));
    }

    /**
     * How the store takes orders, from its active shipping methods.
     */
    private function receives(Store $store): string
    {
        $types = StoreShippingMethod::query()
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->pluck('type');

        $pickup = $types->contains(ShippingMethodType::Pickup);
        $delivery = $types->contains(fn (ShippingMethodType $type): bool => $type !== ShippingMethodType::Pickup);

        return match (true) {
            $pickup && $delivery => 'Antar atau ambil',
            $pickup => 'Hanya ambil di lapak',
            $delivery => 'Hanya diantar',
            default => 'Tanyakan ke penjual',
        };
    }

    /**
     * Up to three other active stores selling something buyers can see,
     * real stores first, then nearest to this store first, each with
     * `distance_km` and `product_count`. Only public columns.
     *
     * @return Collection<int, Store>
     */
    private function nearbyStores(Store $store): Collection
    {
        $visible = Product::query()->visibleInCatalog();

        return Store::query()
            ->select(['stores.id', 'stores.name', 'stores.slug', 'stores.city', 'stores.logo', 'stores.is_demo'])
            ->withDistanceFrom((string) $store->lat, (string) $store->lng)
            ->where('stores.status', StoreStatus::Active)
            ->whereKeyNot($store->id)
            ->whereIn('stores.id', (clone $visible)->select('products.store_id'))
            ->withCount(['products as product_count' => fn (Builder $products) => $products->whereIn('products.id', (clone $visible)->select('products.id'))])
            ->orderBy('stores.is_demo')
            ->orderBy('distance_km')
            ->limit(self::NEARBY_STORES)
            ->get();
    }

    /**
     * The newest three products buyers can see of each nearby store, keyed
     * by store id. One query for all of them, plus the card details.
     *
     * @param  Collection<int, Store>  $stores
     * @return array<int, Collection<int, Product>>
     */
    private function nearbyProducts(Collection $stores): array
    {
        if ($stores->isEmpty()) {
            return [];
        }

        $ranked = Product::query()
            ->visibleInCatalog()
            ->whereIn('products.store_id', $stores->modelKeys())
            ->select('products.id')
            ->selectRaw('row_number() over (partition by products.store_id order by products.created_at desc, products.id desc) as position');

        return Product::query()
            ->withCardDetails()
            ->joinSub($ranked, 'ranked', 'ranked.id', '=', 'products.id')
            ->where('ranked.position', '<=', self::NEARBY_PRODUCTS)
            ->orderBy('ranked.position')
            ->get(['products.id', 'products.store_id', 'products.name', 'products.slug'])
            ->groupBy('store_id')
            ->all();
    }

    /**
     * The schema.org Store for search results. Address, phone, and location
     * are public for an active store; bank details are never included.
     *
     * @return array<string, mixed>
     */
    private function jsonLd(Store $store): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Store',
            'name' => $store->name,
            'description' => $store->description,
            'url' => route('stores.show', $store->slug),
            'image' => $store->logoUrl(),
            // A demo store is an example: its phone is never published.
            'telephone' => $store->is_demo ? null : $store->internationalPhone(),
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $store->address,
                'addressLocality' => $store->city,
                'addressCountry' => 'ID',
            ],
            'geo' => [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $store->lat,
                'longitude' => (float) $store->lng,
            ],
        ], fn (mixed $value): bool => $value !== null);
    }
}
