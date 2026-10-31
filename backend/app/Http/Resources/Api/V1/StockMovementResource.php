<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One line of the stock book. `qty` is signed and `stock_after` is the stock
 * right after the movement, both in the variant's unit. Expects
 * `variant.product` and `createdBy` to be loaded, including deleted variants
 * and products.
 *
 * @mixin StockMovement
 */
class StockMovementResource extends JsonResource
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
            'qty' => $this->qty,
            'stock_after' => $this->stock_after,
            'variant' => [
                'id' => $this->variant->id,
                'name' => $this->variant->name,
                'unit' => [
                    'value' => $this->variant->unit->value,
                    'label' => $this->variant->unit->label(),
                ],
            ],
            'product' => [
                'id' => $this->variant->product->id,
                'name' => $this->variant->product->name,
            ],
            'supplier_name' => $this->supplier_name,
            'unit_cost' => $this->unit_cost,
            'note' => $this->note,
            'created_by' => $this->createdBy === null ? null : [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
