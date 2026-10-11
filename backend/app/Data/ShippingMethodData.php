<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\ShippingFeeType;
use App\Enums\ShippingMethodType;

/**
 * A store's settings for one shipping method. Fees are whole rupiah; the
 * radius is a decimal string so no precision is lost to floats.
 */
readonly class ShippingMethodData
{
    public function __construct(
        public ShippingMethodType $type,
        public ShippingFeeType $feeType,
        public ?string $maxRadiusKm,
        public ?int $flatFee,
        public ?int $feePerKm,
        public ?int $minFee,
        public bool $isActive = true,
    ) {}
}
