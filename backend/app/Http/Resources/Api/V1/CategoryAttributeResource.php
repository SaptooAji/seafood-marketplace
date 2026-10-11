<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Category;
use App\Models\CategoryAttribute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An attribute as it applies to one category. `inherited` is true when an
 * ancestor defines it rather than the category itself.
 *
 * @mixin CategoryAttribute
 */
class CategoryAttributeResource extends JsonResource
{
    /**
     * @param  CategoryAttribute  $resource
     * @param  Category  $shownUnder  the category the attribute is shown under
     */
    public function __construct($resource, private readonly Category $shownUnder)
    {
        parent::__construct($resource);
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
            'type' => [
                'value' => $this->type->value,
                'label' => $this->type->label(),
            ],
            'options' => $this->options,
            'unit' => $this->unit,
            'is_required' => $this->is_required,
            'is_filterable' => $this->is_filterable,
            'inherited' => $this->category_id !== $this->shownUnder->id,
        ];
    }
}
