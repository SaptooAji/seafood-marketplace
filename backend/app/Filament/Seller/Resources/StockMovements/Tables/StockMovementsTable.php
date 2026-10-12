<?php

declare(strict_types=1);

namespace App\Filament\Seller\Resources\StockMovements\Tables;

use App\Enums\StockMovementType;
use App\Filament\Seller\Resources\StockMovements\StockMovementResource;
use App\Models\StockMovement;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The stock book, newest first. Read only: no record actions.
 */
class StockMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query) => $query->orderByDesc('created_at')->orderByDesc('id'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i'),
                TextColumn::make('variant.product.name')
                    ->label('Produk')
                    ->description(fn (StockMovement $record): string => $record->variant->name),
                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (StockMovementType $state): string => $state->label())
                    ->color(fn (StockMovementType $state): string => match ($state->direction()) {
                        1 => 'success',
                        -1 => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('qty')
                    ->label('Jumlah'),
                TextColumn::make('stock_after')
                    ->label('Stok setelah'),
                TextColumn::make('supplier_name')
                    ->label('Penyetor')
                    ->placeholder('-'),
                TextColumn::make('unit_cost')
                    ->label('Harga beli')
                    ->money('IDR', locale: 'id')
                    ->placeholder('-'),
                TextColumn::make('createdBy.name')
                    ->label('Dicatat oleh')
                    ->placeholder('Sistem'),
                TextColumn::make('note')
                    ->label('Catatan')
                    ->limit(40)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Jenis')
                    ->options(collect(StockMovementType::cases())->mapWithKeys(
                        fn (StockMovementType $type): array => [$type->value => $type->label()],
                    )),
                SelectFilter::make('variant_id')
                    ->label('Varian')
                    ->options(fn (): array => StockMovementResource::variantOptions(withTrashed: true))
                    ->searchable(),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from')->label('Dari'),
                        DatePicker::make('until')->label('Sampai'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, string $date) => $query->whereDate('created_at', '<=', $date))),
            ]);
    }
}
