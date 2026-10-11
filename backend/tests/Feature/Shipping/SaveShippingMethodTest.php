<?php

declare(strict_types=1);

use App\Actions\SaveShippingMethod;
use App\Data\ShippingMethodData;
use App\Enums\ShippingFeeType;
use App\Enums\ShippingMethodType;
use App\Exceptions\InvalidShippingMethod;
use App\Models\Store;
use App\Models\StoreShippingMethod;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Shipping input with every fee field empty unless given. Money is in rupiah,
 * the radius a decimal string.
 *
 * @param  array<string, mixed>  $fields  Keyed by ShippingMethodData constructor parameter.
 */
function shippingData(ShippingMethodType $type, ShippingFeeType $feeType, array $fields = []): ShippingMethodData
{
    return new ShippingMethodData(...array_merge([
        'type' => $type,
        'feeType' => $feeType,
        'maxRadiusKm' => null,
        'flatFee' => null,
        'feePerKm' => null,
        'minFee' => null,
        'isActive' => true,
    ], $fields));
}

function saveShipping(Store $store, ShippingMethodData $data): StoreShippingMethod
{
    return app(SaveShippingMethod::class)->handle($store, $data);
}

dataset('valid shipping methods', [
    'own delivery, flat Rp10.000 within 15 km' => fn () => [
        shippingData(ShippingMethodType::OwnDelivery, ShippingFeeType::Flat, ['maxRadiusKm' => '15', 'flatFee' => 10_000]),
        ['max_radius_km' => '15.00', 'flat_fee' => 10_000, 'fee_per_km' => null, 'min_fee' => null],
    ],
    'own delivery, Rp3.000 per km, minimum Rp10.000 within 20 km' => fn () => [
        shippingData(ShippingMethodType::OwnDelivery, ShippingFeeType::PerKm, ['maxRadiusKm' => '20', 'feePerKm' => 3_000, 'minFee' => 10_000]),
        ['max_radius_km' => '20.00', 'flat_fee' => null, 'fee_per_km' => 3_000, 'min_fee' => 10_000],
    ],
    'own delivery, free flat fee' => fn () => [
        shippingData(ShippingMethodType::OwnDelivery, ShippingFeeType::Flat, ['maxRadiusKm' => '5', 'flatFee' => 0]),
        ['max_radius_km' => '5.00', 'flat_fee' => 0, 'fee_per_km' => null, 'min_fee' => null],
    ],
    'pickup, flat 0' => fn () => [
        shippingData(ShippingMethodType::Pickup, ShippingFeeType::Flat, ['flatFee' => 0]),
        ['max_radius_km' => null, 'flat_fee' => 0, 'fee_per_km' => null, 'min_fee' => null],
    ],
    'ojol, manual' => fn () => [
        shippingData(ShippingMethodType::Ojol, ShippingFeeType::Manual),
        ['max_radius_km' => null, 'flat_fee' => null, 'fee_per_km' => null, 'min_fee' => null],
    ],
    'expedition, manual' => fn () => [
        shippingData(ShippingMethodType::Expedition, ShippingFeeType::Manual),
        ['max_radius_km' => null, 'flat_fee' => null, 'fee_per_km' => null, 'min_fee' => null],
    ],
]);

dataset('invalid shipping methods', [
    'own delivery with manual fee' => fn () => [
        shippingData(ShippingMethodType::OwnDelivery, ShippingFeeType::Manual, ['maxRadiusKm' => '10']), 'fee_type',
    ],
    'own delivery without radius' => fn () => [
        shippingData(ShippingMethodType::OwnDelivery, ShippingFeeType::Flat, ['flatFee' => 10_000]), 'max_radius_km',
    ],
    'own delivery with radius 0' => fn () => [
        shippingData(ShippingMethodType::OwnDelivery, ShippingFeeType::Flat, ['maxRadiusKm' => '0', 'flatFee' => 10_000]), 'max_radius_km',
    ],
    'own delivery flat without flat fee' => fn () => [
        shippingData(ShippingMethodType::OwnDelivery, ShippingFeeType::Flat, ['maxRadiusKm' => '10']), 'flat_fee',
    ],
    'own delivery flat with a per km fee' => fn () => [
        shippingData(ShippingMethodType::OwnDelivery, ShippingFeeType::Flat, ['maxRadiusKm' => '10', 'flatFee' => 10_000, 'feePerKm' => 3_000]), 'fee_per_km',
    ],
    'own delivery per km without fee per km' => fn () => [
        shippingData(ShippingMethodType::OwnDelivery, ShippingFeeType::PerKm, ['maxRadiusKm' => '10', 'minFee' => 10_000]), 'fee_per_km',
    ],
    'own delivery per km with fee per km 0' => fn () => [
        shippingData(ShippingMethodType::OwnDelivery, ShippingFeeType::PerKm, ['maxRadiusKm' => '10', 'feePerKm' => 0, 'minFee' => 10_000]), 'fee_per_km',
    ],
    'own delivery per km without minimum fee' => fn () => [
        shippingData(ShippingMethodType::OwnDelivery, ShippingFeeType::PerKm, ['maxRadiusKm' => '10', 'feePerKm' => 3_000]), 'min_fee',
    ],
    'own delivery per km with a flat fee' => fn () => [
        shippingData(ShippingMethodType::OwnDelivery, ShippingFeeType::PerKm, ['maxRadiusKm' => '10', 'feePerKm' => 3_000, 'minFee' => 0, 'flatFee' => 5_000]), 'flat_fee',
    ],
    'pickup with flat fee 5000' => fn () => [
        shippingData(ShippingMethodType::Pickup, ShippingFeeType::Flat, ['flatFee' => 5_000]), 'flat_fee',
    ],
    'pickup with per km fee type' => fn () => [
        shippingData(ShippingMethodType::Pickup, ShippingFeeType::PerKm, ['feePerKm' => 3_000, 'minFee' => 0]), 'fee_type',
    ],
    'pickup with a radius' => fn () => [
        shippingData(ShippingMethodType::Pickup, ShippingFeeType::Flat, ['flatFee' => 0, 'maxRadiusKm' => '5']), 'max_radius_km',
    ],
    'ojol with flat fee type' => fn () => [
        shippingData(ShippingMethodType::Ojol, ShippingFeeType::Flat, ['flatFee' => 10_000]), 'fee_type',
    ],
    'expedition manual with a flat fee' => fn () => [
        shippingData(ShippingMethodType::Expedition, ShippingFeeType::Manual, ['flatFee' => 10_000]), 'flat_fee',
    ],
    'negative flat fee' => fn () => [
        shippingData(ShippingMethodType::OwnDelivery, ShippingFeeType::Flat, ['maxRadiusKm' => '10', 'flatFee' => -1]), 'flat_fee',
    ],
    'negative minimum fee' => fn () => [
        shippingData(ShippingMethodType::OwnDelivery, ShippingFeeType::PerKm, ['maxRadiusKm' => '10', 'feePerKm' => 3_000, 'minFee' => -1]), 'min_fee',
    ],
    'negative radius' => fn () => [
        shippingData(ShippingMethodType::OwnDelivery, ShippingFeeType::Flat, ['maxRadiusKm' => '-5', 'flatFee' => 10_000]), 'max_radius_km',
    ],
]);

it('saves a valid shipping method', function (ShippingMethodData $data, array $expected) {
    $store = Store::factory()->create();

    $method = saveShipping($store, $data);

    expect($method->fresh())
        ->store_id->toBe($store->id)
        ->type->toBe($data->type)
        ->fee_type->toBe($data->feeType)
        ->is_active->toBeTrue()
        ->and($method->fresh()->only(array_keys($expected)))->toBe($expected);
})->with('valid shipping methods');

it('rejects an invalid combination and names the wrong field', function (ShippingMethodData $data, string $field) {
    $store = Store::factory()->create();

    expect(fn () => saveShipping($store, $data))
        ->toThrow(fn (InvalidShippingMethod $e) => expect($e->field)->toBe($field));

    expect(StoreShippingMethod::query()->count())->toBe(0);
})->with('invalid shipping methods');

it('updates the existing row when the same type is saved again', function () {
    $store = Store::factory()->create();
    $first = saveShipping($store, shippingData(ShippingMethodType::OwnDelivery, ShippingFeeType::Flat, ['maxRadiusKm' => '15', 'flatFee' => 10_000]));

    $second = saveShipping($store, shippingData(ShippingMethodType::OwnDelivery, ShippingFeeType::Flat, ['maxRadiusKm' => '25', 'flatFee' => 12_000]));

    expect($second->id)->toBe($first->id)
        ->and(StoreShippingMethod::query()->count())->toBe(1)
        ->and($second->fresh())
        ->max_radius_km->toBe('25.00')
        ->flat_fee->toBe(12_000);
});

it('clears the per km fields when own delivery switches to flat', function () {
    $store = Store::factory()->create();
    saveShipping($store, shippingData(ShippingMethodType::OwnDelivery, ShippingFeeType::PerKm, ['maxRadiusKm' => '20', 'feePerKm' => 3_000, 'minFee' => 10_000]));

    $method = saveShipping($store, shippingData(ShippingMethodType::OwnDelivery, ShippingFeeType::Flat, ['maxRadiusKm' => '20', 'flatFee' => 10_000]));

    expect($method->fresh())
        ->fee_type->toBe(ShippingFeeType::Flat)
        ->flat_fee->toBe(10_000)
        ->fee_per_km->toBeNull()
        ->min_fee->toBeNull();
});

it('keeps the fee settings of an inactive method', function () {
    $store = Store::factory()->create();

    $method = saveShipping($store, shippingData(ShippingMethodType::OwnDelivery, ShippingFeeType::Flat, [
        'maxRadiusKm' => '15', 'flatFee' => 10_000, 'isActive' => false,
    ]));

    expect($method->fresh())
        ->is_active->toBeFalse()
        ->max_radius_km->toBe('15.00')
        ->flat_fee->toBe(10_000);
});

it('lets two stores use the same type', function () {
    $data = shippingData(ShippingMethodType::Pickup, ShippingFeeType::Flat, ['flatFee' => 0]);

    saveShipping(Store::factory()->create(), $data);
    saveShipping(Store::factory()->create(), $data);

    expect(StoreShippingMethod::query()->count())->toBe(2);
});

describe('model and factory', function () {
    it('lists the shipping methods of a store', function () {
        $store = Store::factory()->create();
        StoreShippingMethod::factory()->for($store)->pickup()->create();
        StoreShippingMethod::factory()->for($store)->ojol()->create();

        expect($store->shippingMethods)->toHaveCount(2)
            ->and($store->shippingMethods->first()->store->is($store))->toBeTrue();
    });

    it('builds a valid method for each factory state', function (string $state, ShippingMethodType $type, ShippingFeeType $feeType) {
        $method = StoreShippingMethod::factory()->{$state}()->create()->fresh();

        expect($method)
            ->type->toBe($type)
            ->fee_type->toBe($feeType);

        // The same values pass the Action's rules.
        saveShipping($method->store, new ShippingMethodData(
            type: $method->type,
            feeType: $method->fee_type,
            maxRadiusKm: $method->max_radius_km,
            flatFee: $method->flat_fee,
            feePerKm: $method->fee_per_km,
            minFee: $method->min_fee,
            isActive: $method->is_active,
        ));
    })->with([
        'own delivery flat' => ['ownDeliveryFlat', ShippingMethodType::OwnDelivery, ShippingFeeType::Flat],
        'own delivery per km' => ['ownDeliveryPerKm', ShippingMethodType::OwnDelivery, ShippingFeeType::PerKm],
        'pickup' => ['pickup', ShippingMethodType::Pickup, ShippingFeeType::Flat],
        'ojol' => ['ojol', ShippingMethodType::Ojol, ShippingFeeType::Manual],
        'expedition' => ['expedition', ShippingMethodType::Expedition, ShippingFeeType::Manual],
    ]);

    it('allows one row per type per store in the database', function () {
        $store = Store::factory()->create();
        StoreShippingMethod::factory()->for($store)->pickup()->create();

        expect(fn () => StoreShippingMethod::factory()->for($store)->pickup()->create())
            ->toThrow(UniqueConstraintViolationException::class);
    });

    it('removes the shipping methods when the store is deleted', function () {
        $store = Store::factory()->create();
        StoreShippingMethod::factory()->for($store)->pickup()->create();

        $store->delete();

        expect(StoreShippingMethod::query()->count())->toBe(0);
    });
});
