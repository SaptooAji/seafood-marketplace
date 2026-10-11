<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('lets platform roles into the admin panel', function (string $role) {
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)->get('/admin')->assertOk();
})->with([
    'super admin' => ['super_admin'],
    'admin' => ['admin'],
]);

it('forbids the admin panel to users without a platform role', function () {
    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();
});

it('forbids the seller panel to platform roles without a store', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');

    $this->actingAs($user)->get('/seller')->assertForbidden();
});
