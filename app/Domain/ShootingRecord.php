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
     * Gültige Einträge im Zeitraum, neueste zuerst; optional nur einer Disziplin (Teilnahmenachweis).
     *
     * @return Collection<int, LogEntry>
     */
    public function entries(CarbonImmutable $from, CarbonImmutable $to, ?string $discipline = null): Collection
    {
        return LogEntry::query()
            ->active()
            ->where('user_id', $this->user->id)
            ->when($discipline !== null, fn ($q) => $q->where('discipline', $discipline))
            ->whereDate('shot_on', '>=', $from)
            ->whereDate('shot_on', '<=', $to)
            ->with(['recordedBy:id,name', 'sessionEntry', 'corrects.recordedBy:id,name', 'corrects.sessionEntry'])
            ->orderByDesc('shot_on')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Stornierte Einträge im Zeitraum, nur für die Ansicht des Mitglieds (K8): nie in Zählung und Auszug (K13).
     * Ohne Zeilen, die eine Korrektur ersetzt hat; dort steht die Korrektur (wie im Session-Protokoll).
     *
     * @return Collection<int, LogEntry>
     */
    public function cancelled(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return LogEntry::query()
            ->where('status', '!=', 'active')
            ->where('user_id', $this->user->id)
            ->whereDate('shot_on', '>=', $from)
            ->whereDate('shot_on', '<=', $to)
            ->whereNotExists(fn ($q) => $q->from('log_entries as c')->whereColumn('c.corrects_id', 'log_entries.id'))
            ->with(['recordedBy:id,name', 'cancelledBy:id,name', 'sessionEntry', 'corrects.recordedBy:id,name', 'corrects.sessionEntry'])
            ->orderByDesc('shot_on')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Disziplinen mit gültigen Einträgen, für die Knöpfe „Teilnahmenachweis“. Ohne Katalog leer (E9).
     *
     * @param  Collection<int, LogEntry>  $entries
     * @return list<array{key: string, label: string, days: int}>
     */
    public static function disciplines(Collection $entries): array
    {
        $catalog = Disciplines::all();
        $list = [];

        foreach ($catalog as $key => $name) {
            $days = $entries->filter(fn (LogEntry $e) => $e->discipline === $key)
                ->map(fn (LogEntry $e) => $e->shot_on->toDateString())->unique()->count();

            if ($days > 0) {
                $list[] = ['key' => (string) $key, 'label' => "{$key} {$name}", 'days' => $days];
            }
        }

        return $list;
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
