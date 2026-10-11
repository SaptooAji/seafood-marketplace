<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

/**
 * Registers a user who signs in with email and password.
 */
class RegisterUser
{
    /**
     * Create the user. The password is hashed by the model's `hashed` cast.
     *
     * @param  array{name: string, email: string, phone: string, password: string}  $attributes
     */
    public function handle(array $attributes): User
    {
        return User::query()->create($attributes);
    }
}
