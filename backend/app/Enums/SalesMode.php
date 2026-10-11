<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a product is sold, stored in `products.sales_mode`.
 *
 * Ready stock ships from current stock. Pre-order products use daily quota,
 * lead time, and cutoff time, and their approved orders become scheduled
 * until production day.
 */
enum SalesMode: string
{
    case ReadyStock = 'ready_stock';
    case PreOrder = 'pre_order';

    /**
     * Get the label for the sales mode.
     */
    public function label(): string
    {
        return match ($this) {
            self::ReadyStock => 'Stok Siap',
            self::PreOrder => 'Pre Order',
        };
    }
}
