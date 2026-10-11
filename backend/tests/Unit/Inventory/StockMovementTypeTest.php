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

it('knows which way each type moves the stock', function (StockMovementType $type, ?int $direction) {
    expect($type->direction())->toBe($direction);
})->with([
    'stock in adds' => [StockMovementType::StockIn, 1],
    'cancel return adds' => [StockMovementType::CancelReturn, 1],
    'app sale removes' => [StockMovementType::SaleApp, -1],
    'offline sale removes' => [StockMovementType::SaleOffline, -1],
    'shrinkage removes' => [StockMovementType::Shrinkage, -1],
    'adjustment goes either way' => [StockMovementType::Adjustment, null],
]);

it('has a unique, non-empty label for every case', function () {
    $labels = array_map(fn (StockMovementType $case) => $case->label(), StockMovementType::cases());

    expect($labels)->each->not->toBeEmpty()
        ->and(array_unique($labels))->toHaveCount(count($labels));
});
