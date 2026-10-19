<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\PlatformRole;
use App\Events\StoreOpened;
use App\Models\User;
use App\Notifications\StoreAwaitingReview;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;

/**
 * Tells the platform admins, who verify stores, that a new one waits for
 * review. Store owners and staff are not platform roles and are not told.
 */
class NotifyAdminsOfNewStore
{
    public function handle(StoreOpened $event): void
    {
        // Not User::role(): it throws when a role is not seeded yet, which
        // would make opening a store fail.
        $admins = User::query()
            ->whereHas('roles', fn (Builder $roles) => $roles->whereIn('name', [
                PlatformRole::Admin->value,
                PlatformRole::SuperAdmin->value,
            ]))
            ->get();

        Notification::send($admins, new StoreAwaitingReview($event->store));
    }
}
