<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Products\Tables;

use App\Filament\Admin\Resources\Products\Actions\ProductModerationActions;
use App\Models\Product;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['store', 'category']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable(),
                TextColumn::make('store.name')
                    ->label('Toko')
                    ->searchable(),
                TextColumn::make('category.name')
                    ->label('Kategori'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Product $record): string => self::status($record))
                    ->color(fn (string $state): string => match ($state) {
                        'Aktif' => 'success',
                        'Diblokir' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('block_reason')
                    ->label('Alasan blokir')
                    ->limit(40)
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Diubah')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Aktif',
                        'inactive' => 'Nonaktif',
                        'blocked' => 'Diblokir',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'active' => $query->where('is_active', true)->whereNull('blocked_at'),
                        'inactive' => $query->where('is_active', false)->whereNull('blocked_at'),
                        'blocked' => $query->whereNotNull('blocked_at'),
                        default => $query,
                    }),
                SelectFilter::make('store')
                    ->label('Toko')
                    ->relationship('store', 'name')
                    ->searchable(),
                SelectFilter::make('category')
                    ->label('Kategori')
                    ->relationship('category', 'name')
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make(),
                ...ProductModerationActions::all(),
            ]);
    }

    /**
     * A block wins over the seller's own switch.
     */
    private static function status(Product $product): string
    {
        return match (true) {
            $product->blocked_at !== null => 'Diblokir',
            $product->is_active => 'Aktif',
            default => 'Nonaktif',
        };
    }
}
