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

const SELLER_VARIANTS_URL = '/api/v1/seller/variants';

beforeEach(function () {
    $this->travelTo(now()->setDateTime(2026, 10, 12, 6, 0));

    $this->user = User::factory()->create();
    $this->store = Store::factory()->active()->create();
    $this->store->users()->attach($this->user, ['role' => StoreRole::Owner->value]);
    Sanctum::actingAs($this->user);

    $this->product = Product::factory()->for($this->store)->create();
    $this->variant = ProductVariant::factory()->for($this->product)->create([
        'name' => 'Size 50', 'unit' => Unit::Kg, 'price' => 95_000, 'min_qty' => '1.000', 'stock' => '10.000',
    ]);
});

function productVariantsUrl(Product|int $product): string
{
    return '/api/v1/seller/products/'.($product instanceof Product ? $product->id : $product).'/variants';
}

/**
 * A Size 70 variant sold per kg, unless overridden.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function variantPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Size 70',
        'unit' => 'kg',
        'price' => 80_000,
        'min_qty' => '0.5',
        'is_active' => true,
    ], $overrides);
}

it('requires a token', function (string $method, Closure $url) {
    app('auth')->forgetGuards();

    $this->json($method, $url())->assertUnauthorized();
})->with([
    'create' => ['POST', fn () => productVariantsUrl(1)],
    'update' => ['PUT', fn () => SELLER_VARIANTS_URL.'/1'],
    'delete' => ['DELETE', fn () => SELLER_VARIANTS_URL.'/1'],
]);

describe('POST /seller/products/{id}/variants', function () {
    it('adds a variant with its opening stock', function () {
        $response = $this->postJson(productVariantsUrl($this->product), variantPayload(['initial_stock' => '7.5']))
            ->assertCreated()
            ->assertExactJsonStructure(['data' => [
                'id', 'name', 'unit' => ['value', 'label'], 'price', 'min_qty', 'stock', 'stock_confirmed_at', 'is_stock_stale', 'is_active',
            ]])
            ->assertJsonPath('data.name', 'Size 70')
            ->assertJsonPath('data.unit', ['value' => 'kg', 'label' => Unit::Kg->label()])
            ->assertJsonPath('data.price', 80_000)
            ->assertJsonPath('data.min_qty', '0.500')
            ->assertJsonPath('data.stock', '7.500')
            ->assertJsonPath('data.stock_confirmed_at', now()->toIso8601String())
            ->assertJsonPath('data.is_stock_stale', false)
            ->assertJsonPath('data.is_active', true);

        $variant = ProductVariant::query()->whereKey($response->json('data.id'))->sole();
        expect($variant->product_id)->toBe($this->product->id)
            ->and(StockMovement::query()->sole())
            ->variant_id->toBe($variant->id)
            ->type->toBe(StockMovementType::Adjustment)
            ->qty->toBe('7.500')
            ->created_by->toBe($this->user->id);
    });

    it('adds a variant without opening stock', function () {
        $this->postJson(productVariantsUrl($this->product), variantPayload())
            ->assertCreated()
            ->assertJsonPath('data.stock', '0.000')
            ->assertJsonPath('data.stock_confirmed_at', null);

        expect(StockMovement::query()->count())->toBe(0);
    });

    it('lets staff add variants', function () {
        $staff = User::factory()->create();
        $this->store->users()->attach($staff, ['role' => StoreRole::Staff->value]);
        Sanctum::actingAs($staff);

        $this->postJson(productVariantsUrl($this->product), variantPayload())->assertCreated();
    });

    it('rejects input on the field at fault and adds nothing', function (array $overrides, string $field) {
        $this->postJson(productVariantsUrl($this->product), variantPayload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        expect($this->product->variants()->pluck('name')->all())->toBe(['Size 50'])
            ->and(StockMovement::query()->count())->toBe(0);
    })->with([
        'a name already used' => [['name' => 'Size 50'], 'name'],
        'a name already used in other letter case' => [['name' => 'size 50'], 'name'],
        'no name' => [['name' => ''], 'name'],
        'an unknown unit' => [['unit' => 'ton'], 'unit'],
        'a price of 0' => [['price' => 0], 'price'],
        'a price with decimals' => [['price' => 80_000.5], 'price'],
        'no minimum quantity' => [['min_qty' => null], 'min_qty'],
        'a minimum quantity of 0' => [['min_qty' => '0'], 'min_qty'],
        'a negative opening stock' => [['initial_stock' => '-1'], 'initial_stock'],
        'an opening stock with 4 decimals' => [['initial_stock' => '1.2345'], 'initial_stock'],
    ]);

    it('allows the name of a deleted variant', function () {
        $this->variant->delete();

        $this->postJson(productVariantsUrl($this->product), variantPayload(['name' => 'Size 50']))->assertCreated();
    });

    it('returns 404 for a product of another store', function () {
        $other = Product::factory()->create();

        $this->postJson(productVariantsUrl($other), variantPayload())->assertNotFound();

        expect($other->variants()->count())->toBe(0);
    });

    it('returns 404 for a deleted product', function () {
        $this->product->delete();

        $this->postJson(productVariantsUrl($this->product), variantPayload())->assertNotFound();
    });

    it('returns 404 when the user has no store', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(productVariantsUrl($this->product), variantPayload())->assertNotFound();
    });
});

describe('PUT /seller/variants/{id}', function () {
    it('changes the price and the other details', function () {
        $this->putJson(SELLER_VARIANTS_URL.'/'.$this->variant->id, variantPayload([
            'name' => 'Size 50 Super', 'unit' => 'pack', 'price' => 99_000, 'min_qty' => '2', 'is_active' => false,
        ]))
            ->assertOk()
            ->assertJsonPath('data.id', $this->variant->id)
            ->assertJsonPath('data.name', 'Size 50 Super')
            ->assertJsonPath('data.unit.value', 'pack')
            ->assertJsonPath('data.price', 99_000)
            ->assertJsonPath('data.min_qty', '2.000')
            ->assertJsonPath('data.is_active', false);

        expect($this->variant->fresh())
            ->name->toBe('Size 50 Super')
            ->unit->toBe(Unit::Pack)
            ->price->toBe(99_000)
            ->is_active->toBeFalse();
    });

    it('keeps its own name', function () {
        $this->putJson(SELLER_VARIANTS_URL.'/'.$this->variant->id, variantPayload(['name' => 'Size 50', 'price' => 99_000]))
            ->assertOk()
            ->assertJsonPath('data.price', 99_000);
    });

    it('ignores stock in the body: stock changes go through the stock book', function (string $field) {
        $confirmedAt = $this->variant->stock_confirmed_at;

        $this->putJson(SELLER_VARIANTS_URL.'/'.$this->variant->id, variantPayload([$field => '999']))
            ->assertOk()
            ->assertJsonPath('data.stock', '10.000');

        expect($this->variant->fresh())
            ->stock->toBe('10.000')
            ->stock_confirmed_at->toEqual($confirmedAt)
            ->and(StockMovement::query()->count())->toBe(0);
    })->with(['stock', 'initial_stock']);

    it('rejects input on the field at fault and changes nothing', function (array $overrides, string $field) {
        ProductVariant::factory()->for($this->product)->create(['name' => 'Size 70']);

        $this->putJson(SELLER_VARIANTS_URL.'/'.$this->variant->id, variantPayload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        expect($this->variant->fresh())
            ->name->toBe('Size 50')
            ->price->toBe(95_000);
    })->with([
        'the name of another variant' => [['name' => 'SIZE 70'], 'name'],
        'an unknown unit' => [['name' => 'Size 50', 'unit' => 'ton'], 'unit'],
        'a price of 0' => [['name' => 'Size 50', 'price' => 0], 'price'],
        'a minimum quantity of 0' => [['name' => 'Size 50', 'min_qty' => '0'], 'min_qty'],
    ]);

    it('lets staff change variants', function () {
        $staff = User::factory()->create();
        $this->store->users()->attach($staff, ['role' => StoreRole::Staff->value]);
        Sanctum::actingAs($staff);

        $this->putJson(SELLER_VARIANTS_URL.'/'.$this->variant->id, variantPayload())->assertOk();
    });

    it('returns 404 for a variant of another store', function () {
        $other = ProductVariant::factory()->create(['price' => 95_000]);

        $this->putJson(SELLER_VARIANTS_URL.'/'.$other->id, variantPayload())->assertNotFound();

        expect($other->fresh()->price)->toBe(95_000);
    });

    it('returns 404 for a deleted variant or a variant of a deleted product', function (Closure $delete) {
        $delete($this->variant);

        $this->putJson(SELLER_VARIANTS_URL.'/'.$this->variant->id, variantPayload())->assertNotFound();
    })->with([
        'deleted variant' => [fn (ProductVariant $variant) => $variant->delete()],
        'deleted product' => [fn (ProductVariant $variant) => $variant->product->delete()],
    ]);
});

describe('DELETE /seller/variants/{id}', function () {
    it('soft deletes the variant and keeps its stock book', function () {
        ProductVariant::factory()->for($this->product)->create(['name' => 'Size 70']);
        $movement = StockMovement::factory()->for($this->variant, 'variant')->create();

        $this->deleteJson(SELLER_VARIANTS_URL.'/'.$this->variant->id)->assertNoContent();

        $this->assertSoftDeleted($this->variant);
        $this->assertModelExists($movement);
        expect($this->product->variants()->count())->toBe(1);
    });

    it('refuses to delete the last variant of the product', function (Closure $setUp) {
        $setUp($this->product);

        $this->deleteJson(SELLER_VARIANTS_URL.'/'.$this->variant->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['variant' => 'Produk perlu minimal satu ukuran. Matikan produk kalau sedang tidak dijual.']);

        $this->assertNotSoftDeleted($this->variant);
    })->with([
        'only variant' => [fn (Product $product) => null],
        'other variant already deleted' => [fn (Product $product) => ProductVariant::factory()->for($product)->create(['name' => 'Size 70'])->delete()],
    ]);

    it('returns 404 for a variant of another store', function (int $variants) {
        $other = ProductVariant::factory()->create();
        ProductVariant::factory()->count($variants - 1)->for($other->product)->sequence(fn ($sequence) => ['name' => 'Lain '.$sequence->index])->create();

        // The same 404 whether or not it is the last variant, so the count of
        // another store's variants does not leak.
        $this->deleteJson(SELLER_VARIANTS_URL.'/'.$other->id)
            ->assertNotFound()
            ->assertJsonMissingPath('errors');

        $this->assertNotSoftDeleted($other);
    })->with([
        'its last variant' => [1],
        'one of two variants' => [2],
    ]);

    it('returns 404 for a variant already deleted', function () {
        $this->variant->delete();

        $this->deleteJson(SELLER_VARIANTS_URL.'/'.$this->variant->id)->assertNotFound();
    });
});
