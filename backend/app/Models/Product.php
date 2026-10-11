<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductCondition;
use App\Enums\SalesMode;
use App\Enums\StoreStatus;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A product a store sells, in one leaf category, with variants for size or
 * grade. `is_active` belongs to the seller; `blocked_at` and `block_reason`
 * belong to admins and are not fillable, so a seller cannot undo a block.
 * `store_id` is set through `Store::products()`.
 *
 * @property ProductCondition $condition
 * @property SalesMode $sales_mode
 * @property string|null $daily_quota
 * @property bool $is_active
 * @property Carbon|null $blocked_at
 */
#[Fillable([
    'category_id', 'name', 'slug', 'description', 'condition', 'sales_mode', 'daily_quota',
    'lead_time_days', 'cutoff_time', 'shelf_life_days', 'storage_instructions', 'is_active',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'condition' => ProductCondition::class,
            'sales_mode' => SalesMode::class,
            'daily_quota' => 'decimal:3',
            'lead_time_days' => 'integer',
            'shelf_life_days' => 'integer',
            'is_active' => 'boolean',
            'blocked_at' => 'datetime',
        ];
    }

    /**
     * Products buyers may see: active, not blocked, from an active store, and
     * with at least one active variant. Soft deleted products and variants are
     * already left out by SoftDeletes.
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function visibleInCatalog(Builder $query): void
    {
        $query->where('is_active', true)
            ->whereNull('blocked_at')
            ->whereHas('store', fn (Builder $store) => $store->where('status', StoreStatus::Active->value))
            ->whereHas('variants', fn (Builder $variant) => $variant->where('is_active', true));
    }

    /**
     * Get the store that sells this product.
     *
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the leaf category of this product.
     *
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the sizes or grades this product is sold in, oldest first.
     *
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('id');
    }

    /**
     * Get the photos in display order.
     *
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    /**
     * Get the values filled in for the category's attributes.
     *
     * @return HasMany<ProductAttributeValue, $this>
     */
    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }
}
