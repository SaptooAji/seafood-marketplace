<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Store;
use App\Models\User;

/**
 * Store members, owner and staff alike, keep the stock book (PRD: staff
 * handle products, stock, and orders). Movements are never edited or
 * deleted; a mistake is corrected with an adjustment.
 */
class StockMovementPolicy
{
    /**
     * Members may read their store's stock book.
     */
    public function viewAny(User $user, Store $store): bool
    {
        return $user->roleIn($store) !== null;
    }

    /**
     * Members may record movements for their store's variants.
     */
    public function create(User $user, Store $store): bool
    {
        return $user->roleIn($store) !== null;
    }
}
