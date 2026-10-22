<?php

declare(strict_types=1);

namespace App\Actions;

use App\Exceptions\InvalidProductData;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * Removes a variant from its product. A product always keeps at least one
 * variant, because a product without one has no price and cannot be bought.
 * Inactive variants count; the whole product is deleted with DeleteProduct.
 */
class DeleteVariant
{
    /**
     * Soft delete: the stock book keeps pointing at the variant, and its name
     * may be used again for a new variant. The product row is locked before
     * counting, so two requests deleting the last two variants at once take
     * turns and the second one is refused.
     *
     * @throws InvalidProductData on `variant` when it is the product's last variant
     */
    public function handle(ProductVariant $variant): void
    {
        DB::transaction(function () use ($variant): void {
            Product::query()->whereKey($variant->product_id)->lockForUpdate()->first();

            $hasOthers = ProductVariant::query()
                ->where('product_id', $variant->product_id)
                ->whereKeyNot($variant->id)
                ->exists();

            if (! $hasOthers) {
                throw new InvalidProductData('variant', 'Produk perlu minimal satu ukuran. Matikan produk kalau sedang tidak dijual.');
            }

            $variant->delete();
        });
    }
}
