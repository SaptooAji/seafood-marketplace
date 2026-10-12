<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\StockMovementType;
use App\Exceptions\InvalidStockMovement;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The seller's daily stock check: count what is really there, correct the
 * variants that differ, and confirm the stock so buyers are not warned that
 * it may be out of date.
 */
class ConfirmDailyStock
{
    public function __construct(private RecordStockMovement $recordStockMovement) {}

    /**
     * Set each counted variant to its counted stock with a "Konfirmasi harian"
     * adjustment for the difference (none when it is the same), then confirm
     * the counted variants, or every active variant of the store's active
     * products when `$confirmAll` is set. All of it happens in one transaction:
     * one rejected count changes nothing.
     *
     * The difference is taken from the stock in the database at that moment,
     * so sales made since the seller loaded the screen do not skew it.
     *
     * @param  list<array{id: int, stock: string}>  $counts  the variants whose count differs, as decimal strings
     *
     * @throws InvalidStockMovement on `variants`, or `variants.<index>.<id|stock>` for one count
     */
    public function handle(Store $store, array $counts, bool $confirmAll, ?User $by = null): void
    {
        $this->ensure($counts !== [] || $confirmAll, 'variants', 'Isi stok varian yang berubah, atau konfirmasi semua.');

        $seen = [];

        foreach ($counts as $index => $count) {
            $this->ensure(
                preg_match('/^\d{1,7}(\.\d{1,3})?$/', $count['stock']) === 1,
                "variants.{$index}.stock",
                'Stok harus angka 0 atau lebih, maksimal 3 desimal.',
            );
            $this->ensure(! isset($seen[$count['id']]), "variants.{$index}.id", 'Varian ini sudah diisi.');
            $seen[$count['id']] = true;
        }

        DB::transaction(function () use ($store, $counts, $confirmAll, $by): void {
            $variants = $this->variantsToCount($store)->lockForUpdate()->get()->keyBy('id');

            foreach ($counts as $index => $count) {
                $variant = $variants->get($count['id']);
                $this->ensure($variant !== null, "variants.{$index}.id", 'Varian tidak ada di daftar stok hari ini.');
                /** @var ProductVariant $variant */
                $difference = $this->toThousandths($count['stock']) - $this->toThousandths($variant->stock);

                if ($difference !== 0) {
                    $this->recordStockMovement->handle(
                        $variant,
                        StockMovementType::Adjustment,
                        $this->fromThousandths($difference),
                        by: $by,
                        note: 'Konfirmasi harian',
                    );
                }
            }

            $confirmed = $confirmAll ? $variants->keys()->all() : array_column($counts, 'id');

            ProductVariant::query()->whereKey($confirmed)->update(['stock_confirmed_at' => now()]);
        });
    }

    /**
     * The variants on the daily stock screen: active variants of the store's
     * active products. Deleted ones are left out by SoftDeletes.
     *
     * @return Builder<ProductVariant>
     */
    private function variantsToCount(Store $store): Builder
    {
        return ProductVariant::query()
            ->where('is_active', true)
            ->whereHas('product', fn (Builder $product) => $product->whereBelongsTo($store)->where('is_active', true));
    }

    /**
     * Exact integer arithmetic on decimal(10,3) values: `7.5` becomes 7500.
     */
    private function toThousandths(string $decimal): int
    {
        [$whole, $fraction] = array_pad(explode('.', $decimal, 2), 2, '');

        return (int) $whole * 1000 + (int) str_pad(substr($fraction, 0, 3), 3, '0');
    }

    private function fromThousandths(int $thousandths): string
    {
        $absolute = abs($thousandths);

        return sprintf('%s%d.%03d', $thousandths < 0 ? '-' : '', intdiv($absolute, 1000), $absolute % 1000);
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
