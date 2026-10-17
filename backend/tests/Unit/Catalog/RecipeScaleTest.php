<?php

declare(strict_types=1);

use App\Data\Recipe;

/*
 * Scaling a recipe amount to another number of servings (#189), rounded the
 * way a cook measures: whole pieces, half spoons, 10 ml, two decimals for
 * weights. Amounts are decimal strings in and out, never floats (VAL-2).
 */

it('rounds countable pieces to whole numbers, at least one', function (string $unit, string $amount, int $to, string $expected) {
    expect(Recipe::scaleAmount($amount, $unit, 4, $to))->toBe($expected);
})->with([
    'siung for 1' => ['siung', '8', 1, '2'],
    'siung for 5' => ['siung', '8', 5, '10'],
    'siung for 12' => ['siung', '8', 12, '24'],
    'siung rounds down' => ['siung', '5', 5, '6'],
    'buah rounds up' => ['buah', '6', 5, '8'],
    'lembar for 1' => ['lembar', '2', 1, '1'],
    'batang never below one' => ['batang', '1', 1, '1'],
    'butir never below one' => ['butir', '1', 1, '1'],
    'butir for 12' => ['butir', '1', 12, '3'],
]);

it('rounds spoons and centimetres to halves, at least a half', function (string $unit, string $amount, int $to, string $expected) {
    expect(Recipe::scaleAmount($amount, $unit, 4, $to))->toBe($expected);
})->with([
    'sdm for 1' => ['sdm', '3', 1, '1'],
    'sdm for 5' => ['sdm', '3', 5, '4'],
    'sdm for 12' => ['sdm', '3', 12, '9'],
    'small sdm stays a half' => ['sdm', '1', 1, '0.5'],
    'sdm to a half' => ['sdm', '1', 5, '1.5'],
    'cm for 5' => ['cm', '3', 5, '4'],
]);

it('rounds millilitres to tens', function (int $to, string $expected) {
    expect(Recipe::scaleAmount('250', 'ml', 4, $to))->toBe($expected);
})->with([
    'for 1' => [1, '60'],
    'for 5' => [5, '310'],
    'for 12' => [12, '750'],
]);

it('keeps two decimals for weights and other units', function (string $unit, string $amount, int $to, string $expected) {
    expect(Recipe::scaleAmount($amount, $unit, 4, $to))->toBe($expected);
})->with([
    'grams for 1' => ['g', '50', 1, '12.5'],
    'grams for 5' => ['g', '50', 5, '62.5'],
    'kg for 3' => ['kg', '1', 3, '0.75'],
    'litres for 5' => ['liter', '1', 5, '1.25'],
    'teaspoons for 1' => ['sdt', '1', 1, '0.25'],
]);

it('returns the amount unchanged for the original servings', function () {
    expect(Recipe::scaleAmount('2.5', 'sdm', 4, 4))->toBe('2.5');
});

it('shows an amount the way buyers read it', function (string $amount, string $expected) {
    expect(Recipe::formatAmount($amount))->toBe($expected);
})->with([
    'whole' => ['10', '10'],
    'half' => ['1.5', '1,5'],
    'two decimals' => ['0.75', '0,75'],
    'trailing zeros' => ['2.500', '2,5'],
]);

it('scales the seafood to buy and names it for the scaled amount', function (int $to, string $kg, string $label) {
    $recipe = new Recipe('a', 'Resep', 'Deskripsi', 'images/resep/a.jpg', 'kepiting', 45, 4, 'Sedang', 'acara',
        ['category' => 'bakau', 'kg' => '1.000', 'label' => '1 kg kepiting bakau'], [], ['Masak.'], [], false);

    expect($recipe->neededKg($to))->toBe($kg)
        ->and($recipe->neededLabel($to))->toBe($label);
})->with([
    'original' => [4, '1.000', '1 kg kepiting bakau'],
    'more' => [5, '1.250', '1,25 kg kepiting bakau'],
    'under a kilo in grams' => [2, '0.500', '500 g kepiting bakau'],
    'one serving' => [1, '0.250', '250 g kepiting bakau'],
]);

it('works out the price of the seafood for a number of servings in whole rupiah', function (int $pricePerKg, int $to, int $expected) {
    $recipe = new Recipe('a', 'Resep', 'Deskripsi', 'images/resep/a.jpg', 'kepiting', 45, 4, 'Sedang', 'acara',
        ['category' => 'bakau', 'kg' => '0.500', 'label' => '500 g kepiting bakau'], [], ['Masak.'], [], false);

    expect($recipe->seafoodCost($pricePerKg, $to))->toBe($expected);
})->with([
    'half a kilo' => [185_000, 4, 92_500],
    'scaled up' => [185_000, 5, 115_625],
    'rounded to the nearest rupiah' => [99_999, 3, 37_500],
]);
