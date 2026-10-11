<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\StoreStatus;
use App\Exceptions\InvalidStoreStatusTransition;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

/**
 * Moves a store to a new status after checking the transition is allowed.
 * Shared by VerifyStore, RejectStore, SuspendStore, and ReactivateStore.
 */
class ChangeStoreStatus
{
    /**
     * Lock the store row, check the transition against its current status in
     * the database, then save the status with any extra columns. The lock stops
     * two admins from acting on the same store at once.
     *
     * @param  array<string, mixed>  $attributes  Extra columns to set, such as `verified_at`.
     *
     * @throws InvalidStoreStatusTransition when the store cannot move to the new status
     */
    public function handle(Store $store, StoreStatus $newStatus, array $attributes = []): Store
    {
        return DB::transaction(function () use ($store, $newStatus, $attributes): Store {
            $current = Store::query()->lockForUpdate()->findOrFail($store->id)->status;

            if (! $current->canTransitionTo($newStatus)) {
                throw new InvalidStoreStatusTransition($current, $newStatus);
            }

            // Status fields are not fillable, so they are set explicitly here.
            $store->forceFill(['status' => $newStatus, ...$attributes])->save();

            return $store;
        });
    }
}
