<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\StoreShippingMethod;
use App\Support\StockCheckTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->store = Store::factory()->active()->create([
        'name' => 'Toko Bahari',
        'slug' => 'toko-bahari',
        'description' => 'Kepiting dan cumi segar langsung dari nelayan Muara Angke sejak 2010.',
        'city' => 'Jakarta Utara',
        'address' => 'Jl. Dermaga Raya No. 5, Muara Angke',
        'phone' => '081234567890',
        'lat' => '-6.1065000',
        'lng' => '106.7765000',
        'bank_name' => 'Bank Rahasia',
        'bank_account_no' => '9876543210',
        'bank_account_name' => 'Pemilik Rahasia',
    ]);
});

/**
 * A product buyers can see, with one active variant, from the given store.
 *
 * @param  array<string, mixed>  $attributes
 */
function storePageProduct(Store $store, array $attributes = []): Product
{
    $product = Product::factory()->for($store)->create($attributes);
    ProductVariant::factory()->for($product)->create();

    return $product;
}

/**
 * The decoded JSON-LD block of a store page.
 *
 * @return array<string, mixed>
 */
function storeJsonLdOf(string $html): array
{
    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

    expect($matches)->toHaveKey(1);

    return json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
}

describe('profile', function () {
    it('shows the store with its contact links', function () {
        $this->get('/toko/toko-bahari')
            ->assertOk()
            ->assertSee('Toko Bahari')
            ->assertSee('Kepiting dan cumi segar langsung dari nelayan Muara Angke sejak 2010.')
            ->assertSee('Jakarta Utara')
            ->assertSee('Jl. Dermaga Raya No. 5, Muara Angke')
            ->assertSee('href="'.e($this->store->googleMapsUrl()).'"', escape: false)
            ->assertSee('href="https://wa.me/6281234567890', escape: false)
            ->assertSee('href="tel:+6281234567890"', escape: false)
            ->assertSee('rel="noopener noreferrer"', escape: false);
    });

    it('shows the logo when there is one', function () {
        $this->store->update(['logo' => 'stores/logo-bahari.webp']);

        $this->get('/toko/toko-bahari')
            ->assertOk()
            ->assertSee('src="'.Storage::disk('public')->url('stores/logo-bahari.webp').'"', escape: false);
    });

    it('is a 404 for a store that is not active', function (string $state) {
        Store::factory()->{$state}()->create(['slug' => 'toko-tersembunyi']);

        $this->get('/toko/toko-tersembunyi')->assertNotFound();
    })->with(['pending', 'rejected', 'suspended']);

    it('is a 404 for an unknown slug', function () {
        $this->get('/toko/tidak-ada')->assertNotFound();
    });

    it('keeps a demo store out of search results', function () {
        Store::factory()->active()->demo()->create(['slug' => 'toko-contoh']);

        $this->get('/toko/toko-contoh')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', escape: false);
    });

    it('is a 404 for a suspended demo store', function () {
        Store::factory()->suspended()->demo()->create(['slug' => 'toko-contoh']);

        $this->get('/toko/toko-contoh')->assertNotFound();
    });
});

describe('products', function () {
    it('lists only the products of this store that buyers can see', function () {
        storePageProduct($this->store, ['name' => 'Kepiting Bakau Super']);
        storePageProduct(Store::factory()->active()->create(), ['name' => 'Produk Toko Lain']);
        storePageProduct($this->store, ['name' => 'Produk Diblokir', 'blocked_at' => now(), 'block_reason' => 'Foto tidak sesuai.']);
        storePageProduct($this->store, ['name' => 'Produk Nonaktif', 'is_active' => false]);
        storePageProduct($this->store, ['name' => 'Produk Terhapus'])->delete();
        ProductVariant::factory()->for(Product::factory()->for($this->store)->create(['name' => 'Produk Tanpa Varian Aktif']))->create(['is_active' => false]);

        $html = $this->get('/toko/toko-bahari')->assertOk()->getContent();
        // The other store's product may show under the nearby stores, never in this list.
        $list = Str::before(Str::after($html, 'aria-labelledby="produk-toko"'), '</section>');

        expect($list)
            ->toContain('Kepiting Bakau Super')
            ->toContain('href="'.url('/produk/'.Product::query()->where('name', 'Kepiting Bakau Super')->value('slug')).'"')
            ->not->toContain('Produk Toko Lain')
            ->and($html)
            ->not->toContain('Produk Diblokir')
            ->not->toContain('Produk Nonaktif')
            ->not->toContain('Produk Terhapus')
            ->not->toContain('Produk Tanpa Varian Aktif');
    });

    it('shows a message when the store has no products yet', function () {
        $this->get('/toko/toko-bahari')->assertOk()->assertSee('Toko ini belum punya produk');
    });

    it('pages by 24, newest first', function () {
        foreach (range(1, 30) as $number) {
            storePageProduct($this->store, ['name' => sprintf('Produk Nomor %02d.', $number), 'created_at' => now()->subMinutes($number)]);
        }

        $first = $this->get('/toko/toko-bahari')->assertOk()->getContent();
        $second = $this->get('/toko/toko-bahari?page=2')->assertOk()->getContent();

        $countOn = fn (string $html) => collect(range(1, 30))
            ->filter(fn (int $number) => str_contains($html, sprintf('Produk Nomor %02d.', $number)))
            ->count();

        expect($countOn($first))->toBe(24)
            ->and($countOn($second))->toBe(6)
            ->and($first)->toContain('Produk Nomor 01.')
            ->and($second)->toContain('Produk Nomor 30.');
    });

    it('runs the same number of queries for 3 and 30 products', function () {
        $count = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get('/toko/toko-bahari')->assertOk();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        foreach (range(1, 3) as $i) {
            storePageProduct($this->store);
        }
        $few = $count();

        foreach (range(1, 27) as $i) {
            storePageProduct($this->store);
        }

        expect($count())->toBe($few);
    });
});

describe('SEO', function () {
    it('has the title, meta description, canonical, and Open Graph tags', function () {
        $this->get('/toko/toko-bahari?page=1')
            ->assertOk()
            ->assertSee('<title>Toko Bahari, Jakarta Utara | '.config('app.name').'</title>', escape: false)
            ->assertSee('<meta name="description" content="Kepiting dan cumi segar langsung dari nelayan Muara Angke sejak 2010.">', escape: false)
            ->assertSee('<link rel="canonical" href="'.url('/toko/toko-bahari').'">', escape: false)
            ->assertSee('<meta property="og:title" content="Toko Bahari, Jakarta Utara | '.config('app.name').'">', escape: false)
            ->assertDontSee('og:image')
            ->assertDontSee('noindex');
    });

    it('uses the logo as the Open Graph image', function () {
        $this->store->update(['logo' => 'stores/logo-bahari.webp']);

        $this->get('/toko/toko-bahari')
            ->assertOk()
            ->assertSee('<meta property="og:image" content="'.Storage::disk('public')->url('stores/logo-bahari.webp').'">', escape: false);
    });

    it('cuts a long description at a word within 155 characters', function () {
        $this->store->update(['description' => str_repeat("Kepiting segar dari Muara Angke.\n\n", 12)]);

        $html = $this->get('/toko/toko-bahari')->assertOk()->getContent();
        preg_match('#<meta name="description" content="([^"]*)">#', $html, $matches);
        $meta = html_entity_decode($matches[1]);

        expect(mb_strlen($meta))->toBeLessThanOrEqual(155)
            ->and($meta)->toEndWith('…')
            ->and($meta)->not->toContain("\n")
            ->and(str_repeat('Kepiting segar dari Muara Angke. ', 12))->toStartWith(mb_substr($meta, 0, -1));
    });

    it('describes a store without a description by its name and city', function () {
        $this->store->update(['description' => null]);

        $this->get('/toko/toko-bahari')
            ->assertOk()
            ->assertSee('<meta name="description" content="Toko Bahari menjual hasil laut segar di Jakarta Utara.">', escape: false);
    });

    it('describes the store in JSON-LD', function () {
        $data = storeJsonLdOf($this->get('/toko/toko-bahari')->assertOk()->getContent());

        expect($data)->toBe([
            '@context' => 'https://schema.org',
            '@type' => 'Store',
            'name' => 'Toko Bahari',
            'description' => 'Kepiting dan cumi segar langsung dari nelayan Muara Angke sejak 2010.',
            'url' => url('/toko/toko-bahari'),
            'telephone' => '+6281234567890',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => 'Jl. Dermaga Raya No. 5, Muara Angke',
                'addressLocality' => 'Jakarta Utara',
                'addressCountry' => 'ID',
            ],
            'geo' => [
                '@type' => 'GeoCoordinates',
                'latitude' => -6.1065,
                'longitude' => 106.7765,
            ],
        ]);
    });

    it('adds the logo to the JSON-LD when there is one', function () {
        $this->store->update(['logo' => 'stores/logo-bahari.webp']);

        expect(storeJsonLdOf($this->get('/toko/toko-bahari')->getContent())['image'])
            ->toBe(Storage::disk('public')->url('stores/logo-bahari.webp'));
    });
});

describe('security', function () {
    it('never shows bank details', function () {
        $html = $this->get('/toko/toko-bahari')->assertOk()->getContent();

        expect($html)
            ->not->toContain('Bank Rahasia')
            ->not->toContain('9876543210')
            ->not->toContain('Pemilik Rahasia');
    });

    it('escapes the store text and keeps the JSON-LD valid', function () {
        $this->store->update([
            'name' => 'Toko </script><script>alert(1)</script>',
            'description' => '<img src=x onerror=alert(2)>',
            'address' => '<b>Jl. Dermaga</b>',
        ]);

        $html = $this->get('/toko/toko-bahari')->assertOk()->getContent();

        expect($html)
            ->not->toContain('<script>alert(1)</script>')
            ->not->toContain('<img src=x onerror=alert(2)>')
            ->not->toContain('<b>Jl. Dermaga</b>')
            ->and(storeJsonLdOf($html)['name'])->toBe('Toko </script><script>alert(1)</script>');
    });
});

describe('layout', function () {
    it('heads the page with the name, city, and contact links', function () {
        $this->get('/toko/toko-bahari')
            ->assertOk()
            ->assertSeeInOrder(['<h1', 'Toko Bahari', 'Jakarta Utara', 'https://wa.me/', 'tel:', e($this->store->googleMapsUrl())], escape: false);
    });

    it('lists the products with the product card and their count', function () {
        storePageProduct($this->store, ['name' => 'Kepiting Bakau Super']);
        storePageProduct($this->store, ['name' => 'Cumi Segar']);

        $this->get('/toko/toko-bahari')
            ->assertOk()
            ->assertSee('2 dijual')
            ->assertSee('Mulai')
            ->assertSee('<ul', escape: false);
    });

    it('shows the empty state when there are no products', function () {
        $this->get('/toko/toko-bahari')
            ->assertOk()
            ->assertSee('Toko ini belum punya produk')
            ->assertSee('href="'.route('catalog').'"', escape: false);
    });

    it('pages with numbered links', function () {
        foreach (range(1, 25) as $number) {
            storePageProduct($this->store);
        }

        $this->get('/toko/toko-bahari')
            ->assertOk()
            ->assertSee('<nav aria-label="Halaman"', escape: false)
            ->assertSee('aria-current="page"', escape: false)
            ->assertSee('aria-label="Halaman 2"', escape: false)
            ->assertSee('href="'.e(url('/toko/toko-bahari?page=2')).'"', escape: false)
            ->assertSee('wire:click.prevent="gotoPage(2, &#039;page&#039;)"', escape: false);
    });

    it('opens every new tab with noopener noreferrer', function () {
        $html = $this->get('/toko/toko-bahari')->assertOk()->getContent();

        preg_match_all('#<a\s[^>]*target="_blank"[^>]*>#', $html, $links);

        expect($links[0])->toHaveCount(3)
            ->each->toContain('rel="noopener noreferrer"');
    });
});

/**
 * A store that sells one visible product, at the given distance north of
 * Toko Bahari (-6.1065, 106.7765).
 *
 * @param  array<string, mixed>  $attributes
 */
function nearbyStore(float $km, array $attributes = []): Store
{
    $store = Store::factory()->active()->create([
        'lat' => number_format(-6.1065 + $km / 6371 * 180 / M_PI, 7, '.', ''),
        'lng' => '106.7765000',
        ...$attributes,
    ]);
    storePageProduct($store, ['name' => 'Produk '.$store->name]);

    return $store;
}

/**
 * The page of Toko Bahari, or with the given query string.
 */
function storePageHtml(string $query = ''): string
{
    return test()->get('/toko/toko-bahari'.$query)->assertOk()->getContent();
}

/**
 * The markup of one figure in the identity card, up to the next figure.
 */
function storeFigure(string $html, string $label): string
{
    expect($html)->toContain('<dt class');

    return Str::before(Str::after($html, '>'.$label.'</dt>'), '</dd>');
}

describe('cover and identity card', function () {
    it('uses the photo of the newest product buyers can see as the cover', function (Closure $hide) {
        $older = ProductImage::factory()->for(storePageProduct($this->store, ['created_at' => now()->subDays(2)]))->create();
        $hidden = ProductImage::factory()->for(storePageProduct($this->store, ['created_at' => now()]))->create();
        $hide($hidden->product);

        $html = storePageHtml();

        expect($html)
            ->toMatch('#data-store-cover[^>]*src="'.preg_quote($older->url(), '#').'"#')
            ->not->toContain($hidden->url());
    })->with([
        'blocked' => [fn (Product $p) => $p->forceFill(['blocked_at' => now(), 'block_reason' => 'Foto tidak sesuai.'])->save()],
        'inactive' => [fn (Product $p) => $p->update(['is_active' => false])],
    ]);

    it('shows a plain sand band without product photos', function () {
        storePageProduct($this->store);

        expect(storePageHtml())->toContain('data-store-cover="polos"')->not->toMatch('#<img[^>]*data-store-cover#');
    });

    it('labels a real store as verified', function () {
        expect(storePageHtml())->toContain('Lapak terverifikasi')->not->toContain('Toko contoh');
    });

    it('offers WhatsApp, phone, and directions to the coordinates of the store', function () {
        $html = storePageHtml();
        $directions = 'https://www.google.com/maps/dir/?api=1&destination=-6.1065000,106.7765000';

        expect($html)
            ->toContain('Chat WhatsApp')
            ->toMatch('#<a[^>]*href="'.preg_quote(e($directions), '#').'"[^>]*rel="noopener noreferrer"[^>]*>\s*Petunjuk arah#')
            ->not->toContain(urlencode('Jl. Dermaga Raya'));
    });

    it('shows when the stock was last checked, from active variants of visible products', function () {
        $this->travelTo(CarbonImmutable::parse('2026-10-16 09:00', StockCheckTime::TIMEZONE)->utc());
        $at = fn (string $time) => CarbonImmutable::parse("2026-10-16 {$time}", StockCheckTime::TIMEZONE)->utc();

        storePageProduct($this->store)->variants()->sole()->forceFill(['stock_confirmed_at' => $at('05:00')])->save();
        storePageProduct($this->store)->variants()->sole()->forceFill(['stock_confirmed_at' => $at('06:30')])->save();
        $withInactive = storePageProduct($this->store);
        $withInactive->variants()->sole()->forceFill(['stock_confirmed_at' => $at('05:30')])->save();
        ProductVariant::factory()->for($withInactive)->create(['is_active' => false])->forceFill(['stock_confirmed_at' => $at('08:00')])->save();
        storePageProduct($this->store, ['blocked_at' => now(), 'block_reason' => 'Foto tidak sesuai.'])->variants()->sole()->forceFill(['stock_confirmed_at' => $at('08:30')])->save();

        expect(storeFigure(storePageHtml(), 'Stok dicek'))->toContain('06.30')->not->toContain('08.');
    });

    it('says the stock was not checked when no variant has been', function () {
        expect(storeFigure(storePageHtml(), 'Stok dicek'))->toContain('Belum dicek');
    });

    it('counts the products buyers can see', function () {
        storePageProduct($this->store);
        storePageProduct($this->store);
        storePageProduct($this->store, ['is_active' => false]);

        expect(storeFigure(storePageHtml(), 'Produk'))->toContain('2 dijual');
    });

    it('shows 0 for sale and the empty state without products', function () {
        expect(storePageHtml())
            ->toContain('0 dijual')
            ->toContain('Toko ini belum punya produk');
    });

    it('says how the store takes orders from its active shipping methods', function (array $methods, string $text) {
        foreach ($methods as $method) {
            StoreShippingMethod::factory()->for($this->store)->{$method}()->create();
        }
        StoreShippingMethod::factory()->for($this->store)->expedition()->create(['is_active' => false]);

        expect(storeFigure(storePageHtml(), 'Terima pesanan'))->toContain($text);
    })->with([
        'delivery and pickup' => [['ownDeliveryFlat', 'pickup'], 'Antar atau ambil'],
        'pickup only' => [['pickup'], 'Hanya ambil di lapak'],
        'own delivery only' => [['ownDeliveryPerKm'], 'Hanya diantar'],
        'ojol only' => [['ojol'], 'Hanya diantar'],
        'none active' => [[], 'Tanyakan ke penjual'],
    ]);
});

describe('about card', function () {
    it('puts the description, a map picture, the address, and a Google Maps link in one card', function () {
        $card = Str::before(Str::after(storePageHtml(), 'aria-label="Tentang lapak"'), '</aside>');

        expect($card)
            ->toContain('Tentang lapak')
            ->toContain('Kepiting dan cumi segar langsung dari nelayan Muara Angke sejak 2010.')
            ->toMatch('#<svg[^>]*aria-hidden="true"#')
            ->toContain('Jl. Dermaga Raya No. 5, Muara Angke')
            ->toMatch('#href="'.preg_quote(e($this->store->googleMapsUrl()), '#').'"[^>]*>\s*Buka di Google Maps#')
            ->not->toContain('>Alamat<');
    });

    it('leaves out the about text when there is no description', function () {
        $this->store->update(['description' => null]);

        expect(Str::before(Str::after(storePageHtml(), 'aria-label="Tentang lapak"'), '</aside>'))
            ->not->toContain('<h2')
            ->toContain('Jl. Dermaga Raya No. 5, Muara Angke');
    });
});

describe('category tabs', function () {
    beforeEach(function () {
        $this->kepiting = Category::factory()->create(['name' => 'Kepiting', 'slug' => 'kepiting']);
        $bakau = Category::factory()->childOf($this->kepiting)->create(['name' => 'Bakau', 'slug' => 'bakau']);
        $soka = Category::factory()->childOf($this->kepiting)->create(['name' => 'Soka', 'slug' => 'soka']);
        $cumi = Category::factory()->create(['name' => 'Cumi', 'slug' => 'cumi']);

        storePageProduct($this->store, ['name' => 'Kepiting Bakau Super', 'category_id' => $bakau->id]);
        storePageProduct($this->store, ['name' => 'Kepiting Soka Beku', 'category_id' => $soka->id]);
        storePageProduct($this->store, ['name' => 'Cumi Segar Pagi', 'category_id' => $cumi->id]);
        storePageProduct($this->store, ['name' => 'Cumi Tersembunyi', 'category_id' => $cumi->id, 'is_active' => false]);
    });

    it('lists the root categories of the store with their number of products', function () {
        $tabs = Str::before(Str::after(storePageHtml(), 'aria-label="Kategori di lapak ini"'), '</nav>');

        expect($tabs)
            ->toMatch('#href="'.preg_quote(url('/toko/toko-bahari'), '#').'"[^>]*aria-current="page"[^>]*>\s*Semua\s*<span[^>]*>3</span>#')
            ->toMatch('#href="'.preg_quote(e(url('/toko/toko-bahari?kategori=kepiting')), '#').'"[^>]*>\s*Kepiting\s*<span[^>]*>2</span>#')
            ->toMatch('#>\s*Cumi\s*<span[^>]*>1</span>#')
            ->not->toContain('Bakau');
    });

    it('shows only the products of the chosen root category', function () {
        $html = storePageHtml('?kategori=kepiting');

        expect($html)
            ->toContain('Kepiting Bakau Super')
            ->toContain('Kepiting Soka Beku')
            ->not->toContain('Cumi Segar Pagi')
            ->toMatch('#href="'.preg_quote(e(url('/toko/toko-bahari?kategori=kepiting')), '#').'"[^>]*aria-current="page"#');
    });

    it('shows every product for a category the store does not sell', function (string $slug) {
        expect(storePageHtml('?kategori='.$slug))
            ->toContain('Kepiting Bakau Super')
            ->toContain('Cumi Segar Pagi');
    })->with(['tidak-ada', 'bakau']);
});

describe('nearby stores', function () {
    it('lists up to 3 other stores by distance from this store, real stores first', function () {
        nearbyStore(5.8, ['name' => 'Lapak Jauh', 'slug' => 'lapak-jauh']);
        nearbyStore(1.9, ['name' => 'Lapak Dekat', 'slug' => 'lapak-dekat']);
        nearbyStore(3.1, ['name' => 'Lapak Tengah', 'slug' => 'lapak-tengah']);
        nearbyStore(0.5, ['name' => 'Lapak Contoh', 'is_demo' => true]);
        nearbyStore(0.8, ['name' => 'Lapak Ditangguhkan'])->forceFill(['status' => 'suspended'])->save();
        Store::factory()->active()->create(['name' => 'Lapak Kosong', 'lat' => '-6.1070000', 'lng' => '106.7765000']);

        $section = Str::before(Str::after(storePageHtml(), 'aria-labelledby="lapak-sekitar"'), '</section>');

        expect($section)
            ->toContain('Lapak lain di sekitar')
            ->toContain('1,9 km')
            ->toContain('3,1 km')
            ->toContain('5,8 km')
            ->not->toContain('Lapak Contoh')
            ->not->toContain('Lapak Ditangguhkan')
            ->not->toContain('Lapak Kosong')
            ->and(strpos($section, 'Lapak Dekat'))->toBeLessThan(strpos($section, 'Lapak Tengah'))
            ->and(strpos($section, 'Lapak Tengah'))->toBeLessThan(strpos($section, 'Lapak Jauh'));
    });

    it('lists a demo store after the real stores', function () {
        nearbyStore(0.5, ['name' => 'Lapak Contoh', 'is_demo' => true]);
        nearbyStore(3.1, ['name' => 'Lapak Asli']);

        $section = Str::before(Str::after(storePageHtml(), 'aria-labelledby="lapak-sekitar"'), '</section>');

        expect(strpos($section, 'Lapak Asli'))->toBeLessThan(strpos($section, 'Lapak Contoh'))
            ->and($section)->toContain('Toko contoh');
    });

    it('shows each store with its products, up to 3, and a link to visit it', function () {
        $near = nearbyStore(1.9, ['name' => 'Lapak Dekat', 'slug' => 'lapak-dekat']);
        foreach (range(1, 3) as $number) {
            storePageProduct($near, ['name' => "Jualan Dekat {$number}.", 'created_at' => now()->addMinutes($number)]);
        }

        $section = Str::before(Str::after(storePageHtml(), 'aria-labelledby="lapak-sekitar"'), '</section>');

        expect($section)
            ->toContain('4 produk')
            ->toContain('Jualan Dekat 3.')
            ->toContain('Jualan Dekat 1.')
            ->not->toContain('Produk Lapak Dekat')
            ->toContain('Mulai')
            ->toMatch('#href="'.preg_quote(route('stores.show', 'lapak-dekat'), '#').'"[^>]*>\s*Kunjungi lapak#');
    });

    it('is not rendered without other active stores selling something', function () {
        nearbyStore(1.9)->forceFill(['status' => 'suspended'])->save();

        expect(storePageHtml())->not->toContain('aria-labelledby="lapak-sekitar"');
    });

    it('never shows the coordinates or bank details of the other stores', function () {
        nearbyStore(1.9, ['bank_account_no' => '1111222233', 'lng' => '106.7765432']);

        expect(storePageHtml())->not->toContain('1111222233')->not->toContain('106.7765432');
    });

    it('runs the same number of queries for 2 and 20 other stores', function () {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            storePageHtml();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        storePageProduct($this->store);
        foreach (range(1, 2) as $i) {
            ProductImage::factory()->for(nearbyStore($i)->products()->sole())->create();
        }
        $few = $count();

        foreach (range(3, 20) as $i) {
            ProductImage::factory()->for(nearbyStore($i)->products()->sole())->create();
        }

        expect($count())->toBe($few);
    });
});

describe('map', function () {
    it('gives the map its coordinates and a label through attributes', function () {
        expect(storePageHtml())
            ->toMatch('#<div[^>]*data-store-map[^>]*>#')
            ->toContain('data-lat="-6.1065000"')
            ->toContain('data-lng="106.7765000"')
            ->toMatch('#data-store-map[^>]*role="img"[^>]*aria-label="Peta lokasi Toko Bahari"#');
    });

    it('keeps the drawn map inside as the fallback', function () {
        $map = Str::before(Str::after(storePageHtml(), 'data-store-map'), '</svg>');

        expect($map)->toContain('<svg');
    });

    it('shows the map of a demo store without a Google Maps link', function () {
        Store::factory()->active()->demo()->create(['slug' => 'toko-contoh', 'lat' => '-6.1000000', 'lng' => '106.8000000']);

        expect($this->get('/toko/toko-contoh')->assertOk()->getContent())
            ->toContain('data-store-map')
            ->toContain('data-lat="-6.1000000"')
            ->not->toContain('google.com/maps');
    });

    it('loads no map script, style, or tile from another site', function () {
        expect(storePageHtml())
            ->not->toContain('unpkg.com')
            ->not->toContain('cdnjs')
            ->not->toContain('openstreetmap.org')
            ->not->toContain('leaflet.css');
    });

    it('escapes the store name in the map label', function () {
        $this->store->update(['name' => 'Toko <script>alert(1)</script>']);

        expect(storePageHtml())
            ->toContain('aria-label="Peta lokasi Toko &lt;script&gt;alert(1)&lt;/script&gt;"')
            ->not->toContain('<script>alert(1)</script>');
    });
});

describe('tabs without reload', function () {
    beforeEach(function () {
        $this->kepiting = Category::factory()->create(['name' => 'Kepiting', 'slug' => 'kepiting']);
        $this->bakau = Category::factory()->childOf($this->kepiting)->create(['name' => 'Bakau', 'slug' => 'bakau']);
        $this->cumi = Category::factory()->create(['name' => 'Cumi', 'slug' => 'cumi']);

        storePageProduct($this->store, ['name' => 'Kepiting Bakau Super', 'category_id' => $this->bakau->id]);
        storePageProduct($this->store, ['name' => 'Cumi Segar Pagi', 'category_id' => $this->cumi->id]);
    });

    it('renders the products as a Livewire component', function () {
        $this->get('/toko/toko-bahari')->assertOk()->assertSeeLivewire('store-products');
    });

    it('switches the category in place and goes back to the first page', function () {
        Livewire::withQueryParams(['page' => 2])
            ->test('store-products', ['store' => $this->store])
            ->call('chooseCategory', 'kepiting')
            ->assertSet('category', 'kepiting')
            ->assertSet('paginators.page', 1)
            ->assertSee('Kepiting Bakau Super')
            ->assertDontSee('Cumi Segar Pagi')
            ->assertSeeHtml('aria-current="page"')
            ->call('chooseCategory', '')
            ->assertSet('category', '')
            ->assertSee('Cumi Segar Pagi');
    });

    it('ignores a category the store does not sell as a root category', function (string $slug) {
        Category::factory()->create(['slug' => 'udang']);

        Livewire::test('store-products', ['store' => $this->store])
            ->call('chooseCategory', 'kepiting')
            ->call('chooseCategory', $slug)
            ->assertSet('category', '')
            ->assertSee('Kepiting Bakau Super')
            ->assertSee('Cumi Segar Pagi');
    })->with(['a subcategory' => ['bakau'], 'not sold here' => ['udang'], 'unknown' => ['tidak-ada']]);

    it('reads the category from the URL', function () {
        Livewire::withQueryParams(['kategori' => 'cumi'])
            ->test('store-products', ['store' => $this->store])
            ->assertSet('category', 'cumi')
            ->assertSee('Cumi Segar Pagi')
            ->assertDontSee('Kepiting Bakau Super');
    });

    it('keeps the tabs as real links that Livewire takes over', function () {
        $html = storePageHtml();

        expect($html)
            ->toMatch('#<a[^>]*href="'.preg_quote(e(url('/toko/toko-bahari?kategori=kepiting')), '#').'"[^>]*wire:click\.prevent="chooseCategory\(\'kepiting\'\)"#')
            ->toMatch('#<a[^>]*href="'.preg_quote(url('/toko/toko-bahari'), '#').'"[^>]*wire:click\.prevent="chooseCategory\(\'\'\)"#');
    });

    it('keeps the chosen category in the page links, which lead back to this store', function () {
        foreach (range(1, 25) as $i) {
            storePageProduct($this->store, ['category_id' => $this->bakau->id]);
        }

        expect(storePageHtml('?kategori=kepiting'))
            ->toContain('href="'.e(url('/toko/toko-bahari?kategori=kepiting&page=2')).'"');
    });

    it('dims the list and announces loading while it changes', function () {
        expect(storePageHtml())
            ->toContain('wire:loading.class="opacity-60"')
            ->toMatch('#<[^>]*wire:loading[^>]*role="status"[^>]*>\s*Memuat produk#');
    });

    it('does not let the browser swap the store', function () {
        $other = Store::factory()->active()->create();

        expect(fn () => Livewire::test('store-products', ['store' => $this->store])->set('store', $other->id))
            ->toThrow(Exception::class);
    });

    it('escapes the category names in the tabs', function () {
        $this->cumi->update(['name' => 'Cumi <script>alert(1)</script>']);

        expect(storePageHtml())
            ->toContain('Cumi &lt;script&gt;alert(1)&lt;/script&gt;')
            ->not->toContain('<script>alert(1)</script>');
    });

    it('runs the same number of queries to switch tabs for 3 and 30 products', function () {
        $count = function (): int {
            $component = Livewire::test('store-products', ['store' => $this->store]);

            DB::flushQueryLog();
            DB::enableQueryLog();
            $component->call('chooseCategory', 'kepiting');
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $few = $count();

        foreach (range(1, 27) as $i) {
            storePageProduct($this->store, ['category_id' => $this->bakau->id]);
        }

        expect($count())->toBe($few);
    });
});
