<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\StoreStatus;
use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Contracts\View\View;

class StoreController extends Controller
{
    public const int PER_PAGE = 24;

    /**
     * The public page of an active store with its products, newest first.
     * Any other store is a 404, and only public columns are loaded, so bank
     * details never reach the view.
     */
    public function __invoke(string $slug): View
    {
        $store = Store::query()
            ->where('status', StoreStatus::Active)
            ->where('slug', $slug)
            ->firstOrFail(['id', 'name', 'slug', 'description', 'phone', 'address', 'city', 'lat', 'lng', 'logo']);

        $products = $store->products()
            ->visibleInCatalog()
            ->withCardDetails()
            ->latest()
            ->latest('id')
            ->paginate(self::PER_PAGE, ['id', 'store_id', 'name', 'slug', 'created_at']);

        return view('stores.show', [
            'store' => $store,
            'products' => $products,
            'jsonLd' => $this->jsonLd($store),
        ]);
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
            'telephone' => $store->internationalPhone(),
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
