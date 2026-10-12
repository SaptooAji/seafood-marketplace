<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use App\Enums\StoreRole;
use App\Filament\Seller\Pages\DailyStock;
use App\Filament\Seller\Resources\StockMovements\Pages\ListStockMovements;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
 * The user owns an active store, the current tenant, with two products:
 *   Udang Vaname: Size 50 (10 kg), Size 70 (4 kg), both confirmed 25 hours ago
 *   Cumi Segar: Besar (6 kg, never confirmed)
 */
beforeEach(function () {
    $this->travelTo(now()->setDateTime(2026, 10, 12, 6, 0));
    $yesterday = now()->subHours(25);

    $this->user = User::factory()->create(['name' => 'Budi Santoso']);
    $this->store = Store::factory()->active()->create();
    $this->store->users()->attach($this->user, ['role' => StoreRole::Owner->value]);

    $this->actingAs($this->user);
    Filament::setCurrentPanel('seller');
    Filament::setTenant($this->store);
    Filament::bootCurrentPanel();

    $this->udang = Product::factory()->for($this->store)->create(['name' => 'Udang Vaname']);
    $this->size50 = ProductVariant::factory()->for($this->udang)->create(['name' => 'Size 50', 'stock' => '10.000', 'stock_confirmed_at' => $yesterday]);
    $this->size70 = ProductVariant::factory()->for($this->udang)->create(['name' => 'Size 70', 'stock' => '4.000', 'stock_confirmed_at' => $yesterday]);

    $this->cumi = Product::factory()->for($this->store)->create(['name' => 'Cumi Segar']);
    $this->besar = ProductVariant::factory()->for($this->cumi)->create(['name' => 'Besar', 'stock' => '6.000', 'stock_confirmed_at' => null]);
});

/**
 * A variant of another active store, created outside the panel tenant.
 *
 * @param  array<string, mixed>  $attributes
 */
function otherStoreVariant(array $attributes = []): ProductVariant
{
    return outsideTenant(fn () => ProductVariant::factory()
        ->for(Product::factory()->for(Store::factory()->active())->create(['name' => 'Kepiting Tetangga']))
        ->create($attributes));
}

/**
 * A movement recorded for the variant through the stock book, as the API does.
 */
function stockBookEntry(ProductVariant $variant, StockMovementType $type, string $qty, array $attributes = []): StockMovement
{
    return StockMovement::factory()->for($variant, 'variant')->create([
        'type' => $type,
        'qty' => $qty,
        'stock_after' => $variant->stock,
        ...$attributes,
    ]);
}

describe('access', function () {
    it('opens both pages for members of the store', function (StoreRole $role) {
        $user = User::factory()->create();
        $this->store->users()->attach($user, ['role' => $role->value]);

        $this->actingAs($user)->get("/seller/{$this->store->slug}/daily-stock")->assertOk();
        $this->actingAs($user)->get("/seller/{$this->store->slug}/stock-movements")->assertOk();
    })->with([
        'owner' => [StoreRole::Owner],
        'staff' => [StoreRole::Staff],
    ]);
});

describe('Stok hari ini', function () {
    it('shows the active variants of the store with their stock', function () {
        Livewire::test(DailyStock::class)
            ->assertSee(['Udang Vaname', 'Size 50', 'Size 70', 'Cumi Segar', 'Besar'])
            ->assertFormSet([
                'counts.'.$this->size50->id => '10.000',
                'counts.'.$this->size70->id => '4.000',
                'counts.'.$this->besar->id => '6.000',
            ]);
    });

    it('marks stale stock', function () {
        $this->size70->forceFill(['stock_confirmed_at' => now()->subHours(2)])->save();

        Livewire::test(DailyStock::class)
            ->assertFormFieldExists('counts.'.$this->size50->id, fn ($field): bool => str_contains((string) $field->getHint(), 'Perlu dikonfirmasi'))
            ->assertFormFieldExists('counts.'.$this->size70->id, fn ($field): bool => ! str_contains((string) $field->getHint(), 'Perlu dikonfirmasi'));
    });

    it('leaves out inactive and deleted products and variants, and other stores', function () {
        ProductVariant::factory()->for($this->udang)->create(['name' => 'Size 90 nonaktif', 'is_active' => false]);
        ProductVariant::factory()->for($this->udang)->create(['name' => 'Size 30 dihapus', 'deleted_at' => now()]);
        Product::factory()->for($this->store)->inactive()->create(['name' => 'Rajungan nonaktif'])
            ->variants()->save(ProductVariant::factory()->make());
        $other = otherStoreVariant(['name' => 'Jantan tetangga']);

        Livewire::test(DailyStock::class)
            ->assertDontSee(['Size 90 nonaktif', 'Size 30 dihapus', 'Rajungan nonaktif', 'Kepiting Tetangga', 'Jantan tetangga'])
            ->assertFormFieldDoesNotExist('counts.'.$other->id);
    });

    it('confirms every variant without a movement with "same as yesterday"', function () {
        Livewire::test(DailyStock::class)
            ->callAction('sameAsYesterday')
            ->assertHasNoErrors();

        foreach ([$this->size50, $this->size70, $this->besar] as $variant) {
            expect($variant->fresh()->stock_confirmed_at?->equalTo(now()))->toBeTrue();
        }
        expect(StockMovement::query()->count())->toBe(0);
    });

    it('records one adjustment for a changed variant and confirms the rest', function () {
        Livewire::test(DailyStock::class)
            ->fillForm(['counts.'.$this->size50->id => '8.5'])
            ->callAction('save')
            ->assertHasNoFormErrors();

        expect(StockMovement::query()->sole())
            ->variant_id->toBe($this->size50->id)
            ->type->toBe(StockMovementType::Adjustment)
            ->qty->toBe('-1.500')
            ->created_by->toBe($this->user->id)
            ->and($this->size50->fresh()->stock)->toBe('8.500')
            ->and($this->size70->fresh()->stock)->toBe('4.000');

        foreach ([$this->size50, $this->size70, $this->besar] as $variant) {
            expect($variant->fresh()->stock_confirmed_at?->equalTo(now()))->toBeTrue();
        }
    });

    it('rejects a count on its field and changes nothing', function (string $count) {
        Livewire::test(DailyStock::class)
            ->fillForm(['counts.'.$this->size50->id => $count])
            ->callAction('save')
            ->assertHasFormErrors(['counts.'.$this->size50->id]);

        expect($this->size50->fresh())
            ->stock->toBe('10.000')
            ->stock_confirmed_at->not->toBeNull()
            ->and(StockMovement::query()->count())->toBe(0)
            ->and($this->besar->fresh()->stock_confirmed_at)->toBeNull();
    })->with([
        'negative' => ['-1'],
        'four decimals' => ['1.2345'],
        'not a number' => ['sepuluh'],
        'empty' => [''],
    ]);
});

describe('Buku stok', function () {
    it('lists the movements of the store, including deleted variants', function () {
        $stockIn = stockBookEntry($this->size50, StockMovementType::StockIn, '5.000', ['supplier_name' => 'Pak Darto']);
        $deleted = ProductVariant::factory()->for($this->udang)->create(['name' => 'Size 30']);
        $old = stockBookEntry($deleted, StockMovementType::Shrinkage, '-1.000');
        $deleted->delete();
        $others = stockBookEntry(otherStoreVariant(), StockMovementType::StockIn, '3.000');

        Livewire::test(ListStockMovements::class)
            ->assertCanSeeTableRecords([$stockIn, $old])
            ->assertCanNotSeeTableRecords([$others])
            ->assertSee('Pak Darto');
    });

    it('filters by type, variant, and date', function () {
        $stockIn = stockBookEntry($this->size50, StockMovementType::StockIn, '5.000');
        $shrinkage = stockBookEntry($this->size70, StockMovementType::Shrinkage, '-1.000', ['created_at' => now()->subDays(3)]);

        Livewire::test(ListStockMovements::class)
            ->filterTable('type', StockMovementType::Shrinkage->value)
            ->assertCanSeeTableRecords([$shrinkage])
            ->assertCanNotSeeTableRecords([$stockIn]);

        Livewire::test(ListStockMovements::class)
            ->filterTable('variant_id', $this->size50->id)
            ->assertCanSeeTableRecords([$stockIn])
            ->assertCanNotSeeTableRecords([$shrinkage]);

        Livewire::test(ListStockMovements::class)
            ->filterTable('created_at', ['from' => now()->subDay()->toDateString(), 'until' => now()->toDateString()])
            ->assertCanSeeTableRecords([$stockIn])
            ->assertCanNotSeeTableRecords([$shrinkage]);
    });

    it('records stock in from a supplier', function () {
        Livewire::test(ListStockMovements::class)
            ->callAction('record', data: [
                'variant_id' => $this->size50->id,
                'type' => StockMovementType::StockIn->value,
                'qty' => '5.5',
                'supplier_name' => 'Pak Darto',
                'unit_cost' => 60_000,
                'note' => 'Setoran pagi',
            ])
            ->assertHasNoActionErrors();

        expect($this->size50->fresh()->stock)->toBe('15.500')
            ->and(StockMovement::query()->sole())
            ->type->toBe(StockMovementType::StockIn)
            ->qty->toBe('5.500')
            ->supplier_name->toBe('Pak Darto')
            ->unit_cost->toBe(60_000)
            ->created_by->toBe($this->user->id);

        Livewire::test(ListStockMovements::class)
            ->assertCanSeeTableRecords([StockMovement::query()->sole()]);
    });

    it('records an offline sale, shrinkage, and an adjustment with their sign', function (StockMovementType $type, string $qty, string $stock) {
        Livewire::test(ListStockMovements::class)
            ->callAction('record', data: ['variant_id' => $this->size50->id, 'type' => $type->value, 'qty' => $qty])
            ->assertHasNoActionErrors();

        expect($this->size50->fresh()->stock)->toBe($stock);
    })->with([
        'offline sale' => [StockMovementType::SaleOffline, '2', '8.000'],
        'shrinkage' => [StockMovementType::Shrinkage, '0.5', '9.500'],
        'adjustment down' => [StockMovementType::Adjustment, '-3', '7.000'],
        'adjustment up' => [StockMovementType::Adjustment, '1.25', '11.250'],
    ]);

    it('rejects input on the field at fault and records nothing', function (Closure $data, string $field) {
        Livewire::test(ListStockMovements::class)
            ->callAction('record', data: $data())
            ->assertHasActionErrors([$field]);

        expect(StockMovement::query()->count())->toBe(0)
            ->and($this->size50->fresh()->stock)->toBe('10.000');
    })->with([
        'more than the stock' => [fn () => ['variant_id' => test()->size50->id, 'type' => 'shrinkage', 'qty' => '10.5'], 'qty'],
        'a system type' => [fn () => ['variant_id' => test()->size50->id, 'type' => 'sale_app', 'qty' => '1'], 'type'],
        'a variant of another store' => [fn () => ['variant_id' => otherStoreVariant()->id, 'type' => 'stock_in', 'qty' => '1'], 'variant_id'],
        'zero' => [fn () => ['variant_id' => test()->size50->id, 'type' => 'stock_in', 'qty' => '0'], 'qty'],
        'four decimals' => [fn () => ['variant_id' => test()->size50->id, 'type' => 'stock_in', 'qty' => '1.2345'], 'qty'],
        'a negative unit cost' => [fn () => ['variant_id' => test()->size50->id, 'type' => 'stock_in', 'qty' => '1', 'unit_cost' => -1], 'unit_cost'],
    ]);

    it('has no actions to edit or delete a movement', function () {
        $movement = stockBookEntry($this->size50, StockMovementType::StockIn, '5.000');

        Livewire::test(ListStockMovements::class)
            ->assertActionDoesNotExist(TestAction::make('edit')->table($movement))
            ->assertActionDoesNotExist(TestAction::make('delete')->table($movement));
    });
});
