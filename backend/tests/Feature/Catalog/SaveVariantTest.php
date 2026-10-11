<?php

declare(strict_types=1);

use App\Actions\DeleteVariant;
use App\Actions\RecordStockMovement;
use App\Actions\SaveVariant;
use App\Data\VariantData;
use App\Enums\StockMovementType;
use App\Enums\Unit;
use App\Exceptions\InvalidProductData;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;

beforeEach(function () {
    $this->product = Product::factory()->create();
});

/**
 * Grade A 300-400 g per kg at Rp185.000, unless overridden.
 *
 * @param  array<string, mixed>  $overrides  Keyed by VariantData constructor parameter.
 */
function variantData(array $overrides = []): VariantData
{
    return new VariantData(...array_merge([
        'name' => 'Grade A 300-400 g',
        'unit' => Unit::Kg,
        'price' => 185_000,
        'minQty' => '0.5',
        'isActive' => true,
        'initialStock' => null,
    ], $overrides));
}

function saveVariant(VariantData $data, ?ProductVariant $variant = null, ?User $by = null): ProductVariant
{
    return app(SaveVariant::class)->handle(test()->product, $data, $variant, $by);
}

describe('creating', function () {
    it('creates a variant with its opening stock in the stock book', function () {
        $this->travelTo(now()->setDateTime(2026, 10, 11, 6, 0));
        $user = User::factory()->create();

        $variant = saveVariant(variantData(['initialStock' => '12.5']), by: $user);

        expect($variant->fresh())
            ->product_id->toBe($this->product->id)
            ->name->toBe('Grade A 300-400 g')
            ->unit->toBe(Unit::Kg)
            ->price->toBe(185_000)
            ->min_qty->toBe('0.500')
            ->is_active->toBeTrue()
            ->stock->toBe('12.500')
            ->stock_confirmed_at->toDateTimeString()->toBe('2026-10-11 06:00:00');

        expect(StockMovement::query()->sole())
            ->variant_id->toBe($variant->id)
            ->type->toBe(StockMovementType::Adjustment)
            ->qty->toBe('12.500')
            ->note->toBe('Stok awal')
            ->created_by->toBe($user->id);
    });

    it('creates a variant without stock and leaves it unconfirmed', function (?string $initialStock) {
        $variant = saveVariant(variantData(['initialStock' => $initialStock]));

        expect($variant->fresh())
            ->stock->toBe('0.000')
            ->stock_confirmed_at->toBeNull()
            ->and(StockMovement::query()->count())->toBe(0);
    })->with([
        'no opening stock' => [null],
        'zero opening stock' => ['0'],
    ]);
});

describe('updating', function () {
    it('changes the price without touching the stock', function () {
        $variant = saveVariant(variantData(['initialStock' => '10']));

        saveVariant(variantData(['price' => 190_000, 'initialStock' => '99']), $variant);

        expect($variant->fresh())
            ->price->toBe(190_000)
            ->stock->toBe('10.000')
            ->and(StockMovement::query()->count())->toBe(1);
    });

    it('keeps its own name when saved again', function () {
        $variant = saveVariant(variantData());

        expect(saveVariant(variantData(['name' => 'GRADE A 300-400 G']), $variant)->fresh()->name)->toBe('GRADE A 300-400 G');
    });

    it('keeps the stock when a variant is saved after stock moved', function () {
        $variant = saveVariant(variantData(['initialStock' => '10']));
        app(RecordStockMovement::class)->handle($variant, StockMovementType::SaleOffline, '4');

        saveVariant(variantData(['isActive' => false]), $variant);

        expect($variant->fresh())
            ->stock->toBe('6.000')
            ->is_active->toBeFalse();
    });
});

describe('deleting', function () {
    it('soft deletes a variant and keeps its stock book', function () {
        $variant = saveVariant(variantData(['initialStock' => '5']));

        app(DeleteVariant::class)->handle($variant);

        expect(ProductVariant::query()->count())->toBe(0)
            ->and(ProductVariant::withTrashed()->find($variant->id))->not->toBeNull()
            ->and(StockMovement::query()->count())->toBe(1);
    });

    it('allows the name again after the old variant is deleted', function () {
        app(DeleteVariant::class)->handle(saveVariant(variantData()));

        expect(saveVariant(variantData())->name)->toBe('Grade A 300-400 g');
    });
});

describe('rejected variants', function () {
    it('names the field at fault and saves nothing', function (array $overrides, string $field) {
        saveVariant(variantData(['name' => 'Grade A 300-400 g']));

        expect(fn () => saveVariant(variantData($overrides)))
            ->toThrow(fn (InvalidProductData $e) => expect($e->field)->toBe($field));

        expect(ProductVariant::query()->count())->toBe(1);
    })->with([
        'same name in other letter case' => [['name' => 'grade a 300-400 g'], 'name'],
        'empty name' => [['name' => '  '], 'name'],
        'price 0' => [['name' => 'Grade B', 'price' => 0], 'price'],
        'negative price' => [['name' => 'Grade B', 'price' => -1], 'price'],
        'minimum quantity 0' => [['name' => 'Grade B', 'minQty' => '0'], 'min_qty'],
        'minimum quantity not a number' => [['name' => 'Grade B', 'minQty' => 'satu'], 'min_qty'],
        'negative opening stock' => [['name' => 'Grade B', 'initialStock' => '-1'], 'initial_stock'],
    ]);

    it('allows the same name in another product', function () {
        saveVariant(variantData());
        $otherProduct = Product::factory()->create();

        $variant = app(SaveVariant::class)->handle($otherProduct, variantData());

        expect($variant->product_id)->toBe($otherProduct->id);
    });

    it('rejects renaming to the name of a sibling', function () {
        saveVariant(variantData(['name' => 'Grade A']));
        $gradeB = saveVariant(variantData(['name' => 'Grade B']));

        expect(fn () => saveVariant(variantData(['name' => 'grade a']), $gradeB))
            ->toThrow(fn (InvalidProductData $e) => expect($e->field)->toBe('name'));

        expect($gradeB->fresh()->name)->toBe('Grade B');
    });
});
