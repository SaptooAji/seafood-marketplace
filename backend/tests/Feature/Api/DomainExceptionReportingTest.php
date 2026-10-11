<?php

declare(strict_types=1);

use App\Enums\StoreRole;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

// The log is checked instead of Exceptions::fake(): the fake does not apply
// exception mapping, so it would count InvalidShippingMethod as reported even
// though the real handler maps it to a ValidationException that is never logged.
beforeEach(function () {
    Log::spy();
});

/**
 * Sign in a seller who owns an active store.
 */
function actingAsStoreOwner(): Store
{
    $owner = User::factory()->create();
    $store = Store::factory()->active()->create();
    $store->users()->attach($owner, ['role' => StoreRole::Owner->value]);
    Sanctum::actingAs($owner);

    return $store;
}

it('does not log a seller trying to open a second store', function () {
    actingAsStoreOwner();

    $this->postJson('/api/v1/seller/store', [
        'name' => 'Toko Kedua',
        'phone' => '081234567890',
        'address' => 'Jl. Pelabuhan No. 5',
        'city' => 'Jakarta Utara',
        'lat' => -6.2087634,
        'lng' => 106.845599,
        'bank_name' => 'BCA',
        'bank_account_no' => '1234567890',
        'bank_account_name' => 'Budi Santoso',
    ])
        ->assertConflict()
        ->assertExactJson(['message' => 'Anda sudah memiliki toko.']);

    Log::shouldNotHaveReceived('error');
});

it('does not log an invalid shipping method combination', function () {
    actingAsStoreOwner();

    $this->postJson('/api/v1/seller/shipping-methods', [
        'type' => 'pickup',
        'fee_type' => 'flat',
        'flat_fee' => 5_000,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('flat_fee');

    Log::shouldNotHaveReceived('error');
});

it('still logs unexpected errors', function () {
    Route::middleware('api')->get('api/v1/_test/boom', fn () => throw new RuntimeException('Unexpected failure'));

    $this->getJson('/api/v1/_test/boom')->assertInternalServerError();

    Log::shouldHaveReceived('error')->withArgs(fn (string $message) => $message === 'Unexpected failure')->once();
});
