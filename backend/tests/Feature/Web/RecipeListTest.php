<?php

declare(strict_types=1);

use App\Data\Recipe;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * The static recipe list /resep (#188), board "Resep Daftar 2" and "Resep
 * Daftar Ponsel 2" on the design canvas (Eksperimen 1.5.0).
 */

/**
 * A recipe entry for config('recipes'), with the given fields changed.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function listRecipe(array $overrides = []): array
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
        'ingredients' => [['amount' => '8', 'unit' => 'siung', 'name' => 'bawang merah']],
        'steps' => ['Bersihkan kepiting.'],
        'swaps' => [],
        'featured' => false,
        ...$overrides,
    ];
}

/**
 * The HTML of the section labelled by the given heading id, or '' when it is not rendered.
 */
function recipeSection(string $html, string $labelledBy): string
{
    if (! str_contains($html, 'aria-labelledby="'.$labelledBy.'"')) {
        return '';
    }

    return Str::before(Str::after($html, 'aria-labelledby="'.$labelledBy.'"'), '</section>');
}

describe('page', function () {
    it('shows every recipe in its group, with the group size', function () {
        $html = $this->get('/resep')->assertOk()->getContent();

        foreach (Recipe::GROUPS as $group => $text) {
            $recipes = Recipe::inGroup($group);
            $section = recipeSection($html, 'resep-'.$group);

            expect($section)->toContain($text['title']);

            foreach ($recipes as $recipe) {
                expect($section)->toContain(e($recipe->name));
            }
        }
    });

    it('is named recipes.index', function () {
        expect(route('recipes.index'))->toBe(url('/resep'));
    });

    it('opens with the question, a search box, and shortcuts to each group with its size', function () {
        config(['recipes' => [
            listRecipe(['slug' => 'a', 'name' => 'Resep A', 'group' => 'cepat']),
            listRecipe(['slug' => 'b', 'name' => 'Resep B', 'group' => 'cepat']),
            listRecipe(['slug' => 'c', 'name' => 'Resep C', 'group' => 'keluarga']),
        ]]);

        $this->get('/resep')
            ->assertOk()
            ->assertSee('Mau masak apa hari ini?')
            ->assertSee('role="search"', escape: false)
            ->assertSee('href="#resep-cepat"', escape: false)
            ->assertSeeInOrder([Recipe::GROUPS['cepat']['title'], '2 resep'])
            ->assertSeeInOrder([Recipe::GROUPS['keluarga']['title'], '1 resep'])
            ->assertSeeInOrder(['Semua resep', '3 resep']);
    });

    it('shows the time, servings, and what to buy on each card', function () {
        config(['recipes' => [listRecipe()]]);

        $this->get('/resep')
            ->assertOk()
            ->assertSeeInOrder(['45 menit, 4 porsi', 'Kepiting saus padang', 'Butuh 1 kg kepiting bakau'])
            ->assertSee('src="'.asset('images/resep/kepiting-saus-padang.jpg').'"', escape: false)
            ->assertSee('href="'.url('/resep/kepiting-saus-padang').'"', escape: false);
    });

    it('is linked from the header and the footer', function () {
        $html = $this->get('/')->assertOk()->getContent();

        expect(substr_count($html, 'href="'.url('/resep').'"'))->toBeGreaterThanOrEqual(2);
    });

    it('has the title, meta description, canonical, and Open Graph tags', function () {
        $this->get('/resep')
            ->assertOk()
            ->assertSee('<title>Resep hasil laut | '.config('app.name').'</title>', escape: false)
            ->assertSee('<meta name="description"', escape: false)
            ->assertSee('<link rel="canonical" href="'.url('/resep').'">', escape: false)
            ->assertSee('<meta property="og:title"', escape: false)
            ->assertDontSee('noindex');
    });

    it('is in the sitemap', function () {
        $this->get('/sitemap.xml')->assertOk()->assertSee('<loc>'.url('/resep').'</loc>', escape: false);
    });
});

describe('search', function () {
    beforeEach(function () {
        config(['recipes' => [
            listRecipe(['slug' => 'kepiting-saus-padang', 'name' => 'Kepiting saus padang', 'group' => 'acara']),
            listRecipe(['slug' => 'udang-goreng-mentega', 'name' => 'Udang goreng mentega', 'group' => 'cepat',
                'ingredients' => [['amount' => '50', 'unit' => 'g', 'name' => 'mentega']]]),
            listRecipe(['slug' => 'sup-jagung', 'name' => 'Sup jagung manis', 'group' => 'keluarga',
                'ingredients' => [['amount' => '200', 'unit' => 'g', 'name' => 'daging Kepiting']]]),
        ]]);
    });

    it('finds recipes by name or ingredient, ignoring letter case', function () {
        $this->get('/resep?q=KEPITING')
            ->assertOk()
            ->assertSee('Kepiting saus padang')
            ->assertSee('Sup jagung manis')
            ->assertDontSee('Udang goreng mentega');
    });

    it('says what was searched for', function () {
        $this->get('/resep?q=mentega')->assertOk()->assertSee('Hasil untuk')->assertSee('mentega');
    });

    it('keeps search results out of search engines', function () {
        $this->get('/resep?q=udang')->assertOk()->assertSee('noindex');
    });

    it('shows a message and a link to all recipes when nothing matches', function () {
        $this->get('/resep?q=xyz')
            ->assertOk()
            ->assertSee('Belum ada resep untuk')
            ->assertSee('Lihat semua resep')
            ->assertDontSee('Kepiting saus padang');
    });

    it('cuts a search longer than 100 characters instead of failing', function () {
        $this->get('/resep?q='.str_repeat('a', 150))
            ->assertOk()
            ->assertSee(str_repeat('a', 100))
            ->assertDontSee(str_repeat('a', 101));
    });

    it('treats an array query as no search', function () {
        $this->get('/resep?q[]=kepiting')->assertOk()->assertSee('Udang goreng mentega');
    });
});

describe('security', function () {
    it('escapes the search shown back on the page', function () {
        $this->get('/resep?q='.urlencode('<script>alert(1)</script>'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', escape: false)
            ->assertSee('<script>alert(1)</script>');
    });

    it('escapes the recipe text', function () {
        config(['recipes' => [listRecipe(['name' => '<b>Resep</b>', 'needs' => ['category' => 'bakau', 'kg' => '1.000', 'label' => '<i>1 kg</i>']])]]);

        $this->get('/resep')
            ->assertOk()
            ->assertDontSee('<b>Resep</b>', escape: false)
            ->assertDontSee('<i>1 kg</i>', escape: false);
    });
});

describe('edge cases', function () {
    it('renders a group without recipes not at all, not even its heading', function () {
        config(['recipes' => [listRecipe(['group' => 'cepat'])]]);

        $html = $this->get('/resep')->assertOk()->getContent();

        expect(recipeSection($html, 'resep-cepat'))->not->toBe('')
            ->and($html)->not->toContain('aria-labelledby="resep-keluarga"')
            ->and($html)->not->toContain('aria-labelledby="resep-acara"')
            ->and($html)->not->toContain('href="#resep-acara"');
    });

    it('runs no database query for the recipes', function () {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->get('/resep')->assertOk();
        $withRecipes = count(DB::getQueryLog());

        config(['recipes' => []]);
        DB::flushQueryLog();
        $this->get('/resep')->assertOk();
        $withoutRecipes = count(DB::getQueryLog());
        DB::disableQueryLog();

        expect($withRecipes)->toBe($withoutRecipes);
    });
});
