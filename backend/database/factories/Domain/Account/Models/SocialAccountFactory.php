<?php

declare(strict_types=1);

namespace Database\Factories\Domain\Account\Models;

use App\Domain\Account\Enums\SocialProvider;
use App\Domain\Account\Models\SocialAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    /**
     * The model this factory builds. Laravel cannot guess it for models outside app/Models.
     *
     * @var class-string<SocialAccount>
     */
    protected $model = SocialAccount::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => fake()->randomElement(SocialProvider::cases()),
            'provider_id' => fake()->uuid(),
        ];
    }
}
