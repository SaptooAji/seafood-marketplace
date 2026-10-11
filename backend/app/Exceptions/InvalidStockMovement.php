<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Thrown when a stock movement's quantity or details do not fit its type.
 * `field` is the input at fault, so the API can report it on that field.
 */
class InvalidStockMovement extends Exception
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}
