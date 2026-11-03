<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const TRIGGERS = [
        'shooting_sessions_no_delete',
        'shooting_sessions_final',
        'session_participants_insert_open',
        'session_participants_frozen',
        'session_participants_delete_open',
        'session_entries_no_delete',
        'session_entries_insert_open',
        'session_entries_final',
    ];

    /**
     * Schieß-Session der Aufsicht (UMBAU-SESSION.md, Datenmodell): Teilnehmer und die Vorstufe
     * ihrer Einträge. Erst das Abzeichnen macht daraus Zeilen in der Kladde (log_entries).
     *
     * Laravel fasst SQLite-Migrationen nicht in eine Transaktion; deshalb hier selbst, mit Guards
     * für einen erneuten Lauf durch cron.php nach einem Abbruch.
     */
    public function up(): void
    {
        $this->atomically(function () {
            if (! Schema::hasTable('shooting_sessions')) {
                Schema::create('shooting_sessions', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('supervisor_id')->constrained('users')->restrictOnDelete();
                    $table->date('shot_on');
                    $table->string('status')->default('open');
                    $table->timestamp('signed_at')->nullable();
                    $table->string('kiosk_token_hash', 64)->nullable()->unique();
                    $table->timestamps();

                    // 1 bei offenen Sessions, sonst NULL: je Aufsicht und Tag höchstens eine offene.
                    $table->unsignedTinyInteger('open_key')->nullable()->storedAs("case when status = 'open' then 1 end");
                    $table->unique(['supervisor_id', 'shot_on', 'open_key'], 'shooting_sessions_open_unique');
                    $table->index('shot_on');
                });
            }

            if (! Schema::hasTable('session_participants')) {
                Schema::create('session_participants', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('session_id')->constrained('shooting_sessions')->restrictOnDelete();
                    $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
                    $table->string('guest_name')->nullable();
                    $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
                    $table->timestamps();

                    $table->unique(['session_id', 'user_id']);
                    $table->unique(['session_id', 'guest_name']);
                });
            }

            if (! Schema::hasTable('session_entries')) {
                Schema::create('session_entries', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('participant_id')->constrained('session_participants')->restrictOnDelete();
                    // '' statt NULL, damit der Unique-Index auf SQLite und MySQL gleich wirkt.
                    $table->string('discipline')->default('');
                    $table->string('weapon_class');
                    $table->string('weapon_owner')->default('');
                    $table->unsignedInteger('shots')->nullable();
                    $table->string('via');
                    $table->foreignId('entered_by_id')->nullable()->constrained('users')->restrictOnDelete();
                    $table->string('status')->default('pending');
                    $table->string('reject_reason')->nullable();
                    $table->foreignId('rejected_by_id')->nullable()->constrained('users')->restrictOnDelete();
                    $table->timestamps();

                    // 1 bei offenen und abgezeichneten Einträgen: Doppel-Tap legt nichts doppelt an.
                    $table->unsignedTinyInteger('open_key')->nullable()->storedAs("case when status in ('pending', 'signed') then 1 end");
                    $table->unique(['participant_id', 'discipline', 'weapon_class', 'weapon_owner', 'open_key'], 'session_entries_open_unique');
                });
            }

            foreach (self::TRIGGERS as $trigger) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
            }

            $driver = DB::getDriverName();

            if ($driver === 'sqlite') {
                $this->sqliteTriggers();
            }

            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                $this->mysqlTriggers();
            }
        });
    }

    private function sqliteTriggers(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER shooting_sessions_no_delete BEFORE DELETE ON shooting_sessions
            BEGIN SELECT RAISE(ABORT, 'Sessions werden nicht gelöscht.'); END;

            CREATE TRIGGER shooting_sessions_final BEFORE UPDATE ON shooting_sessions
            WHEN OLD.status <> 'open'
                OR NEW.status NOT IN ('open', 'signed')
                OR NEW.id IS NOT OLD.id
                OR NEW.supervisor_id IS NOT OLD.supervisor_id
                OR NEW.shot_on IS NOT OLD.shot_on
                OR NEW.created_at IS NOT OLD.created_at
                OR (NEW.status = 'signed' AND NEW.signed_at IS NULL)
            BEGIN SELECT RAISE(ABORT, 'Abgezeichnete Sessions sind endgültig.'); END;

            CREATE TRIGGER session_participants_insert_open BEFORE INSERT ON session_participants
            WHEN (SELECT status FROM shooting_sessions WHERE id = NEW.session_id) IS NOT 'open'
            BEGIN SELECT RAISE(ABORT, 'Teilnehmer nur in offenen Sessions.'); END;

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

            CREATE TRIGGER session_entries_insert_open BEFORE INSERT ON session_entries
            WHEN (SELECT s.status FROM shooting_sessions s JOIN session_participants p ON p.session_id = s.id WHERE p.id = NEW.participant_id) IS NOT 'open'
                OR NEW.status <> 'pending'
            BEGIN SELECT RAISE(ABORT, 'Einträge nur in offenen Sessions.'); END;

            CREATE TRIGGER session_entries_final BEFORE UPDATE ON session_entries
            WHEN OLD.status <> 'pending'
                OR NEW.status NOT IN ('pending', 'withdrawn', 'rejected', 'signed')
                OR (NEW.status = 'rejected' AND (NEW.reject_reason IS NULL OR NEW.rejected_by_id IS NULL))
                OR NEW.id IS NOT OLD.id
                OR NEW.participant_id IS NOT OLD.participant_id
                OR NEW.discipline IS NOT OLD.discipline
                OR NEW.weapon_class IS NOT OLD.weapon_class
                OR NEW.weapon_owner IS NOT OLD.weapon_owner
                OR NEW.shots IS NOT OLD.shots
                OR NEW.via IS NOT OLD.via
                OR NEW.entered_by_id IS NOT OLD.entered_by_id
                OR NEW.created_at IS NOT OLD.created_at
                OR (SELECT s.status FROM shooting_sessions s JOIN session_participants p ON p.session_id = s.id WHERE p.id = OLD.participant_id) IS NOT 'open'
            BEGIN SELECT RAISE(ABORT, 'Einträge werden nicht bearbeitet, nur zurückgezogen, abgelehnt oder abgezeichnet.'); END;
            SQL);
    }

    private function mysqlTriggers(): void
    {
        DB::unprepared("CREATE TRIGGER shooting_sessions_no_delete BEFORE DELETE ON shooting_sessions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Sessions werden nicht gelöscht'");
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER shooting_sessions_final BEFORE UPDATE ON shooting_sessions FOR EACH ROW
            BEGIN
                IF OLD.status <> 'open' OR NEW.status NOT IN ('open', 'signed')
                    OR NOT (NEW.id <=> OLD.id) OR NOT (NEW.supervisor_id <=> OLD.supervisor_id)
                    OR NOT (NEW.shot_on <=> OLD.shot_on) OR NOT (NEW.created_at <=> OLD.created_at)
                    OR (NEW.status = 'signed' AND NEW.signed_at IS NULL) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Abgezeichnete Sessions sind endgültig';
                END IF;
            END
            SQL);
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER session_participants_insert_open BEFORE INSERT ON session_participants FOR EACH ROW
            BEGIN
                IF NOT ((SELECT status FROM shooting_sessions WHERE id = NEW.session_id) <=> 'open') THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Teilnehmer nur in offenen Sessions';
                END IF;
            END
            SQL);
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER session_participants_frozen BEFORE UPDATE ON session_participants FOR EACH ROW
            BEGIN
                IF NOT (NEW.id <=> OLD.id) OR NOT (NEW.session_id <=> OLD.session_id)
                    OR NOT (NEW.user_id <=> OLD.user_id) OR NOT (NEW.guest_name <=> OLD.guest_name)
                    OR NOT (NEW.created_at <=> OLD.created_at) OR NOT (NEW.updated_at <=> OLD.updated_at)
                    OR (NOT (NEW.booking_id <=> OLD.booking_id) AND NEW.booking_id IS NOT NULL) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Teilnahmen werden nicht geändert';
                END IF;
            END
            SQL);
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER session_participants_delete_open BEFORE DELETE ON session_participants FOR EACH ROW
            BEGIN
                IF NOT ((SELECT status FROM shooting_sessions WHERE id = OLD.session_id) <=> 'open') THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Teilnehmer abgezeichneter Sessions bleiben';
                END IF;
            END
            SQL);
        DB::unprepared("CREATE TRIGGER session_entries_no_delete BEFORE DELETE ON session_entries FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Einträge werden nicht gelöscht'");
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER session_entries_insert_open BEFORE INSERT ON session_entries FOR EACH ROW
            BEGIN
                IF NOT ((SELECT s.status FROM shooting_sessions s JOIN session_participants p ON p.session_id = s.id WHERE p.id = NEW.participant_id) <=> 'open')
                    OR NEW.status <> 'pending' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Einträge nur in offenen Sessions';
                END IF;
            END
            SQL);
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER session_entries_final BEFORE UPDATE ON session_entries FOR EACH ROW
            BEGIN
                IF OLD.status <> 'pending' OR NEW.status NOT IN ('pending', 'withdrawn', 'rejected', 'signed')
                    OR (NEW.status = 'rejected' AND (NEW.reject_reason IS NULL OR NEW.rejected_by_id IS NULL))
                    OR NOT (NEW.id <=> OLD.id) OR NOT (NEW.participant_id <=> OLD.participant_id)
                    OR NOT (NEW.discipline <=> OLD.discipline) OR NOT (NEW.weapon_class <=> OLD.weapon_class)
                    OR NOT (NEW.weapon_owner <=> OLD.weapon_owner) OR NOT (NEW.shots <=> OLD.shots)
                    OR NOT (NEW.via <=> OLD.via) OR NOT (NEW.entered_by_id <=> OLD.entered_by_id)
                    OR NOT (NEW.created_at <=> OLD.created_at)
                    OR NOT ((SELECT s.status FROM shooting_sessions s JOIN session_participants p ON p.session_id = s.id WHERE p.id = OLD.participant_id) <=> 'open') THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Einträge werden nicht bearbeitet';
                END IF;
            END
            SQL);
    }

    public function down(): void
    {
        foreach (self::TRIGGERS as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }

        Schema::dropIfExists('session_entries');
        Schema::dropIfExists('session_participants');
        Schema::dropIfExists('shooting_sessions');
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
