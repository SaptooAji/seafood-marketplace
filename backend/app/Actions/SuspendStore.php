<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\StoreStatus;
use App\Exceptions\InvalidStoreStatusTransition;
use App\Models\Store;

/**
 * An admin temporarily stops an active store from selling.
 */
class SuspendStore
{
    public function __construct(private ChangeStoreStatus $changeStatus) {}

    /**
     * Suspend the store. `verified_at` is kept so a later reactivation restores it as is.
     *
     * @throws InvalidStoreStatusTransition when the store is not active
     */
    public function handle(Store $store): Store
    {
        return $this->changeStatus->handle($store, StoreStatus::Suspended);
    }
}
