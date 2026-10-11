<?php

declare(strict_types=1);

use App\Actions\UpdateStore;
use App\Enums\StoreStatus;
use App\Models\Store;

it('updates the profile, location, and bank account but keeps the slug', function () {
    $store = Store::factory()->withOwner()->create(['name' => 'Kepiting Pak Budi', 'slug' => 'kepiting-pak-budi']);

    app(UpdateStore::class)->handle($store, storeData([
        'name' => 'Kepiting & Rajungan Pak Budi',
        'phone' => '+6281298765432',
        'lat' => '-6.1234567',
        'lng' => '106.7654321',
        'bankName' => 'Mandiri',
        'bankAccountNo' => '9876543210',
        'bankAccountName' => 'Budi S',
    ]));

    expect($store->fresh())
        ->name->toBe('Kepiting & Rajungan Pak Budi')
        ->phone->toBe('+6281298765432')
        ->lat->toBe('-6.1234567')
        ->lng->toBe('106.7654321')
        ->bank_name->toBe('Mandiri')
        ->bank_account_no->toBe('9876543210')
        ->bank_account_name->toBe('Budi S')
        ->slug->toBe('kepiting-pak-budi');
});

it('returns the updated store', function () {
    $store = Store::factory()->create();

    $result = app(UpdateStore::class)->handle($store, storeData(['name' => 'Cumi Segar Bu Sri']));

    expect($result->is($store))->toBeTrue()
        ->and($result->name)->toBe('Cumi Segar Bu Sri');
});

it('does not change the status or verification fields', function (string $state, StoreStatus $status) {
    $store = Store::factory()->{$state}()->create();
    $before = $store->fresh();

    app(UpdateStore::class)->handle($store, storeData());

    $after = $store->fresh();

    expect($after->status)->toBe($status)
        ->and($after->verified_at?->toDateTimeString())->toBe($before->verified_at?->toDateTimeString())
        ->and($after->rejection_reason)->toBe($before->rejection_reason);
})->with([
    'pending' => ['pending', StoreStatus::Pending],
    'active' => ['active', StoreStatus::Active],
    'rejected' => ['rejected', StoreStatus::Rejected],
    'suspended' => ['suspended', StoreStatus::Suspended],
]);
