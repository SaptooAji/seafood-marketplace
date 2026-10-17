<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Stores\Tables;

use App\Enums\StoreStatus;
use App\Filament\Admin\Resources\Stores\Actions\StoreStatusActions;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StoresTable
{
    /**
     * Stores waiting for review come first, then the newest.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('city')->label('Kota')->searchable(),
                TextColumn::make('owners.name')->label('Pemilik'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (StoreStatus $state): string => $state->label())
                    ->color(fn (StoreStatus $state): string => self::statusColor($state)),
                // Read only: demo stores are only made by the demo seeder.
                TextColumn::make('is_demo')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Toko contoh' : 'Toko asli')
                    ->color(fn (bool $state): string => $state ? 'gray' : 'success'),
                TextColumn::make('rejection_reason')
                    ->label('Alasan penolakan')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Dibuat')->dateTime()->sortable(),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [StoreStatus::Pending->value])
                ->orderByDesc('created_at'))
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(StoreStatus::cases())->mapWithKeys(
                        fn (StoreStatus $status): array => [$status->value => $status->label()],
                    )),
                TernaryFilter::make('is_demo')
                    ->label('Toko contoh')
                    ->trueLabel('Hanya toko contoh')
                    ->falseLabel('Hanya toko asli'),
            ])
            ->recordActions([
                ViewAction::make(),
                ...StoreStatusActions::all(),
            ]);
    }

    /**
     * Badge color per status, shared with the view page.
     */
    public static function statusColor(StoreStatus $status): string
    {
        return match ($status) {
            StoreStatus::Pending => 'warning',
            StoreStatus::Active => 'success',
            StoreStatus::Rejected => 'danger',
            StoreStatus::Suspended => 'gray',
        };
    }
}
