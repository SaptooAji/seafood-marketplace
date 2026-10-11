<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ShippingFeeType;
use App\Enums\ShippingMethodType;
use App\Models\Store;
use App\Models\StoreShippingMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Every state is a combination SaveShippingMethod accepts.
 *
 * @extends Factory<StoreShippingMethod>
 */
class StoreShippingMethodFactory extends Factory
{
    /**
     * Define the model's default state: pickup at the store, free.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'type' => ShippingMethodType::Pickup,
            'fee_type' => ShippingFeeType::Flat,
            'max_radius_km' => null,
            'flat_fee' => 0,
            'fee_per_km' => null,
            'min_fee' => null,
            'is_active' => true,
        ];
    }

    /**
     * The store delivers itself for one flat fee within a radius.
     */
    public function ownDeliveryFlat(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ShippingMethodType::OwnDelivery,
            'fee_type' => ShippingFeeType::Flat,
            'max_radius_km' => '15.00',
            'flat_fee' => 10_000,
            'fee_per_km' => null,
            'min_fee' => null,
        ]);
    }

    /**
     * The store delivers itself, charging per km with a minimum, within a radius.
     */
    public function ownDeliveryPerKm(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ShippingMethodType::OwnDelivery,
            'fee_type' => ShippingFeeType::PerKm,
            'max_radius_km' => '20.00',
            'flat_fee' => null,
            'fee_per_km' => 3_000,
            'min_fee' => 10_000,
        ]);
    }

    /**
     * The buyer picks the order up at the store, free.
     */
    public function pickup(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => ShippingMethodType::Pickup,
            'fee_type' => ShippingFeeType::Flat,
            'max_radius_km' => null,
            'flat_fee' => 0,
            'fee_per_km' => null,
            'min_fee' => null,
        ]);
    }

    /**
     * An online motorbike courier; the seller enters the fee per order.
     */
    public function ojol(): static
    {
        return $this->manual(ShippingMethodType::Ojol);
    }

    /**
     * A courier company; the seller enters the fee per order.
     */
    public function expedition(): static
    {
        return $this->manual(ShippingMethodType::Expedition);
    }

    private function manual(ShippingMethodType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
            'fee_type' => ShippingFeeType::Manual,
            'max_radius_km' => null,
            'flat_fee' => null,
            'fee_per_km' => null,
            'min_fee' => null,
        ]);
    }
}
