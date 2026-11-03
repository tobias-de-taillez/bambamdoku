<?php

namespace App\Domain;

use Carbon\CarbonImmutable;

/**
 * Wiederholungsregeln für Schießtage: jede Woche, alle zwei Wochen, monatlich am selben
 * Wochentag (z. B. jeden 1. und 3. Samstag) oder monatlich am selben Tag.
 *
 * Rein rechnerisch, ohne „heute“: Die Grenzen (ab heute, höchstens ein Jahr) prüft der Request.
 * Das Formular rechnet die Vorschau mit denselben Regeln nach; maßgeblich ist diese Klasse.
 */
final class Recurrence
{
    public const NONE = 'none';

    public const WEEKLY = 'weekly';

    public const BIWEEKLY = 'biweekly';

    public const MONTHLY_WEEKDAY = 'monthly_weekday';

    public const MONTHLY_DAY = 'monthly_day';

    public const PATTERNS = [self::NONE, self::WEEKLY, self::BIWEEKLY, self::MONTHLY_WEEKDAY, self::MONTHLY_DAY];

    /** Wochen im Monat: der 1. bis 4. Wochentag und der letzte. */
    public const WEEKS = ['1', '2', '3', '4', 'last'];

    private const WEEKDAYS = [0 => 'Sonntag', 1 => 'Montag', 2 => 'Dienstag', 3 => 'Mittwoch', 4 => 'Donnerstag', 5 => 'Freitag', 6 => 'Samstag'];

    /**
     * Alle Termine der Serie ab dem Startdatum bis einschließlich $until, aufsteigend und ohne Doppelte.
     *
     * @param  array<int, int|string>  $weeks  nur für monatlich am selben Wochentag
     * @return list<string> Datumsangaben als JJJJ-MM-TT
     */
    public static function dates(string $start, string $pattern, ?string $until = null, array $weeks = []): array
    {
        $from = CarbonImmutable::parse($start)->startOfDay();
        $to = $until !== null ? CarbonImmutable::parse($until)->startOfDay() : null;

        if ($pattern === self::NONE || $to === null || $to->lt($from)) {
            return [$from->toDateString()];
        }

        $dates = match ($pattern) {
            self::WEEKLY => self::every($from, $to, 7),
            self::BIWEEKLY => self::every($from, $to, 14),
            self::MONTHLY_WEEKDAY => self::monthlyWeekday($from, $to, self::normalizeWeeks($weeks)),
            self::MONTHLY_DAY => self::monthlyDay($from, $to),
            default => [$from],
        };

        $strings = array_values(array_unique(array_map(fn (CarbonImmutable $date) => $date->toDateString(), $dates)));
        sort($strings);

        return $strings;
    }

    /**
     * Woche im Monat (1 bis 5): der wievielte dieses Wochentags im Monat der Tag ist.
     */
    public static function weekOfMonth(CarbonImmutable $date): int
    {
        return intdiv($date->day - 1, 7) + 1;
    }

    /**
     * Ob der Tag der letzte seines Wochentags im Monat ist.
     */
    public static function isLastOfMonth(CarbonImmutable $date): bool
    {
        return $date->day + 7 > $date->daysInMonth;
    }

    /**
     * Ob das Startdatum zu den angekreuzten Wochen passt.
     *
     * @param  array<int, int|string>  $weeks
     */
    public static function matchesWeeks(CarbonImmutable $date, array $weeks): bool
    {
        $weeks = self::normalizeWeeks($weeks);

        return in_array((string) self::weekOfMonth($date), $weeks, true)
            || (in_array('last', $weeks, true) && self::isLastOfMonth($date));
    }

    /**
     * Die Woche, die das Formular für ein Startdatum vorschlägt.
     */
    public static function defaultWeek(CarbonImmutable $date): string
    {
        $week = self::weekOfMonth($date);

        return $week > 4 ? 'last' : (string) $week;
    }

    /**
     * Angekreuzte Wochen als Strings '1' bis '4' und 'last', ohne Doppelte, in fester Reihenfolge.
     *
     * @param  array<int, mixed>  $weeks
     * @return list<string>
     */
    public static function normalizeWeeks(array $weeks): array
    {
        $given = array_map(fn ($week) => is_scalar($week) ? (string) $week : '', $weeks);

        return array_values(array_filter(self::WEEKS, fn (string $week) => in_array($week, $given, true)));
    }

    /**
     * „Samstag“ für ein Datum, unabhängig von der App-Sprache.
     */
    public static function weekdayName(CarbonImmutable $date): string
    {
        return self::WEEKDAYS[$date->dayOfWeek];
    }

    /**
     * Beschreibung der Regel für Protokoll und Meldungen, z. B. „jeden 1. und 3. Samstag bis 30.10.2027“.
     *
     * @param  array<int, int|string>  $weeks
     */
    public static function describe(string $start, string $pattern, string $until, array $weeks = []): string
    {
        $from = CarbonImmutable::parse($start);
        $to = CarbonImmutable::parse($until)->format('d.m.Y');

        return match ($pattern) {
            self::WEEKLY => "jede Woche bis $to",
            self::BIWEEKLY => "alle zwei Wochen bis $to",
            self::MONTHLY_WEEKDAY => sprintf('jeden %s %s bis %s', self::weekList(self::normalizeWeeks($weeks)), self::weekdayName($from), $to),
            self::MONTHLY_DAY => sprintf('monatlich am %d. bis %s', $from->day, $to),
            default => 'einmalig',
        };
    }

    /**
     * Meldung, wenn das Startdatum keiner angekreuzten Woche entspricht.
     */
    public static function mismatchMessage(CarbonImmutable $date): string
    {
        $week = self::weekOfMonth($date);
        $position = match (true) {
            $week > 4 => 'letzte',
            self::isLastOfMonth($date) => "$week. und letzte",
            default => "$week.",
        };

        return sprintf(
            'Der %s ist der %s %s im Monat. Bitte diese Woche ankreuzen oder ein anderes Startdatum wählen.',
            $date->format('d.m.Y'),
            $position,
            self::weekdayName($date),
        );
    }

    /**
     * @return list<CarbonImmutable>
     */
    private static function every(CarbonImmutable $from, CarbonImmutable $to, int $days): array
    {
        $dates = [];

        for ($date = $from; $date->lte($to); $date = $date->addDays($days)) {
            $dates[] = $date;
        }

        return $dates;
    }

    /**
     * Je Monat die angekreuzten Wochentage; der 4. und der letzte können derselbe Tag sein.
     *
     * @param  list<string>  $weeks
     * @return list<CarbonImmutable>
     */
    private static function monthlyWeekday(CarbonImmutable $from, CarbonImmutable $to, array $weeks): array
    {
        $weekday = $from->dayOfWeek;
        $dates = [];

        for ($month = $from->startOfMonth(); $month->lte($to); $month = $month->addMonthNoOverflow()) {
            foreach ($weeks as $week) {
                $date = $week === 'last' ? $month->lastOfMonth($weekday) : $month->nthOfMonth((int) $week, $weekday);

                if ($date instanceof CarbonImmutable && $date->gte($from) && $date->lte($to)) {
                    $dates[] = $date;
                }
            }
        }

        return $dates;
    }

    /**
     * Derselbe Tag in jedem Monat; Monate ohne diesen Tag (z. B. den 31.) fallen aus.
     *
     * @return list<CarbonImmutable>
     */
    private static function monthlyDay(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $day = $from->day;
        $dates = [];

        for ($month = $from->startOfMonth(); $month->lte($to); $month = $month->addMonthNoOverflow()) {
            if ($day > $month->daysInMonth) {
                continue;
            }

            $date = $month->setDay($day);

            if ($date->gte($from) && $date->lte($to)) {
                $dates[] = $date;
            }
        }

        return $dates;
    }

    /**
     * „1. und 3.“, „1., 2. und letzten“, „letzten“.
     *
     * @param  list<string>  $weeks
     */
    private static function weekList(array $weeks): string
    {
        $labels = array_map(fn (string $week) => $week === 'last' ? 'letzten' : "$week.", $weeks);
        $last = array_pop($labels);

        return $labels === [] ? (string) $last : implode(', ', $labels).' und '.$last;
    }
}
