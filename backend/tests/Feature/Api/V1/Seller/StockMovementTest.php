<?php

declare(strict_types=1);

use App\Actions\RecordStockMovement;
use App\Enums\StockMovementType;
use App\Enums\StoreRole;
use App\Enums\Unit;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

const STOCK_MOVEMENTS_URL = '/api/v1/seller/stock-movements';

beforeEach(function () {
    $this->travelTo(now()->setDateTime(2026, 10, 12, 7, 0));
});

/**
 * Sign in a member of an active store with the given role, and give the store
 * a product with a variant holding 20 kg.
 *
 * @return array{User, Store, ProductVariant}
 */
function actingAsStockKeeper(StoreRole $role = StoreRole::Owner): array
{
    $user = User::factory()->create(['name' => 'Bu Sari']);
    $store = Store::factory()->active()->create();
    $store->users()->attach($user, ['role' => $role->value]);
    Sanctum::actingAs($user);

    $product = Product::factory()->for($store)->create(['name' => 'Udang Vaname']);
    $variant = ProductVariant::factory()->for($product)->create(['name' => 'Size 50', 'stock' => '20.000']);

    return [$user, $store, $variant];
}

/**
 * A movement recorded through the stock book.
 */
function recordMovement(ProductVariant $variant, StockMovementType $type, string $qty, ?User $by = null): StockMovement
{
    return app(RecordStockMovement::class)->handle($variant, $type, $qty, $by);
}

it('requires a token', function (string $method) {
    $this->json($method, STOCK_MOVEMENTS_URL)->assertUnauthorized();
})->with(['GET', 'POST']);

describe('POST /seller/stock-movements', function () {
    it('records stock in from a supplier and adds to the stock', function () {
        [$user, , $variant] = actingAsStockKeeper();

        $this->postJson(STOCK_MOVEMENTS_URL, [
            'variant_id' => $variant->id,
            'type' => 'stock_in',
            'qty' => '10',
            'supplier_name' => 'Pak Darto',
            'unit_cost' => 60_000,
            'note' => 'Setoran pagi',
        ])
            ->assertCreated()
            ->assertJsonPath('data.type', ['value' => 'stock_in', 'label' => StockMovementType::StockIn->label()])
            ->assertJsonPath('data.qty', '10.000')
            ->assertJsonPath('data.stock_after', '30.000')
            ->assertJsonPath('data.variant', [
                'id' => $variant->id,
                'name' => 'Size 50',
                'unit' => ['value' => 'kg', 'label' => Unit::Kg->label()],
            ])
            ->assertJsonPath('data.product', ['id' => $variant->product_id, 'name' => 'Udang Vaname'])
            ->assertJsonPath('data.supplier_name', 'Pak Darto')
            ->assertJsonPath('data.unit_cost', 60_000)
            ->assertJsonPath('data.note', 'Setoran pagi')
            ->assertJsonPath('data.created_by', ['id' => $user->id, 'name' => 'Bu Sari'])
            ->assertJsonPath('data.created_at', now()->toIso8601String());

        expect($variant->fresh()->stock)->toBe('30.000')
            ->and(StockMovement::query()->sole()->created_by)->toBe($user->id);
    });

    it('takes sales and shrinkage off the stock', function (string $type, string $qty, string $signed, string $after) {
        [, , $variant] = actingAsStockKeeper();

        $this->postJson(STOCK_MOVEMENTS_URL, ['variant_id' => $variant->id, 'type' => $type, 'qty' => $qty])
            ->assertCreated()
            ->assertJsonPath('data.qty', $signed)
            ->assertJsonPath('data.stock_after', $after);

        expect($variant->fresh()->stock)->toBe($after);
    })->with([
        'offline sale of 2 kg' => ['sale_offline', '2', '-2.000', '18.000'],
        'shrinkage of 0.5 kg' => ['shrinkage', '0.5', '-0.500', '19.500'],
        'adjustment down' => ['adjustment', '-1.25', '-1.250', '18.750'],
        'adjustment up' => ['adjustment', '3', '3.000', '23.000'],
    ]);

    it('accepts the quantity as a JSON number', function () {
        [, , $variant] = actingAsStockKeeper();

        $this->postJson(STOCK_MOVEMENTS_URL, ['variant_id' => $variant->id, 'type' => 'shrinkage', 'qty' => 0.5])
            ->assertCreated()
            ->assertJsonPath('data.qty', '-0.500');
    });

    it('lets staff record stock', function () {
        [, , $variant] = actingAsStockKeeper(StoreRole::Staff);

        $this->postJson(STOCK_MOVEMENTS_URL, ['variant_id' => $variant->id, 'type' => 'sale_offline', 'qty' => '1'])
            ->assertCreated();
    });

    it('rejects taking more than the stock and leaves it unchanged', function () {
        [, , $variant] = actingAsStockKeeper();

        $this->postJson(STOCK_MOVEMENTS_URL, ['variant_id' => $variant->id, 'type' => 'sale_offline', 'qty' => '25'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('qty');

        expect($variant->fresh()->stock)->toBe('20.000')
            ->and(StockMovement::query()->count())->toBe(0);
    });

    it('rejects input on the field at fault', function (Closure $payload, string $field) {
        [, , $variant] = actingAsStockKeeper();

        $this->postJson(STOCK_MOVEMENTS_URL, $payload($variant))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        expect($variant->fresh()->stock)->toBe('20.000')
            ->and(StockMovement::query()->count())->toBe(0);
    })->with([
        'an app sale, which only orders record' => [fn ($variant) => ['variant_id' => $variant->id, 'type' => 'sale_app', 'qty' => '1'], 'type'],
        'a cancel return, which only orders record' => [fn ($variant) => ['variant_id' => $variant->id, 'type' => 'cancel_return', 'qty' => '1'], 'type'],
        'an unknown type' => [fn ($variant) => ['variant_id' => $variant->id, 'type' => 'gift', 'qty' => '1'], 'type'],
        'no quantity' => [fn ($variant) => ['variant_id' => $variant->id, 'type' => 'stock_in'], 'qty'],
        'a zero quantity' => [fn ($variant) => ['variant_id' => $variant->id, 'type' => 'stock_in', 'qty' => '0'], 'qty'],
        'four decimals' => [fn ($variant) => ['variant_id' => $variant->id, 'type' => 'stock_in', 'qty' => '1.0005'], 'qty'],
        'a negative stock in' => [fn ($variant) => ['variant_id' => $variant->id, 'type' => 'stock_in', 'qty' => '-1'], 'qty'],
        'a supplier on a sale' => [fn ($variant) => ['variant_id' => $variant->id, 'type' => 'sale_offline', 'qty' => '1', 'supplier_name' => 'Pak Darto'], 'supplier_name'],
        'a negative unit cost' => [fn ($variant) => ['variant_id' => $variant->id, 'type' => 'stock_in', 'qty' => '1', 'unit_cost' => -1], 'unit_cost'],
        'no variant' => [fn ($variant) => ['type' => 'stock_in', 'qty' => '1'], 'variant_id'],
    ]);

    it('rejects a variant of another store', function () {
        actingAsStockKeeper();
        $other = ProductVariant::factory()->create(['stock' => '20.000']);

        $this->postJson(STOCK_MOVEMENTS_URL, ['variant_id' => $other->id, 'type' => 'sale_offline', 'qty' => '1'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('variant_id');

        expect($other->fresh()->stock)->toBe('20.000');
    });

    it('rejects a deleted variant', function () {
        [, , $variant] = actingAsStockKeeper();
        $variant->delete();

        $this->postJson(STOCK_MOVEMENTS_URL, ['variant_id' => $variant->id, 'type' => 'stock_in', 'qty' => '1'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('variant_id');
    });

    it('returns 404 when the user has no store', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(STOCK_MOVEMENTS_URL, ['variant_id' => 1, 'type' => 'stock_in', 'qty' => '1'])->assertNotFound();
    });
});

describe('GET /seller/stock-movements', function () {
    it('lists the store’s movements, newest first', function () {
        [$user, , $variant] = actingAsStockKeeper();
        $first = recordMovement($variant, StockMovementType::StockIn, '5', $user);
        $this->travel(1)->minutes();
        $second = recordMovement($variant, StockMovementType::SaleOffline, '2', $user);
        recordMovement(ProductVariant::factory()->create(), StockMovementType::StockIn, '1');

        $this->getJson(STOCK_MOVEMENTS_URL)
            ->assertOk()
            ->assertJsonPath('data.*.id', [$second->id, $first->id])
            ->assertJsonPath('meta.per_page', 30);
    });

    it('pages by 30', function () {
        [, , $variant] = actingAsStockKeeper();
        foreach (range(1, 31) as $i) {
            recordMovement($variant, StockMovementType::StockIn, '1');
        }

        $this->getJson(STOCK_MOVEMENTS_URL)->assertOk()->assertJsonCount(30, 'data')->assertJsonPath('meta.total', 31);
    });

    it('filters by type', function () {
        [, , $variant] = actingAsStockKeeper();
        $in = recordMovement($variant, StockMovementType::StockIn, '5');
        recordMovement($variant, StockMovementType::SaleOffline, '2');

        $this->getJson(STOCK_MOVEMENTS_URL.'?type=stock_in')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$in->id]);
    });

    it('filters by variant', function () {
        [, $store, $variant] = actingAsStockKeeper();
        $other = ProductVariant::factory()->for(Product::factory()->for($store))->create();
        recordMovement($variant, StockMovementType::StockIn, '5');
        $wanted = recordMovement($other, StockMovementType::StockIn, '5');

        $this->getJson(STOCK_MOVEMENTS_URL.'?variant_id='.$other->id)
            ->assertOk()
            ->assertJsonPath('data.*.id', [$wanted->id]);
    });

    it('filters by a date range, both days included', function () {
        [, , $variant] = actingAsStockKeeper();
        $this->travelTo(now()->setDateTime(2026, 10, 9, 23, 59));
        recordMovement($variant, StockMovementType::StockIn, '1');
        $this->travelTo(now()->setDateTime(2026, 10, 10, 0, 0));
        $start = recordMovement($variant, StockMovementType::StockIn, '1');
        $this->travelTo(now()->setDateTime(2026, 10, 11, 23, 59));
        $end = recordMovement($variant, StockMovementType::StockIn, '1');
        $this->travelTo(now()->setDateTime(2026, 10, 12, 0, 0));
        recordMovement($variant, StockMovementType::StockIn, '1');

        $this->getJson(STOCK_MOVEMENTS_URL.'?from=2026-10-10&to=2026-10-11')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$end->id, $start->id]);
    });

    it('rejects a range that ends before it starts', function () {
        actingAsStockKeeper();

        $this->getJson(STOCK_MOVEMENTS_URL.'?from=2026-10-11&to=2026-10-10')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');
    });

    it('keeps the movements of a deleted variant and product in the history', function () {
        [, , $variant] = actingAsStockKeeper();
        $movement = recordMovement($variant, StockMovementType::StockIn, '5');
        $variant->delete();
        Product::query()->find($variant->product_id)?->delete();

        $this->getJson(STOCK_MOVEMENTS_URL)
            ->assertOk()
            ->assertJsonPath('data.*.id', [$movement->id])
            ->assertJsonPath('data.0.variant.name', 'Size 50')
            ->assertJsonPath('data.0.variant.unit.value', 'kg')
            ->assertJsonPath('data.0.product.name', 'Udang Vaname');
    });

    it('shows a system movement without a recorder', function () {
        [, , $variant] = actingAsStockKeeper();
        recordMovement($variant, StockMovementType::StockIn, '5');

        $this->getJson(STOCK_MOVEMENTS_URL)->assertOk()->assertJsonPath('data.0.created_by', null);
    });

    it('lets staff read the stock book', function () {
        [, , $variant] = actingAsStockKeeper(StoreRole::Staff);
        recordMovement($variant, StockMovementType::StockIn, '5');

        $this->getJson(STOCK_MOVEMENTS_URL)->assertOk()->assertJsonCount(1, 'data');
    });

    it('uses the same number of queries for 3 and for 30 movements', function () {
        // Each movement on its own product and variant, recorded by a member.
        $queriesFor = function (int $count): int {
            [$user, $store] = actingAsStockKeeper();
            foreach (range(1, $count) as $i) {
                $variant = ProductVariant::factory()->for(Product::factory()->for($store))->create();
                recordMovement($variant, StockMovementType::StockIn, '1', $user);
            }

            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson(STOCK_MOVEMENTS_URL)->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        // Warm-up: the first request also loads the permission cache.
        $queriesFor(1);

        expect($queriesFor(3))->toBe($queriesFor(30));
    });

    it('returns 404 when the user has no store', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(STOCK_MOVEMENTS_URL)->assertNotFound();
    });
});
