<?php

declare(strict_types=1);

use App\Enums\PlatformRole;
use App\Enums\StoreRole;
use App\Enums\StoreStatus;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;

/**
 * A store in the given state with the user as a member in the given role.
 */
function sellerStore(User $user, string $state = 'active', StoreRole $role = StoreRole::Owner): Store
{
    $store = Store::factory()->{$state}()->create();
    $store->users()->attach($user, ['role' => $role->value]);

    return $store;
}

it('lets members of an active store into its panel', function (StoreRole $role) {
    $user = User::factory()->create();
    $store = sellerStore($user, role: $role);

    $this->actingAs($user)->get("/seller/{$store->slug}")->assertOk();
})->with([
    'owner' => [StoreRole::Owner],
    'staff' => [StoreRole::Staff],
]);

it('sends a member from /seller to their store', function () {
    $user = User::factory()->create();
    $store = sellerStore($user);

    $this->actingAs($user)->get('/seller')->assertRedirect("/seller/{$store->slug}");
});

it('lists only the active stores of the user as tenants', function () {
    $user = User::factory()->create();
    $first = sellerStore($user);
    $second = sellerStore($user, role: StoreRole::Staff);
    sellerStore($user, 'pending');
    sellerStore($user, 'suspended');

    $tenants = $user->getTenants(Filament::getPanel('seller'));

    expect($tenants->modelKeys())->toEqualCanonicalizing([$first->id, $second->id]);
});

it('forbids the panel to owners whose store is not active', function (string $state) {
    $user = User::factory()->create();
    sellerStore($user, $state);

    $this->actingAs($user)->get('/seller')->assertForbidden();
})->with(['pending', 'rejected', 'suspended']);

it('forbids the panel to users without a store', function () {
    $this->actingAs(User::factory()->create())->get('/seller')->assertForbidden();
});

it('forbids the panel to platform admins without a store', function () {
    $this->seed(RoleSeeder::class);
    $admin = User::factory()->create();
    $admin->assignRole(PlatformRole::SuperAdmin);

    $this->actingAs($admin)->get('/seller')->assertForbidden();
});

it('does not open another store’s panel', function (string $otherState) {
    $user = User::factory()->create();
    sellerStore($user);
    $other = Store::factory()->{$otherState}()->create();

    $status = $this->actingAs($user)->get("/seller/{$other->slug}")->status();

    expect($status)->toBeIn([403, 404]);
})->with([
    'active store of someone else' => ['active'],
    'pending store of someone else' => ['pending'],
]);

it('does not open the user’s own store that is not active yet', function () {
    $user = User::factory()->create();
    sellerStore($user);
    $pending = sellerStore($user, 'pending');

    $status = $this->actingAs($user)->get("/seller/{$pending->slug}")->status();

    expect($status)->toBeIn([403, 404]);
});

it('locks the owner out on the next request after the store is suspended', function () {
    $user = User::factory()->create();
    $store = sellerStore($user);
    $this->actingAs($user)->get("/seller/{$store->slug}")->assertOk();

    $store->forceFill(['status' => StoreStatus::Suspended])->save();

    $this->get("/seller/{$store->slug}")->assertForbidden();
});

it('has no tenant registration page', function () {
    expect(collect(Route::getRoutes()->getRoutesByName())->keys()->filter(
        fn (string $name) => str_starts_with($name, 'filament.seller.tenant.registration'),
    ))->toBeEmpty();
});
