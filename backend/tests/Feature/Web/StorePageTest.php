<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
});

describe('products', function () {
    it('lists only the products of this store that buyers can see', function () {
        storePageProduct($this->store, ['name' => 'Kepiting Bakau Super']);
        storePageProduct(Store::factory()->active()->create(), ['name' => 'Produk Toko Lain']);
        storePageProduct($this->store, ['name' => 'Produk Diblokir', 'blocked_at' => now(), 'block_reason' => 'Foto tidak sesuai.']);
        storePageProduct($this->store, ['name' => 'Produk Nonaktif', 'is_active' => false]);
        storePageProduct($this->store, ['name' => 'Produk Terhapus'])->delete();
        ProductVariant::factory()->for(Product::factory()->for($this->store)->create(['name' => 'Produk Tanpa Varian Aktif']))->create(['is_active' => false]);

        $this->get('/toko/toko-bahari')
            ->assertOk()
            ->assertSee('Kepiting Bakau Super')
            ->assertSee('href="'.url('/produk/'.Product::query()->where('name', 'Kepiting Bakau Super')->value('slug')).'"', escape: false)
            ->assertDontSee('Produk Toko Lain')
            ->assertDontSee('Produk Diblokir')
            ->assertDontSee('Produk Nonaktif')
            ->assertDontSee('Produk Terhapus')
            ->assertDontSee('Produk Tanpa Varian Aktif');
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
