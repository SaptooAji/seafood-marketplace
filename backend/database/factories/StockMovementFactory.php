<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Rows only, for listing tests: the variant's stock is not changed. Use
 * RecordStockMovement when the stock itself matters.
 *
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'variant_id' => ProductVariant::factory(),
            'type' => StockMovementType::StockIn,
            'qty' => '1.000',
            'stock_after' => '1.000',
            'supplier_name' => null,
            'unit_cost' => null,
            'order_id' => null,
            'note' => null,
            'created_by' => null,
        ];
    }
}
