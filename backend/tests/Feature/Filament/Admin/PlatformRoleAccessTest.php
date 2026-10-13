<?php

declare(strict_types=1);

use App\Enums\PlatformRole;
use App\Filament\Admin\Resources\Activities\ActivityResource;
use App\Filament\Admin\Resources\Categories\CategoryResource;
use App\Filament\Admin\Resources\Products\ProductResource;
use App\Filament\Admin\Resources\Stores\StoreResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

/*
 * AC-8: Super Admin and Admin are told apart in the Policy, not only in the
 * menu. PRD: only the Super Admin manages roles and reads the activity log;
 * both moderate stores, products, and categories.
 */

/**
 * Admin panel resources only the Super Admin may use.
 *
 * @var list<class-string>
 */
const SUPER_ADMIN_ONLY_RESOURCES = [
    ActivityResource::class,
    RoleResource::class,
];

/**
 * Admin panel resources both platform roles may use.
 *
 * @var list<class-string>
 */
const SHARED_ADMIN_RESOURCES = [
    CategoryResource::class,
    ProductResource::class,
    StoreResource::class,
];

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('admin');
});

function platformUser(PlatformRole $role): User
{
    return tap(User::factory()->create(), fn (User $user) => $user->assignRole($role));
}

it('sorts every admin panel resource into super admin only or shared', function () {
    $resources = array_values(Filament::getPanel('admin')->getResources());

    expect($resources)->toEqualCanonicalizing([...SUPER_ADMIN_ONLY_RESOURCES, ...SHARED_ADMIN_RESOURCES]);
});

describe('super admin only', function () {
    it('denies the admin and allows the super admin in the policy', function (string $ability, Closure $arguments) {
        expect(Gate::forUser(platformUser(PlatformRole::Admin))->allows($ability, $arguments()))->toBeFalse()
            ->and(Gate::forUser(platformUser(PlatformRole::SuperAdmin))->allows($ability, $arguments()))->toBeTrue();
    })->with([
        'list roles' => ['viewAny', fn () => [Role::class]],
        'view a role' => ['view', fn () => [Role::findByName(PlatformRole::Admin->value)]],
        'create a role' => ['create', fn () => [Role::class]],
        'update a role' => ['update', fn () => [Role::findByName(PlatformRole::Admin->value)]],
        'delete a role' => ['delete', fn () => [Role::findByName(PlatformRole::Admin->value)]],
        'list activity' => ['viewAny', fn () => [Activity::class]],
        'view an activity' => ['view', fn () => [Activity::query()->create(['description' => 'created'])]],
    ]);

    it('forbids the role pages to the admin but not to the super admin', function (Closure $url) {
        $this->actingAs(platformUser(PlatformRole::Admin))->get($url())->assertForbidden();
        $this->actingAs(platformUser(PlatformRole::SuperAdmin))->get($url())->assertOk();
    })->with([
        'list' => [fn () => RoleResource::getUrl('index')],
        'create' => [fn () => RoleResource::getUrl('create')],
        'view' => [fn () => RoleResource::getUrl('view', ['record' => Role::findByName(PlatformRole::Admin->value)])],
        'edit' => [fn () => RoleResource::getUrl('edit', ['record' => Role::findByName(PlatformRole::Admin->value)])],
    ]);
});

describe('shared moderation', function () {
    it('allows both platform roles', function (PlatformRole $role, string $ability, Closure $arguments) {
        expect(Gate::forUser(platformUser($role))->allows($ability, $arguments()))->toBeTrue();
    })->with([
        'admin' => [PlatformRole::Admin],
        'super admin' => [PlatformRole::SuperAdmin],
    ])->with([
        'change a store status' => ['changeStatus', fn () => [Store::factory()->create()]],
        'block a product' => ['moderate', fn () => [Product::factory()->create()]],
        'create a category' => ['create', fn () => [Category::class]],
        'update a category' => ['update', fn () => [Category::factory()->create()]],
    ]);
});
