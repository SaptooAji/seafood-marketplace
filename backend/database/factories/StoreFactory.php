<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\StoreRole;
use App\Enums\StoreStatus;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    /**
     * Define the model's default state: a pending store in the Jakarta area.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            // Random suffix: different company names can produce the same slug.
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'description' => fake()->sentence(),
            'phone' => fake()->numerify('08##########'),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'lat' => fake()->randomFloat(7, -6.4, -6.1),
            'lng' => fake()->randomFloat(7, 106.7, 107.0),
            'bank_name' => fake()->randomElement(['BCA', 'BRI', 'BNI', 'Mandiri']),
            'bank_account_no' => fake()->numerify('##########'),
            'bank_account_name' => fake()->name(),
            'status' => StoreStatus::Pending,
        ];
    }

    /**
     * Indicate that the store is waiting for admin verification.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StoreStatus::Pending,
            'verified_at' => null,
        ]);
    }

    /**
     * Indicate that the store has been verified by an admin.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StoreStatus::Active,
            'verified_at' => now(),
        ]);
    }

    /**
     * Indicate that an admin rejected the store, with a reason.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StoreStatus::Rejected,
            'rejection_reason' => fake()->sentence(),
        ]);
    }

    /**
     * Indicate that a previously verified store has been suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StoreStatus::Suspended,
            'verified_at' => now(),
        ]);
    }

    /**
     * Attach an owner to the store: the given user, or a new one.
     */
    public function withOwner(?User $user = null): static
    {
        return $this->hasAttached($user ?? User::factory(), ['role' => StoreRole::Owner->value], 'users');
    }
}
