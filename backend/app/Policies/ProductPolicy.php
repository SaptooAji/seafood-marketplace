<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Product;
use App\Models\Store;
use App\Models\User;

/**
 * Store members, owner and staff alike, manage the store's products (PRD:
 * staff handle products, stock, and orders). Admin moderation abilities are
 * added in #97, since a model has only one policy.
 */
class ProductPolicy
{
    /**
     * Members may list the products of their store.
     */
    public function viewAny(User $user, Store $store): bool
    {
        return $user->roleIn($store) !== null;
    }

    public function view(User $user, Product $product): bool
    {
        return $user->roleIn($product->store) !== null;
    }

    /**
     * Members may add products to their store.
     */
    public function create(User $user, Store $store): bool
    {
        return $user->roleIn($store) !== null;
    }

    public function update(User $user, Product $product): bool
    {
        return $user->roleIn($product->store) !== null;
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->roleIn($product->store) !== null;
    }
}
