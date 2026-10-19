<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Filament\Admin\Resources\Stores\StoreResource;
use App\Models\Store;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells an admin that a store waits for review: in the /admin bell and by
 * email, the email being the one that arrives while no browser is open.
 * Only the store's name and city and a link go out; the bank account, phone,
 * and address stay in the panel (DATA-2).
 */
class StoreAwaitingReview extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Store $store) {}

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * The bell notification, in the format Filament reads back.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(User $notifiable): array
    {
        return FilamentNotification::make()
            ->title('Toko baru menunggu review')
            ->body("{$this->store->name}, {$this->store->city}")
            ->icon('heroicon-o-building-storefront')
            ->actions([
                Action::make('view')
                    ->label('Lihat toko')
                    ->url($this->url())
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Toko baru menunggu review: {$this->store->name}")
            ->greeting('Ada toko baru')
            ->line("{$this->store->name} di {$this->store->city} menunggu review sebelum tampil ke pembeli.")
            ->action('Lihat toko', $this->url());
    }

    private function url(): string
    {
        return StoreResource::getUrl('view', ['record' => $this->store], panel: 'admin');
    }
}
