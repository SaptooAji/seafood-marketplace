<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Stale Stock
    |--------------------------------------------------------------------------
    |
    | A variant whose stock was last confirmed longer ago than this is shown
    | to buyers with a "stock not confirmed" badge. Moves to the platform
    | settings table in Tahap 5.
    |
    */

    'stale_stock_hours' => 24,

    /*
    |--------------------------------------------------------------------------
    | Product Photos
    |--------------------------------------------------------------------------
    |
    | A product holds up to `max_photos` photos of at most `max_photo_kb` each.
    | Uploads are stored as WebP: the photo's longest side fits within
    | `photo_max_side` pixels and the thumbnail's within `thumbnail_side`.
    | Smaller photos are never enlarged.
    |
    */

    'max_photos' => 8,

    'max_photo_kb' => 5120,

    'photo_max_side' => 1600,

    'thumbnail_side' => 400,

    'photo_quality' => 80,

    /*
    |--------------------------------------------------------------------------
    | Product Photo Standard
    |--------------------------------------------------------------------------
    |
    | The least a product photo must be, so the buyer pages look tidy whatever
    | is uploaded (#236):
    |
    | - `photo_min_side`: the shorter side in pixels. A smaller photo looks
    |   blurry once the product page shows it large.
    | - `photo_max_ratio`: the longer side over the shorter one. Cards and
    |   thumbnails are square, so a very tall or wide photo would lose most of
    |   itself there; 4:3 matches the crop choices of the Android app and the
    |   panel editor (1:1, 4:3, 3:4).
    | - `photo_max_pixels`: checked from the file header before decoding, so
    |   a small file claiming huge dimensions (a decompression bomb) never
    |   fills the memory of GD. 40 MP is above any phone camera in common use.
    |
    */

    'photo_min_side' => 600,

    'photo_max_ratio' => 4 / 3,

    'photo_max_pixels' => 40_000_000,

];
