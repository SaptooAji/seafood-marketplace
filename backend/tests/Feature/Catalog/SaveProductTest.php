<?php

declare(strict_types=1);

use App\Actions\DeleteProduct;
use App\Actions\SaveProduct;
use App\Data\ProductData;
use App\Enums\AttributeType;
use App\Enums\ProductCondition;
use App\Enums\SalesMode;
use App\Exceptions\InvalidProductData;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Product;
use App\Models\Store;

/*
 * Kepiting (Berat per ekor: number, required)
 *   ├─ Bakau (Jenis kelamin: select Jantan/Betina, required; Catatan: text)
 *   └─ Soka
 * Cumi
 *   └─ Cumi Beku (Tanggal beku: date, required)
 */
beforeEach(function () {
    $this->store = Store::factory()->active()->create();

    $this->kepiting = Category::factory()->create(['name' => 'Kepiting']);
    $this->bakau = Category::factory()->childOf($this->kepiting)->create(['name' => 'Bakau']);
    $this->soka = Category::factory()->childOf($this->kepiting)->create(['name' => 'Soka']);
    $this->cumi = Category::factory()->create(['name' => 'Cumi']);
    $this->cumiBeku = Category::factory()->childOf($this->cumi)->create(['name' => 'Cumi Beku']);

    $this->berat = CategoryAttribute::factory()->for($this->kepiting)->create([
        'name' => 'Berat per ekor', 'type' => AttributeType::Number, 'unit' => 'g', 'is_required' => true,
    ]);
    $this->kelamin = CategoryAttribute::factory()->for($this->bakau)->create([
        'name' => 'Jenis kelamin', 'type' => AttributeType::Select, 'options' => ['Jantan', 'Betina'], 'is_required' => true,
    ]);
    $this->catatan = CategoryAttribute::factory()->for($this->bakau)->create([
        'name' => 'Catatan', 'type' => AttributeType::Text, 'is_required' => false,
    ]);
    $this->tanggalBeku = CategoryAttribute::factory()->for($this->cumiBeku)->create([
        'name' => 'Tanggal beku', 'type' => AttributeType::Date, 'is_required' => true,
    ]);
});

/**
 * A ready stock Kepiting Bakau with both required attributes, unless overridden.
 *
 * @param  array<string, mixed>  $overrides  Keyed by ProductData constructor parameter.
 */
function productData(array $overrides = []): ProductData
{
    $test = test();

    return new ProductData(...array_merge([
        'categoryId' => $test->bakau->id,
        'name' => 'Kepiting Bakau Super',
        'description' => 'Kepiting bakau hidup dari tambak Muara Angke.',
        'condition' => ProductCondition::Live,
        'salesMode' => SalesMode::ReadyStock,
        'dailyQuota' => null,
        'leadTimeDays' => null,
        'cutoffTime' => null,
        'shelfLifeDays' => 2,
        'storageInstructions' => 'Simpan di tempat lembap dan sejuk.',
        'isActive' => true,
        'attributes' => [$test->berat->id => '350', $test->kelamin->id => 'Jantan'],
    ], $overrides));
}

function saveProduct(ProductData $data, ?Product $product = null): Product
{
    return app(SaveProduct::class)->handle(test()->store, $data, $product);
}

/**
 * @return array<int, string>
 */
function attributeValuesOf(Product $product): array
{
    return $product->attributeValues()->pluck('value', 'category_attribute_id')->all();
}

describe('valid products', function () {
    it('creates a ready stock product with its inherited and own attributes', function () {
        $product = saveProduct(productData());

        expect($product->fresh())
            ->store_id->toBe($this->store->id)
            ->category_id->toBe($this->bakau->id)
            ->name->toBe('Kepiting Bakau Super')
            ->condition->toBe(ProductCondition::Live)
            ->sales_mode->toBe(SalesMode::ReadyStock)
            ->shelf_life_days->toBe(2)
            ->is_active->toBeTrue()
            ->and(attributeValuesOf($product))->toEqualCanonicalizing([
                $this->berat->id => '350',
                $this->kelamin->id => 'Jantan',
            ]);
    });

    it('creates a pre-order product with its quota, lead time, and cutoff', function () {
        $product = saveProduct(productData([
            'salesMode' => SalesMode::PreOrder,
            'dailyQuota' => '20',
            'leadTimeDays' => 2,
            'cutoffTime' => '15:00',
        ]));

        expect($product->fresh())
            ->sales_mode->toBe(SalesMode::PreOrder)
            ->daily_quota->toBe('20.000')
            ->lead_time_days->toBe(2)
            ->cutoff_time->toStartWith('15:00');
    });

    it('builds a unique slug from the name', function () {
        $first = saveProduct(productData());
        $second = saveProduct(productData());

        expect($first->slug)->toBe('kepiting-bakau-super')
            ->and($second->slug)->toBe('kepiting-bakau-super-2');
    });

    it('does not reuse the slug of a soft deleted product', function () {
        app(DeleteProduct::class)->handle(saveProduct(productData()));

        expect(saveProduct(productData())->slug)->toBe('kepiting-bakau-super-2');
    });

    it('keeps the slug when the name changes', function () {
        $product = saveProduct(productData());

        $updated = saveProduct(productData(['name' => 'Kepiting Bakau Jumbo']), $product);

        expect($updated->is($product))->toBeTrue()
            ->and($updated->fresh())
            ->name->toBe('Kepiting Bakau Jumbo')
            ->slug->toBe('kepiting-bakau-super');
    });

    it('updates attribute values and drops an optional one left empty', function () {
        $product = saveProduct(productData(['attributes' => [
            $this->berat->id => '350', $this->kelamin->id => 'Jantan', $this->catatan->id => 'Ikat tali rafia',
        ]]));

        saveProduct(productData(['attributes' => [
            $this->berat->id => '400', $this->kelamin->id => 'Betina', $this->catatan->id => '',
        ]]), $product);

        expect(attributeValuesOf($product))->toEqualCanonicalizing([
            $this->berat->id => '400',
            $this->kelamin->id => 'Betina',
        ]);
    });

    it('clears pre-order fields on a ready stock product', function () {
        $product = saveProduct(productData(['dailyQuota' => '20', 'leadTimeDays' => 2, 'cutoffTime' => '15:00']));

        expect($product->fresh())
            ->daily_quota->toBeNull()
            ->lead_time_days->toBeNull()
            ->cutoff_time->toBeNull();
    });

    it('drops attributes that no longer apply when the category changes', function () {
        $product = saveProduct(productData());

        saveProduct(productData([
            'categoryId' => $this->cumiBeku->id,
            'condition' => ProductCondition::Frozen,
            'attributes' => [$this->tanggalBeku->id => '2026-10-01'],
        ]), $product);

        expect(attributeValuesOf($product))->toBe([$this->tanggalBeku->id => '2026-10-01']);
    });

    it('soft deletes a product', function () {
        $product = saveProduct(productData());

        app(DeleteProduct::class)->handle($product);

        expect(Product::query()->count())->toBe(0)
            ->and(Product::withTrashed()->find($product->id))->not->toBeNull();
    });
});

describe('rejected products', function () {
    it('names the field at fault and saves nothing', function (Closure $data, Closure $field) {
        expect(fn () => saveProduct($data()))
            ->toThrow(fn (InvalidProductData $e) => expect($e->field)->toBe($field($this)));

        expect(Product::withTrashed()->count())->toBe(0);
    })->with([
        'category with subcategories' => [
            fn () => productData(['categoryId' => test()->kepiting->id]),
            fn () => 'category_id',
        ],
        'unknown category' => [
            fn () => productData(['categoryId' => 999_999]),
            fn () => 'category_id',
        ],
        'required attribute missing' => [
            fn () => productData(['attributes' => [test()->berat->id => '350']]),
            fn ($t) => "attributes.{$t->kelamin->id}",
        ],
        'required attribute empty' => [
            fn () => productData(['attributes' => [test()->berat->id => '  ', test()->kelamin->id => 'Jantan']]),
            fn ($t) => "attributes.{$t->berat->id}",
        ],
        'select value outside the options' => [
            fn () => productData(['attributes' => [test()->berat->id => '350', test()->kelamin->id => 'Campur']]),
            fn ($t) => "attributes.{$t->kelamin->id}",
        ],
        'number that is not a number' => [
            fn () => productData(['attributes' => [test()->berat->id => 'tiga ratus', test()->kelamin->id => 'Jantan']]),
            fn ($t) => "attributes.{$t->berat->id}",
        ],
        'date in the wrong format' => [
            fn () => productData([
                'categoryId' => test()->cumiBeku->id,
                'attributes' => [test()->tanggalBeku->id => '01-10-2026'],
            ]),
            fn ($t) => "attributes.{$t->tanggalBeku->id}",
        ],
        'impossible date' => [
            fn () => productData([
                'categoryId' => test()->cumiBeku->id,
                'attributes' => [test()->tanggalBeku->id => '2026-02-30'],
            ]),
            fn ($t) => "attributes.{$t->tanggalBeku->id}",
        ],
        'text longer than 255 characters' => [
            fn () => productData(['attributes' => [
                test()->berat->id => '350', test()->kelamin->id => 'Jantan', test()->catatan->id => str_repeat('a', 256),
            ]]),
            fn ($t) => "attributes.{$t->catatan->id}",
        ],
        'attribute of another category' => [
            fn () => productData(['attributes' => [
                test()->berat->id => '350', test()->kelamin->id => 'Jantan', test()->tanggalBeku->id => '2026-10-01',
            ]]),
            fn ($t) => "attributes.{$t->tanggalBeku->id}",
        ],
        'pre-order without a daily quota' => [
            fn () => productData(['salesMode' => SalesMode::PreOrder, 'leadTimeDays' => 2, 'cutoffTime' => '15:00']),
            fn () => 'daily_quota',
        ],
        'pre-order with a zero daily quota' => [
            fn () => productData(['salesMode' => SalesMode::PreOrder, 'dailyQuota' => '0', 'leadTimeDays' => 2, 'cutoffTime' => '15:00']),
            fn () => 'daily_quota',
        ],
        'pre-order without lead time' => [
            fn () => productData(['salesMode' => SalesMode::PreOrder, 'dailyQuota' => '20', 'leadTimeDays' => 0, 'cutoffTime' => '15:00']),
            fn () => 'lead_time_days',
        ],
        'pre-order without a cutoff time' => [
            fn () => productData(['salesMode' => SalesMode::PreOrder, 'dailyQuota' => '20', 'leadTimeDays' => 2]),
            fn () => 'cutoff_time',
        ],
    ]);

    it('requires the attributes of the new category when the category changes', function () {
        $product = saveProduct(productData());

        expect(fn () => saveProduct(productData([
            'categoryId' => $this->cumiBeku->id,
            'attributes' => [],
        ]), $product))->toThrow(fn (InvalidProductData $e) => expect($e->field)->toBe("attributes.{$this->tanggalBeku->id}"));

        expect($product->fresh()->category_id)->toBe($this->bakau->id)
            ->and(attributeValuesOf($product))->toHaveCount(2);
    });
});
