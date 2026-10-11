<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Reason for a stock change, stored in `stock_movements.type`.
 *
 * `stock_movements.qty` is signed. Most types always move stock one way (see
 * direction()); an adjustment can go either way and takes its sign from the
 * quantity.
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
            self::CancelReturn => 'Kembali dari Pembatalan',
        };
    }

    /**
     * Which way this type moves the stock: 1 adds, -1 removes, null for an
     * adjustment, which can go either way.
     */
    public function direction(): ?int
    {
        return match ($this) {
            self::StockIn, self::CancelReturn => 1,
            self::SaleApp, self::SaleOffline, self::Shrinkage => -1,
            self::Adjustment => null,
        };
    }
}
