<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Products\Schemas;

use App\Enums\ProductCondition;
use App\Enums\SalesMode;
use App\Enums\Unit;
use App\Models\Product;
use App\Models\ProductImage;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * What an admin needs to moderate a product: its data, store, variants, and
 * photos. No bank details of the store.
 */
class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Produk')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('name')->label('Nama'),
                        TextEntry::make('store.name')->label('Toko'),
                        TextEntry::make('category.name')->label('Kategori'),
                        TextEntry::make('condition')
                            ->label('Kondisi')
                            ->formatStateUsing(fn (ProductCondition $state): string => $state->label()),
                        TextEntry::make('sales_mode')
                            ->label('Mode jual')
                            ->formatStateUsing(fn (SalesMode $state): string => $state->label()),
                        IconEntry::make('is_active')->label('Diaktifkan penjual')->boolean(),
                        TextEntry::make('blocked_at')
                            ->label('Diblokir sejak')
                            ->dateTime('d M Y H:i')
                            ->placeholder('Tidak diblokir'),
                        TextEntry::make('block_reason')
                            ->label('Alasan blokir')
                            ->placeholder('-'),
                        TextEntry::make('description')
                            ->label('Deskripsi')
                            ->columnSpanFull(),
                        TextEntry::make('storage_instructions')
                            ->label('Cara penyimpanan')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
                Section::make('Foto')
                    ->schema([
                        ImageEntry::make('photos')
                            ->hiddenLabel()
                            ->state(fn (Product $record): array => $record->images
                                ->map(fn (ProductImage $image): string => $image->thumbnailUrl())
                                ->all())
                            ->placeholder('Belum ada foto'),
                    ]),
                Section::make('Varian')
                    ->schema([
                        RepeatableEntry::make('variants')
                            ->hiddenLabel()
                            ->columns(4)
                            ->schema([
                                TextEntry::make('name')->label('Nama'),
                                TextEntry::make('price')->label('Harga')->money('IDR', locale: 'id'),
                                TextEntry::make('unit')
                                    ->label('Satuan')
                                    ->formatStateUsing(fn (Unit $state): string => $state->label()),
                                TextEntry::make('stock')->label('Stok'),
                            ]),
                    ]),
            ]);
    }
}
