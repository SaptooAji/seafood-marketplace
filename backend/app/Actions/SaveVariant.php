<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\VariantData;
use App\Enums\StockMovementType;
use App\Exceptions\InvalidProductData;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Creates or edits a variant (size or grade) of a product.
 */
class SaveVariant
{
    public function __construct(private RecordStockMovement $recordStockMovement) {}

    /**
     * Save the variant. A new variant's opening stock is written to the stock
     * book as an "Stok awal" adjustment and counts as confirmed now. Editing a
     * variant never changes its stock.
     *
     * @throws InvalidProductData naming the first field that breaks a rule
     */
    public function handle(Product $product, VariantData $data, ?ProductVariant $variant = null, ?User $by = null): ProductVariant
    {
        $name = trim($data->name);

        $this->check('name', $name !== '', 'Nama varian wajib diisi.');
        $this->check('name', ! $this->nameTaken($product, $name, $variant), 'Nama varian sudah dipakai di produk ini.');
        $this->check('price', $data->price > 0, 'Harga harus lebih dari 0.');
        $this->check('min_qty', $this->isPositiveDecimal($data->minQty), 'Jumlah minimal harus angka lebih dari 0.');

        $attributes = [
            'name' => $name,
            'unit' => $data->unit,
            'price' => $data->price,
            'min_qty' => $data->minQty,
            'is_active' => $data->isActive,
        ];

        if ($variant !== null) {
            $variant->update($attributes);

            return $variant;
        }

        $initialStock = $data->initialStock;
        // Checked here, not left to RecordStockMovement, so the error names this field.
        $this->check(
            'initial_stock',
            $initialStock === null || preg_match('/^\d{1,7}(\.\d{1,3})?$/', $initialStock) === 1,
            'Stok awal harus angka 0 atau lebih, maksimal 3 desimal.',
        );

        return DB::transaction(function () use ($product, $attributes, $initialStock, $by): ProductVariant {
            /** @var ProductVariant $variant */
            $variant = $product->variants()->create($attributes);

            if ($initialStock !== null && preg_match('/[1-9]/', $initialStock) === 1) {
                $this->recordStockMovement->handle($variant, StockMovementType::Adjustment, $initialStock, by: $by, note: 'Stok awal');
                $variant->forceFill(['stock_confirmed_at' => now()])->save();
            }

            return $variant;
        });
    }

    /**
     * Names are unique per product without regard to letter case, among
     * variants that are not deleted.
     */
    private function nameTaken(Product $product, string $name, ?ProductVariant $ignore): bool
    {
        return $product->variants()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore?->getKey()))
            ->exists();
    }

    private function isPositiveDecimal(string $value): bool
    {
        return preg_match('/^\d{1,7}(\.\d{1,3})?$/', $value) === 1 && preg_match('/[1-9]/', $value) === 1;
    }

    /**
     * @throws InvalidProductData when the condition does not hold
     */
    private function check(string $field, bool $condition, string $message): void
    {
        if (! $condition) {
            throw new InvalidProductData($field, $message);
        }
    }
}
