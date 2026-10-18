<?php

declare(strict_types=1);

use App\Enums\ProductCondition;
use App\Enums\SalesMode;
use App\Enums\Unit;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

/*
 * The all pre-orders page /pre-order (#198), after the "Pre Order Web 2" and
 * "Pre Order Ponsel 2" boards: pre-order products with the earliest day they
 * can be delivered and their daily capacity, filtered by how soon they ship,
 * by root category, and by distance once the buyer shares a location; under
 * them, ready stock products checked today. The remaining quota of a day
 * comes with orders in Tahap 5.
 *
 * Friday 16 October 2026 in WIB is the frozen "today" unless a test says
 * otherwise, so Saturday 17 is tomorrow.
 */

beforeEach(function () {
    config(['app.name' => 'Seafood Marketplace']);
    poFreezeAt('10:00');
});

/**
 * Freeze the time on Friday 16 October 2026 at the given WIB time.
 */
function poFreezeAt(string $time): void
{
    test()->travelTo(Carbon::parse('2026-10-16 '.$time, 'Asia/Jakarta'));
}

/**
 * A pre-order product buyers can see, with one active variant.
 *
 * @param  array<string, mixed>  $attributes
 * @param  array<string, mixed>  $variant
 */
function poProduct(string $name, array $attributes = [], ?Store $store = null, array $variant = []): Product
{
    $product = Product::factory()
        ->preOrder()
        ->for($store ?? Store::factory()->active()->create())
        ->create(['name' => $name, 'lead_time_days' => 1, 'cutoff_time' => '15:00', ...$attributes]);
    ProductVariant::factory()->for($product)->create($variant);

    return $product;
}

/**
 * A ready stock product buyers can see, its stock checked at the given time.
 *
 * @param  array<string, mixed>  $variant
 */
function poReadyProduct(string $name, ?Carbon $checkedAt, ?Store $store = null, array $variant = [], array $attributes = []): Product
{
    $product = Product::factory()
        ->for($store ?? Store::factory()->active()->create())
        ->create(['name' => $name, 'sales_mode' => SalesMode::ReadyStock, ...$attributes]);
    ProductVariant::factory()->for($product)->create(['stock_confirmed_at' => $checkedAt, ...$variant]);

    return $product;
}

/**
 * The HTML of the page at the given query.
 *
 * @param  array<string, string>  $query
 */
function poPage(array $query = []): string
{
    return test()->get(route('pre-orders', $query))->assertOk()->getContent();
}

/**
 * The part of the HTML inside the section labelled by the given id, or empty
 * when there is no such section.
 */
function poSection(string $html, string $labelledBy): string
{
    if (! str_contains($html, 'aria-labelledby="'.$labelledBy.'"')) {
        return '';
    }

    return Str::before(Str::after($html, 'aria-labelledby="'.$labelledBy.'"'), '</section>');
}

/**
 * The pre-order list of the page.
 *
 * @param  array<string, string>  $query
 */
function poList(array $query = []): string
{
    return poSection(poPage($query), 'pre-order-judul');
}

/**
 * The names on the pre-order cards, in order.
 *
 * @return list<string>
 */
function poCardNames(string $html): array
{
    preg_match_all('#<li[^>]*data-pre-order-card="([^"]*)"#', $html, $matches);

    return array_map(fn (string $name) => html_entity_decode($name, ENT_QUOTES), $matches[1]);
}

/**
 * The count shown on a filter option.
 */
function poOptionCount(string $html, string $attribute, string $value): ?int
{
    return preg_match('#'.$attribute.'="'.preg_quote($value, '#').'"[^>]*data-count="(\d+)"#', $html, $match) === 1 ? (int) $match[1] : null;
}

describe('page', function () {
    it('has its title, meta description, and canonical, and is indexed', function () {
        $this->get('/pre-order')
            ->assertOk()
            ->assertSee('<title>Semua pre order | Seafood Marketplace</title>', escape: false)
            ->assertSee('<meta name="description" content="', escape: false)
            ->assertSee('<link rel="canonical" href="'.url('/pre-order').'">', escape: false)
            ->assertDontSee('<meta name="robots"', escape: false)
            ->assertSeeInOrder(['<h1', 'Semua pre order', '</h1>'], escape: false);
    });

    it('keeps a filtered page out of search results', function (array $query) {
        Category::factory()->create(['slug' => 'kepiting']);

        expect(poPage($query))->toContain('<meta name="robots" content="noindex, follow">');
    })->with([
        'by deadline' => [['paling_lambat' => 'besok']],
        'by category' => [['kategori' => 'kepiting']],
    ]);

    it('is linked from the header and from the home page pre-order band', function () {
        poProduct('Kepiting bakau');

        expect($this->get('/produk')->assertOk()->getContent())
            ->toMatch('#<nav aria-label="Utama".*?<a href="'.preg_quote(url('/pre-order'), '#').'"[^>]*>Pre order</a>#s');

        $home = $this->get('/')->assertOk()->getContent();
        expect(Str::before(Str::after($home, 'aria-labelledby="preorder-judul"'), '</section>'))
            ->toMatch('#<a href="'.preg_quote(url('/pre-order'), '#').'"[^>]*>Semua pre order</a>#');
    });

    it('is listed in the sitemap', function () {
        expect($this->get('/sitemap.xml')->assertOk()->getContent())->toContain('<loc>'.url('/pre-order').'</loc>');
    });
});

describe('pre-order list', function () {
    it('lists pre-order products only, with the count', function () {
        poProduct('Kepiting bakau');
        poProduct('Udang vaname');
        poReadyProduct('Kerang hijau siap', now());

        $list = poList();

        expect(poCardNames($list))->toEqualCanonicalizing(['Kepiting bakau', 'Udang vaname'])
            ->and(poPage())->toContain('2 produk pre order');
    });

    it('leaves out pre-orders buyers cannot see', function (Closure $hide) {
        $product = poProduct('Kepiting tersembunyi');
        $hide($product);

        expect(poCardNames(poList()))->not->toContain('Kepiting tersembunyi');
    })->with([
        'suspended store' => [fn (Product $product) => $product->store->forceFill(['status' => 'suspended'])->save()],
        'blocked' => [fn (Product $product) => $product->forceFill(['blocked_at' => now(), 'block_reason' => 'Foto tidak sesuai.'])->save()],
        'turned off' => [fn (Product $product) => $product->forceFill(['is_active' => false])->save()],
        'no active variant' => [fn (Product $product) => $product->variants()->update(['is_active' => false])],
    ]);

    it('labels each card with the earliest delivery day', function (string $time, int $leadDays, string $label) {
        poFreezeAt($time);
        poProduct('Kepiting bakau', ['lead_time_days' => $leadDays]);

        expect(poList())->toContain('Paling cepat '.$label);
    })->with([
        'before the cutoff, one day' => ['10:00', 1, 'Sab 17'],
        'after the cutoff, one day' => ['16:00', 1, 'Min 18'],
        'at the cutoff, one day' => ['15:00', 1, 'Min 18'],
        'before the cutoff, two days' => ['10:00', 2, 'Min 18'],
    ]);

    it('shows the store, the name, the starting price, and the daily capacity', function () {
        $store = Store::factory()->active()->create(['name' => 'UD Kepiting Pak Budi']);
        poProduct('Kepiting bakau', ['daily_quota' => '20.000'], $store, ['price' => 185000, 'unit' => Unit::Kg]);

        expect(poList())->toMatch('#data-pre-order-card="Kepiting bakau".*?UD Kepiting Pak Budi.*?Kepiting bakau.*?Rp185\.000.*?/ Kg.*?Kuota 20 kg per hari#s');
    });

    it('writes a fractional capacity with a comma', function () {
        poProduct('Kepiting bakau', ['daily_quota' => '2.500']);

        expect(poList())->toContain('Kuota 2,5 kg per hari');
    });

    it('leaves out the capacity line without a daily quota', function () {
        poProduct('Kepiting bakau', ['daily_quota' => null]);

        expect(poList())->not->toContain('Kuota');
    });

    it('labels products of a demo store', function () {
        poProduct('Udang contoh', [], Store::factory()->active()->demo()->create());

        expect(poList())->toMatch('#data-pre-order-card="Udang contoh".*?Toko contoh#s');
    });

    it('puts real stores first, then the earliest delivery day, then the newest', function () {
        $demo = Store::factory()->active()->demo()->create();
        poProduct('Contoh besok', ['created_at' => now()->subDay()], $demo);
        poProduct('Lama besok', ['created_at' => now()->subDays(3)]);
        poProduct('Baru lusa', ['lead_time_days' => 2, 'created_at' => now()->subHour()]);
        poProduct('Baru besok', ['created_at' => now()->subDays(2)]);

        expect(poCardNames(poList()))->toBe(['Baru besok', 'Lama besok', 'Baru lusa', 'Contoh besok']);
    });

    it('tells the latest time to order for delivery tomorrow', function () {
        poProduct('Kepiting bakau', ['cutoff_time' => '15:00']);
        poProduct('Udang vaname', ['cutoff_time' => '12:30']);

        expect(poPage())->toContain('Pesan sebelum 15.00 untuk dikirim besok');
    });

    it('leaves out the order-by line when nothing can be delivered tomorrow', function () {
        poFreezeAt('16:00');
        poProduct('Kepiting bakau');

        expect(poPage())->not->toContain('untuk dikirim besok');
    });
});

describe('deadline filter', function () {
    beforeEach(function () {
        poProduct('Besok', ['lead_time_days' => 1]);
        poProduct('Lusa', ['lead_time_days' => 2]);
        poProduct('Tiga hari', ['lead_time_days' => 3]);
        poProduct('Enam hari', ['lead_time_days' => 6]);
        poProduct('Sepuluh hari', ['lead_time_days' => 10]);
    });

    it('keeps the products deliverable by the chosen day', function (string $value, array $names) {
        expect(poCardNames(poList(['paling_lambat' => $value])))->toEqualCanonicalizing($names);
    })->with([
        'tomorrow' => ['besok', ['Besok']],
        'the day after' => ['lusa', ['Besok', 'Lusa']],
        'three days' => ['3-hari', ['Besok', 'Lusa', 'Tiga hari']],
        'this week' => ['minggu-ini', ['Besok', 'Lusa', 'Tiga hari', 'Enam hari']],
    ]);

    it('names the days and counts the products of each option', function () {
        $html = poPage();

        expect($html)
            ->toMatch('#data-deadline-option="besok"[^>]*>\s*Besok, Sabtu#')
            ->toMatch('#data-deadline-option="lusa"[^>]*>\s*Minggu#')
            ->toMatch('#data-deadline-option="3-hari"[^>]*>\s*Senin#')
            ->toMatch('#data-deadline-option="minggu-ini"[^>]*>\s*Minggu ini#')
            ->and(poOptionCount($html, 'data-deadline-option', 'besok'))->toBe(1)
            ->and(poOptionCount($html, 'data-deadline-option', 'lusa'))->toBe(2)
            ->and(poOptionCount($html, 'data-deadline-option', '3-hari'))->toBe(3)
            ->and(poOptionCount($html, 'data-deadline-option', 'minggu-ini'))->toBe(4);
    });

    it('moves a product to the next day after the cutoff', function () {
        poFreezeAt('16:00');

        expect(poCardNames(poList(['paling_lambat' => 'besok'])))->toBe([])
            ->and(poCardNames(poList(['paling_lambat' => 'lusa'])))->toBe(['Besok']);
    });

    it('marks the chosen option and switches in place', function () {
        $component = Livewire::test('pre-orders')
            ->call('chooseDeadline', 'lusa')
            ->assertSet('deadline', 'lusa');

        expect($component->html())
            ->toMatch('#data-deadline-option="lusa"[^>]*aria-pressed="true"#')
            ->toMatch('#data-deadline-option="besok"[^>]*aria-pressed="false"#');

        // Choosing it again shows every pre-order.
        $component->call('chooseDeadline', 'lusa')->assertSet('deadline', '');
    });

    it('keeps the choice in the URL', function () {
        Livewire::withQueryParams(['paling_lambat' => '3-hari'])
            ->test('pre-orders')
            ->assertSet('deadline', '3-hari');
    });

    it('ignores a value it does not offer', function (string $value) {
        expect(poCardNames(poList(['paling_lambat' => $value])))->toHaveCount(5);

        Livewire::test('pre-orders')->call('chooseDeadline', $value)->assertSet('deadline', '');
    })->with([
        'yesterday' => ['kemarin'],
        'a number' => ['2'],
        'markup' => ['<script>'],
    ]);
});

describe('category filter', function () {
    beforeEach(function () {
        $this->kepiting = Category::factory()->create(['name' => 'Kepiting', 'slug' => 'kepiting', 'sort_order' => 1]);
        $this->bakau = Category::factory()->childOf($this->kepiting)->create(['name' => 'Bakau', 'slug' => 'bakau']);
        $this->udang = Category::factory()->create(['name' => 'Udang', 'slug' => 'udang', 'sort_order' => 2]);
        Category::factory()->create(['name' => 'Tiram', 'slug' => 'tiram', 'sort_order' => 3]);

        poProduct('Kepiting bakau', ['category_id' => $this->bakau->id]);
        poProduct('Kepiting soka', ['category_id' => $this->kepiting->id]);
        poProduct('Udang vaname', ['category_id' => $this->udang->id, 'lead_time_days' => 3]);
    });

    it('offers the root categories with pre-orders, with their counts', function () {
        $html = poPage();

        expect(poOptionCount($html, 'data-category-option', ''))->toBe(3)
            ->and(poOptionCount($html, 'data-category-option', 'kepiting'))->toBe(2)
            ->and(poOptionCount($html, 'data-category-option', 'udang'))->toBe(1)
            ->and($html)->not->toContain('data-category-option="tiram"')
            ->and($html)->not->toContain('data-category-option="bakau"');
    });

    it('keeps the products of the category and its subcategories', function () {
        expect(poCardNames(poList(['kategori' => 'kepiting'])))->toEqualCanonicalizing(['Kepiting bakau', 'Kepiting soka']);
    });

    it('counts each filter with the other one applied', function () {
        $html = poPage(['paling_lambat' => 'besok', 'kategori' => 'udang']);

        expect(poOptionCount($html, 'data-category-option', 'kepiting'))->toBe(2)
            ->and(poOptionCount($html, 'data-category-option', 'udang'))->toBe(0)
            ->and(poOptionCount($html, 'data-deadline-option', 'besok'))->toBe(0)
            ->and(poOptionCount($html, 'data-deadline-option', '3-hari'))->toBe(1);
    });

    it('chooses a category in place and keeps it in the URL', function () {
        Livewire::test('pre-orders')
            ->call('chooseCategory', 'udang')
            ->assertSet('category', 'udang')
            ->assertSee('Udang vaname')
            ->assertDontSee('Kepiting soka');

        Livewire::withQueryParams(['kategori' => 'kepiting'])->test('pre-orders')->assertSet('category', 'kepiting');
    });

    it('ignores a slug that is not a root category with pre-orders', function (string $slug) {
        expect(poCardNames(poList(['kategori' => $slug])))->toHaveCount(3);

        Livewire::test('pre-orders')->call('chooseCategory', $slug)->assertSet('category', '');
    })->with([
        'unknown' => ['tidak-ada'],
        'subcategory' => ['bakau'],
        'root without pre-orders' => ['tiram'],
    ]);

    it('escapes category names', function () {
        $this->udang->update(['name' => 'Udang <script>alert(1)</script>']);

        expect(poPage())->toContain(e('Udang <script>alert(1)</script>'))->not->toContain('<script>alert(1)</script>');
    });
});

describe('location', function () {
    beforeEach(function () {
        // About 2 km and 30 km from the buyer at Monas.
        poProduct('Kepiting dekat', [], Store::factory()->active()->create(['name' => 'Lapak Dekat', 'lat' => '-6.1900', 'lng' => '106.8300']));
        poProduct('Udang jauh', [], Store::factory()->active()->create(['name' => 'Lapak Jauh', 'lat' => '-6.4000', 'lng' => '106.9500']));
    });

    it('asks for the location before offering the radius', function () {
        expect(poPage())
            ->toContain('Bagikan lokasi')
            ->toMatch('#<input[^>]*data-radius-option="10"[^>]*disabled#')
            ->not->toContain('Ubah lokasi');
    });

    it('shows the distance and keeps the stores within the radius', function () {
        $component = Livewire::test('pre-orders')
            ->call('locate', -6.1754, 106.8272)
            ->assertSee('Ubah lokasi');

        expect($component->html())
            ->toMatch('#Lapak Dekat, \d,\d km#')
            ->not->toMatch('#<input[^>]*data-radius-option="10"[^>]*disabled#');

        $component->set('radius', '10')
            ->assertSee('Lapak dalam 10 km dari lokasi Anda')
            ->assertSee('Kepiting dekat')
            ->assertDontSee('Udang jauh');
    });

    it('rounds the location and keeps it out of the URL and the session', function () {
        $component = Livewire::test('pre-orders')->call('locate', -6.175412345, 106.827298765)
            ->assertSet('lat', '-6.175')
            ->assertSet('lng', '106.827');

        expect($component->html())->not->toContain('-6.175412345')->not->toContain('-6.175"')
            ->and(session()->all())->not->toHaveKey('lat');
    });

    it('never reads the location from the URL', function () {
        Livewire::withQueryParams(['lat' => '-6.1754', 'lng' => '106.8272'])
            ->test('pre-orders')
            ->assertSet('lat', null)
            ->assertSet('lng', null)
            ->assertDontSee('Lapak Dekat, ');
    });

    it('ignores a point that is not on earth', function (mixed $lat, mixed $lng) {
        Livewire::test('pre-orders')->call('locate', $lat, $lng)->assertSet('lat', null)->assertSet('lng', null);
    })->with([
        'latitude too large' => [91, 106.8],
        'longitude too large' => [-6.1, 181],
        'not a number' => ['a', 'b'],
    ]);

    it('ignores a radius it does not offer or without a location', function (bool $located, string $radius) {
        $component = Livewire::test('pre-orders');

        if ($located) {
            $component->call('locate', -6.1754, 106.8272);
        }

        $component->set('radius', $radius)->assertSee('Kepiting dekat')->assertSee('Udang jauh');
    })->with([
        'not offered' => [true, '7'],
        'not a number' => [true, 'dekat'],
        'without a location' => [false, '10'],
    ]);
});

describe('ready today', function () {
    it('lists up to eight ready stock products checked today, real stores first, last checked first', function () {
        $demo = Store::factory()->active()->demo()->create();
        poReadyProduct('Contoh pagi', Carbon::parse('2026-10-16 07:00', 'Asia/Jakarta')->utc(), $demo);
        foreach (range(1, 8) as $i) {
            poReadyProduct('Siap '.$i, Carbon::parse('2026-10-16 06:00', 'Asia/Jakarta')->addMinutes($i)->utc());
        }
        poReadyProduct('Kemarin sore', Carbon::parse('2026-10-15 17:20', 'Asia/Jakarta')->utc());
        poReadyProduct('Belum dicek', null);
        poProduct('Pre order');

        $section = poSection(poPage(), 'siap-judul');
        preg_match_all('#data-ready-card="([^"]*)"#', $section, $matches);

        expect($matches[1])->toBe(['Siap 8', 'Siap 7', 'Siap 6', 'Siap 5', 'Siap 4', 'Siap 3', 'Siap 2', 'Siap 1']);
    });

    it('shows the condition, check time, stock left, name, store, and price', function () {
        $store = Store::factory()->active()->create(['name' => 'Lapak Bu Sari']);
        poReadyProduct('Kepiting bakau', Carbon::parse('2026-10-16 06:30', 'Asia/Jakarta')->utc(), $store, ['price' => 185000, 'stock' => '12.000', 'unit' => Unit::Kg], ['condition' => ProductCondition::Live]);

        expect(poSection(poPage(), 'siap-judul'))
            ->toContain('Tidak mau menunggu? Ini stok yang ada hari ini')
            ->toMatch('#data-ready-card="Kepiting bakau".*?Siap hari ini.*?Hidup.*?Dicek 06\.30, sisa 12 kg.*?Kepiting bakau.*?Lapak Bu Sari.*?Rp185\.000.*?/ Kg#s')
            ->toContain('href="'.route('catalog').'"');
    });

    it('labels products of a demo store', function () {
        poReadyProduct('Udang contoh', now(), Store::factory()->active()->demo()->create());

        expect(poSection(poPage(), 'siap-judul'))->toMatch('#data-ready-card="Udang contoh".*?Toko contoh#s');
    });

    it('is left out when no ready stock was checked today', function () {
        poReadyProduct('Kemarin', now()->subDay());

        expect(poPage())->not->toContain('aria-labelledby="siap-judul"');
    });
});

describe('empty', function () {
    it('says there are no pre-orders and still shows the stock ready today', function () {
        poReadyProduct('Kerang hijau', now());

        $html = poPage();

        expect(poSection($html, 'pre-order-judul'))
            ->toContain('Belum ada produk pre order')
            ->toContain('href="'.route('catalog').'"')
            ->and(poSection($html, 'siap-judul'))->toContain('Kerang hijau');
    });

    it('offers to clear the filters when none match', function () {
        poProduct('Sepuluh hari', ['lead_time_days' => 10]);

        Livewire::withQueryParams(['paling_lambat' => 'besok'])
            ->test('pre-orders')
            ->assertSee('Tidak ada pre order untuk pilihan ini')
            ->call('clearFilters')
            ->assertSet('deadline', '')
            ->assertSee('Sepuluh hari');
    });
});

it('runs the same queries for two or twenty pre-orders', function () {
    $count = function (): int {
        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });
        poPage();
        DB::flushQueryLog();

        return $queries;
    };

    foreach (range(1, 2) as $i) {
        poProduct('Pre order '.$i);
        poReadyProduct('Siap '.$i, now());
    }
    $two = $count();

    foreach (range(3, 20) as $i) {
        poProduct('Pre order '.$i);
        poReadyProduct('Siap '.$i, now());
    }

    expect($count())->toBe($two);
});
