<?php

namespace Tests\Feature;

use App\Domain\Retention;
use App\Models\Booking;
use App\Models\ShootingDay;
use App\Models\User;
use App\Support\ClubSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Löschlauf nach den Aufbewahrungsfristen (Q6): Kladde 5 Jahre, Gastnamen 12 Monate, in den Einstellungen.
 *
 * Die Trigger rechnen mit der Uhr der Datenbank (date('now'), UTC), die travelTo nicht verstellt. Die Tests
 * stellen PHP deshalb auf den heutigen Tag der Datenbank und legen alte Daten relativ dazu an, nie in der Zukunft.
 */
class RetentionTest extends TestCase
{
    use RefreshDatabase;

    private const TABLES = ['log_entries', 'session_entries', 'session_participants', 'shooting_sessions', 'bookings', 'audit_entries', 'retention_runs'];

    private User $supervisor;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse(DB::selectOne("SELECT date('now') AS d")->d.' 12:00:00'));
        $this->supervisor = User::factory()->create();
        $this->member = User::factory()->create(['name' => 'Max Gast']);
    }

    private function yearsAgo(int $years, int $days = 0): string
    {
        return today()->subYearsNoOverflow($years)->subDays($days)->toDateString();
    }

    private function monthsAgo(int $months, int $days = 0): string
    {
        return today()->subMonthsNoOverflow($months)->subDays($days)->toDateString();
    }

    private function logRow(string $day, array $attributes = []): int
    {
        return DB::table('log_entries')->insertGetId([
            'user_id' => $this->member->id,
            'shot_on' => $day,
            'weapon_class' => 'short',
            'weapon_owner' => 'own',
            'recorded_by_id' => $this->supervisor->id,
            'source' => 'session',
            'status' => 'active',
            'created_at' => $day.' 18:00:00',
            'updated_at' => $day.' 18:00:00',
            ...$attributes,
        ]);
    }

    private function guestRow(string $day, string $name, array $attributes = []): int
    {
        return $this->logRow($day, ['user_id' => null, 'guest_name' => $name, 'weapon_owner' => null, ...$attributes]);
    }

    private function cancel(int $id): void
    {
        DB::table('log_entries')->where('id', $id)->update(['status' => 'cancelled', 'cancel_reason' => 'Fehleingabe', 'cancelled_by_id' => $this->supervisor->id, 'cancelled_at' => now()]);
    }

    /**
     * Abgezeichnete Session mit einer Person, einem Eintrag und der Kladde-Zeile daraus.
     *
     * @return array{session: int, participant: int, entry: int, log: int}
     */
    private function signedSession(string $day, ?string $guest = null, ?int $bookingId = null): array
    {
        $at = $day.' 18:00:00';
        $session = DB::table('shooting_sessions')->insertGetId(['supervisor_id' => $this->supervisor->id, 'shot_on' => $day, 'status' => 'open', 'created_at' => $at, 'updated_at' => $at]);
        $participant = DB::table('session_participants')->insertGetId(['session_id' => $session, 'user_id' => $guest ? null : $this->member->id, 'guest_name' => $guest, 'booking_id' => $bookingId, 'created_at' => $at, 'updated_at' => $at]);
        $entry = DB::table('session_entries')->insertGetId(['participant_id' => $participant, 'weapon_class' => 'short', 'weapon_owner' => $guest ? '' : 'own', 'shots' => 20, 'via' => 'stand', 'entered_by_id' => $this->supervisor->id, 'created_at' => $at, 'updated_at' => $at]);
        DB::table('session_entries')->where('id', $entry)->update(['status' => 'signed']);
        DB::table('shooting_sessions')->where('id', $session)->update(['status' => 'signed', 'signed_at' => $at]);
        $log = $guest
            ? $this->guestRow($day, $guest, ['session_entry_id' => $entry, 'booking_id' => $bookingId])
            : $this->logRow($day, ['session_entry_id' => $entry, 'booking_id' => $bookingId]);

        return compact('session', 'participant', 'entry', 'log');
    }

    private function booking(string $day, ?string $guest = null): int
    {
        $shootingDay = ShootingDay::factory()->withRange()->create(['date' => $day]);

        return Booking::factory()->create([
            'shooting_day_id' => $shootingDay->id,
            'range_id' => $shootingDay->ranges->first()->id,
            'user_id' => $guest === null ? $this->member->id : null,
            'guest_name' => $guest,
            'host_id' => $guest === null ? null : $this->member->id,
        ])->id;
    }

    private function audit(string $day, string $summary, ?int $bookingId = null, string $action = 'booking.created'): int
    {
        return DB::table('audit_entries')->insertGetId([
            'actor_id' => $this->supervisor->id,
            'action' => $action,
            'subject_type' => $bookingId === null ? null : (new Booking)->getMorphClass(),
            'subject_id' => $bookingId,
            'summary' => $summary,
            'created_at' => $day.' 10:00:00',
        ]);
    }

    /**
     * Ein Lauf, wie ihn nur ein Eingriff in die Datenbank anlegt; Stichtage als SQL der Datenbank-Uhr.
     */
    private function forgeRun(string $recordCutoffSql, string $guestCutoffSql, bool $finished = false): void
    {
        DB::insert("INSERT INTO retention_runs (record_cutoff, guest_cutoff, started_at, finished_at) VALUES ({$recordCutoffSql}, {$guestCutoffSql}, datetime('now'), ".($finished ? "datetime('now')" : 'NULL').')');
    }

    private function anonymizedName(int $id): string
    {
        return 'Gast (anonymisiert #'.$id.')';
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

    /**
     * @return array<string, string>
     */
    private function snapshot(): array
    {
        return collect(self::TABLES)->mapWithKeys(fn (string $table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    }

    public function test_deletes_kladde_session_data_and_bookings_only_before_the_record_cutoff()
    {
        $oldDay = $this->yearsAgo(5, 1);
        $oldBooking = $this->booking($oldDay);
        $old = $this->signedSession($oldDay, null, $oldBooking);
        $oldLate = $this->logRow($oldDay, ['source' => 'late_entry', 'late_reason' => 'Papierliste', 'weapon_class' => 'long']);
        $original = $this->logRow($this->yearsAgo(6), ['weapon_class' => 'exempt']);
        $this->cancel($original);
        $correction = $this->logRow($this->yearsAgo(6), ['source' => 'late_entry', 'late_reason' => 'Korrektur', 'corrects_id' => $original]);
        $oldGuest = $this->signedSession($this->yearsAgo(7), 'Gerd Alt');

        $boundary = $this->signedSession($this->yearsAgo(5));
        $boundaryBooking = $this->booking($this->yearsAgo(5));
        $young = $this->signedSession($this->yearsAgo(4));
        $youngBooking = $this->booking($this->yearsAgo(4));

        $this->artisan('verein:aufbewahrung')->expectsOutputToContain('Löschlauf #')->assertSuccessful();

        foreach ([$old['log'], $oldLate, $original, $correction, $oldGuest['log']] as $id) {
            $this->assertFalse(DB::table('log_entries')->where('id', $id)->exists(), "Kladde-Zeile {$id} vor dem Stichtag steht noch.");
        }

        $this->assertFalse(DB::table('shooting_sessions')->whereIn('id', [$old['session'], $oldGuest['session']])->exists());
        $this->assertFalse(DB::table('session_participants')->whereIn('id', [$old['participant'], $oldGuest['participant']])->exists());
        $this->assertFalse(DB::table('session_entries')->whereIn('id', [$old['entry'], $oldGuest['entry']])->exists());
        $this->assertFalse(DB::table('bookings')->where('id', $oldBooking)->exists());

        foreach ([$boundary, $young] as $kept) {
            $this->assertTrue(DB::table('log_entries')->where('id', $kept['log'])->exists());
            $this->assertTrue(DB::table('shooting_sessions')->where('id', $kept['session'])->exists());
            $this->assertTrue(DB::table('session_participants')->where('id', $kept['participant'])->exists());
            $this->assertTrue(DB::table('session_entries')->where('id', $kept['entry'])->exists());
        }

        $this->assertTrue(DB::table('bookings')->whereIn('id', [$boundaryBooking, $youngBooking])->count() === 2);

        $run = DB::table('retention_runs')->sole();
        $this->assertSame($this->yearsAgo(5), $run->record_cutoff);
        $this->assertSame($this->monthsAgo(12), $run->guest_cutoff);
        $this->assertNotNull($run->finished_at);
        $this->assertSame(5, (int) $run->deleted_entries);
        $this->assertSame(2, (int) $run->deleted_sessions);
        $this->assertSame(1, (int) $run->deleted_bookings);
        $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
    }

    public function test_anonymizes_only_guest_names_before_the_guest_cutoff_in_every_table_and_the_protocol()
    {
        $oldDay = $this->monthsAgo(12, 1);
        $youngDay = $this->monthsAgo(11);

        $oldBooking = $this->booking($oldDay, 'Erika Besuch');
        $oldSession = $this->signedSession($oldDay, 'Max Gast', $oldBooking);
        $cancelled = $this->guestRow($oldDay, 'Otto Storno');
        $this->cancel($cancelled);
        $shortName = $this->guestRow($this->monthsAgo(14), 'Max');
        $youngSession = $this->signedSession($youngDay, 'Max Gast');
        $youngBooking = $this->booking($youngDay, 'Erika Besuch');
        $memberSession = $this->signedSession($oldDay);
        $memberBooking = $this->booking($oldDay);

        $texts = [
            'booked' => [$this->audit($oldDay, 'Gast Erika Besuch gebucht: 01.01.2025, Stand 1, 18:00–19:00 Uhr', $oldBooking), 'Gast (anonymisiert) gebucht: 01.01.2025, Stand 1, 18:00–19:00 Uhr'],
            'activated' => [$this->audit($oldDay, 'Aktiviert: Erika Besuch, 01.01.2025, 18:00–19:00 Uhr', $oldBooking, 'booking.checked_in'), 'Aktiviert: Gast (anonymisiert), 01.01.2025, 18:00–19:00 Uhr'],
            // Buchung schon gelöscht (Schießtag entfernt): Name aus der Liste und Muster „Gast … gebucht:“
            'gone' => [$this->audit($oldDay, 'Storniert: Max Gast, 01.01.2025, 18:00–19:00 Uhr', 999999, 'booking.cancelled'), 'Storniert: Gast (anonymisiert), 01.01.2025, 18:00–19:00 Uhr'],
            'gonePattern' => [$this->audit($oldDay, 'Gast Hans Unbekannt gebucht: 01.01.2025, Stand 1, 18:00–19:00 Uhr', 999998), 'Gast (anonymisiert) gebucht: 01.01.2025, Stand 1, 18:00–19:00 Uhr'],
            'wordBoundary' => [$this->audit($oldDay, 'Nicht erschienen: Maximiliane, 01.01.2025, 18:00–19:00 Uhr', 999997, 'booking.no_show'), 'Nicht erschienen: Maximiliane, 01.01.2025, 18:00–19:00 Uhr'],
            // Texte über Mitglieder bleiben, auch wenn das Mitglied wie ein Gast heißt
            'memberBooking' => [$this->audit($oldDay, 'Max Gast gebucht: 01.01.2025, Stand 1, 18:00–19:00 Uhr', $memberBooking), 'Max Gast gebucht: 01.01.2025, Stand 1, 18:00–19:00 Uhr'],
            'memberText' => [$this->audit($oldDay, 'Mitglied angelegt: Max Gast (member)', null, 'user.created'), 'Mitglied angelegt: Max Gast (member)'],
            // nach dem Stichtag bleibt alles
            'young' => [$this->audit($youngDay, 'Gast Erika Besuch gebucht: 01.02.2025, Stand 1, 18:00–19:00 Uhr', $youngBooking), 'Gast Erika Besuch gebucht: 01.02.2025, Stand 1, 18:00–19:00 Uhr'],
        ];

        $this->artisan('verein:aufbewahrung')->assertSuccessful();

        foreach ([$oldSession['log'], $cancelled, $shortName] as $id) {
            $this->assertSame($this->anonymizedName($id), DB::table('log_entries')->where('id', $id)->value('guest_name'));
        }

        $this->assertSame('cancelled', DB::table('log_entries')->where('id', $cancelled)->value('status'));
        $this->assertSame('Fehleingabe', DB::table('log_entries')->where('id', $cancelled)->value('cancel_reason'));
        $this->assertSame($this->anonymizedName($oldSession['participant']), DB::table('session_participants')->where('id', $oldSession['participant'])->value('guest_name'));
        $this->assertSame($this->anonymizedName($oldBooking), DB::table('bookings')->where('id', $oldBooking)->value('guest_name'));

        $this->assertSame('Max Gast', DB::table('log_entries')->where('id', $youngSession['log'])->value('guest_name'));
        $this->assertSame('Max Gast', DB::table('session_participants')->where('id', $youngSession['participant'])->value('guest_name'));
        $this->assertSame('Erika Besuch', DB::table('bookings')->where('id', $youngBooking)->value('guest_name'));

        $member = DB::table('log_entries')->where('id', $memberSession['log'])->first();
        $this->assertSame($this->member->id, (int) $member->user_id);
        $this->assertNull($member->guest_name);
        $this->assertNull(DB::table('session_participants')->where('id', $memberSession['participant'])->value('guest_name'));
        $this->assertSame($this->member->id, (int) DB::table('bookings')->where('id', $memberBooking)->value('user_id'));

        foreach ($texts as $key => [$id, $expected]) {
            $this->assertSame($expected, DB::table('audit_entries')->where('id', $id)->value('summary'), "Protokolltext {$key}");
        }

        $run = DB::table('retention_runs')->sole();
        $this->assertSame(5, (int) $run->anonymized_guests);
        $this->assertSame(0, (int) $run->deleted_entries);

        $summary = DB::table('audit_entries')->where('action', 'retention.run')->value('summary');
        $this->assertStringContainsString('5 Zeilen, 4 Protokolltexte', $summary);

        foreach (['Erika', 'Max', 'Otto', 'Hans'] as $name) {
            $this->assertStringNotContainsString($name, $summary);
        }
    }

    public function test_without_an_open_run_the_triggers_still_refuse_direct_deletes_and_updates()
    {
        $old = $this->signedSession($this->yearsAgo(6), 'Gerd Alt');

        $attempts = function () use ($old) {
            $this->assertDbRefuses(fn () => DB::table('log_entries')->where('id', $old['log'])->delete(), 'Kladde-Zeile gelöscht.');
            $this->assertDbRefuses(fn () => DB::table('log_entries')->where('id', $old['log'])->update(['guest_name' => $this->anonymizedName($old['log'])]), 'Gast in der Kladde anonymisiert.');
            $this->assertDbRefuses(fn () => DB::table('session_entries')->where('id', $old['entry'])->delete(), 'Eintrag gelöscht.');
            $this->assertDbRefuses(fn () => DB::table('session_participants')->where('id', $old['participant'])->update(['guest_name' => $this->anonymizedName($old['participant'])]), 'Teilnahme anonymisiert.');
            $this->assertDbRefuses(fn () => DB::table('session_participants')->where('id', $old['participant'])->delete(), 'Teilnahme gelöscht.');
            $this->assertDbRefuses(fn () => DB::table('shooting_sessions')->where('id', $old['session'])->delete(), 'Session gelöscht.');
        };

        $attempts();

        // ein abgeschlossener Lauf öffnet nichts mehr
        $this->forgeRun("date('now', '-5 years')", "date('now', '-12 months')", finished: true);
        $attempts();

        $this->assertSame('Gerd Alt', DB::table('log_entries')->where('id', $old['log'])->value('guest_name'));
    }

    public function test_a_forged_run_with_a_too_young_cutoff_opens_nothing()
    {
        $this->forgeRun("date('now', '-2 years')", "date('now', '-20 days')");
        $veryOld = $this->signedSession($this->yearsAgo(8), 'Gerd Alt');
        $recent = $this->logRow($this->yearsAgo(2, 100));
        $guest = $this->guestRow($this->monthsAgo(6), 'Erika Besuch');
        $freshGuest = $this->guestRow(today()->subDays(25)->toDateString(), 'Otto Neu');

        $this->assertDbRefuses(fn () => DB::table('log_entries')->where('id', $veryOld['log'])->delete(), 'Stichtag unter 3 Jahren öffnete das Löschen.');
        $this->assertDbRefuses(fn () => DB::table('log_entries')->where('id', $recent)->delete(), 'Zeile von vor gut 2 Jahren gelöscht.');
        $this->assertDbRefuses(fn () => DB::table('session_entries')->where('id', $veryOld['entry'])->delete(), 'Eintrag gelöscht.');
        $this->assertDbRefuses(fn () => DB::table('shooting_sessions')->where('id', $veryOld['session'])->delete(), 'Session gelöscht.');

        foreach ([$veryOld['log'], $guest, $freshGuest] as $id) {
            $this->assertDbRefuses(fn () => DB::table('log_entries')->where('id', $id)->update(['guest_name' => $this->anonymizedName($id)]), 'Stichtag unter 1 Monat öffnete das Anonymisieren.');
        }

        $this->assertDbRefuses(fn () => DB::table('session_participants')->where('id', $veryOld['participant'])->update(['guest_name' => $this->anonymizedName($veryOld['participant'])]), 'Teilnahme anonymisiert.');
    }

    public function test_an_open_run_allows_only_the_exact_anonymization_of_guests()
    {
        $this->forgeRun("date('now', '-5 years')", "date('now', '-12 months')");
        $guest = $this->guestRow($this->monthsAgo(13), 'Erika Besuch', ['shots' => 10]);
        $member = $this->logRow($this->monthsAgo(13), ['weapon_class' => 'long']);
        $young = $this->guestRow($this->monthsAgo(11), 'Otto Neu');
        $session = $this->signedSession($this->monthsAgo(13));
        $deletable = $this->logRow($this->yearsAgo(6));
        $kept = $this->logRow($this->yearsAgo(4));

        foreach ([
            'NULL' => ['guest_name' => null],
            'anderer Text' => ['guest_name' => 'Gast (anonymisiert) Erika'],
            'fremde Nummer' => ['guest_name' => $this->anonymizedName($guest + 1)],
            'mit Schusszahl' => ['guest_name' => $this->anonymizedName($guest), 'shots' => 11],
            'mit Storno' => ['guest_name' => $this->anonymizedName($guest), 'status' => 'cancelled', 'cancel_reason' => 'x', 'cancelled_by_id' => $this->supervisor->id, 'cancelled_at' => now()],
            'mit updated_at' => ['guest_name' => $this->anonymizedName($guest), 'updated_at' => now()],
        ] as $case => $values) {
            $this->assertDbRefuses(fn () => DB::table('log_entries')->where('id', $guest)->update($values), "Anonymisieren {$case} wurde angenommen.");
        }

        $this->assertDbRefuses(fn () => DB::table('log_entries')->where('id', $member)->update(['user_id' => null, 'guest_name' => $this->anonymizedName($member)]), 'Mitglied wurde zum Gast.');
        $this->assertDbRefuses(fn () => DB::table('log_entries')->where('id', $member)->update(['guest_name' => $this->anonymizedName($member)]), 'Mitgliedszeile bekam einen Gastnamen.');
        $this->assertDbRefuses(fn () => DB::table('session_participants')->where('id', $session['participant'])->update(['guest_name' => $this->anonymizedName($session['participant'])]), 'Mitgliedsteilnahme bekam einen Gastnamen.');
        $this->assertDbRefuses(fn () => DB::table('log_entries')->where('id', $young)->update(['guest_name' => $this->anonymizedName($young)]), 'Gast nach dem Stichtag anonymisiert.');
        $this->assertDbRefuses(fn () => DB::table('log_entries')->where('id', $kept)->delete(), 'Zeile nach dem Stichtag gelöscht.');

        DB::table('log_entries')->where('id', $guest)->update(['guest_name' => $this->anonymizedName($guest)]);
        DB::table('log_entries')->where('id', $deletable)->delete();

        $this->assertSame($this->anonymizedName($guest), DB::table('log_entries')->where('id', $guest)->value('guest_name'));
        $this->assertFalse(DB::table('log_entries')->where('id', $deletable)->exists());

        // abschließen: danach ist die Ausnahme zu, auch für alte Zeilen; der Lauf bleibt stehen
        DB::table('retention_runs')->update(['finished_at' => now()]);
        $older = $this->logRow($this->yearsAgo(7));
        $this->assertDbRefuses(fn () => DB::table('log_entries')->where('id', $older)->delete(), 'Löschen nach dem Abschluss angenommen.');
        $this->assertDbRefuses(fn () => DB::table('retention_runs')->update(['finished_at' => null]), 'Lauf wieder geöffnet.');
        $this->assertDbRefuses(fn () => DB::table('retention_runs')->delete(), 'Lauf gelöscht.');
    }

    public function test_a_second_run_changes_nothing_and_leaves_no_run_behind()
    {
        $this->signedSession($this->yearsAgo(6), 'Gerd Alt');
        $this->signedSession($this->monthsAgo(13), 'Erika Besuch');
        $this->signedSession($this->monthsAgo(2), 'Otto Neu');

        $this->artisan('verein:aufbewahrung')->assertSuccessful();
        $before = $this->snapshot();

        $this->artisan('verein:aufbewahrung')->expectsOutputToContain('Nichts zu tun.')->assertSuccessful();

        $this->assertSame($before, $this->snapshot());
        $this->assertSame(1, DB::table('retention_runs')->count());
        $this->assertSame(1, DB::table('audit_entries')->where('action', 'retention.run')->count());
    }

    public function test_dry_run_changes_nothing_and_counts_what_a_run_would_do()
    {
        $this->signedSession($this->yearsAgo(6), 'Gerd Alt');
        $this->booking($this->yearsAgo(6), 'Gerd Alt');
        $this->signedSession($this->monthsAgo(13), 'Erika Besuch');
        $this->signedSession($this->monthsAgo(2), 'Otto Neu');
        $before = $this->snapshot();

        $this->artisan('verein:aufbewahrung --dry-run')->expectsOutputToContain('Probelauf, nichts geändert')->assertSuccessful();
        $dry = app(Retention::class)->run(true);

        $this->assertSame($before, $this->snapshot());
        $this->assertSame(0, DB::table('retention_runs')->count());
        $this->assertNull($dry['run_id']);

        $real = app(Retention::class)->run();
        $this->assertNotNull($real['run_id']);
        $this->assertSame(array_diff_key($dry, ['run_id' => 0]), array_diff_key($real, ['run_id' => 0]));
        $this->assertSame(1, $real['deleted_entries']);
        $this->assertSame(1, $real['deleted_bookings']);
        $this->assertSame(2, $real['anonymized_guests']);
    }

    public function test_an_unknown_open_run_stops_the_run()
    {
        $old = $this->logRow($this->yearsAgo(6));
        $this->forgeRun("date('now', '-5 years')", "date('now', '-12 months')");

        $this->artisan('verein:aufbewahrung')->expectsOutputToContain('offener Löschlauf')->assertFailed();

        $this->assertTrue(DB::table('log_entries')->where('id', $old)->exists());
        $this->assertSame(1, DB::table('retention_runs')->count());
    }

    public function test_settings_validate_the_periods_and_the_run_uses_them()
    {
        $admin = User::factory()->admin()->create();
        $base = ['name' => 'SV Musterdorf', 'slot_minutes' => 60, 'cancel_minutes_before' => 60, 'booking_horizon_days' => 90];

        $this->actingAs($admin)->get(route('admin.settings.edit'))
            ->assertInertia(fn ($page) => $page->where('settings.retention_record_years', 5)->where('settings.retention_guest_months', 12));

        foreach ([
            ['retention_record_years' => 2], ['retention_record_years' => 11], ['retention_record_years' => ''],
            ['retention_guest_months' => 0], ['retention_guest_months' => 61],
            ['retention_record_years' => 3, 'retention_guest_months' => 37],
        ] as $invalid) {
            $this->actingAs($admin)->put(route('admin.settings.update'), [...$base, ...$invalid])
                ->assertSessionHasErrors(array_key_last($invalid));
        }

        $this->assertSame(5, Retention::recordYears());

        $this->actingAs($admin)->put(route('admin.settings.update'), [...$base, 'retention_record_years' => 3, 'retention_guest_months' => 36])
            ->assertSessionHasNoErrors();

        config(['verein.retention_record_years' => 5, 'verein.retention_guest_months' => 12]);
        ClubSettings::apply();
        $this->assertSame(3, Retention::recordYears());
        $this->assertSame(36, Retention::guestMonths());
        $this->assertStringContainsString('Aufbewahrung Kladde 3 Jahre, Gastnamen 36 Monate', DB::table('audit_entries')->where('action', 'settings.updated')->latest('id')->value('summary'));

        $fourYears = $this->logRow($this->yearsAgo(4));
        $twoYears = $this->logRow($this->yearsAgo(2));
        $this->artisan('verein:aufbewahrung')->assertSuccessful();

        $this->assertFalse(DB::table('log_entries')->where('id', $fourYears)->exists());
        $this->assertTrue(DB::table('log_entries')->where('id', $twoYears)->exists());

        $this->get(route('privacy'))->assertInertia(fn ($page) => $page->where('recordYears', 3)->where('guestMonths', 36));
    }

    public function test_out_of_range_stored_periods_are_limited()
    {
        config(['verein.retention_record_years' => 1, 'verein.retention_guest_months' => 0]);
        $this->assertSame(3, Retention::recordYears());
        $this->assertSame(1, Retention::guestMonths());

        config(['verein.retention_record_years' => 50, 'verein.retention_guest_months' => 500]);
        $this->assertSame(10, Retention::recordYears());
        $this->assertSame(60, Retention::guestMonths());
    }

    public function test_the_run_is_scheduled_daily()
    {
        $this->artisan('schedule:list')->expectsOutputToContain('verein:aufbewahrung')->assertSuccessful();
    }

    public function test_admin_pages_still_render_after_a_run()
    {
        $this->signedSession($this->yearsAgo(6), 'Gerd Alt');
        $this->signedSession($this->monthsAgo(13), 'Erika Besuch');
        $this->artisan('verein:aufbewahrung')->assertSuccessful();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.record.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.audit.index'))->assertOk();
        $this->get(route('privacy'))->assertOk()->assertInertia(fn ($page) => $page->where('recordYears', 5)->where('guestMonths', 12));
    }

    public function test_the_migration_runs_again_and_rolls_back_to_the_old_triggers()
    {
        $migration = require database_path('migrations/2026_11_03_001600_create_retention_runs.php');
        $triggers = ['log_entries_no_delete', 'log_entries_only_cancel', 'shooting_sessions_no_delete', 'session_participants_frozen', 'session_participants_delete_open', 'session_entries_no_delete', 'retention_runs_no_delete', 'retention_runs_finish_once'];

        $migration->up();

        foreach ($triggers as $trigger) {
            $this->assertSame(1, DB::table('sqlite_master')->where('type', 'trigger')->where('name', $trigger)->count(), $trigger);
        }

        $old = $this->logRow($this->yearsAgo(6));
        $this->forgeRun("date('now', '-5 years')", "date('now', '-12 months')");

        $migration->down();
        $this->assertFalse(Schema::hasTable('retention_runs'));
        $this->assertSame(0, DB::table('sqlite_master')->where('type', 'trigger')->where('sql', 'like', '%retention_runs%')->count());
        $this->assertDbRefuses(fn () => DB::table('log_entries')->where('id', $old)->delete(), 'Nach down() wurde gelöscht.');
        DB::table('log_entries')->where('id', $old)->update(['status' => 'cancelled', 'cancel_reason' => 'Test', 'cancelled_by_id' => $this->supervisor->id, 'cancelled_at' => now()]);

        $migration->up();
        $this->assertTrue(Schema::hasTable('retention_runs'));
        $this->assertDbRefuses(fn () => DB::table('log_entries')->where('id', $old)->delete(), 'Ohne Lauf wurde gelöscht.');
    }
}
