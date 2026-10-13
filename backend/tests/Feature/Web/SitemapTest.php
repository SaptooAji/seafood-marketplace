<?php

declare(strict_types=1);

use App\Enums\StoreStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Support\Carbon;

/**
 * A product buyers can see, with one active variant.
 *
 * @param  array<string, mixed>  $attributes
 */
function sitemapProduct(Store $store, array $attributes = []): Product
{
    $product = Product::factory()->for($store)->create($attributes);
    ProductVariant::factory()->for($product)->create();

    return $product;
}

/**
 * The sitemap as `loc => lastmod` (null when there is no lastmod). Fails the
 * test when the body is not valid XML.
 *
 * @return array<string, string|null>
 */
function sitemapEntries(string $xml): array
{
    $sitemap = simplexml_load_string($xml);

    expect($sitemap)->not->toBeFalse()
        ->and($sitemap->getName())->toBe('urlset')
        ->and($sitemap->getNamespaces())->toBe(['' => 'http://www.sitemaps.org/schemas/sitemap/0.9']);

    $entries = [];
    foreach ($sitemap->url as $url) {
        $entries[(string) $url->loc] = isset($url->lastmod) ? (string) $url->lastmod : null;
    }

    return $entries;
}

beforeEach(function () {
    $this->store = Store::factory()->active()->create(['slug' => 'toko-bahari']);
});

describe('sitemap', function () {
    it('lists the home page, the catalog, visible products, and active stores', function () {
        $product = sitemapProduct($this->store, ['slug' => 'kepiting-bakau', 'updated_at' => Carbon::parse('2026-10-01 08:30:00')]);

        $response = $this->get('/sitemap.xml')->assertOk();

        expect($response->headers->get('Content-Type'))->toStartWith('application/xml')
            ->and(sitemapEntries($response->getContent()))->toEqualCanonicalizing([
                url('/') => null,
                url('/produk') => null,
                url('/produk/kepiting-bakau') => $product->updated_at->toAtomString(),
                url('/toko/toko-bahari') => null,
            ]);
    });

    it('leaves out products and stores buyers cannot see', function () {
        sitemapProduct($this->store, ['slug' => 'produk-tampil']);
        sitemapProduct($this->store, ['slug' => 'produk-diblokir', 'blocked_at' => now(), 'block_reason' => 'Foto tidak sesuai.']);
        sitemapProduct($this->store, ['slug' => 'produk-nonaktif', 'is_active' => false]);
        sitemapProduct($this->store, ['slug' => 'produk-terhapus'])->delete();
        ProductVariant::factory()->for(Product::factory()->for($this->store)->create(['slug' => 'produk-tanpa-varian']))->create(['is_active' => false]);
        sitemapProduct(Store::factory()->suspended()->create(['slug' => 'toko-ditangguhkan']), ['slug' => 'produk-toko-ditangguhkan']);
        Store::factory()->pending()->create(['slug' => 'toko-menunggu']);
        Store::factory()->rejected()->create(['slug' => 'toko-ditolak']);

        expect(array_keys(sitemapEntries($this->get('/sitemap.xml')->assertOk()->getContent())))->toEqualCanonicalizing([
            url('/'),
            url('/produk'),
            url('/produk/produk-tampil'),
            url('/toko/toko-bahari'),
        ]);
    });

    it('is valid with only the home page and catalog when nothing is listed', function () {
        $this->store->forceFill(['status' => StoreStatus::Pending])->save();

        expect(array_keys(sitemapEntries($this->get('/sitemap.xml')->assertOk()->getContent())))
            ->toEqualCanonicalizing([url('/'), url('/produk')]);
    });

    it('stays valid XML when a URL has an ampersand', function () {
        sitemapProduct($this->store, ['name' => 'Udang & Cumi', 'slug' => 'udang-&-cumi']);

        expect(sitemapEntries($this->get('/sitemap.xml')->assertOk()->getContent()))
            ->toHaveKey(url('/produk/udang-&-cumi'));
    });

    it('holds only URLs and dates, not product or store details', function () {
        $this->store->update(['name' => 'Toko Bahari Rahasia', 'phone' => '081234567890', 'bank_account_no' => '9876543210']);
        sitemapProduct($this->store, ['name' => 'Kepiting Bakau Super']);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertDontSee('Toko Bahari Rahasia')
            ->assertDontSee('081234567890')
            ->assertDontSee('9876543210')
            ->assertDontSee('Kepiting Bakau Super');
    });
});

describe('robots.txt', function () {
    it('keeps crawlers out of private areas and points to the sitemap', function () {
        $response = $this->get('/robots.txt')->assertOk();

        expect($response->headers->get('Content-Type'))->toStartWith('text/plain');

        $lines = array_map(trim(...), explode("\n", $response->getContent()));

        expect($lines)->toContain('User-agent: *')
            ->toContain('Disallow: /admin')
            ->toContain('Disallow: /seller')
            ->toContain('Disallow: /keranjang')
            ->toContain('Disallow: /checkout')
            ->toContain('Disallow: /pesanan')
            ->toContain('Disallow: /akun')
            ->toContain('Sitemap: '.url('/sitemap.xml'))
            ->not->toContain('Disallow: /');
    });

    it('is served by the route, not a static file', function () {
        // A file in public/ would be served by the web server before Laravel.
        expect(public_path('robots.txt'))->not->toBeFile();
    });
});

describe('catalog indexing', function () {
    it('asks search engines not to index a catalog search', function () {
        $this->get('/produk?q=kepiting')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', escape: false);
    });

    it('lets search engines index the plain catalog', function () {
        $this->get('/produk')->assertOk()->assertDontSee('noindex');
    });
});
