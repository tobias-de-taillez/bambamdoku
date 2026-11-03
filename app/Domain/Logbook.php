<?php

namespace App\Domain;

use App\Enums\BookingStatus;
use App\Enums\LogSource;
use App\Enums\WeaponClass;
use App\Enums\WeaponOwner;
use App\Models\Booking;
use App\Models\LogEntry;
use App\Models\SessionEntry;
use App\Models\ShootingSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Die Regeln der Schießkladde (Wettbewerbsanalyse K1–K9).
 *
 * - Eintragen = Bestätigen: nur eine Person mit gültiger Zeile im Aufsichtsregister,
 *   nie für sich selbst, immer für heute (das Datum setzt der Server).
 * - Für Kurz- und Langwaffe braucht die Aufsicht den Umfang „erlaubnispflichtig“.
 * - Ein zweiter gleicher Eintrag am selben Tag legt nichts Neues an („heute schon eingetragen“).
 * - Korrigiert wird per Storno mit Grund; ab dem Folgetag nur noch durch den Admin.
 * - Nachträge nur durch den Admin, mit Pflichtgrund, gekennzeichnet.
 */
class Logbook
{
    /**
     * Trägt am Stand ein. Gibt den (ggf. schon vorhandenen) Eintrag zurück.
     *
     * @return array{entry: LogEntry, duplicate: bool}
     */
    public function record(
        User $actor,
        ?User $member,
        ?string $guestName,
        WeaponClass $class,
        ?WeaponOwner $owner,
        ?string $discipline = null,
        ?Booking $booking = null,
        bool $guestIsAdult = false,
    ): array {
        $today = CarbonImmutable::today();
        $this->ensureGate($actor, $member, $class, $today);

        if ($member === null) {
            $guestName = trim((string) $guestName);

            if ($guestName === '') {
                $this->fail('guest_name', 'Bitte den Namen des Gastes angeben.');
            }

            if (! $guestIsAdult) {
                $this->fail('guest_adult', 'Bitte bestätigen, dass der Gast volljährig ist. Minderjährige weiter auf Papier.');
            }

            $owner = null;
        } else {
            if (! $member->active) {
                $this->fail('user_id', sprintf('%s ist deaktiviert.', $member->name));
            }

            if ($owner === null) {
                $this->fail('weapon_owner', 'Bitte angeben, ob eine eigene oder eine Vereinswaffe genutzt wird.');
            }
        }

        $this->ensureDiscipline($discipline, $member !== null);

        return $this->insertOnce([
            'user_id' => $member?->id,
            'guest_name' => $member ? null : $guestName,
            'shot_on' => $today->toDateString(),
            'weapon_class' => $class,
            'weapon_owner' => $owner,
            'discipline' => $member ? $discipline : null,
            'recorded_by_id' => $actor->id,
            'source' => LogSource::Stand,
            'booking_id' => $booking?->id,
        ]);
    }

    /**
     * Schreibweg beim Abzeichnen einer Session: Datum ist der Session-Tag, nicht heute;
     * bestätigt hat die abzeichnende Aufsicht. Trifft der Eintrag auf eine gleiche gültige Zeile
     * (zweite Session oder Nachtrag am selben Tag), entsteht keine zweite.
     *
     * @return array{entry: LogEntry, duplicate: bool}
     */
    public function recordFromSession(ShootingSession $session, SessionEntry $entry): array
    {
        $existing = LogEntry::query()->where('session_entry_id', $entry->id)->first();

        if ($existing) {
            return ['entry' => $existing, 'duplicate' => true];
        }

        $participant = $entry->participant;

        return $this->insertOnce([
            'user_id' => $participant->user_id,
            'guest_name' => $participant->user_id ? null : $participant->guest_name,
            'shot_on' => $session->shot_on->toDateString(),
            'weapon_class' => $entry->weapon_class,
            'weapon_owner' => $participant->user_id ? $entry->weapon_owner : null,
            'discipline' => $participant->user_id ? $entry->discipline : null,
            'shots' => $entry->shots,
            'recorded_by_id' => $session->supervisor_id,
            'source' => LogSource::Session,
            'session_entry_id' => $entry->id,
        ]);
    }

    /**
     * Nachtrag durch den Admin, z. B. aus der Papier-Ersatzliste. Ein Formular, mehrere Personen.
     *
     * @param  list<User>  $members
     * @return array{created: int, duplicates: int}
     */
    public function lateEntries(User $admin, array $members, string $date, WeaponClass $class, WeaponOwner $owner, ?string $discipline, string $reason): array
    {
        if (! $admin->isAdmin()) {
            abort(403);
        }

        $day = CarbonImmutable::parse($date);

        if ($day->isFuture()) {
            $this->fail('date', 'Nachträge nur für heute oder vergangene Tage.');
        }

        if (mb_strlen(trim($reason)) < 5) {
            $this->fail('reason', 'Bitte einen Grund mit Beleg angeben, z. B. „Papierliste vom …, Aufsicht: …“.');
        }

        if (collect($members)->contains(fn (User $m) => $m->is($admin))) {
            $this->fail('members', 'Niemand trägt sich selbst ein, auch nicht als Nachtrag.');
        }

        if (collect($members)->contains(fn (User $m) => $m->isApplicant())) {
            $this->fail('members', 'Für einen Mitgliedsantrag gibt es keine Einträge. Erst aufnehmen.');
        }

        $this->ensureDiscipline($discipline, true);

        $created = 0;
        $duplicates = 0;

        DB::transaction(function () use ($members, $day, $class, $owner, $discipline, $admin, $reason, &$created, &$duplicates) {
            foreach ($members as $member) {
                $result = $this->insertOnce([
                    'user_id' => $member->id,
                    'shot_on' => $day->toDateString(),
                    'weapon_class' => $class,
                    'weapon_owner' => $owner,
                    'discipline' => $discipline,
                    'recorded_by_id' => $admin->id,
                    'source' => LogSource::LateEntry,
                    'late_reason' => trim($reason),
                ]);
                $result['duplicate'] ? $duplicates++ : $created++;
            }
        });

        return ['created' => $created, 'duplicates' => $duplicates];
    }

    /**
     * Storno mit Grund. Aufsicht: nur eigene Einträge von heute. Danach nur der Admin.
     */
    public function cancel(User $actor, LogEntry $entry, string $reason): void
    {
        if (! $entry->isActive()) {
            $this->fail('entry', 'Dieser Eintrag ist bereits storniert.');
        }

        $allowed = $actor->isAdmin()
            || ($entry->recorded_by_id === $actor->id && $entry->shot_on->isToday() && $entry->source === LogSource::Stand);

        abort_unless($allowed, 403);

        if (trim($reason) === '') {
            $this->fail('reason', 'Ein Storno braucht einen Grund.');
        }

        DB::transaction(function () use ($actor, $entry, $reason) {
            $entry->forceFill([
                'status' => 'cancelled',
                'cancel_reason' => mb_substr(trim($reason), 0, 255),
                'cancelled_by_id' => $actor->id,
                'cancelled_at' => now(),
            ])->save();

            // Eingecheckte Buchungen dieser Person an diesem Tag, die durch den Eintrag
            // gedeckt waren, sind danach wieder offen und können neu eingetragen werden.
            $this->matchingCheckIns($entry)->each(fn (Booking $booking) => $booking->forceFill([
                'status' => BookingStatus::Booked,
                'checked_in_at' => null,
                'checked_in_by_id' => null,
            ])->save());
        });
    }

    /**
     * @return Collection<int, Booking>
     */
    private function matchingCheckIns(LogEntry $entry): Collection
    {
        return Booking::query()
            ->where('status', BookingStatus::CheckedIn)
            ->whereHas('shootingDay', fn ($q) => $q->whereDate('date', $entry->shot_on))
            ->when($entry->user_id,
                fn ($q) => $q->where('user_id', $entry->user_id),
                fn ($q) => $q->whereNull('user_id')->where('guest_name', $entry->guest_name))
            ->get()
            ->filter(fn (Booking $b) => WeaponClass::fromWeaponType($b->weapon_type) === $entry->weapon_class
                && ($entry->user_id === null || ($b->club_weapon ? WeaponOwner::Club : WeaponOwner::Own) === $entry->weapon_owner))
            ->values();
    }

    /**
     * Das Tor (K2): registrierte Aufsicht, nicht für sich selbst, Umfang passend.
     */
    public function ensureGate(User $actor, ?User $member, WeaponClass $class, CarbonImmutable $date): void
    {
        if (! $actor->canSupervise($date)) {
            $this->fail('entry', 'Eintragen dürfen nur Personen mit gültiger Zeile im Aufsichtsregister (§ 10 Abs. 3 AWaffV). Nachträge macht der Vorstand in der Kladde des Vereins.');
        }

        if ($class->requiresLicense() && ! $actor->canSupervise($date, licensed: true)) {
            $this->fail('weapon_class', 'Für Kurz- und Langwaffen braucht die Aufsicht den Umfang „erlaubnispflichtige Waffen“ im Register.');
        }

        if ($member !== null && $member->is($actor)) {
            $this->fail('user_id', 'Niemand trägt sich selbst ein. Bitte eine zweite Aufsicht eintragen lassen.');
        }
    }

    /**
     * Gibt es einen Disziplinkatalog, muss bei Mitgliedern eine Disziplin daraus gewählt sein.
     */
    private function ensureDiscipline(?string $discipline, bool $forMember): void
    {
        $catalog = Disciplines::all();

        if ($catalog === [] || ! $forMember) {
            return;
        }

        if ($discipline === null || ! array_key_exists($discipline, $catalog)) {
            $this->fail('discipline', 'Bitte eine Disziplin aus dem Katalog des Verbands wählen.');
        }
    }

    /**
     * Legt an oder liefert den schon vorhandenen gültigen Eintrag (Doppel-Tap, Neusenden).
     *
     * @param  array<string, mixed>  $attributes
     * @return array{entry: LogEntry, duplicate: bool}
     */
    private function insertOnce(array $attributes): array
    {
        $existing = $this->findActive($attributes);

        if ($existing) {
            return ['entry' => $existing, 'duplicate' => true];
        }

        try {
            return ['entry' => LogEntry::create($attributes), 'duplicate' => false];
        } catch (QueryException $e) {
            $existing = $this->findActive($attributes);

            if ($existing) {
                return ['entry' => $existing, 'duplicate' => true];
            }

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function findActive(array $attributes): ?LogEntry
    {
        $query = LogEntry::query()->active()->whereDate('shot_on', $attributes['shot_on']);

        if ($attributes['user_id'] ?? null) {
            $query->where('user_id', $attributes['user_id'])
                ->where('weapon_class', $attributes['weapon_class'] instanceof WeaponClass ? $attributes['weapon_class']->value : $attributes['weapon_class'])
                ->where('weapon_owner', $attributes['weapon_owner'] instanceof WeaponOwner ? $attributes['weapon_owner']->value : $attributes['weapon_owner'])
                // wie der Unique-Index: zwei Disziplinen am selben Tag sind zwei Zeilen
                ->whereRaw("coalesce(discipline, '') = ?", [(string) ($attributes['discipline'] ?? '')]);
        } else {
            $query->whereNull('user_id')->where('guest_name', $attributes['guest_name']);
        }

        return $query->first();
    }

    /**
     * @return never
     */
    private function fail(string $field, string $message): void
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
