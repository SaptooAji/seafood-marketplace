<?php

declare(strict_types=1);

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

/*
 * CONF-5 (#160): every response carries the basic security headers, from the
 * buyer web, the Filament panels, the API, and the error pages alike.
 * Content-Security-Policy and HSTS stay for Tahap 7 (CONF-4).
 */

/**
 * The headers every response must carry.
 *
 * @var array<string, string>
 */
const SECURITY_HEADERS = [
    'X-Content-Type-Options' => 'nosniff',
    'Referrer-Policy' => 'strict-origin-when-cross-origin',
    'X-Frame-Options' => 'DENY',
];

function assertSecurityHeaders(TestResponse $response): void
{
    foreach (SECURITY_HEADERS as $name => $value) {
        $response->assertHeader($name, $value);
    }
}

beforeEach(function () {
    $store = Store::factory()->active()->create(['slug' => 'toko-header']);
    $product = Product::factory()->for($store)->create(['slug' => 'produk-header']);
    ProductVariant::factory()->for($product)->create();
});

describe('web and panels', function () {
    it('sends the headers on every page', function (string $url) {
        $response = $this->get($url);

        expect($response->status())->toBeLessThan(400);
        assertSecurityHeaders($response);
    })->with([
        'home' => '/',
        'catalog' => '/produk',
        'product' => '/produk/produk-header',
        'store' => '/toko/toko-header',
        'sitemap' => '/sitemap.xml',
        'robots' => '/robots.txt',
        'admin login' => '/admin/login',
        'seller login' => '/seller/login',
    ]);

    it('sends the headers on a page that is not found', function () {
        $response = $this->get('/halaman-yang-tidak-ada');

        $response->assertNotFound();
        assertSecurityHeaders($response);
    });

    it('sends the headers on a redirect', function () {
        $response = $this->get('/seller');

        $response->assertRedirect();
        assertSecurityHeaders($response);
    });
});

describe('API', function () {
    it('sends the headers on a signed-in request', function () {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/v1/categories');

        $response->assertOk();
        assertSecurityHeaders($response);
    });

    it('sends the headers on a sign-in', function () {
        User::factory()->create(['email' => 'penjual@example.com', 'password' => 'rahasia123']);

        $response = $this->postJson('/api/v1/auth/login', ['email' => 'penjual@example.com', 'password' => 'rahasia123', 'device_name' => 'Pixel 8']);

        $response->assertOk();
        assertSecurityHeaders($response);
    });

    it('sends the headers on an empty 204 response', function () {
        $token = User::factory()->create()->createToken('Pixel 8')->plainTextToken;

        $response = $this->withToken($token)->postJson('/api/v1/auth/logout');

        $response->assertNoContent();
        assertSecurityHeaders($response);
    });

    it('sends the headers on API errors', function (Closure $request, int $status) {
        $response = $request($this);

        $response->assertStatus($status);
        assertSecurityHeaders($response);
    })->with([
        'unknown route 404' => [fn ($test) => $test->getJson('/api/v1/tidak-ada'), 404],
        'no token 401' => [fn ($test) => $test->getJson('/api/v1/categories'), 401],
        'validation 422' => [fn ($test) => $test->postJson('/api/v1/auth/login', []), 422],
    ]);

    it('sends the headers on a 429', function () {
        foreach (range(1, 5) as $_) {
            $this->postJson('/api/v1/auth/login', ['email' => 'penjual@example.com', 'password' => 'salah', 'device_name' => 'Pixel 8']);
        }

        $response = $this->postJson('/api/v1/auth/login', ['email' => 'penjual@example.com', 'password' => 'salah', 'device_name' => 'Pixel 8']);

        $response->assertTooManyRequests();
        assertSecurityHeaders($response);
    });
});

describe('security', function () {
    it('cannot be turned off from the request', function () {
        $response = $this->withHeaders([
            'X-Frame-Options' => 'ALLOWALL',
            'Referrer-Policy' => 'unsafe-url',
            'X-Content-Type-Options' => '',
        ])->get('/?X-Frame-Options=ALLOWALL&nosniff=0');

        assertSecurityHeaders($response);
    });

    it('keeps a header that a response sets on purpose', function () {
        Route::get('/_test/embeddable', fn () => response('ok')->header('X-Frame-Options', 'SAMEORIGIN'));

        $response = $this->get('/_test/embeddable');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    });
});
