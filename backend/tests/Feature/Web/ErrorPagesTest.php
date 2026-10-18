<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Database\Seeders\CategorySeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/*
 * Error pages of the buyer web (#168, #197): in Indonesian, in the site
 * layout, with the code, a title, what happened, a way back, and photo tiles
 * of the categories. Server errors stay generic (DATA-5). The 404 page also
 * shows the products most recently checked; 419, 429, and 500 show three
 * steps instead and run no queries, so they render when the database is the
 * reason for the error and add no load while rate limited. The tiles come
 * from config/error-pages.php.
 */

beforeEach(function () {
    config(['app.name' => 'Seafood Marketplace', 'app.debug' => false]);
});

/**
 * A product buyers can see, with its stock checked at the given time.
 */
function errorPageProduct(string $name, ?Carbon $checkedAt = null): Product
{
    $product = Product::factory()->for(Store::factory()->active())->create(['name' => $name]);
    ProductVariant::factory()->for($product)->create(['stock_confirmed_at' => $checkedAt]);

    return $product;
}

/**
 * Route a test URL to an error with the given status.
 */
function errorPageRoute(int $status): string
{
    Route::middleware('web')->get('/__galat-'.$status, fn () => abort($status));

    return '/__galat-'.$status;
}

/**
 * Count the queries run while handling the request.
 */
function errorPageQueries(callable $request): int
{
    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $request();

    return $queries;
}

/**
 * Handle the request with the default connection pointing at a server that
 * refuses connections, then point it back so the test transaction can roll
 * back.
 */
function errorPageWithDatabaseDown(callable $request): TestResponse
{
    $default = config('database.default');
    config([
        'database.connections.broken' => [...config('database.connections.pgsql'), 'host' => '127.0.0.1', 'port' => 1],
        'database.default' => 'broken',
    ]);

    try {
        return $request();
    } finally {
        config(['database.default' => $default]);
    }
}

describe('404', function () {
    it('shows the code, the title, and what happened in the layout', function () {
        $this->get('/halaman-yang-tidak-ada')
            ->assertNotFound()
            ->assertSee('<title>Halaman tidak ditemukan | Seafood Marketplace</title>', escape: false)
            ->assertSee('<meta name="robots" content="noindex, follow">', escape: false)
            ->assertSee('<main id="konten"', escape: false)
            ->assertSee('Kode 404')
            ->assertSeeInOrder(['<h1', 'Halaman ini tidak ada di pasar kami', '</h1>'], escape: false)
            ->assertSee('Mungkin tautannya salah ketik atau produknya sudah tidak dijual.');
    });

    it('shows the same page for a missing product', function () {
        $this->get('/produk/tidak-ada')
            ->assertNotFound()
            ->assertSee('Halaman ini tidak ada di pasar kami')
            ->assertSee('Atau mulai dari kategori');
    });

    it('offers a big search to the catalog on the web and a way home on the phone', function () {
        $html = $this->get('/halaman-yang-tidak-ada')->assertNotFound()->getContent();

        // The header search keeps its own id; the page search is another form.
        expect($html)
            ->toMatch('#<form[^>]*action="'.preg_quote(route('catalog'), '#').'"[^>]*method="GET"[^>]*role="search"[^>]*class="[^"]*hidden[^"]*lg:flex#')
            ->toContain('<label for="cari-galat"')
            ->toMatch('#<input[^>]*id="cari-galat"[^>]*name="q"#')
            ->toMatch('#<a href="'.preg_quote(url('/'), '#').'"[^>]*class="[^"]*lg:hidden[^"]*"[^>]*>\s*Kembali ke beranda#');
    });

    it('shows the products most recently checked, four at most', function () {
        errorPageProduct('Udang kemarin', now()->subDay());
        errorPageProduct('Kepiting pagi ini', now()->subHour());
        errorPageProduct('Cumi belum dicek');
        errorPageProduct('Kerang subuh', now()->subHours(3));
        errorPageProduct('Rajungan pagi', now()->subHours(2));
        errorPageProduct('Tiram lusa', now()->subDays(2));

        $this->get('/halaman-yang-tidak-ada')
            ->assertNotFound()
            ->assertSee('Baru dikonfirmasi pagi ini')
            ->assertSee('href="'.route('catalog').'"', escape: false)
            ->assertSeeInOrder(['Kepiting pagi ini', 'Rajungan pagi', 'Kerang subuh', 'Udang kemarin'])
            ->assertDontSee('Tiram lusa')
            ->assertDontSee('Cumi belum dicek');
    });

    it('labels each product with when its stock was checked', function () {
        $this->travelTo(Carbon::parse('2026-10-18 09:00', 'Asia/Jakarta'));
        errorPageProduct('Kepiting pagi ini', Carbon::parse('2026-10-18 06:30', 'Asia/Jakarta'));

        $this->get('/halaman-yang-tidak-ada')->assertSee('Dicek');
    });

    it('leaves out products buyers cannot see', function () {
        $hidden = errorPageProduct('Kepiting nonaktif', now());
        $hidden->forceFill(['is_active' => false])->save();
        $blocked = errorPageProduct('Udang diblokir', now());
        $blocked->forceFill(['blocked_at' => now()])->save();

        $this->get('/halaman-yang-tidak-ada')
            ->assertNotFound()
            ->assertDontSee('Kepiting nonaktif')
            ->assertDontSee('Udang diblokir');
    });

    it('leaves out the products section when there are none', function () {
        $this->get('/halaman-yang-tidak-ada')
            ->assertNotFound()
            ->assertDontSee('Baru dikonfirmasi pagi ini')
            ->assertSee('Atau mulai dari kategori');
    });

    it('runs the same queries for four or ten products', function (int $count) {
        foreach (range(1, $count) as $i) {
            errorPageProduct('Produk '.$i, now()->subMinutes($i));
        }

        $queries = errorPageQueries(fn () => $this->get('/halaman-yang-tidak-ada')->assertNotFound());

        expect($queries)->toBeLessThanOrEqual(8);
    })->with([
        'four products' => [4],
        'ten products' => [10],
    ]);

    it('still renders without the products when the database is down', function () {
        errorPageWithDatabaseDown(fn () => $this->get('/halaman-yang-tidak-ada'))
            ->assertNotFound()
            ->assertSee('Halaman ini tidak ada di pasar kami')
            ->assertSee('Atau mulai dari kategori')
            ->assertDontSee('Baru dikonfirmasi pagi ini');
    });

    it('does not echo the requested URL', function () {
        $html = $this->get('/produk/%3Cscript%3Ealert(1)%3C%2Fscript%3E')->assertNotFound()->getContent();

        expect($html)
            ->not->toContain('<script>alert(1)</script>')
            ->not->toContain('&lt;script&gt;alert(1)')
            ->not->toContain('alert(1)');
    });
});

describe('419, 429, and 500', function () {
    it('shows the code, the title, and three numbered steps', function (int $status, string $tabTitle, string $title, string $stepsTitle, array $steps) {
        $html = $this->get(errorPageRoute($status))
            ->assertStatus($status)
            ->assertSee('<title>'.$tabTitle.' | Seafood Marketplace</title>', escape: false)
            ->assertSee('<meta name="robots" content="noindex, follow">', escape: false)
            ->assertSee('Kode '.$status)
            ->assertSeeInOrder(['<h1', $title, '</h1>'], escape: false)
            ->assertSeeInOrder([$stepsTitle, '<ol', ...$steps, '</ol>'], escape: false)
            ->assertDontSee('Baru dikonfirmasi pagi ini')
            ->getContent();

        expect(substr_count($html, '<li data-error-step'))->toBe(3);
    })->with([
        'expired session' => [419, 'Sesi kedaluwarsa', 'Halaman ini sudah terlalu lama dibuka', 'Cara melanjutkan', [
            'Muat ulang halaman ini.',
            'Isi ulang formulirnya, lalu kirim lagi.',
            'Kalau terus muncul, keluar lalu masuk kembali ke akun Anda.',
        ]],
        'too many requests' => [429, 'Terlalu banyak permintaan', 'Terlalu banyak permintaan dalam waktu singkat', 'Yang bisa Anda lakukan', [
            'Tunggu sekitar satu menit.',
            'Hindari menekan tombol kirim berkali-kali.',
            'Setelah itu buka lagi halamannya seperti biasa.',
        ]],
        'server error' => [500, 'Terjadi kesalahan', 'Ada gangguan di server kami', 'Sambil menunggu', [
            'Muat ulang halaman dalam beberapa menit.',
            'Kalau sedang memesan, cek halaman Pesanan saya sebelum memesan ulang agar tidak dobel.',
            'Gangguan biasanya pulih dalam waktu singkat.',
        ]],
    ]);

    it('offers to load the page again and a way home', function (int $status, string $reload) {
        $html = $this->get(errorPageRoute($status))->assertStatus($status)->getContent();

        // An empty href loads the same address again, without printing it.
        expect($html)
            ->toMatch('#<a href=""[^>]*>\s*'.preg_quote($reload, '#').'\s*</a>#')
            ->toMatch('#<a href="'.preg_quote(url('/'), '#').'"[^>]*>\s*Kembali ke beranda\s*</a>#');
    })->with([
        'expired session' => [419, 'Muat ulang halaman'],
        'too many requests' => [429, 'Coba lagi'],
        'server error' => [500, 'Muat ulang'],
    ]);

    it('runs no queries', function (int $status) {
        Category::factory()->create(['name' => 'Kategori dari database']);
        $url = errorPageRoute($status);

        $queries = errorPageQueries(fn () => $this->get($url)->assertStatus($status)->assertDontSee('Kategori dari database'));

        expect($queries)->toBe(0);
    })->with([
        'expired session' => [419],
        'too many requests' => [429],
        'server error' => [500],
    ]);

    it('renders the server error page when the database is down', function () {
        Route::middleware('web')->get('/__galat-db', fn () => DB::select('select 1'));

        errorPageWithDatabaseDown(fn () => $this->get('/__galat-db'))
            ->assertStatus(500)
            ->assertSee('Ada gangguan di server kami')
            ->assertSee('Atau mulai dari kategori');
    });

    it('shows a generic page for a server error, without the exception', function () {
        Route::middleware('web')->get('/__galat-uji', function () {
            throw new RuntimeException('Rahasia internal: rekening 9876543210');
        });

        $html = $this->get('/__galat-uji')
            ->assertStatus(500)
            ->assertSee('Ada gangguan di server kami')
            ->getContent();

        expect($html)
            ->not->toContain('Rahasia internal')
            ->not->toContain('9876543210')
            ->not->toContain('RuntimeException')
            ->not->toContain('.php');
    });
});

describe('category tiles', function () {
    it('shows a photo tile for every category in the config, linking to the catalog', function (string $url, int $status) {
        $response = $this->get($url === 'route' ? errorPageRoute($status) : $url)->assertStatus($status);

        foreach (config()->array('error-pages.categories') as $tile) {
            expect($response->getContent())->toMatch(
                '#<a href="'.preg_quote(route('catalog', ['kategori' => $tile['slug']]), '#').'"[^>]*data-error-category[^>]*>\s*'
                .'<img src="'.preg_quote(asset($tile['photo']), '#').'" alt=""[^>]*>.*?'.preg_quote(e($tile['name']), '#').'#s',
            );
        }
    })->with([
        '404' => ['/halaman-yang-tidak-ada', 404],
        '419' => ['route', 419],
        '429' => ['route', 429],
        '500' => ['route', 500],
    ]);

    it('lists four tiles, each with a photo that exists and is credited', function () {
        $tiles = config()->array('error-pages.categories');
        $credited = collect(config()->array('photo-credits'))->pluck('file');

        expect($tiles)->toHaveCount(4);

        foreach ($tiles as $tile) {
            expect($tile)->toHaveKeys(['name', 'slug', 'photo'])
                ->and(public_path($tile['photo']))->toBeFile()
                ->and($credited)->toContain('public/'.$tile['photo']);
        }
    });

    it('names root categories of the seeded tree', function () {
        $this->seed(CategorySeeder::class);
        $roots = Category::query()->whereNull('parent_id')->pluck('slug');

        foreach (config()->array('error-pages.categories') as $tile) {
            expect($roots)->toContain($tile['slug']);
        }
    });
});

it('keeps API errors as JSON', function () {
    $this->getJson('/api/v1/tidak-ada')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});
