<?php

declare(strict_types=1);

use App\Enums\ProductCondition;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
 * Kepiting
 *   â”œâ”€ Bakau
 *   â””â”€ Soka
 * Cumi
 *   â””â”€ Cumi Segar
 */
beforeEach(function () {
    $this->kepiting = Category::factory()->create(['name' => 'Kepiting', 'slug' => 'kepiting']);
    $this->bakau = Category::factory()->childOf($this->kepiting)->create(['name' => 'Bakau', 'slug' => 'bakau']);
    $this->soka = Category::factory()->childOf($this->kepiting)->create(['name' => 'Soka', 'slug' => 'soka']);
    $this->cumi = Category::factory()->create(['name' => 'Cumi', 'slug' => 'cumi']);
    $this->cumiSegar = Category::factory()->childOf($this->cumi)->create(['name' => 'Cumi Segar', 'slug' => 'cumi-segar']);
});

/**
 * A product buyers can see, in Bakau, from a new active store, with one
 * active variant at the given price, unless overridden.
 *
 * @param  array<string, mixed>  $attributes
 */
function catalogProduct(array $attributes = [], int $price = 100_000, ?Store $store = null): Product
{
    $product = Product::factory()
        ->for($store ?? Store::factory()->active())
        ->create(['category_id' => test()->bakau->id, ...$attributes]);

    ProductVariant::factory()->for($product)->create(['price' => $price]);

    return $product;
}

/**
 * The number of queries the catalog page runs for the given URL.
 */
function catalogQueryCount(string $url = '/produk'): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    test()->get($url)->assertOk();

    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

/**
 * Three products in different categories, conditions, stores, and prices.
 */
function catalogFixture(): void
{
    $test = test();
    $test->bahari = Store::factory()->active()->create(['name' => 'Toko Bahari', 'slug' => 'toko-bahari']);

    catalogProduct(['name' => 'Kepiting Bakau Super', 'condition' => ProductCondition::Live, 'created_at' => now()->subDays(3)], 185_000, $test->bahari);
    catalogProduct(['name' => 'Kepiting Soka Beku', 'category_id' => $test->soka->id, 'condition' => ProductCondition::Frozen, 'created_at' => now()->subDays(2)], 95_000);
    catalogProduct(['name' => 'Cumi Segar Pagi', 'category_id' => $test->cumiSegar->id, 'condition' => ProductCondition::Fresh, 'created_at' => now()->subDay()], 60_000);
}

describe('page', function () {
    it('lists the products buyers can see', function () {
        catalogFixture();

        $this->get('/produk')
            ->assertOk()
            ->assertSeeLivewire('catalog')
            ->assertSee('Kepiting Bakau Super')
            ->assertSee('Kepiting Soka Beku')
            ->assertSee('Cumi Segar Pagi')
            ->assertSee('<title>Katalog | '.config('app.name').'</title>', escape: false)
            ->assertDontSee('noindex');
    });

    it('shows the products on white cards', function () {
        catalogProduct(['name' => 'Kepiting Bakau Super']);

        $this->get('/produk')->assertOk()->assertSee('data-tone="white"', escape: false);
    });

    it('uses the shared product card with the cheapest active price', function () {
        $product = catalogProduct(['name' => 'Kepiting Bakau Super', 'slug' => 'kepiting-bakau-super'], 185_000);
        ProductVariant::factory()->for($product)->create(['price' => 40_000, 'is_active' => false]);
        ProductVariant::factory()->for($product)->create(['price' => 150_000]);
        $image = ProductImage::factory()->for($product)->create();

        $this->get('/produk')
            ->assertOk()
            ->assertSee('href="'.url('/produk/kepiting-bakau-super').'"', escape: false)
            ->assertSee($image->thumbnailUrl(), escape: false)
            ->assertSee('Rp150.000')
            ->assertDontSee('Rp40.000');
    });

    it('hides products buyers may not see, even when they match the search', function (Closure $create) {
        $create();

        $this->get('/produk?q=tersembunyi')->assertOk()->assertDontSee('Produk Tersembunyi');
    })->with([
        'blocked' => [fn () => catalogProduct(['name' => 'Produk Tersembunyi', 'blocked_at' => now(), 'block_reason' => 'Foto tidak sesuai.'])],
        'inactive' => [fn () => catalogProduct(['name' => 'Produk Tersembunyi', 'is_active' => false])],
        'from a pending store' => [fn () => catalogProduct(['name' => 'Produk Tersembunyi'], store: Store::factory()->pending()->create())],
        'from a suspended store' => [fn () => catalogProduct(['name' => 'Produk Tersembunyi'], store: Store::factory()->suspended()->create())],
        'deleted' => [fn () => catalogProduct(['name' => 'Produk Tersembunyi'])->delete()],
        'without an active variant' => [fn () => ProductVariant::factory()
            ->for(Product::factory()->create(['name' => 'Produk Tersembunyi']))
            ->create(['is_active' => false])],
    ]);

    it('pages by 24', function () {
        foreach (range(1, 30) as $number) {
            catalogProduct(['name' => sprintf('Produk Nomor %02d.', $number), 'created_at' => now()->subMinutes($number)]);
        }

        $first = $this->get('/produk')->assertOk()->getContent();
        $second = $this->get('/produk?page=2')->assertOk()->getContent();

        $countOn = fn (string $html) => collect(range(1, 30))
            ->filter(fn (int $number) => str_contains($html, sprintf('Produk Nomor %02d.', $number)))
            ->count();

        expect($countOn($first))->toBe(24)
            ->and($countOn($second))->toBe(6)
            ->and($first)->toContain('Produk Nomor 01.')
            ->and($second)->toContain('Produk Nomor 30.');
    });

    it('shows a message when nothing is found', function () {
        $this->get('/produk')->assertOk()->assertSee('Produk tidak ditemukan');
    });
});

describe('filters', function () {
    it('finds products by name without regard to letter case', function () {
        catalogFixture();

        $this->get('/produk?q=BAKAU')
            ->assertOk()
            ->assertSee('Kepiting Bakau Super')
            ->assertDontSee('Kepiting Soka Beku')
            ->assertDontSee('Cumi Segar Pagi');
    });

    it('includes the subcategories of a category', function () {
        catalogFixture();

        $this->get('/produk?kategori=kepiting')
            ->assertOk()
            ->assertSee('Kepiting Bakau Super')
            ->assertSee('Kepiting Soka Beku')
            ->assertDontSee('Cumi Segar Pagi')
            ->assertSee('<title>Kepiting | '.config('app.name').'</title>', escape: false);
    });

    it('filters a leaf category', function () {
        catalogFixture();

        $this->get('/produk?kategori=soka')
            ->assertOk()
            ->assertSee('Kepiting Soka Beku')
            ->assertDontSee('Kepiting Bakau Super');
    });

    it('filters by condition, store, and price', function (string $query, array $shown, array $hidden) {
        catalogFixture();

        $response = $this->get('/produk?'.$query)->assertOk();

        foreach ($shown as $name) {
            $response->assertSee($name);
        }
        foreach ($hidden as $name) {
            $response->assertDontSee($name);
        }
    })->with([
        'condition' => ['kondisi=frozen', ['Kepiting Soka Beku'], ['Kepiting Bakau Super', 'Cumi Segar Pagi']],
        'store' => ['toko=toko-bahari', ['Kepiting Bakau Super'], ['Kepiting Soka Beku', 'Cumi Segar Pagi']],
        'minimum price' => ['harga_min=95000', ['Kepiting Bakau Super', 'Kepiting Soka Beku'], ['Cumi Segar Pagi']],
        'maximum price' => ['harga_maks=95000', ['Kepiting Soka Beku', 'Cumi Segar Pagi'], ['Kepiting Bakau Super']],
        'price range' => ['harga_min=90000&harga_maks=100000', ['Kepiting Soka Beku'], ['Kepiting Bakau Super', 'Cumi Segar Pagi']],
        'search and category together' => ['q=kepiting&kategori=soka', ['Kepiting Soka Beku'], ['Kepiting Bakau Super', 'Cumi Segar Pagi']],
    ]);

    it('filters by the cheapest active variant price', function () {
        $product = catalogProduct(['name' => 'Kepiting Bakau Super'], 185_000);
        ProductVariant::factory()->for($product)->create(['price' => 40_000, 'is_active' => false]);

        $this->get('/produk?harga_maks=50000')->assertOk()->assertDontSee('Kepiting Bakau Super');
        $this->get('/produk?harga_min=150000')->assertOk()->assertSee('Kepiting Bakau Super');
    });

    it('sorts newest first by default, or by price', function (string $query, array $order) {
        catalogFixture();

        $this->get('/produk'.$query)->assertOk()->assertSeeInOrder($order);
    })->with([
        'newest by default' => ['', ['Cumi Segar Pagi', 'Kepiting Soka Beku', 'Kepiting Bakau Super']],
        'newest' => ['?urut=terbaru', ['Cumi Segar Pagi', 'Kepiting Soka Beku', 'Kepiting Bakau Super']],
        'cheapest' => ['?urut=termurah', ['Cumi Segar Pagi', 'Kepiting Soka Beku', 'Kepiting Bakau Super']],
        'most expensive' => ['?urut=termahal', ['Kepiting Bakau Super', 'Kepiting Soka Beku', 'Cumi Segar Pagi']],
    ]);

    it('sorts by price before age', function () {
        catalogProduct(['name' => 'Murah Lama', 'created_at' => now()->subDays(5)], 50_000);
        catalogProduct(['name' => 'Mahal Baru', 'created_at' => now()], 300_000);

        $this->get('/produk?urut=termurah')->assertOk()->assertSeeInOrder(['Murah Lama', 'Mahal Baru']);
    });

    it('shows an empty list with a message for unknown values', function (string $query) {
        catalogFixture();
        Store::factory()->suspended()->create(['slug' => 'toko-ditangguhkan']);

        $this->get('/produk?'.$query)
            ->assertOk()
            ->assertSee('Produk tidak ditemukan')
            ->assertDontSee('Kepiting Bakau Super');
    })->with([
        'unknown category' => ['kategori=paus'],
        'unknown store' => ['toko=toko-hantu'],
        'suspended store' => ['toko=toko-ditangguhkan'],
        'unknown condition' => ['kondisi=busuk'],
        'minimum above maximum' => ['harga_min=200000&harga_maks=100000'],
    ]);

    it('keeps the filters in the URL', function () {
        Livewire::withQueryParams(['q' => 'bakau', 'kategori' => 'kepiting', 'kondisi' => 'live', 'toko' => 'toko-bahari', 'harga_min' => '1000', 'harga_maks' => '200000', 'urut' => 'termurah'])
            ->test('catalog')
            ->assertSet('q', 'bakau')
            ->assertSet('category', 'kepiting')
            ->assertSet('condition', 'live')
            ->assertSet('store', 'toko-bahari')
            ->assertSet('minPrice', '1000')
            ->assertSet('maxPrice', '200000')
            ->assertSet('sort', 'termurah');
    });

    it('updates the list when a filter changes and goes back to the first page', function () {
        catalogFixture();
        foreach (range(1, 25) as $number) {
            catalogProduct(['name' => "Pengisi {$number}", 'created_at' => now()->subYear()]);
        }

        Livewire::withQueryParams(['page' => 2])
            ->test('catalog')
            ->set('q', 'soka')
            ->assertSee('Kepiting Soka Beku')
            ->assertDontSee('Kepiting Bakau Super')
            ->assertSet('paginators.page', 1);
    });
});

describe('SEO', function () {
    it('asks search engines not to index a search or filter', function (string $query) {
        $this->get('/produk?'.$query)
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', escape: false)
            ->assertSee('<link rel="canonical" href="'.url('/produk').'">', escape: false);
    })->with([
        'search' => ['q=bakau'],
        'category' => ['kategori=kepiting'],
        'condition' => ['kondisi=live'],
        'store' => ['toko=toko-bahari'],
        'price' => ['harga_min=1000'],
        'sort' => ['urut=termurah'],
    ]);

    it('lets search engines index the plain catalog', function () {
        $this->get('/produk')
            ->assertOk()
            ->assertDontSee('noindex')
            ->assertSee('<link rel="canonical" href="'.url('/produk').'">', escape: false);
    });
});

describe('security', function () {
    it('escapes the search query', function () {
        $this->get('/produk?q='.urlencode('<script>alert(1)</script>'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', escape: false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', escape: false);
    });

    it('treats LIKE wildcards as plain text', function (string $query) {
        catalogProduct(['name' => 'Kepiting Bakau Super']);

        $this->get('/produk?q='.urlencode($query))
            ->assertOk()
            ->assertDontSee('Kepiting Bakau Super')
            ->assertSee('Produk tidak ditemukan');
    })->with(['%', '_', '\\', 'Kepiting%Super']);

    it('ignores invalid numbers and sort values', function (string $query) {
        catalogProduct(['name' => 'Kepiting Bakau Super']);

        $this->get('/produk?'.$query)->assertOk()->assertSee('Kepiting Bakau Super');
    })->with([
        'text as a price' => ['harga_min=abc'],
        'a negative price' => ['harga_min=-1'],
        'a decimal price' => ['harga_maks=99.5e9'],
        'an array as a price' => ['harga_min[]=1'],
        'an unknown sort' => ['urut=drop'],
        'an array as the search' => ['q[]=bakau'],
    ]);

    it('cuts a long search query to 100 characters', function () {
        Livewire::withQueryParams(['q' => str_repeat('a', 300)])
            ->test('catalog')
            ->assertOk()
            ->assertSet('q', str_repeat('a', 100))
            ->set('q', str_repeat('b', 300))
            ->assertSet('q', str_repeat('b', 100));
    });

    it('never shows bank details', function () {
        $store = Store::factory()->active()->create(['bank_name' => 'Bank Rahasia', 'bank_account_no' => '9876543210', 'bank_account_name' => 'Pemilik Rahasia']);
        catalogProduct(store: $store);

        $this->get('/produk')
            ->assertOk()
            ->assertDontSee('Bank Rahasia')
            ->assertDontSee('9876543210')
            ->assertDontSee('Pemilik Rahasia');
    });
});

describe('queries', function () {
    it('runs the same number of queries for 3 and 30 products', function () {
        $addProducts = function (int $count) {
            foreach (range(1, $count) as $i) {
                $product = catalogProduct();
                ProductVariant::factory()->for($product)->create();
                ProductImage::factory()->for($product)->count(2)->sequence(['sort_order' => 1], ['sort_order' => 2])->create();
            }
        };

        $addProducts(3);
        $few = catalogQueryCount('/produk?kategori=kepiting&urut=termurah');

        $addProducts(27);
        $many = catalogQueryCount('/produk?kategori=kepiting&urut=termurah');

        expect($many)->toBe($few);
    });
});

describe('nearest stores', function () {
    beforeEach(function () {
        $this->muaraAngke = Store::factory()->active()->create(['name' => 'Toko Muara Angke', 'lat' => '-6.1065', 'lng' => '106.7765']);
        $this->cirebon = Store::factory()->active()->create(['name' => 'Toko Cirebon', 'lat' => '-6.7063', 'lng' => '108.5570']);

        catalogProduct(['name' => 'Cumi Cirebon', 'created_at' => now()], store: $this->cirebon);
        catalogProduct(['name' => 'Kepiting Muara', 'created_at' => now()->subDay()], store: $this->muaraAngke);
    });

    it('sorts by distance from the buyer and shows the distance on the card', function () {
        Livewire::test('catalog')
            ->call('locate', -6.1754, 106.8272)
            ->assertSet('sort', 'terdekat')
            ->assertSeeInOrder(['Kepiting Muara', 'Cumi Cirebon'])
            // The buyer's point is rounded to -6.175, 106.827 before measuring.
            ->assertSee('9,4 km')
            ->assertSee('200,1 km');
    });

    it('rounds the location to 3 decimals', function () {
        Livewire::test('catalog')
            ->call('locate', -6.175412345, 106.827298765)
            ->assertSet('lat', '-6.175')
            ->assertSet('lng', '106.827');
    });

    it('ignores a location that is not a point on earth', function (mixed $lat, mixed $lng) {
        Livewire::test('catalog')
            ->call('locate', $lat, $lng)
            ->assertOk()
            ->assertSet('lat', null)
            ->assertSet('lng', null)
            ->assertSet('sort', 'terbaru')
            ->assertDontSee('9,4 km');
    })->with([
        'latitude above 90' => [91, 106.8],
        'latitude below -90' => [-90.5, 106.8],
        'longitude above 180' => [-6.1, 181],
        'longitude below -180' => [-6.1, -180.5],
        'text' => ['abc', 'def'],
        'nothing' => [null, null],
        'an array' => [[1], [2]],
    ]);

    it('filters by radius once the location is known', function () {
        Livewire::test('catalog')
            ->call('locate', -6.1754, 106.8272)
            ->set('radius', '10')
            ->assertSee('Kepiting Muara')
            ->assertDontSee('Cumi Cirebon')
            ->set('radius', '5')
            ->assertDontSee('Kepiting Muara')
            ->assertSee('Produk tidak ditemukan');
    });

    it('ignores a radius outside the offered values or without a location', function (bool $located, string $radius) {
        $component = Livewire::withQueryParams(['radius' => $radius])->test('catalog');

        if ($located) {
            $component->call('locate', -6.1754, 106.8272);
        }

        $component->assertSee('Kepiting Muara')->assertSee('Cumi Cirebon');
    })->with([
        'an unoffered radius' => [true, '7'],
        'text as a radius' => [true, 'jauh'],
        'a radius without a location' => [false, '10'],
    ]);

    it('falls back to the newest first when there is no location', function () {
        $this->get('/produk?urut=terdekat')
            ->assertOk()
            ->assertSeeInOrder(['Cumi Cirebon', 'Kepiting Muara'])
            ->assertDontSee('200,1 km');
    });

    it('never reads the location from the URL', function () {
        Livewire::withQueryParams(['lat' => '-6.1754', 'lng' => '106.8272', 'urut' => 'terdekat'])
            ->test('catalog')
            ->assertSet('lat', null)
            ->assertSet('lng', null)
            ->assertDontSee('9,4 km');

        $this->get('/produk?lat=-6.1754&lng=106.8272&urut=terdekat')
            ->assertOk()
            ->assertDontSee('9,4 km');
    });

    it('goes back to the first page when the location is set', function () {
        Livewire::withQueryParams(['page' => 2])
            ->test('catalog')
            ->call('locate', -6.1754, 106.8272)
            ->assertSet('paginators.page', 1);
    });

    it('does not show store coordinates', function () {
        Livewire::test('catalog')
            ->call('locate', -6.1754, 106.8272)
            ->assertDontSee('-6.1065')
            ->assertDontSee('108.557');
    });
});

describe('active filters', function () {
    it('shows each valid filter as a chip that removes it', function () {
        catalogFixture();

        Livewire::withQueryParams(['q' => 'bakau', 'kategori' => 'kepiting', 'kondisi' => 'live', 'toko' => 'toko-bahari', 'harga_min' => '1000', 'harga_maks' => '200000'])
            ->test('catalog')
            ->assertSeeHtml('aria-label="Hapus filter “bakau”"')
            ->assertSeeHtml('aria-label="Hapus filter Kepiting"')
            ->assertSeeHtml('aria-label="Hapus filter Hidup"')
            ->assertSeeHtml('aria-label="Hapus filter Toko Bahari"')
            ->assertSeeHtml('aria-label="Hapus filter Mulai Rp1.000"')
            ->assertSeeHtml('aria-label="Hapus filter Sampai Rp200.000"')
            ->assertSeeHtml('wire:click="removeFilter(&#039;category&#039;)"');
    });

    it('removes only the filter whose chip is pressed', function () {
        catalogFixture();

        Livewire::withQueryParams(['kategori' => 'kepiting', 'kondisi' => 'live'])
            ->test('catalog')
            ->call('removeFilter', 'category')
            ->assertSet('category', '')
            ->assertSet('condition', 'live')
            ->assertDontSeeHtml('aria-label="Hapus filter Kepiting"')
            ->assertSeeHtml('aria-label="Hapus filter Hidup"')
            ->assertSee('Kepiting Bakau Super')
            ->assertDontSee('Cumi Segar Pagi');
    });

    it('goes back to the first page when a filter is removed', function () {
        foreach (range(1, 30) as $number) {
            catalogProduct(['name' => "Pengisi {$number}"]);
        }

        Livewire::withQueryParams(['page' => 2, 'q' => 'Pengisi'])
            ->test('catalog')
            ->call('removeFilter', 'q')
            ->assertSet('paginators.page', 1);
    });

    it('shows the radius as a chip once the location is known', function () {
        Livewire::test('catalog')
            ->call('locate', -6.1754, 106.8272)
            ->set('radius', '10')
            ->assertSeeHtml('aria-label="Hapus filter Dalam 10 km"')
            ->call('removeFilter', 'radius')
            ->assertSet('radius', '')
            ->assertSet('lat', '-6.175');
    });

    it('shows no chip for a value it does not know', function (string $query) {
        catalogFixture();

        $this->get('/produk?'.$query)->assertOk()->assertDontSee('aria-label="Filter aktif"', escape: false);
    })->with([
        'unknown condition' => ['kondisi=xyz'],
        'unknown category' => ['kategori=paus'],
        'unknown store' => ['toko=toko-hantu'],
        'price that is not a number' => ['harga_min=abc'],
        'radius without a location' => ['radius=10'],
        'only a sort' => ['urut=termurah'],
    ]);

    it('ignores a remove request for anything that is not a filter', function (string $name) {
        Livewire::test('catalog')
            ->call('locate', -6.1754, 106.8272)
            ->call('removeFilter', $name)
            ->assertSet('lat', '-6.175')
            ->assertSet('lng', '106.827')
            ->assertSet('sort', 'terdekat');
    })->with(['lat', 'lng', 'sort', 'paginators', 'unknown']);

    it('clears every filter and the sort but keeps the location', function () {
        catalogFixture();

        Livewire::withQueryParams(['q' => 'bakau', 'kategori' => 'kepiting', 'kondisi' => 'live', 'toko' => 'toko-bahari', 'harga_min' => '1000', 'harga_maks' => '200000', 'urut' => 'termurah'])
            ->test('catalog')
            ->call('locate', -6.1754, 106.8272)
            ->set('radius', '50')
            ->call('clearFilters')
            ->assertSet('q', '')
            ->assertSet('category', '')
            ->assertSet('condition', '')
            ->assertSet('store', '')
            ->assertSet('minPrice', '')
            ->assertSet('maxPrice', '')
            ->assertSet('radius', '')
            ->assertSet('sort', 'terbaru')
            ->assertSet('lat', '-6.175')
            ->assertSee('Cumi Segar Pagi')
            ->assertDontSeeHtml('aria-label="Filter aktif"');
    });

    it('offers to clear all filters only when a filter is set', function () {
        catalogFixture();

        $this->get('/produk?kondisi=live')->assertOk()->assertSee('wire:click="clearFilters"', escape: false);
        $this->get('/produk')->assertOk()->assertDontSee('wire:click="clearFilters"', escape: false);
    });

    it('escapes the search query in its chip', function () {
        $this->get('/produk?q='.urlencode('<script>alert(1)</script>'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', escape: false)
            ->assertSee('Hapus filter “&lt;script&gt;alert(1)&lt;/script&gt;”', escape: false);
    });
});

describe('filter panel and results', function () {
    it('has a button that opens the filter panel on a phone', function () {
        $this->get('/produk')
            ->assertOk()
            ->assertSee('aria-controls="panel-filter"', escape: false)
            ->assertSee('aria-expanded="false"', escape: false)
            ->assertSee('id="panel-filter"', escape: false);
    });

    it('counts the active filters on the panel button, leaving out the sort', function () {
        catalogFixture();

        $html = $this->get('/produk?kategori=kepiting&kondisi=live&urut=termurah')->assertOk()->getContent();

        expect(Str::between($html, 'aria-controls="panel-filter"', '</button>'))
            ->toContain('Filter')
            ->toMatch('/<span[^>]*>\s*2\s*<\/span>/');
    });

    it('shows the total number of results, not only this page', function () {
        foreach (range(1, 30) as $number) {
            catalogProduct(['name' => "Pengisi {$number}"]);
        }

        $this->get('/produk')->assertOk()->assertSee('30 produk');
    });

    it('announces loading while the list updates', function () {
        $this->get('/produk')
            ->assertOk()
            ->assertSee('wire:loading', escape: false)
            ->assertSee('Memuat produk');
    });

    it('shows the empty state with a way to clear the filters', function () {
        catalogFixture();

        $this->get('/produk?kondisi=marinated')
            ->assertOk()
            ->assertSee('Produk tidak ditemukan')
            ->assertSee('Hapus semua filter');
    });
});

describe('pagination', function () {
    it('marks the current page and links to the others by number', function () {
        foreach (range(1, 50) as $number) {
            catalogProduct(['name' => "Pengisi {$number}"]);
        }

        expect($this->get('/produk?page=2')->assertOk()->getContent())
            ->toMatch('/<span[^>]*aria-current="page"[^>]*>\s*2\s*<\/span>/')
            ->toContain('aria-label="Halaman 1"')
            ->toContain('aria-label="Halaman 3"')
            ->toContain('Sebelumnya')
            ->toContain('Berikutnya');
    });

    it('shows no pagination for a single page', function () {
        catalogProduct();

        $this->get('/produk')->assertOk()->assertDontSee('aria-label="Halaman', escape: false);
    });
});

/**
 * The markup of the category card for the given root category slug.
 */
function categoryCard(string $html, string $slug): string
{
    expect($html)->toContain('data-category-card="'.$slug.'"');

    return Str::before(Str::after($html, 'data-category-card="'.$slug.'"'), '</button>');
}

describe('category cards', function () {
    it('shows a card for each root category with its number of visible products', function () {
        catalogProduct(['name' => 'Kepiting Satu']);
        catalogProduct(['name' => 'Kepiting Dua']);
        catalogProduct(['name' => 'Kepiting Tiga', 'category_id' => $this->soka->id]);
        catalogProduct(['name' => 'Cumi Satu', 'category_id' => $this->cumiSegar->id]);
        catalogProduct(['name' => 'Cumi Dua', 'category_id' => $this->cumiSegar->id]);

        $html = $this->get('/produk')->assertOk()->getContent();

        expect(categoryCard($html, 'kepiting'))->toContain('Kepiting')->toContain('3 produk')
            ->and(categoryCard($html, 'cumi'))->toContain('Cumi')->toContain('2 produk')
            ->and($html)->not->toContain('data-category-card="bakau"');
    });

    it('uses the photo of the newest visible product in the category, subcategories included', function () {
        $older = ProductImage::factory()->for(catalogProduct(['created_at' => now()->subDays(2)]))->create();
        $newer = ProductImage::factory()->for(catalogProduct(['category_id' => $this->soka->id, 'created_at' => now()->subDay()]))->create();
        $cumi = ProductImage::factory()->for(catalogProduct(['category_id' => $this->cumiSegar->id, 'created_at' => now()]))->create();

        $card = categoryCard($this->get('/produk')->assertOk()->getContent(), 'kepiting');

        expect($card)->toContain($newer->thumbnailUrl())
            ->not->toContain($older->thumbnailUrl())
            ->not->toContain($cumi->thumbnailUrl());
    });

    it('skips the newest product when it has no photo', function () {
        $photo = ProductImage::factory()->for(catalogProduct(['created_at' => now()->subDays(2)]))->create();
        catalogProduct(['created_at' => now()]);

        expect(categoryCard($this->get('/produk')->assertOk()->getContent(), 'kepiting'))
            ->toContain($photo->thumbnailUrl())
            ->not->toContain('Belum ada foto');
    });

    it('shows the empty photo when no product in the category has one', function () {
        catalogProduct();

        expect(categoryCard($this->get('/produk')->assertOk()->getContent(), 'kepiting'))->toContain('Belum ada foto');
    });

    it('does not render a root category without visible products', function () {
        catalogProduct();

        $this->get('/produk')
            ->assertOk()
            ->assertSee('data-category-card="kepiting"', escape: false)
            ->assertDontSee('data-category-card="cumi"', escape: false);
    });

    it('neither counts nor uses the photo of products buyers may not see', function (Closure $create) {
        $visible = ProductImage::factory()->for(catalogProduct(['created_at' => now()->subDays(2)]))->create();
        $hidden = ProductImage::factory()->for($create())->create();

        $card = categoryCard($this->get('/produk')->assertOk()->getContent(), 'kepiting');

        expect($card)->toContain('1 produk')
            ->toContain($visible->thumbnailUrl())
            ->not->toContain($hidden->thumbnailUrl());
    })->with([
        'blocked' => [fn () => catalogProduct(['blocked_at' => now(), 'block_reason' => 'Foto tidak sesuai.'])],
        'inactive' => [fn () => catalogProduct(['is_active' => false])],
        'from a pending store' => [fn () => catalogProduct(store: Store::factory()->pending()->create())],
        'from a suspended store' => [fn () => catalogProduct(store: Store::factory()->suspended()->create())],
    ]);

    it('sets the category filter when a card is clicked and removes it on a second click', function () {
        catalogProduct();

        Livewire::test('catalog')
            ->call('toggleCategory', 'kepiting')
            ->assertSet('category', 'kepiting')
            ->call('toggleCategory', 'kepiting')
            ->assertSet('category', '');
    });

    it('ignores a click on a category that is not a root category', function (string $slug) {
        catalogProduct();

        Livewire::test('catalog')->call('toggleCategory', $slug)->assertSet('category', '');
    })->with([
        'unknown' => ['tidak-ada'],
        'a subcategory' => ['bakau'],
    ]);

    it('marks the card of the chosen category as pressed', function () {
        catalogProduct();
        catalogProduct(['category_id' => $this->cumiSegar->id]);

        $html = $this->get('/produk?kategori=kepiting')->assertOk()->getContent();

        expect(categoryCard($html, 'kepiting'))->toContain('aria-pressed="true"')
            ->and(categoryCard($html, 'cumi'))->toContain('aria-pressed="false"');
    });

    it('calls the toggle from each card', function () {
        catalogProduct();

        expect(categoryCard($this->get('/produk')->assertOk()->getContent(), 'kepiting'))
            ->toContain('wire:click="toggleCategory(&#039;kepiting&#039;)"');
    });

    it('counts only the products matching the other filters', function () {
        catalogProduct(['condition' => ProductCondition::Frozen]);
        catalogProduct(['condition' => ProductCondition::Live]);
        catalogProduct(['condition' => ProductCondition::Frozen, 'category_id' => $this->cumiSegar->id]);
        catalogProduct(['condition' => ProductCondition::Frozen, 'category_id' => $this->cumiSegar->id]);
        catalogProduct(['condition' => ProductCondition::Fresh, 'category_id' => $this->cumiSegar->id]);

        $html = $this->get('/produk?kondisi=frozen')->assertOk()->getContent();

        expect(categoryCard($html, 'kepiting'))->toContain('1 produk')
            ->and(categoryCard($html, 'cumi'))->toContain('2 produk');
    });

    it('does not narrow the counts by the chosen category itself', function () {
        catalogProduct();
        catalogProduct(['category_id' => $this->cumiSegar->id]);
        catalogProduct(['category_id' => $this->cumiSegar->id]);

        expect(categoryCard($this->get('/produk?kategori=kepiting')->assertOk()->getContent(), 'cumi'))->toContain('2 produk');
    });

    it('escapes the category name', function () {
        $root = Category::factory()->create(['name' => '<script>alert(1)</script>', 'slug' => 'berbahaya']);
        catalogProduct(['category_id' => $root->id]);

        expect(categoryCard($this->get('/produk')->assertOk()->getContent(), 'berbahaya'))
            ->not->toContain('<script>alert(1)</script>')
            ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
    });

    it('runs the same number of queries for 2 and 8 root categories', function () {
        $addCategories = function (int $count) {
            foreach (range(1, $count) as $i) {
                $root = Category::factory()->create();
                $child = Category::factory()->childOf($root)->create();
                ProductImage::factory()->for(catalogProduct(['category_id' => $child->id]))->create();
            }
        };

        catalogProduct();
        catalogProduct(['category_id' => $this->cumiSegar->id]);
        $few = catalogQueryCount('/produk?kondisi=live');

        $addCategories(6);
        $many = catalogQueryCount('/produk?kondisi=live');

        expect($many)->toBe($few);
    });
});
