<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Throwable;

/**
 * Thrown when an AI description suggestion cannot be made: the feature is not
 * configured, Gemini cannot be reached or refuses, or its answer is unusable.
 * Rendered as a 503 with a general message; the seller can try again or
 * write the description themselves. The details are logged by the caller,
 * never put in the message.
 */
class ProductDescriptionUnavailable extends ServiceUnavailableHttpException
{
    public function __construct(?Throwable $previous = null)
    {
        parent::__construct(
            message: 'Saran deskripsi sedang tidak tersedia. Silakan coba lagi nanti atau tulis deskripsi sendiri.',
            previous: $previous,
        );
    }
}
