<?php

declare(strict_types=1);

use App\Enums\StockMovementType;
use App\Enums\StoreRole;
use App\Enums\Unit;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

const STOCK_TODAY_URL = '/api/v1/seller/stock/today';
const STOCK_CONFIRM_URL = '/api/v1/seller/stock/confirm';

/*
 * The store has two products:
 *   Udang Vaname: Size 50 (10 kg), Size 70 (4 kg), both confirmed 25 hours ago
 *   Cumi Segar: Besar (6 kg, never confirmed)
 */
beforeEach(function () {
    $this->travelTo(now()->setDateTime(2026, 10, 12, 6, 0));
    $yesterday = now()->subHours(25);

    $this->user = User::factory()->create();
    $this->store = Store::factory()->active()->create();
    $this->store->users()->attach($this->user, ['role' => StoreRole::Owner->value]);
    Sanctum::actingAs($this->user);

    $this->udang = Product::factory()->for($this->store)->create(['name' => 'Udang Vaname']);
    $this->size50 = ProductVariant::factory()->for($this->udang)->create(['name' => 'Size 50', 'stock' => '10.000', 'stock_confirmed_at' => $yesterday]);
    $this->size70 = ProductVariant::factory()->for($this->udang)->create(['name' => 'Size 70', 'stock' => '4.000', 'stock_confirmed_at' => $yesterday]);

    $this->cumi = Product::factory()->for($this->store)->create(['name' => 'Cumi Segar']);
    $this->besar = ProductVariant::factory()->for($this->cumi)->create(['name' => 'Besar', 'unit' => Unit::Kg, 'stock' => '6.000', 'stock_confirmed_at' => null]);
});

it('requires a token', function (string $method, string $url) {
    app('auth')->forgetGuards();

    $this->json($method, $url)->assertUnauthorized();
})->with([
    'today' => ['GET', STOCK_TODAY_URL],
    'confirm' => ['POST', STOCK_CONFIRM_URL],
]);

describe('GET /seller/stock/today', function () {
    it('groups the active variants by product', function () {
        $this->getJson(STOCK_TODAY_URL)
            ->assertOk()
            ->assertExactJsonStructure(['data' => ['*' => [
                'id', 'name', 'variants' => ['*' => ['id', 'name', 'unit' => ['value', 'label'], 'stock', 'stock_confirmed_at', 'is_stock_stale']],
            ]]])
            ->assertJsonPath('data.*.name', ['Cumi Segar', 'Udang Vaname'])
            ->assertJsonPath('data.1.variants.*.name', ['Size 50', 'Size 70'])
            ->assertJsonPath('data.1.variants.0.stock', '10.000')
            ->assertJsonPath('data.1.variants.0.unit', ['value' => 'kg', 'label' => Unit::Kg->label()])
            ->assertJsonPath('data.1.variants.0.stock_confirmed_at', now()->subHours(25)->toIso8601String());
    });

    it('marks stock that is stale', function () {
        $this->size70->forceFill(['stock_confirmed_at' => now()->subHours(2)])->save();

        $this->getJson(STOCK_TODAY_URL)
            ->assertOk()
            ->assertJsonPath('data.0.variants.0.is_stock_stale', true)
            ->assertJsonPath('data.1.variants.0.is_stock_stale', true)
            ->assertJsonPath('data.1.variants.1.is_stock_stale', false);
    });

    it('leaves out inactive and deleted products and variants', function () {
        Product::factory()->for($this->store)->inactive()->create()
            ->variants()->save(ProductVariant::factory()->make());
        Product::factory()->for($this->store)->create(['deleted_at' => now()])
            ->variants()->save(ProductVariant::factory()->make());
        ProductVariant::factory()->for($this->udang)->create(['is_active' => false]);
        ProductVariant::factory()->for($this->udang)->create(['deleted_at' => now()]);
        ProductVariant::factory()->for(Product::factory())->create();

        $this->getJson(STOCK_TODAY_URL)
            ->assertOk()
            ->assertJsonPath('data.*.id', [$this->cumi->id, $this->udang->id])
            ->assertJsonPath('data.1.variants.*.id', [$this->size50->id, $this->size70->id]);
    });

    it('lets staff see the stock', function () {
        $staff = User::factory()->create();
        $this->store->users()->attach($staff, ['role' => StoreRole::Staff->value]);
        Sanctum::actingAs($staff);

        $this->getJson(STOCK_TODAY_URL)->assertOk()->assertJsonCount(2, 'data');
    });

    it('returns 404 when the user has no store', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(STOCK_TODAY_URL)->assertNotFound();
    });
});

describe('POST /seller/stock/confirm', function () {
    it('confirms every variant unchanged with "same as yesterday"', function () {
        $this->postJson(STOCK_CONFIRM_URL, ['confirm_all' => true])->assertOk();

        foreach ([$this->size50, $this->size70, $this->besar] as $variant) {
            expect($variant->fresh()->stock_confirmed_at->toDateTimeString())->toBe('2026-10-12 06:00:00');
        }
        expect(StockMovement::query()->count())->toBe(0)
            ->and($this->size50->fresh()->stock)->toBe('10.000');
    });

    it('adjusts a changed variant by the difference and confirms the rest', function () {
        $this->postJson(STOCK_CONFIRM_URL, [
            'variants' => [['id' => $this->size50->id, 'stock' => '7.5']],
            'confirm_all' => true,
        ])->assertOk();

        expect(StockMovement::query()->sole())
            ->variant_id->toBe($this->size50->id)
            ->type->toBe(StockMovementType::Adjustment)
            ->qty->toBe('-2.500')
            ->stock_after->toBe('7.500')
            ->note->toBe('Konfirmasi harian')
            ->created_by->toBe($this->user->id);
        expect($this->size50->fresh()->stock)->toBe('7.500');

        foreach ([$this->size50, $this->size70, $this->besar] as $variant) {
            expect($variant->fresh()->stock_confirmed_at->toDateTimeString())->toBe('2026-10-12 06:00:00');
        }
    });

    it('confirms only the listed variants without "confirm all"', function () {
        $this->postJson(STOCK_CONFIRM_URL, ['variants' => [['id' => $this->besar->id, 'stock' => 8]]])->assertOk();

        expect($this->besar->fresh())
            ->stock->toBe('8.000')
            ->stock_confirmed_at->not->toBeNull()
            ->and($this->size50->fresh()->stock_confirmed_at->toDateTimeString())->toBe('2026-10-11 05:00:00')
            ->and(StockMovement::query()->sole()->qty)->toBe('2.000');
    });

    it('confirms a variant whose count did not change without a movement', function () {
        $this->postJson(STOCK_CONFIRM_URL, ['variants' => [['id' => $this->size50->id, 'stock' => '10.000']]])->assertOk();

        expect(StockMovement::query()->count())->toBe(0)
            ->and($this->size50->fresh()->stock_confirmed_at->toDateTimeString())->toBe('2026-10-12 06:00:00');
    });

    it('sets the counted stock even when sales happened since the screen was loaded', function () {
        // A sale lowers the stock to 8 after the seller saw 10 and counted 7.
        $this->size50->forceFill(['stock' => '8.000'])->save();

        $this->postJson(STOCK_CONFIRM_URL, ['variants' => [['id' => $this->size50->id, 'stock' => '7']]])->assertOk();

        expect($this->size50->fresh()->stock)->toBe('7.000')
            ->and(StockMovement::query()->sole()->qty)->toBe('-1.000');
    });

    it('returns the confirmed stock grouped by product', function () {
        $this->postJson(STOCK_CONFIRM_URL, ['variants' => [['id' => $this->size50->id, 'stock' => '7.5']], 'confirm_all' => true])
            ->assertOk()
            ->assertJsonPath('data.1.variants.0.stock', '7.500')
            ->assertJsonPath('data.1.variants.0.is_stock_stale', false)
            ->assertJsonPath('data.0.variants.0.is_stock_stale', false);
    });

    it('lets staff confirm the stock', function () {
        $staff = User::factory()->create();
        $this->store->users()->attach($staff, ['role' => StoreRole::Staff->value]);
        Sanctum::actingAs($staff);

        $this->postJson(STOCK_CONFIRM_URL, ['confirm_all' => true])->assertOk();
    });

    it('rejects input and changes nothing', function (Closure $payload, string $field) {
        $this->postJson(STOCK_CONFIRM_URL, $payload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        expect(StockMovement::query()->count())->toBe(0)
            ->and($this->size50->fresh())
            ->stock->toBe('10.000')
            ->stock_confirmed_at->toDateTimeString()->toBe('2026-10-11 05:00:00')
            ->and($this->besar->fresh()->stock_confirmed_at)->toBeNull();
    })->with([
        'a negative stock' => [fn () => ['variants' => [['id' => test()->size50->id, 'stock' => '-1']]], 'variants.0.stock'],
        'four decimals' => [fn () => ['variants' => [['id' => test()->size50->id, 'stock' => '1.0005']]], 'variants.0.stock'],
        'no stock' => [fn () => ['variants' => [['id' => test()->size50->id]]], 'variants.0.stock'],
        'nothing to confirm' => [fn () => ['variants' => [], 'confirm_all' => false], 'variants'],
        'the same variant twice' => [fn () => ['variants' => [
            ['id' => test()->size50->id, 'stock' => '7'],
            ['id' => test()->size50->id, 'stock' => '8'],
        ]], 'variants.0.id'],
        'a variant of another store after a valid one' => [fn () => ['variants' => [
            ['id' => test()->size50->id, 'stock' => '7'],
            ['id' => ProductVariant::factory()->create()->id, 'stock' => '3'],
        ], 'confirm_all' => true], 'variants.1.id'],
        'an inactive variant after a valid one' => [fn () => ['variants' => [
            ['id' => test()->size50->id, 'stock' => '7'],
            ['id' => ProductVariant::factory()->for(test()->udang)->create(['is_active' => false])->id, 'stock' => '3'],
        ]], 'variants.1.id'],
    ]);

    it('leaves a variant of another store untouched', function () {
        $other = ProductVariant::factory()->create(['stock' => '5.000', 'stock_confirmed_at' => null]);

        $this->postJson(STOCK_CONFIRM_URL, ['variants' => [['id' => $other->id, 'stock' => '1']]])->assertUnprocessable();

        expect($other->fresh())
            ->stock->toBe('5.000')
            ->stock_confirmed_at->toBeNull();
    });

    it('returns 404 when the user has no store', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(STOCK_CONFIRM_URL, ['confirm_all' => true])->assertNotFound();
    });
});
