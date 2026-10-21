<?php

declare(strict_types=1);

use App\Enums\StoreRole;
use App\Enums\StoreStatus;
use App\Models\Store;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

const SELLER_STORE_URL = '/api/v1/seller/store';

/**
 * @param  array<string, mixed>  $overrides  Use null to drop a field from the payload.
 * @return array<string, mixed>
 */
function storePayload(array $overrides = []): array
{
    return array_filter(array_merge([
        'name' => 'Kepiting Pak Budi',
        'description' => 'Kepiting bakau segar langsung dari tambak.',
        'phone' => '081234567890',
        'address' => 'Jl. Pelabuhan No. 5, Muara Angke',
        'city' => 'Jakarta Utara',
        'lat' => -6.2087634,
        'lng' => 106.845599,
        'bank_name' => 'BCA',
        'bank_account_no' => '1234567890',
        'bank_account_name' => 'Budi Santoso',
        'stock_reminder_time' => '06:00',
    ], $overrides), fn (mixed $value) => $value !== null);
}

/**
 * A store with the given user as a member in the given role.
 */
function storeWithMember(User $user, StoreRole $role, string $state = 'pending'): Store
{
    $store = Store::factory()->{$state}()->create(['slug' => 'kepiting-pak-budi']);
    $store->users()->attach($user, ['role' => $role->value]);

    return $store;
}

describe('POST /seller/store', function () {
    it('creates a pending store owned by the seller', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson(SELLER_STORE_URL, storePayload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Kepiting Pak Budi')
            ->assertJsonPath('data.slug', 'kepiting-pak-budi')
            ->assertJsonPath('data.status.value', 'pending')
            ->assertJsonPath('data.status.label', StoreStatus::Pending->label())
            ->assertJsonPath('data.bank.account_no', '1234567890')
            ->assertJsonPath('data.role', ['value' => 'owner', 'label' => StoreRole::Owner->label()]);

        expect($user->roleIn(Store::query()->sole()))->toBe(StoreRole::Owner);
    });

    it('lets a staff member of another store create their own store', function () {
        $user = User::factory()->create();
        storeWithMember($user, StoreRole::Staff);
        Sanctum::actingAs($user);

        $this->postJson(SELLER_STORE_URL, storePayload(['name' => 'Cumi Segar Bu Sri']))->assertCreated();
    });

    it('returns 409 when the seller already owns a store', function () {
        $user = User::factory()->create();
        storeWithMember($user, StoreRole::Owner);
        Sanctum::actingAs($user);

        $this->postJson(SELLER_STORE_URL, storePayload())
            ->assertConflict()
            ->assertJsonStructure(['message']);

        expect(Store::query()->count())->toBe(1);
    });

    it('ignores status and verification fields in the body', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(SELLER_STORE_URL, storePayload([
            'status' => 'active',
            'verified_at' => '2026-01-01 00:00:00',
            'rejection_reason' => 'Disisipkan penjual',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.status.value', 'pending')
            ->assertJsonPath('data.verified_at', null)
            ->assertJsonPath('data.rejection_reason', null);

        expect(Store::query()->sole())
            ->status->toBe(StoreStatus::Pending)
            ->verified_at->toBeNull()
            ->rejection_reason->toBeNull();
    });

    it('ignores the demo flag in the body', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(SELLER_STORE_URL, storePayload(['is_demo' => true]))->assertCreated();

        expect(Store::query()->sole()->is_demo)->toBeFalse();
    });

    it('accepts coordinates sent as strings', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(SELLER_STORE_URL, storePayload(['lat' => '-6.2087634', 'lng' => '106.8455990']))
            ->assertCreated();

        expect(Store::query()->sole())
            ->lat->toBe('-6.2087634')
            ->lng->toBe('106.8455990');
    });

    it('rejects invalid store data', function (array $overrides, string $field) {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(SELLER_STORE_URL, storePayload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        expect(Store::query()->count())->toBe(0);
    })->with([
        'lat above 90' => [['lat' => 91], 'lat'],
        'lng below -180' => [['lng' => -181], 'lng'],
        'phone with letters' => [['phone' => 'abc'], 'phone'],
        'bank account number with letters' => [['bank_account_no' => '12ab'], 'bank_account_no'],
        'bank account number of 4 digits' => [['bank_account_no' => '1234'], 'bank_account_no'],
        'bank account number of 21 digits' => [['bank_account_no' => str_repeat('1', 21)], 'bank_account_no'],
        'missing city' => [['city' => null], 'city'],
        'missing address' => [['address' => null], 'address'],
        'missing bank name' => [['bank_name' => null], 'bank_name'],
        'missing bank account name' => [['bank_account_name' => null], 'bank_account_name'],
        'name of 101 characters' => [['name' => str_repeat('a', 101)], 'name'],
        'description of 2001 characters' => [['description' => str_repeat('a', 2001)], 'description'],
        'reminder time 25:00' => [['stock_reminder_time' => '25:00'], 'stock_reminder_time'],
    ]);
});

describe('GET /seller/store', function () {
    it('shows the store with its bank account to the owner', function () {
        $user = User::factory()->create();
        $store = storeWithMember($user, StoreRole::Owner);
        Sanctum::actingAs($user);

        $this->getJson(SELLER_STORE_URL)
            ->assertOk()
            ->assertExactJsonStructure(['data' => [
                'id', 'name', 'slug', 'description', 'phone', 'address', 'city', 'lat', 'lng',
                'status' => ['value', 'label'], 'verified_at', 'rejection_reason', 'stock_reminder_time',
                'role' => ['value', 'label'],
                'bank' => ['name', 'account_no', 'account_name'],
            ]])
            ->assertJsonPath('data.id', $store->id)
            ->assertJsonPath('data.role', ['value' => 'owner', 'label' => 'Pemilik'])
            ->assertJsonPath('data.bank.account_no', $store->bank_account_no);
    });

    it('shows staff their role and hides the bank account', function () {
        $user = User::factory()->create();
        storeWithMember($user, StoreRole::Staff);
        Sanctum::actingAs($user);

        $this->getJson(SELLER_STORE_URL)
            ->assertOk()
            ->assertJsonPath('data.role', ['value' => 'staff', 'label' => StoreRole::Staff->label()])
            ->assertJsonMissingPath('data.bank');
    });

    it('shows only the role of the user asking, not the other members', function () {
        $owner = User::factory()->create();
        $store = storeWithMember($owner, StoreRole::Owner);
        $staff = User::factory()->create();
        $store->users()->attach($staff, ['role' => StoreRole::Staff->value]);
        Sanctum::actingAs($staff);

        $response = $this->getJson(SELLER_STORE_URL)
            ->assertOk()
            ->assertJsonPath('data.role.value', 'staff');

        expect($response->getContent())
            ->not->toContain((string) $owner->email)
            ->not->toContain('"owner"');
    });

    it('shows the rejection reason only for a rejected store', function (string $state, bool $hasReason) {
        $user = User::factory()->create();
        $store = storeWithMember($user, StoreRole::Owner, $state);
        Sanctum::actingAs($user);

        $this->getJson(SELLER_STORE_URL)
            ->assertOk()
            ->assertJsonPath('data.rejection_reason', $hasReason ? $store->rejection_reason : null);
    })->with([
        'rejected' => ['rejected', true],
        'pending' => ['pending', false],
        'active' => ['active', false],
    ]);

    it('returns 404 when the user has no store', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(SELLER_STORE_URL)->assertNotFound();
    });

    it('returns the owned store to a user who is also staff elsewhere', function () {
        $user = User::factory()->create();
        $ownStore = Store::factory()->create();
        // Staff membership created first, so it would win without an explicit order.
        Store::factory()->create()->users()->attach($user, ['role' => StoreRole::Staff->value]);
        $ownStore->users()->attach($user, ['role' => StoreRole::Owner->value]);
        Sanctum::actingAs($user);

        $this->getJson(SELLER_STORE_URL)
            ->assertOk()
            ->assertJsonPath('data.id', $ownStore->id);
    });
});

describe('PUT /seller/store', function () {
    it('lets the owner update the store but keeps the slug', function () {
        $user = User::factory()->create();
        $store = storeWithMember($user, StoreRole::Owner);
        Sanctum::actingAs($user);

        $this->putJson(SELLER_STORE_URL, storePayload([
            'name' => 'Kepiting & Rajungan Pak Budi',
            'lat' => '-6.1234567',
            'lng' => '106.7654321',
        ]))
            ->assertOk()
            ->assertJsonPath('data.name', 'Kepiting & Rajungan Pak Budi')
            ->assertJsonPath('data.slug', 'kepiting-pak-budi')
            ->assertJsonPath('data.role.value', 'owner');

        expect($store->fresh())
            ->name->toBe('Kepiting & Rajungan Pak Budi')
            ->lat->toBe('-6.1234567')
            ->lng->toBe('106.7654321')
            ->slug->toBe('kepiting-pak-budi');
    });

    it('forbids staff from updating the store', function () {
        $user = User::factory()->create();
        $store = storeWithMember($user, StoreRole::Staff);
        Sanctum::actingAs($user);

        $this->putJson(SELLER_STORE_URL, storePayload(['name' => 'Diubah Staff']))->assertForbidden();

        expect($store->fresh()->name)->not->toBe('Diubah Staff');
    });

    it('ignores status and rejection fields in the body', function () {
        $user = User::factory()->create();
        $store = storeWithMember($user, StoreRole::Owner, 'rejected');
        $reason = $store->rejection_reason;
        Sanctum::actingAs($user);

        $this->putJson(SELLER_STORE_URL, storePayload([
            'status' => 'active',
            'verified_at' => '2026-01-01 00:00:00',
            'rejection_reason' => 'Diubah penjual',
        ]))->assertOk();

        expect($store->fresh())
            ->status->toBe(StoreStatus::Rejected)
            ->verified_at->toBeNull()
            ->rejection_reason->toBe($reason);
    });

    it('never lets the owner change the demo flag', function (bool $isDemo) {
        $user = User::factory()->create();
        $store = Store::factory()->active()->state(['is_demo' => $isDemo])->create();
        $store->users()->attach($user, ['role' => StoreRole::Owner->value]);
        Sanctum::actingAs($user);

        $this->putJson(SELLER_STORE_URL, storePayload(['is_demo' => ! $isDemo]))->assertOk();

        expect($store->fresh()->is_demo)->toBe($isDemo);
    })->with([
        'real store asks to become a demo store' => [false],
        'demo store asks to drop the demo flag' => [true],
    ]);

    it('validates the update like the create', function () {
        $user = User::factory()->create();
        storeWithMember($user, StoreRole::Owner);
        Sanctum::actingAs($user);

        $this->putJson(SELLER_STORE_URL, storePayload(['lat' => 91]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('lat');
    });

    it('returns 404 when the user has no store', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->putJson(SELLER_STORE_URL, storePayload())->assertNotFound();
    });
});

it('requires a token', function (string $method) {
    $this->json($method, SELLER_STORE_URL, storePayload())->assertUnauthorized();
})->with(['POST', 'GET', 'PUT']);
