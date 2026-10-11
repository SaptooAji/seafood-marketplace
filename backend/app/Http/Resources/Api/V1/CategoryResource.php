<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Category;
use App\Models\CategoryAttribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A node of the category tree, built by ListCategoryTree, which sets the
 * `children` and `effectiveAttributes` relations.
 *
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Collection<int, CategoryAttribute> $attributes */
        $attributes = $this->resource->getRelation('effectiveAttributes');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sort_order' => $this->sort_order,
            'quality_standard' => $this->quality_standard,
            'attributes' => $attributes->map(fn (CategoryAttribute $attribute): CategoryAttributeResource => new CategoryAttributeResource($attribute, shownUnder: $this->resource))->all(),
            'children' => self::collection($this->resource->getRelation('children')),
        ];
    }
}
