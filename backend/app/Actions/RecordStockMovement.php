<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStock;
use App\Exceptions\InvalidStockMovement;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The only way a variant's stock changes: every change is written to the
 * stock book together with the stock right after it.
 */
class RecordStockMovement
{
    /**
     * Apply the movement and record it.
     *
     * `$qty` is a decimal string with at most three decimals. For types with a
     * fixed direction it is the positive amount and the sign comes from the
     * type; an adjustment takes its sign from `$qty`.
     *
     * The stock is changed by one conditional UPDATE in PostgreSQL, so the
     * arithmetic is exact decimal, the row is locked while it changes, and
     * the check against going below zero uses the stock in the database,
     * not the possibly outdated value on `$variant`.
     *
     * @throws InvalidStockMovement when the quantity or details do not fit the type
     * @throws InsufficientStock when the stock would go below zero
     */
    public function handle(
        ProductVariant $variant,
        StockMovementType $type,
        string $qty,
        ?User $by = null,
        ?string $supplierName = null,
        ?int $unitCost = null,
        ?string $note = null,
        ?int $orderId = null,
    ): StockMovement {
        $signedQty = $this->signedQty($type, $qty);

        if ($type !== StockMovementType::StockIn) {
            $this->ensure($supplierName === null, 'supplier_name', 'Penyetor hanya untuk stok masuk.');
            $this->ensure($unitCost === null, 'unit_cost', 'Harga beli hanya untuk stok masuk.');
        }

        $this->ensure($unitCost === null || $unitCost >= 0, 'unit_cost', 'Harga beli tidak boleh negatif.');

        return DB::transaction(function () use ($variant, $type, $signedQty, $by, $supplierName, $unitCost, $note, $orderId): StockMovement {
            $row = DB::selectOne(
                'UPDATE product_variants SET stock = stock + ?::numeric, updated_at = ?
                 WHERE id = ? AND stock + ?::numeric >= 0
                 RETURNING stock',
                [$signedQty, now(), $variant->getKey(), $signedQty],
            );

            if ($row === null) {
                throw new InsufficientStock($variant);
            }

            // Keep the given model in step without marking it dirty.
            $variant->setAttribute('stock', (string) $row->stock);
            $variant->syncOriginalAttribute('stock');

            /** @var StockMovement */
            return $variant->stockMovements()->create([
                'type' => $type,
                'qty' => $signedQty,
                'stock_after' => (string) $row->stock,
                'supplier_name' => $supplierName,
                'unit_cost' => $unitCost,
                'order_id' => $orderId,
                'note' => $note,
                'created_by' => $by?->getKey(),
            ]);
        });
    }

    /**
     * The quantity with the sign the type gives it.
     *
     * @throws InvalidStockMovement
     */
    private function signedQty(StockMovementType $type, string $qty): string
    {
        // decimal(10,3): up to 7 digits before the point and 3 after.
        $this->ensure(preg_match('/^-?\d{1,7}(\.\d{1,3})?$/', $qty) === 1, 'qty', 'Jumlah harus angka dengan maksimal 3 desimal.');
        $this->ensure(preg_match('/[1-9]/', $qty) === 1, 'qty', 'Jumlah tidak boleh 0.');

        $direction = $type->direction();

        if ($direction === null) {
            return $qty;
        }

        $this->ensure(! str_starts_with($qty, '-'), 'qty', 'Jumlah harus positif; arahnya ditentukan oleh jenis catatan.');

        return $direction < 0 ? "-{$qty}" : $qty;
    }

    /**
     * @throws InvalidStockMovement when the condition does not hold
     */
    private function ensure(bool $condition, string $field, string $message): void
    {
        if (! $condition) {
            throw new InvalidStockMovement($field, $message);
        }
    }
}
