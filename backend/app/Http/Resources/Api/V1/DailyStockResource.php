<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A product on the daily stock screen with the variants to count. Expects
 * `variants` to be loaded with only the variants to show.
 *
 * @mixin Product
 */
class DailyStockResource extends JsonResource
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
            'variants' => $this->variants->map(fn (ProductVariant $variant): array => [
                'id' => $variant->id,
                'name' => $variant->name,
                'unit' => [
                    'value' => $variant->unit->value,
                    'label' => $variant->unit->label(),
                ],
                'stock' => $variant->stock,
                'stock_confirmed_at' => $variant->stock_confirmed_at?->toIso8601String(),
                'is_stock_stale' => $variant->isStockStale(),
            ])->all(),
        ];
    }
}
