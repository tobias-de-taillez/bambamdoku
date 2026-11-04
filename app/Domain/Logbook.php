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
 * - Geändert wird per Storno oder Korrektur mit Grund, nur durch den Admin und nie an eigenen Zeilen.
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
    public function lateEntries(User $admin, array $members, string $date, WeaponClass $class, WeaponOwner $owner, ?string $discipline, string $reason, ?int $shots = null): array
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

        $this->ensureDiscipline($discipline, $class->requiresLicense());
        $discipline = $this->normalizeDiscipline($discipline);
        $this->ensureShots($shots);

        $created = 0;
        $duplicates = 0;

        DB::transaction(function () use ($members, $day, $class, $owner, $discipline, $admin, $reason, $shots, &$created, &$duplicates) {
            foreach ($members as $member) {
                $result = $this->insertOnce([
                    'user_id' => $member->id,
                    'shot_on' => $day->toDateString(),
                    'weapon_class' => $class,
                    'weapon_owner' => $owner,
                    'discipline' => $discipline,
                    'shots' => $shots,
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
     * Nie an Zeilen, in denen der Admin selbst Mitglied oder Bestätiger ist (E3).
     */
    public function cancel(User $actor, LogEntry $entry, string $reason): void
    {
        $this->ensureMayChange($actor, $entry, $reason, 'Ein Storno braucht einen Grund.');

        DB::transaction(function () use ($actor, $entry, $reason) {
            $row = LogEntry::query()->lockForUpdate()->findOrFail($entry->id);

            if (! $row->isActive()) {
                $this->fail('entry', 'Dieser Eintrag ist bereits storniert.');
            }

            $this->cancelRow($row, $actor, trim($reason));
        });
    }

    /**
     * Korrektur durch den Admin (E3): Storno der alten Zeile und neue Zeile `late_entry` mit `corrects_id`
     * und Grund, in einer Transaktion. Person und Datum bleiben; dafür gibt es Storno plus Nachtrag.
     * Kurz- und Langwaffe nur, wenn die Ursprungszeile schon Kurz- oder Langwaffe war.
     */
    public function correct(User $actor, LogEntry $entry, WeaponClass $class, ?WeaponOwner $owner, ?string $discipline, ?int $shots, string $reason): LogEntry
    {
        $this->ensureMayChange($actor, $entry, $reason, 'Eine Korrektur braucht einen Grund.');

        if (mb_strlen(trim($reason)) < 5) {
            $this->fail('reason', 'Bitte den Grund der Korrektur nennen, z. B. „Kategorie falsch erfasst, laut Aufsicht …“.');
        }

        if ($class->requiresLicense() && ! $entry->weapon_class->requiresLicense()) {
            $this->fail('weapon_class', 'Kurz- und Langwaffe nur, wenn die Zeile schon Kurz- oder Langwaffe war. Sonst Storno und Nachtrag.');
        }

        $member = $entry->user_id !== null;

        if ($member) {
            if ($owner === null) {
                $this->fail('weapon_owner', 'Bitte angeben, ob eine eigene oder eine Vereinswaffe genutzt wurde.');
            }

            $this->ensureDiscipline($discipline, $class->requiresLicense());
            $discipline = $this->normalizeDiscipline($discipline);
        } else {
            // Gäste: ohne eigen/Verein und ohne Disziplin
            $owner = null;
            $discipline = null;
        }

        $this->ensureShots($shots);

        if ($class === $entry->weapon_class && $owner === $entry->weapon_owner && $discipline === $entry->discipline && $shots === $entry->shots) {
            $this->fail('entry', 'Die Korrektur ändert nichts.');
        }

        return DB::transaction(function () use ($actor, $entry, $class, $owner, $discipline, $shots, $reason) {
            $row = LogEntry::query()->lockForUpdate()->findOrFail($entry->id);

            if (! $row->isActive()) {
                $this->fail('entry', 'Dieser Eintrag ist bereits storniert.');
            }

            // Erst stornieren: dann blockiert der Unique-Index die neue Zeile nicht mehr.
            $this->cancelRow($row, $actor, 'Korrektur: '.trim($reason));

            $attributes = [
                'user_id' => $row->user_id,
                'guest_name' => $row->guest_name,
                'shot_on' => $row->shot_on->toDateString(),
                'weapon_class' => $class,
                'weapon_owner' => $owner,
                'discipline' => $discipline,
                'shots' => $shots,
                'recorded_by_id' => $actor->id,
                'source' => LogSource::LateEntry,
                'late_reason' => trim($reason),
                // session_entry_id bleibt an der alten Zeile (Unique-Index); die Herkunft läuft über corrects_id.
                'corrects_id' => $row->id,
            ];

            if ($this->findActive($attributes)) {
                $this->fail('entry', 'Für diesen Tag gibt es diese Zeile schon. Dann nur stornieren.');
            }

            return LogEntry::create($attributes);
        });
    }

    /**
     * Gemeinsame Regeln für Storno und Korrektur.
     */
    private function ensureMayChange(User $actor, LogEntry $entry, string $reason, string $missingReason): void
    {
        abort_unless($actor->isAdmin(), 403);

        if (! $entry->isActive()) {
            $this->fail('entry', 'Dieser Eintrag ist bereits storniert.');
        }

        // Über die ganze Korrekturkette: Wer die Ursprungszeile bestätigt hat, bleibt Bestätiger (confirmedByLabel).
        if ($entry->involves($actor->id)) {
            $this->fail('entry', 'Nie an Zeilen, in denen Sie selbst Mitglied oder Bestätiger sind. Das übernimmt ein zweiter Admin.');
        }

        if (trim($reason) === '') {
            $this->fail('reason', $missingReason);
        }
    }

    private function cancelRow(LogEntry $row, User $actor, string $reason): void
    {
        $row->forceFill([
            'status' => 'cancelled',
            'cancel_reason' => mb_substr($reason, 0, 255),
            'cancelled_by_id' => $actor->id,
            'cancelled_at' => now(),
        ])->save();
    }

    private function ensureShots(?int $shots): void
    {
        if ($shots !== null && ($shots < 1 || $shots > Sessions::MAX_SHOTS)) {
            $this->fail('shots', sprintf('Die Schusszahl muss zwischen 1 und %d liegen.', Sessions::MAX_SHOTS));
        }
    }

    /**
     * Gibt es einen Disziplinkatalog, muss eine angegebene Disziplin daraus stammen. Pflicht ist sie
     * nur bei erlaubnispflichtigen Kategorien (wie Sessions::normalizeDiscipline).
     */
    private function ensureDiscipline(?string $discipline, bool $required): void
    {
        $catalog = Disciplines::all();

        if ($catalog === [] || ($discipline === null && ! $required)) {
            return;
        }

        if ($discipline === null || ! array_key_exists($discipline, $catalog)) {
            $this->fail('discipline', 'Bitte eine Disziplin aus dem Katalog des Verbands wählen.');
        }
    }

    /**
     * Ohne Katalog wird keine Disziplin gespeichert (NULL), sonst greift der Doppelschutz nicht (wie Sessions::normalizeDiscipline).
     */
    private function normalizeDiscipline(?string $discipline): ?string
    {
        return Disciplines::all() === [] ? null : $discipline;
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
