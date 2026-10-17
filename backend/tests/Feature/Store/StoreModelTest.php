<?php

declare(strict_types=1);

use App\Enums\StoreRole;
use App\Enums\StoreStatus;
use App\Models\Store;
use App\Models\StoreMember;
use App\Models\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

it('creates a pending, unverified store by default', function () {
    $store = Store::factory()->create();

    expect($store->fresh())
        ->status->toBe(StoreStatus::Pending)
        ->verified_at->toBeNull()
        ->rejection_reason->toBeNull()
        ->parent_store_id->toBeNull();
});

it('sets the status of each factory state', function (string $state, StoreStatus $status) {
    expect(Store::factory()->{$state}()->create()->fresh()->status)->toBe($status);
})->with([
    'pending' => ['pending', StoreStatus::Pending],
    'active' => ['active', StoreStatus::Active],
    'rejected' => ['rejected', StoreStatus::Rejected],
    'suspended' => ['suspended', StoreStatus::Suspended],
]);

it('fills verified_at for an active store', function () {
    expect(Store::factory()->active()->create()->fresh()->verified_at)->not->toBeNull();
});

it('fills rejection_reason only for a rejected store', function (string $state, bool $hasReason) {
    $reason = Store::factory()->{$state}()->create()->fresh()->rejection_reason;

    $hasReason ? expect($reason)->not->toBeEmpty() : expect($reason)->toBeNull();
})->with([
    'rejected' => ['rejected', true],
    'pending' => ['pending', false],
    'active' => ['active', false],
    'suspended' => ['suspended', false],
]);

it('adds the given user as the store owner', function () {
    $user = User::factory()->create();

    $store = Store::factory()->withOwner($user)->create();

    $member = StoreMember::query()->sole();

    expect($member->role)->toBe(StoreRole::Owner)
        ->and($member->store->is($store))->toBeTrue()
        ->and($member->user->is($user))->toBeTrue()
        ->and($store->members)->toHaveCount(1)
        ->and($user->stores->sole()->is($store))->toBeTrue()
        ->and($store->users->first()->pivot->role)->toBe('owner');
});

it('creates an owner when none is given', function () {
    $store = Store::factory()->withOwner()->create();

    expect($store->users)->toHaveCount(1)
        ->and(StoreMember::query()->sole()->role)->toBe(StoreRole::Owner);
});

it('rejects two stores with the same slug', function () {
    Store::factory()->create(['slug' => 'nelayan-jaya']);

    expect(fn () => Store::factory()->create(['slug' => 'nelayan-jaya']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('rejects adding the same user to a store twice', function () {
    $store = Store::factory()->create();
    $user = User::factory()->create();
    $store->users()->attach($user, ['role' => StoreRole::Staff->value]);

    expect(fn () => $store->users()->attach($user, ['role' => StoreRole::Staff->value]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('removes the members when the store is deleted', function () {
    $store = Store::factory()->withOwner()->create();

    $store->delete();

    expect(StoreMember::query()->count())->toBe(0)
        ->and(User::query()->count())->toBe(1);
});

it('removes the memberships when the user is deleted', function () {
    $user = User::factory()->create();
    $store = Store::factory()->withOwner($user)->create();

    $user->delete();

    expect(StoreMember::query()->count())->toBe(0)
        ->and($store->fresh())->not->toBeNull();
});

it('keeps the full precision of the coordinates', function () {
    $store = Store::factory()->create(['lat' => '-6.2087634', 'lng' => '106.8455990']);

    $row = DB::table('stores')->where('id', $store->id)->first();

    expect((string) $row->lat)->toBe('-6.2087634')
        ->and((string) $row->lng)->toBe('106.8455990');
});

it('encrypts the bank account number at rest', function () {
    $store = Store::factory()->create(['bank_account_no' => '1234567890']);

    $stored = DB::table('stores')->where('id', $store->id)->value('bank_account_no');

    expect($stored)->not->toBe('1234567890')
        ->and($stored)->not->toContain('1234567890')
        ->and($store->fresh()->bank_account_no)->toBe('1234567890');
});

describe('demo stores (#184)', function () {
    it('is a real store by default', function () {
        $store = Store::factory()->create();

        expect($store->fresh()->is_demo)->toBeFalse();
    });

    it('marks a store as a demo store with the factory state', function () {
        expect(Store::factory()->demo()->create()->fresh()->is_demo)->toBeTrue();
    });

    it('defaults to false in the database', function () {
        $store = Store::factory()->create();

        expect(DB::table('stores')->where('id', $store->id)->value('is_demo'))->toBeFalse();
    });

    it('separates real stores from demo stores', function () {
        $real = Store::factory()->create();
        $demo = Store::factory()->demo()->create();

        expect(Store::query()->real()->pluck('id')->all())->toBe([$real->id])
            ->and(Store::query()->demo()->pluck('id')->all())->toBe([$demo->id]);
    });

    it('cannot be mass assigned', function () {
        $store = Store::factory()->create();

        expect(fn () => $store->fill(['is_demo' => true]))->toThrow(MassAssignmentException::class)
            ->and($store->fresh()->is_demo)->toBeFalse();
    });
});
