<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\StoreRole;
use App\Models\Store;
use App\Models\StoreShippingMethod;
use App\Models\User;

/**
 * Store members may see the shipping methods; only the owner may change them.
 */
class StoreShippingMethodPolicy
{
    /**
     * Any member of the store, owner or staff, may list its methods.
     */
    public function viewAny(User $user, Store $store): bool
    {
        return $user->roleIn($store) !== null;
    }

    /**
     * Only the owner may add a method to the store or replace one of the same type.
     */
    public function create(User $user, Store $store): bool
    {
        return $user->roleIn($store) === StoreRole::Owner;
    }

    /**
     * Only the owner of the method's store may change it.
     */
    public function update(User $user, StoreShippingMethod $method): bool
    {
        return $user->roleIn($method->store) === StoreRole::Owner;
    }
}
