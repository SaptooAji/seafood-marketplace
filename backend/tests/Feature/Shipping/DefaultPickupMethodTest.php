<?php

declare(strict_types=1);

use App\Actions\CreateStore;
use App\Actions\SaveShippingMethod;
use App\Data\ShippingMethodData;
use App\Enums\ShippingFeeType;
use App\Enums\ShippingMethodType;
use App\Exceptions\InvalidShippingMethod;
use App\Exceptions\UserAlreadyOwnsStore;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\StoreShippingMethod;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Laravel\Sanctum\Sanctum;

/*
 * Every store has "Ambil sendiri" as its default way to receive an order
 * (#212): a new store gets an active pickup method, existing stores get one
 * from a data migration, and a seller may turn pickup off only while another
 * method stays active, so buyers always have at least one choice.
 */

/**
 * Pickup with its only valid fee settings.
 */
function defaultPickupData(bool $isActive = true): ShippingMethodData
{
    return new ShippingMethodData(ShippingMethodType::Pickup, ShippingFeeType::Flat, null, 0, null, null, $isActive);
}

/**
 * Own delivery for a flat Rp10.000 within 15 km.
 */
function defaultOwnDeliveryData(bool $isActive = true): ShippingMethodData
{
    return new ShippingMethodData(ShippingMethodType::OwnDelivery, ShippingFeeType::Flat, '15', 10_000, null, null, $isActive);
}

/**
 * The data migration that adds the default pickup method.
 */
function defaultPickupMigration(): Migration
{
    return require collect(glob(database_path('migrations/*_add_default_pickup_to_stores.php')))->sole();
}

describe('a new store', function () {
    it('gets one active pickup method, free and without a radius', function () {
        $store = app(CreateStore::class)->handle(User::factory()->create(), storeData());

        expect($store->shippingMethods()->get())->toHaveCount(1)
            ->and($store->shippingMethods()->sole())
            ->type->toBe(ShippingMethodType::Pickup)
            ->fee_type->toBe(ShippingFeeType::Flat)
            ->flat_fee->toBe(0)
            ->max_radius_km->toBeNull()
            ->fee_per_km->toBeNull()
            ->min_fee->toBeNull()
            ->is_active->toBeTrue();
    });

    it('leaves no shipping method behind when the store is not created', function () {
        $owner = User::factory()->create();
        Store::factory()->withOwner($owner)->create();

        expect(fn () => app(CreateStore::class)->handle($owner, storeData()))->toThrow(UserAlreadyOwnsStore::class)
            ->and(StoreShippingMethod::query()->count())->toBe(0);
    });

    it('leaves no shipping method behind when adding the owner fails', function () {
        // A user id with no row breaks the store_members foreign key after the store insert.
        $ghost = User::factory()->make(['id' => 999_999]);

        expect(fn () => app(CreateStore::class)->handle($ghost, storeData()))->toThrow(Exception::class)
            ->and(Store::query()->count())->toBe(0)
            ->and(StoreShippingMethod::query()->count())->toBe(0);
    });
});

describe('at least one active method', function () {
    it('turns pickup off while own delivery stays active', function () {
        $store = Store::factory()->create();
        app(SaveShippingMethod::class)->handle($store, defaultPickupData());
        app(SaveShippingMethod::class)->handle($store, defaultOwnDeliveryData());

        $pickup = app(SaveShippingMethod::class)->handle($store, defaultPickupData(isActive: false));

        expect($pickup->fresh()->is_active)->toBeFalse()
            ->and($store->shippingMethods()->where('is_active', true)->sole()->type)->toBe(ShippingMethodType::OwnDelivery);
    });

    it('refuses to turn off the last active method', function (Closure $setUp, Closure $change) {
        $store = Store::factory()->create();
        $setUp($store);
        $before = $store->shippingMethods()->orderBy('id')->get(['type', 'is_active'])->toArray();

        try {
            $change($store);
            $this->fail('The change was saved.');
        } catch (InvalidShippingMethod $exception) {
            expect($exception->field)->toBe('is_active')
                ->and($exception->getMessage())->toBe('Toko harus punya minimal satu cara kirim yang aktif.');
        }

        expect($store->shippingMethods()->orderBy('id')->get(['type', 'is_active'])->toArray())->toBe($before);
    })->with([
        'pickup is the only method' => [
            fn (Store $store) => app(SaveShippingMethod::class)->handle($store, defaultPickupData()),
            fn (Store $store) => app(SaveShippingMethod::class)->handle($store, defaultPickupData(isActive: false)),
        ],
        'own delivery is already off' => [
            function (Store $store) {
                app(SaveShippingMethod::class)->handle($store, defaultPickupData());
                StoreShippingMethod::factory()->for($store)->ownDeliveryFlat()->create(['is_active' => false]);
            },
            fn (Store $store) => app(SaveShippingMethod::class)->handle($store, defaultPickupData(isActive: false)),
        ],
        'a new method saved off while nothing is active' => [
            fn (Store $store) => null,
            fn (Store $store) => app(SaveShippingMethod::class)->handle($store, defaultOwnDeliveryData(isActive: false)),
        ],
    ]);

    it('counts only the methods of the same store', function () {
        $store = Store::factory()->create();
        app(SaveShippingMethod::class)->handle($store, defaultPickupData());
        StoreShippingMethod::factory()->for(Store::factory())->ownDeliveryFlat()->create();

        expect(fn () => app(SaveShippingMethod::class)->handle($store, defaultPickupData(isActive: false)))
            ->toThrow(InvalidShippingMethod::class);
    });

    it('always lets a method be turned back on', function () {
        $store = Store::factory()->create();
        StoreShippingMethod::factory()->for($store)->pickup()->create(['is_active' => false]);

        $pickup = app(SaveShippingMethod::class)->handle($store, defaultPickupData());

        expect($pickup->fresh()->is_active)->toBeTrue();
    });

    it('checks the fee settings first', function () {
        $store = Store::factory()->create();
        app(SaveShippingMethod::class)->handle($store, defaultPickupData());

        try {
            app(SaveShippingMethod::class)->handle($store, new ShippingMethodData(ShippingMethodType::Pickup, ShippingFeeType::Flat, '5', 0, null, null, false));
            $this->fail('The change was saved.');
        } catch (InvalidShippingMethod $exception) {
            expect($exception->field)->toBe('max_radius_km');
        }
    });
});

describe('the seller API and the product page', function () {
    beforeEach(function () {
        $this->owner = User::factory()->create();
        $this->store = app(CreateStore::class)->handle($this->owner, storeData());
        $this->store->forceFill(['status' => 'active', 'verified_at' => now()])->save();
        $this->product = Product::factory()->for($this->store)->create();
        ProductVariant::factory()->for($this->product)->create();
        Sanctum::actingAs($this->owner);
    });

    it('offers pickup on the product page of a new store', function () {
        $this->get(route('products.show', $this->product->slug))
            ->assertOk()
            ->assertSee(ShippingMethodType::Pickup->label())
            ->assertDontSee('Tanyakan cara kirim ke penjual');
    });

    it('offers two choices after the store adds own delivery', function () {
        $this->postJson('/api/v1/seller/shipping-methods', [
            'type' => 'own_delivery', 'fee_type' => 'flat', 'max_radius_km' => 15, 'flat_fee' => 10_000,
            'fee_per_km' => null, 'min_fee' => null, 'is_active' => true,
        ])->assertCreated();

        expect($this->store->shippingMethods()->where('is_active', true)->count())->toBe(2);

        $this->get(route('products.show', $this->product->slug))
            ->assertOk()
            ->assertSee(ShippingMethodType::Pickup->label())
            ->assertSee(ShippingMethodType::OwnDelivery->label());
    });

    it('offers only own delivery after pickup is turned off', function () {
        app(SaveShippingMethod::class)->handle($this->store, defaultOwnDeliveryData());
        $pickup = $this->store->shippingMethods()->where('type', ShippingMethodType::Pickup)->sole();

        $this->putJson("/api/v1/seller/shipping-methods/{$pickup->id}", [
            'type' => 'pickup', 'fee_type' => 'flat', 'max_radius_km' => null, 'flat_fee' => 0,
            'fee_per_km' => null, 'min_fee' => null, 'is_active' => false,
        ])->assertOk();

        $this->get(route('products.show', $this->product->slug))
            ->assertOk()
            ->assertSee(ShippingMethodType::OwnDelivery->label())
            ->assertDontSee(ShippingMethodType::Pickup->label());
    });

    it('answers 422 on is_active when the last active method is turned off', function () {
        $pickup = $this->store->shippingMethods()->sole();

        $this->putJson("/api/v1/seller/shipping-methods/{$pickup->id}", [
            'type' => 'pickup', 'fee_type' => 'flat', 'max_radius_km' => null, 'flat_fee' => 0,
            'fee_per_km' => null, 'min_fee' => null, 'is_active' => false,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_active' => 'Toko harus punya minimal satu cara kirim yang aktif.']);

        expect($pickup->fresh()->is_active)->toBeTrue();
    });

    it('answers 404 when the owner of another store changes this one', function () {
        $pickup = $this->store->shippingMethods()->sole();
        $other = User::factory()->create();
        Store::factory()->active()->withOwner($other)->create();
        Sanctum::actingAs($other);

        $this->putJson("/api/v1/seller/shipping-methods/{$pickup->id}", [
            'type' => 'pickup', 'fee_type' => 'flat', 'max_radius_km' => null, 'flat_fee' => 0,
            'fee_per_km' => null, 'min_fee' => null, 'is_active' => false,
        ])->assertNotFound();

        expect($pickup->fresh()->is_active)->toBeTrue();
    });
});

describe('data migration', function () {
    it('adds an active pickup method to stores without one and leaves the rest', function () {
        $none = Store::factory()->create();
        $deliveryOnly = Store::factory()->create();
        StoreShippingMethod::factory()->for($deliveryOnly)->ownDeliveryFlat()->create();
        $pickupOff = Store::factory()->create();
        StoreShippingMethod::factory()->for($pickupOff)->pickup()->create(['is_active' => false]);
        $pickupOn = Store::factory()->create();
        StoreShippingMethod::factory()->for($pickupOn)->pickup()->create();

        defaultPickupMigration()->up();

        foreach ([$none, $deliveryOnly] as $store) {
            expect($store->shippingMethods()->where('type', ShippingMethodType::Pickup)->sole())
                ->fee_type->toBe(ShippingFeeType::Flat)
                ->flat_fee->toBe(0)
                ->max_radius_km->toBeNull()
                ->fee_per_km->toBeNull()
                ->min_fee->toBeNull()
                ->is_active->toBeTrue()
                ->created_at->not->toBeNull();
        }

        expect($deliveryOnly->shippingMethods()->count())->toBe(2)
            ->and($pickupOff->shippingMethods()->sole()->is_active)->toBeFalse()
            ->and($pickupOn->shippingMethods()->count())->toBe(1);
    });

    it('adds nothing more when run again, and removes nothing when rolled back', function () {
        Store::factory()->count(3)->create();
        $migration = defaultPickupMigration();

        $migration->up();
        $migration->down();
        $migration->up();

        expect(StoreShippingMethod::query()->count())->toBe(3)
            ->and(StoreShippingMethod::query()->where('type', ShippingMethodType::Pickup)->where('is_active', true)->count())->toBe(3);
    });
});
