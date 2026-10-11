<?php

declare(strict_types=1);

use App\Enums\CancelledBy;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;

it('keeps the stored values unchanged', function (string $enum, array $values) {
    /** @var class-string<BackedEnum> $enum */
    expect(array_map(fn (BackedEnum $case) => $case->value, $enum::cases()))->toBe($values);
})->with([
    'order status' => [OrderStatus::class, [
        'awaiting_confirmation',
        'awaiting_payment',
        'payment_review',
        'scheduled',
        'processing',
        'shipped',
        'completed',
        'cancelled',
    ]],
    'payment status' => [PaymentStatus::class, ['pending', 'approved', 'rejected']],
    'cancelled by' => [CancelledBy::class, ['buyer', 'seller', 'admin', 'system']],
]);

it('returns null for an unknown order status', function () {
    expect(OrderStatus::tryFrom('unknown'))->toBeNull();
});

it('has a unique, non-empty label for every case', function (string $enum) {
    /** @var class-string<OrderStatus|PaymentStatus|CancelledBy> $enum */
    $labels = array_map(fn (OrderStatus|PaymentStatus|CancelledBy $case) => $case->label(), $enum::cases());

    expect($labels)->each->not->toBeEmpty()
        ->and(array_unique($labels))->toHaveCount(count($labels));
})->with([
    'order status' => [OrderStatus::class],
    'payment status' => [PaymentStatus::class],
    'cancelled by' => [CancelledBy::class],
]);
