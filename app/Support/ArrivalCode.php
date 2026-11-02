<?php

namespace App\Support;

use App\Models\ShootingDay;

/**
 * Wechselnder Code für den QR-Check-in am Stand. Gilt fünf Minuten (plus die
 * vorherigen fünf, damit ein gerade gewechselter Code noch funktioniert).
 * Wer den Code scannt, war also kürzlich vor Ort.
 */
class ArrivalCode
{
    public const WINDOW_SECONDS = 300;

    public static function current(ShootingDay $day, ?int $at = null): string
    {
        return self::forWindow($day, intdiv($at ?? now()->getTimestamp(), self::WINDOW_SECONDS));
    }

    public static function isValid(ShootingDay $day, string $code, ?int $at = null): bool
    {
        $window = intdiv($at ?? now()->getTimestamp(), self::WINDOW_SECONDS);

        return hash_equals(self::forWindow($day, $window), $code)
            || hash_equals(self::forWindow($day, $window - 1), $code);
    }

    private static function forWindow(ShootingDay $day, int $window): string
    {
        return substr(hash_hmac('sha256', "arrival|{$day->id}|{$window}", (string) config('app.key')), 0, 12);
    }
}
