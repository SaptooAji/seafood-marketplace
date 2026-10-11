<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ShippingFeeType;
use App\Enums\ShippingMethodType;
use Database\Factories\StoreShippingMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One way a store delivers orders, with its fee settings. A store has at most
 * one row per type. Valid type and fee combinations are enforced by
 * SaveShippingMethod.
 *
 * @property ShippingMethodType $type
 * @property ShippingFeeType $fee_type
 * @property string|null $max_radius_km
 * @property int|null $flat_fee
 * @property int|null $fee_per_km
 * @property int|null $min_fee
 * @property bool $is_active
 */
#[Fillable(['store_id', 'type', 'max_radius_km', 'fee_type', 'flat_fee', 'fee_per_km', 'min_fee', 'is_active'])]
class StoreShippingMethod extends Model
{
    /** @use HasFactory<StoreShippingMethodFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ShippingMethodType::class,
            'fee_type' => ShippingFeeType::class,
            'max_radius_km' => 'decimal:2',
            'flat_fee' => 'integer',
            'fee_per_km' => 'integer',
            'min_fee' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the store that offers this shipping method.
     *
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
