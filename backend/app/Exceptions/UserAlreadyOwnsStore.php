<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Thrown when a user who already owns a store tries to create another one.
 * In the MVP a user owns at most one store; branches come later.
 */
class UserAlreadyOwnsStore extends Exception
{
    protected $message = 'Anda sudah memiliki toko.';
}
