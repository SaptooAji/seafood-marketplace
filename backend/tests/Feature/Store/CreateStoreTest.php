<?php

declare(strict_types=1);

use App\Actions\CreateStore;
use App\Data\StoreData;
use App\Enums\StoreRole;
use App\Enums\StoreStatus;
use App\Exceptions\UserAlreadyOwnsStore;
use App\Models\Store;
use App\Models\StoreMember;
use App\Models\User;
use Illuminate\Database\QueryException;

function createStore(User $owner, ?StoreData $data = null): Store
{
    return app(CreateStore::class)->handle($owner, $data ?? storeData());
}

it('creates a pending store owned by the user', function () {
    $owner = User::factory()->create();

    $store = createStore($owner);

    expect($store->fresh())
        ->status->toBe(StoreStatus::Pending)
        ->verified_at->toBeNull()
        ->rejection_reason->toBeNull()
        ->name->toBe('Kepiting Pak Budi')
        ->phone->toBe('081234567890')
        ->city->toBe('Jakarta Utara')
        ->bank_account_no->toBe('1234567890');

    $member = StoreMember::query()->sole();

    expect($member->user_id)->toBe($owner->id)
        ->and($member->store_id)->toBe($store->id)
        ->and($member->role)->toBe(StoreRole::Owner);
});

it('builds the slug from the name', function (string $name, string $slug) {
    expect(createStore(User::factory()->create(), storeData(['name' => $name]))->slug)->toBe($slug);
})->with([
    'plain name' => ['Kepiting Pak Budi', 'kepiting-pak-budi'],
    'special characters' => ['Kepiting & Cumi Bu Sri', 'kepiting-cumi-bu-sri'],
]);

it('adds a number to the slug when the name is already taken', function () {
    $slugs = collect(range(1, 3))
        ->map(fn () => createStore(User::factory()->create())->slug);

    expect($slugs->all())->toBe(['kepiting-pak-budi', 'kepiting-pak-budi-2', 'kepiting-pak-budi-3']);
});

it('rejects a user who already owns a store', function () {
    $owner = User::factory()->create();
    Store::factory()->withOwner($owner)->create();

    expect(fn () => createStore($owner))->toThrow(UserAlreadyOwnsStore::class);

    expect(Store::query()->count())->toBe(1);
});

it('lets a staff member of another store create their own store', function () {
    $staff = User::factory()->create();
    Store::factory()->create()->users()->attach($staff, ['role' => StoreRole::Staff->value]);

    $store = createStore($staff);

    expect($staff->stores()->wherePivot('role', StoreRole::Owner->value)->sole()->is($store))->toBeTrue();
});

it('does not save the store when adding the owner fails', function () {
    // A user id with no row breaks the store_members foreign key after the store insert.
    $ghost = User::factory()->make(['id' => 999_999]);

    expect(fn () => createStore($ghost))->toThrow(QueryException::class);

    expect(Store::query()->count())->toBe(0);
});

it('creates a real store, never a demo store', function () {
    expect(createStore(User::factory()->create())->fresh()->is_demo)->toBeFalse();
});
