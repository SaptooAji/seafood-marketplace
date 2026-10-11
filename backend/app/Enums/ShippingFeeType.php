<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a shipping method's fee is calculated, stored in `store_shipping_methods.fee_type`.
 * Flat uses `flat_fee`, per km uses `fee_per_km` with `min_fee`, manual is set per order.
 */
enum ShippingFeeType: string
{
    case Flat = 'flat';
    case PerKm = 'per_km';
    case Manual = 'manual';

    /**
     * Get the label for the shipping fee type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Flat => 'Tetap',
            self::PerKm => 'Per Kilometer',
            self::Manual => 'Manual',
        };
    }
}
