<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Selling unit of a product variant, stored in `product_variants.unit`.
 * Quantities in this unit are decimals, so 1.5 kg is valid.
 */
enum Unit: string
{
    case Kg = 'kg';
    case Piece = 'piece';
    case Dozen = 'dozen';
    case Pack = 'pack';

    /**
     * Get the label for the unit.
     */
    public function label(): string
    {
        return match ($this) {
            self::Kg => 'Kg',
            self::Piece => 'Pcs',
            self::Dozen => 'Lusin',
            self::Pack => 'Pack',
        };
    }
}
