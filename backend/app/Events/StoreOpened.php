<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Store;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A seller opened a store, which now waits for an admin to review it.
 * Dispatched after the transaction commits, so a store that is rolled back
 * never announces itself.
 */
class StoreOpened implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public Store $store) {}
}
