<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Die Kladde bekommt Schüsse, die Herkunft aus der Session und die Korrektur-Verknüpfung.
     *
     * SQLite: nur rohes ADD COLUMN. Blueprint mit constrained() würde die Tabelle neu bauen
     * und dabei die Trigger stillschweigend löschen. Alle neuen Spalten sind nullable, kein Backfill
     * (der Trigger blockt Updates). Guards für einen erneuten Lauf nach einem Abbruch.
     */
    public function up(): void
    {
        $this->atomically(function () {
            $driver = DB::getDriverName();

            if ($driver === 'sqlite') {
                if (! Schema::hasColumn('log_entries', 'shots')) {
                    DB::statement('ALTER TABLE log_entries ADD COLUMN shots INTEGER NULL');
                }

                if (! Schema::hasColumn('log_entries', 'session_entry_id')) {
                    DB::statement('ALTER TABLE log_entries ADD COLUMN session_entry_id INTEGER NULL REFERENCES session_entries(id)');
                }

                if (! Schema::hasColumn('log_entries', 'corrects_id')) {
                    DB::statement('ALTER TABLE log_entries ADD COLUMN corrects_id INTEGER NULL REFERENCES log_entries(id)');
                }

                DB::statement('DROP INDEX IF EXISTS log_entries_member_unique');
                DB::statement("CREATE UNIQUE INDEX log_entries_member_unique ON log_entries (user_id, shot_on, weapon_class, weapon_owner, coalesce(discipline, ''), active_key)");
                DB::statement('DROP INDEX IF EXISTS log_entries_session_entry_unique');
                DB::statement('CREATE UNIQUE INDEX log_entries_session_entry_unique ON log_entries (session_entry_id)');

                DB::unprepared('DROP TRIGGER IF EXISTS log_entries_only_cancel');
                DB::unprepared(<<<'SQL'
                    CREATE TRIGGER log_entries_only_cancel BEFORE UPDATE ON log_entries
                    WHEN OLD.status <> 'active'
                        OR NEW.status NOT IN ('active', 'cancelled')
                        OR (NEW.status <> 'cancelled' AND (NEW.cancel_reason IS NOT OLD.cancel_reason OR NEW.cancelled_by_id IS NOT OLD.cancelled_by_id OR NEW.cancelled_at IS NOT OLD.cancelled_at))
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
                    SQL);

                return;
            }

            if (! in_array($driver, ['mysql', 'mariadb'], true)) {
                return;
            }

            // MySQL behält die Trigger beim ALTER; ungetestet (UMBAU-SESSION.md, Migration und Betrieb).
            Schema::table('log_entries', function (Blueprint $table) {
                if (! Schema::hasColumn('log_entries', 'shots')) {
                    $table->unsignedInteger('shots')->nullable();
                }

                if (! Schema::hasColumn('log_entries', 'session_entry_id')) {
                    $table->foreignId('session_entry_id')->nullable()->constrained('session_entries');
                }

                if (! Schema::hasColumn('log_entries', 'corrects_id')) {
                    $table->foreignId('corrects_id')->nullable()->constrained('log_entries');
                }
            });

            // Member-Index auf MySQL ohne coalesce: schützt schwächer, findActive prüft weiter.
            $indexes = collect(Schema::getIndexes('log_entries'))->pluck('name');

            // InnoDB braucht für den Fremdschlüssel auf user_id einen Index; bisher trug ihn der
            // alte Unique-Index. Erst einen eigenen anlegen, sonst scheitert das Löschen (1553).
            if (! $indexes->contains('log_entries_user_id_index')) {
                DB::statement('CREATE INDEX log_entries_user_id_index ON log_entries (user_id)');
            }

            if ($indexes->contains('log_entries_member_unique')) {
                DB::statement('ALTER TABLE log_entries DROP INDEX log_entries_member_unique');
            }

            DB::statement('CREATE UNIQUE INDEX log_entries_member_unique ON log_entries (user_id, shot_on, weapon_class, weapon_owner, discipline, active_key)');

            if (! $indexes->contains('log_entries_session_entry_unique')) {
                DB::statement('CREATE UNIQUE INDEX log_entries_session_entry_unique ON log_entries (session_entry_id)');
            }

            DB::unprepared('DROP TRIGGER IF EXISTS log_entries_only_cancel');
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER log_entries_only_cancel BEFORE UPDATE ON log_entries FOR EACH ROW
                BEGIN
                    IF OLD.status <> 'active' OR NEW.status NOT IN ('active', 'cancelled')
                        OR (NEW.status <> 'cancelled' AND (NOT (NEW.cancel_reason <=> OLD.cancel_reason) OR NOT (NEW.cancelled_by_id <=> OLD.cancelled_by_id) OR NOT (NEW.cancelled_at <=> OLD.cancelled_at)))
                        OR NOT (NEW.id <=> OLD.id)
                        OR NOT (NEW.user_id <=> OLD.user_id) OR NOT (NEW.guest_name <=> OLD.guest_name)
                        OR NOT (NEW.shot_on <=> OLD.shot_on) OR NOT (NEW.weapon_class <=> OLD.weapon_class)
                        OR NOT (NEW.weapon_owner <=> OLD.weapon_owner) OR NOT (NEW.discipline <=> OLD.discipline)
                        OR NOT (NEW.recorded_by_id <=> OLD.recorded_by_id) OR NOT (NEW.source <=> OLD.source)
                        OR NOT (NEW.late_reason <=> OLD.late_reason) OR NOT (NEW.booking_id <=> OLD.booking_id)
                        OR NOT (NEW.created_at <=> OLD.created_at) OR NOT (NEW.shots <=> OLD.shots)
                        OR NOT (NEW.session_entry_id <=> OLD.session_entry_id) OR NOT (NEW.corrects_id <=> OLD.corrects_id) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Kladde-Einträge werden nicht geändert';
                    END IF;
                END
                SQL);
        });
    }

    /**
     * Setzt nur Indizes und Trigger zurück; die Spalten bleiben (ADD COLUMN ohne Neubau).
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        $this->atomically(function () {
            DB::statement('DROP INDEX IF EXISTS log_entries_session_entry_unique');
            DB::statement('DROP INDEX IF EXISTS log_entries_member_unique');
            DB::statement('CREATE UNIQUE INDEX log_entries_member_unique ON log_entries (user_id, shot_on, weapon_class, weapon_owner, active_key)');
            DB::unprepared('DROP TRIGGER IF EXISTS log_entries_only_cancel');
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER log_entries_only_cancel BEFORE UPDATE ON log_entries
                WHEN OLD.status <> 'active'
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
                BEGIN SELECT RAISE(ABORT, 'Kladde-Einträge werden nicht geändert, nur einmal storniert.'); END;
                SQL);
        });
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
