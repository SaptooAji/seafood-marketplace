<?php

declare(strict_types=1);

namespace App\Domain\Account\Enums;

/**
 * Platform-wide role managed with spatie/laravel-permission. The value is the
 * role name in the `roles` table; `super_admin` must match the super admin
 * name in config/filament-shield.php.
 *
 * Store roles (owner, staff) are not platform roles; see StoreRole.
 */
enum PlatformRole: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';

    /**
     * Get the label for the platform role.
     */
    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
        };
    }
}
