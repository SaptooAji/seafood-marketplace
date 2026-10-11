<?php

declare(strict_types=1);

use App\Enums\AttributeType;
use App\Enums\ProductCondition;
use App\Enums\SalesMode;
use App\Enums\Unit;

it('keeps the stored values unchanged', function (string $enum, array $values) {
    /** @var class-string<BackedEnum> $enum */
    expect(array_map(fn (BackedEnum $case) => $case->value, $enum::cases()))->toBe($values);
})->with([
    'product condition' => [ProductCondition::class, ['live', 'fresh', 'frozen', 'marinated']],
    'sales mode' => [SalesMode::class, ['ready_stock', 'pre_order']],
    'unit' => [Unit::class, ['kg', 'piece', 'dozen', 'pack']],
    'attribute type' => [AttributeType::class, ['text', 'number', 'select', 'date']],
]);

it('resolves a case from its stored value', function () {
    expect(Unit::from('kg'))->toBe(Unit::Kg)
        ->and(SalesMode::from('pre_order'))->toBe(SalesMode::PreOrder);
});

it('returns null for an unknown product condition', function () {
    expect(ProductCondition::tryFrom('rotten'))->toBeNull();
});

it('has a unique, non-empty label for every case', function (string $enum) {
    /** @var class-string<ProductCondition|SalesMode|Unit|AttributeType> $enum */
    $labels = array_map(fn (ProductCondition|SalesMode|Unit|AttributeType $case) => $case->label(), $enum::cases());

    expect($labels)->each->not->toBeEmpty()
        ->and(array_unique($labels))->toHaveCount(count($labels));
})->with([
    'product condition' => [ProductCondition::class],
    'sales mode' => [SalesMode::class],
    'unit' => [Unit::class],
    'attribute type' => [AttributeType::class],
]);
