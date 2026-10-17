<?php

declare(strict_types=1);

namespace App\Data;

/**
 * A static recipe from config/recipes.php. Views read recipes through this
 * class, never the config, so the source can later become a `recipes` table
 * owned by the root category (#174).
 */
readonly class Recipe
{
    /**
     * The groups on the recipe list, in order.
     *
     * @var array<string, array{title: string, note: string}>
     */
    public const array GROUPS = [
        'cepat' => ['title' => 'Cepat, di bawah 30 menit', 'note' => 'Untuk makan malam sepulang kerja.'],
        'keluarga' => ['title' => 'Untuk makan bersama keluarga', 'note' => 'Porsi besar, bumbu yang disukai semua umur.'],
        'acara' => ['title' => 'Untuk acara dan tamu', 'note' => 'Sedikit lebih repot, hasilnya pantas disajikan.'],
    ];

    /**
     * @param  array{category: string, kg: string, label: string}  $needs  the seafood to buy: a leaf category and an amount in kg
     * @param  list<array{amount: string|null, unit: string|null, name: string}>  $ingredients  amounts for `servings` people
     * @param  list<string>  $steps
     * @param  list<string>  $swaps  leaf categories that can replace the seafood
     */
    public function __construct(
        public string $slug,
        public string $name,
        public string $description,
        public string $photo,
        public string $category,
        public int $minutes,
        public int $servings,
        public string $level,
        public string $group,
        public array $needs,
        public array $ingredients,
        public array $steps,
        public array $swaps,
        public bool $featured,
    ) {}

    /**
     * Every recipe, in config order.
     *
     * @return list<self>
     */
    public static function all(): array
    {
        /** @var list<array{slug: string, name: string, description: string, photo: string, category: string, minutes: int, servings: int, level: string, group: string, needs: array{category: string, kg: string, label: string}, ingredients: list<array{amount: string|null, unit: string|null, name: string}>, steps: list<string>, swaps?: list<string>, featured?: bool}> $recipes */
        $recipes = config()->array('recipes');

        return array_map(fn (array $recipe): self => new self(
            slug: $recipe['slug'],
            name: $recipe['name'],
            description: $recipe['description'],
            photo: $recipe['photo'],
            category: $recipe['category'],
            minutes: $recipe['minutes'],
            servings: $recipe['servings'],
            level: $recipe['level'],
            group: $recipe['group'],
            needs: $recipe['needs'],
            ingredients: $recipe['ingredients'],
            steps: $recipe['steps'],
            swaps: $recipe['swaps'] ?? [],
            featured: $recipe['featured'] ?? false,
        ), $recipes);
    }

    public static function find(string $slug): ?self
    {
        foreach (self::all() as $recipe) {
            if ($recipe->slug === $slug) {
                return $recipe;
            }
        }

        return null;
    }

    /**
     * The recipes of a root category, by its slug.
     *
     * @return list<self>
     */
    public static function inCategory(string $rootSlug): array
    {
        return array_values(array_filter(self::all(), fn (self $recipe): bool => $recipe->category === $rootSlug));
    }

    /**
     * The recipes of a group in GROUPS.
     *
     * @return list<self>
     */
    public static function inGroup(string $group): array
    {
        return array_values(array_filter(self::all(), fn (self $recipe): bool => $recipe->group === $group));
    }

    /**
     * The recipes picked for the home page.
     *
     * @return list<self>
     */
    public static function featured(): array
    {
        return array_values(array_filter(self::all(), fn (self $recipe): bool => $recipe->featured));
    }

    /**
     * The recipes whose name or an ingredient contains the term, ignoring case.
     *
     * @return list<self>
     */
    public static function search(string $term): array
    {
        $term = mb_strtolower(trim($term));

        return array_values(array_filter(self::all(), function (self $recipe) use ($term): bool {
            $haystack = mb_strtolower($recipe->name.' '.implode(' ', array_column($recipe->ingredients, 'name')));

            return str_contains($haystack, $term);
        }));
    }

    /**
     * Time and servings, as on the cards: "45 menit, 4 porsi".
     */
    public function meta(): string
    {
        return "{$this->minutes} menit, {$this->servings} porsi";
    }
}
