<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * Thrown when an uploaded product photo cannot be read as an image. `field` is
 * the input at fault, so the API and panels can show it on that field.
 */
class InvalidProductPhoto extends Exception
{
    public function __construct(public readonly string $field, string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }
}
