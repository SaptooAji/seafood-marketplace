<?php

declare(strict_types=1);

use App\Enums\StockMovementType;

it('keeps the stored values unchanged', function () {
    expect(array_map(fn (StockMovementType $case) => $case->value, StockMovementType::cases()))->toBe([
        'stock_in',
        'sale_app',
        'sale_offline',
        'shrinkage',
        'adjustment',
        'cancel_return',
    ]);
});

it('has a unique, non-empty label for every case', function () {
    $labels = array_map(fn (StockMovementType $case) => $case->label(), StockMovementType::cases());

    expect($labels)->each->not->toBeEmpty()
        ->and(array_unique($labels))->toHaveCount(count($labels));
});
