<?php

declare(strict_types=1);

use App\Enums\PlatformRole;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Role;

it('creates every platform role on the web guard', function () {
    $this->seed(RoleSeeder::class);

    expect(Role::query()->orderBy('name')->pluck('name')->all())
        ->toBe(['admin', 'super_admin'])
        ->and(Role::query()->pluck('guard_name')->unique()->all())->toBe(['web']);
});

it('can run more than once without duplicating roles', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(RoleSeeder::class);

    expect(Role::query()->count())->toBe(count(PlatformRole::cases()));
});
