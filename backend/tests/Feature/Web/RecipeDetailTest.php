<?php

declare(strict_types=1);

use App\Enums\Unit;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * The static recipe detail page /resep/{slug} (#189), boards "Resep Detail
 * 3C" and "Resep Detail Ponsel 3C" on the design canvas (Eksperimen 1.5.0).
 *
 * Kepiting
 *   ├─ Bakau  (what Kepiting saus padang needs: 1 kg for 4 servings)
 *   └─ Soka   (a swap)
 */
beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 17)->setTime(9, 0));

    $this->kepiting = Category::factory()->create(['name' => 'Kepiting', 'slug' => 'kepiting']);
    $this->bakau = Category::factory()->childOf($this->kepiting)->create(['name' => 'Bakau', 'slug' => 'bakau']);
    $this->soka = Category::factory()->childOf($this->kepiting)->create(['name' => 'Soka', 'slug' => 'soka']);

    config(['recipes' => [
        detailRecipe(),
        detailRecipe(['slug' => 'sup-kepiting-jagung', 'name' => 'Sup kepiting jagung', 'group' => 'keluarga']),
        detailRecipe(['slug' => 'kepiting-kukus-jahe', 'name' => 'Kepiting kukus jahe', 'group' => 'cepat']),
        detailRecipe(['slug' => 'udang-goreng-mentega', 'name' => 'Udang goreng mentega', 'category' => 'udang', 'needs' => ['category' => 'vaname', 'kg' => '0.500', 'label' => '500 g udang vaname']]),
    ]]);
});

/**
 * A recipe entry for config('recipes'): Kepiting saus padang unless changed.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function detailRecipe(array $overrides = []): array
{
    return [
        'slug' => 'kepiting-saus-padang',
        'name' => 'Kepiting saus padang',
        'description' => 'Kepiting bakau dalam kuah merah yang pedas, manis, dan sedikit asam.',
        'photo' => 'images/resep/kepiting-saus-padang.jpg',
        'category' => 'kepiting',
        'minutes' => 45,
        'servings' => 4,
        'level' => 'Sedang',
        'group' => 'acara',
        'needs' => ['category' => 'bakau', 'kg' => '1.000', 'label' => '1 kg kepiting bakau'],
        'ingredients' => [
            ['amount' => '8', 'unit' => 'siung', 'name' => 'bawang merah'],
            ['amount' => '3', 'unit' => 'sdm', 'name' => 'saus tomat'],
            ['amount' => null, 'unit' => null, 'name' => 'garam dan gula secukupnya'],
        ],
        'steps' => ['Bersihkan kepiting.', 'Kukus kepiting.', 'Tumis bumbu.', 'Masukkan saus.', 'Masukkan kepiting.', 'Tuang telur.'],
        'swaps' => ['soka'],
        'featured' => false,
        ...$overrides,
    ];
}

/**
 * A product buyers can see in the given category, from the given store, with
 * one active variant: price per unit, stock, and when the stock was checked.
 */
function sellingProduct(Store $store, Category $category, int $price, Unit $unit = Unit::Kg, string $stock = '12.000', mixed $checkedAt = null): Product
{
    $product = Product::factory()->for($store)->for($category)->create();
    $variant = ProductVariant::factory()->for($product)->create(['price' => $price, 'unit' => $unit]);
    $variant->forceFill(['stock' => $stock, 'stock_confirmed_at' => $checkedAt ?? now()->setTime(6, 30)])->save();

    return $product;
}

/**
 * The HTML of the section labelled by the given heading id, or '' when it is not rendered.
 */
function detailSection(string $html, string $labelledBy): string
{
    if (! str_contains($html, 'aria-labelledby="'.$labelledBy.'"')) {
        return '';
    }

    return Str::before(Str::after($html, 'aria-labelledby="'.$labelledBy.'"'), '</section>');
}

/**
 * The decoded JSON-LD block of a page.
 *
 * @return array<string, mixed>
 */
function recipeJsonLd(string $html): array
{
    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

    expect($matches)->toHaveKey(1);

    return json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
}

describe('recipe sheet', function () {
    it('shows the title, description, time, servings, level, ingredients, and numbered steps', function () {
        $this->get('/resep/kepiting-saus-padang')
            ->assertOk()
            ->assertSeeInOrder(['Kepiting saus padang', 'Kepiting bakau dalam kuah merah', '45 menit', '4 orang', 'Sedang'])
            ->assertSeeInOrder(['bawang merah', '8 siung', 'saus tomat', '3 sdm', 'garam dan gula secukupnya'])
            ->assertSeeInOrder(['Bersihkan kepiting.', 'Kukus kepiting.', 'Tuang telur.'])
            ->assertSee('src="'.asset('images/resep/kepiting-saus-padang.jpg').'"', escape: false);
    });

    it('is named recipes.show', function () {
        expect(route('recipes.show', 'kepiting-saus-padang'))->toBe(url('/resep/kepiting-saus-padang'));
    });

    it('offers the scaled amounts for 1 to 12 servings to the servings stepper', function () {
        $html = $this->get('/resep/kepiting-saus-padang')->assertOk()->getContent();

        // 8 siung for 4 servings: 10 for 5, 24 for 12; 3 sdm: 1 for 1, 4 for 5.
        expect($html)->toContain('aria-label="Tambah porsi"')
            ->toContain('aria-label="Kurangi porsi"')
            ->toMatch('/&quot;5&quot;:&quot;10 siung&quot;|"5":"10 siung"/')
            ->toMatch('/&quot;12&quot;:&quot;24 siung&quot;|"12":"24 siung"/')
            ->toMatch('/&quot;1&quot;:&quot;1 sdm&quot;|"1":"1 sdm"/');
    });

    it('has print and share buttons', function () {
        $this->get('/resep/kepiting-saus-padang')
            ->assertOk()
            ->assertSee('Cetak')
            ->assertSee('Bagikan')
            ->assertSee('window.print()', escape: false);
    });

    it('links to the other recipes of the same root category, at most 3', function () {
        $section = detailSection($this->get('/resep/kepiting-saus-padang')->getContent(), 'resep-lain');

        expect($section)->toContain('Sup kepiting jagung')
            ->toContain('Kepiting kukus jahe')
            ->not->toContain('Udang goreng mentega')
            ->not->toContain('href="'.url('/resep/kepiting-saus-padang').'"');
    });
});

describe('where to buy', function () {
    it('lists the stores selling what the recipe needs, with the total for the servings', function () {
        $store = Store::factory()->active()->create(['name' => 'Lapak Bu Sari', 'city' => 'Jakarta Utara']);
        sellingProduct($store, $this->bakau, 180_000, stock: '20.000');

        $section = detailSection($this->get('/resep/kepiting-saus-padang')->getContent(), 'beli');

        expect($section)->toContain('Beli 1 kg kepiting bakau untuk resep ini')
            ->toContain('Lapak Bu Sari')
            ->toContain('Jakarta Utara')
            ->toContain('Sisa 20 kg')
            ->toContain('06.30')
            ->toContain('Rp180.000');
    });

    it('works out the total for half a kilo', function () {
        config(['recipes' => [detailRecipe(['needs' => ['category' => 'bakau', 'kg' => '0.500', 'label' => '500 g kepiting bakau']])]]);
        sellingProduct(Store::factory()->active()->create(), $this->bakau, 185_000);

        expect(detailSection($this->get('/resep/kepiting-saus-padang')->getContent(), 'beli'))
            ->toContain('Beli 500 g kepiting bakau untuk resep ini')
            ->toContain('Rp92.500')
            ->toContain('Rp185.000');
    });

    it('offers the totals for 1 to 12 servings to the servings stepper', function () {
        sellingProduct(Store::factory()->active()->create(), $this->bakau, 180_000);

        $html = $this->get('/resep/kepiting-saus-padang')->getContent();

        // 1 kg for 4 servings at Rp180.000/kg: Rp225.000 for 5 servings.
        expect($html)->toMatch('/&quot;5&quot;:&quot;Rp225\.000&quot;|"5":"Rp225\.000"/');
    });

    it('marks the cheapest real store, real stores first, at most 3', function () {
        $budi = Store::factory()->active()->create(['name' => 'UD Pak Budi']);
        $sari = Store::factory()->active()->create(['name' => 'Lapak Bu Sari']);
        $pantai = Store::factory()->active()->create(['name' => 'Pantai Indah']);
        $demo = Store::factory()->active()->demo()->create(['name' => 'Bahari Contoh']);
        $fourth = Store::factory()->active()->create(['name' => 'Toko Keempat']);
        sellingProduct($budi, $this->bakau, 185_000);
        sellingProduct($sari, $this->bakau, 180_000);
        sellingProduct($pantai, $this->bakau, 182_000);
        sellingProduct($demo, $this->bakau, 150_000);
        sellingProduct($fourth, $this->bakau, 199_000);

        $section = detailSection($this->get('/resep/kepiting-saus-padang')->getContent(), 'beli');

        expect($section)->toContain('Lapak Bu Sari')
            ->toContain('Pantai Indah')
            ->toContain('UD Pak Budi')
            ->not->toContain('Bahari Contoh')
            ->not->toContain('Toko Keempat')
            ->and(Str::position($section, 'Lapak Bu Sari'))->toBeLessThan(Str::position($section, 'Pantai Indah'))
            ->and(substr_count($section, 'Termurah'))->toBe(1)
            ->and(Str::position($section, 'Termurah'))->toBeLessThan(Str::position($section, 'Pantai Indah'));
    });

    it('lists a cheaper demo store last, labelled, without the cheapest badge', function () {
        sellingProduct(Store::factory()->active()->create(['name' => 'Lapak Bu Sari']), $this->bakau, 180_000);
        sellingProduct(Store::factory()->active()->demo()->create(['name' => 'Bahari Contoh']), $this->bakau, 150_000);

        $section = detailSection($this->get('/resep/kepiting-saus-padang')->getContent(), 'beli');

        expect(Str::position($section, 'Lapak Bu Sari'))->toBeLessThan(Str::position($section, 'Bahari Contoh'))
            ->and(Str::position($section, 'Termurah'))->toBeLessThan(Str::position($section, 'Bahari Contoh'))
            ->and($section)->toContain('Toko contoh');
    });

    it('only counts active variants sold by the kilo', function () {
        $perPiece = Store::factory()->active()->create(['name' => 'Lapak Per Ekor']);
        sellingProduct($perPiece, $this->bakau, 60_000, Unit::Piece);
        $store = Store::factory()->active()->create(['name' => 'Lapak Per Kilo']);
        $product = sellingProduct($store, $this->bakau, 190_000);
        ProductVariant::factory()->for($product)->create(['price' => 100_000, 'unit' => Unit::Kg, 'is_active' => false]);

        $section = detailSection($this->get('/resep/kepiting-saus-padang')->getContent(), 'beli');

        expect($section)->not->toContain('Lapak Per Ekor')
            ->toContain('Lapak Per Kilo')
            ->toContain('Rp190.000')
            ->not->toContain('Rp100.000');
    });

    it('leaves out stores and products buyers may not see', function (Closure $hide) {
        $store = Store::factory()->active()->create(['name' => 'Lapak Tersembunyi']);
        $hide(sellingProduct($store, $this->bakau, 150_000), $store);

        expect(detailSection($this->get('/resep/kepiting-saus-padang')->getContent(), 'beli'))->not->toContain('Lapak Tersembunyi');
    })->with([
        'suspended store' => [fn (Product $product, Store $store) => $store->forceFill(['status' => 'suspended'])->save()],
        'pending store' => [fn (Product $product, Store $store) => $store->forceFill(['status' => 'pending'])->save()],
        'blocked product' => [fn (Product $product) => $product->forceFill(['blocked_at' => now(), 'block_reason' => 'Foto tidak sesuai.'])->save()],
        'inactive product' => [fn (Product $product) => $product->forceFill(['is_active' => false])->save()],
        'deleted product' => [fn (Product $product) => $product->delete()],
    ]);

    it('says no store sells it yet and links to the catalog', function () {
        expect(detailSection($this->get('/resep/kepiting-saus-padang')->getContent(), 'beli'))
            ->toContain('Belum ada lapak yang menjual kepiting bakau')
            ->toContain('href="'.route('catalog', ['kategori' => 'bakau']).'"');
    });

    it('suggests the swaps with their starting price for the same amount', function () {
        sellingProduct(Store::factory()->active()->create(), $this->soka, 140_000);

        expect(detailSection($this->get('/resep/kepiting-saus-padang')->getContent(), 'beli'))
            ->toContain('Bisa juga diganti dengan')
            ->toContain('Soka')
            ->toContain('Rp140.000');
    });

    it('leaves the swaps out when nobody sells them', function () {
        $this->get('/resep/kepiting-saus-padang')->assertOk()->assertDontSee('Bisa juga diganti dengan');
    });
});

describe('SEO', function () {
    it('has the title, meta description, canonical, and Open Graph image', function () {
        $this->get('/resep/kepiting-saus-padang?utm=wa')
            ->assertOk()
            ->assertSee('<title>Kepiting saus padang | '.config('app.name').'</title>', escape: false)
            ->assertSee('<meta name="description" content="Kepiting bakau dalam kuah merah yang pedas, manis, dan sedikit asam.">', escape: false)
            ->assertSee('<link rel="canonical" href="'.url('/resep/kepiting-saus-padang').'">', escape: false)
            ->assertSee('<meta property="og:image" content="'.asset('images/resep/kepiting-saus-padang.jpg').'">', escape: false)
            ->assertDontSee('noindex');
    });

    it('describes the recipe in JSON-LD', function () {
        $jsonLd = recipeJsonLd($this->get('/resep/kepiting-saus-padang')->getContent());

        expect($jsonLd['@type'])->toBe('Recipe')
            ->and($jsonLd['name'])->toBe('Kepiting saus padang')
            ->and($jsonLd['totalTime'])->toBe('PT45M')
            ->and($jsonLd['recipeYield'])->toBe('4 porsi')
            ->and($jsonLd['image'])->toBe(asset('images/resep/kepiting-saus-padang.jpg'))
            ->and($jsonLd['recipeIngredient'])->toBe(['8 siung bawang merah', '3 sdm saus tomat', 'garam dan gula secukupnya'])
            ->and(collect($jsonLd['recipeInstructions'])->pluck('text')->all())->toHaveCount(6)
            ->and($jsonLd['recipeInstructions'][0])->toBe(['@type' => 'HowToStep', 'text' => 'Bersihkan kepiting.']);
    });

    it('lists every recipe in the sitemap', function () {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('<loc>'.url('/resep/kepiting-saus-padang').'</loc>', escape: false)
            ->assertSee('<loc>'.url('/resep/udang-goreng-mentega').'</loc>', escape: false);
    });

    it('is a 404 for an unknown recipe', function () {
        $this->get('/resep/tidak-ada')->assertNotFound();
    });
});

describe('security', function () {
    it('never shows bank details or coordinates of the stores', function () {
        $store = Store::factory()->active()->create([
            'lat' => '-6.1065432', 'lng' => '106.7765432',
            'bank_name' => 'Bank Rahasia', 'bank_account_no' => '9876543210', 'bank_account_name' => 'Pemilik Rahasia',
        ]);
        sellingProduct($store, $this->bakau, 180_000);

        $html = $this->get('/resep/kepiting-saus-padang')->assertOk()->getContent();

        expect($html)->not->toContain('9876543210')
            ->not->toContain('Pemilik Rahasia')
            ->not->toContain('-6.1065432')
            ->not->toContain('106.7765432');
    });

    it('escapes the store names and the recipe text', function () {
        config(['recipes' => [detailRecipe(['name' => 'Resep <b>tebal</b>', 'steps' => ['Langkah <script>alert(1)</script>']])]]);
        sellingProduct(Store::factory()->active()->create(['name' => 'Toko <script>alert(2)</script>']), $this->bakau, 180_000);

        $html = $this->get('/resep/kepiting-saus-padang')->assertOk()->getContent();

        expect($html)->not->toContain('<b>tebal</b>')
            ->not->toContain('<script>alert(1)</script>')
            ->not->toContain('<script>alert(2)</script>')
            ->and(recipeJsonLd($html)['name'])->toBe('Resep <b>tebal</b>');
    });
});

describe('performance', function () {
    it('runs the same number of queries for 2 and 20 stores', function () {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get('/resep/kepiting-saus-padang')->assertOk();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        foreach (range(1, 2) as $index) {
            sellingProduct(Store::factory()->active()->create(), $this->bakau, 180_000 + $index);
        }
        $few = $count();

        foreach (range(1, 18) as $index) {
            sellingProduct(Store::factory()->active()->create(), $this->bakau, 190_000 + $index);
        }

        expect($count())->toBe($few);
    });
});
