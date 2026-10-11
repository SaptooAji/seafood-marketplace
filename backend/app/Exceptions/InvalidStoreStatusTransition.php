<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\StoreStatus;
use Exception;

/**
 * Thrown when a store status change is not allowed by StoreStatus::canTransitionTo().
 */
class InvalidStoreStatusTransition extends Exception
{
    public function __construct(public readonly StoreStatus $from, public readonly StoreStatus $to)
    {
        parent::__construct("Status toko tidak bisa diubah dari {$from->label()} ke {$to->label()}.");
    }
}
