<?php

declare(strict_types=1);

use App\Enums\ShippingFeeType;
use App\Enums\ShippingMethodType;
use App\Enums\StoreRole;
use App\Models\Store;
use App\Models\StoreShippingMethod;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

const SHIPPING_METHODS_URL = '/api/v1/seller/shipping-methods';

/**
 * Own delivery for a flat Rp10.000 within 15 km, unless overridden.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function shippingPayload(array $overrides = []): array
{
    return array_merge([
        'type' => 'own_delivery',
        'fee_type' => 'flat',
        'max_radius_km' => 15,
        'flat_fee' => 10_000,
        'fee_per_km' => null,
        'min_fee' => null,
        'is_active' => true,
    ], $overrides);
}

/**
 * Sign in a user who has the given role in a store in the given state.
 */
function actingAsStoreMember(StoreRole $role = StoreRole::Owner, string $state = 'active'): Store
{
    $user = User::factory()->create();
    $store = Store::factory()->{$state}()->create();
    $store->users()->attach($user, ['role' => $role->value]);
    Sanctum::actingAs($user);

    return $store;
}

describe('GET /seller/shipping-methods', function () {
    it('lists only the methods of the user’s store', function () {
        $store = actingAsStoreMember();
        $mine = [
            StoreShippingMethod::factory()->for($store)->ownDeliveryFlat()->create(),
            StoreShippingMethod::factory()->for($store)->pickup()->create(),
        ];
        StoreShippingMethod::factory()->for(Store::factory())->ojol()->create();

        $response = $this->getJson(SHIPPING_METHODS_URL)
            ->assertOk()
            ->assertExactJsonStructure(['data' => ['*' => [
                'id', 'type' => ['value', 'label'], 'fee_type' => ['value', 'label'],
                'max_radius_km', 'flat_fee', 'fee_per_km', 'min_fee', 'is_active',
            ]]]);

        expect(collect($response->json('data'))->pluck('id')->all())
            ->toEqualCanonicalizing(collect($mine)->pluck('id')->all());
    });

    it('shows the values and labels of a method', function () {
        $store = actingAsStoreMember();
        StoreShippingMethod::factory()->for($store)->ownDeliveryPerKm()->create();

        $this->getJson(SHIPPING_METHODS_URL)
            ->assertOk()
            ->assertJsonPath('data.0.type.value', 'own_delivery')
            ->assertJsonPath('data.0.type.label', ShippingMethodType::OwnDelivery->label())
            ->assertJsonPath('data.0.fee_type.value', 'per_km')
            ->assertJsonPath('data.0.fee_type.label', ShippingFeeType::PerKm->label())
            ->assertJsonPath('data.0.fee_per_km', 3_000)
            ->assertJsonPath('data.0.min_fee', 10_000)
            ->assertJsonPath('data.0.flat_fee', null)
            ->assertJsonPath('data.0.is_active', true);
    });

    it('is open to staff', function () {
        $store = actingAsStoreMember(StoreRole::Staff);
        StoreShippingMethod::factory()->for($store)->pickup()->create();

        $this->getJson(SHIPPING_METHODS_URL)->assertOk()->assertJsonCount(1, 'data');
    });

    it('returns 404 when the user has no store', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson(SHIPPING_METHODS_URL)->assertNotFound();
    });
});

describe('POST /seller/shipping-methods', function () {
    it('creates a new method with 201', function () {
        $store = actingAsStoreMember();

        $this->postJson(SHIPPING_METHODS_URL, shippingPayload())
            ->assertCreated()
            ->assertJsonPath('data.type.value', 'own_delivery')
            ->assertJsonPath('data.flat_fee', 10_000);

        expect($store->shippingMethods()->sole())
            ->type->toBe(ShippingMethodType::OwnDelivery)
            ->max_radius_km->toBe('15.00')
            ->flat_fee->toBe(10_000);
    });

    it('updates the method of an existing type with 200', function () {
        $store = actingAsStoreMember();
        $existing = StoreShippingMethod::factory()->for($store)->ownDeliveryFlat()->create();

        $this->postJson(SHIPPING_METHODS_URL, shippingPayload(['flat_fee' => 12_000]))
            ->assertOk()
            ->assertJsonPath('data.id', $existing->id)
            ->assertJsonPath('data.flat_fee', 12_000);

        expect($store->shippingMethods()->count())->toBe(1);
    });

    it('lets the owner of a pending store set up shipping', function () {
        actingAsStoreMember(state: 'pending');

        $this->postJson(SHIPPING_METHODS_URL, shippingPayload())->assertCreated();
    });

    it('forbids staff from creating a method', function () {
        $store = actingAsStoreMember(StoreRole::Staff);

        $this->postJson(SHIPPING_METHODS_URL, shippingPayload())->assertForbidden();

        expect($store->shippingMethods()->count())->toBe(0);
    });

    it('reports an invalid combination on the field at fault', function () {
        $store = actingAsStoreMember();

        $this->postJson(SHIPPING_METHODS_URL, shippingPayload([
            'type' => 'pickup',
            'max_radius_km' => null,
            'flat_fee' => 5_000,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('flat_fee');

        expect($store->shippingMethods()->count())->toBe(0);
    });

    it('rejects values outside the enums or missing', function (array $overrides, string $field) {
        actingAsStoreMember();

        $this->postJson(SHIPPING_METHODS_URL, shippingPayload($overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    })->with([
        'unknown type' => [['type' => 'drone'], 'type'],
        'unknown fee type' => [['fee_type' => 'free'], 'fee_type'],
        'missing type' => [['type' => null], 'type'],
        'missing fee type' => [['fee_type' => null], 'fee_type'],
        'fee that is not a number' => [['flat_fee' => 'sepuluh ribu'], 'flat_fee'],
    ]);

    it('returns 404 when the user has no store', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson(SHIPPING_METHODS_URL, shippingPayload())->assertNotFound();
    });
});

describe('PUT /seller/shipping-methods/{id}', function () {
    it('deactivates a method and keeps its fees', function () {
        $store = actingAsStoreMember();
        $method = StoreShippingMethod::factory()->for($store)->ownDeliveryFlat()->create();

        $this->putJson(SHIPPING_METHODS_URL."/{$method->id}", shippingPayload(['is_active' => false]))
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        expect($method->fresh())
            ->is_active->toBeFalse()
            ->flat_fee->toBe(10_000);
    });

    it('keeps the type of the method', function () {
        $store = actingAsStoreMember();
        $method = StoreShippingMethod::factory()->for($store)->ojol()->create();

        $this->putJson(SHIPPING_METHODS_URL."/{$method->id}", shippingPayload([
            'type' => 'expedition',
            'fee_type' => 'manual',
            'max_radius_km' => null,
            'flat_fee' => null,
        ]))->assertOk();

        expect($method->fresh()->type)->toBe(ShippingMethodType::Ojol)
            ->and($store->shippingMethods()->count())->toBe(1);
    });

    it('reports an invalid combination on the field at fault', function () {
        $store = actingAsStoreMember();
        $method = StoreShippingMethod::factory()->for($store)->ownDeliveryFlat()->create();

        $this->putJson(SHIPPING_METHODS_URL."/{$method->id}", shippingPayload(['max_radius_km' => 0]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('max_radius_km');
    });

    it('forbids staff from updating a method', function () {
        $store = actingAsStoreMember(StoreRole::Staff);
        $method = StoreShippingMethod::factory()->for($store)->ownDeliveryFlat()->create();

        $this->putJson(SHIPPING_METHODS_URL."/{$method->id}", shippingPayload(['is_active' => false]))
            ->assertForbidden();

        expect($method->fresh()->is_active)->toBeTrue();
    });

    it('returns 404 for a method of another store', function () {
        actingAsStoreMember();
        $other = StoreShippingMethod::factory()->for(Store::factory())->ownDeliveryFlat()->create();

        $this->putJson(SHIPPING_METHODS_URL."/{$other->id}", shippingPayload(['is_active' => false]))
            ->assertNotFound();

        expect($other->fresh()->is_active)->toBeTrue();
    });
});

it('requires a token', function (string $method, string $url) {
    $this->json($method, $url, shippingPayload())->assertUnauthorized();
})->with([
    'GET' => ['GET', SHIPPING_METHODS_URL],
    'POST' => ['POST', SHIPPING_METHODS_URL],
    'PUT' => ['PUT', SHIPPING_METHODS_URL.'/1'],
]);
