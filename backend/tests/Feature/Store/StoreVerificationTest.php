<?php

declare(strict_types=1);

use App\Actions\ReactivateStore;
use App\Actions\RejectStore;
use App\Actions\SuspendStore;
use App\Actions\VerifyStore;
use App\Enums\PlatformRole;
use App\Enums\StoreStatus;
use App\Exceptions\InvalidStoreStatusTransition;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleSeeder;

describe('VerifyStore', function () {
    it('activates a pending store and records when', function () {
        $this->travelTo(now()->setDateTime(2026, 10, 11, 9, 30));
        $store = Store::factory()->pending()->create();

        app(VerifyStore::class)->handle($store);

        expect($store->fresh())
            ->status->toBe(StoreStatus::Active)
            ->verified_at->toDateTimeString()->toBe('2026-10-11 09:30:00')
            ->rejection_reason->toBeNull();
    });

    it('rejects verifying a store that is already active', function () {
        $store = Store::factory()->active()->create(['verified_at' => '2026-01-01 08:00:00']);

        expect(fn () => app(VerifyStore::class)->handle($store))
            ->toThrow(InvalidStoreStatusTransition::class);

        expect($store->fresh())
            ->status->toBe(StoreStatus::Active)
            ->verified_at->toDateTimeString()->toBe('2026-01-01 08:00:00');
    });
});

describe('RejectStore', function () {
    it('rejects a pending store with the reason', function () {
        $store = Store::factory()->pending()->create();

        app(RejectStore::class)->handle($store, 'Foto lokasi tidak sesuai');

        expect($store->fresh())
            ->status->toBe(StoreStatus::Rejected)
            ->verified_at->toBeNull()
            ->rejection_reason->toBe('Foto lokasi tidak sesuai');
    });

    it('trims the reason', function () {
        $store = Store::factory()->pending()->create();

        app(RejectStore::class)->handle($store, '  Rekening tidak valid  ');

        expect($store->fresh()->rejection_reason)->toBe('Rekening tidak valid');
    });

    it('stores a default reason when none is given', function (?string $reason) {
        $store = Store::factory()->pending()->create();

        app(RejectStore::class)->handle($store, $reason);

        expect($store->fresh()->rejection_reason)->toBe('Tidak diketahui');
    })->with([
        'null' => [null],
        'empty string' => [''],
        'only spaces' => ['   '],
    ]);

    it('rejects rejecting a store that is not pending', function () {
        $store = Store::factory()->active()->create();

        expect(fn () => app(RejectStore::class)->handle($store, 'Terlambat'))
            ->toThrow(InvalidStoreStatusTransition::class);

        expect($store->fresh())
            ->status->toBe(StoreStatus::Active)
            ->rejection_reason->toBeNull();
    });
});

describe('SuspendStore and ReactivateStore', function () {
    it('suspends an active store and keeps verified_at', function () {
        $store = Store::factory()->active()->create(['verified_at' => '2026-01-01 08:00:00']);

        app(SuspendStore::class)->handle($store);

        expect($store->fresh())
            ->status->toBe(StoreStatus::Suspended)
            ->verified_at->toDateTimeString()->toBe('2026-01-01 08:00:00')
            ->rejection_reason->toBeNull();
    });

    it('reactivates a suspended store with its original verified_at', function () {
        $this->travelTo(now()->setDateTime(2026, 10, 11, 9, 30));
        $store = Store::factory()->suspended()->create(['verified_at' => '2026-01-01 08:00:00']);

        app(ReactivateStore::class)->handle($store);

        expect($store->fresh())
            ->status->toBe(StoreStatus::Active)
            ->verified_at->toDateTimeString()->toBe('2026-01-01 08:00:00')
            ->rejection_reason->toBeNull();
    });

    it('rejects suspending a pending store', function () {
        $store = Store::factory()->pending()->create();

        expect(fn () => app(SuspendStore::class)->handle($store))
            ->toThrow(InvalidStoreStatusTransition::class);

        expect($store->fresh()->status)->toBe(StoreStatus::Pending);
    });

    it('rejects reactivating a rejected store', function () {
        $store = Store::factory()->rejected()->create();

        expect(fn () => app(ReactivateStore::class)->handle($store))
            ->toThrow(InvalidStoreStatusTransition::class);

        expect($store->fresh()->status)->toBe(StoreStatus::Rejected);
    });
});

describe('StorePolicy::changeStatus', function () {
    beforeEach(function () {
        $this->seed(RoleSeeder::class);
    });

    it('allows platform admins only', function (?PlatformRole $role, bool $allowed) {
        $user = User::factory()->create();

        if ($role !== null) {
            $user->assignRole($role);
        }

        expect($user->can('changeStatus', Store::factory()->create()))->toBe($allowed);
    })->with([
        'super admin' => [PlatformRole::SuperAdmin, true],
        'admin' => [PlatformRole::Admin, true],
        'user without a platform role' => [null, false],
    ]);

    it('does not let the owner change the status of their own store', function () {
        $owner = User::factory()->create();
        $store = Store::factory()->withOwner($owner)->create();

        expect($owner->can('changeStatus', $store))->toBeFalse();
    });
});
