<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Stores\Schemas;

use App\Enums\StoreStatus;
use App\Filament\Admin\Resources\Stores\Tables\StoresTable;
use App\Models\Store;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StoreInfolist
{
    /**
     * Everything an admin needs to decide on a store: profile, contact and
     * location, bank account, and its verification state.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profil')->columns(2)->schema([
                    TextEntry::make('name')->label('Nama'),
                    TextEntry::make('slug'),
                    TextEntry::make('owners.name')->label('Pemilik'),
                    TextEntry::make('description')->label('Deskripsi')->placeholder('-')->columnSpanFull(),
                ]),
                Section::make('Kontak dan lokasi')->columns(2)->schema([
                    TextEntry::make('phone')->label('Telepon'),
                    TextEntry::make('city')->label('Kota'),
                    TextEntry::make('address')->label('Alamat')->columnSpanFull(),
                    TextEntry::make('coordinates')
                        ->label('Koordinat')
                        ->state(fn (Store $record): string => "{$record->lat}, {$record->lng}")
                        ->url(fn (Store $record): string => $record->googleMapsUrl())
                        ->openUrlInNewTab()
                        ->helperText('Buka di Google Maps'),
                ]),
                Section::make('Rekening')->columns(3)->schema([
                    TextEntry::make('bank_name')->label('Bank'),
                    TextEntry::make('bank_account_no')->label('Nomor rekening'),
                    TextEntry::make('bank_account_name')->label('Atas nama'),
                ]),
                Section::make('Verifikasi')->columns(2)->schema([
                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (StoreStatus $state): string => $state->label())
                        ->color(fn (StoreStatus $state): string => StoresTable::statusColor($state)),
                    TextEntry::make('verified_at')->label('Diverifikasi')->dateTime()->placeholder('-'),
                    TextEntry::make('rejection_reason')
                        ->label('Alasan penolakan')
                        ->visible(fn (Store $record): bool => $record->status === StoreStatus::Rejected)
                        ->columnSpanFull(),
                    TextEntry::make('created_at')->label('Dibuat')->dateTime(),
                ]),
            ]);
    }
}
