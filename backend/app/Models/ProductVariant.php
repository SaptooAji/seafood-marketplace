<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Unit;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A size or grade of a product with its own unit, price, and stock.
 *
 * `stock` and `stock_confirmed_at` are not fillable: stock only changes
 * through RecordStockMovement, so every change is in the stock book.
 *
 * @property Unit $unit
 * @property int $price
 * @property string $stock
 * @property string $min_qty
 * @property Carbon|null $stock_confirmed_at
 * @property bool $is_active
 */
#[Fillable(['name', 'unit', 'price', 'min_qty', 'is_active'])]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit' => Unit::class,
            'price' => 'integer',
            'stock' => 'decimal:3',
            'min_qty' => 'decimal:3',
            'stock_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Whether buyers should be warned that the stock may be out of date: it was
     * never confirmed, or not within `catalog.stale_stock_hours`.
     */
    public function isStockStale(): bool
    {
        return $this->stock_confirmed_at === null
            || $this->stock_confirmed_at->lt(now()->subHours(config()->integer('catalog.stale_stock_hours')));
    }

    /**
     * Get the product this variant belongs to.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
