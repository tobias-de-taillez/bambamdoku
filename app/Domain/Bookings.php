<?php

namespace App\Domain;

use App\Enums\BookingStatus;
use App\Enums\WeaponType;
use App\Models\Booking;
use App\Models\Range;
use App\Models\ShootingDay;
use App\Models\Supervision;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Alle schreibenden Regeln für Buchungen und Aufsichten an einer Stelle.
 *
 * Fehler werden als ValidationException geworfen, damit sie im Formular am
 * passenden Feld erscheinen.
 */
class Bookings
{
    /**
     * Bucht eine Bahn für ein Mitglied oder einen Gast.
     */
    public function book(
        User $actor,
        ShootingDay $day,
        Range $range,
        TimeWindow $window,
        WeaponType $weaponType,
        bool $clubWeapon = false,
        ?User $shooter = null,
        ?string $guestName = null,
    ): Booking {
        return DB::transaction(function () use ($actor, $day, $range, $window, $weaponType, $clubWeapon, $shooter, $guestName) {
            // Tag neu laden, damit gleichzeitige Buchungen in derselben Transaktion gezählt werden.
            $day = ShootingDay::query()->lockForUpdate()->findOrFail($day->id);
            $schedule = new Schedule($day);
            $isGuest = $shooter === null;

            $this->ensureDayIsBookable($day, $actor, $isGuest);

            if (! $day->ranges->contains('id', $range->id) || ! $range->active) {
                $this->fail('range_id', 'Dieser Stand ist an diesem Tag nicht geöffnet.');
            }

            if (! $schedule->isOnGrid($window)) {
                $this->fail('starts_at', sprintf(
                    'Bitte eine Zeit im %d-Minuten-Raster zwischen %s und %s Uhr wählen.',
                    Schedule::slotMinutes(),
                    $schedule->window()->startString(),
                    $schedule->window()->endString(),
                ));
            }

            if (! $range->weapon_types->contains($weaponType)) {
                $this->fail('weapon_type', sprintf('Auf „%s“ ist %s nicht zugelassen.', $range->name, $weaponType->label()));
            }

            if (! $schedule->isSupervised($range, $window)) {
                $this->fail('starts_at', 'Für diese Zeit ist noch keine Standaufsicht eingeteilt. Ohne Aufsicht darf nicht geschossen werden (§ 27 WaffG).');
            }

            if ($schedule->peakOccupancy($range, $window) >= $range->lanes) {
                $this->fail('starts_at', 'Der Stand ist in dieser Zeit voll belegt.');
            }

            if ($shooter !== null && $this->hasOverlappingBooking($day, $shooter, $window)) {
                $this->fail('starts_at', $shooter->is($actor)
                    ? 'Sie haben in dieser Zeit bereits eine Buchung.'
                    : sprintf('%s hat in dieser Zeit bereits eine Buchung.', $shooter->name));
            }

            return Booking::create([
                'shooting_day_id' => $day->id,
                'range_id' => $range->id,
                'user_id' => $shooter?->id,
                'guest_name' => $isGuest ? $guestName : null,
                'host_id' => $isGuest ? $actor->id : null,
                'starts_at' => $window->startString(),
                'ends_at' => $window->endString(),
                'weapon_type' => $weaponType,
                'club_weapon' => $clubWeapon,
                'booked_by_id' => $actor->id,
            ]);
        });
    }

    public function cancel(User $actor, Booking $booking): void
    {
        if (! $this->canCancel($actor, $booking)) {
            $this->fail('booking', sprintf(
                'Stornieren ist nur bis %d Minuten vor Beginn möglich. Bitte wenden Sie sich an die Standaufsicht.',
                config('verein.cancel_minutes_before'),
            ));
        }

        $booking->forceFill(['status' => BookingStatus::Cancelled, 'cancelled_at' => now()])->save();
    }

    public function canCancel(User $actor, Booking $booking): bool
    {
        if ($booking->status !== BookingStatus::Booked) {
            return false;
        }

        if ($actor->isAdmin() || $this->supervises($actor, $booking)) {
            return true;
        }

        $owns = $booking->user_id === $actor->id || $booking->host_id === $actor->id;
        $deadline = $booking->startsAtDateTime()->subMinutes((int) config('verein.cancel_minutes_before'));

        return $owns && now()->lt($deadline);
    }

    /**
     * Teilt eine Standaufsicht ein. Mitglieder können sich mit gültiger Qualifikation selbst eintragen.
     */
    public function assignSupervision(User $actor, ShootingDay $day, User $supervisor, ?Range $range, TimeWindow $window): Supervision
    {
        if (! $actor->isAdmin() && ! $actor->is($supervisor)) {
            abort(403);
        }

        if ($day->isCancelled() || $day->isOver()) {
            $this->fail('starts_at', 'Für abgesagte oder vergangene Schießtage kann keine Aufsicht mehr eingeteilt werden.');
        }

        if (! $supervisor->canSupervise($day->date)) {
            $this->fail('user_id', $actor->is($supervisor)
                ? 'Sie haben keine gültige Qualifikation als Standaufsicht. Bitte wenden Sie sich an den Vorstand.'
                : sprintf('%s hat keine gültige Qualifikation als Standaufsicht.', $supervisor->name));
        }

        $schedule = new Schedule($day);

        if (! $schedule->window()->contains($window)) {
            $this->fail('starts_at', sprintf('Die Aufsicht muss zwischen %s und %s Uhr liegen.', $schedule->window()->startString(), $schedule->window()->endString()));
        }

        if ($range !== null && ! $day->ranges->contains('id', $range->id)) {
            $this->fail('range_id', 'Dieser Stand ist an diesem Tag nicht geöffnet.');
        }

        $overlaps = $day->supervisions()
            ->where('user_id', $supervisor->id)
            ->get()
            ->contains(fn (Supervision $s) => $s->window()->overlaps($window));

        if ($overlaps) {
            $this->fail('starts_at', sprintf('%s ist in dieser Zeit bereits als Aufsicht eingetragen.', $actor->is($supervisor) ? 'Sie sind' : $supervisor->name));
        }

        return Supervision::create([
            'shooting_day_id' => $day->id,
            'user_id' => $supervisor->id,
            'range_id' => $range?->id,
            'starts_at' => $window->startString(),
            'ends_at' => $window->endString(),
            'assigned_by_id' => $actor->id,
        ]);
    }

    /**
     * Entfernt eine Aufsicht, solange keine Buchung dadurch ohne Aufsicht bliebe.
     */
    public function removeSupervision(User $actor, Supervision $supervision): void
    {
        if (! $actor->isAdmin() && $supervision->user_id !== $actor->id) {
            abort(403);
        }

        $uncovered = (new Schedule($supervision->shootingDay))->bookingsUncoveredWithout($supervision);

        if ($uncovered->isNotEmpty()) {
            $this->fail('supervision', trans_choice(
                '{1} Eine Buchung wäre dann ohne Aufsicht. Bitte zuerst eine Vertretung eintragen.|[2,*] :count Buchungen wären dann ohne Aufsicht. Bitte zuerst eine Vertretung eintragen.',
                $uncovered->count(),
                ['count' => $uncovered->count()],
            ));
        }

        $supervision->delete();
    }

    /**
     * Ob der Benutzer zur Zeit der Buchung für deren Stand Aufsicht hat.
     */
    public function supervises(User $user, Booking $booking): bool
    {
        return $booking->shootingDay->supervisions
            ->contains(fn (Supervision $s) => $s->user_id === $user->id
                && $s->covers($booking->range)
                && $s->window()->overlaps($booking->window()));
    }

    private function ensureDayIsBookable(ShootingDay $day, User $actor, bool $isGuest): void
    {
        if ($day->isCancelled()) {
            $this->fail('shooting_day', 'Dieser Schießtag ist abgesagt.');
        }

        if ($day->isOver()) {
            $this->fail('shooting_day', 'Dieser Schießtag ist bereits vorbei.');
        }

        if ($day->date->gt(today()->addDays((int) config('verein.booking_horizon_days')))) {
            $this->fail('shooting_day', 'So weit im Voraus kann noch nicht gebucht werden.');
        }

        if (! $day->isOpenFor($actor)) {
            abort(403);
        }

        if ($isGuest && ! $day->guests_allowed) {
            $this->fail('guest_name', 'An diesem Schießtag sind keine Gastschützen zugelassen.');
        }
    }

    private function hasOverlappingBooking(ShootingDay $day, User $user, TimeWindow $window): bool
    {
        return $day->bookings()
            ->where('user_id', $user->id)
            ->occupying()
            ->get()
            ->contains(fn (Booking $booking) => $booking->window()->overlaps($window));
    }

    /**
     * @return never
     */
    private function fail(string $field, string $message): void
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
