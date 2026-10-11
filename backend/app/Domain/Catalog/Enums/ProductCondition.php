<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

/**
 * Condition of a product when sold, stored in `products.condition`.
 * Used by buyers to filter the catalog.
 */
enum ProductCondition: string
{
    case Live = 'live';
    case Fresh = 'fresh';
    case Frozen = 'frozen';
    case Marinated = 'marinated';

    /**
     * Get the label for the product condition.
     */
    public function label(): string
    {
        return match ($this) {
            self::Live => 'Hidup',
            self::Fresh => 'Segar',
            self::Frozen => 'Beku',
            self::Marinated => 'Dibumbui',
        };
    }
}
