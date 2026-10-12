<?php

declare(strict_types=1);

use App\Enums\AttributeType;
use App\Enums\ProductCondition;
use App\Enums\SalesMode;
use App\Enums\StockMovementType;
use App\Enums\StoreRole;
use App\Enums\Unit;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

const PRODUCTS_URL = '/api/v1/seller/products';

/*
 * Kepiting (Berat per ekor: number, required)
 *   └─ Bakau (Jenis kelamin: select Jantan/Betina, required; Catatan: text)
 */
beforeEach(function () {
    $this->kepiting = Category::factory()->create(['name' => 'Kepiting']);
    $this->bakau = Category::factory()->childOf($this->kepiting)->create(['name' => 'Bakau']);

    $this->berat = CategoryAttribute::factory()->for($this->kepiting)->create([
        'name' => 'Berat per ekor', 'type' => AttributeType::Number, 'unit' => 'g', 'is_required' => true,
    ]);
    $this->kelamin = CategoryAttribute::factory()->for($this->bakau)->create([
        'name' => 'Jenis kelamin', 'type' => AttributeType::Select, 'options' => ['Jantan', 'Betina'], 'is_required' => true,
    ]);
    $this->catatan = CategoryAttribute::factory()->for($this->bakau)->create([
        'name' => 'Catatan', 'type' => AttributeType::Text, 'is_required' => false,
    ]);
});

/**
 * Sign in a member of an active store with the given role.
 */
function actingAsProductSeller(StoreRole $role = StoreRole::Owner): Store
{
    $user = User::factory()->create();
    $store = Store::factory()->active()->create();
    $store->users()->attach($user, ['role' => $role->value]);
    Sanctum::actingAs($user);

    return $store;
}

/**
 * A ready stock Kepiting Bakau with its required attributes and two variants,
 * unless overridden.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function productPayload(array $overrides = []): array
{
    $test = test();

    return array_merge([
        'category_id' => $test->bakau->id,
        'name' => 'Kepiting Bakau Super',
        'description' => 'Kepiting bakau hidup dari tambak Muara Angke.',
        'condition' => 'live',
        'sales_mode' => 'ready_stock',
        'shelf_life_days' => 2,
        'storage_instructions' => 'Simpan di tempat lembap dan sejuk.',
        'is_active' => true,
        'attributes' => [
            $test->berat->id => '350',
            $test->kelamin->id => 'Jantan',
        ],
        'variants' => [
            ['name' => 'Jantan 300-400 g', 'unit' => 'kg', 'price' => 185_000, 'min_qty' => '0.5', 'initial_stock' => '12.5'],
            ['name' => 'Jantan 500 g up', 'unit' => 'kg', 'price' => 240_000, 'min_qty' => '1', 'initial_stock' => '0'],
        ],
    ], $overrides);
}

/**
 * A product of the store in Kepiting Bakau with its required attribute values.
 *
 * @param  array<string, mixed>  $attributes
 */
function bakauProduct(Store $store, array $attributes = []): Product
{
    $test = test();
    $product = Product::factory()->for($store)->for($test->bakau)->create($attributes);
    $product->attributeValues()->create(['category_attribute_id' => $test->berat->id, 'value' => '350']);
    $product->attributeValues()->create(['category_attribute_id' => $test->kelamin->id, 'value' => 'Jantan']);

    return $product;
}

it('requires a token', function (string $method, string $url) {
    $this->json($method, $url)->assertUnauthorized();
})->with([
    'list' => ['GET', PRODUCTS_URL],
    'create' => ['POST', PRODUCTS_URL],
    'show' => ['GET', PRODUCTS_URL.'/1'],
    'update' => ['PUT', PRODUCTS_URL.'/1'],
    'delete' => ['DELETE', PRODUCTS_URL.'/1'],
]);

describe('GET /seller/products', function () {
    it('lists only the products of the user’s store, newest first', function () {
        $store = actingAsProductSeller();
        $older = Product::factory()->for($store)->create(['created_at' => now()->subDay()]);
        $newer = Product::factory()->for($store)->create();
        Product::factory()->create();

        $this->getJson(PRODUCTS_URL)
            ->assertOk()
            ->assertJsonPath('data.*.id', [$newer->id, $older->id])
            ->assertJsonPath('meta.per_page', 20);
    });

    it('pages by 20', function () {
        $store = actingAsProductSeller();
        Product::factory()->for($store)->count(21)->create();

        $this->getJson(PRODUCTS_URL)->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('meta.total', 21);
        $this->getJson(PRODUCTS_URL.'?page=2')->assertOk()->assertJsonCount(1, 'data');
    });

    it('filters by status', function (string $status, string $expected) {
        $store = actingAsProductSeller();
        $products = [
            'active' => Product::factory()->for($store)->create(),
            'inactive' => Product::factory()->for($store)->inactive()->create(),
            'blocked' => Product::factory()->for($store)->blocked()->create(),
        ];

        $this->getJson(PRODUCTS_URL.'?status='.$status)
            ->assertOk()
            ->assertJsonPath('data.*.id', [$products[$expected]->id]);
    })->with([
        'active' => ['active', 'active'],
        'inactive' => ['inactive', 'inactive'],
        'blocked' => ['blocked', 'blocked'],
    ]);

    it('searches by name without regard to letter case', function () {
        $store = actingAsProductSeller();
        $bakau = Product::factory()->for($store)->create(['name' => 'Kepiting Bakau Super']);
        Product::factory()->for($store)->create(['name' => 'Udang Vaname']);

        $this->getJson(PRODUCTS_URL.'?search=bakau')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$bakau->id]);
    });

    it('rejects an unknown status', function () {
        actingAsProductSeller();

        $this->getJson(PRODUCTS_URL.'?status=deleted')->assertUnprocessable()->assertJsonValidationErrors('status');
    });

    it('uses the same number of queries for 3 and for 20 products', function () {
        $queriesFor = function (int $count): int {
            $store = actingAsProductSeller();
            Product::factory()->for($store)->count($count)->create()->each(function (Product $product) {
                ProductVariant::factory()->for($product)->count(2)->create();
                ProductImage::factory()->for($product)->create();
            });

            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson(PRODUCTS_URL)->assertOk();
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        expect($queriesFor(3))->toBe($queriesFor(20));
    });

    it('returns 404 when the user has no store', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(PRODUCTS_URL)->assertNotFound();
    });
});

describe('POST /seller/products', function () {
    it('creates the product with its variants and opening stock', function () {
        $store = actingAsProductSeller();

        $response = $this->postJson(PRODUCTS_URL, productPayload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Kepiting Bakau Super')
            ->assertJsonPath('data.slug', 'kepiting-bakau-super')
            ->assertJsonPath('data.condition', ['value' => 'live', 'label' => ProductCondition::Live->label()])
            ->assertJsonPath('data.sales_mode', ['value' => 'ready_stock', 'label' => SalesMode::ReadyStock->label()])
            ->assertJsonPath('data.category', ['id' => $this->bakau->id, 'name' => 'Bakau'])
            ->assertJsonPath('data.blocked', false)
            ->assertJsonPath('data.block_reason', null)
            ->assertJsonPath('data.variants.*.name', ['Jantan 300-400 g', 'Jantan 500 g up'])
            ->assertJsonPath('data.variants.0.unit', ['value' => 'kg', 'label' => Unit::Kg->label()])
            ->assertJsonPath('data.variants.0.price', 185_000)
            ->assertJsonPath('data.variants.0.stock', '12.500')
            ->assertJsonPath('data.variants.0.is_stock_stale', false)
            ->assertJsonPath('data.variants.1.stock', '0.000')
            ->assertJsonPath('data.variants.1.is_stock_stale', true)
            ->assertJsonPath('data.images', []);

        $product = Product::query()->sole();
        expect($product->store_id)->toBe($store->id)
            ->and($product->variants()->count())->toBe(2)
            ->and(StockMovement::query()->sole())
            ->type->toBe(StockMovementType::Adjustment)
            ->qty->toBe('12.500');
        expect($response->json('data.id'))->toBe($product->id);
    });

    it('shows the effective attributes with their values', function () {
        actingAsProductSeller();

        $this->postJson(PRODUCTS_URL, productPayload())
            ->assertCreated()
            ->assertJsonPath('data.attributes', [
                ['id' => $this->berat->id, 'name' => 'Berat per ekor', 'type' => ['value' => 'number', 'label' => AttributeType::Number->label()], 'options' => null, 'unit' => 'g', 'is_required' => true, 'value' => '350'],
                ['id' => $this->kelamin->id, 'name' => 'Jenis kelamin', 'type' => ['value' => 'select', 'label' => AttributeType::Select->label()], 'options' => ['Jantan', 'Betina'], 'unit' => null, 'is_required' => true, 'value' => 'Jantan'],
                ['id' => $this->catatan->id, 'name' => 'Catatan', 'type' => ['value' => 'text', 'label' => AttributeType::Text->label()], 'options' => null, 'unit' => null, 'is_required' => false, 'value' => null],
            ]);
    });

    it('lets staff add products', function () {
        actingAsProductSeller(StoreRole::Staff);

        $this->postJson(PRODUCTS_URL, productPayload())->assertCreated();
    });

    it('rejects input on the field at fault and saves nothing', function (Closure $overrides, string $field) {
        actingAsProductSeller();

        $this->postJson(PRODUCTS_URL, productPayload($overrides()))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        expect(Product::withTrashed()->count())->toBe(0)
            ->and(ProductVariant::withTrashed()->count())->toBe(0);
    })->with([
        'no variants' => [fn () => ['variants' => []], 'variants'],
        'variants left out' => [fn () => ['variants' => null], 'variants'],
        'a variant without a name' => [fn () => ['variants' => [['unit' => 'kg', 'price' => 185_000, 'min_qty' => '1']]], 'variants.0.name'],
        'a variant with an unknown unit' => [fn () => ['variants' => [['name' => 'Grade A', 'unit' => 'ton', 'price' => 185_000, 'min_qty' => '1']]], 'variants.0.unit'],
        'two variants with the same name' => [fn () => ['variants' => [
            ['name' => 'Grade A', 'unit' => 'kg', 'price' => 185_000, 'min_qty' => '1'],
            ['name' => 'grade a', 'unit' => 'kg', 'price' => 190_000, 'min_qty' => '1'],
        ]], 'variants.1.name'],
        'an unknown condition' => [fn () => ['condition' => 'rotten'], 'condition'],
        'a pre-order without a quota' => [fn () => ['sales_mode' => 'pre_order', 'lead_time_days' => 2, 'cutoff_time' => '15:00'], 'daily_quota'],
    ]);

    it('reports an empty required attribute on its key', function () {
        actingAsProductSeller();

        $this->postJson(PRODUCTS_URL, productPayload(['attributes' => [$this->berat->id => '', $this->kelamin->id => 'Jantan']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attributes.'.$this->berat->id);

        expect(Product::withTrashed()->count())->toBe(0);
    });

    it('returns 404 when the user has no store', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(PRODUCTS_URL, productPayload())->assertNotFound();
    });
});

describe('GET /seller/products/{id}', function () {
    it('shows a product of the user’s store with its photos', function () {
        $store = actingAsProductSeller();
        $product = bakauProduct($store);
        $image = ProductImage::factory()->for($product)->create();

        $this->getJson(PRODUCTS_URL.'/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.images', [[
                'id' => $image->id,
                'url' => Storage::disk('public')->url($image->path),
                'thumbnail_url' => Storage::disk('public')->url($image->thumbnailPath()),
                'sort_order' => 1,
            ]])
            ->assertJsonPath('data.attributes.0.value', '350');
    });

    it('shows a block and its reason', function () {
        $store = actingAsProductSeller();
        $product = bakauProduct($store, ['blocked_at' => now(), 'block_reason' => 'Foto tidak sesuai produk.']);

        $this->getJson(PRODUCTS_URL.'/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.blocked', true)
            ->assertJsonPath('data.block_reason', 'Foto tidak sesuai produk.');
    });
});

describe('PUT /seller/products/{id}', function () {
    it('changes the name and attributes and keeps the slug', function () {
        $store = actingAsProductSeller();
        $product = bakauProduct($store, ['name' => 'Kepiting Bakau', 'slug' => 'kepiting-bakau']);

        $this->putJson(PRODUCTS_URL.'/'.$product->id, productPayload([
            'name' => 'Kepiting Bakau Jumbo',
            'attributes' => [$this->berat->id => '500', $this->kelamin->id => 'Betina'],
        ]))
            ->assertOk()
            ->assertJsonPath('data.name', 'Kepiting Bakau Jumbo')
            ->assertJsonPath('data.slug', 'kepiting-bakau')
            ->assertJsonPath('data.attributes.0.value', '500')
            ->assertJsonPath('data.attributes.1.value', 'Betina');
    });

    it('leaves the variants alone', function () {
        $store = actingAsProductSeller();
        $product = bakauProduct($store);
        $variant = ProductVariant::factory()->for($product)->create();

        $this->putJson(PRODUCTS_URL.'/'.$product->id, productPayload())->assertOk();

        expect($product->variants()->pluck('id')->all())->toBe([$variant->id]);
    });

    it('lets staff edit products', function () {
        $store = actingAsProductSeller(StoreRole::Staff);
        $product = bakauProduct($store);

        $this->putJson(PRODUCTS_URL.'/'.$product->id, productPayload(['name' => 'Kepiting Bakau Jumbo']))->assertOk();
    });

    it('keeps a blocked product from being turned on by the seller', function () {
        $store = actingAsProductSeller();
        $product = bakauProduct($store, ['is_active' => false, 'blocked_at' => now(), 'block_reason' => 'Foto tidak sesuai produk.']);

        $this->putJson(PRODUCTS_URL.'/'.$product->id, productPayload(['is_active' => true]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('is_active');

        expect($product->fresh()->is_active)->toBeFalse();
    });

    it('lets the seller edit a blocked product that stays off', function () {
        $store = actingAsProductSeller();
        $product = bakauProduct($store, ['is_active' => false, 'blocked_at' => now()]);

        $this->putJson(PRODUCTS_URL.'/'.$product->id, productPayload(['name' => 'Kepiting Bakau Asli', 'is_active' => false]))
            ->assertOk()
            ->assertJsonPath('data.name', 'Kepiting Bakau Asli')
            ->assertJsonPath('data.blocked', true);
    });

    it('rejects an empty required attribute', function () {
        $store = actingAsProductSeller();
        $product = bakauProduct($store);

        $this->putJson(PRODUCTS_URL.'/'.$product->id, productPayload(['attributes' => [$this->berat->id => '350', $this->kelamin->id => '']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attributes.'.$this->kelamin->id);
    });
});

describe('DELETE /seller/products/{id}', function () {
    it('soft deletes the product and drops it from the list', function () {
        $store = actingAsProductSeller();
        $product = bakauProduct($store);

        $this->deleteJson(PRODUCTS_URL.'/'.$product->id)->assertNoContent();

        expect(Product::withTrashed()->find($product->id)->trashed())->toBeTrue();
        $this->getJson(PRODUCTS_URL)->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(PRODUCTS_URL.'/'.$product->id)->assertNotFound();
    });
});

describe('a product of another store', function () {
    it('is a 404', function (string $method) {
        actingAsProductSeller();
        $other = bakauProduct(Store::factory()->active()->create());

        $this->json($method, PRODUCTS_URL.'/'.$other->id, $method === 'PUT' ? productPayload() : [])->assertNotFound();

        expect(Product::query()->find($other->id))->not->toBeNull();
    })->with(['GET', 'PUT', 'DELETE']);
});
