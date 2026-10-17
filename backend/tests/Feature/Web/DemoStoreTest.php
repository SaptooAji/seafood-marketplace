<?php

declare(strict_types=1);

use App\Livewire\Catalog;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
 * Demo stores on the buyer web (#185): always labelled "Toko contoh", never
 * reachable by phone or WhatsApp, never under "Lapak terverifikasi", and
 * listed after the real stores wherever stores or products are listed.
 */

const DEMO_EXPLANATION = 'Ini toko contoh untuk memperlihatkan tampilan marketplace. Nama, harga, dan stoknya bukan sungguhan, jadi produknya tidak bisa dipesan.';

beforeEach(function () {
    $this->kepiting = Category::factory()->create(['name' => 'Kepiting', 'slug' => 'kepiting']);
    $this->bakau = Category::factory()->childOf($this->kepiting)->create(['name' => 'Bakau', 'slug' => 'bakau']);

    $this->real = Store::factory()->active()->create([
        'name' => 'Lapak Asli', 'slug' => 'lapak-asli', 'phone' => '081111111111', 'lat' => '-6.2000000', 'lng' => '106.8000000',
    ]);
    $this->demo = Store::factory()->active()->demo()->create([
        'name' => 'Bahari Contoh', 'slug' => 'bahari-contoh', 'phone' => '082222222222', 'lat' => '-6.1000000', 'lng' => '106.8000000',
    ]);
});

/**
 * A product buyers can see in Bakau, with one active variant at the given
 * price, its stock confirmed at the given time.
 *
 * @param  array<string, mixed>  $attributes
 */
function demoTestProduct(Store $store, array $attributes = [], int $price = 100_000, mixed $confirmedAt = null): Product
{
    $product = Product::factory()->for($store)->create(['category_id' => test()->bakau->id, ...$attributes]);
    ProductVariant::factory()->for($product)->create(['price' => $price, 'stock_confirmed_at' => $confirmedAt ?? now()]);

    return $product;
}

/**
 * The HTML of the section labelled by the given heading id, or '' when it is not rendered.
 */
function demoTestSection(string $html, string $labelledBy): string
{
    if (! str_contains($html, 'aria-labelledby="'.$labelledBy.'"')) {
        return '';
    }

    return Str::before(Str::after($html, 'aria-labelledby="'.$labelledBy.'"'), '</section>');
}

describe('label', function () {
    it('labels the product card of a demo store, not of a real store', function () {
        $demoProduct = demoTestProduct($this->demo, ['name' => 'Kepiting Contoh']);
        $realProduct = demoTestProduct($this->real, ['name' => 'Kepiting Asli']);

        $this->blade('<x-product-card :product="$product" />', ['product' => $demoProduct->load(['images', 'variants', 'store'])])
            ->assertSee('Toko contoh');
        $this->blade('<x-product-card :product="$product" />', ['product' => $realProduct->load(['images', 'variants', 'store'])])
            ->assertDontSee('Toko contoh');
    });

    it('labels demo products in the catalog and on the home page', function (string $url) {
        demoTestProduct($this->demo, ['name' => 'Kepiting Contoh']);

        $this->get($url)->assertOk()->assertSeeInOrder(['Toko contoh', 'Kepiting Contoh']);
    })->with(['catalog' => ['/produk'], 'home' => ['/']]);

    it('shows no label when only real stores are listed', function (string $url) {
        $this->demo->delete();
        demoTestProduct($this->real, ['name' => 'Kepiting Asli']);

        $this->get($url)->assertOk()->assertDontSee('Toko contoh');
    })->with(['catalog' => ['/produk'], 'home' => ['/']]);

    it('labels the store of a demo product on the product page', function () {
        demoTestProduct($this->demo, ['name' => 'Kepiting Contoh', 'slug' => 'kepiting-contoh']);

        $this->get('/produk/kepiting-contoh')->assertOk()->assertSeeInOrder(['Bahari Contoh', 'Toko contoh']);
    });

    it('explains a demo store on its page instead of the verified badge', function () {
        $this->get('/toko/bahari-contoh')
            ->assertOk()
            ->assertSee('Toko contoh')
            ->assertSee(DEMO_EXPLANATION)
            ->assertDontSee('Lapak terverifikasi');
    });

    it('shows no demo label or explanation on a real store page', function () {
        $this->get('/toko/lapak-asli')
            ->assertOk()
            ->assertDontSee('Toko contoh')
            ->assertDontSee(DEMO_EXPLANATION);
    });
});

describe('contact', function () {
    it('has no WhatsApp, phone, or directions on a demo store page', function () {
        $html = $this->get('/toko/bahari-contoh')->assertOk()->getContent();

        expect($html)->not->toContain('wa.me')
            ->not->toContain('tel:')
            ->not->toContain('2222222222')
            ->not->toContain('google.com/maps');
    });

    it('has no WhatsApp or phone on the page of a demo product', function () {
        demoTestProduct($this->demo, ['slug' => 'kepiting-contoh']);

        $html = $this->get('/produk/kepiting-contoh')->assertOk()->getContent();

        expect($html)->not->toContain('wa.me')
            ->not->toContain('tel:')
            ->not->toContain('2222222222');
    });

    it('leaves the phone out of the JSON-LD of a demo store', function () {
        $html = $this->get('/toko/bahari-contoh')->assertOk()->getContent();
        $json = Str::betweenFirst($html, '<script type="application/ld+json">', '</script>');

        expect(json_decode($json, true, flags: JSON_THROW_ON_ERROR))->not->toHaveKey('telephone');
    });

    it('keeps the contact buttons of a real store', function () {
        $html = $this->get('/toko/lapak-asli')->assertOk()->getContent();

        expect($html)->toContain('wa.me/6281111111111')
            ->toContain('tel:+6281111111111');
    });
});

describe('order', function () {
    it('lists real products before demo products in the catalog for every sort', function (string $sort, int $demoPrice, int $realPrice) {
        demoTestProduct($this->demo, ['name' => 'Produk Contoh', 'created_at' => now()], $demoPrice);
        demoTestProduct($this->real, ['name' => 'Produk Asli', 'created_at' => now()->subDay()], $realPrice);

        Livewire::test(Catalog::class)
            ->set('sort', $sort)
            ->assertSeeInOrder(['Produk Asli', 'Produk Contoh']);
    })->with([
        'newest, demo is newer' => ['terbaru', 100_000, 100_000],
        'cheapest, demo is cheaper' => ['termurah', 50_000, 150_000],
        'dearest, demo is dearer' => ['termahal', 150_000, 50_000],
    ]);

    it('lists real products before demo products when sorting by distance', function () {
        demoTestProduct($this->demo, ['name' => 'Produk Contoh']);
        demoTestProduct($this->real, ['name' => 'Produk Asli']);

        // The demo store is the nearest to this point.
        Livewire::test(Catalog::class)
            ->call('locate', -6.1, 106.8)
            ->set('sort', 'terdekat')
            ->assertSeeInOrder(['Produk Asli', 'Produk Contoh']);
    });

    it('lists real products first under recently confirmed on the home page', function () {
        demoTestProduct($this->demo, ['name' => 'Produk Contoh'], confirmedAt: now());
        demoTestProduct($this->real, ['name' => 'Produk Asli'], confirmedAt: now()->subHours(3));

        $section = demoTestSection($this->get('/')->getContent(), 'baru-judul');

        expect(Str::position($section, 'Produk Asli'))->toBeLessThan(Str::position($section, 'Produk Contoh'));
    });

    it('lists real products first under similar products', function () {
        demoTestProduct($this->real, ['name' => 'Kepiting Dilihat', 'slug' => 'kepiting-dilihat', 'created_at' => now()->subDays(3)]);
        demoTestProduct($this->demo, ['name' => 'Serupa Contoh', 'created_at' => now()]);
        demoTestProduct(Store::factory()->active()->create(), ['name' => 'Serupa Asli', 'created_at' => now()->subDay()]);

        $section = demoTestSection($this->get('/produk/kepiting-dilihat')->getContent(), 'produk-serupa');

        expect(Str::position($section, 'Serupa Asli'))->toBeLessThan(Str::position($section, 'Serupa Contoh'));
    });

    it('lists real stores first under other stores, even when a demo store is cheaper', function () {
        demoTestProduct(Store::factory()->active()->create(), ['slug' => 'kepiting-dilihat']);
        demoTestProduct($this->demo, price: 150_000);
        demoTestProduct($this->real, price: 185_000);

        $section = demoTestSection($this->get('/produk/kepiting-dilihat')->getContent(), 'lapak-lain');

        expect(Str::position($section, 'Lapak Asli'))->toBeLessThan(Str::position($section, 'Bahari Contoh'));
    });
});

describe('verified stores on the home page', function () {
    it('never lists demo stores', function () {
        Store::factory()->active()->create(['name' => 'Lapak Asli Kedua']);
        Store::factory()->active()->demo()->count(2)->sequence(['name' => 'Contoh Dua'], ['name' => 'Contoh Tiga'])->create();

        $section = demoTestSection($this->get('/')->getContent(), 'lapak-judul');

        expect($section)->toContain('Lapak Asli')
            ->toContain('Lapak Asli Kedua')
            ->not->toContain('Bahari Contoh')
            ->not->toContain('Contoh Dua')
            ->not->toContain('Contoh Tiga');
    });

    it('is not rendered at all when every store is a demo store', function () {
        $this->real->delete();

        $html = $this->get('/')->assertOk()->getContent();

        expect($html)->not->toContain('aria-labelledby="lapak-judul"')
            ->not->toContain('Lapak terverifikasi');
    });

    it('still lists demo products when every store is a demo store', function () {
        $this->real->delete();
        demoTestProduct($this->demo, ['name' => 'Kepiting Contoh']);

        $this->get('/produk')->assertOk()->assertSee('Kepiting Contoh')->assertSee('Toko contoh');
    });
});
