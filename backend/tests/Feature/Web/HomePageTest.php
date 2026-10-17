<?php

declare(strict_types=1);

use App\Enums\Unit;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    // 08.00 WIB on 15 October.
    $this->travelTo(now()->setDateTime(2026, 10, 15, 1, 0));
});

/**
 * A product buyers can see, from an active store, with one active variant at
 * the given price whose stock was confirmed at the given time (now, unless
 * overridden; null for never).
 *
 * @param  array<string, mixed>  $attributes
 */
function homeProduct(array $attributes = [], int $price = 100_000, ?Store $store = null, mixed $confirmedAt = 'now'): Product
{
    $product = Product::factory()
        ->for($store ?? Store::factory()->active())
        ->create($attributes);

    ProductVariant::factory()->for($product)->create([
        'price' => $price,
        'stock_confirmed_at' => $confirmedAt === 'now' ? now() : $confirmedAt,
    ]);

    return $product;
}

/**
 * A visible product with one photo, confirmed at the given time.
 *
 * @param  array<string, mixed>  $attributes
 */
function homeProductWithPhoto(array $attributes = [], mixed $confirmedAt = 'now', ?Store $store = null): Product
{
    $product = homeProduct($attributes, store: $store, confirmedAt: $confirmedAt);
    ProductImage::factory()->for($product)->create(['sort_order' => 1]);

    return $product;
}

/**
 * The HTML of the home page section labelled by the given heading id.
 */
function homeSection(string $labelledBy): string
{
    $html = test()->get('/')->assertOk()->getContent();

    if (! str_contains($html, 'aria-labelledby="'.$labelledBy.'"')) {
        return '';
    }

    return Str::before(Str::after($html, 'aria-labelledby="'.$labelledBy.'"'), '</section>');
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

/**
 * A highlight entry for config('home.highlights'), linking the given product.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function homeHighlight(string $slug, array $overrides = []): array
{
    return [
        'product' => $slug,
        'title' => 'Sorotan '.$slug,
        'text' => 'Teks sorotan '.$slug,
        'button' => 'Lihat produk',
        'photo' => null,
        ...$overrides,
    ];
}

/**
 * A recipe entry for config('recipes').
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function homeRecipe(string $slug, bool $featured = true, array $overrides = []): array
{
    return [
        'slug' => $slug,
        'name' => 'Resep '.$slug,
        'description' => 'Deskripsi.',
        'photo' => 'images/resep/kepiting-saus-padang.jpg',
        'category' => 'kepiting',
        'minutes' => 30,
        'servings' => 4,
        'level' => 'Mudah',
        'group' => 'cepat',
        'needs' => ['category' => 'bakau', 'kg' => '1.000', 'label' => '1 kg kepiting bakau'],
        'ingredients' => [],
        'steps' => ['Masak.'],
        'swaps' => [],
        'featured' => $featured,
        ...$overrides,
    ];
}

describe('hero highlights', function () {
    beforeEach(function () {
        config(['home.highlights' => [], 'recipes' => []]);
    });

    it('shows the first highlight over its product photo, with a white product card', function () {
        $store = Store::factory()->active()->create(['name' => 'Toko Bahari']);
        $product = homeProductWithPhoto(['name' => 'Kepiting Bakau Jumbo', 'slug' => 'kepiting-bakau-jumbo'], confirmedAt: now()->subMinutes(90), store: $store);
        $product->variants()->first()->update(['price' => 240_000, 'unit' => Unit::Kg]);
        config(['home.highlights' => [homeHighlight('kepiting-bakau-jumbo', ['title' => 'Kepiting hidup, dipilih per ekor', 'text' => 'Diikat dan dipilih satu per satu.', 'button' => 'Pilih kepiting'])]]);

        $hero = homeSection('beranda-judul');

        expect($hero)
            ->toContain('Kepiting hidup, dipilih per ekor')
            ->toContain('Diikat dan dipilih satu per satu.')
            ->toContain('Pilih kepiting')
            ->toContain($product->images()->first()->url())
            ->toContain('data-highlight-card')
            ->toContain('Kepiting Bakau Jumbo')
            ->toContain('Toko Bahari')
            ->toContain('Rp240.000')
            ->toContain('Dicek 06.30')
            ->toContain('href="'.url('/produk/kepiting-bakau-jumbo').'"');
    });

    it('uses the photo set in the config instead of the product photo', function () {
        homeProductWithPhoto(['slug' => 'cumi-segar']);
        config(['home.highlights' => [homeHighlight('cumi-segar', ['photo' => 'images/resep/cumi-saus-tiram.jpg'])]]);

        expect(homeSection('beranda-judul'))->toContain('src="'.asset('images/resep/cumi-saus-tiram.jpg').'"');
    });

    it('has one tab per highlight in the config order, the first selected, without autoplay', function () {
        foreach (['udang', 'cumi', 'kerang'] as $slug) {
            homeProductWithPhoto(['name' => 'Produk '.$slug, 'slug' => $slug]);
        }
        config(['home.highlights' => [homeHighlight('kerang'), homeHighlight('udang'), homeHighlight('cumi')]]);

        $hero = homeSection('beranda-judul');

        expect(substr_count($hero, 'role="tab"'))->toBe(3)
            ->and(substr_count($hero, 'aria-selected="true"'))->toBe(1)
            ->and(Str::position($hero, 'Sorotan kerang'))->toBeLessThan(Str::position($hero, 'Sorotan udang'))
            ->and(Str::position($hero, 'Sorotan udang'))->toBeLessThan(Str::position($hero, 'Sorotan cumi'))
            ->and($hero)->not->toContain('setInterval');
    });

    it('skips a highlight whose product buyers cannot see', function (Closure $hide) {
        homeProductWithPhoto(['name' => 'Produk Tampil', 'slug' => 'tampil']);
        $hidden = homeProductWithPhoto(['name' => 'Produk Tersembunyi', 'slug' => 'tersembunyi']);
        $hide($hidden);
        config(['home.highlights' => [homeHighlight('tersembunyi'), homeHighlight('tidak-ada'), homeHighlight('tampil')]]);

        $hero = homeSection('beranda-judul');

        expect($hero)->toContain('Sorotan tampil')
            ->not->toContain('Sorotan tersembunyi')
            ->not->toContain('Sorotan tidak-ada')
            ->not->toContain('role="tab"');
    })->with([
        'suspended store' => [fn (Product $product) => $product->store->forceFill(['status' => 'suspended'])->save()],
        'blocked product' => [fn (Product $product) => $product->forceFill(['blocked_at' => now(), 'block_reason' => 'Foto tidak sesuai.'])->save()],
        'inactive product' => [fn (Product $product) => $product->forceFill(['is_active' => false])->save()],
    ]);

    it('falls back to a plain photo without a product card when every highlight is skipped', function () {
        config(['home.highlights' => [homeHighlight('tidak-ada')], 'home.fallback_photo' => 'images/resep/kepiting-saus-padang.jpg']);

        $this->get('/')->assertOk();
        $hero = homeSection('beranda-judul');

        expect($hero)->toContain('src="'.asset('images/resep/kepiting-saus-padang.jpg').'"')
            ->not->toContain('data-highlight-card')
            ->not->toContain('role="tab"');
    });

    it('no longer shows the hero numbers or the nearest stores button', function () {
        homeProduct();
        Store::factory()->active()->count(2)->create();

        expect(homeSection('beranda-judul'))
            ->not->toContain('Lapak aktif')
            ->not->toContain('Stok dicek dalam')
            ->not->toContain('Belanja menurut kondisi')
            ->not->toContain('Cari lapak terdekat');
    });

    it('runs the same number of queries for one and four highlights', function () {
        foreach (range(1, 4) as $number) {
            homeProductWithPhoto(['slug' => "sorotan-{$number}"]);
        }

        config(['home.highlights' => [homeHighlight('sorotan-1')]]);
        $one = homePageQueryCount();

        config(['home.highlights' => array_map(fn (int $number) => homeHighlight("sorotan-{$number}"), range(1, 4))]);

        expect(homePageQueryCount())->toBe($one);
    });

    it('escapes the highlight text and the product name', function () {
        homeProductWithPhoto(['name' => '<script>alert(5)</script>', 'slug' => 'berbahaya']);
        config(['home.highlights' => [homeHighlight('berbahaya', ['title' => '<b>Judul</b>', 'text' => '<i>Teks</i>'])]]);

        $hero = homeSection('beranda-judul');

        expect($hero)->not->toContain('<script>alert(5)</script>')
            ->not->toContain('<b>Judul</b>')
            ->not->toContain('<i>Teks</i>')
            ->toContain('&lt;script&gt;alert(5)&lt;/script&gt;');
    });
});

describe('recipe picks', function () {
    beforeEach(function () {
        config(['home.highlights' => []]);
    });

    it('shows up to 4 featured recipes and links to all recipes', function () {
        config(['recipes' => [
            homeRecipe('satu'), homeRecipe('dua'), homeRecipe('bukan', featured: false),
            homeRecipe('tiga'), homeRecipe('empat'), homeRecipe('lima'),
        ]]);

        $section = homeSection('resep-pilihan');

        expect($section)->toContain('Mau masak apa hari ini?')
            ->toContain('href="'.url('/resep/satu').'"')
            ->toContain('href="'.url('/resep/empat').'"')
            ->not->toContain('href="'.url('/resep/lima').'"')
            ->not->toContain('href="'.url('/resep/bukan').'"')
            ->toContain('href="'.url('/resep').'"')
            ->toContain('Semua resep')
            ->toContain('30 menit');
    });

    it('shows only the featured recipes there are, without empty slots', function () {
        config(['recipes' => [homeRecipe('satu'), homeRecipe('dua'), homeRecipe('bukan', featured: false)]]);

        expect(substr_count(homeSection('resep-pilihan'), 'href="'.url('/resep/')))->toBe(2);
    });

    it('is not rendered when no recipe is featured', function () {
        config(['recipes' => [homeRecipe('bukan', featured: false)]]);

        expect(homeSection('resep-pilihan'))->toBe('');
    });
});

describe('recently confirmed products', function () {
    it('shows the product with its thumbnail, cheapest price and unit, store, and city', function () {
        $store = Store::factory()->active()->create(['name' => 'Toko Bahari', 'city' => 'Jakarta Utara']);
        $product = Product::factory()->for($store)->create(['name' => 'Kepiting Bakau', 'slug' => 'kepiting-bakau']);
        ProductVariant::factory()->for($product)->create(['price' => 185_000, 'unit' => Unit::Kg]);
        ProductVariant::factory()->for($product)->create(['price' => 60_000, 'unit' => Unit::Piece]);
        $second = ProductImage::factory()->for($product)->create(['sort_order' => 2]);
        $first = ProductImage::factory()->for($product)->create(['sort_order' => 1]);

        expect(homeSection('baru-judul'))
            ->toContain('Kepiting Bakau')
            ->toContain('href="'.url('/produk/kepiting-bakau').'"')
            ->toContain($first->thumbnailUrl())
            ->not->toContain($second->thumbnailUrl())
            ->toContain('Rp60.000')
            ->toContain(Unit::Piece->label())
            ->not->toContain('Rp185.000')
            ->toContain('Toko Bahari')
            ->toContain('Jakarta Utara');
    });

    it('shows the products on white cards', function () {
        homeProduct(['name' => 'Cumi Segar']);

        expect(homeSection('baru-judul'))->toContain('data-tone="white"');
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

    it('orders by the latest stock check of active variants, never checked last', function () {
        homeProduct(['name' => 'Produk Belum Dicek'], confirmedAt: null);
        homeProduct(['name' => 'Produk Tiga Hari'], confirmedAt: now()->subDays(3));
        homeProduct(['name' => 'Produk Sejam'], confirmedAt: now()->subHour());
        $mixed = homeProduct(['name' => 'Produk Lima Hari'], confirmedAt: now()->subDays(5));
        ProductVariant::factory()->for($mixed)->create(['is_active' => false, 'stock_confirmed_at' => now()->subMinutes(10)]);

        expect(homeSection('baru-judul'))->toMatch('/Produk Sejam.*Produk Tiga Hari.*Produk Lima Hari.*Produk Belum Dicek/s');
    });

    it('shows only 8 products, the newest first when checked at the same time', function () {
        foreach (range(1, 10) as $day) {
            homeProduct(['name' => sprintf('Produk Hari %02d', $day), 'created_at' => now()->subDays(11 - $day)]);
        }

        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder(['Produk Hari 10', 'Produk Hari 09', 'Produk Hari 03'])
            ->assertDontSee('Produk Hari 02')
            ->assertDontSee('Produk Hari 01');
    });

    it('labels each product with when its stock was checked, in WIB', function (mixed $confirmedAt, ?string $label) {
        homeProduct(['name' => 'Kepiting Bakau'], confirmedAt: $confirmedAt);

        $section = homeSection('baru-judul');

        $label === null
            ? expect($section)->not->toContain('Dicek')
            : expect($section)->toContain($label);
    })->with([
        'earlier today in WIB' => [fn () => now()->setDateTime(2026, 10, 14, 23, 30), 'Dicek 06.30'],
        'yesterday in WIB, same day in UTC' => [fn () => now()->setDateTime(2026, 10, 14, 16, 30), 'Dicek 14 Okt'],
        'three days ago' => [fn () => now()->setDateTime(2026, 10, 12, 3, 0), 'Dicek 12 Okt'],
        'never' => [null, null],
    ]);

    it('hides products buyers may not see', function (Closure $create) {
        $create();

        $this->get('/')->assertOk()->assertDontSee('Produk Tersembunyi');
    })->with([
        'blocked' => [fn () => homeProductWithPhoto(['name' => 'Produk Tersembunyi', 'blocked_at' => now(), 'block_reason' => 'Foto tidak sesuai.'])],
        'inactive' => [fn () => homeProductWithPhoto(['name' => 'Produk Tersembunyi', 'is_active' => false])],
        'from a pending store' => [fn () => homeProductWithPhoto(['name' => 'Produk Tersembunyi'], store: Store::factory()->pending()->create())],
        'from a suspended store' => [fn () => homeProductWithPhoto(['name' => 'Produk Tersembunyi'], store: Store::factory()->suspended()->create())],
        'from a rejected store' => [fn () => homeProductWithPhoto(['name' => 'Produk Tersembunyi'], store: Store::factory()->rejected()->create())],
        'deleted' => [fn () => homeProductWithPhoto(['name' => 'Produk Tersembunyi'])->delete()],
        'without an active variant' => [fn () => ProductVariant::factory()
            ->for(Product::factory()->create(['name' => 'Produk Tersembunyi']))
            ->create(['is_active' => false])],
    ]);

    it('links to the catalog', function () {
        homeProduct();

        expect(homeSection('baru-judul'))
            ->toContain('href="'.url('/produk').'"')
            ->toContain('Lihat semua di katalog');
    });
});

describe('category chips', function () {
    it('has "Semua" and the root categories of the listed products, in admin order', function () {
        $kepiting = Category::factory()->create(['name' => 'Kepiting', 'slug' => 'kepiting', 'sort_order' => 1]);
        $cumi = Category::factory()->create(['name' => 'Cumi', 'slug' => 'cumi', 'sort_order' => 2]);
        Category::factory()->create(['name' => 'Kerang', 'slug' => 'kerang', 'sort_order' => 3]);
        $bakau = Category::factory()->childOf($kepiting)->create(['name' => 'Bakau Subkategori', 'slug' => 'bakau']);
        homeProduct(['category_id' => $cumi->id]);
        homeProduct(['category_id' => $bakau->id]);

        $chips = Str::between(homeSection('baru-judul'), 'role="group" aria-label="Kategori"', '</div>');

        expect($chips)
            ->toMatch('/Semua.*Kepiting.*Cumi/s')
            ->not->toContain('Kerang')
            ->not->toContain('Bakau Subkategori');
    });

    it('filters a product from a subcategory by its root category', function () {
        $kepiting = Category::factory()->create(['name' => 'Kepiting', 'slug' => 'kepiting', 'sort_order' => 1]);
        $cumi = Category::factory()->create(['name' => 'Cumi', 'slug' => 'cumi', 'sort_order' => 2]);
        $bakau = Category::factory()->childOf($kepiting)->create(['slug' => 'bakau']);
        homeProduct(['category_id' => $kepiting->id]);
        homeProduct(['category_id' => $bakau->id]);
        homeProduct(['category_id' => $cumi->id]);

        $section = homeSection('baru-judul');

        expect(substr_count($section, '<li data-kategori="kepiting"'))->toBe(2)
            ->and(substr_count($section, '<li data-kategori="cumi"'))->toBe(1)
            ->and($section)->not->toContain('data-kategori="bakau"');
    });

    it('has no chips when the listed products share one root category', function () {
        $kepiting = Category::factory()->create(['slug' => 'kepiting']);
        homeProduct(['category_id' => $kepiting->id]);
        homeProduct(['category_id' => Category::factory()->childOf($kepiting)->create()->id]);

        expect(homeSection('baru-judul'))->not->toContain('aria-label="Kategori"');
    });
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
    it('escapes product, store, city, and category names everywhere they show', function () {
        $category = Category::factory()->create(['name' => '<script>alert(4)</script>', 'sort_order' => 1]);
        $store = Store::factory()->active()->create(['name' => '<script>alert(1)</script>', 'city' => '<script>alert(2)</script>']);
        homeProductWithPhoto(['name' => '<script>alert(3)</script>', 'category_id' => $category->id], store: $store);
        homeProduct(['category_id' => Category::factory()->create(['sort_order' => 2])->id]);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', escape: false)
            ->assertDontSee('<script>alert(2)</script>', escape: false)
            ->assertDontSee('<script>alert(3)</script>', escape: false)
            ->assertDontSee('<script>alert(4)</script>', escape: false)
            ->assertSee('&lt;script&gt;alert(3)&lt;/script&gt;', escape: false)
            ->assertSee('&lt;script&gt;alert(4)&lt;/script&gt;', escape: false);
    });

    it('never shows bank details', function () {
        $store = Store::factory()->active()->create(['bank_name' => 'Bank Rahasia', 'bank_account_no' => '9876543210', 'bank_account_name' => 'Pemilik Rahasia']);
        homeProductWithPhoto(store: $store);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Bank Rahasia')
            ->assertDontSee('9876543210')
            ->assertDontSee('Pemilik Rahasia');
    });
});

describe('edge cases', function () {
    it('works without products, stores, and categories', function () {
        $hero = homeSection('beranda-judul');

        expect($hero)
            ->not->toBe('')
            ->not->toContain('data-highlight-card')
            ->not->toContain('role="tab"')
            ->and(homeSection('baru-judul'))->toBe('');
    });

    it('runs the same number of queries for 3 and 20 products', function () {
        $addProducts = function (int $count) {
            foreach (range(1, $count) as $i) {
                $product = homeProduct(['category_id' => Category::factory()->create()->id]);
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
