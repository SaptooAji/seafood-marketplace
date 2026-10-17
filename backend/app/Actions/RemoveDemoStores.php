<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\StoreRole;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Takes the demo stores ("Toko contoh") out again once there are enough real
 * stores: the stores, their products with variants, stock book, and photos,
 * and the owner accounts made for them. Every query is limited to
 * `is_demo` stores, so a real store is never touched (CONF-7).
 */
class RemoveDemoStores
{
    /**
     * @return array{stores: int, products: int, owners: int}
     */
    public function handle(): array
    {
        $photoPaths = [];

        $counts = DB::transaction(function () use (&$photoPaths): array {
            $storeIds = Store::query()->demo()->pluck('id');
            $productIds = Product::withTrashed()->whereIn('store_id', $storeIds)->pluck('id');

            $photoPaths = ProductImage::query()->whereIn('product_id', $productIds)->get()
                ->flatMap(fn (ProductImage $image): array => [$image->path, $image->thumbnailPath()])
                ->all();

            // Owners of demo stores who belong to no real store and hold no platform role.
            $ownerIds = User::query()
                ->whereHas('stores', fn ($store) => $store->whereIn('stores.id', $storeIds)->where('store_members.role', StoreRole::Owner->value))
                ->whereDoesntHave('stores', fn ($store) => $store->where('stores.is_demo', false))
                ->whereDoesntHave('roles')
                ->pluck('id');

            // The stock book blocks deleting a variant, so it goes first.
            StockMovement::query()->whereHas('variant', fn ($variant) => $variant->withTrashed()->whereIn('product_id', $productIds))->delete();
            Product::withTrashed()->whereKey($productIds)->forceDelete();
            Store::query()->whereKey($storeIds)->delete();
            User::query()->whereKey($ownerIds)->delete();

            return ['stores' => $storeIds->count(), 'products' => $productIds->count(), 'owners' => $ownerIds->count()];
        });

        // Files go only after the rows are gone for good.
        Storage::disk('public')->delete($photoPaths);

        return $counts;
    }
}
