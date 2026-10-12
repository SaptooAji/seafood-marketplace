<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Product;

/**
 * An admin lifts a block.
 */
class UnblockProduct
{
    /**
     * Clear the block and its reason. The product is back in the catalog only
     * if the seller still has it switched on. Unblocking a product that is not
     * blocked changes nothing.
     */
    public function handle(Product $product): void
    {
        $product->forceFill([
            'blocked_at' => null,
            'block_reason' => null,
        ])->save();
    }
}
