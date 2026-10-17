<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductCondition;
use App\Enums\SalesMode;
use App\Enums\StoreStatus;
use App\Support\MetaDescription;
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
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A product a store sells, in one leaf category, with variants for size or
 * grade. `is_active` belongs to the seller; `blocked_at` and `block_reason`
 * belong to admins and are not fillable, so a seller cannot undo a block.
 * `store_id` is set through `Store::products()`.
 *
 * @property ProductCondition $condition
 * @property SalesMode $sales_mode
 * @property string|null $seo_description
 * @property string|null $daily_quota
 * @property bool $is_active
 * @property Carbon|null $blocked_at
 */
#[Fillable([
    'category_id', 'name', 'slug', 'description', 'seo_description', 'condition', 'sales_mode', 'daily_quota',
    'lead_time_days', 'cutoff_time', 'shelf_life_days', 'storage_instructions', 'is_active',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    public const int SEO_DESCRIPTION_MAX_LENGTH = 160;

    /**
     * Log the seller's name and active switch and the admin's block, only the
     * fields that changed, so moderation shows who blocked what and why.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'is_active', 'blocked_at', 'block_reason'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

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
     * Add `last_checked_at`: when the stock of the product's active variants
     * was last checked, as a date, or null when it never was.
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function withLastCheckedAt(Builder $query): void
    {
        $query->withMax(['variants as last_checked_at' => fn (Builder $variant) => $variant->where('is_active', true)], 'stock_confirmed_at')
            ->withCasts(['last_checked_at' => 'datetime']);
    }

    /**
     * Put the products of real stores before those of demo stores. Call it
     * before any other order, so the buyer's sort applies within each group.
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function realStoresFirst(Builder $query): void
    {
        $query->orderBy(Store::query()->select('is_demo')->whereColumn('stores.id', 'products.store_id'));
    }

    /**
     * The meta description for the product page: the seller's SEO summary, or
     * else the description on one line, cut at a word to fit with an ellipsis.
     */
    public function metaDescription(): string
    {
        if (filled($this->seo_description)) {
            return (string) $this->seo_description;
        }

        return MetaDescription::fromText((string) $this->description);
    }

    /**
     * Load what a product card shows: the store's public columns, the first
     * photo, and the cheapest active variant. A fixed number of queries
     * however many products are listed.
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function withCardDetails(Builder $query): void
    {
        $query->with([
            'store:id,name,slug,city,is_demo',
            'images' => fn (HasMany $images) => $images->limit(1),
            'variants' => fn (HasMany $variants) => $variants
                ->where('is_active', true)
                ->reorder('price')
                ->orderBy('id')
                ->limit(1),
        ]);
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
