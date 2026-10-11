<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Verification status of a store, stored in `stores.status`.
 * New stores stay pending until an admin verifies them.
 */
enum StoreStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Rejected = 'rejected';
    case Suspended = 'suspended';

    /**
     * Get the label for the store status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Persetujuan',
            self::Active => 'Aktif',
            self::Rejected => 'Ditolak',
            self::Suspended => 'Ditangguhkan',
        };
    }

    /**
     * Statuses this status may move to next. Rejected is final in the MVP.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Active, self::Rejected],
            self::Active => [self::Suspended],
            self::Suspended => [self::Active],
            self::Rejected => [],
        };
    }

    /**
     * Whether moving from this status to the given status is allowed.
     */
    public function canTransitionTo(self $newStatus): bool
    {
        return in_array($newStatus, $this->allowedTransitions(), true);
    }
}
