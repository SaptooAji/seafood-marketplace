<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Thrown when a shipping method's type, fee type, and fee fields do not fit
 * together. `field` is the column at fault, so the API can report it as a
 * validation error on that field.
 */
class InvalidShippingMethod extends Exception
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}
