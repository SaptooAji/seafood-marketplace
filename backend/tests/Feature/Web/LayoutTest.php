<?php

declare(strict_types=1);

use App\Models\Category;
use Illuminate\Support\Facades\Blade;

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

describe('home page', function () {
    it('uses the layout with the app name, search box, and root categories', function () {
        $kepiting = Category::factory()->create(['name' => 'Kepiting', 'slug' => 'kepiting', 'sort_order' => 1]);
        Category::factory()->create(['name' => 'Cumi', 'slug' => 'cumi', 'sort_order' => 2]);
        Category::factory()->childOf($kepiting)->create(['name' => 'Bakau Subkategori']);

        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="id"', escape: false)
            ->assertSee('Seafood Marketplace')
            ->assertSee('action="'.url('/produk').'"', escape: false)
            ->assertSee('name="q"', escape: false)
            ->assertSeeInOrder(['Kepiting', 'Cumi'])
            ->assertSee('href="'.url('/produk').'?kategori=kepiting"', escape: false)
            ->assertDontSee('Bakau Subkategori');
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
});
