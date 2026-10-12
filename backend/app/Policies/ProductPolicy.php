<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Product;
use App\Models\Store;
use App\Models\User;

/**
 * Store members, owner and staff alike, manage the store's products (PRD:
 * staff handle products, stock, and orders). Platform admins see every
 * product and moderate it through the Shield permissions, but never get the
 * seller's update or delete: blocking is its own ability.
 */
class ProductPolicy
{
    /**
     * Members may list the products of their store; with no store given (the
     * admin panel), admins with the Shield permission may list every product.
     */
    public function viewAny(User $user, ?Store $store = null): bool
    {
        if ($store === null) {
            return $user->can('ViewAny:Product');
        }

        return $user->roleIn($store) !== null;
    }

    public function view(User $user, Product $product): bool
    {
        return $user->can('View:Product') || $user->roleIn($product->store) !== null;
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

    /**
     * Only admins may block or unblock a product, never its own store.
     */
    public function moderate(User $user, Product $product): bool
    {
        return $user->can('Update:Product');
    }
}
