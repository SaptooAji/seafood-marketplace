<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\ProductVariant;
use Exception;

/**
 * Thrown when a movement would take a variant's stock below zero.
 */
class InsufficientStock extends Exception
{
    public function __construct(public readonly ProductVariant $variant)
    {
        parent::__construct("Stok {$variant->name} tidak cukup.");
    }
}
