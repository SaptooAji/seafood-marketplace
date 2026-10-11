<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockMovementType;
use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of the stock book: a signed change to a variant's stock and the
 * stock right after it. Written only by RecordStockMovement.
 *
 * @property StockMovementType $type
 * @property string $qty
 * @property string $stock_after
 * @property int|null $unit_cost
 */
#[Fillable(['type', 'qty', 'stock_after', 'supplier_name', 'unit_cost', 'order_id', 'note', 'created_by'])]
class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'qty' => 'decimal:3',
            'stock_after' => 'decimal:3',
            'unit_cost' => 'integer',
        ];
    }

    /**
     * Get the variant whose stock moved, even if it was deleted since.
     *
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id')->withTrashed();
    }

    /**
     * Get the user who recorded the movement; null for system movements.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
