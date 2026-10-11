<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StoreRole;
use App\Models\Store;
use App\Models\User;

/**
 * Seller access to stores. Admin abilities for the Filament panel (#38, #39)
 * are added here too, since a model has only one policy.
 */
class StorePolicy
{
    /**
     * Any member of the store, owner or staff, may view it.
     */
    public function view(User $user, Store $store): bool
    {
        return $user->roleIn($store) !== null;
    }

    /**
     * A user may open a store only if they do not own one yet.
     * Being staff in another store does not count.
     */
    public function create(User $user): bool
    {
        return ! $user->ownsAnyStore();
    }

    /**
     * Only the owner may change the store's details.
     */
    public function update(User $user, Store $store): bool
    {
        return $user->roleIn($store) === StoreRole::Owner;
    }
}
