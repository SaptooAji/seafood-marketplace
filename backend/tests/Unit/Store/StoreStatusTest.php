<?php

declare(strict_types=1);

use App\Enums\StoreStatus;

dataset('allowed store transitions', [
    'pending to active' => [StoreStatus::Pending, StoreStatus::Active],
    'pending to rejected' => [StoreStatus::Pending, StoreStatus::Rejected],
    'active to suspended' => [StoreStatus::Active, StoreStatus::Suspended],
    'suspended to active' => [StoreStatus::Suspended, StoreStatus::Active],
]);

dataset('rejected store transitions', [
    'active back to pending' => [StoreStatus::Active, StoreStatus::Pending],
    'rejected is final, not active' => [StoreStatus::Rejected, StoreStatus::Active],
    'rejected is final, not pending' => [StoreStatus::Rejected, StoreStatus::Pending],
    'suspended to rejected' => [StoreStatus::Suspended, StoreStatus::Rejected],
    'pending straight to suspended' => [StoreStatus::Pending, StoreStatus::Suspended],
    'active to itself' => [StoreStatus::Active, StoreStatus::Active],
]);

it('allows the transition', function (StoreStatus $from, StoreStatus $to) {
    expect($from->canTransitionTo($to))->toBeTrue();
})->with('allowed store transitions');

it('rejects the transition', function (StoreStatus $from, StoreStatus $to) {
    expect($from->canTransitionTo($to))->toBeFalse();
})->with('rejected store transitions');

it('allows exactly the transitions in the table', function () {
    $actual = [];

    foreach (StoreStatus::cases() as $from) {
        foreach (StoreStatus::cases() as $to) {
            if ($from->canTransitionTo($to)) {
                $actual[] = $from->value.' -> '.$to->value;
            }
        }
    }

    expect($actual)->toEqualCanonicalizing([
        'pending -> active',
        'pending -> rejected',
        'active -> suspended',
        'suspended -> active',
    ]);
});

it('has no transitions out of rejected', function () {
    expect(StoreStatus::Rejected->allowedTransitions())->toBe([]);
});

it('never transitions to itself', function (StoreStatus $status) {
    expect($status->canTransitionTo($status))->toBeFalse();
})->with(StoreStatus::cases());
