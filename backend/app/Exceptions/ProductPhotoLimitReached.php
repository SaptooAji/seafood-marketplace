<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Thrown when a product photo upload is refused before the photo is resized
 * because a limit is used up: the user's per-minute limit or the store's
 * daily quota. Rendered as a 429 with a Retry-After header.
 */
class ProductPhotoLimitReached extends TooManyRequestsHttpException
{
    public static function tooFrequent(int $retryAfter): self
    {
        return new self($retryAfter, 'Terlalu sering mengunggah foto. Tunggu sebentar lalu coba lagi.');
    }

    public static function storeQuotaUsed(int $retryAfter): self
    {
        return new self($retryAfter, 'Kuota unggah foto toko hari ini sudah habis. Coba lagi besok.');
    }
}
