<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who cancelled an order, stored in `orders.cancelled_by`.
 */
enum CancelledBy: string
{
    case Buyer = 'buyer';
    case Seller = 'seller';
    case Admin = 'admin';

    /** Automatic cancellation when the payment deadline passes. */
    case System = 'system';

    /**
     * Labels for each cancelling party in Indonesian.
     */
    public function label(): string
    {
        return match ($this) {
            self::Buyer => 'Pembeli',
            self::Seller => 'Penjual',
            self::Admin => 'Admin',
            self::System => 'Sistem',
        };
    }
}
