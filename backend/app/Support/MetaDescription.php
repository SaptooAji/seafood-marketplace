<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Meta descriptions for public pages, from free text written by sellers.
 * Shared by Product and Store.
 */
class MetaDescription
{
    /**
     * Search engines show about this many characters of a meta description.
     */
    public const int LENGTH = 155;

    /**
     * The text on one line, cut at a word to fit with an ellipsis.
     */
    public static function fromText(string $text): string
    {
        return Str::limit(Str::squish($text), self::LENGTH - 1, '…', preserveWords: true);
    }
}
