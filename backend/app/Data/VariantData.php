<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\Unit;

/**
 * A variant as the seller enters it. Quantities are decimal strings. The
 * opening stock is only used when the variant is created; after that, stock
 * changes go through the stock book.
 */
readonly class VariantData
{
    public function __construct(
        public string $name,
        public Unit $unit,
        public int $price,
        public string $minQty,
        public bool $isActive,
        public ?string $initialStock = null,
    ) {}
}
