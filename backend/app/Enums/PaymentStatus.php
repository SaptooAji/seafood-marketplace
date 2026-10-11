<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Verification status of a payment proof, stored in `payments.status`.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    /**
     * Labels for each payment status in Indonesian.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Verifikasi Pembayaran',
            self::Approved => 'Pembayaran Disetujui',
            self::Rejected => 'Pembayaran Ditolak',
        };
    }
}
