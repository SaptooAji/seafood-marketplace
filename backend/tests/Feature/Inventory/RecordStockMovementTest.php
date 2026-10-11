<?php

declare(strict_types=1);

use App\Actions\RecordStockMovement;
use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStock;
use App\Exceptions\InvalidStockMovement;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;

/**
 * A variant whose stock starts at the given amount, set directly as a fixture.
 */
function variantWithStock(string $stock = '0'): ProductVariant
{
    return ProductVariant::factory()->create(['stock' => $stock]);
}

/**
 * @param  array<string, mixed>  $extra  supplierName, unitCost, note, by
 */
function recordStock(ProductVariant $variant, StockMovementType $type, string $qty, array $extra = []): StockMovement
{
    return app(RecordStockMovement::class)->handle($variant, $type, $qty, ...$extra);
}

describe('valid movements', function () {
    it('records stock coming in with the supplier and purchase price', function () {
        $variant = variantWithStock('0');
        $user = User::factory()->create();

        $movement = recordStock($variant, StockMovementType::StockIn, '10', [
            'by' => $user,
            'supplierName' => 'Pak Darto',
            'unitCost' => 60_000,
            'note' => 'Setoran pagi',
        ]);

        expect($variant->fresh()->stock)->toBe('10.000')
            ->and($movement->fresh())
            ->type->toBe(StockMovementType::StockIn)
            ->qty->toBe('10.000')
            ->stock_after->toBe('10.000')
            ->supplier_name->toBe('Pak Darto')
            ->unit_cost->toBe(60_000)
            ->note->toBe('Setoran pagi')
            ->created_by->toBe($user->id)
            ->variant_id->toBe($variant->id);
    });

    it('stores outgoing movements as negative quantities', function () {
        $variant = variantWithStock('10');

        $sale = recordStock($variant, StockMovementType::SaleOffline, '2.5');
        $shrinkage = recordStock($variant, StockMovementType::Shrinkage, '0.5');

        expect($sale->fresh()->qty)->toBe('-2.500')
            ->and($sale->fresh()->stock_after)->toBe('7.500')
            ->and($shrinkage->fresh()->qty)->toBe('-0.500')
            ->and($shrinkage->fresh()->stock_after)->toBe('7.000')
            ->and($variant->fresh()->stock)->toBe('7.000');
    });

    it('applies the direction of every type', function (StockMovementType $type, string $expectedStock) {
        $variant = variantWithStock('10');

        recordStock($variant, $type, '1');

        expect($variant->fresh()->stock)->toBe($expectedStock);
    })->with([
        'stock in' => [StockMovementType::StockIn, '11.000'],
        'cancel return' => [StockMovementType::CancelReturn, '11.000'],
        'app sale' => [StockMovementType::SaleApp, '9.000'],
        'offline sale' => [StockMovementType::SaleOffline, '9.000'],
        'shrinkage' => [StockMovementType::Shrinkage, '9.000'],
    ]);

    it('takes the sign of an adjustment from the quantity', function (string $qty, string $expectedStock) {
        $variant = variantWithStock('10');

        $movement = recordStock($variant, StockMovementType::Adjustment, $qty);

        expect($variant->fresh()->stock)->toBe($expectedStock)
            ->and($movement->fresh()->stock_after)->toBe($expectedStock);
    })->with([
        'down' => ['-1.25', '8.750'],
        'up' => ['3', '13.000'],
    ]);

    it('lists the movements of a variant', function () {
        $variant = variantWithStock('0');
        recordStock($variant, StockMovementType::StockIn, '5');
        recordStock($variant, StockMovementType::SaleOffline, '1');

        expect($variant->stockMovements()->count())->toBe(2)
            ->and(StockMovement::query()->first()->variant->is($variant))->toBeTrue();
    });
});

describe('rejected movements', function () {
    it('refuses to take more than the stock and changes nothing', function () {
        $variant = variantWithStock('2');

        expect(fn () => recordStock($variant, StockMovementType::SaleOffline, '2.001'))
            ->toThrow(InsufficientStock::class);

        expect($variant->fresh()->stock)->toBe('2.000')
            ->and(StockMovement::query()->count())->toBe(0);
    });

    it('refuses an adjustment below zero', function () {
        $variant = variantWithStock('1');

        expect(fn () => recordStock($variant, StockMovementType::Adjustment, '-1.5'))
            ->toThrow(InsufficientStock::class);

        expect($variant->fresh()->stock)->toBe('1.000');
    });

    it('rejects a quantity that does not fit the type', function (StockMovementType $type, string $qty) {
        $variant = variantWithStock('10');

        expect(fn () => recordStock($variant, $type, $qty))
            ->toThrow(fn (InvalidStockMovement $e) => expect($e->field)->toBe('qty'));

        expect(StockMovement::query()->count())->toBe(0)
            ->and($variant->fresh()->stock)->toBe('10.000');
    })->with([
        'zero stock in' => [StockMovementType::StockIn, '0'],
        'negative stock in' => [StockMovementType::StockIn, '-1'],
        'negative shrinkage' => [StockMovementType::Shrinkage, '-1'],
        'zero adjustment' => [StockMovementType::Adjustment, '0'],
        'not a number' => [StockMovementType::StockIn, 'sepuluh'],
        'more than three decimals' => [StockMovementType::StockIn, '1.2345'],
    ]);

    it('accepts a supplier and purchase price only for stock coming in', function (string $field, array $extra) {
        $variant = variantWithStock('10');

        expect(fn () => recordStock($variant, StockMovementType::Shrinkage, '1', $extra))
            ->toThrow(fn (InvalidStockMovement $e) => expect($e->field)->toBe($field));

        expect(StockMovement::query()->count())->toBe(0);
    })->with([
        'supplier on shrinkage' => ['supplier_name', ['supplierName' => 'Pak Darto']],
        'purchase price on shrinkage' => ['unit_cost', ['unitCost' => 60_000]],
    ]);
});

describe('edge cases', function () {
    it('adds decimals exactly', function () {
        $variant = variantWithStock('0');

        recordStock($variant, StockMovementType::StockIn, '0.1');
        recordStock($variant, StockMovementType::StockIn, '0.2');

        expect($variant->fresh()->stock)->toBe('0.300');
    });

    it('keeps stock_after in step across movements', function () {
        $variant = variantWithStock('0');

        $afters = collect([
            [StockMovementType::StockIn, '12.5'],
            [StockMovementType::SaleOffline, '2.25'],
            [StockMovementType::Adjustment, '-0.25'],
        ])->map(fn (array $step) => recordStock($variant, $step[0], $step[1])->fresh()->stock_after);

        expect($afters->all())->toBe(['12.500', '10.250', '10.000'])
            ->and($variant->fresh()->stock)->toBe('10.000');
    });

    it('works on a variant model whose stock is out of date', function () {
        $variant = variantWithStock('5');
        $stale = ProductVariant::query()->find($variant->id);
        recordStock($variant, StockMovementType::SaleOffline, '4');

        expect(fn () => recordStock($stale, StockMovementType::SaleOffline, '2'))->toThrow(InsufficientStock::class);

        expect($variant->fresh()->stock)->toBe('1.000');
    });

    it('updates the variant model it was given', function () {
        $variant = variantWithStock('5');

        recordStock($variant, StockMovementType::StockIn, '1');

        expect($variant->stock)->toBe('6.000');
    });
});
