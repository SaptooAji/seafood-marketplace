<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ProductVariant;

/**
 * Removes a variant from its product.
 */
class DeleteVariant
{
    /**
     * Soft delete: the stock book keeps pointing at the variant, and its name
     * may be used again for a new variant.
     */
    public function handle(ProductVariant $variant): void
    {
        $variant->delete();
    }
}
