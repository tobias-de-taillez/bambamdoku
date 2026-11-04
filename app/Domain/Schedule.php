<?php

namespace App\Domain;

use App\Enums\BookingStatus;
use App\Enums\WeaponType;
use App\Models\Booking;
use App\Models\Range;
use App\Models\ShootingDay;
use App\Models\Supervision;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Lesesicht auf einen Schießtag: Raster, Aufsicht und Belegung je Stand.
 *
 * Erwartet, dass ranges, supervisions und bookings geladen sind oder lädt sie nach.
 */
final class Schedule
{
    /** @var Collection<int, Supervision> */
    private Collection $supervisions;

    /** @var Collection<int, Booking> */
    private Collection $bookings;

    public function __construct(public readonly ShootingDay $day)
    {
        $day->loadMissing(['ranges', 'supervisions.user', 'bookings']);
        $this->supervisions = $day->supervisions;
        $this->bookings = $day->bookings->filter(fn (Booking $booking) => $booking->status->occupiesLane());
    }

    public function window(): TimeWindow
    {
        return TimeWindow::fromStrings($this->day->starts_at, $this->day->ends_at);
    }

    public static function slotMinutes(): int
    {
        return max(15, (int) config('verein.slot_minutes', 60));
    }

    /**
     * Rasterzeitpunkte vom Beginn des Tages bis kurz vor Ende.
     *
     * @return list<TimeWindow>
     */
    public function slots(): array
    {
        $window = $this->window();
        $step = self::slotMinutes();
        $slots = [];

        for ($start = $window->start; $start < $window->end; $start += $step) {
            $slots[] = new TimeWindow($start, min($start + $step, $window->end));
        }

        return $slots;
    }

    public function isOnGrid(TimeWindow $window): bool
    {
        $day = $this->window();
        $step = self::slotMinutes();

        $startsOnGrid = ($window->start - $day->start) % $step === 0;
        $endsOnGrid = ($window->end - $day->start) % $step === 0 || $window->end === $day->end;

        return $day->contains($window) && $startsOnGrid && $endsOnGrid;
    }

    /**
     * Die eine Regel, ob eine Aufsicht zählt (G9): Sie gilt für den Stand, und die Person ist am Tag
     * aktiv und im Register, mit $licensed im Umfang erlaubnispflichtig (Kurz- und Langwaffe).
     * Geprüft wird jetzt, nicht beim Eintragen der Aufsicht – auch wer sich früher gültig
     * eingetragen hat, zählt nach Entzug, Ablauf oder Herabstufen nicht mehr (§ 10 AWaffV).
     *
     * Buchung, Planung, Standansicht und Übersicht fragen nur hierüber; den Umfang leiten
     * countsForBooking() und countsForRange() ab.
     */
    public static function counts(Supervision $supervision, Range $range, CarbonImmutable $on, bool $licensed): bool
    {
        return $supervision->covers($range) && $supervision->user->canSupervise($on, licensed: $licensed);
    }

    /**
     * Zählt die Aufsicht für die Buchung? Kurz- und Langwaffe nur mit Umfang erlaubnispflichtig.
     */
    public static function countsForBooking(Supervision $supervision, Booking $booking): bool
    {
        return self::counts($supervision, $booking->range, $booking->shootingDay->date, $booking->weapon_type->requiresLicense());
    }

    /**
     * Zählt die Aufsicht für den Stand? Ein Stand nur für Kurz- oder Langwaffe braucht den Umfang
     * erlaubnispflichtig; auf einem gemischten Stand genügt erlaubnisfrei für dessen übrige Waffen.
     */
    public static function countsForRange(Supervision $supervision, Range $range, CarbonImmutable $on): bool
    {
        return self::counts($supervision, $range, $on, self::onlyLicensed($range));
    }

    /**
     * Mit $licensed nur Aufsichten mit Umfang erlaubnispflichtig (Kurz- und Langwaffe).
     *
     * @return Collection<int, Supervision>
     */
    public function supervisionsFor(Range $range, bool $licensed = false): Collection
    {
        return $this->supervisions
            ->filter(fn (Supervision $supervision) => self::counts($supervision, $range, $this->day->date, $licensed))
            ->values();
    }

    public function isSupervised(Range $range, TimeWindow $window, bool $licensed = false): bool
    {
        return $window->isCoveredBy($this->supervisionsFor($range, $licensed)->map(fn (Supervision $s) => $s->window()));
    }

    /**
     * Beaufsichtigt für mindestens eine Waffenart des Standes. Ein Stand nur für
     * erlaubnispflichtige Waffen braucht dafür eine Aufsicht mit diesem Umfang.
     */
    public function isSupervisedForRange(Range $range, TimeWindow $window): bool
    {
        return $this->isSupervised($range, $window, licensed: self::onlyLicensed($range));
    }

    /**
     * Der Stand lässt nur Waffenarten zu, die eine Erlaubnis brauchen, z. B. „25 m Kurzwaffe“.
     */
    public static function onlyLicensed(Range $range): bool
    {
        return $range->weapon_types->isNotEmpty()
            && $range->weapon_types->every(fn (WeaponType $type) => $type->requiresLicense());
    }

    /**
     * Höchste gleichzeitige Belegung des Standes im Zeitfenster.
     */
    public function peakOccupancy(Range $range, TimeWindow $window, ?Booking $except = null): int
    {
        $overlapping = $this->bookings
            ->filter(fn (Booking $booking) => $booking->range_id === $range->id
                && $booking->id !== $except?->id
                && $booking->window()->overlaps($window));

        $peak = 0;
        $step = 15;

        for ($minute = $window->start; $minute < $window->end; $minute += $step) {
            $probe = new TimeWindow($minute, min($minute + $step, $window->end));
            $peak = max($peak, $overlapping->filter(fn (Booking $booking) => $booking->window()->overlaps($probe))->count());
        }

        return $peak;
    }

    public function freeLanes(Range $range, TimeWindow $window): int
    {
        return max(0, $range->lanes - $this->peakOccupancy($range, $window));
    }

    /**
     * Raster je Stand für die Oberfläche.
     *
     * @return array<int, array<string, mixed>>
     */
    public function grid(): array
    {
        // Heute vergangene Zeiten sind nicht mehr buchbar (siehe Bookings::book).
        $now = $this->day->date->isToday() ? TimeWindow::minutes(now()->format('H:i')) : null;

        return $this->day->ranges->sortBy([['position', 'asc'], ['name', 'asc']])->values()->map(fn (Range $range) => [
            'id' => $range->id,
            'name' => $range->name,
            'lanes' => $range->lanes,
            'weapon_types' => $range->weapon_types->map->value->values(),
            // Nur Kurz- oder Langwaffe: Aufsicht braucht den Umfang erlaubnispflichtig (G9).
            'only_licensed' => self::onlyLicensed($range),
            'slots' => array_map(fn (TimeWindow $slot) => [
                'past' => $now !== null && $slot->end <= $now,
                'start' => $slot->startString(),
                'end' => $slot->endString(),
                'free' => $this->freeLanes($range, $slot),
                'supervised' => $this->isSupervisedForRange($range, $slot),
                // Kurz- und Langwaffe nur unter einer Aufsicht mit Umfang erlaubnispflichtig.
                'licensed' => $this->isSupervised($range, $slot, licensed: true),
            ], $this->slots()),
        ])->all();
    }

    /**
     * Die Stände, nach denen sich Aufsicht und Übersicht richten: die aktiven, wie beim Buchen
     * (Bookings::book) und Übernehmen (Bookings::ensureScopeFits). Auf einem Stand außer Betrieb
     * bucht niemand. Ist an dem Tag keiner aktiv, alle zugeordneten, sonst stünde der Tag als „full“.
     *
     * @return Collection<int, Range>
     */
    private function rangesThatCount(): Collection
    {
        $active = $this->day->ranges->filter(fn (Range $range) => $range->active)->values();

        return $active->isNotEmpty() ? $active : $this->day->ranges->values();
    }

    /**
     * Aufsichten, die für mindestens einen Stand nach dessen Regel zählen (countsForRange) und in
     * die Zeiten des Tages fallen. Wer herabgestuft, entzogen oder abgelaufen ist, nur einen Stand
     * außer Betrieb hat oder nach einer Verschiebung des Tages außerhalb liegt, fehlt hier.
     *
     * @return Collection<int, Supervision>
     */
    public function countingSupervisions(): Collection
    {
        $ranges = $this->rangesThatCount();
        $window = $this->window();

        return $this->supervisions
            ->filter(fn (Supervision $supervision) => $supervision->window()->overlaps($window)
                && $ranges->contains(fn (Range $range) => self::countsForRange($supervision, $range, $this->day->date)))
            ->values();
    }

    /**
     * Ob für mindestens einen Stand zu irgendeiner Zeit eine Aufsicht zählt (G9).
     */
    public function hasAnySupervision(): bool
    {
        return $this->countingSupervisions()->isNotEmpty();
    }

    /**
     * Stände, die nicht über den ganzen Tag beaufsichtigt sind, nur die, die zählen.
     *
     * @return Collection<int, Range>
     */
    public function rangesWithoutSupervision(): Collection
    {
        $window = $this->window();

        return $this->rangesThatCount()
            ->reject(fn (Range $range) => $this->isSupervisedForRange($range, $window))
            ->values();
    }

    /**
     * full: alle Stände über den ganzen Tag beaufsichtigt, partial: teilweise, none: gar nicht.
     * Ein Stand nur für Kurz- oder Langwaffe gilt erst mit Umfang erlaubnispflichtig als beaufsichtigt (G9).
     * Stände außer Betrieb zählen nicht mit (siehe rangesThatCount).
     */
    public function state(): string
    {
        if (! $this->hasAnySupervision()) {
            return 'none';
        }

        return $this->rangesWithoutSupervision()->isEmpty() ? 'full' : 'partial';
    }

    /**
     * Kann die Person an diesem Tag selbst eine Aufsicht übernehmen? Wer nur erlaubnisfrei
     * registriert ist, nicht, wenn alle geöffneten Stände nur Kurz- oder Langwaffe zulassen.
     * Dieselbe Regel wie Bookings::ensureScopeFits für die ganze Anlage.
     */
    public function canBeTakenOverBy(User $user): bool
    {
        if (! $user->canSupervise($this->day->date)) {
            return false;
        }

        if ($user->canSupervise($this->day->date, licensed: true)) {
            return true;
        }

        $active = $this->day->ranges->filter(fn (Range $range) => $range->active);

        return $active->isEmpty() || ! $active->every(fn (Range $range) => self::onlyLicensed($range));
    }

    /**
     * Aktive Buchungen, für die die angegebene Aufsicht zählt und die ohne sie nicht mehr abgedeckt wären.
     *
     * Zählte die Aufsicht für eine Buchung gar nicht (anderer Stand, andere Zeit, Umfang zu klein,
     * nicht mehr im Register), hält diese Buchung das Austragen nicht auf (G9).
     *
     * @return Collection<int, Booking>
     */
    public function bookingsUncoveredWithout(Supervision $removed): Collection
    {
        return $this->bookings->filter(function (Booking $booking) use ($removed) {
            $range = $this->day->ranges->firstWhere('id', $booking->range_id);

            if (! $range instanceof Range || ! $removed->window()->overlaps($booking->window())) {
                return false;
            }

            // Kurz- und Langwaffe deckt nur eine Aufsicht mit Umfang erlaubnispflichtig ab.
            $counting = $this->supervisionsFor($range, $booking->weapon_type->requiresLicense());

            if (! $counting->contains('id', $removed->id)) {
                return false;
            }

            $remaining = $counting
                ->reject(fn (Supervision $s) => $s->id === $removed->id)
                ->map(fn (Supervision $s) => $s->window());

            return ! $booking->window()->isCoveredBy($remaining);
        })->values();
    }

    public function activeBookingCount(): int
    {
        return $this->bookings->filter(fn (Booking $b) => $b->status !== BookingStatus::Cancelled)->count();
    }
}
