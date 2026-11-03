<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Neu angelegte Trigger; down() stellt den Stand aus …_001100, …_001300 und …_001500 wieder her.
     *
     * @var list<string>
     */
    private const TRIGGERS = [
        'log_entries_no_delete',
        'log_entries_only_cancel',
        'shooting_sessions_no_delete',
        'session_participants_frozen',
        'session_participants_delete_open',
        'session_entries_no_delete',
    ];

    /**
     * Löschlauf nach den Aufbewahrungsfristen (Q6, App\Domain\Retention).
     *
     * Die Kladde bleibt insert-only. Die Trigger bekommen eine eng begrenzte Ausnahme: Löschen und
     * Anonymisieren gehen nur, solange ein Lauf in retention_runs offen ist (finished_at IS NULL), nur
     * für Zeilen vor dessen Stichtag und nie mit einem Stichtag jünger als 3 Jahre (Kladde) bzw.
     * 1 Monat (Gastnamen), gerechnet mit der Uhr der Datenbank. Der Lauf legt seine Zeile in derselben
     * Transaktion an und schließt sie; andere Verbindungen sehen nie einen offenen Lauf.
     *
     * Alle anderen Bedingungen der Trigger sind wörtlich übernommen. Wiederholbar (DROP IF EXISTS).
     * MySQL/MariaDB: nur die Tabelle, keine Ausnahme; der Löschlauf verweigert sich dort.
     */
    public function up(): void
    {
        $this->atomically(function () {
            if (! Schema::hasTable('retention_runs')) {
                Schema::create('retention_runs', function (Blueprint $table) {
                    $table->id();
                    $table->date('record_cutoff');
                    $table->date('guest_cutoff');
                    $table->timestamp('started_at');
                    $table->timestamp('finished_at')->nullable()->index();
                    $table->unsignedInteger('deleted_entries')->default(0);
                    $table->unsignedInteger('deleted_sessions')->default(0);
                    $table->unsignedInteger('deleted_bookings')->default(0);
                    $table->unsignedInteger('anonymized_guests')->default(0);
                });
            }

            if (DB::getDriverName() !== 'sqlite') {
                return;
            }

            foreach ([...self::TRIGGERS, 'retention_runs_no_delete', 'retention_runs_finish_once'] as $trigger) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
            }

            $this->retentionRunTriggers();
            $this->triggersWithException();
        });
    }

    /**
     * Ein Lauf bleibt stehen und wird genau einmal abgeschlossen; Stichtage und Start sind fest.
     */
    private function retentionRunTriggers(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER retention_runs_no_delete BEFORE DELETE ON retention_runs
            BEGIN SELECT RAISE(ABORT, 'Löschläufe bleiben im Protokoll.'); END;

            CREATE TRIGGER retention_runs_finish_once BEFORE UPDATE ON retention_runs
            WHEN OLD.finished_at IS NOT NULL
                OR NEW.id IS NOT OLD.id
                OR NEW.record_cutoff IS NOT OLD.record_cutoff
                OR NEW.guest_cutoff IS NOT OLD.guest_cutoff
                OR NEW.started_at IS NOT OLD.started_at
            BEGIN SELECT RAISE(ABORT, 'Ein Löschlauf wird nur einmal abgeschlossen, Stichtage bleiben.'); END;
            SQL);
    }

    /**
     * Ausnahme nur bei offenem Lauf, nur vor dessen Stichtag und nie mit einem Stichtag jünger als
     * 3 Jahre bzw. 1 Monat. Update-Trigger: Die Ausnahme steht vorn, damit auch stornierte Gastzeilen
     * anonymisiert werden können; coalesce, damit ein NULL in der Ausnahme nicht durchrutscht. Erlaubt ist
     * genau „Gast (anonymisiert #<id>)“ an einer Gastzeile, alle anderen Spalten unverändert.
     */
    private function triggersWithException(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER log_entries_no_delete BEFORE DELETE ON log_entries
            WHEN NOT EXISTS (SELECT 1 FROM retention_runs r WHERE r.finished_at IS NULL
                AND date(r.record_cutoff) <= date('now', '-3 years')
                AND date(OLD.shot_on) < date(r.record_cutoff))
            BEGIN SELECT RAISE(ABORT, 'Kladde-Einträge werden nicht gelöscht, nur storniert.'); END;

            CREATE TRIGGER log_entries_only_cancel BEFORE UPDATE ON log_entries
            WHEN (NOT coalesce((
                    OLD.user_id IS NULL AND NEW.user_id IS NULL
                    AND OLD.guest_name IS NOT NULL
                    AND OLD.guest_name IS NOT ('Gast (anonymisiert #' || OLD.id || ')')
                    AND NEW.guest_name IS ('Gast (anonymisiert #' || OLD.id || ')')
                    AND NEW.id IS OLD.id
                    AND NEW.shot_on IS OLD.shot_on
                    AND NEW.weapon_class IS OLD.weapon_class
                    AND NEW.weapon_owner IS OLD.weapon_owner
                    AND NEW.discipline IS OLD.discipline
                    AND NEW.recorded_by_id IS OLD.recorded_by_id
                    AND NEW.source IS OLD.source
                    AND NEW.late_reason IS OLD.late_reason
                    AND NEW.booking_id IS OLD.booking_id
                    AND NEW.status IS OLD.status
                    AND NEW.cancel_reason IS OLD.cancel_reason
                    AND NEW.cancelled_by_id IS OLD.cancelled_by_id
                    AND NEW.cancelled_at IS OLD.cancelled_at
                    AND NEW.created_at IS OLD.created_at
                    AND NEW.updated_at IS OLD.updated_at
                    AND NEW.shots IS OLD.shots
                    AND NEW.session_entry_id IS OLD.session_entry_id
                    AND NEW.corrects_id IS OLD.corrects_id
                    AND EXISTS (SELECT 1 FROM retention_runs r WHERE r.finished_at IS NULL
                        AND date(r.guest_cutoff) <= date('now', '-1 month')
                        AND date(OLD.shot_on) < date(r.guest_cutoff))
                ), 0))
                AND (OLD.status <> 'active'
                    OR NEW.status NOT IN ('active', 'cancelled')
                    OR (NEW.status <> 'cancelled' AND (NEW.cancel_reason IS NOT OLD.cancel_reason OR NEW.cancelled_by_id IS NOT OLD.cancelled_by_id OR NEW.cancelled_at IS NOT OLD.cancelled_at))
                    OR (NEW.status = 'cancelled' AND (NEW.cancel_reason IS NULL OR NEW.cancelled_by_id IS NULL OR NEW.cancelled_at IS NULL))
                    OR NEW.id IS NOT OLD.id
                    OR NEW.user_id IS NOT OLD.user_id
                    OR NEW.guest_name IS NOT OLD.guest_name
                    OR NEW.shot_on IS NOT OLD.shot_on
                    OR NEW.weapon_class IS NOT OLD.weapon_class
                    OR NEW.weapon_owner IS NOT OLD.weapon_owner
                    OR NEW.discipline IS NOT OLD.discipline
                    OR NEW.recorded_by_id IS NOT OLD.recorded_by_id
                    OR NEW.source IS NOT OLD.source
                    OR NEW.late_reason IS NOT OLD.late_reason
                    OR NEW.booking_id IS NOT OLD.booking_id
                    OR NEW.created_at IS NOT OLD.created_at
                    OR NEW.shots IS NOT OLD.shots
                    OR NEW.session_entry_id IS NOT OLD.session_entry_id
                    OR NEW.corrects_id IS NOT OLD.corrects_id)
            BEGIN SELECT RAISE(ABORT, 'Kladde-Einträge werden nicht geändert, nur einmal storniert.'); END;

            CREATE TRIGGER shooting_sessions_no_delete BEFORE DELETE ON shooting_sessions
            WHEN NOT EXISTS (SELECT 1 FROM retention_runs r WHERE r.finished_at IS NULL
                AND date(r.record_cutoff) <= date('now', '-3 years')
                AND date(OLD.shot_on) < date(r.record_cutoff))
            BEGIN SELECT RAISE(ABORT, 'Sessions werden nicht gelöscht.'); END;

            CREATE TRIGGER session_participants_frozen BEFORE UPDATE ON session_participants
            WHEN (NOT coalesce((
                    OLD.user_id IS NULL AND NEW.user_id IS NULL
                    AND OLD.guest_name IS NOT NULL
                    AND OLD.guest_name IS NOT ('Gast (anonymisiert #' || OLD.id || ')')
                    AND NEW.guest_name IS ('Gast (anonymisiert #' || OLD.id || ')')
                    AND NEW.id IS OLD.id
                    AND NEW.session_id IS OLD.session_id
                    AND NEW.booking_id IS OLD.booking_id
                    AND NEW.created_at IS OLD.created_at
                    AND NEW.updated_at IS OLD.updated_at
                    AND EXISTS (SELECT 1 FROM retention_runs r WHERE r.finished_at IS NULL
                        AND date(r.guest_cutoff) <= date('now', '-1 month')
                        AND (SELECT date(s.shot_on) FROM shooting_sessions s WHERE s.id = OLD.session_id) < date(r.guest_cutoff))
                ), 0))
                AND (NEW.id IS NOT OLD.id
                    OR NEW.session_id IS NOT OLD.session_id
                    OR NEW.user_id IS NOT OLD.user_id
                    OR NEW.guest_name IS NOT OLD.guest_name
                    OR NEW.created_at IS NOT OLD.created_at
                    OR NEW.updated_at IS NOT OLD.updated_at
                    OR (NEW.booking_id IS NOT OLD.booking_id AND NEW.booking_id IS NOT NULL))
            BEGIN SELECT RAISE(ABORT, 'Teilnahmen werden nicht geändert.'); END;

            CREATE TRIGGER session_participants_delete_open BEFORE DELETE ON session_participants
            WHEN (SELECT status FROM shooting_sessions WHERE id = OLD.session_id) IS NOT 'open'
                AND NOT EXISTS (SELECT 1 FROM retention_runs r WHERE r.finished_at IS NULL
                    AND date(r.record_cutoff) <= date('now', '-3 years')
                    AND (SELECT date(s.shot_on) FROM shooting_sessions s WHERE s.id = OLD.session_id) < date(r.record_cutoff))
            BEGIN SELECT RAISE(ABORT, 'Teilnehmer abgezeichneter Sessions bleiben.'); END;

            CREATE TRIGGER session_entries_no_delete BEFORE DELETE ON session_entries
            WHEN NOT EXISTS (SELECT 1 FROM retention_runs r WHERE r.finished_at IS NULL
                AND date(r.record_cutoff) <= date('now', '-3 years')
                AND (SELECT date(s.shot_on) FROM shooting_sessions s JOIN session_participants p ON p.session_id = s.id WHERE p.id = OLD.participant_id) < date(r.record_cutoff))
            BEGIN SELECT RAISE(ABORT, 'Einträge werden nicht gelöscht, nur zurückgezogen oder abgelehnt.'); END;
            SQL);
    }

    public function down(): void
    {
        $this->atomically(function () {
            if (DB::getDriverName() === 'sqlite') {
                foreach ([...self::TRIGGERS, 'retention_runs_no_delete', 'retention_runs_finish_once'] as $trigger) {
                    DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
                }

                $this->previousTriggers();
            }

            Schema::dropIfExists('retention_runs');
        });
    }

    /**
     * Wörtlich aus …_001100 (no_delete), …_001500 (only_cancel) und …_001300 (Session-Tabellen).
     */
    private function previousTriggers(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER log_entries_no_delete BEFORE DELETE ON log_entries
            BEGIN SELECT RAISE(ABORT, 'Kladde-Einträge werden nicht gelöscht, nur storniert.'); END;

            CREATE TRIGGER log_entries_only_cancel BEFORE UPDATE ON log_entries
            WHEN OLD.status <> 'active'
                OR NEW.status NOT IN ('active', 'cancelled')
                OR (NEW.status <> 'cancelled' AND (NEW.cancel_reason IS NOT OLD.cancel_reason OR NEW.cancelled_by_id IS NOT OLD.cancelled_by_id OR NEW.cancelled_at IS NOT OLD.cancelled_at))
                OR (NEW.status = 'cancelled' AND (NEW.cancel_reason IS NULL OR NEW.cancelled_by_id IS NULL OR NEW.cancelled_at IS NULL))
                OR NEW.id IS NOT OLD.id
                OR NEW.user_id IS NOT OLD.user_id
                OR NEW.guest_name IS NOT OLD.guest_name
                OR NEW.shot_on IS NOT OLD.shot_on
                OR NEW.weapon_class IS NOT OLD.weapon_class
                OR NEW.weapon_owner IS NOT OLD.weapon_owner
                OR NEW.discipline IS NOT OLD.discipline
                OR NEW.recorded_by_id IS NOT OLD.recorded_by_id
                OR NEW.source IS NOT OLD.source
                OR NEW.late_reason IS NOT OLD.late_reason
                OR NEW.booking_id IS NOT OLD.booking_id
                OR NEW.created_at IS NOT OLD.created_at
                OR NEW.shots IS NOT OLD.shots
                OR NEW.session_entry_id IS NOT OLD.session_entry_id
                OR NEW.corrects_id IS NOT OLD.corrects_id
            BEGIN SELECT RAISE(ABORT, 'Kladde-Einträge werden nicht geändert, nur einmal storniert.'); END;

            CREATE TRIGGER shooting_sessions_no_delete BEFORE DELETE ON shooting_sessions
            BEGIN SELECT RAISE(ABORT, 'Sessions werden nicht gelöscht.'); END;

            CREATE TRIGGER session_participants_frozen BEFORE UPDATE ON session_participants
            WHEN NEW.id IS NOT OLD.id
                OR NEW.session_id IS NOT OLD.session_id
                OR NEW.user_id IS NOT OLD.user_id
                OR NEW.guest_name IS NOT OLD.guest_name
                OR NEW.created_at IS NOT OLD.created_at
                OR NEW.updated_at IS NOT OLD.updated_at
                OR (NEW.booking_id IS NOT OLD.booking_id AND NEW.booking_id IS NOT NULL)
            BEGIN SELECT RAISE(ABORT, 'Teilnahmen werden nicht geändert.'); END;

            CREATE TRIGGER session_participants_delete_open BEFORE DELETE ON session_participants
            WHEN (SELECT status FROM shooting_sessions WHERE id = OLD.session_id) IS NOT 'open'
            BEGIN SELECT RAISE(ABORT, 'Teilnehmer abgezeichneter Sessions bleiben.'); END;

            CREATE TRIGGER session_entries_no_delete BEFORE DELETE ON session_entries
            BEGIN SELECT RAISE(ABORT, 'Einträge werden nicht gelöscht, nur zurückgezogen oder abgelehnt.'); END;
            SQL);
    }

    /**
     * SQLite rollt DDL in einer Transaktion zurück. MySQL und MariaDB beenden eine
     * Transaktion bei jedem DDL-Befehl selbst, dort laufen die Schritte ohne Klammer;
     * die Guards machen sie wiederholbar.
     *
     * @param  Closure(): void  $steps
     */
    private function atomically(Closure $steps): void
    {
        DB::getDriverName() === 'sqlite' ? DB::transaction($steps) : $steps();
    }
};
