<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProductCondition;
use App\Enums\SalesMode;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state: an active ready stock product of an
     * active store. Add variants with ProductVariant::factory()->for($product).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->words(3, true));

        return [
            'store_id' => Store::factory()->active(),
            'category_id' => Category::factory(),
            'name' => $name,
            // Random suffix: different names can produce the same slug.
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => fake()->paragraph(),
            'condition' => fake()->randomElement(ProductCondition::cases()),
            'sales_mode' => SalesMode::ReadyStock,
            'daily_quota' => null,
            'lead_time_days' => null,
            'cutoff_time' => null,
            'shelf_life_days' => null,
            'storage_instructions' => null,
            'is_active' => true,
            'blocked_at' => null,
            'block_reason' => null,
        ];
    }

    /**
     * Sold to order: a daily quota, days of lead time, and a cutoff time.
     */
    public function preOrder(): static
    {
        return $this->state(fn (array $attributes) => [
            'sales_mode' => SalesMode::PreOrder,
            'daily_quota' => '20.000',
            'lead_time_days' => 2,
            'cutoff_time' => '15:00',
        ]);
    }

    /**
     * Turned off by the seller.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Blocked by an admin.
     */
    public function blocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'blocked_at' => now(),
            'block_reason' => 'Foto tidak sesuai produk.',
        ]);
    }
}
