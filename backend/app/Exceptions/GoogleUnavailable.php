<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Throwable;

/**
 * Thrown when Google cannot be reached to verify a sign-in, rendered as a 503
 * so the client knows to retry instead of treating the token as invalid.
 */
class GoogleUnavailable extends ServiceUnavailableHttpException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct(
            message: 'Layanan Google sedang tidak bisa dihubungi. Silakan coba lagi.',
            previous: $previous,
        );
    }
}
