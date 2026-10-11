<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\StoreStatus;
use App\Exceptions\InvalidStoreStatusTransition;
use App\Models\Store;

/**
 * An admin rejects a pending store. Rejection is final in the MVP.
 */
class RejectStore
{
    /** Saved when the admin gives no reason, so the seller always sees one. */
    public const string DEFAULT_REASON = 'Tidak diketahui';

    public function __construct(private ChangeStoreStatus $changeStatus) {}

    /**
     * Reject the store with the trimmed reason, or the default when it is blank.
     *
     * @throws InvalidStoreStatusTransition when the store is not pending
     */
    public function handle(Store $store, ?string $reason = null): Store
    {
        $reason = trim($reason ?? '');

        return $this->changeStatus->handle($store, StoreStatus::Rejected, [
            'rejection_reason' => $reason !== '' ? $reason : self::DEFAULT_REASON,
        ]);
    }
}
