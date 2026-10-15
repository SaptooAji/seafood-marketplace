<?php

declare(strict_types=1);

use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
 * Error pages of the buyer web (#168): in Indonesian, in the site layout,
 * with a way back, and generic for server errors (DATA-5). They use the bare
 * layout, which runs no queries, so they still render when the database is
 * the reason for the error.
 */

beforeEach(function () {
    config(['app.name' => 'Seafood Marketplace', 'app.debug' => false]);
});

it('shows a 404 page in the layout with a way back', function () {
    $this->get('/halaman-yang-tidak-ada')
        ->assertNotFound()
        ->assertSee('<title>Halaman tidak ditemukan | Seafood Marketplace</title>', escape: false)
        ->assertSee('<meta name="robots" content="noindex, follow">', escape: false)
        ->assertSee('<main id="konten"', escape: false)
        ->assertSee('href="'.url('/').'"', escape: false)
        ->assertSee('href="'.url('/produk').'"', escape: false);
});

it('shows the same 404 page for a missing product', function () {
    $this->get('/produk/tidak-ada')
        ->assertNotFound()
        ->assertSee('Halaman tidak ditemukan');
});

it('renders the 404 page without querying the database', function () {
    Category::factory()->create(['name' => 'Kepiting']);
    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $this->get('/halaman-yang-tidak-ada')->assertNotFound()->assertDontSee('Kepiting');

    expect($queries)->toBe(0);
});

it('shows a generic page for a server error, without the exception', function () {
    Route::middleware('web')->get('/__galat-uji', function () {
        throw new RuntimeException('Rahasia internal: rekening 9876543210');
    });

    $html = $this->get('/__galat-uji')
        ->assertStatus(500)
        ->assertSee('Terjadi kesalahan')
        ->getContent();

    expect($html)
        ->not->toContain('Rahasia internal')
        ->not->toContain('9876543210')
        ->not->toContain('RuntimeException')
        ->not->toContain('.php');
});

it('explains other errors in Indonesian', function (int $status, string $title) {
    Route::middleware('web')->get('/__galat-'.$status, fn () => abort($status));

    $this->get('/__galat-'.$status)
        ->assertStatus($status)
        ->assertSee('<title>'.$title.' | Seafood Marketplace</title>', escape: false)
        ->assertSee('href="'.url('/').'"', escape: false);
})->with([
    'expired session' => [419, 'Sesi kedaluwarsa'],
    'too many requests' => [429, 'Terlalu banyak permintaan'],
]);

it('keeps API errors as JSON', function () {
    $this->getJson('/api/v1/tidak-ada')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});
