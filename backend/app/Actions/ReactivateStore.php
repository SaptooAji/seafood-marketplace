<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\StoreStatus;
use App\Exceptions\InvalidStoreStatusTransition;
use App\Models\Store;

/**
 * An admin lifts a suspension so the store can sell again.
 */
class ReactivateStore
{
    public function __construct(private ChangeStoreStatus $changeStatus) {}

    /**
     * Activate the store again, keeping the original `verified_at`.
     *
     * @throws InvalidStoreStatusTransition when the store is not suspended
     */
    public function handle(Store $store): Store
    {
        return $this->changeStatus->handle($store, StoreStatus::Active);
    }
}
