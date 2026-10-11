<?php

declare(strict_types=1);

use App\Enums\ProductCondition;
use App\Enums\SalesMode;
use App\Enums\Unit;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * A product of an active store with one active variant: visible in the catalog.
 */
function visibleProduct(): Product
{
    $product = Product::factory()->create();
    ProductVariant::factory()->for($product)->create();

    return $product;
}

describe('factory and casts', function () {
    it('creates an active product with enum condition and sales mode', function () {
        $product = Product::factory()->create()->fresh();

        expect($product->is_active)->toBeTrue()
            ->and($product->condition)->toBeInstanceOf(ProductCondition::class)
            ->and($product->sales_mode)->toBe(SalesMode::ReadyStock)
            ->and($product->daily_quota)->toBeNull()
            ->and($product->blocked_at)->toBeNull()
            ->and($product->store->status->value)->toBe('active');
    });

    it('creates a pre-order product with its quota, lead time, and cutoff', function () {
        $product = Product::factory()->preOrder()->create()->fresh();

        expect($product->sales_mode)->toBe(SalesMode::PreOrder)
            ->and($product->daily_quota)->not->toBeNull()
            ->and($product->lead_time_days)->toBeGreaterThanOrEqual(1)
            ->and($product->cutoff_time)->not->toBeNull();
    });

    it('casts the variant unit and keeps decimal stock exact', function () {
        $variant = ProductVariant::factory()->create(['unit' => Unit::Kg, 'stock' => '12.345', 'min_qty' => '0.500'])->fresh();

        expect($variant->unit)->toBe(Unit::Kg)
            ->and($variant->stock)->toBe('12.345')
            ->and($variant->min_qty)->toBe('0.500')
            ->and($variant->price)->toBeInt();
    });
});

describe('relations', function () {
    it('links a product to its store, category, variants, images, and attribute values', function () {
        $product = Product::factory()->create();
        ProductVariant::factory()->for($product)->count(2)->create();

        expect($product->store)->toBeInstanceOf(Store::class)
            ->and($product->category)->toBeInstanceOf(Category::class)
            ->and($product->variants)->toHaveCount(2)
            ->and($product->store->products()->sole()->is($product))->toBeTrue()
            ->and($product->attributeValues)->toBeEmpty();
    });

    it('orders images by sort_order', function () {
        $product = Product::factory()->create();
        ProductImage::factory()->for($product)->create(['sort_order' => 2, 'path' => 'b.webp']);
        ProductImage::factory()->for($product)->create(['sort_order' => 1, 'path' => 'a.webp']);

        expect($product->images()->pluck('path')->all())->toBe(['a.webp', 'b.webp']);
    });
});

describe('visibleInCatalog()', function () {
    it('includes an active product of an active store with an active variant', function () {
        $product = visibleProduct();

        expect(Product::query()->visibleInCatalog()->pluck('id')->all())->toBe([$product->id]);
    });

    it('leaves out products buyers must not see', function (Closure $hide) {
        $product = visibleProduct();
        $hide($product);

        expect(Product::query()->visibleInCatalog()->count())->toBe(0);
    })->with([
        'inactive product' => fn () => fn (Product $p) => $p->update(['is_active' => false]),
        'blocked product' => fn () => fn (Product $p) => $p->forceFill(['blocked_at' => now(), 'block_reason' => 'Foto tidak sesuai'])->save(),
        'deleted product' => fn () => fn (Product $p) => $p->delete(),
        'pending store' => fn () => fn (Product $p) => $p->store->forceFill(['status' => 'pending'])->save(),
        'suspended store' => fn () => fn (Product $p) => $p->store->forceFill(['status' => 'suspended'])->save(),
        'only inactive variants' => fn () => fn (Product $p) => $p->variants()->update(['is_active' => false]),
        'only deleted variants' => fn () => fn (Product $p) => $p->variants->each->delete(),
    ]);

    it('keeps the variants of a soft deleted product', function () {
        $product = visibleProduct();

        $product->delete();

        expect(ProductVariant::query()->where('product_id', $product->id)->count())->toBe(1)
            ->and(Product::withTrashed()->find($product->id))->not->toBeNull();
    });
});

describe('isStockStale()', function () {
    it('tells whether the stock confirmation is older than the limit', function (?int $hoursAgo, bool $stale) {
        $variant = ProductVariant::factory()->create([
            'stock_confirmed_at' => $hoursAgo === null ? null : now()->subHours($hoursAgo),
        ]);

        expect($variant->isStockStale())->toBe($stale);
    })->with([
        'confirmed 23 hours ago' => [23, false],
        'confirmed 25 hours ago' => [25, true],
        'never confirmed' => [null, true],
    ]);

    it('reads the limit from config', function () {
        config(['catalog.stale_stock_hours' => 6]);

        expect(ProductVariant::factory()->create(['stock_confirmed_at' => now()->subHours(7)])->isStockStale())->toBeTrue();
    });
});

describe('constraints', function () {
    it('rejects two products with the same slug', function () {
        Product::factory()->create(['slug' => 'kepiting-bakau']);

        expect(fn () => Product::factory()->create(['slug' => 'kepiting-bakau']))
            ->toThrow(UniqueConstraintViolationException::class);
    });

    it('does not mass assign the variant stock', function () {
        $variant = ProductVariant::factory()->create();

        expect(fn () => $variant->update(['stock' => '999']))->toThrow(MassAssignmentException::class);
    });

    it('does not delete a category that has products', function () {
        $product = Product::factory()->create();

        // A savepoint keeps PostgreSQL's test transaction usable after the failed delete.
        expect(fn () => DB::transaction(fn () => $product->category->delete()))->toThrow(QueryException::class);

        expect($product->category->fresh())->not->toBeNull();
    });

    it('allows one value per attribute per product', function () {
        $product = Product::factory()->create();
        $attribute = CategoryAttribute::factory()->for($product->category)->create();
        $product->attributeValues()->create(['category_attribute_id' => $attribute->id, 'value' => '350']);

        expect(fn () => $product->attributeValues()->create(['category_attribute_id' => $attribute->id, 'value' => '400']))
            ->toThrow(UniqueConstraintViolationException::class);
    });
});
