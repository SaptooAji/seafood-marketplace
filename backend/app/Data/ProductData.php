<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\ProductCondition;
use App\Enums\SalesMode;

/**
 * The product details a seller fills in. `attributes` maps a category
 * attribute id to its value as text; an empty value means "not filled in".
 * Quantities are decimal strings so no precision is lost to floats.
 * `seoDescription` comes last with a default so older callers keep working.
 */
readonly class ProductData
{
    /**
     * @param  array<int, string|null>  $attributes
     */
    public function __construct(
        public int $categoryId,
        public string $name,
        public string $description,
        public ProductCondition $condition,
        public SalesMode $salesMode,
        public ?string $dailyQuota,
        public ?int $leadTimeDays,
        public ?string $cutoffTime,
        public ?int $shelfLifeDays,
        public ?string $storageInstructions,
        public bool $isActive,
        public array $attributes,
        public ?string $seoDescription = null,
    ) {}
}
