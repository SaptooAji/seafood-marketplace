<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Checks an email and password pair without starting a session.
 */
class AuthenticateWithPassword
{
    /**
     * Return the user whose credentials match. An unknown email, a wrong
     * password, and a social-only user without a password all fail with the
     * same message, so the response does not reveal which accounts exist.
     *
     * @throws ValidationException on the `email` field when the credentials do not match
     */
    public function handle(string $email, string $password): User
    {
        $user = User::query()->where('email', Str::lower($email))->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        return $user;
    }
}
