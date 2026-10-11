<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Unit;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    /**
     * Define the model's default state: an active variant per kg with stock
     * confirmed just now. Factories may set `stock` directly; the app may not.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => 'Grade '.fake()->unique()->bothify('?## ##'),
            'unit' => Unit::Kg,
            'price' => fake()->numberBetween(20, 300) * 1_000,
            'stock' => '10.000',
            'stock_confirmed_at' => now(),
            'min_qty' => '1.000',
            'is_active' => true,
        ];
    }
}
