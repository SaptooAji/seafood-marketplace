<?php

declare(strict_types=1);

namespace App\Domain\Account\Enums;

/**
 * Sign-in provider of a social account, stored in `social_accounts.provider`.
 * Facebook is reserved for after the MVP; only Google is used for now.
 */
enum SocialProvider: string
{
    case Google = 'google';
    case Facebook = 'facebook';

    /**
     * Get the label for the social provider.
     */
    public function label(): string
    {
        return match ($this) {
            self::Google => 'Google',
            self::Facebook => 'Facebook',
        };
    }
}
