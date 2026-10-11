<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AttributeType;
use App\Models\Category;
use App\Models\CategoryAttribute;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CategoryAttribute>
 */
class CategoryAttributeFactory extends Factory
{
    /**
     * Define the model's default state: an optional text attribute.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => Str::ucfirst(fake()->unique()->words(2, true)),
            'type' => AttributeType::Text,
            'options' => null,
            'unit' => null,
            'is_required' => false,
            'is_filterable' => false,
        ];
    }
}
