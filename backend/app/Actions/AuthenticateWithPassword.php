<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;

/**
 * Checks an email and password pair without starting a session.
 */
class AuthenticateWithPassword
{
    /**
     * Every failure takes at least this long, longer than a password hash
     * check, so an unknown email cannot be told apart by how fast it fails.
     * Laravel's session guard and Filament's login do the same.
     */
    private const int FAILURE_MICROSECONDS = 400_000;

    public function __construct(private readonly Timebox $timebox) {}

    /**
     * Return the user whose credentials match. An unknown email, a wrong
     * password, and a social-only user without a password all fail with the
     * same message and after the same minimum time, so neither the response
     * nor its timing reveals which accounts exist. A success returns at once.
     *
     * @throws ValidationException on the `email` field when the credentials do not match
     */
    public function handle(string $email, string $password): User
    {
        return $this->timebox->call(function (Timebox $timebox) use ($email, $password): User {
            $user = User::query()->where('email', Str::lower($email))->first();

            if (! $user || ! Hash::check($password, $user->password)) {
                throw ValidationException::withMessages([
                    'email' => __('auth.failed'),
                ]);
            }

            $timebox->returnEarly();

            return $user;
        }, self::FAILURE_MICROSECONDS);
    }
}
