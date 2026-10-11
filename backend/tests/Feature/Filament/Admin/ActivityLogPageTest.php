<?php

declare(strict_types=1);

use App\Enums\PlatformRole;
use App\Filament\Admin\Resources\Activities\Pages\ListActivities;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

const ACTIVITY_LOG_URL = '/admin/activities';

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Filament::setCurrentPanel('admin');
});

function userWithRole(?PlatformRole $role): User
{
    $user = User::factory()->create(['name' => 'Rina Admin']);

    if ($role !== null) {
        $user->assignRole($role);
    }

    return $user;
}

it('shows the latest activity to the super admin', function () {
    $superAdmin = userWithRole(PlatformRole::SuperAdmin);
    $store = Store::factory()->create(['name' => 'Kepiting Pak Budi']);
    $this->actingAs($superAdmin);
    $store->update(['name' => 'UD Kepiting Pak Budi']);
    $latest = Activity::query()->latest('id')->first();

    $this->get(ACTIVITY_LOG_URL)->assertOk();

    Livewire::test(ListActivities::class)
        ->assertCanSeeTableRecords([$latest])
        ->assertSee('Rina Admin')
        ->assertSee('UD Kepiting Pak Budi');
});

it('lists the newest activity first', function () {
    $this->actingAs(userWithRole(PlatformRole::SuperAdmin));
    $store = Store::factory()->create();
    $store->update(['name' => 'Nama Kedua']);
    $activities = Activity::query()->orderByDesc('id')->get();

    Livewire::test(ListActivities::class)->assertCanSeeTableRecords($activities, inOrder: true);
});

it('is forbidden to everyone but the super admin', function (?PlatformRole $role) {
    $this->actingAs(userWithRole($role))->get(ACTIVITY_LOG_URL)->assertForbidden();
})->with([
    'admin' => [PlatformRole::Admin],
    'user without a platform role' => [null],
]);

it('is read only', function () {
    expect(Route::has('filament.admin.resources.activities.index'))->toBeTrue()
        ->and(Route::has('filament.admin.resources.activities.create'))->toBeFalse()
        ->and(Route::has('filament.admin.resources.activities.edit'))->toBeFalse();
});
