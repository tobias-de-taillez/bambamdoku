<?php

namespace App\Domain;

use App\Enums\BookingStatus;
use App\Enums\Role;
use App\Enums\WeaponClass;
use App\Enums\WeaponOwner;
use App\Enums\WeaponType;
use App\Models\AuditEntry;
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
        ?User $host = null,
    ): Booking {
        return DB::transaction(function () use ($actor, $day, $range, $window, $weaponType, $clubWeapon, $shooter, $guestName, $host) {
            // Tag neu laden, damit gleichzeitige Buchungen in derselben Transaktion gezählt werden.
            $day = ShootingDay::query()->lockForUpdate()->findOrFail($day->id);
            $schedule = new Schedule($day);
            $isGuest = $shooter === null || $shooter->role === Role::Guest;

            $this->ensureDayIsBookable($day, $shooter ?? $host ?? $actor, $isGuest);

            if ($isGuest && trim((string) $guestName) === '') {
                $this->fail('guest_name', 'Bitte den Namen des Gastes angeben.');
            }

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

            if ($day->date->isToday() && $window->end <= TimeWindow::minutes(now()->format('H:i'))) {
                $this->fail('starts_at', 'Diese Zeit ist schon vorbei.');
            }

            if ($schedule->peakOccupancy($range, $window) + $this->sameRangeElsewhere($day, $range, $window) >= $range->lanes) {
                $this->fail('starts_at', 'Der Stand ist in dieser Zeit voll belegt.');
            }

            if ($shooter !== null && $this->hasOverlappingBooking($day, $shooter, $window)) {
                $this->fail('starts_at', $shooter->is($actor)
                    ? 'Sie haben in dieser Zeit bereits eine Buchung.'
                    : sprintf('%s hat in dieser Zeit bereits eine Buchung.', $shooter->name));
            }

            $booking = Booking::create([
                'shooting_day_id' => $day->id,
                'range_id' => $range->id,
                'user_id' => $shooter?->id,
                'guest_name' => $isGuest ? $guestName : null,
                'host_id' => $isGuest ? ($host ?? $actor)->id : null,
                'starts_at' => $window->startString(),
                'ends_at' => $window->endString(),
                'weapon_type' => $weaponType,
                'club_weapon' => $clubWeapon,
                'booked_by_id' => $actor->id,
            ]);

            AuditEntry::record($actor, 'booking.created', $booking, sprintf(
                '%s gebucht: %s, %s, %s–%s Uhr',
                $isGuest ? 'Gast '.$guestName : $shooter->name,
                $day->date->format('d.m.Y'),
                $range->name,
                $window->startString(),
                $window->endString(),
            ));

            return $booking;
        });
    }

    private function describe(Booking $booking): string
    {
        return sprintf('%s, %s, %s–%s Uhr', $booking->shooterName(), $booking->shootingDay->date->format('d.m.Y'), substr($booking->starts_at, 0, 5), substr($booking->ends_at, 0, 5));
    }

    public function __construct(private Logbook $logbook) {}

    /**
     * Die Standaufsicht bestätigt die Anwesenheit und legt damit den Kladde-Eintrag an.
     * Die Buchung füllt nur die Eincheckliste vor; nachweisrelevant ist allein der Eintrag.
     */
    public function checkIn(User $actor, Booking $booking, ?string $discipline = null, bool $guestIsAdult = false): void
    {
        $this->ensureCanRunTheRange($actor, $booking);

        // Der Eintrag bekommt das heutige Datum – also nur am Schießtag selbst, auch für Admins.
        // Vergangene Termine laufen als Nachtrag mit Grund.
        if (! $booking->shootingDay->date->isToday()) {
            $this->fail('booking', 'Anwesenheit nur am Schießtag selbst eintragen. Vergangene Termine bitte als Nachtrag in der Kladde des Vereins.');
        }

        // Doppel-Tap: schon eingetragen ist kein Fehler.
        if ($booking->status === BookingStatus::CheckedIn) {
            return;
        }

        if ($booking->status !== BookingStatus::Booked && $booking->status !== BookingStatus::NoShow) {
            $this->fail('booking', 'Diese Buchung kann nicht mehr eingecheckt werden.');
        }

        if ($booking->status === BookingStatus::NoShow) {
            $this->ensureLaneIsFreeAgain($booking);
        }

        DB::transaction(function () use ($actor, $booking, $discipline, $guestIsAdult) {
            $this->logbook->record(
                actor: $actor,
                member: $booking->user,
                guestName: $booking->guest_name,
                class: WeaponClass::fromWeaponType($booking->weapon_type),
                owner: $booking->user ? ($booking->club_weapon ? WeaponOwner::Club : WeaponOwner::Own) : null,
                discipline: $discipline,
                booking: $booking,
                guestIsAdult: $guestIsAdult,
            );

            $booking->forceFill([
                'status' => BookingStatus::CheckedIn,
                'checked_in_at' => now(),
                'checked_in_by_id' => $actor->id,
            ])->save();
        });

        AuditEntry::record($actor, 'booking.checked_in', $booking, 'Anwesend: '.$this->describe($booking));
    }

    public function markNoShow(User $actor, Booking $booking): void
    {
        $this->ensureCanRunTheRange($actor, $booking);

        if ($booking->status !== BookingStatus::Booked) {
            $this->fail('booking', 'Nur offene Buchungen können als nicht erschienen markiert werden.');
        }

        $booking->forceFill(['status' => BookingStatus::NoShow])->save();

        AuditEntry::record($actor, 'booking.no_show', $booking, 'Nicht erschienen: '.$this->describe($booking));
    }

    /**
     * Macht ein versehentliches „nicht erschienen“ rückgängig. Ein bestätigter Besuch
     * steht in der Kladde und wird dort per Storno mit Grund korrigiert.
     */
    public function reset(User $actor, Booking $booking): void
    {
        $this->ensureCanRunTheRange($actor, $booking);

        if ($booking->status === BookingStatus::CheckedIn) {
            $this->fail('booking', 'Ein bestätigter Besuch wird nicht zurückgesetzt, sondern in der Kladde mit Grund storniert.');
        }

        if ($booking->status !== BookingStatus::NoShow) {
            $this->fail('booking', 'Diese Buchung ist bereits offen.');
        }

        $this->ensureLaneIsFreeAgain($booking);

        $booking->forceFill(['status' => BookingStatus::Booked])->save();

        AuditEntry::record($actor, 'booking.reset', $booking, 'Zurückgesetzt: '.$this->describe($booking));
    }

    /**
     * Admins immer, sonst nur wer am Tag der Buchung für deren Stand Aufsicht hat – und nur am Schießtag selbst.
     */
    public function canRunTheRange(User $actor, Booking $booking): bool
    {
        if ($actor->isAdmin()) {
            return true;
        }

        $supervises = $booking->shootingDay->supervisions
            ->contains(fn (Supervision $s) => $s->user_id === $actor->id && $s->covers($booking->range));

        return $supervises
            && $booking->shootingDay->date->isToday()
            && $actor->canSupervise($booking->shootingDay->date);
    }

    /**
     * Eine als „nicht erschienen“ markierte Buchung hat ihre Bahn freigegeben.
     * Bevor sie wieder zählt, müssen Bahn und Aufsicht noch passen.
     */
    private function ensureLaneIsFreeAgain(Booking $booking): void
    {
        $schedule = new Schedule($booking->shootingDay->fresh() ?? $booking->shootingDay);

        if ($schedule->peakOccupancy($booking->range, $booking->window(), $booking) >= $booking->range->lanes) {
            $this->fail('booking', 'Die Bahn ist inzwischen wieder vergeben. Bitte eine andere Zeit oder einen anderen Stand eintragen.');
        }

        if (! $schedule->isSupervised($booking->range, $booking->window())) {
            $this->fail('booking', 'Für diese Zeit ist keine Standaufsicht mehr eingeteilt.');
        }
    }

    private function ensureCanRunTheRange(User $actor, Booking $booking): void
    {
        abort_unless($this->canRunTheRange($actor, $booking), 403);
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

        AuditEntry::record($actor, 'booking.cancelled', $booking, 'Storniert: '.$this->describe($booking));
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

        $overlaps = Supervision::query()
            ->where('user_id', $supervisor->id)
            ->whereHas('shootingDay', fn ($q) => $q->whereDate('date', $day->date)->whereNull('cancelled_at'))
            ->get()
            ->contains(fn (Supervision $s) => $s->window()->overlaps($window));

        if ($overlaps) {
            $this->fail('starts_at', sprintf('%s ist in dieser Zeit bereits als Aufsicht eingetragen.', $actor->is($supervisor) ? 'Sie sind' : $supervisor->name));
        }

        $supervision = Supervision::create([
            'shooting_day_id' => $day->id,
            'user_id' => $supervisor->id,
            'range_id' => $range?->id,
            'starts_at' => $window->startString(),
            'ends_at' => $window->endString(),
            'assigned_by_id' => $actor->id,
        ]);

        AuditEntry::record($actor, 'supervision.assigned', $supervision, sprintf(
            'Aufsicht %s: %s, %s–%s Uhr, %s',
            $supervisor->name,
            $day->date->format('d.m.Y'),
            $window->startString(),
            $window->endString(),
            $range->name ?? 'ganze Anlage',
        ));

        return $supervision;
    }

    /**
     * Entfernt eine Aufsicht, solange keine Buchung dadurch ohne Aufsicht bliebe.
     */
    public function removeSupervision(User $actor, Supervision $supervision): void
    {
        if (! $actor->isAdmin() && $supervision->user_id !== $actor->id) {
            abort(403);
        }

        DB::transaction(function () use ($actor, $supervision) {
            ShootingDay::query()->lockForUpdate()->findOrFail($supervision->shooting_day_id);
            $this->removeSupervisionLocked($actor, $supervision);
        });
    }

    private function removeSupervisionLocked(User $actor, Supervision $supervision): void
    {
        $uncovered = (new Schedule($supervision->shootingDay->fresh() ?? $supervision->shootingDay))->bookingsUncoveredWithout($supervision);

        if ($uncovered->isNotEmpty()) {
            $this->fail('supervision', trans_choice(
                '{1} Eine Buchung wäre dann ohne Aufsicht. Bitte zuerst eine Vertretung eintragen.|[2,*] :count Buchungen wären dann ohne Aufsicht. Bitte zuerst eine Vertretung eintragen.',
                $uncovered->count(),
                ['count' => $uncovered->count()],
            ));
        }

        AuditEntry::record($actor, 'supervision.removed', $supervision->shootingDay, sprintf(
            'Aufsicht ausgetragen: %s, %s, %s–%s Uhr',
            $supervision->user->name,
            $supervision->shootingDay->date->format('d.m.Y'),
            substr($supervision->starts_at, 0, 5),
            substr($supervision->ends_at, 0, 5),
        ));

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

        if ($isGuest && ! $day->guests_allowed) {
            $this->fail('guest_name', 'An diesem Schießtag sind keine Gastschützen zugelassen.');
        }
    }

    private function hasOverlappingBooking(ShootingDay $day, User $user, TimeWindow $window): bool
    {
        // Über alle Schießtage am selben Datum, nicht nur diesen.
        return Booking::query()
            ->where('user_id', $user->id)
            ->occupying()
            ->whereHas('shootingDay', fn ($q) => $q->whereDate('date', $day->date))
            ->get()
            ->contains(fn (Booking $booking) => $booking->window()->overlaps($window));
    }

    /**
     * Belegung desselben Standes durch andere Schießtage am selben Datum.
     * Überlappende Tage mit gemeinsamem Stand verhindert schon die Verwaltung;
     * das hier fängt Altbestände ab.
     */
    private function sameRangeElsewhere(ShootingDay $day, Range $range, TimeWindow $window): int
    {
        return Booking::query()
            ->where('range_id', $range->id)
            ->occupying()
            ->where('shooting_day_id', '!=', $day->id)
            ->whereHas('shootingDay', fn ($q) => $q->whereDate('date', $day->date))
            ->get()
            ->filter(fn (Booking $b) => $b->window()->overlaps($window))
            ->count();
    }

    /**
     * @return never
     */
    private function fail(string $field, string $message): void
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
