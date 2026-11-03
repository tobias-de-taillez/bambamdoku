<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Härtung des Storno-Triggers: Ein Storno (status = cancelled) braucht Grund, Admin und Zeitpunkt.
     * Bisher ließ der Trigger ein nacktes status = 'cancelled' durch; danach war die Zeile ohne Grund
     * eingefroren. Die übrige WHEN-Liste ist unverändert aus …_001400. Wiederholbar (DROP TRIGGER IF EXISTS).
     */
    public function up(): void
    {
        $this->atomically(function () {
            $driver = DB::getDriverName();

            if ($driver === 'sqlite') {
                DB::unprepared('DROP TRIGGER IF EXISTS log_entries_only_cancel');
                DB::unprepared(<<<'SQL'
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
                    SQL);

                return;
            }

            if (! in_array($driver, ['mysql', 'mariadb'], true)) {
                return;
            }

            // ungetestet wie in …_001400 (UMBAU-SESSION.md, Migration und Betrieb)
            DB::unprepared('DROP TRIGGER IF EXISTS log_entries_only_cancel');
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER log_entries_only_cancel BEFORE UPDATE ON log_entries FOR EACH ROW
                BEGIN
                    IF OLD.status <> 'active' OR NEW.status NOT IN ('active', 'cancelled')
                        OR (NEW.status <> 'cancelled' AND (NOT (NEW.cancel_reason <=> OLD.cancel_reason) OR NOT (NEW.cancelled_by_id <=> OLD.cancelled_by_id) OR NOT (NEW.cancelled_at <=> OLD.cancelled_at)))
                        OR (NEW.status = 'cancelled' AND (NEW.cancel_reason IS NULL OR NEW.cancelled_by_id IS NULL OR NEW.cancelled_at IS NULL))
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
     * Zurück auf den Trigger aus …_001400.
     */
    public function down(): void
    {
        $this->atomically(function () {
            $driver = DB::getDriverName();

            if ($driver === 'sqlite') {
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
