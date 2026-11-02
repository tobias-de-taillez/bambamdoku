<?php

namespace App\Domain;

use App\Enums\BookingStatus;
use App\Enums\WeaponType;
use App\Models\Booking;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Schießkladde eines Mitglieds: die von der Standaufsicht bestätigten Besuche
 * und ihre Auswertung nach § 14 Abs. 3 und 4 WaffG.
 *
 * Die Auswertung ist eine Orientierung für Mitglied und Verein. Maßgeblich
 * bleibt die Bescheinigung des Schießsportverbands.
 */
final class ShootingRecord
{
    public function __construct(public readonly User $user, public readonly CarbonImmutable $asOf) {}

    public static function for(User $user, ?CarbonImmutable $asOf = null): self
    {
        return new self($user, $asOf ?? CarbonImmutable::today());
    }

    /**
     * Bestätigte Besuche, neueste zuerst.
     *
     * @return Collection<int, Booking>
     */
    public function entries(?CarbonImmutable $from = null, ?CarbonImmutable $to = null): Collection
    {
        return Booking::query()
            ->where('user_id', $this->user->id)
            ->where('status', BookingStatus::CheckedIn)
            ->whereHas('shootingDay', fn ($q) => $q
                ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
                ->whereDate('date', '<=', $to ?? $this->asOf))
            ->with(['shootingDay', 'range', 'checkedInBy'])
            ->get()
            ->sortByDesc(fn (Booking $b) => $b->shootingDay->date->toDateString().$b->starts_at)
            ->values();
    }

    /**
     * Ein Schießtag zählt einmal, auch wenn mehrere Buchungen bestätigt wurden.
     *
     * @param  Collection<int, Booking>  $entries
     * @return Collection<int, string> Datumswerte (Y-m-d), aufsteigend
     */
    private static function days(Collection $entries): Collection
    {
        return $entries->map(fn (Booking $b) => $b->shootingDay->date->toDateString())->unique()->sort()->values();
    }

    /**
     * § 14 Abs. 3 Nr. 2: in den letzten zwölf Monaten einmal in jedem ganzen Monat oder 18-mal insgesamt,
     * mit erlaubnispflichtigen Schusswaffen.
     *
     * @return array<string, mixed>
     */
    public function acquisitionCheck(): array
    {
        $end = $this->asOf->startOfMonth()->subDay();
        $start = $end->subMonthsNoOverflow(11)->startOfMonth();
        $rollingStart = $this->asOf->subYear()->addDay();

        $licensed = $this->entries($rollingStart)->filter(fn (Booking $b) => $b->weapon_type->requiresLicense());
        $days = self::days($licensed);

        $months = [];
        for ($month = $start; $month->lte($end); $month = $month->addMonth()) {
            $key = $month->format('Y-m');
            $months[] = [
                'month' => $key,
                'count' => $this->entries($month, $month->endOfMonth())
                    ->filter(fn (Booking $b) => $b->weapon_type->requiresLicense())
                    ->pipe(fn ($entries) => self::days($entries))
                    ->count(),
            ];
        }

        $monthsCovered = count(array_filter($months, fn (array $m) => $m['count'] > 0));

        return [
            'from' => $rollingStart->toDateString(),
            'to' => $this->asOf->toDateString(),
            'total' => $days->count(),
            'months' => $months,
            'months_covered' => $monthsCovered,
            'every_month' => $monthsCovered === 12,
            'eighteen' => $days->count() >= 18,
            'fulfilled' => $monthsCovered === 12 || $days->count() >= 18,
        ];
    }

    /**
     * § 14 Abs. 4 Satz 1: in den letzten 24 Monaten mit eigener Waffe mindestens einmal alle drei Monate
     * oder sechsmal innerhalb eines Zeitraums von zwölf Monaten – je Waffenkategorie.
     *
     * @return list<array<string, mixed>>
     */
    public function possessionCheck(): array
    {
        $from = $this->asOf->subMonthsNoOverflow(24)->addDay();
        $entries = $this->entries($from)->filter(fn (Booking $b) => ! $b->club_weapon);

        return array_map(function (WeaponType $type) use ($entries, $from) {
            $days = self::days($entries->filter(fn (Booking $b) => $b->weapon_type === $type));

            $quarters = [];
            for ($i = 0; $i < 8; $i++) {
                $qStart = $from->addMonthsNoOverflow($i * 3);
                $qEnd = $i === 7 ? $this->asOf : $from->addMonthsNoOverflow(($i + 1) * 3)->subDay();
                $quarters[] = [
                    'from' => $qStart->toDateString(),
                    'to' => $qEnd->toDateString(),
                    'count' => $days->filter(fn (string $d) => $d >= $qStart->toDateString() && $d <= $qEnd->toDateString())->count(),
                ];
            }

            $bestTwelveMonths = $days->map(function (string $start) use ($days) {
                $end = CarbonImmutable::parse($start)->addYear()->subDay()->toDateString();

                return $days->filter(fn (string $d) => $d >= $start && $d <= $end)->count();
            })->max() ?? 0;

            $everyQuarter = collect($quarters)->every(fn (array $q) => $q['count'] > 0);

            return [
                'weapon_type' => $type->value,
                'label' => $type->label(),
                'total' => $days->count(),
                'quarters' => $quarters,
                'quarters_covered' => collect($quarters)->filter(fn (array $q) => $q['count'] > 0)->count(),
                'every_quarter' => $everyQuarter,
                'best_twelve_months' => $bestTwelveMonths,
                'six_in_twelve' => $bestTwelveMonths >= 6,
                'fulfilled' => $everyQuarter || $bestTwelveMonths >= 6,
            ];
        }, [WeaponType::Pistol, WeaponType::Rifle]);
    }

    /**
     * @return array<string, int>
     */
    public function totalsByWeaponType(?CarbonImmutable $from = null, ?CarbonImmutable $to = null): array
    {
        $entries = $this->entries($from, $to);

        return collect(WeaponType::cases())
            ->mapWithKeys(fn (WeaponType $type) => [$type->value => self::days($entries->filter(fn (Booking $b) => $b->weapon_type === $type))->count()])
            ->all();
    }
}
