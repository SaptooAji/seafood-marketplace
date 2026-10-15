<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * When a seller last checked a stock, as buyers read it: in WIB, the time for
 * a check made today and the date for an earlier one.
 */
class StockCheckTime
{
    public const string TIMEZONE = 'Asia/Jakarta';

    /**
     * `06.30` for today in WIB, `14 Okt` for an earlier day.
     */
    public static function format(CarbonInterface $checkedAt): string
    {
        $local = $checkedAt->avoidMutation()->setTimezone(self::TIMEZONE)->locale('id');

        return $local->isSameDay(now(self::TIMEZONE))
            ? $local->format('H.i')
            : $local->translatedFormat('j M');
    }
}
