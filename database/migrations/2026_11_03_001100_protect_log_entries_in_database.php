<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Die Unveränderlichkeit von Kladde und Aufsichtsregister gilt auch in der Datenbank,
     * nicht nur im Eloquent-Model: Massen-Updates, direkte Queries und Kaskaden werden abgewiesen.
     */
    public function up(): void
    {
        // shot_on einheitlich als reines Datum, damit der Unique-Index gleiche Tage erkennt.
        foreach (DB::table('log_entries')->whereRaw('length(shot_on) > 10')->get(['id', 'shot_on']) as $row) {
            try {
                DB::table('log_entries')->where('id', $row->id)->update(['shot_on' => substr($row->shot_on, 0, 10)]);
            } catch (Throwable) {
                // Kollidiert mit einem gleichen gültigen Eintrag: unverändert lassen, die App erkennt ihn trotzdem.
            }
        }

        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER log_entries_no_delete BEFORE DELETE ON log_entries
                BEGIN SELECT RAISE(ABORT, 'Kladde-Einträge werden nicht gelöscht, nur storniert.'); END;

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

                CREATE TRIGGER qualifications_no_update BEFORE UPDATE ON qualifications
                BEGIN SELECT RAISE(ABORT, 'Das Aufsichtsregister wird nur ergänzt.'); END;

                CREATE TRIGGER qualifications_no_delete BEFORE DELETE ON qualifications
                BEGIN SELECT RAISE(ABORT, 'Das Aufsichtsregister wird nur ergänzt.'); END;

                CREATE TRIGGER bookings_keep_logged BEFORE DELETE ON bookings
                WHEN EXISTS (SELECT 1 FROM log_entries WHERE booking_id = OLD.id)
                BEGIN SELECT RAISE(ABORT, 'Buchung gehört zu einem Kladde-Eintrag und bleibt erhalten.'); END;
                SQL);
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::unprepared("CREATE TRIGGER log_entries_no_delete BEFORE DELETE ON log_entries FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Kladde-Einträge werden nicht gelöscht'");
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER log_entries_only_cancel BEFORE UPDATE ON log_entries FOR EACH ROW
                BEGIN
                    IF OLD.status <> 'active'
                        OR NOT (NEW.user_id <=> OLD.user_id) OR NOT (NEW.guest_name <=> OLD.guest_name)
                        OR NOT (NEW.shot_on <=> OLD.shot_on) OR NOT (NEW.weapon_class <=> OLD.weapon_class)
                        OR NOT (NEW.weapon_owner <=> OLD.weapon_owner) OR NOT (NEW.discipline <=> OLD.discipline)
                        OR NOT (NEW.recorded_by_id <=> OLD.recorded_by_id) OR NOT (NEW.source <=> OLD.source)
                        OR NOT (NEW.late_reason <=> OLD.late_reason) OR NOT (NEW.booking_id <=> OLD.booking_id)
                        OR NOT (NEW.created_at <=> OLD.created_at) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Kladde-Einträge werden nicht geändert';
                    END IF;
                END
                SQL);
            DB::unprepared("CREATE TRIGGER qualifications_no_update BEFORE UPDATE ON qualifications FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Register nur ergänzen'");
            DB::unprepared("CREATE TRIGGER qualifications_no_delete BEFORE DELETE ON qualifications FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Register nur ergänzen'");
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER bookings_keep_logged BEFORE DELETE ON bookings FOR EACH ROW
                BEGIN
                    IF EXISTS (SELECT 1 FROM log_entries WHERE booking_id = OLD.id) THEN
                        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Buchung gehört zu einem Kladde-Eintrag';
                    END IF;
                END
                SQL);
        }
    }

    public function down(): void
    {
        foreach (['log_entries_no_delete', 'log_entries_only_cancel', 'qualifications_no_update', 'qualifications_no_delete', 'bookings_keep_logged'] as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }
};
