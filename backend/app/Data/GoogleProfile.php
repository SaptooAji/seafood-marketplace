<?php

declare(strict_types=1);

namespace App\Data;

/**
 * The Google account details needed to sign a user in.
 */
readonly class GoogleProfile
{
    public function __construct(
        public string $id,
        public string $email,
        public string $name,
        public ?string $avatar,
        public bool $emailVerified,
    ) {}
}
