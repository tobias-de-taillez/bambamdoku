<?php

namespace App\Domain;

use App\Enums\BookingStatus;
use App\Enums\EntryVia;
use App\Enums\SessionEntryStatus;
use App\Enums\SessionStatus;
use App\Enums\WeaponClass;
use App\Enums\WeaponOwner;
use App\Models\AuditEntry;
use App\Models\Booking;
use App\Models\LogEntry;
use App\Models\SessionEntry;
use App\Models\SessionParticipant;
use App\Models\ShootingSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Die Regeln der Schieß-Session (UMBAU-SESSION.md, Ablauf am Stand und Zustände).
 *
 * - Die Aufsicht startet die Session mit gültiger Registerzeile am Tag (E4), das Datum setzt der Server.
 * - Sie aktiviert Teilnehmer vor Ort: aus einer Buchung (unabhängig vom Status), über die Suche
 *   oder als Gast. Nie sich selbst, nie wer heute selbst eine offene Session führt (E6).
 * - Teilnehmer erfassen in die Vorstufe: am eigenen Gerät, am Kiosk oder über die Aufsicht.
 *   Bearbeitet wird nichts; zurückgezogen wird von der erfassenden Person, abgelehnt von der Inhaberin.
 * - Erst das Abzeichnen am Session-Tag (E5) schreibt genau die angezeigten Einträge in die Kladde.
 *
 * Jede Methode prüft in einer Transaktion (SQLite: IMMEDIATE, config/database.php).
 * Fehler kommen als ValidationException am passenden Feld.
 */
class Sessions
{
    public const MAX_SHOTS = 999;

    public function __construct(private Bookings $bookings, private Logbook $logbook) {}

    /**
     * Startet die Session der Aufsicht für heute. Ein zweiter Tap liefert die schon offene Session.
     */
    public function open(User $supervisor): ShootingSession
    {
        return DB::transaction(function () use ($supervisor) {
            $supervisor = $this->fresh($supervisor);
            $today = CarbonImmutable::today();

            if (! $supervisor->canSupervise($today)) {
                $this->fail('session', 'Eine Session starten dürfen nur Personen mit gültiger Zeile im Aufsichtsregister (§ 10 Abs. 3 AWaffV), auch Admins.');
            }

            $existing = $this->openSessionOf($supervisor);

            if ($existing) {
                return $existing;
            }

            $session = ShootingSession::create(['supervisor_id' => $supervisor->id, 'shot_on' => $today->toDateString()]);

            AuditEntry::record($supervisor, 'session.opened', $session, sprintf('Session gestartet: %s, %s Uhr', $today->format('d.m.Y'), now()->format('H:i')));

            return $session;
        });
    }

    /**
     * Die offene Session, die diese Person heute führt (verfallene von gestern zählen nicht).
     */
    public function openSessionOf(User $user): ?ShootingSession
    {
        return ShootingSession::query()->openToday()->where('supervisor_id', $user->id)->latest('id')->first();
    }

    /**
     * Die jüngste Aktivierung der Person heute; nur dort entstehen neue Einträge.
     */
    public function latestParticipation(User $member): ?SessionParticipant
    {
        return SessionParticipant::query()
            ->where('user_id', $member->id)
            ->whereHas('session', fn ($q) => $q->whereDate('shot_on', CarbonImmutable::today()))
            ->latest('created_at')
            ->latest('id')
            ->first();
    }

    /**
     * Eigene Einträge der Vorstufe im Zeitraum, die nicht in die Kladde gekommen sind:
     * zurückgezogen, abgelehnt oder offen in einer Session, die nicht mehr offen ist (verfallen).
     * Offene Einträge der heute offenen Session zeigt „Heute schießen“.
     *
     * @return Collection<int, SessionEntry>
     */
    public function entriesOutsideRecord(User $member, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $inPeriod = fn ($q) => $q->whereDate('shot_on', '>=', $from->toDateString())->whereDate('shot_on', '<=', $to->toDateString());
        $notOpenToday = fn ($q) => $q->where('status', '!=', SessionStatus::Open->value)->orWhereDate('shot_on', '<', CarbonImmutable::today()->toDateString());

        return SessionEntry::query()
            ->whereHas('participant', fn ($q) => $q->where('user_id', $member->id)->whereHas('session', $inPeriod))
            ->where(fn (Builder $q) => $q
                ->whereIn('status', [SessionEntryStatus::Withdrawn->value, SessionEntryStatus::Rejected->value])
                ->orWhere(fn (Builder $q) => $q
                    ->where('status', SessionEntryStatus::Pending->value)
                    ->whereHas('participant.session', $notOpenToday)))
            ->with(['participant.session.supervisor', 'rejectedBy'])
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Aktivieren aus der Buchungsliste, unabhängig vom Buchungsstatus. Der Status wird nur zum Label
     * „aktiviert“ (checked_in); nachweisrelevant ist allein das spätere Abzeichnen.
     */
    public function activate(User $actor, ShootingSession $session, Booking $booking, bool $adultChecked = false, bool $guestIsAdult = false): SessionParticipant
    {
        return DB::transaction(function () use ($actor, $session, $booking, $adultChecked, $guestIsAdult) {
            $session = $this->ensureOwnerOfOpenSession($actor, $session);
            $booking = Booking::query()->with(['shootingDay', 'range', 'user'])->findOrFail($booking->id);

            if (! $booking->shootingDay->date->isSameDay($session->shot_on)) {
                $this->fail('booking', 'Aktivieren nur für Buchungen vom Tag der Session.');
            }

            if ($booking->status === BookingStatus::Cancelled) {
                $this->fail('booking', 'Diese Buchung ist storniert.');
            }

            $participant = $booking->user
                ? $this->addMemberLocked($session, $booking->user, $adultChecked, $booking)
                : $this->addGuestLocked($session, (string) $booking->guest_name, $guestIsAdult, $booking);

            if ($booking->status !== BookingStatus::CheckedIn) {
                if ($booking->status === BookingStatus::NoShow) {
                    $this->bookings->ensureLaneIsFreeAgain($booking);
                }

                $booking->forceFill([
                    'status' => BookingStatus::CheckedIn,
                    'checked_in_at' => now(),
                    'checked_in_by_id' => $session->supervisor_id,
                ])->save();
            }

            return $participant;
        });
    }

    /**
     * Mitglied über die Suche aktivieren (nur aktive Mitglieder; Anträge sind inaktiv).
     */
    public function addMember(User $actor, ShootingSession $session, User $member, bool $adultChecked = false): SessionParticipant
    {
        return DB::transaction(function () use ($actor, $session, $member, $adultChecked) {
            $session = $this->ensureOwnerOfOpenSession($actor, $session);

            return $this->addMemberLocked($session, $member, $adultChecked);
        });
    }

    /**
     * Gast mit Name und Pflichthaken „volljährig“ (Minderjährige weiter auf Papier, E11).
     */
    public function addGuest(User $actor, ShootingSession $session, string $name, bool $isAdult): SessionParticipant
    {
        return DB::transaction(function () use ($actor, $session, $name, $isAdult) {
            $session = $this->ensureOwnerOfOpenSession($actor, $session);

            return $this->addGuestLocked($session, $name, $isAdult);
        });
    }

    private function addMemberLocked(ShootingSession $session, User $member, bool $adultChecked, ?Booking $booking = null): SessionParticipant
    {
        $member = $this->fresh($member);

        if ($member->id === $session->supervisor_id) {
            $this->fail('user_id', 'Die Aufsicht ist nie Teilnehmerin ihrer eigenen Session. Wer schießt, beaufsichtigt nicht.');
        }

        if (! $member->active) {
            $this->fail('user_id', sprintf('%s ist kein aktives Mitglied.', $member->name));
        }

        if ($this->openSessionOf($member)) {
            $this->fail('user_id', sprintf('%s führt gerade selbst eine offene Session und kann erst nach dem Abzeichnen aktiviert werden.', $member->name));
        }

        $existing = SessionParticipant::query()->where('session_id', $session->id)->where('user_id', $member->id)->first();

        if ($existing) {
            return $existing;
        }

        if ($member->adult_confirmed_at === null) {
            if (! $adultChecked) {
                $this->fail('adult_confirmed', sprintf('Bitte einmalig bestätigen, dass %s volljährig ist (geprüft).', $member->name));
            }

            $member->forceFill(['adult_confirmed_at' => now()])->save();
            AuditEntry::record($session->supervisor, 'user.adult_confirmed', $member, sprintf('Volljährig geprüft: %s (beim Aktivieren)', $member->name));
        }

        return SessionParticipant::create([
            'session_id' => $session->id,
            'user_id' => $member->id,
            'booking_id' => $booking?->id,
        ]);
    }

    private function addGuestLocked(ShootingSession $session, string $name, bool $isAdult, ?Booking $booking = null): SessionParticipant
    {
        $name = trim($name);

        if ($name === '') {
            $this->fail('guest_name', 'Bitte den Namen des Gastes angeben.');
        }

        if (! $isAdult) {
            $this->fail('guest_adult', 'Bitte bestätigen, dass der Gast volljährig ist. Minderjährige weiter auf Papier.');
        }

        $existing = SessionParticipant::query()->where('session_id', $session->id)->whereNull('user_id')->where('guest_name', $name)->first();

        if ($existing) {
            return $existing;
        }

        return SessionParticipant::create([
            'session_id' => $session->id,
            'guest_name' => mb_substr($name, 0, 100),
            'booking_id' => $booking?->id,
        ]);
    }

    /**
     * Entfernt eine versehentliche Aktivierung: nur die Inhaberin, nur ohne Einträge.
     * Die Buchung geht zurück auf „gebucht“, wenn keine andere Teilnahme sie nennt.
     */
    public function removeParticipant(User $actor, SessionParticipant $participant): void
    {
        DB::transaction(function () use ($actor, $participant) {
            $participant = SessionParticipant::query()->findOrFail($participant->id);
            $this->ensureOwnerOfOpenSession($actor, $participant->session);

            if ($participant->entries()->exists()) {
                $this->fail('participant', sprintf('%s hat schon Einträge und bleibt in der Session. Offene Einträge bitte ablehnen.', $participant->name()));
            }

            $bookingId = $participant->booking_id;
            $participant->delete();

            if ($bookingId !== null && ! SessionParticipant::query()->where('booking_id', $bookingId)->exists()) {
                $booking = Booking::query()->find($bookingId);

                if ($booking && $booking->status === BookingStatus::CheckedIn && ! LogEntry::query()->where('booking_id', $bookingId)->exists()) {
                    $booking->forceFill(['status' => BookingStatus::Booked, 'checked_in_at' => null, 'checked_in_by_id' => null])->save();
                }
            }
        });
    }

    /**
     * Erfasst einen Eintrag in der Vorstufe.
     *
     * - `own_device`: $actor ist das Mitglied selbst, nur für sich.
     * - `kiosk`: $actor ist das Mitglied nach richtiger PIN (die Prüfung macht der Kiosk, PR 6).
     * - `stand`: $actor ist die Inhaberin der Session.
     *
     * Ein gleicher Eintrag liefert den vorhandenen zurück (`duplicate`), nichts wird still überschrieben.
     *
     * @return array{entry: SessionEntry, duplicate: bool}
     */
    public function stage(
        User $actor,
        SessionParticipant $participant,
        EntryVia $via,
        WeaponClass $class,
        ?WeaponOwner $owner,
        ?int $shots,
        ?string $discipline = null,
    ): array {
        return DB::transaction(function () use ($actor, $participant, $via, $class, $owner, $shots, $discipline) {
            $participant = SessionParticipant::query()->with(['session.supervisor', 'user'])->findOrFail($participant->id);
            $session = $participant->session;
            $member = $participant->user;

            if (! $session->isOpenToday()) {
                $this->fail('entry', $session->isOpen()
                    ? 'Diese Session ist verfallen (nicht am Session-Tag abgezeichnet). Zählen kann nur ein Nachtrag des Vorstands.'
                    : 'Diese Session ist abgezeichnet. Neue Einträge nur in einer neuen Session.');
            }

            $this->ensureMayStage($actor, $participant, $via);

            if ($member !== null) {
                $member = $this->fresh($member);

                if (! $member->active) {
                    $this->fail('entry', sprintf('%s ist kein aktives Mitglied.', $member->name));
                }

                if ($this->openSessionOf($member)) {
                    $this->fail('entry', 'Wer selbst eine offene Session führt, trägt nichts ein. Wer beaufsichtigt, schießt nicht.');
                }

                if ($this->latestParticipation($member)?->id !== $participant->id) {
                    $this->fail('entry', sprintf('%s wurde inzwischen in einer anderen Session aktiviert. Bitte dort erfassen.', $member->name));
                }

                if ($owner === null) {
                    $this->fail('weapon_owner', 'Bitte angeben, ob eine eigene oder eine Vereinswaffe genutzt wird.');
                }

                if ($shots === null) {
                    $this->fail('shots', 'Bitte die Schusszahl angeben.');
                }
            } else {
                $owner = null;
            }

            if ($shots !== null && ($shots < 1 || $shots > self::MAX_SHOTS)) {
                $this->fail('shots', sprintf('Die Schusszahl muss zwischen 1 und %d liegen.', self::MAX_SHOTS));
            }

            if ($class->requiresLicense() && ! $this->fresh($session->supervisor)->canSupervise(CarbonImmutable::today(), licensed: true)) {
                $this->fail('weapon_class', 'Für Kurz- und Langwaffen braucht die Aufsicht der Session den Umfang „erlaubnispflichtige Waffen“ im Register.');
            }

            $discipline = $this->normalizeDiscipline($discipline, $member !== null && $class->requiresLicense());

            $attributes = [
                'participant_id' => $participant->id,
                'discipline' => (string) $discipline,
                'weapon_class' => $class->value,
                'weapon_owner' => $owner->value ?? '',
            ];

            $existing = $this->findOpenEntry($attributes);

            if ($existing) {
                return ['entry' => $existing, 'duplicate' => true];
            }

            if ($member !== null && $this->alreadyInLogbook($member, $session->shot_on, $class, $owner, $discipline)) {
                $this->fail('entry', sprintf('%s ist heute mit dieser Kategorie schon in der Kladde.', $member->name));
            }

            try {
                $entry = DB::transaction(fn () => SessionEntry::create([
                    ...$attributes,
                    'shots' => $shots,
                    'via' => $via,
                    'entered_by_id' => $actor->id,
                ]));
            } catch (QueryException $e) {
                $existing = $this->findOpenEntry($attributes);

                if ($existing) {
                    return ['entry' => $existing, 'duplicate' => true];
                }

                throw $e;
            }

            return ['entry' => $entry, 'duplicate' => false];
        });
    }

    /**
     * Zurückziehen: nur wer erfasst hat. Kiosk-Einträge werden nicht zurückgezogen, sondern abgelehnt.
     */
    public function withdraw(User $actor, SessionEntry $entry): void
    {
        DB::transaction(function () use ($actor, $entry) {
            $entry = $this->freshEntry($entry);
            $session = $entry->participant->session;

            if ($entry->entered_by_id !== $actor->id || $entry->via === EntryVia::Kiosk) {
                abort(403);
            }

            $this->ensurePendingInOpenSession($entry, $session);

            $entry->forceFill(['status' => SessionEntryStatus::Withdrawn])->save();
        });
    }

    /**
     * Ablehnen mit Grund: nur die Inhaberin, für jeden offenen Eintrag ihrer Session.
     */
    public function reject(User $actor, SessionEntry $entry, string $reason): void
    {
        DB::transaction(function () use ($actor, $entry, $reason) {
            $entry = $this->freshEntry($entry);
            $session = $entry->participant->session;

            if ($session->supervisor_id !== $actor->id) {
                abort(403);
            }

            $this->ensurePendingInOpenSession($entry, $session);

            $reason = trim($reason);

            if (mb_strlen($reason) < 3) {
                $this->fail('reason', 'Bitte einen Grund angeben, das Mitglied sieht ihn.');
            }

            $entry->forceFill([
                'status' => SessionEntryStatus::Rejected,
                'reject_reason' => mb_substr($reason, 0, 255),
                'rejected_by_id' => $actor->id,
            ])->save();
        });
    }

    /**
     * Abzeichnen: schreibt genau die angezeigten offenen Einträge insert-only in die Kladde
     * (recorded_by_id = Inhaberin, shot_on = Session-Tag), dann die Einträge, dann die Session.
     * Ein zweites Abzeichnen ändert nichts.
     *
     * @param  list<int>  $shownEntryIds  die IDs, die der Dialog angezeigt hat
     * @return array{created: int, merged: int}
     */
    public function signOff(User $actor, ShootingSession $session, array $shownEntryIds): array
    {
        return DB::transaction(function () use ($actor, $session, $shownEntryIds) {
            $session = ShootingSession::query()->lockForUpdate()->findOrFail($session->id);

            if ($session->supervisor_id !== $actor->id) {
                abort(403);
            }

            if ($session->status === SessionStatus::Signed) {
                return ['created' => 0, 'merged' => 0];
            }

            $today = CarbonImmutable::today();

            if (! $session->shot_on->isSameDay($today)) {
                $this->fail('session', 'Abzeichnen nur am Session-Tag. Die Session ist verfallen; zählen kann nur ein Nachtrag des Vorstands.');
            }

            $supervisor = $this->fresh($actor);

            if (! $supervisor->canSupervise($today)) {
                $this->fail('session', 'Ihre Zeile im Aufsichtsregister ist nicht mehr gültig. Abzeichnen ist nicht möglich.');
            }

            $pending = SessionEntry::query()
                ->whereHas('participant', fn ($q) => $q->where('session_id', $session->id))
                ->where('status', SessionEntryStatus::Pending->value)
                ->with('participant')
                ->orderBy('id')
                ->get();

            $shown = collect($shownEntryIds)->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();

            if ($shown !== $pending->pluck('id')->sort()->values()->all()) {
                $this->fail('entry_ids', 'Die Liste hat sich geändert, bitte neu prüfen.');
            }

            if ($pending->contains(fn (SessionEntry $e) => $e->weapon_class->requiresLicense()) && ! $supervisor->canSupervise($today, licensed: true)) {
                $this->fail('session', 'Für Kurz- und Langwaffen braucht die Aufsicht den Umfang „erlaubnispflichtige Waffen“ im Register.');
            }

            $created = 0;
            $merged = 0;

            foreach ($pending as $entry) {
                $result = $this->logbook->recordFromSession($session, $entry);
                $result['duplicate'] ? $merged++ : $created++;
            }

            SessionEntry::query()->whereKey($pending->pluck('id'))->update(['status' => SessionEntryStatus::Signed->value, 'updated_at' => now()]);
            $session->forceFill(['status' => SessionStatus::Signed, 'signed_at' => now()])->save();

            AuditEntry::record($supervisor, 'session.signed', $session, sprintf(
                'Session abgezeichnet: %s, bis %s Uhr, %d Einträge',
                $session->shot_on->format('d.m.Y'),
                now()->format('H:i'),
                $pending->count(),
            ));

            return ['created' => $created, 'merged' => $merged];
        });
    }

    /**
     * Erzeugt den Kiosk-Link der Session. Gespeichert wird nur der Hash; ein neuer Link ersetzt den alten.
     *
     * @return string das Token im Klartext, nur einmal
     */
    public function issueKioskToken(User $actor, ShootingSession $session): string
    {
        return DB::transaction(function () use ($actor, $session) {
            $session = $this->ensureOwnerOfOpenSession($actor, $session);
            $token = Str::random(48);

            $session->forceFill(['kiosk_token_hash' => hash('sha256', $token)])->save();

            return $token;
        });
    }

    /**
     * Die Session zu einem Kiosk-Link, nur wenn sie offen und von heute ist.
     */
    public function sessionForKioskToken(string $token): ?ShootingSession
    {
        if ($token === '') {
            return null;
        }

        return ShootingSession::query()->openToday()->where('kiosk_token_hash', hash('sha256', $token))->first();
    }

    private function ensureMayStage(User $actor, SessionParticipant $participant, EntryVia $via): void
    {
        $session = $participant->session;

        $allowed = match ($via) {
            // die Aufsicht für jeden Teilnehmer ihrer Session, auch Gäste
            EntryVia::Stand => $session->supervisor_id === $actor->id,
            // das Mitglied nur für sich
            EntryVia::OwnDevice => $participant->user_id === $actor->id,
            // nur Mitglieder mit gesetzter PIN; Gäste und Mitglieder ohne PIN erfasst die Aufsicht
            EntryVia::Kiosk => $participant->user_id === $actor->id && $actor->kiosk_pin_hash !== null,
        };

        abort_unless($allowed, 403);
    }

    private function ensureOwnerOfOpenSession(User $actor, ShootingSession $session): ShootingSession
    {
        $session = ShootingSession::query()->with('supervisor')->findOrFail($session->id);

        if ($session->supervisor_id !== $actor->id) {
            abort(403);
        }

        if (! $session->isOpenToday()) {
            $this->fail('session', $session->isOpen()
                ? 'Diese Session ist verfallen. Bitte eine neue Session starten.'
                : 'Diese Session ist schon abgezeichnet. Bitte eine neue Session starten.');
        }

        if (! $this->fresh($actor)->canSupervise(CarbonImmutable::today())) {
            $this->fail('session', 'Ihre Zeile im Aufsichtsregister ist nicht mehr gültig.');
        }

        return $session;
    }

    private function ensurePendingInOpenSession(SessionEntry $entry, ShootingSession $session): void
    {
        if (! $session->isOpenToday()) {
            $this->fail('entry', 'Diese Session ist abgezeichnet oder verfallen.');
        }

        if (! $entry->isPending()) {
            $this->fail('entry', sprintf('Dieser Eintrag ist schon %s.', $entry->status->label()));
        }
    }

    /**
     * Mit Katalog nur Schlüssel daraus, ohne Katalog immer leer. Pflicht ist sie nur, wenn $required
     * (Mitglied mit erlaubnispflichtiger Kategorie): Verbandskataloge wie der des BDS führen nur
     * Disziplinen für Bescheinigungen, Luftdruck und Bogen fehlen darin.
     */
    private function normalizeDiscipline(?string $discipline, bool $required): ?string
    {
        $catalog = Disciplines::all();
        $discipline = $discipline === null || trim($discipline) === '' ? null : trim($discipline);

        if ($catalog === []) {
            return null;
        }

        if ($discipline === null && ! $required) {
            return null;
        }

        if ($discipline === null || ! array_key_exists($discipline, $catalog)) {
            $this->fail('discipline', 'Bitte eine Disziplin aus dem Katalog des Verbands wählen.');
        }

        return $discipline;
    }

    /**
     * @param  array{participant_id: int, discipline: string, weapon_class: string, weapon_owner: string}  $attributes
     */
    private function findOpenEntry(array $attributes): ?SessionEntry
    {
        return SessionEntry::query()
            ->where('participant_id', $attributes['participant_id'])
            ->where('discipline', $attributes['discipline'])
            ->where('weapon_class', $attributes['weapon_class'])
            ->where('weapon_owner', $attributes['weapon_owner'])
            ->whereIn('status', [SessionEntryStatus::Pending->value, SessionEntryStatus::Signed->value])
            ->first();
    }

    private function alreadyInLogbook(User $member, CarbonImmutable $day, WeaponClass $class, ?WeaponOwner $owner, ?string $discipline): bool
    {
        return LogEntry::query()
            ->active()
            ->where('user_id', $member->id)
            ->whereDate('shot_on', $day)
            ->where('weapon_class', $class->value)
            ->where('weapon_owner', $owner?->value)
            ->whereRaw("coalesce(discipline, '') = ?", [(string) $discipline])
            ->exists();
    }

    private function freshEntry(SessionEntry $entry): SessionEntry
    {
        return SessionEntry::query()->with('participant.session')->findOrFail($entry->id);
    }

    /**
     * Register und Status aus der Datenbank, nicht aus einer im Request zwischengespeicherten Instanz.
     */
    private function fresh(User $user): User
    {
        return User::query()->findOrFail($user->id);
    }

    /**
     * @return never
     */
    private function fail(string $field, string $message): void
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
