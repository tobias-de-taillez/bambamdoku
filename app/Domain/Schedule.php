<?php

namespace App\Domain;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Range;
use App\Models\ShootingDay;
use App\Models\Supervision;
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
     * @return Collection<int, Supervision>
     */
    public function supervisionsFor(Range $range): Collection
    {
        return $this->supervisions->filter(fn (Supervision $supervision) => $supervision->covers($range))->values();
    }

    public function isSupervised(Range $range, TimeWindow $window): bool
    {
        return $window->isCoveredBy($this->supervisionsFor($range)->map(fn (Supervision $s) => $s->window()));
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
        return $this->day->ranges->sortBy([['position', 'asc'], ['name', 'asc']])->values()->map(fn (Range $range) => [
            'id' => $range->id,
            'name' => $range->name,
            'lanes' => $range->lanes,
            'weapon_types' => $range->weapon_types->map->value->values(),
            'slots' => array_map(fn (TimeWindow $slot) => [
                'start' => $slot->startString(),
                'end' => $slot->endString(),
                'free' => $this->freeLanes($range, $slot),
                'supervised' => $this->isSupervised($range, $slot),
            ], $this->slots()),
        ])->all();
    }

    /**
     * Ob am Tag zu irgendeiner Zeit Aufsicht eingeteilt ist.
     */
    public function hasAnySupervision(): bool
    {
        return $this->supervisions->isNotEmpty();
    }

    /**
     * Aktive Buchungen eines Standes, die ohne die angegebene Aufsicht nicht mehr abgedeckt wären.
     *
     * @return Collection<int, Booking>
     */
    public function bookingsUncoveredWithout(Supervision $removed): Collection
    {
        return $this->bookings->filter(function (Booking $booking) use ($removed) {
            $range = $this->day->ranges->firstWhere('id', $booking->range_id);

            if (! $range instanceof Range || ! $removed->covers($range) || ! $removed->window()->overlaps($booking->window())) {
                return false;
            }

            $remaining = $this->supervisionsFor($range)
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
