<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\StoreStatus;
use App\Exceptions\InvalidStoreStatusTransition;
use App\Models\Store;

/**
 * An admin approves a pending store so it can start selling.
 */
class VerifyStore
{
    public function __construct(private ChangeStoreStatus $changeStatus) {}

    /**
     * Activate the store and record when it was verified.
     *
     * @throws InvalidStoreStatusTransition when the store is not pending
     */
    public function handle(Store $store): Store
    {
        return $this->changeStatus->handle($store, StoreStatus::Active, ['verified_at' => now()]);
    }
}
