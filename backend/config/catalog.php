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

];
