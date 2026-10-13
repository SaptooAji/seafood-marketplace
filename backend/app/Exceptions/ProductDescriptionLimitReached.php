<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Thrown when an AI description suggestion is refused before calling Gemini
 * because a limit is used up: the user's per-minute limit, the store's daily
 * quota, or the daily limit for the whole platform. Rendered as a 429 with a
 * Retry-After header.
 */
class ProductDescriptionLimitReached extends TooManyRequestsHttpException
{
    public static function tooFrequent(int $retryAfter): self
    {
        return new self($retryAfter, 'Terlalu sering meminta saran deskripsi. Tunggu sebentar lalu coba lagi.');
    }

    public static function storeQuotaUsed(int $retryAfter): self
    {
        return new self($retryAfter, 'Kuota saran deskripsi toko hari ini sudah habis. Coba lagi besok atau tulis deskripsi sendiri.');
    }

    public static function dailyLimitReached(int $retryAfter): self
    {
        return new self($retryAfter, 'Saran deskripsi sudah mencapai batas hari ini. Coba lagi besok atau tulis deskripsi sendiri.');
    }
}
