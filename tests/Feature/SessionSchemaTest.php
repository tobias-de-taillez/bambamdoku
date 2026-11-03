<?php

namespace Tests\Feature;

use App\Domain\ShootingRecord;
use App\Models\Booking;
use App\Models\LogEntry;
use App\Models\ShootingDay;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Datenmodell der Session und erweiterte Kladde (UMBAU-SESSION.md, PR 3): Trigger, Indizes, Migration.
 */
class SessionSchemaTest extends TestCase
{
    use RefreshDatabase;

    private const CANCEL_FIELDS = ['status', 'cancel_reason', 'cancelled_by_id', 'cancelled_at', 'updated_at', 'active_key'];

    private User $supervisor;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(18, 0));
        $this->supervisor = User::factory()->create();
        $this->member = User::factory()->create();
    }

    /**
     * Legt Session, Teilnehmer und einen offenen Eintrag direkt in der Datenbank an.
     *
     * @return array{session: int, participant: int, entry: int}
     */
    private function rawSession(?int $bookingId = null): array
    {
        $now = now()->toDateTimeString();
        $session = DB::table('shooting_sessions')->insertGetId(['supervisor_id' => $this->supervisor->id, 'shot_on' => today()->toDateString(), 'status' => 'open', 'created_at' => $now, 'updated_at' => $now]);
        $participant = DB::table('session_participants')->insertGetId(['session_id' => $session, 'user_id' => $this->member->id, 'booking_id' => $bookingId, 'created_at' => $now, 'updated_at' => $now]);
        $entry = DB::table('session_entries')->insertGetId(['participant_id' => $participant, 'weapon_class' => 'short', 'weapon_owner' => 'own', 'shots' => 30, 'via' => 'stand', 'entered_by_id' => $this->supervisor->id, 'created_at' => $now, 'updated_at' => $now]);

        return compact('session', 'participant', 'entry');
    }

    private function sign(array $ids): void
    {
        DB::table('session_entries')->where('id', $ids['entry'])->update(['status' => 'signed']);
        DB::table('shooting_sessions')->where('id', $ids['session'])->update(['status' => 'signed', 'signed_at' => now()]);
    }

    private function assertDbRefuses(callable $action, string $message): void
    {
        try {
            $action();
            $this->fail($message);
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }

    private function booking(array $attributes = []): Booking
    {
        $day = ShootingDay::factory()->withRange()->create();

        return Booking::factory()->create(['shooting_day_id' => $day->id, 'range_id' => $day->ranges->first()->id, ...$attributes]);
    }

    private function logRow(array $attributes = []): int
    {
        return DB::table('log_entries')->insertGetId([
            'user_id' => $this->member->id,
            'shot_on' => today()->toDateString(),
            'weapon_class' => 'short',
            'weapon_owner' => 'own',
            'recorded_by_id' => $this->supervisor->id,
            'source' => 'session',
            'status' => 'active',
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
            ...$attributes,
        ]);
    }

    public function test_all_expected_triggers_and_indexes_exist_after_migrate()
    {
        $triggers = DB::table('sqlite_master')->where('type', 'trigger')->pluck('name')->all();

        foreach ([
            'log_entries_no_delete', 'log_entries_only_cancel',
            'qualifications_no_update', 'qualifications_no_delete', 'bookings_keep_logged',
            'shooting_sessions_no_delete', 'shooting_sessions_final',
            'session_participants_insert_open', 'session_participants_frozen', 'session_participants_delete_open',
            'session_entries_no_delete', 'session_entries_insert_open', 'session_entries_final',
        ] as $trigger) {
            $this->assertContains($trigger, $triggers);
        }

        $memberIndex = DB::table('sqlite_master')->where('type', 'index')->where('name', 'log_entries_member_unique')->value('sql');
        $this->assertStringContainsString("coalesce(discipline, '')", $memberIndex);
        $this->assertNotNull(DB::table('sqlite_master')->where('type', 'index')->where('name', 'log_entries_session_entry_unique')->value('sql'));
    }

    public function test_every_log_entry_column_except_cancel_fields_is_frozen()
    {
        $other = User::factory()->create();
        $id = $this->logRow(['discipline' => 'a', 'late_reason' => 'x', 'shots' => 10]);
        $booking = $this->booking();
        $values = [
            'id' => 999999, 'user_id' => $other->id, 'guest_name' => 'Neu', 'shot_on' => today()->subDay()->toDateString(),
            'weapon_class' => 'long', 'weapon_owner' => 'club', 'discipline' => 'b', 'recorded_by_id' => $other->id,
            'source' => 'stand', 'late_reason' => 'y', 'booking_id' => $booking->id, 'created_at' => '2020-01-01 00:00:00',
            'shots' => 11, 'session_entry_id' => 12345, 'corrects_id' => $id,
        ];

        $columns = array_diff(Schema::getColumnListing('log_entries'), self::CANCEL_FIELDS);
        $this->assertEqualsCanonicalizing(array_keys($values), array_values($columns));

        foreach ($columns as $column) {
            $this->assertDbRefuses(fn () => DB::table('log_entries')->where('id', $id)->update([$column => $values[$column]]), "UPDATE auf {$column} wurde nicht abgewiesen.");
        }

        $this->assertDbRefuses(fn () => DB::table('log_entries')->where('id', $id)->update(['status' => 'archived']), 'Unbekannter Status wurde angenommen.');
        $this->assertDbRefuses(fn () => DB::table('log_entries')->where('id', $id)->update(['cancel_reason' => 'ohne Storno']), 'Storno-Feld ohne Storno wurde angenommen.');
        $this->assertDbRefuses(fn () => DB::table('log_entries')->where('id', $id)->delete(), 'DELETE wurde nicht abgewiesen.');

        DB::table('log_entries')->where('id', $id)->update(['status' => 'cancelled', 'cancel_reason' => 'Fehler', 'cancelled_by_id' => $other->id, 'cancelled_at' => now()]);
        $this->assertDbRefuses(fn () => DB::table('log_entries')->where('id', $id)->update(['cancel_reason' => 'zweites Storno']), 'Zweites Storno wurde angenommen.');
    }

    public function test_two_disciplines_make_two_rows_and_count_as_one_day()
    {
        $this->logRow(['discipline' => 'BDS:1.10']);
        $this->logRow(['discipline' => 'BDS:1.20']);

        $this->assertSame(2, LogEntry::active()->count());
        $totals = collect(ShootingRecord::totals(ShootingRecord::for($this->member)->entries(today()->subYear(), today())))->keyBy('class');
        $this->assertSame(1, $totals['short']['days']);
    }

    public function test_duplicate_protection_holds_with_empty_discipline()
    {
        $this->logRow();

        $this->assertDbRefuses(fn () => $this->logRow(), 'Zweite gleiche Zeile ohne Disziplin wurde angenommen.');
        $this->assertDbRefuses(fn () => $this->logRow(['discipline' => null, 'source' => 'late_entry']), 'Nachtrag mit gleicher Kategorie wurde angenommen.');
    }

    public function test_direct_insert_with_the_same_session_entry_id_fails()
    {
        $ids = $this->rawSession();
        $this->logRow(['session_entry_id' => $ids['entry']]);

        $this->assertDbRefuses(fn () => $this->logRow(['session_entry_id' => $ids['entry'], 'weapon_class' => 'long']), 'Zweite Zeile zum selben Eintrag wurde angenommen.');
    }

    public function test_signed_session_is_frozen_against_update_delete_and_insert()
    {
        $ids = $this->rawSession();
        $this->sign($ids);
        $now = now()->toDateTimeString();

        $this->assertDbRefuses(fn () => DB::table('shooting_sessions')->where('id', $ids['session'])->update(['status' => 'open']), 'Session wurde wieder geöffnet.');
        $this->assertDbRefuses(fn () => DB::table('shooting_sessions')->where('id', $ids['session'])->update(['kiosk_token_hash' => str_repeat('a', 64)]), 'Kiosk-Link nach dem Abzeichnen wurde angenommen.');
        $this->assertDbRefuses(fn () => DB::table('shooting_sessions')->where('id', $ids['session'])->delete(), 'Session wurde gelöscht.');
        $this->assertDbRefuses(fn () => DB::table('session_participants')->where('id', $ids['participant'])->update(['guest_name' => 'X']), 'Teilnahme wurde geändert.');
        $this->assertDbRefuses(fn () => DB::table('session_participants')->where('id', $ids['participant'])->delete(), 'Teilnahme wurde gelöscht.');
        $this->assertDbRefuses(fn () => DB::table('session_participants')->insert(['session_id' => $ids['session'], 'guest_name' => 'Spät', 'created_at' => $now, 'updated_at' => $now]), 'Teilnehmer nach dem Abzeichnen wurde angenommen.');
        $this->assertDbRefuses(fn () => DB::table('session_entries')->where('id', $ids['entry'])->update(['status' => 'withdrawn']), 'Eintrag nach dem Abzeichnen wurde geändert.');
        $this->assertDbRefuses(fn () => DB::table('session_entries')->where('id', $ids['entry'])->delete(), 'Eintrag wurde gelöscht.');
        $this->assertDbRefuses(fn () => DB::table('session_entries')->insert(['participant_id' => $ids['participant'], 'weapon_class' => 'long', 'via' => 'stand', 'created_at' => $now, 'updated_at' => $now]), 'Eintrag nach dem Abzeichnen wurde angenommen.');
    }

    public function test_open_entries_only_change_their_status()
    {
        $ids = $this->rawSession();

        foreach (['shots' => 31, 'weapon_class' => 'long', 'weapon_owner' => 'club', 'discipline' => 'x', 'via' => 'kiosk', 'entered_by_id' => $this->member->id, 'participant_id' => $ids['participant'] + 1] as $column => $value) {
            $this->assertDbRefuses(fn () => DB::table('session_entries')->where('id', $ids['entry'])->update([$column => $value]), "UPDATE auf {$column} wurde nicht abgewiesen.");
        }

        $this->assertDbRefuses(fn () => DB::table('session_entries')->where('id', $ids['entry'])->update(['status' => 'rejected']), 'Ablehnen ohne Grund wurde angenommen.');
        DB::table('session_entries')->where('id', $ids['entry'])->update(['status' => 'rejected', 'reject_reason' => 'falsche Kategorie', 'rejected_by_id' => $this->supervisor->id]);
        $this->assertDbRefuses(fn () => DB::table('session_entries')->where('id', $ids['entry'])->update(['status' => 'pending']), 'Abgelehnter Eintrag wurde wieder geöffnet.');
    }

    public function test_one_open_session_per_supervisor_and_day()
    {
        $ids = $this->rawSession();
        $now = now()->toDateTimeString();
        $row = ['supervisor_id' => $this->supervisor->id, 'shot_on' => today()->toDateString(), 'status' => 'open', 'created_at' => $now, 'updated_at' => $now];

        $this->assertDbRefuses(fn () => DB::table('shooting_sessions')->insert($row), 'Zweite offene Session wurde angenommen.');

        $this->sign($ids);
        DB::table('shooting_sessions')->insert($row);
        $this->assertSame(2, DB::table('shooting_sessions')->count());
    }

    public function test_deleting_a_booking_only_clears_the_link_of_a_signed_participation()
    {
        $booking = $this->booking(['user_id' => $this->member->id]);
        $ids = $this->rawSession($booking->id);
        $this->sign($ids);

        DB::table('bookings')->where('id', $booking->id)->delete();

        $this->assertNull(DB::table('session_participants')->where('id', $ids['participant'])->value('booking_id'));
    }

    public function test_migrations_run_again_after_a_partial_abort()
    {
        $sessions = require database_path('migrations/2026_11_03_001300_create_shooting_sessions_tables.php');
        $logEntries = require database_path('migrations/2026_11_03_001400_extend_log_entries_for_sessions.php');

        // simulierter Abbruch: ein Trigger und ein Index fehlen, der Rest steht schon
        DB::unprepared('DROP TRIGGER session_entries_final');
        DB::statement('DROP INDEX log_entries_session_entry_unique');

        $sessions->up();
        $logEntries->up();
        $logEntries->up();

        $this->assertSame(1, DB::table('sqlite_master')->where('type', 'trigger')->where('name', 'session_entries_final')->count());
        $this->assertSame(1, DB::table('sqlite_master')->where('type', 'index')->where('name', 'log_entries_session_entry_unique')->count());
        $this->assertSame(1, collect(Schema::getColumnListing('log_entries'))->filter(fn ($c) => $c === 'shots')->count());
    }

    public function test_existing_rows_survive_the_migration_unchanged()
    {
        $id = $this->logRow(['source' => 'stand']);
        $before = (array) DB::table('log_entries')->find($id);

        $logEntries = require database_path('migrations/2026_11_03_001400_extend_log_entries_for_sessions.php');
        $logEntries->up();

        $this->assertSame($before, (array) DB::table('log_entries')->find($id));
        $this->assertNull(LogEntry::find($id)->shots);
    }

    public function test_a_cancel_needs_reason_admin_and_time_in_the_database()
    {
        $other = User::factory()->create();
        $complete = ['status' => 'cancelled', 'cancel_reason' => 'Fehler', 'cancelled_by_id' => $other->id, 'cancelled_at' => now()->toDateTimeString()];
        $id = $this->logRow();

        $this->assertDbRefuses(fn () => DB::table('log_entries')->where('id', $id)->update(['status' => 'cancelled']), 'Storno ohne Grund, Admin und Zeitpunkt wurde angenommen.');

        foreach (['cancel_reason', 'cancelled_by_id', 'cancelled_at'] as $missing) {
            $this->assertDbRefuses(fn () => DB::table('log_entries')->where('id', $id)->update([...$complete, $missing => null]), "Storno ohne {$missing} wurde angenommen.");
        }

        $this->assertSame('active', DB::table('log_entries')->where('id', $id)->value('status'));
        DB::table('log_entries')->where('id', $id)->update($complete);
        $this->assertSame('cancelled', DB::table('log_entries')->where('id', $id)->value('status'));
    }

    public function test_the_hardened_cancel_trigger_migration_runs_again()
    {
        $migration = require database_path('migrations/2026_11_03_001500_harden_log_entries_cancel_trigger.php');
        $migration->up();
        $migration->up();

        $this->assertSame(1, DB::table('sqlite_master')->where('type', 'trigger')->where('name', 'log_entries_only_cancel')->count());
        $id = $this->logRow();
        $this->assertDbRefuses(fn () => DB::table('log_entries')->where('id', $id)->update(['status' => 'cancelled']), 'Storno ohne Felder nach erneutem Lauf angenommen.');
    }
}
