<?php

declare(strict_types=1);

use App\Domain\Store\Enums\StoreRole;
use App\Domain\Store\Enums\StoreStatus;

it('keeps the stored values unchanged', function (string $enum, array $values) {
    /** @var class-string<BackedEnum> $enum */
    expect(array_map(fn (BackedEnum $case) => $case->value, $enum::cases()))->toBe($values);
})->with([
    'store status' => [StoreStatus::class, ['pending', 'active', 'rejected', 'suspended']],
    'store role' => [StoreRole::class, ['owner', 'staff']],
]);

it('returns null for an unknown store status', function () {
    expect(StoreStatus::tryFrom('deleted'))->toBeNull();
});

it('has a unique, non-empty label for every case', function (string $enum) {
    /** @var class-string<StoreStatus|StoreRole> $enum */
    $labels = array_map(fn (StoreStatus|StoreRole $case) => $case->label(), $enum::cases());

    expect($labels)->each->not->toBeEmpty()
        ->and(array_unique($labels))->toHaveCount(count($labels));
})->with([
    'store status' => [StoreStatus::class],
    'store role' => [StoreRole::class],
]);
