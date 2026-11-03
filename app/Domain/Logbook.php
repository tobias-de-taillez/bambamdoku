<?php

namespace App\Domain;

use App\Enums\LogSource;
use App\Enums\WeaponClass;
use App\Enums\WeaponOwner;
use App\Models\LogEntry;
use App\Models\SessionEntry;
use App\Models\ShootingSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Die Regeln der Schießkladde (Wettbewerbsanalyse K1–K9, UMBAU-SESSION.md).
 *
 * - Neue Zeilen entstehen nur beim Abzeichnen einer Session (Sessions::signOff) oder als
 *   Nachtrag des Admins mit Pflichtgrund. Insert-only, das Datum ist der Session-Tag.
 * - Ein zweiter gleicher Eintrag am selben Tag legt nichts Neues an (Doppelschutz K5).
 * - Korrigiert wird per Storno mit Grund, nur durch den Admin.
 */
class Logbook
{
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
     * Storno mit Grund, nur durch den Admin (die Aufsicht hat seit dem Abzeichnen kein Storno-Recht mehr).
     */
    public function cancel(User $actor, LogEntry $entry, string $reason): void
    {
        abort_unless($actor->isAdmin(), 403);

        if (! $entry->isActive()) {
            $this->fail('entry', 'Dieser Eintrag ist bereits storniert.');
        }

        if (trim($reason) === '') {
            $this->fail('reason', 'Ein Storno braucht einen Grund.');
        }

        $entry->forceFill([
            'status' => 'cancelled',
            'cancel_reason' => mb_substr(trim($reason), 0, 255),
            'cancelled_by_id' => $actor->id,
            'cancelled_at' => now(),
        ])->save();
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
