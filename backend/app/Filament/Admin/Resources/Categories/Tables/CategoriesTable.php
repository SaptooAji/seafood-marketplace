<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Categories\Tables;

use App\Filament\Admin\Resources\Categories\CategoryResource;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CategoriesTable
{
    /**
     * Categories in their display order; rows can be dragged to change `sort_order`.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('parent')->withCount('children'))
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('parent.name')->label('Induk')->placeholder('-'),
                TextColumn::make('children_count')->label('Subkategori'),
                TextColumn::make('sort_order')->label('Urutan')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->recordActions([
                EditAction::make(),
                CategoryResource::deleteAction(),
            ]);
    }
}
