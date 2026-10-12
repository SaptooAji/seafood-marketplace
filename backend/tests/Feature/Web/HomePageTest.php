<?php

declare(strict_types=1);

use App\Enums\Unit;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

/**
 * A product buyers can see, from an active store, with one active variant at
 * the given price, unless overridden.
 *
 * @param  array<string, mixed>  $attributes
 */
function homeProduct(array $attributes = [], int $price = 100_000, ?Store $store = null): Product
{
    $product = Product::factory()
        ->for($store ?? Store::factory()->active())
        ->create($attributes);

    ProductVariant::factory()->for($product)->create(['price' => $price]);

    return $product;
}

/**
 * The number of queries the home page runs.
 */
function homePageQueryCount(): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    test()->get('/')->assertOk();

    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

describe('categories', function () {
    it('links the root categories to the catalog', function () {
        $kepiting = Category::factory()->create(['name' => 'Kepiting', 'slug' => 'kepiting', 'sort_order' => 1]);
        Category::factory()->childOf($kepiting)->create(['name' => 'Kepiting Bakau Anak']);

        $this->get('/')
            ->assertOk()
            ->assertSee('href="'.url('/produk').'?kategori=kepiting"', escape: false)
            ->assertDontSee('Kepiting Bakau Anak');
    });
});

describe('latest products', function () {
    it('shows the product with its thumbnail, cheapest price and unit, store, and city', function () {
        $store = Store::factory()->active()->create(['name' => 'Toko Bahari', 'city' => 'Jakarta Utara']);
        $product = Product::factory()->for($store)->create(['name' => 'Kepiting Bakau', 'slug' => 'kepiting-bakau']);
        ProductVariant::factory()->for($product)->create(['price' => 185_000, 'unit' => Unit::Kg]);
        ProductVariant::factory()->for($product)->create(['price' => 60_000, 'unit' => Unit::Piece]);
        $second = ProductImage::factory()->for($product)->create(['sort_order' => 2]);
        $first = ProductImage::factory()->for($product)->create(['sort_order' => 1]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Kepiting Bakau')
            ->assertSee('href="'.url('/produk/kepiting-bakau').'"', escape: false)
            ->assertSee($first->thumbnailUrl(), escape: false)
            ->assertDontSee($second->thumbnailUrl(), escape: false)
            ->assertSee('Rp60.000')
            ->assertSee(Unit::Piece->label())
            ->assertDontSee('Rp185.000')
            ->assertSee('Toko Bahari')
            ->assertSee('Jakarta Utara');
    });

    it('ignores inactive and deleted variants for the starting price', function () {
        $product = homeProduct(['name' => 'Cumi Segar'], price: 90_000);
        ProductVariant::factory()->for($product)->create(['price' => 40_000, 'is_active' => false]);
        ProductVariant::factory()->for($product)->create(['price' => 30_000])->delete();

        $this->get('/')
            ->assertOk()
            ->assertSee('Rp90.000')
            ->assertDontSee('Rp40.000')
            ->assertDontSee('Rp30.000');
    });

    it('shows a product without photos', function () {
        homeProduct(['name' => 'Tiram Tanpa Foto']);

        $this->get('/')->assertOk()->assertSee('Tiram Tanpa Foto');
    });

    it('shows only the 8 newest products, newest first', function () {
        foreach (range(1, 10) as $day) {
            homeProduct(['name' => sprintf('Produk Hari %02d', $day), 'created_at' => now()->subDays(11 - $day)]);
        }

        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder(['Produk Hari 10', 'Produk Hari 09', 'Produk Hari 03'])
            ->assertDontSee('Produk Hari 02')
            ->assertDontSee('Produk Hari 01');
    });

    it('hides products buyers may not see', function (Closure $create) {
        $create();

        $this->get('/')->assertOk()->assertDontSee('Produk Tersembunyi');
    })->with([
        'blocked' => [fn () => homeProduct(['name' => 'Produk Tersembunyi', 'blocked_at' => now(), 'block_reason' => 'Foto tidak sesuai.'])],
        'inactive' => [fn () => homeProduct(['name' => 'Produk Tersembunyi', 'is_active' => false])],
        'from a pending store' => [fn () => homeProduct(['name' => 'Produk Tersembunyi'], store: Store::factory()->pending()->create())],
        'from a suspended store' => [fn () => homeProduct(['name' => 'Produk Tersembunyi'], store: Store::factory()->suspended()->create())],
        'from a rejected store' => [fn () => homeProduct(['name' => 'Produk Tersembunyi'], store: Store::factory()->rejected()->create())],
        'deleted' => [fn () => homeProduct(['name' => 'Produk Tersembunyi'])->delete()],
        'without an active variant' => [fn () => ProductVariant::factory()
            ->for(Product::factory()->create(['name' => 'Produk Tersembunyi']))
            ->create(['is_active' => false])],
    ]);
});

describe('stores', function () {
    it('links active stores to their page', function () {
        Store::factory()->active()->create(['name' => 'Toko Muara Angke', 'slug' => 'toko-muara-angke', 'city' => 'Jakarta Utara']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Toko Muara Angke')
            ->assertSee('href="'.url('/toko/toko-muara-angke').'"', escape: false);
    });

    it('hides stores that are not active', function (string $state) {
        Store::factory()->{$state}()->create(['name' => 'Toko Tersembunyi']);

        $this->get('/')->assertOk()->assertDontSee('Toko Tersembunyi');
    })->with(['pending', 'rejected', 'suspended']);

    it('shows at most 6 stores', function () {
        Store::factory()->active()->count(8)->sequence(fn ($sequence) => ['name' => 'Toko Nomor '.($sequence->index + 1).'.'])->create();

        $response = $this->get('/')->assertOk();

        $shown = collect(range(1, 8))->filter(fn (int $number) => str_contains($response->getContent(), "Toko Nomor {$number}."));
        expect($shown)->toHaveCount(6);
    });
});

describe('security', function () {
    it('escapes product names, store names, and cities', function () {
        $store = Store::factory()->active()->create(['name' => '<script>alert(1)</script>', 'city' => '<script>alert(2)</script>']);
        homeProduct(['name' => '<script>alert(3)</script>'], store: $store);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', escape: false)
            ->assertDontSee('<script>alert(2)</script>', escape: false)
            ->assertDontSee('<script>alert(3)</script>', escape: false)
            ->assertSee('&lt;script&gt;alert(3)&lt;/script&gt;', escape: false);
    });

    it('never shows bank details', function () {
        $store = Store::factory()->active()->create(['bank_name' => 'Bank Rahasia', 'bank_account_no' => '9876543210', 'bank_account_name' => 'Pemilik Rahasia']);
        homeProduct(store: $store);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Bank Rahasia')
            ->assertDontSee('9876543210')
            ->assertDontSee('Pemilik Rahasia');
    });
});

describe('edge cases', function () {
    it('works without products and stores', function () {
        $this->get('/')->assertOk();
    });

    it('runs the same number of queries for 3 and 20 products', function () {
        $addProducts = function (int $count) {
            foreach (range(1, $count) as $i) {
                $product = homeProduct();
                ProductVariant::factory()->for($product)->create();
                ProductImage::factory()->for($product)->count(2)->sequence(['sort_order' => 1], ['sort_order' => 2])->create();
            }
        };

        $addProducts(3);
        $few = homePageQueryCount();

        $addProducts(17);
        $many = homePageQueryCount();

        expect($many)->toBe($few);
    });
});
