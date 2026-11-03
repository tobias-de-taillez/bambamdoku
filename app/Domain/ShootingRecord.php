<?php

namespace App\Domain;

use App\Enums\WeaponClass;
use App\Enums\WeaponOwner;
use App\Models\LogEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Sicht eines Mitglieds auf die Kladde des Vereins: seine gültigen Einträge und eine
 * neutrale Zählung. Bewusst ohne Urteil „erfüllt“ – bescheinigen muss der Verband
 * (§ 14 Abs. 3 und 4, § 58 Abs. 21 WaffG), und die Auslegung der Fristen ist offen.
 */
final class ShootingRecord
{
    public function __construct(public readonly User $user) {}

    public static function for(User $user): self
    {
        return new self($user);
    }

    /**
     * Gültige Einträge im Zeitraum, neueste zuerst.
     *
     * @return Collection<int, LogEntry>
     */
    public function entries(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return LogEntry::query()
            ->active()
            ->where('user_id', $this->user->id)
            ->whereDate('shot_on', '>=', $from)
            ->whereDate('shot_on', '<=', $to)
            ->with(['recordedBy:id,name', 'sessionEntry', 'corrects.recordedBy:id,name', 'corrects.sessionEntry'])
            ->orderByDesc('shot_on')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Anzahl Tage je Kategorie, einmal alle und einmal nur mit eigener Waffe; dazu die erfassten
     * Schüsse (zählen nie mit) und wie viele Zeilen ohne Angabe sind.
     *
     * @param  Collection<int, LogEntry>  $entries
     * @return list<array{class: string, label: string, days: int, own_days: int, shots: int, shots_missing: int}>
     */
    public static function totals(Collection $entries): array
    {
        $totals = [];

        foreach (WeaponClass::cases() as $class) {
            $ofClass = $entries->filter(fn (LogEntry $e) => $e->weapon_class === $class);
            $totals[] = [
                'class' => $class->value,
                'label' => $class->label(),
                'days' => $ofClass->map(fn (LogEntry $e) => $e->shot_on->toDateString())->unique()->count(),
                'own_days' => $ofClass->filter(fn (LogEntry $e) => $e->weapon_owner === WeaponOwner::Own)
                    ->map(fn (LogEntry $e) => $e->shot_on->toDateString())->unique()->count(),
                'shots' => (int) $ofClass->sum(fn (LogEntry $e) => $e->shots ?? 0),
                'shots_missing' => $ofClass->filter(fn (LogEntry $e) => $e->shots === null)->count(),
            ];
        }

        return $totals;
    }
}
