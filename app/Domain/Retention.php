<?php

namespace App\Domain;

use App\Models\AuditEntry;
use App\Models\Booking;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Löschlauf nach den Aufbewahrungsfristen (Q6 = A, docs/ENTSCHEIDUNGEN.md).
 *
 * - Kladde: Zeilen, deren Termin vor dem Stichtag liegt (heute minus Jahre aus den Einstellungen),
 *   werden gelöscht, mit ihnen Sessions samt Teilnehmern und Vorstufe sowie Buchungen dieser Tage.
 * - Gäste: Namen vor dem Stichtag (heute minus Monate) werden in Kladde, Session und Buchung zu
 *   „Gast (anonymisiert #<id>)“, im Protokoll zu „Gast (anonymisiert)“. Mitglieder nie.
 *
 * Alles läuft in einer Transaktion (SQLite: IMMEDIATE). Sie legt zuerst die Zeile in retention_runs an;
 * nur solange sie offen ist, lassen die Trigger Löschen und Anonymisieren zu (…_001600). Ein Probelauf
 * und ein Lauf ohne Arbeit werden zurückgerollt und hinterlassen nichts.
 */
class Retention
{
    public const RECORD_YEARS_MIN = 3;

    public const RECORD_YEARS_MAX = 10;

    public const GUEST_MONTHS_MIN = 1;

    public const GUEST_MONTHS_MAX = 60;

    /** Ersatz für Gastnamen in Protokolltexten; in den Tabellen mit Zeilennummer (Unique-Indizes). */
    public const ANONYMIZED = 'Gast (anonymisiert)';

    /**
     * Frist der Kladde in Jahren. Ein Wert außerhalb der Grenzen (Handeingriff in settings) wird
     * begrenzt, sonst bräche jeder Lauf am Trigger ab.
     */
    public static function recordYears(): int
    {
        return max(self::RECORD_YEARS_MIN, min(self::RECORD_YEARS_MAX, (int) config('verein.retention_record_years', 5)));
    }

    public static function guestMonths(): int
    {
        return max(self::GUEST_MONTHS_MIN, min(self::GUEST_MONTHS_MAX, (int) config('verein.retention_guest_months', 12)));
    }

    /**
     * @return array{record_cutoff: string, guest_cutoff: string, deleted_entries: int, deleted_sessions: int, deleted_bookings: int, anonymized_guests: int, anonymized_texts: int, run_id: int|null}
     */
    public function run(bool $dryRun = false): array
    {
        if (DB::getDriverName() !== 'sqlite') {
            throw new DomainException('Der Löschlauf läuft nur mit SQLite. Unter MySQL/MariaDB fehlen die Ausnahmen in den Triggern; diese Datenbanken sind für den Echtbetrieb nicht freigegeben.');
        }

        DB::beginTransaction();

        try {
            $result = $this->execute();
            $changed = $result['deleted_entries'] + $result['deleted_sessions'] + $result['deleted_bookings']
                + $result['anonymized_guests'] + $result['anonymized_texts'] > 0;

            if ($dryRun || ! $changed) {
                DB::rollBack();
                $result['run_id'] = null;
            } else {
                DB::commit();
            }
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        return $result;
    }

    /**
     * @return array{record_cutoff: string, guest_cutoff: string, deleted_entries: int, deleted_sessions: int, deleted_bookings: int, anonymized_guests: int, anonymized_texts: int, run_id: int|null}
     */
    private function execute(): array
    {
        [$recordCutoff, $guestCutoff] = $this->cutoffs();

        // Ein eigener Lauf ist außerhalb seiner Transaktion nie offen sichtbar. Ein offener stammt also
        // von einem Eingriff in die Datenbank und hielte die Ausnahme der Trigger offen.
        if (DB::table('retention_runs')->whereNull('finished_at')->exists()) {
            throw new DomainException('In retention_runs steht ein offener Löschlauf, den nicht dieses System angelegt hat. Bitte prüfen; bis dahin wird nichts gelöscht.');
        }

        // Namen vor dem Löschen sammeln: Auch gelöschte Gastzeilen haben Namen im Protokoll.
        $textCutoff = max($guestCutoff, $recordCutoff);
        $names = $this->guestNames($textCutoff);

        $runId = DB::table('retention_runs')->insertGetId([
            'record_cutoff' => $recordCutoff,
            'guest_cutoff' => $guestCutoff,
            'started_at' => now()->toDateTimeString(),
        ]);

        $deletedEntries = $this->deleteEntries($recordCutoff);
        $deletedSessions = $this->deleteSessions($recordCutoff);
        $deletedBookings = $this->deleteBookings($recordCutoff);
        $anonymized = 0;

        foreach ($this->guestRows($guestCutoff) as $rows) {
            $anonymized += $rows->update(['guest_name' => DB::raw("'Gast (anonymisiert #' || id || ')'")]);
        }

        $texts = $this->scrubAudit($textCutoff, $names);

        DB::table('retention_runs')->where('id', $runId)->update([
            'deleted_entries' => $deletedEntries,
            'deleted_sessions' => $deletedSessions,
            'deleted_bookings' => $deletedBookings,
            'anonymized_guests' => $anonymized,
            'finished_at' => now()->toDateTimeString(),
        ]);

        AuditEntry::record(null, 'retention.run', null, sprintf(
            'Löschlauf #%d: Termine vor %s gelöscht (%d Kladde-Zeilen, %d Sessions, %d Buchungen); Gastnamen vor %s anonymisiert (%d Zeilen, %d Protokolltexte)',
            $runId,
            CarbonImmutable::parse($recordCutoff)->format('d.m.Y'),
            $deletedEntries,
            $deletedSessions,
            $deletedBookings,
            CarbonImmutable::parse($guestCutoff)->format('d.m.Y'),
            $anonymized,
            $texts,
        ));

        return [
            'record_cutoff' => $recordCutoff,
            'guest_cutoff' => $guestCutoff,
            'deleted_entries' => $deletedEntries,
            'deleted_sessions' => $deletedSessions,
            'deleted_bookings' => $deletedBookings,
            'anonymized_guests' => $anonymized,
            'anonymized_texts' => $texts,
            'run_id' => $runId,
        ];
    }

    /**
     * Stichtage als Y-m-d: Termine davor fallen unter die Frist, der Stichtag selbst nicht.
     * Die Trigger rechnen mit der Uhr der Datenbank (UTC); kurz nach Mitternacht oder bei der
     * Mindestfrist wäre der Stichtag sonst einen Tag zu jung, und der Lauf bräche ab.
     *
     * @return array{0: string, 1: string}
     */
    private function cutoffs(): array
    {
        $today = CarbonImmutable::today();
        /** @var object{record: string, guest: string} $floor */
        $floor = DB::selectOne("SELECT date('now', '-3 years') AS record, date('now', '-1 month') AS guest");

        return [
            min($today->subYearsNoOverflow(self::recordYears())->toDateString(), $floor->record),
            min($today->subMonthsNoOverflow(self::guestMonths())->toDateString(), $floor->guest),
        ];
    }

    /**
     * Kladde-Zeilen in einem Statement (SQLite prüft corrects_id am Ende des Statements). Eine Zeile,
     * auf die eine jüngere Korrektur verweist, bleibt; das kommt im Ablauf nicht vor, weil eine
     * Korrektur das Datum der Ursprungszeile trägt.
     */
    private function deleteEntries(string $cutoff): int
    {
        return DB::table('log_entries')
            ->whereRaw('date(shot_on) < ?', [$cutoff])
            ->whereNotExists(fn (Builder $q) => $q->selectRaw('1')->from('log_entries as c')
                ->whereColumn('c.corrects_id', 'log_entries.id')
                ->whereRaw('date(c.shot_on) >= ?', [$cutoff]))
            ->delete();
    }

    /**
     * Vorstufe, Teilnehmer, Session in dieser Reihenfolge (Fremdschlüssel RESTRICT). Was noch von einer
     * verbliebenen Kladde-Zeile referenziert wird, bleibt mit allem darüber stehen.
     */
    private function deleteSessions(string $cutoff): int
    {
        $sessions = fn (Builder $q) => $q->select('id')->from('shooting_sessions')->whereRaw('date(shot_on) < ?', [$cutoff]);

        DB::table('session_entries')
            ->whereIn('participant_id', fn (Builder $q) => $q->select('id')->from('session_participants')->whereIn('session_id', $sessions))
            ->whereNotExists(fn (Builder $q) => $q->selectRaw('1')->from('log_entries')->whereColumn('log_entries.session_entry_id', 'session_entries.id'))
            ->delete();

        DB::table('session_participants')
            ->whereIn('session_id', $sessions)
            ->whereNotExists(fn (Builder $q) => $q->selectRaw('1')->from('session_entries')->whereColumn('session_entries.participant_id', 'session_participants.id'))
            ->delete();

        return DB::table('shooting_sessions')
            ->whereRaw('date(shot_on) < ?', [$cutoff])
            ->whereNotExists(fn (Builder $q) => $q->selectRaw('1')->from('session_participants')->whereColumn('session_participants.session_id', 'shooting_sessions.id'))
            ->delete();
    }

    /**
     * Buchungen der Tage vor dem Stichtag: Mit Check-in sind sie eine zweite Anwesenheitsliste und
     * dürfen die Kladde nicht überdauern. Eine Buchung mit verbliebener Kladde-Zeile bleibt
     * (bookings_keep_logged). Schießtage und Aufsichten bleiben.
     */
    private function deleteBookings(string $cutoff): int
    {
        return DB::table('bookings')
            ->whereIn('shooting_day_id', fn (Builder $q) => $q->select('id')->from('shooting_days')->whereRaw('date("date") < ?', [$cutoff]))
            ->whereNotExists(fn (Builder $q) => $q->selectRaw('1')->from('log_entries')->whereColumn('log_entries.booking_id', 'bookings.id'))
            ->delete();
    }

    /**
     * Noch nicht anonymisierte Gastzeilen vor dem Stichtag, je Tabelle. Dieselbe Bedingung wie in den
     * Triggern: Mitglied nie (user_id), Datum des Termins vor dem Stichtag.
     *
     * @return list<Builder>
     */
    private function guestRows(string $cutoff): array
    {
        $pending = fn (string $table) => DB::table($table)
            ->whereNull('user_id')
            ->whereNotNull('guest_name')
            ->whereRaw("guest_name IS NOT ('Gast (anonymisiert #' || id || ')')");

        return [
            $pending('log_entries')->whereRaw('date(shot_on) < ?', [$cutoff]),
            $pending('session_participants')->whereIn('session_id', fn (Builder $q) => $q->select('id')->from('shooting_sessions')->whereRaw('date(shot_on) < ?', [$cutoff])),
            $pending('bookings')->whereIn('shooting_day_id', fn (Builder $q) => $q->select('id')->from('shooting_days')->whereRaw('date("date") < ?', [$cutoff])),
        ];
    }

    /**
     * @return list<string> längste zuerst, damit „Max Muster“ vor „Max“ greift
     */
    private function guestNames(string $cutoff): array
    {
        $names = [];

        foreach ($this->guestRows($cutoff) as $rows) {
            foreach ($rows->distinct()->pluck('guest_name') as $name) {
                $name = trim((string) $name);

                if ($name !== '' && ! str_starts_with($name, 'Gast (anonymisiert')) {
                    $names[$name] = mb_strlen($name);
                }
            }
        }

        arsort($names);

        return array_map('strval', array_keys($names));
    }

    /**
     * Protokolltexte vor dem Stichtag zu Buchungen, die keinem Mitglied gehören (Gastbuchung oder schon
     * gelöscht). Nur dort stehen Gastnamen (Bookings::book, Bookings::describe); Texte über Mitglieder
     * bleiben unberührt, auch wenn ein Gast gleich heißt. Ersetzt werden die gesammelten Namen als
     * ganze Wörter und der Name in „Gast … gebucht:“, auch wenn die Buchung schon gelöscht ist.
     *
     * @param  list<string>  $names
     */
    private function scrubAudit(string $cutoff, array $names): int
    {
        $patterns = ['/^Gast .+? gebucht: /u' => self::ANONYMIZED.' gebucht: '];

        foreach (array_chunk($names, 100) as $chunk) {
            $alternatives = implode('|', array_map(fn (string $name) => preg_quote($name, '/'), $chunk));
            $patterns['/(?:Gast\s+)?(?<![\p{L}\p{N}])(?:'.$alternatives.')(?![\p{L}\p{N}])/u'] = self::ANONYMIZED;
        }

        $changed = 0;

        DB::table('audit_entries')
            ->where('created_at', '<', $cutoff)
            ->where('subject_type', (new Booking)->getMorphClass())
            ->whereNotIn('subject_id', fn (Builder $q) => $q->select('id')->from('bookings')->whereNotNull('user_id'))
            ->select(['id', 'summary'])
            ->chunkById(500, function ($rows) use ($patterns, &$changed) {
                foreach ($rows as $row) {
                    $text = preg_replace(array_keys($patterns), array_values($patterns), (string) $row->summary);

                    if (is_string($text) && $text !== $row->summary) {
                        DB::table('audit_entries')->where('id', $row->id)->update(['summary' => mb_substr($text, 0, 255)]);
                        $changed++;
                    }
                }
            });

        return $changed;
    }
}
