<?php

declare(strict_types=1);

namespace App\Domain\Ordering\Enums;

/**
 * Lifecycle status of an order, stored in `orders.status`.
 *
 * This enum only defines which transitions are possible. Who may trigger a
 * transition and when (for example, a buyer cancelling a pre-order before
 * production day) is enforced by Actions and Policies.
 */
enum OrderStatus: string
{
    case AwaitingConfirmation = 'awaiting_confirmation';
    case AwaitingPayment = 'awaiting_payment';
    case PaymentReview = 'payment_review';
    case Scheduled = 'scheduled';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Labels for each order status in Indonesian.
     */
    public function label(): string
    {
        return match ($this) {
            self::AwaitingConfirmation => 'Menunggu Konfirmasi',
            self::AwaitingPayment => 'Menunggu Pembayaran',
            self::PaymentReview => 'Review Pembayaran',
            self::Scheduled => 'Dijadwalkan',
            self::Processing => 'Diproses',
            self::Shipped => 'Dikirim',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
        };
    }

    /**
     * Statuses this status may move to next. Final statuses return an empty list.
     *
     * Payment review allows both processing and scheduled; the Action picks one
     * based on the product's sales mode.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::AwaitingConfirmation => [
                self::AwaitingPayment,
                self::Cancelled,
            ],
            self::AwaitingPayment => [
                self::PaymentReview,
                self::Cancelled,
            ],
            self::PaymentReview => [
                self::AwaitingPayment,
                self::Processing,
                self::Scheduled,
            ],
            self::Scheduled => [
                self::Processing,
                self::Cancelled,
            ],
            self::Processing => [
                self::Shipped,
                self::Cancelled,
            ],
            self::Shipped => [
                self::Completed,
            ],
            self::Cancelled, self::Completed => [],
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
