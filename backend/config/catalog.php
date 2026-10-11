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

];
