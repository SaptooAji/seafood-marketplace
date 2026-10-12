<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A variant as its seller sees it, with the stock and whether buyers are
 * warned that it may be out of date. Quantities are decimal strings.
 *
 * @mixin ProductVariant
 */
class ProductVariantResource extends JsonResource
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
            'name' => $this->name,
            'unit' => [
                'value' => $this->unit->value,
                'label' => $this->unit->label(),
            ],
            'price' => $this->price,
            'min_qty' => $this->min_qty,
            'stock' => $this->stock,
            'stock_confirmed_at' => $this->stock_confirmed_at?->toIso8601String(),
            'is_stock_stale' => $this->isStockStale(),
            'is_active' => $this->is_active,
        ];
    }
}
