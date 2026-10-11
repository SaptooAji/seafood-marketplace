<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Stores;

use App\Filament\Admin\Resources\Stores\Pages\ListStores;
use App\Filament\Admin\Resources\Stores\Pages\ViewStore;
use App\Filament\Admin\Resources\Stores\Schemas\StoreInfolist;
use App\Filament\Admin\Resources\Stores\Tables\StoresTable;
use App\Models\Store;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Admins review stores here: list, inspect, and change their status.
 * Store data is edited by sellers only, so there are no create or edit pages.
 */
class StoreResource extends Resource
{
    protected static ?string $model = Store::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $modelLabel = 'toko';

    protected static ?string $pluralModelLabel = 'toko';

    protected static ?string $recordTitleAttribute = 'name';

    public static function infolist(Schema $schema): Schema
    {
        return StoreInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StoresTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStores::route('/'),
            'view' => ViewStore::route('/{record}'),
        ];
    }
}
