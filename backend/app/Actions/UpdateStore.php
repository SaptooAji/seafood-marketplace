<?php

declare(strict_types=1);

namespace App\Actions;

use App\Data\StoreData;
use App\Models\Store;

/**
 * Edits a store's profile, location, and bank account.
 */
class UpdateStore
{
    /**
     * Save the new details. The slug, status, and verification fields are not
     * part of StoreData, so they keep their values.
     */
    public function handle(Store $store, StoreData $data): Store
    {
        $store->update($data->toAttributes());

        return $store;
    }
}
