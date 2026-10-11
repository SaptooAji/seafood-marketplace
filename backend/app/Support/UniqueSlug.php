<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Slugs for public URLs (`/toko/{slug}`, `/produk/{slug}`), made unique by
 * adding -2, -3, ... Shared by CreateStore and SaveProduct.
 */
class UniqueSlug
{
    /**
     * Slug the name, adding a number while `$query` already has the slug.
     * Pass a query that includes soft deleted rows when the column is unique
     * across them.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     */
    public static function for(Builder $query, string $name, string $fallback): string
    {
        $base = Str::slug($name) ?: $fallback;
        $slug = $base;

        for ($suffix = 2; (clone $query)->where('slug', $slug)->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
