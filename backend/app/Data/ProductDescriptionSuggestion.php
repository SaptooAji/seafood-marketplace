<?php

declare(strict_types=1);

namespace App\Data;

/**
 * A product description and SEO summary suggested by AI, as plain text.
 * It is only a suggestion: the seller reviews and edits it before saving.
 */
readonly class ProductDescriptionSuggestion
{
    public function __construct(
        public string $description,
        public string $seoDescription,
    ) {}
}
