<?php

declare(strict_types=1);

use App\Actions\DeleteProduct;
use App\Actions\DeleteVariant;
use App\Exceptions\InvalidProductData;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

const LAST_VARIANT_MESSAGE = 'Produk perlu minimal satu varian. Nonaktifkan produk kalau sedang tidak dijual.';

beforeEach(function () {
    $this->product = Product::factory()->create();
    $this->variant = ProductVariant::factory()->for($this->product)->create(['name' => 'Size 50']);
});

function deleteVariant(ProductVariant $variant): void
{
    app(DeleteVariant::class)->handle($variant);
}

describe('allowed', function () {
    it('soft deletes one of two variants and keeps its stock book', function () {
        $other = ProductVariant::factory()->for($this->product)->create(['name' => 'Size 70']);
        $movement = StockMovement::factory()->for($this->variant, 'variant')->create();

        deleteVariant($this->variant);

        $this->assertSoftDeleted($this->variant);
        $this->assertNotSoftDeleted($other);
        $this->assertModelExists($movement);
        expect($this->product->variants()->count())->toBe(1);
    });

    it('counts an inactive variant as a variant', function () {
        ProductVariant::factory()->for($this->product)->create(['name' => 'Size 70', 'is_active' => false]);

        deleteVariant($this->variant);

        $this->assertSoftDeleted($this->variant);
    });

    it('allows the name again after the old variant is deleted', function () {
        ProductVariant::factory()->for($this->product)->create(['name' => 'Size 70']);

        deleteVariant($this->variant);

        expect(ProductVariant::factory()->for($this->product)->create(['name' => 'Size 50'])->name)->toBe('Size 50');
    });

    it('still deletes a product that has a single variant', function () {
        app(DeleteProduct::class)->handle($this->product);

        $this->assertSoftDeleted($this->product);
    });
});

describe('the last variant', function () {
    it('refuses to delete it and keeps the variant', function (Closure $setUp) {
        $setUp($this->product);

        expect(fn () => deleteVariant($this->variant))
            ->toThrow(function (InvalidProductData $e) {
                expect($e->field)->toBe('variant')
                    ->and($e->getMessage())->toBe(LAST_VARIANT_MESSAGE);
            });

        expect($this->variant->fresh()->deleted_at)->toBeNull();
    })->with([
        'only variant' => [fn (Product $product) => null],
        'other variant already deleted' => [fn (Product $product) => ProductVariant::factory()->for($product)->create(['name' => 'Size 70'])->delete()],
    ]);

    it('locks the product row before counting the variants', function () {
        ProductVariant::factory()->for($this->product)->create(['name' => 'Size 70']);

        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries) {
            $queries[] = $query->sql;
        });

        deleteVariant($this->variant);

        // Two requests deleting the last two variants at once must take turns,
        // so the second one counts after the first has deleted.
        expect(collect($queries)->contains(fn (string $sql) => str_contains($sql, 'from "products"') && str_contains($sql, 'for update')))->toBeTrue();
    });
});
