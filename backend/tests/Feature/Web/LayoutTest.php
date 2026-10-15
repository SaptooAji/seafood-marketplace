<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['app.name' => 'Seafood Marketplace']);
});

/**
 * The layout rendered around a line of text, as a page would use it.
 *
 * @param  array<string, string>  $props
 */
function renderLayout(array $props = []): string
{
    return Blade::render('<x-layouts.app :title="$title" :description="$description">Isi halaman</x-layouts.app>', [
        'title' => $props['title'] ?? null,
        'description' => $props['description'] ?? null,
    ]);
}

/** The page's header element, so links in the footer do not count. */
function headerOf(string $html): string
{
    return Str::between($html, '<header', '</header>');
}

describe('home page', function () {
    it('uses the layout with the app name and search box', function () {
        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="id"', escape: false)
            ->assertSee('Seafood Marketplace')
            ->assertSee('action="'.url('/produk').'"', escape: false)
            ->assertSee('name="q"', escape: false);
    });

    it('works without categories', function () {
        $this->get('/')->assertOk()->assertSee('Seafood Marketplace');
    });

    it('has the SEO tags', function () {
        $this->get('/')
            ->assertOk()
            ->assertSee('<title>Seafood Marketplace</title>', escape: false)
            ->assertSee('<meta name="description" content="', escape: false)
            ->assertSee('<link rel="canonical" href="'.url('/').'">', escape: false)
            ->assertSee('<meta property="og:site_name" content="Seafood Marketplace">', escape: false)
            ->assertSee('<meta property="og:locale" content="id_ID">', escape: false)
            ->assertSee('<meta property="og:type" content="website">', escape: false)
            ->assertSee('<meta property="og:title" content="Seafood Marketplace">', escape: false)
            ->assertSee('<meta property="og:url" content="'.url('/').'">', escape: false)
            ->assertSee('<meta property="og:description" content="', escape: false);
    });

    it('leaves the query out of the canonical URL', function () {
        $this->get('/?utm_source=wa&ref=budi')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.url('/').'">', escape: false)
            ->assertSee('<meta property="og:url" content="'.url('/').'">', escape: false)
            ->assertDontSee('utm_source');
    });

    it('escapes a search query in the search box and keeps it out of the head', function () {
        $this->get('/?q='.urlencode('<script>alert(1)</script>'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', escape: false)
            ->assertSee('value="&lt;script&gt;alert(1)&lt;/script&gt;"', escape: false)
            ->assertSee('<link rel="canonical" href="'.url('/').'">', escape: false);
    });

    it('ignores a search query sent as an array or longer than 100 characters', function () {
        $this->get('/?q[]=bakau')->assertOk()->assertSee('value=""', escape: false);

        $this->get('/?q='.str_repeat('a', 300))
            ->assertOk()
            ->assertSee('value="'.str_repeat('a', 100).'"', escape: false);
    });
});

describe('layout', function () {
    it('puts the page title before the app name', function () {
        expect(renderLayout(['title' => 'Katalog']))
            ->toContain('<title>Katalog | Seafood Marketplace</title>')
            ->toContain('<meta property="og:title" content="Katalog | Seafood Marketplace">')
            ->toContain('Isi halaman');
    });

    it('uses the page description for the meta and Open Graph descriptions', function () {
        expect(renderLayout(['description' => 'Kepiting bakau hidup dari Muara Angke.']))
            ->toContain('<meta name="description" content="Kepiting bakau hidup dari Muara Angke.">')
            ->toContain('<meta property="og:description" content="Kepiting bakau hidup dari Muara Angke.">');
    });

    it('escapes the title and description', function () {
        $html = renderLayout(['title' => '<script>alert(1)</script>', 'description' => '"><script>alert(2)</script>']);

        expect($html)
            ->not->toContain('<script>alert(1)</script>')
            ->not->toContain('<script>alert(2)</script>')
            ->toContain('&lt;script&gt;alert(1)&lt;/script&gt; | Seafood Marketplace');
    });

    it('renders what pages push to the head stack', function () {
        $html = Blade::render(<<<'BLADE'
            <x-layouts.app title="Produk">
                @push('head')
                    <script type="application/ld+json">{"@type":"Product"}</script>
                @endpush
                Isi
            </x-layouts.app>
            BLADE);

        expect($html)->toContain('<script type="application/ld+json">{"@type":"Product"}</script>');
    });

    it('starts with a skip link to the main content', function () {
        $html = $this->get('/')->assertOk()->getContent();

        expect($html)->toMatch('#<body[^>]*>\s*<a href="\#konten"[^>]*>Lewati ke konten</a>#')
            ->toContain('<main id="konten"');
    });

    it('runs no queries in the bare layout used by error pages, even for a signed-in buyer', function () {
        Category::factory()->create(['name' => 'Kepiting', 'slug' => 'kepiting']);
        $this->actingAs(User::factory()->create());
        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $html = Blade::render('<x-layouts.app title="Galat" :bare="true">Isi</x-layouts.app>');

        expect($queries)->toBe(0)
            ->and($html)->not->toContain('Kepiting')
            ->toContain('Isi');
    });
});

describe('header', function () {
    it('has no category links on any page', function (string $url) {
        Category::factory()->create(['name' => 'Kepiting', 'slug' => 'kepiting']);

        expect(headerOf($this->get($url)->assertOk()->getContent()))
            ->not->toContain('?kategori=')
            ->not->toContain('aria-label="Kategori"');
    })->with([
        'home page' => ['/'],
        'catalog' => ['/produk'],
        'catalog filtered by a category' => ['/produk?kategori=kepiting'],
    ]);

    it('links guests to Google sign-in', function () {
        expect(headerOf($this->get('/')->assertOk()->getContent()))
            ->toContain('href="'.route('login').'"')
            ->toContain('Masuk');
    });

    it('hides the sign-in link from a signed-in buyer', function () {
        $html = $this->actingAs(User::factory()->create())->get('/')->assertOk()->getContent();

        expect(headerOf($html))->not->toContain('href="'.route('login').'"');
    });

    it('leaves the sign-in link out of the bare layout used by error pages', function () {
        $html = Blade::render('<x-layouts.app title="Galat" :bare="true">Isi</x-layouts.app>');

        expect(headerOf($html))
            ->toContain('href="'.route('catalog').'"')
            ->not->toContain('href="'.route('login').'"');
    });
});

describe('footer', function () {
    it('links to the catalog, the root categories, and opening a store', function () {
        Category::factory()->create(['name' => 'Kepiting', 'slug' => 'kepiting']);

        $footer = Str::after($this->get('/')->assertOk()->getContent(), '<footer');

        expect($footer)
            ->toContain('href="'.url('/produk').'"')
            ->toContain('href="'.url('/produk').'?kategori=kepiting"')
            ->toContain('href="'.url('/seller').'"')
            ->toContain('Buka lapak')
            ->toContain('&copy; '.now()->year.' Seafood Marketplace');
    });
});
