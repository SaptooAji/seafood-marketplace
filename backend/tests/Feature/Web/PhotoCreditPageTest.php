<?php

declare(strict_types=1);

use Illuminate\Support\Str;

/*
 * The photo credit page (#187): every Wikimedia Commons photo the site shows,
 * with its title, author, source, license, and changes, as CC BY and CC BY-SA
 * require. Rendered from config/photo-credits.php.
 */

/**
 * One credit entry, with the given fields changed.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function photoCredit(array $overrides = []): array
{
    return [
        'file' => 'kepiting-bakau/1-hidup-di-keranjang.jpg',
        'title' => 'Mud crab 2.jpg',
        'author' => 'Vengolis',
        'source_url' => 'https://commons.wikimedia.org/wiki/File:Mud_crab_2.jpg',
        'license' => 'CC BY-SA 3.0',
        'license_url' => 'https://creativecommons.org/licenses/by-sa/3.0/',
        'changes' => 'Diperkecil dan dikompres ulang.',
        ...$overrides,
    ];
}

/**
 * The HTML of the section labelled by the given heading id, or '' when it is not rendered.
 */
function creditSection(string $html, string $labelledBy): string
{
    if (! str_contains($html, 'aria-labelledby="'.$labelledBy.'"')) {
        return '';
    }

    return Str::before(Str::after($html, 'aria-labelledby="'.$labelledBy.'"'), '</section>');
}

describe('page', function () {
    it('lists every credit with its author, license, and source', function () {
        config(['photo-credits' => [
            photoCredit(),
            photoCredit(['file' => 'udang/1-udang-vaname.jpg', 'title' => 'Whiteleg shrimp 09.jpg', 'author' => 'Judgefloro', 'source_url' => 'https://commons.wikimedia.org/wiki/File:Whiteleg_shrimp_09.jpg', 'license' => 'CC0', 'license_url' => 'https://creativecommons.org/publicdomain/zero/1.0/']),
        ]]);

        $this->get('/kredit-foto')
            ->assertOk()
            ->assertSee('Kredit foto')
            ->assertSeeInOrder(['Mud crab 2.jpg', 'Vengolis', 'CC BY-SA 3.0'])
            ->assertSeeInOrder(['Whiteleg shrimp 09.jpg', 'Judgefloro', 'CC0'])
            ->assertSee('href="https://commons.wikimedia.org/wiki/File:Mud_crab_2.jpg"', escape: false)
            ->assertSee('href="https://creativecommons.org/licenses/by-sa/3.0/"', escape: false);
    });

    it('is named credits', function () {
        expect(route('credits'))->toBe(url('/kredit-foto'));
    });

    it('explains the photos and that demo stores are not real', function () {
        $this->get('/kredit-foto')
            ->assertOk()
            ->assertSee('Wikimedia Commons')
            ->assertSee('Toko contoh bukan toko sungguhan');
    });

    it('shows the changes made to a photo', function () {
        config(['photo-credits' => [photoCredit(['changes' => 'Dipotong dan diperkecil.'])]]);

        $this->get('/kredit-foto')->assertOk()->assertSeeInOrder(['Perubahan', 'Dipotong dan diperkecil.']);
    });

    it('groups the credits into demo store products, recipes, and other pages', function () {
        config(['photo-credits' => [
            photoCredit(['title' => 'Foto Produk.jpg']),
            photoCredit(['file' => 'public/images/resep/kepiting-saus-padang.jpg', 'title' => 'Foto Resep.jpg']),
            photoCredit(['file' => 'public/images/keperluan/keluarga.jpg', 'title' => 'Foto Lain.jpg']),
        ]]);

        $html = $this->get('/kredit-foto')->assertOk()->getContent();

        expect(creditSection($html, 'kredit-produk'))->toContain('Foto Produk.jpg')->not->toContain('Foto Resep.jpg')
            ->and(creditSection($html, 'kredit-resep'))->toContain('Foto Resep.jpg')->not->toContain('Foto Lain.jpg')
            ->and(creditSection($html, 'kredit-lain'))->toContain('Foto Lain.jpg')->not->toContain('Foto Produk.jpg');
    });

    it('shows a small photo for the photos the site serves', function () {
        config(['photo-credits' => [photoCredit(['file' => 'public/images/resep/kepiting-saus-padang.jpg'])]]);

        $this->get('/kredit-foto')
            ->assertOk()
            ->assertSee('src="'.asset('images/resep/kepiting-saus-padang.jpg').'"', escape: false);
    });

    it('has the title, meta description, and canonical of a public page', function () {
        $this->get('/kredit-foto?utm=wa')
            ->assertOk()
            ->assertSee('<title>Kredit foto | '.config('app.name').'</title>', escape: false)
            ->assertSee('<meta name="description"', escape: false)
            ->assertSee('<link rel="canonical" href="'.url('/kredit-foto').'">', escape: false)
            ->assertDontSee('noindex');
    });

    it('is linked from the footer of every page', function (string $url) {
        $this->get($url)->assertOk()->assertSee('href="'.url('/kredit-foto').'"', escape: false);
    })->with(['home' => ['/'], 'catalog' => ['/produk'], 'credits' => ['/kredit-foto']]);
});

describe('security', function () {
    it('escapes the text of a credit', function (string $field) {
        config(['photo-credits' => [photoCredit([$field => '<script>alert(1)</script>'])]]);

        $this->get('/kredit-foto')
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', escape: false)
            ->assertSee('<script>alert(1)</script>');
    })->with(['title', 'author', 'license', 'changes']);

    it('opens every outside link with noopener', function () {
        config(['photo-credits' => [photoCredit()]]);

        $html = $this->get('/kredit-foto')->assertOk()->getContent();

        preg_match_all('#<a [^>]*href="https://[^"]*"[^>]*>#', $html, $links);

        expect($links[0])->not->toBeEmpty();

        foreach ($links[0] as $link) {
            expect($link)->toMatch('/rel="[^"]*noopener/');
        }
    });
});

describe('edge cases', function () {
    it('leaves out the changes line when a photo was not changed', function () {
        config(['photo-credits' => [photoCredit(['changes' => null])]]);

        $this->get('/kredit-foto')->assertOk()->assertDontSee('Perubahan');
    });

    it('shows the license as text when it has no link', function () {
        config(['photo-credits' => [photoCredit(['license' => 'Domain publik', 'license_url' => null])]]);

        $this->get('/kredit-foto')
            ->assertOk()
            ->assertSee('Domain publik')
            ->assertDontSee('href=""', escape: false);
    });

    it('still shows the introduction when no photo needs a credit', function () {
        config(['photo-credits' => []]);

        $this->get('/kredit-foto')
            ->assertOk()
            ->assertSee('Wikimedia Commons')
            ->assertSee('Belum ada foto yang perlu dikreditkan.');
    });

    it('renders a group only when it has credits', function () {
        config(['photo-credits' => [photoCredit()]]);

        $html = $this->get('/kredit-foto')->assertOk()->getContent();

        expect(creditSection($html, 'kredit-produk'))->not->toBe('')
            ->and($html)->not->toContain('aria-labelledby="kredit-resep"')
            ->and($html)->not->toContain('aria-labelledby="kredit-lain"');
    });
});
