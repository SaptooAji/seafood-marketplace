<?php

declare(strict_types=1);

namespace App\Domain\Store\Enums;

/**
 * Role of a user within a store, stored in `store_members.role`.
 * In the MVP each store has exactly one owner.
 */
enum StoreRole: string
{
    case Owner = 'owner';
    case Staff = 'staff';

    /**
     * Get the label for the store role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Pemilik',
            self::Staff => 'Staf',
        };
    }
}
