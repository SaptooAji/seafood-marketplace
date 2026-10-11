<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * Thrown when a social provider reports the email as unverified, so it cannot
 * be trusted to link or create an account.
 */
class UnverifiedSocialEmail extends Exception
{
    protected $message = 'Email Google Anda belum diverifikasi. Silakan verifikasi email Anda terlebih dahulu.';
}
