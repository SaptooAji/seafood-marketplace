<?php

declare(strict_types=1);

use App\Domain\Account\Enums\PlatformRole;

it('keeps the stored values unchanged', function () {
    expect(array_map(fn (PlatformRole $role) => $role->value, PlatformRole::cases()))
        ->toBe(['super_admin', 'admin']);
});

it('has a unique, non-empty label for every case', function () {
    $labels = array_map(fn (PlatformRole $role) => $role->label(), PlatformRole::cases());

    expect($labels)->each->not->toBeEmpty()
        ->and(array_unique($labels))->toHaveCount(count($labels));
});

it('matches the super admin role name configured in Shield', function () {
    expect(PlatformRole::SuperAdmin->value)->toBe('super_admin');
});
