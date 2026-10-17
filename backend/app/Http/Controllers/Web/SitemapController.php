<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\StoreStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * The XML sitemap: the home page, the catalog, every product buyers can
     * see, and every active store. Demo stores and their products are left
     * out. Only slugs and dates are loaded, read in chunks so memory stays
     * small as the catalog grows.
     */
    public function __invoke(): Response
    {
        return response()
            ->view('sitemap', [
                'products' => Product::query()
                    ->visibleInCatalog()
                    ->whereHas('store', fn (Builder $store) => $store->real())
                    ->select(['id', 'slug', 'updated_at'])
                    ->lazy(),
                'stores' => Store::query()->where('status', StoreStatus::Active)->real()->select(['id', 'slug'])->lazy(),
            ])
            ->header('Content-Type', 'application/xml');
    }
}
