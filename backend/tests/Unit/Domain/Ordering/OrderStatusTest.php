<?php

declare(strict_types=1);

use App\Domain\Ordering\Enums\OrderStatus;

dataset('allowed transitions', [
    'awaiting confirmation to awaiting payment' => [OrderStatus::AwaitingConfirmation, OrderStatus::AwaitingPayment],
    'awaiting confirmation to cancelled' => [OrderStatus::AwaitingConfirmation, OrderStatus::Cancelled],
    'awaiting payment to payment review' => [OrderStatus::AwaitingPayment, OrderStatus::PaymentReview],
    'awaiting payment to cancelled' => [OrderStatus::AwaitingPayment, OrderStatus::Cancelled],
    'payment review to awaiting payment' => [OrderStatus::PaymentReview, OrderStatus::AwaitingPayment],
    'payment review to processing' => [OrderStatus::PaymentReview, OrderStatus::Processing],
    'payment review to scheduled' => [OrderStatus::PaymentReview, OrderStatus::Scheduled],
    'scheduled to processing' => [OrderStatus::Scheduled, OrderStatus::Processing],
    'scheduled to cancelled' => [OrderStatus::Scheduled, OrderStatus::Cancelled],
    'processing to shipped' => [OrderStatus::Processing, OrderStatus::Shipped],
    'processing to cancelled' => [OrderStatus::Processing, OrderStatus::Cancelled],
    'shipped to completed' => [OrderStatus::Shipped, OrderStatus::Completed],
]);

dataset('rejected transitions', [
    'completed is final' => [OrderStatus::Completed, OrderStatus::Processing],
    'cancelled is final' => [OrderStatus::Cancelled, OrderStatus::AwaitingPayment],
    'awaiting confirmation skips payment' => [OrderStatus::AwaitingConfirmation, OrderStatus::Processing],
    'awaiting payment skips payment review' => [OrderStatus::AwaitingPayment, OrderStatus::Processing],
    'processing back to awaiting payment' => [OrderStatus::Processing, OrderStatus::AwaitingPayment],
    'shipped back to processing' => [OrderStatus::Shipped, OrderStatus::Processing],
    'shipped cannot be cancelled' => [OrderStatus::Shipped, OrderStatus::Cancelled],
    'processing to itself' => [OrderStatus::Processing, OrderStatus::Processing],
]);

it('allows the transition', function (OrderStatus $from, OrderStatus $to) {
    expect($from->canTransitionTo($to))->toBeTrue();
})->with('allowed transitions');

it('rejects the transition', function (OrderStatus $from, OrderStatus $to) {
    expect($from->canTransitionTo($to))->toBeFalse();
})->with('rejected transitions');

it('allows exactly the transitions in the table', function () {
    $actual = [];

    foreach (OrderStatus::cases() as $from) {
        foreach (OrderStatus::cases() as $to) {
            if ($from->canTransitionTo($to)) {
                $actual[] = $from->value.' -> '.$to->value;
            }
        }
    }

    expect($actual)->toEqualCanonicalizing([
        'awaiting_confirmation -> awaiting_payment',
        'awaiting_confirmation -> cancelled',
        'awaiting_payment -> payment_review',
        'awaiting_payment -> cancelled',
        'payment_review -> awaiting_payment',
        'payment_review -> processing',
        'payment_review -> scheduled',
        'scheduled -> processing',
        'scheduled -> cancelled',
        'processing -> shipped',
        'processing -> cancelled',
        'shipped -> completed',
    ]);
});

it('never allows a transition to the same status', function (OrderStatus $status) {
    expect($status->canTransitionTo($status))->toBeFalse();
})->with(OrderStatus::cases());

it('has no transitions from a final status', function (OrderStatus $status) {
    expect($status->allowedTransitions())->toBe([]);
})->with([
    'completed' => [OrderStatus::Completed],
    'cancelled' => [OrderStatus::Cancelled],
]);

it('returns allowed transitions as a list without duplicates', function (OrderStatus $status) {
    $transitions = $status->allowedTransitions();

    expect($transitions)->toBeList()
        ->and($transitions)->toHaveCount(count(array_unique(array_map(fn (OrderStatus $s) => $s->value, $transitions))));
})->with(OrderStatus::cases());
