<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Thrown when product or variant input breaks a catalog rule. `field` is the
 * input at fault (for example `category_id` or `attributes.12`), so the API and
 * panels can show it on that field.
 */
class InvalidProductData extends Exception
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}
