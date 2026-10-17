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

    /**
     * How a cook measures each unit, in thousandths: the rounding step and
     * the smallest amount. Pieces are whole, spoons and centimetres halves,
     * millilitres tens; anything else keeps two decimals.
     *
     * @var array<string, array{int, int}>
     */
    private const array MEASURES = [
        'siung' => [1000, 1000],
        'buah' => [1000, 1000],
        'lembar' => [1000, 1000],
        'batang' => [1000, 1000],
        'butir' => [1000, 1000],
        'sdm' => [500, 500],
        'cm' => [500, 500],
        'ml' => [10_000, 10_000],
    ];

    /**
     * An amount for `$from` servings scaled to `$to` servings and rounded the
     * way the unit is measured (see MEASURES). Decimal strings in and out;
     * the arithmetic is in whole thousandths, never floats (VAL-2).
     */
    public static function scaleAmount(string $amount, ?string $unit, int $from, int $to): string
    {
        [$step, $minimum] = self::MEASURES[$unit ?? ''] ?? [10, 10];

        $scaled = self::divideRounded(self::thousandths($amount) * $to, $from);

        return self::fromThousandths(max($minimum, self::divideRounded($scaled, $step) * $step));
    }

    /**
     * A decimal string as buyers read it: "2.500" becomes "2,5".
     */
    public static function formatAmount(string $amount): string
    {
        $trimmed = str_contains($amount, '.') ? rtrim(rtrim($amount, '0'), '.') : $amount;

        return str_replace('.', ',', $trimmed);
    }

    /**
     * Each ingredient with its amount for 1 to 12 servings, as the servings
     * stepper shows it: "10 siung". An ingredient without an amount
     * ("secukupnya") has none.
     *
     * @return list<array{name: string, amounts: array<int, string>}>
     */
    public function ingredientsByServings(): array
    {
        return array_map(fn (array $ingredient): array => [
            'name' => $ingredient['name'],
            'amounts' => $ingredient['amount'] === null ? [] : self::byServings(
                fn (int $servings): string => trim(self::formatAmount(self::scaleAmount((string) $ingredient['amount'], $ingredient['unit'], $this->servings, $servings)).' '.$ingredient['unit']),
            ),
        ], $this->ingredients);
    }

    /**
     * The seafood to buy for the given servings, in kg with three decimals.
     */
    public function neededKg(int $servings): string
    {
        $grams = self::divideRounded(self::thousandths($this->needs['kg']) * $servings, $this->servings);

        return intdiv($grams, 1000).'.'.str_pad((string) ($grams % 1000), 3, '0', STR_PAD_LEFT);
    }

    /**
     * What to buy for the given servings, in kg from one kilo up and in grams
     * below it: "1,25 kg kepiting bakau", "500 g kepiting bakau".
     */
    public function neededLabel(int $servings): string
    {
        $grams = self::thousandths($this->neededKg($servings));
        $amount = $grams >= 1000 ? self::formatAmount(self::fromThousandths($grams)).' kg' : $grams.' g';

        return $amount.' '.$this->neededName();
    }

    /**
     * The seafood's name without the amount: "kepiting bakau".
     */
    public function neededName(): string
    {
        return (string) preg_replace('/^[\d.,]+\s*(kg|g)\s+/u', '', $this->needs['label']);
    }

    /**
     * The price of the seafood for the given servings at a price per kg, in
     * whole rupiah, rounded half up.
     */
    public function seafoodCost(int $pricePerKg, int $servings): int
    {
        return self::divideRounded($pricePerKg * self::thousandths($this->needs['kg']) * $servings, 1000 * $this->servings);
    }

    /**
     * A value for each number of servings the stepper offers, 1 to 12.
     *
     * @template TValue
     *
     * @param  callable(int): TValue  $value
     * @return array<int, TValue>
     */
    public static function byServings(callable $value): array
    {
        $values = [];

        foreach (range(1, 12) as $servings) {
            $values[$servings] = $value($servings);
        }

        return $values;
    }

    /**
     * A decimal string as whole thousandths: "2.5" becomes 2500.
     */
    private static function thousandths(string $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return (int) $whole * 1000 + (int) str_pad(substr($fraction, 0, 3), 3, '0');
    }

    /**
     * Whole thousandths as the shortest decimal string: 2500 becomes "2.5".
     */
    private static function fromThousandths(int $thousandths): string
    {
        $fraction = rtrim(str_pad((string) ($thousandths % 1000), 3, '0', STR_PAD_LEFT), '0');

        return intdiv($thousandths, 1000).($fraction === '' ? '' : '.'.$fraction);
    }

    /**
     * Integer division rounded half up, for non-negative numbers.
     */
    private static function divideRounded(int $dividend, int $divisor): int
    {
        return intdiv($dividend * 2 + $divisor, $divisor * 2);
    }
}
