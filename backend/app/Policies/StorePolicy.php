<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PlatformRole;
use App\Enums\StoreRole;
use App\Models\Store;
use App\Models\User;

/**
 * Access to stores for sellers (store members) and for platform admins in the
 * Filament panel, since a model has only one policy. Admin read access comes
 * from Shield permissions (`ViewAny:Store`, `View:Store`), granted by RoleSeeder.
 */
class StorePolicy
{
    /**
     * Only admins with the Shield permission may list every store.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('ViewAny:Store');
    }

    /**
     * Any member of the store, owner or staff, may view it, and so may admins
     * with the Shield permission.
     */
    public function view(User $user, Store $store): bool
    {
        return $user->can('View:Store') || $user->roleIn($store) !== null;
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

    /**
     * Only platform admins may verify, reject, suspend, or reactivate a store,
     * never its own members.
     */
    public function changeStatus(User $user, Store $store): bool
    {
        return $user->hasAnyRole([PlatformRole::SuperAdmin, PlatformRole::Admin]);
    }
}
