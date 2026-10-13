<?php

declare(strict_types=1);

use App\Enums\AttributeType;
use App\Livewire\Catalog;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
 * Audit across every buyer web page (#145):
 * VAL-6 user text is escaped, VAL-7 JSON-LD stays one valid block,
 * DATA-2 bank details never appear, DATA-4 coordinates are handled as decided.
 */

/**
 * Text a seller could type into any field, aimed at HTML, attributes, links,
 * and the JSON-LD script block.
 */
const HOSTILE_TEXT = 'Ikan <script>alert(1)</script> "><img src=x onerror=alert(2)> javascript:alert(3) </script>';

/**
 * Every public buyer page, with the shared fixture's slugs.
 *
 * @var array<string, string>
 */
const BUYER_PAGES = [
    'home' => '/',
    'catalog' => '/produk',
    'product' => '/produk/ikan-uji',
    'store' => '/toko/toko-uji',
    'sitemap' => '/sitemap.xml',
];

beforeEach(function () {
    $category = Category::factory()->create(['name' => 'Ikan Laut']);
    $note = CategoryAttribute::factory()->for($category)->create(['name' => 'Catatan', 'type' => AttributeType::Text, 'unit' => null]);

    $this->store = Store::factory()->active()->create([
        'slug' => 'toko-uji',
        'name' => 'Toko '.HOSTILE_TEXT,
        'city' => 'Kota '.HOSTILE_TEXT,
        'description' => 'Deskripsi '.HOSTILE_TEXT,
        'address' => 'Jl. '.HOSTILE_TEXT,
        'lat' => '-6.1065432',
        'lng' => '106.7765432',
        'bank_name' => 'Bank Rahasia',
        'bank_account_no' => '9876543210',
        'bank_account_name' => 'Pemilik Rahasia',
    ]);

    $product = Product::factory()->for($this->store)->for($category)->create([
        'slug' => 'ikan-uji',
        'name' => HOSTILE_TEXT,
        'description' => 'Deskripsi '.HOSTILE_TEXT,
        'seo_description' => 'SEO '.HOSTILE_TEXT,
        'storage_instructions' => 'Simpan '.HOSTILE_TEXT,
    ]);
    $product->attributeValues()->create(['category_attribute_id' => $note->id, 'value' => 'Atribut '.HOSTILE_TEXT]);
    ProductVariant::factory()->for($product)->create(['name' => 'Varian '.HOSTILE_TEXT]);
});

/**
 * The decoded contents of every JSON-LD block in the page.
 *
 * @return list<array<string, mixed>>
 */
function outputJsonLdBlocks(string $html): array
{
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

    return array_map(fn (string $json): array => json_decode($json, true, flags: JSON_THROW_ON_ERROR), $matches[1]);
}

describe('VAL-6', function () {
    it('escapes seller text on every page', function (string $url) {
        $html = $this->get($url)->assertOk()->getContent();

        expect($html)
            ->toContain(e('<script>alert(1)</script>'))
            ->not->toContain('<script>alert(1)</script>')
            ->not->toContain('<img src=x onerror=alert(2)>')
            ->not->toContain('"><img')
            ->not->toMatch('/(href|src|action)\s*=\s*["\']?\s*javascript:/i');
    })->with(array_values(array_diff_key(BUYER_PAGES, ['sitemap' => true])));

    it('keeps the sitemap valid XML with only URLs, no seller text', function () {
        $xml = $this->get(BUYER_PAGES['sitemap'])->assertOk()->getContent();

        expect(simplexml_load_string($xml))->not->toBeFalse()
            ->and($xml)->not->toContain('alert(');
    });
});

describe('VAL-7', function () {
    it('keeps one JSON-LD block that decodes back to the seller text', function (string $url, Closure $name) {
        $html = $this->get($url)->assertOk()->getContent();
        $blocks = outputJsonLdBlocks($html);

        expect($blocks)->toHaveCount(1)
            ->and(substr_count($html, 'application/ld+json'))->toBe(1)
            ->and($blocks[0]['name'])->toBe($name());
    })->with([
        'product' => [BUYER_PAGES['product'], fn () => HOSTILE_TEXT],
        'store' => [BUYER_PAGES['store'], fn () => 'Toko '.HOSTILE_TEXT],
    ]);
});

describe('DATA-2', function () {
    it('never shows bank details', function (string $url, string $secret) {
        expect($this->get($url)->assertOk()->getContent())->not->toContain($secret);
    })->with(array_values(BUYER_PAGES))->with([
        'bank name' => 'Bank Rahasia',
        'account number' => '9876543210',
        'account holder' => 'Pemilik Rahasia',
    ]);
});

describe('DATA-4', function () {
    it('shows the store location as stored, since it is the public pickup point', function () {
        $html = $this->get(BUYER_PAGES['store'])->assertOk()->getContent();

        expect(outputJsonLdBlocks($html)[0]['geo'])->toBe([
            '@type' => 'GeoCoordinates',
            'latitude' => -6.1065432,
            'longitude' => 106.7765432,
        ])->and($html)->toContain(e('query=-6.1065432,106.7765432'));
    });

    it('keeps the buyer location out of the database, session, and cookies', function () {
        $writes = [];
        DB::listen(function ($query) use (&$writes) {
            if (preg_match('/^\s*(insert|update|delete)/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });

        Livewire::test(Catalog::class)
            ->call('locate', '-6.17543', '106.82721')
            ->assertSet('lat', '-6.175')
            ->assertSet('lng', '106.827');

        $session = json_encode(session()->all(), JSON_THROW_ON_ERROR);

        expect($writes)->toBe([])
            ->and($session)->not->toContain('-6.17')
            ->and($session)->not->toContain('106.82')
            ->and(Cookie::getQueuedCookies())->toBe([]);
    });
});
