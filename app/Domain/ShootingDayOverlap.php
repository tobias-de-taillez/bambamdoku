<?php

namespace App\Domain;

use App\Models\ShootingDay;

/**
 * Zwei Schießtage am selben Datum dürfen sich nicht zeitlich überschneiden,
 * wenn sie einen Stand teilen – sonst zählten Bahnen doppelt.
 * Abgesagte Tage zählen nicht.
 */
final class ShootingDayOverlap
{
    /**
     * Der erste bestehende Schießtag, mit dem sich der geplante überschneidet, sonst null.
     *
     * @param  array<int, int|string>  $rangeIds
     */
    public static function find(string $date, TimeWindow $window, array $rangeIds, ?ShootingDay $except = null): ?ShootingDay
    {
        return ShootingDay::query()
            ->whereDate('date', $date)
            ->whereNull('cancelled_at')
            ->when($except, fn ($q) => $q->whereKeyNot($except?->id))
            ->whereHas('ranges', fn ($q) => $q->whereIn('ranges.id', $rangeIds))
            ->get()
            ->first(fn (ShootingDay $other) => TimeWindow::fromStrings($other->starts_at, $other->ends_at)->overlaps($window));
    }

    public static function message(ShootingDay $clash): string
    {
        return __('Am :date gibt es schon einen Schießtag (:from–:to Uhr) auf denselben Ständen.', [
            'date' => $clash->date->format('d.m.Y'),
            'from' => substr($clash->starts_at, 0, 5),
            'to' => substr($clash->ends_at, 0, 5),
        ]);
    }
}
