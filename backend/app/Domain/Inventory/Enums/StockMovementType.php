<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Enums;

/**
 * Reason for a stock change, stored in `stock_movements.type`.
 *
 * The direction of a movement comes from the sign of `stock_movements.qty`,
 * not from its type, since an adjustment can go either way.
 */
enum StockMovementType: string
{
    case StockIn = 'stock_in';
    case SaleApp = 'sale_app';
    case SaleOffline = 'sale_offline';
    case Shrinkage = 'shrinkage';
    case Adjustment = 'adjustment';
    case CancelReturn = 'cancel_return';

    /**
     * Get the label for the stock movement type.
     */
    public function label(): string
    {
        return match ($this) {
            self::StockIn => 'Stok Masuk',
            self::SaleApp => 'Penjualan Aplikasi',
            self::SaleOffline => 'Penjualan Offline',
            self::Shrinkage => 'Susut',
            self::Adjustment => 'Penyesuaian',
            self::CancelReturn => 'Pembatalan Retur',
        };
    }
}
