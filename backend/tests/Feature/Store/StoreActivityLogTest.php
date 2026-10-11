<?php

declare(strict_types=1);

use App\Actions\RejectStore;
use App\Actions\VerifyStore;
use App\Enums\PlatformRole;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Activitylog\Models\Activity;

/**
 * The `updated` activities recorded for a store, oldest first.
 *
 * @return Collection<int, Activity>
 */
function storeUpdates(Store $store): Collection
{
    return Activity::query()
        ->whereMorphedTo('subject', $store)
        ->where('event', 'updated')
        ->orderBy('id')
        ->get();
}

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(PlatformRole::Admin);
});

it('records who verified a store and the status change', function () {
    $store = Store::factory()->pending()->create();
    $this->actingAs($this->admin);

    app(VerifyStore::class)->handle($store);

    $activity = storeUpdates($store)->sole();

    expect($activity->causer->is($this->admin))->toBeTrue()
        ->and($activity->attribute_changes['old']['status'])->toBe('pending')
        ->and($activity->attribute_changes['attributes']['status'])->toBe('active')
        ->and($activity->attribute_changes['attributes'])->toHaveKey('verified_at');
});

it('records the rejection reason when a store is rejected', function () {
    $store = Store::factory()->pending()->create();
    $this->actingAs($this->admin);

    app(RejectStore::class)->handle($store, 'Foto lokasi tidak sesuai');

    expect(storeUpdates($store)->sole()->attribute_changes['attributes'])
        ->toMatchArray(['status' => 'rejected', 'rejection_reason' => 'Foto lokasi tidak sesuai']);
});

it('records only the profile fields that changed', function () {
    $store = Store::factory()->create(['name' => 'Kepiting Pak Budi', 'city' => 'Jakarta Utara']);

    $store->update(['name' => 'UD Kepiting Pak Budi']);

    expect(storeUpdates($store)->sole()->attribute_changes->toArray())->toBe([
        'attributes' => ['name' => 'UD Kepiting Pak Budi'],
        'old' => ['name' => 'Kepiting Pak Budi'],
    ]);
});

it('records nothing when a save changes no logged value', function () {
    $store = Store::factory()->create();

    $store->update(['name' => $store->name]);
    $store->save();

    expect(storeUpdates($store))->toBeEmpty();
});

it('never logs the bank account number', function () {
    $store = Store::factory()->create(['bank_account_no' => '1234567890']);

    $store->update(['bank_account_no' => '9876543210']);
    $store->update(['bank_account_no' => '5555555555', 'name' => 'Nama Baru']);

    $logged = Activity::query()->whereMorphedTo('subject', $store)->get()
        ->flatMap(fn (Activity $activity) => [
            ...array_keys($activity->attribute_changes['attributes'] ?? []),
            ...array_keys($activity->attribute_changes['old'] ?? []),
        ]);

    expect($logged)->not->toContain('bank_account_no')
        ->and(json_encode(Activity::query()->get()->toArray()))->not->toContain('9876543210')
        ->and(storeUpdates($store))->toHaveCount(1);
});
