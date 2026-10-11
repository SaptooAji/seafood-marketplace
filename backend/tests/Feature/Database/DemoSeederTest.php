<?php

declare(strict_types=1);

use App\Actions\SaveShippingMethod;
use App\Data\ShippingMethodData;
use App\Enums\PlatformRole;
use App\Enums\StoreRole;
use App\Enums\StoreStatus;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Store;
use App\Models\StoreMember;
use App\Models\StoreShippingMethod;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Row counts of everything the seeders create, to compare runs.
 *
 * @return array<string, int>
 */
function seededCounts(): array
{
    return [
        'roles' => Role::query()->count(),
        'users' => User::query()->count(),
        'categories' => Category::query()->count(),
        'attributes' => CategoryAttribute::query()->count(),
        'stores' => Store::query()->count(),
        'members' => StoreMember::query()->count(),
        'shipping methods' => StoreShippingMethod::query()->count(),
    ];
}

it('seeds without errors', function () {
    $this->seed();

    expect(seededCounts())->each->toBeGreaterThan(0);
});

it('creates one super admin and one admin with the demo password', function () {
    config(['demo.password' => 'rahasia-demo']);

    $this->seed();

    $superAdmins = User::role(PlatformRole::SuperAdmin)->get();
    $admins = User::role(PlatformRole::Admin)->get();

    expect(Role::query()->count())->toBe(2)
        ->and($superAdmins)->toHaveCount(1)
        ->and($admins)->toHaveCount(1)
        ->and(Hash::check('rahasia-demo', $superAdmins->sole()->password))->toBeTrue()
        ->and(Hash::check('rahasia-demo', $admins->sole()->password))->toBeTrue();
});

it('creates the seafood category tree', function () {
    $this->seed();

    $children = fn (string $slug): array => Category::query()->where('slug', $slug)->sole()
        ->children()->pluck('slug')->sort()->values()->all();

    expect($children('kepiting'))->toBe(['bakau', 'rajungan', 'soka'])
        ->and($children('cumi'))->toBe(['cumi-beku', 'cumi-segar'])
        ->and(Category::query()->whereIn('slug', ['tiram', 'ikan'])->whereNull('parent_id')->count())->toBe(2);
});

it('gives every root category a required attribute and a quality standard', function () {
    $this->seed();

    $roots = Category::query()->whereNull('parent_id')->get();

    expect($roots)->toHaveCount(4);

    foreach ($roots as $root) {
        expect($root->quality_standard)->not->toBeEmpty()
            ->and($root->attributes()->where('is_required', true)->exists())->toBeTrue();
    }
});

it('creates the demo stores with owners and shipping methods', function () {
    $this->seed();

    expect(Store::query()->orderBy('name')->get()->map(fn (Store $store) => [$store->name, $store->status])->all())
        ->toBe([
            ['Cumi Beku Bu Sri', StoreStatus::Active],
            ['Tiram Segar Mas Joko', StoreStatus::Pending],
            ['UD Kepiting Pak Budi', StoreStatus::Active],
        ]);

    foreach (Store::query()->get() as $store) {
        expect(StoreMember::query()->where('store_id', $store->id)->where('role', StoreRole::Owner->value)->count())->toBe(1)
            ->and($store->shippingMethods()->count())->toBeGreaterThan(0);
    }
});

it('creates active stores with a verification date', function () {
    $this->seed();

    expect(Store::query()->where('status', StoreStatus::Active->value)->whereNull('verified_at')->count())->toBe(0);
});

it('creates shipping methods that pass the SaveShippingMethod rules', function () {
    $this->seed();

    foreach (StoreShippingMethod::query()->with('store')->get() as $method) {
        app(SaveShippingMethod::class)->handle($method->store, new ShippingMethodData(
            type: $method->type,
            feeType: $method->fee_type,
            maxRadiusKm: $method->max_radius_km,
            flatFee: $method->flat_fee,
            feePerKm: $method->fee_per_km,
            minFee: $method->min_fee,
            isActive: $method->is_active,
        ));
    }

    expect(true)->toBeTrue();
});

it('can run twice without duplicating anything', function () {
    $this->seed();
    $first = seededCounts();

    $this->seed();

    expect(seededCounts())->toBe($first);
});

it('skips demo users and stores in production', function () {
    $this->app->detectEnvironment(fn () => 'production');

    // db:seed asks for confirmation in production; --force answers it.
    $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

    expect(User::query()->count())->toBe(0)
        ->and(Store::query()->count())->toBe(0)
        ->and(Role::query()->count())->toBe(2);
});
