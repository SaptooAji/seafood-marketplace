<?php

declare(strict_types=1);

namespace App\Data;

/**
 * A product photo ready to store: both images are encoded WebP bytes.
 */
readonly class ProcessedPhoto
{
    public function __construct(
        public string $photo,
        public string $thumbnail,
    ) {}
}
