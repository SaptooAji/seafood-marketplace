<?php

declare(strict_types=1);

use App\Data\Recipe;
use App\Models\Category;
use Database\Seeders\CategorySeeder;

/*
 * The static recipes (#188) in config/recipes.php, read through App\Data\Recipe.
 * One recipe belongs to one root category, so the data can later move to a
 * `recipes` table owned by the category (#174).
 */

/**
 * @return list<array<string, mixed>>
 */
function recipeConfig(): array
{
    return config()->array('recipes');
}

describe('config data', function () {
    it('has at least 10 recipes for Kepiting, Udang, Cumi, and Kerang', function () {
        $roots = collect(recipeConfig())->pluck('category')->unique()->sort()->values()->all();

        expect(count(recipeConfig()))->toBeGreaterThanOrEqual(10)
            ->and($roots)->toBe(['cumi', 'kepiting', 'kerang', 'udang']);
    });

    it('has unique slugs', function () {
        $slugs = collect(recipeConfig())->pluck('slug');

        expect($slugs->duplicates()->all())->toBe([])
            ->and($slugs->every(fn (string $slug): bool => preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) === 1))->toBeTrue();
    });

    it('points at categories that exist', function () {
        $this->seed(CategorySeeder::class);

        foreach (recipeConfig() as $recipe) {
            $root = Category::query()->where('slug', $recipe['category'])->whereNull('parent_id')->first();
            $needed = Category::query()->where('slug', $recipe['needs']['category'])->first();

            expect($root)->not->toBeNull("{$recipe['slug']}: root category {$recipe['category']}")
                ->and($needed)->not->toBeNull("{$recipe['slug']}: needed category {$recipe['needs']['category']}")
                ->and($needed->children()->exists())->toBeFalse("{$recipe['slug']}: needs a leaf category")
                ->and(in_array($needed->id, [$root->id, ...$root->descendantIds()], true))->toBeTrue("{$recipe['slug']}: needs a category under its root");

            foreach ($recipe['swaps'] ?? [] as $swap) {
                expect(Category::query()->where('slug', $swap)->exists())->toBeTrue("{$recipe['slug']}: swap {$swap}");
            }
        }
    });

    it('uses whole positive numbers for minutes and servings and decimal strings for amounts', function () {
        foreach (recipeConfig() as $recipe) {
            expect($recipe['minutes'])->toBeInt()->toBeGreaterThan(0)
                ->and($recipe['servings'])->toBeInt()->toBeGreaterThan(0)
                ->and($recipe['needs']['kg'])->toMatch('/^\d+\.\d{3}$/')
                ->and($recipe['level'])->toBeIn(['Mudah', 'Sedang'])
                ->and($recipe['group'])->toBeIn(array_keys(Recipe::GROUPS))
                ->and($recipe['steps'])->not->toBeEmpty();

            foreach ($recipe['ingredients'] as $ingredient) {
                expect($ingredient['amount'] === null || preg_match('/^\d+(\.\d{1,3})?$/', $ingredient['amount']) === 1)
                    ->toBeTrue("{$recipe['slug']}: {$ingredient['name']}");
            }
        }
    });

    it('has a photo for every recipe, credited on the photo credit page', function () {
        $credited = collect(config()->array('photo-credits'))->pluck('file')->all();

        foreach (recipeConfig() as $recipe) {
            expect(public_path($recipe['photo']))->toBeFile()
                ->and($credited)->toContain('public/'.$recipe['photo']);
        }
    });
});

describe('App\Data\Recipe', function () {
    beforeEach(function () {
        config(['recipes' => [
            recipeFixture(['slug' => 'kepiting-saus-padang', 'name' => 'Kepiting saus padang', 'category' => 'kepiting', 'group' => 'acara', 'featured' => true]),
            recipeFixture(['slug' => 'udang-goreng-mentega', 'name' => 'Udang goreng mentega', 'category' => 'udang', 'group' => 'cepat', 'featured' => true]),
            recipeFixture(['slug' => 'sup-kepiting-jagung', 'name' => 'Sup kepiting jagung', 'category' => 'kepiting', 'group' => 'keluarga']),
        ]]);
    });

    it('reads every recipe in config order', function () {
        expect(array_map(fn (Recipe $recipe): string => $recipe->slug, Recipe::all()))
            ->toBe(['kepiting-saus-padang', 'udang-goreng-mentega', 'sup-kepiting-jagung']);
    });

    it('finds a recipe by slug, or null', function () {
        expect(Recipe::find('udang-goreng-mentega')?->name)->toBe('Udang goreng mentega')
            ->and(Recipe::find('tidak-ada'))->toBeNull();
    });

    it('lists the recipes of a root category', function () {
        expect(array_map(fn (Recipe $recipe): string => $recipe->slug, Recipe::inCategory('kepiting')))
            ->toBe(['kepiting-saus-padang', 'sup-kepiting-jagung']);
    });

    it('lists the recipes of a group', function () {
        expect(array_map(fn (Recipe $recipe): string => $recipe->slug, Recipe::inGroup('cepat')))->toBe(['udang-goreng-mentega']);
    });

    it('lists the featured recipes for the home page', function () {
        expect(array_map(fn (Recipe $recipe): string => $recipe->slug, Recipe::featured()))
            ->toBe(['kepiting-saus-padang', 'udang-goreng-mentega']);
    });

    it('describes the time and servings', function () {
        expect(Recipe::find('kepiting-saus-padang')->meta())->toBe('45 menit, 4 porsi');
    });
});

/**
 * A complete recipe entry, with the given fields changed.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function recipeFixture(array $overrides = []): array
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
            ['amount' => null, 'unit' => null, 'name' => 'garam dan gula secukupnya'],
        ],
        'steps' => ['Bersihkan kepiting.', 'Masak dengan bumbu.'],
        'swaps' => [],
        'featured' => false,
        ...$overrides,
    ];
}
