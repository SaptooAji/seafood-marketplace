<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The value a product has for one attribute of its category, stored as text
 * and checked against the attribute's type by SaveProduct.
 */
#[Fillable(['category_attribute_id', 'value'])]
class ProductAttributeValue extends Model
{
    /**
     * Get the product the value belongs to.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the category attribute this value answers.
     *
     * @return BelongsTo<CategoryAttribute, $this>
     */
    public function categoryAttribute(): BelongsTo
    {
        return $this->belongsTo(CategoryAttribute::class);
    }
}
