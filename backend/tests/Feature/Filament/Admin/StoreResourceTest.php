<?php

declare(strict_types=1);

use App\Enums\PlatformRole;
use App\Enums\StoreStatus;
use App\Filament\Admin\Resources\Stores\Pages\ListStores;
use App\Filament\Admin\Resources\Stores\Pages\ViewStore;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

const STORE_STATUS_ACTIONS = ['verify', 'reject', 'suspend', 'reactivate'];

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('admin');

    $this->admin = User::factory()->create();
    $this->admin->assignRole(PlatformRole::Admin);
    $this->actingAs($this->admin);
});

describe('list page', function () {
    it('opens for platform roles', function (PlatformRole $role) {
        $user = User::factory()->create();
        $user->assignRole($role);

        $this->actingAs($user)->get('/admin/stores')->assertOk();
    })->with([
        'admin' => [PlatformRole::Admin],
        'super admin' => [PlatformRole::SuperAdmin],
    ]);

    it('is forbidden to users without a platform role', function () {
        $owner = User::factory()->create();
        Store::factory()->withOwner($owner)->create();

        $this->actingAs($owner)->get('/admin/stores')->assertForbidden();
    });

    it('lists the stores', function () {
        $stores = Store::factory()->count(3)->create();

        Livewire::test(ListStores::class)->assertCanSeeTableRecords($stores);
    });

    it('filters by status', function () {
        $pending = Store::factory()->pending()->create();
        $active = Store::factory()->active()->create();

        Livewire::test(ListStores::class)
            ->filterTable('status', StoreStatus::Pending->value)
            ->assertCanSeeTableRecords([$pending])
            ->assertCanNotSeeTableRecords([$active]);
    });

    it('puts pending stores first even when they are the oldest', function () {
        $pending = Store::factory()->pending()->create(['created_at' => now()->subDays(3)]);
        $active = Store::factory()->active()->create(['created_at' => now()->subDay()]);
        $rejected = Store::factory()->rejected()->create(['created_at' => now()]);

        Livewire::test(ListStores::class)
            ->assertCanSeeTableRecords([$pending, $rejected, $active], inOrder: true);
    });

    it('shows the status label', function () {
        Store::factory()->pending()->create();

        Livewire::test(ListStores::class)->assertSee(StoreStatus::Pending->label());
    });

    it('shows which stores are demo stores', function () {
        Store::factory()->demo()->create();

        Livewire::test(ListStores::class)
            ->assertTableColumnExists('is_demo')
            ->assertSee('Toko contoh');
    });

    it('filters the demo stores', function () {
        $real = Store::factory()->create();
        $demo = Store::factory()->demo()->create();

        Livewire::test(ListStores::class)
            ->filterTable('is_demo', true)
            ->assertCanSeeTableRecords([$demo])
            ->assertCanNotSeeTableRecords([$real])
            ->filterTable('is_demo', false)
            ->assertCanSeeTableRecords([$real])
            ->assertCanNotSeeTableRecords([$demo]);
    });
});

describe('table actions', function () {
    it('verifies a pending store', function () {
        $store = Store::factory()->pending()->create();

        Livewire::test(ListStores::class)
            ->callAction(TestAction::make('verify')->table($store))
            ->assertHasNoFormErrors();

        expect($store->fresh()->status)->toBe(StoreStatus::Active);
    });

    it('rejects a pending store with the given reason', function () {
        $store = Store::factory()->pending()->create();

        Livewire::test(ListStores::class)
            ->callAction(TestAction::make('reject')->table($store), data: ['reason' => 'Alamat tidak lengkap'])
            ->assertHasNoFormErrors();

        expect($store->fresh())
            ->status->toBe(StoreStatus::Rejected)
            ->rejection_reason->toBe('Alamat tidak lengkap');
    });

    it('rejects with the default reason when none is given', function () {
        $store = Store::factory()->pending()->create();

        Livewire::test(ListStores::class)
            ->callAction(TestAction::make('reject')->table($store))
            ->assertHasNoFormErrors();

        expect($store->fresh()->rejection_reason)->toBe('Tidak diketahui');
    });

    it('does not accept a reason longer than 500 characters', function () {
        $store = Store::factory()->pending()->create();

        Livewire::test(ListStores::class)
            ->callAction(TestAction::make('reject')->table($store), data: ['reason' => str_repeat('a', 501)])
            ->assertHasFormErrors(['reason' => 'max']);

        expect($store->fresh())
            ->status->toBe(StoreStatus::Pending)
            ->rejection_reason->toBeNull();
    });

    it('suspends an active store and reactivates it', function () {
        $store = Store::factory()->active()->create();

        Livewire::test(ListStores::class)
            ->callAction(TestAction::make('suspend')->table($store));

        expect($store->fresh()->status)->toBe(StoreStatus::Suspended);

        Livewire::test(ListStores::class)
            ->callAction(TestAction::make('reactivate')->table($store));

        expect($store->fresh()->status)->toBe(StoreStatus::Active);
    });

    it('shows only the actions the store status allows', function (string $state, array $visible) {
        $store = Store::factory()->{$state}()->create();
        $page = Livewire::test(ListStores::class);

        foreach (STORE_STATUS_ACTIONS as $action) {
            in_array($action, $visible, true)
                ? $page->assertActionVisible(TestAction::make($action)->table($store))
                : $page->assertActionHidden(TestAction::make($action)->table($store));
        }
    })->with([
        'pending: verify or reject' => ['pending', ['verify', 'reject']],
        'active: suspend' => ['active', ['suspend']],
        'suspended: reactivate' => ['suspended', ['reactivate']],
        'rejected: nothing' => ['rejected', []],
    ]);
});

describe('view page', function () {
    it('shows the store details with a Google Maps link', function () {
        $store = Store::factory()->active()->create([
            'phone' => '081234567890',
            'address' => 'Jl. Pelabuhan No. 5, Muara Angke',
            'lat' => '-6.2087634',
            'lng' => '106.8455990',
            'bank_name' => 'BCA',
            'bank_account_no' => '1234567890',
        ]);

        Livewire::test(ViewStore::class, ['record' => $store->getRouteKey()])
            ->assertOk()
            ->assertSee($store->name)
            ->assertSee('081234567890')
            ->assertSee('Jl. Pelabuhan No. 5, Muara Angke')
            ->assertSee('1234567890')
            ->assertSee('https://www.google.com/maps/search/?api=1&query=-6.2087634,106.8455990');
    });

    it('shows the rejection reason of a rejected store and no actions', function () {
        $store = Store::factory()->rejected()->create(['rejection_reason' => 'Alamat tidak lengkap']);
        $page = Livewire::test(ViewStore::class, ['record' => $store->getRouteKey()]);

        $page->assertSee('Alamat tidak lengkap');

        foreach (STORE_STATUS_ACTIONS as $action) {
            $page->assertActionHidden($action);
        }
    });

    it('verifies a pending store from the view page', function () {
        $store = Store::factory()->pending()->create();

        Livewire::test(ViewStore::class, ['record' => $store->getRouteKey()])
            ->callAction('verify');

        expect($store->fresh()->status)->toBe(StoreStatus::Active);
    });
});

it('has no create or edit page', function () {
    expect(Route::has('filament.admin.resources.stores.index'))->toBeTrue()
        ->and(Route::has('filament.admin.resources.stores.view'))->toBeTrue()
        ->and(Route::has('filament.admin.resources.stores.create'))->toBeFalse()
        ->and(Route::has('filament.admin.resources.stores.edit'))->toBeFalse();
});
