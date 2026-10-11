<?php

declare(strict_types=1);

namespace App\Domain\Shipping\Enums;

/**
 * Delivery option offered by a store, stored in `store_shipping_methods.type`.
 *
 * Own delivery and pickup fees are calculated from distance, so their orders go
 * straight to payment. Ojol and expedition fees are entered by the seller.
 */
enum ShippingMethodType: string
{
    case OwnDelivery = 'own_delivery';
    case Pickup = 'pickup';
    case Ojol = 'ojol';
    case Expedition = 'expedition';

    /**
     * Get the label for the shipping method type.
     */
    public function label(): string
    {
        return match ($this) {
            self::OwnDelivery => 'Pengiriman Sendiri',
            self::Pickup => 'Ambil Sendiri',
            self::Ojol => 'Ojek Online',
            self::Expedition => 'Ekspedisi',
        };
    }
}
