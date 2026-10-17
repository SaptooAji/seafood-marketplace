<?php

declare(strict_types=1);

use App\Enums\SalesMode;
use App\Models\Product;
use Carbon\CarbonImmutable;

/*
 * The earliest delivery date of a pre-order product (#191, also used by
 * #194 and #198): an order placed before the cutoff time counts from today,
 * after it from tomorrow, and the lead time is added to that. Times are in
 * WIB, the time the stores work in.
 */

/**
 * A pre-order product with the given lead time and cutoff, not saved.
 */
function preOrderProduct(int $leadTimeDays = 1, string $cutoff = '15:00'): Product
{
    return Product::factory()->preOrder()->make(['lead_time_days' => $leadTimeDays, 'cutoff_time' => $cutoff]);
}

/**
 * The given WIB time on Friday 16 October 2026.
 */
function fridayAt(string $time): CarbonImmutable
{
    return CarbonImmutable::parse('2026-10-16 '.$time, 'Asia/Jakarta');
}

it('counts from today before the cutoff and from tomorrow after it', function (string $now, int $lead, string $cutoff, string $expected) {
    expect(preOrderProduct($lead, $cutoff)->earliestDeliveryDate(fridayAt($now))?->toDateString())->toBe($expected);
})->with([
    'before the cutoff, one day' => ['10:00', 1, '15:00', '2026-10-17'],
    'after the cutoff, one day' => ['16:00', 1, '15:00', '2026-10-18'],
    'right at the cutoff counts as after' => ['15:00', 1, '15:00', '2026-10-18'],
    'two days lead time' => ['10:00', 2, '15:00', '2026-10-18'],
    'after the cutoff, two days' => ['16:00', 2, '14:00', '2026-10-19'],
    'cutoff with seconds from the database' => ['10:00', 1, '15:00:00', '2026-10-17'],
]);

it('works in WIB whatever the time zone of the given moment', function () {
    // 09:30 UTC is 16:30 WIB: past the 15:00 cutoff.
    $now = CarbonImmutable::parse('2026-10-16 09:30', 'UTC');

    expect(preOrderProduct()->earliestDeliveryDate($now)?->toDateString())->toBe('2026-10-18');
});

it('gives the last moment to order for a delivery date', function () {
    $product = preOrderProduct(2, '14:00');

    expect($product->orderDeadlineFor(CarbonImmutable::parse('2026-10-19', 'Asia/Jakarta'))->format('Y-m-d H:i'))->toBe('2026-10-17 14:00');
});

it('has no delivery date for a ready stock product or missing pre-order settings', function (array $attributes) {
    $product = Product::factory()->make(['sales_mode' => SalesMode::ReadyStock, ...$attributes]);

    expect($product->earliestDeliveryDate(fridayAt('10:00')))->toBeNull();
})->with([
    'ready stock' => [[]],
    'pre order without lead time' => [['sales_mode' => SalesMode::PreOrder, 'lead_time_days' => null, 'cutoff_time' => '15:00']],
    'pre order without cutoff' => [['sales_mode' => SalesMode::PreOrder, 'lead_time_days' => 1, 'cutoff_time' => null]],
]);
