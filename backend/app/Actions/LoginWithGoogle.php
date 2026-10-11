<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\GoogleProfile;
use App\Enums\SocialProvider;
use App\Exceptions\UnverifiedSocialEmail;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Signs a buyer in with a Google profile, shared by the web callback and the API.
 */
class LoginWithGoogle
{
    /**
     * Find the user by linked Google account, then by email (case-insensitive),
     * or create a passwordless verified user. Links the Google account when it
     * is new and refreshes the avatar on every login; the stored name is kept.
     *
     * @throws UnverifiedSocialEmail when Google has not verified the email
     */
    public function handle(GoogleProfile $profile): User
    {
        if (! $profile->emailVerified) {
            throw new UnverifiedSocialEmail;
        }

        return DB::transaction(function () use ($profile): User {
            $user = SocialAccount::query()
                ->where('provider', SocialProvider::Google)
                ->where('provider_id', $profile->id)
                ->first()
                ?->user;

            if ($user) {
                $user->update(['avatar' => $profile->avatar]);

                return $user;
            }

            $user = User::query()
                ->whereRaw('LOWER(email) = ?', [Str::lower($profile->email)])
                ->first();

            if ($user) {
                $user->update(['avatar' => $profile->avatar]);
            } else {
                $user = User::query()->create([
                    'name' => $profile->name,
                    'email' => Str::lower($profile->email),
                    'avatar' => $profile->avatar,
                ]);
                $user->markEmailAsVerified();
            }

            $user->socialAccounts()->create([
                'provider' => SocialProvider::Google,
                'provider_id' => $profile->id,
            ]);

            return $user;
        });
    }
}
