<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\StoreShippingMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A store's shipping method with its fee settings. Fees are whole rupiah.
 *
 * @mixin StoreShippingMethod
 */
class ShippingMethodResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => [
                'value' => $this->type->value,
                'label' => $this->type->label(),
            ],
            'fee_type' => [
                'value' => $this->fee_type->value,
                'label' => $this->fee_type->label(),
            ],
            'max_radius_km' => $this->max_radius_km,
            'flat_fee' => $this->flat_fee,
            'fee_per_km' => $this->fee_per_km,
            'min_fee' => $this->min_fee,
            'is_active' => $this->is_active,
        ];
    }
}
