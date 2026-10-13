<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\StoreStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    public const int LATEST_PRODUCTS = 8;

    public const int ACTIVE_STORES = 6;

    /**
     * The home page: the newest products buyers can see, each with its first
     * photo and cheapest active variant, and the most recently verified stores.
     * Only the public store columns are loaded, so bank details never reach
     * the view.
     */
    public function __invoke(): View
    {
        $products = Product::query()
            ->visibleInCatalog()
            ->withCardDetails()
            ->latest()
            ->latest('id')
            ->limit(self::LATEST_PRODUCTS)
            ->get(['id', 'store_id', 'name', 'slug', 'created_at']);

        $stores = Store::query()
            ->where('status', StoreStatus::Active)
            ->latest('verified_at')
            ->latest('id')
            ->limit(self::ACTIVE_STORES)
            ->get(['id', 'name', 'slug', 'city']);

        return view('home', ['products' => $products, 'stores' => $stores]);
    }
}
