<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\CategoryAttribute;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A product as its seller sees it, including inactive variants and an admin
 * block. Expects `category`, `variants`, and `images` to be loaded.
 *
 * The category's effective attributes are only shown when they are passed in
 * (the detail responses): working them out takes a query per category, which
 * the paginated list leaves out.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /** @var Collection<int, CategoryAttribute>|null */
    private ?Collection $effectiveAttributes = null;

    /**
     * Show these attributes with the product's values; `attributeValues` must
     * be loaded on the product.
     *
     * @param  Collection<int, CategoryAttribute>  $attributes
     */
    public function withEffectiveAttributes(Collection $attributes): static
    {
        $this->effectiveAttributes = $attributes;

        return $this;
    }

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
            'slug' => $this->slug,
            'description' => $this->description,
            'condition' => [
                'value' => $this->condition->value,
                'label' => $this->condition->label(),
            ],
            'sales_mode' => [
                'value' => $this->sales_mode->value,
                'label' => $this->sales_mode->label(),
            ],
            'daily_quota' => $this->daily_quota,
            'lead_time_days' => $this->lead_time_days,
            // Stored as a time (`15:00:00`); the app sends and shows `HH:MM`.
            'cutoff_time' => $this->cutoff_time === null ? null : substr($this->cutoff_time, 0, 5),
            'shelf_life_days' => $this->shelf_life_days,
            'storage_instructions' => $this->storage_instructions,
            'is_active' => $this->is_active,
            'blocked' => $this->blocked_at !== null,
            'block_reason' => $this->block_reason,
            'category' => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ],
            'variants' => ProductVariantResource::collection($this->variants),
            'images' => ProductImageResource::collection($this->images),
            'attributes' => $this->when($this->effectiveAttributes !== null, fn (): array => $this->attributesWithValues()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Every attribute of the category, with the product's value or null.
     *
     * @return list<array<string, mixed>>
     */
    private function attributesWithValues(): array
    {
        $values = $this->attributeValues->pluck('value', 'category_attribute_id');

        return ($this->effectiveAttributes ?? new Collection)->map(fn (CategoryAttribute $attribute): array => [
            'id' => $attribute->id,
            'name' => $attribute->name,
            'type' => [
                'value' => $attribute->type->value,
                'label' => $attribute->type->label(),
            ],
            'options' => $attribute->options,
            'unit' => $attribute->unit,
            'is_required' => $attribute->is_required,
            'value' => $values->get($attribute->id),
        ])->values()->all();
    }
}
