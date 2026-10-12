<?php

declare(strict_types=1);

namespace App\Filament\Seller\Resources\Products\Tables;

use App\Actions\DeleteProduct;
use App\Models\Product;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['category', 'store'])->withCount('variants'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable(),
                TextColumn::make('category.name')
                    ->label('Kategori'),
                TextColumn::make('variants_count')
                    ->label('Varian'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Product $record): string => self::status($record))
                    ->color(fn (string $state): string => match ($state) {
                        'Aktif' => 'success',
                        'Diblokir' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('updated_at')
                    ->label('Diubah')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Aktif')
                    ->options([1 => 'Aktif', 0 => 'Nonaktif']),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->using(fn (Product $record) => app(DeleteProduct::class)->handle($record)),
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
