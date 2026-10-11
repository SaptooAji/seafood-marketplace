<?php

declare(strict_types=1);

use App\Enums\ShippingFeeType;
use App\Enums\ShippingMethodType;

it('keeps the stored values unchanged', function (string $enum, array $values) {
    /** @var class-string<BackedEnum> $enum */
    expect(array_map(fn (BackedEnum $case) => $case->value, $enum::cases()))->toBe($values);
})->with([
    'shipping method type' => [ShippingMethodType::class, ['own_delivery', 'pickup', 'ojol', 'expedition']],
    'shipping fee type' => [ShippingFeeType::class, ['flat', 'per_km', 'manual']],
]);

it('has a unique, non-empty label for every case', function (string $enum) {
    /** @var class-string<ShippingMethodType|ShippingFeeType> $enum */
    $labels = array_map(fn (ShippingMethodType|ShippingFeeType $case) => $case->label(), $enum::cases());

    expect($labels)->each->not->toBeEmpty()
        ->and(array_unique($labels))->toHaveCount(count($labels));
})->with([
    'shipping method type' => [ShippingMethodType::class],
    'shipping fee type' => [ShippingFeeType::class],
]);
