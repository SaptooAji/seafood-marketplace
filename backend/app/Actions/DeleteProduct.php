<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Product;

/**
 * Removes a product from the store and the catalog.
 */
class DeleteProduct
{
    /**
     * Soft delete: variants, photos, and the stock book stay, so the product
     * can be restored and past orders keep their references.
     */
    public function handle(Product $product): void
    {
        $product->delete();
    }
}
