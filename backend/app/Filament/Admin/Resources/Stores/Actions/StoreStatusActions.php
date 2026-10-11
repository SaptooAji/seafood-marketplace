<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Stores\Actions;

use App\Actions\ReactivateStore;
use App\Actions\RejectStore;
use App\Actions\SuspendStore;
use App\Actions\VerifyStore;
use App\Enums\StoreStatus;
use App\Exceptions\InvalidStoreStatusTransition;
use App\Models\Store;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

/**
 * The admin actions that change a store's status, shared by the table and the
 * view page. Each one only calls its domain Action; the transition rules live
 * in StoreStatus and ChangeStoreStatus.
 */
class StoreStatusActions
{
    /**
     * @return list<Action>
     */
    public static function all(): array
    {
        return [
            self::make('verify', 'Verifikasi', StoreStatus::Pending, StoreStatus::Active, 'success')
                ->modalDescription('Toko akan aktif dan bisa mulai berjualan.')
                ->action(self::run(fn (Store $record) => app(VerifyStore::class)->handle($record))),

            self::make('reject', 'Tolak', StoreStatus::Pending, StoreStatus::Rejected, 'danger')
                ->modalDescription('Penolakan bersifat final. Penjual akan melihat alasannya.')
                ->schema([
                    Textarea::make('reason')
                        ->label('Alasan penolakan')
                        ->helperText('Kosongkan bila tidak ada; akan disimpan sebagai "'.RejectStore::DEFAULT_REASON.'".')
                        ->maxLength(500),
                ])
                ->action(self::run(fn (Store $record, array $data) => app(RejectStore::class)->handle($record, $data['reason'] ?? null))),

            self::make('suspend', 'Tangguhkan', StoreStatus::Active, StoreStatus::Suspended, 'warning')
                ->modalDescription('Toko berhenti berjualan sampai diaktifkan kembali.')
                ->action(self::run(fn (Store $record) => app(SuspendStore::class)->handle($record))),

            self::make('reactivate', 'Aktifkan kembali', StoreStatus::Suspended, StoreStatus::Active, 'success')
                ->modalDescription('Toko bisa berjualan lagi dengan tanggal verifikasi yang lama.')
                ->action(self::run(fn (Store $record) => app(ReactivateStore::class)->handle($record))),
        ];
    }

    /**
     * A confirmed action that is visible only for stores in `$from` that may
     * move to `$to`, and only to users allowed by StorePolicy::changeStatus.
     * `$from` tells apart actions with the same target, such as verify and reactivate.
     */
    private static function make(string $name, string $label, StoreStatus $from, StoreStatus $to, string $color): Action
    {
        return Action::make($name)
            ->label($label)
            ->color($color)
            ->requiresConfirmation()
            ->authorize('changeStatus')
            ->visible(fn (Store $record): bool => $record->status === $from && $record->status->canTransitionTo($to));
    }

    /**
     * Wrap a domain call so a status that changed in the meantime (another
     * admin acted first) shows a notification instead of an error page.
     *
     * @param  Closure(Store, array<string, mixed>): mixed  $callback
     * @return Closure(Store, array<string, mixed>): void
     */
    private static function run(Closure $callback): Closure
    {
        return function (Store $record, array $data) use ($callback): void {
            try {
                $callback($record, $data);
            } catch (InvalidStoreStatusTransition $e) {
                Notification::make()->danger()->title($e->getMessage())->send();

                return;
            }

            Notification::make()->success()->title('Status toko diperbarui.')->send();
        };
    }
}
