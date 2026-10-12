<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Products\Actions;

use App\Actions\BlockProduct;
use App\Actions\UnblockProduct;
use App\Exceptions\InvalidProductData;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Block and unblock, for the table and the view page. Both are allowed by
 * ProductPolicy::moderate only, which store members never get.
 */
class ProductModerationActions
{
    /**
     * @return list<Action>
     */
    public static function all(): array
    {
        return [self::block(), self::unblock()];
    }

    public static function block(): Action
    {
        return Action::make('block')
            ->label('Blokir')
            ->color('danger')
            ->authorize('moderate')
            ->visible(fn (Product $record): bool => $record->blocked_at === null)
            ->modalDescription('Produk hilang dari katalog. Penjual melihat alasannya dan tidak bisa mengaktifkannya sampai blokir dibuka.')
            ->schema([
                Textarea::make('reason')
                    ->label('Alasan blokir')
                    ->required()
                    ->maxLength(BlockProduct::MAX_REASON_LENGTH),
            ])
            ->action(function (Product $record, array $data, Action $action): void {
                try {
                    app(BlockProduct::class)->handle($record, (string) $data['reason']);
                } catch (InvalidProductData $e) {
                    throw ValidationException::withMessages([
                        "mountedActions.{$action->getNestingIndex()}.data.{$e->field}" => $e->getMessage(),
                    ]);
                }

                Notification::make()->success()->title('Produk diblokir.')->send();
            });
    }

    public static function unblock(): Action
    {
        return Action::make('unblock')
            ->label('Buka blokir')
            ->color('success')
            ->authorize('moderate')
            ->requiresConfirmation()
            ->visible(fn (Product $record): bool => $record->blocked_at !== null)
            ->modalDescription('Produk tampil lagi di katalog bila penjual masih mengaktifkannya.')
            ->action(function (Product $record): void {
                app(UnblockProduct::class)->handle($record);

                Notification::make()->success()->title('Blokir dibuka.')->send();
            });
    }
}
