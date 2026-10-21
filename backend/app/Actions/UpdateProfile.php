<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

/**
 * Edits the signed-in user's own name and phone number. The email is tied to
 * the Google account and the password has its own flow, so neither is changed
 * here, and neither are the avatar or roles.
 */
class UpdateProfile
{
    /**
     * Save the new name and phone; every other column keeps its value.
     */
    public function handle(User $user, string $name, string $phone): User
    {
        $user->update(['name' => $name, 'phone' => $phone]);

        return $user;
    }
}
