<?php

namespace Tests\Feature;

use App\Domain\Bookings;
use App\Domain\Logbook;
use App\Domain\Sessions;
use App\Enums\BookingStatus;
use App\Enums\EntryVia;
use App\Enums\LogSource;
use App\Enums\SupervisionScope;
use App\Enums\WeaponClass;
use App\Enums\WeaponOwner;
use App\Http\Controllers\ShootingRecordController;
use App\Models\Booking;
use App\Models\LogEntry;
use App\Models\Qualification;
use App\Models\SessionEntry;
use App\Models\SessionParticipant;
use App\Models\ShootingDay;
use App\Models\Supervision;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Rechtliche Invarianten der Schießkladde (Wettbewerbsanalyse, Abschnitt 7.1), seit PR 4b mit
 * der Schieß-Session: Kladde-Zeilen entstehen beim Abzeichnen der Aufsicht.
 */
class LogbookTest extends TestCase
{
    use RefreshDatabase;

    private Logbook $logbook;

    private Sessions $sessions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(18, 0));
        $this->logbook = app(Logbook::class);
        $this->sessions = app(Sessions::class);
    }

    /**
     * Session plus Abzeichnen: die Aufsicht aktiviert, erfasst am Stand und zeichnet ab.
     *
     * @return array{entry: LogEntry, duplicate: bool}
     */
    private function enter(User $actor, ?User $member, WeaponClass $class = WeaponClass::Short, ?string $guest = null, bool $adult = false): array
    {
        $session = $this->sessions->open($actor);
        $participant = $member
            ? $this->sessions->addMember($actor, $session, $member, adultChecked: true)
            : $this->sessions->addGuest($actor, $session, (string) $guest, $adult);
        $staged = $this->sessions->stage($actor, $participant, EntryVia::Stand, $class, $member ? WeaponOwner::Own : null, $member ? 20 : null);
        $result = $this->sessions->signOff($actor, $session, $this->pending($session->id));
        $row = LogEntry::where('session_entry_id', $staged['entry']->id)->first() ?? LogEntry::active()->latest('id')->firstOrFail();

        return ['entry' => $row, 'duplicate' => $result['merged'] > 0];
    }

    private function pending(int $sessionId): array
    {
        return SessionEntry::query()->whereHas('participant', fn ($q) => $q->where('session_id', $sessionId))->where('status', 'pending')->pluck('id')->all();
    }

    private function assertRejected(callable $action, string $field): void
    {
        try {
            $action();
            $this->fail('Eintrag wurde nicht abgelehnt.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors(), json_encode($e->errors()));
        }
    }

    private function todaysDay(User $supervisor): ShootingDay
    {
        $day = ShootingDay::factory()->withRange()->create(['date' => today()->toDateString(), 'starts_at' => '17:00', 'ends_at' => '21:00']);
        Supervision::create(['shooting_day_id' => $day->id, 'user_id' => $supervisor->id, 'starts_at' => '17:00', 'ends_at' => '21:00']);

        return $day;
    }

    public function test_signing_supervisor_confirms_the_entry_for_the_session_day()
    {
        $supervisor = User::factory()->supervisor()->create();
        $member = User::factory()->create();

        $entry = $this->enter($supervisor, $member)['entry'];

        $this->assertTrue($entry->shot_on->isToday());
        $this->assertSame($supervisor->id, $entry->recorded_by_id);
        $this->assertSame(LogSource::Session, $entry->source);
        $this->assertSame(20, $entry->shots);
    }

    public function test_nobody_without_register_entry_can_start_a_session()
    {
        $this->assertRejected(fn () => $this->sessions->open(User::factory()->create()), 'session');
        $this->assertRejected(fn () => $this->sessions->open(User::factory()->admin()->create()), 'session');
        $this->assertSame(0, LogEntry::count());
    }

    public function test_expired_registration_cannot_start_a_session()
    {
        $supervisor = User::factory()->supervisor(until: now()->subDay()->toDateString())->create();

        $this->assertRejected(fn () => $this->sessions->open($supervisor), 'session');
    }

    public function test_exempt_scope_only_allows_exempt_weapons()
    {
        $supervisor = User::factory()->supervisor(SupervisionScope::Exempt)->create();
        $member = User::factory()->create();
        $session = $this->sessions->open($supervisor);
        $participant = $this->sessions->addMember($supervisor, $session, $member, adultChecked: true);

        $this->assertRejected(fn () => $this->sessions->stage($supervisor, $participant, EntryVia::Stand, WeaponClass::Long, WeaponOwner::Own, 20), 'weapon_class');
        $this->assertNotNull($this->sessions->stage($supervisor, $participant, EntryVia::Stand, WeaponClass::Exempt, WeaponOwner::Own, 20)['entry']);
    }

    public function test_nobody_enters_themselves()
    {
        $supervisor = User::factory()->supervisor()->create();
        $session = $this->sessions->open($supervisor);

        $this->assertRejected(fn () => $this->sessions->addMember($supervisor, $session, $supervisor, adultChecked: true), 'user_id');
    }

    public function test_double_tap_creates_exactly_one_entry()
    {
        $supervisor = User::factory()->supervisor()->create();
        $member = User::factory()->create();
        $session = $this->sessions->open($supervisor);
        $participant = $this->sessions->addMember($supervisor, $session, $member, adultChecked: true);

        $first = $this->sessions->stage($supervisor, $participant, EntryVia::Stand, WeaponClass::Short, WeaponOwner::Own, 20);
        $second = $this->sessions->stage($supervisor, $participant, EntryVia::Stand, WeaponClass::Short, WeaponOwner::Own, 20);
        $this->sessions->signOff($supervisor, $session, $this->pending($session->id));

        $this->assertFalse($first['duplicate']);
        $this->assertTrue($second['duplicate']);
        $this->assertSame(1, LogEntry::count());
    }

    public function test_database_refuses_a_second_active_entry_with_the_same_key()
    {
        $supervisor = User::factory()->supervisor()->create();
        $member = User::factory()->create();
        $attributes = ['user_id' => $member->id, 'shot_on' => today()->toDateString(), 'weapon_class' => 'short', 'weapon_owner' => 'own', 'recorded_by_id' => $supervisor->id, 'source' => 'stand'];
        LogEntry::create($attributes);

        $this->expectException(QueryException::class);
        LogEntry::create($attributes);
    }

    public function test_guests_need_a_name_and_adult_confirmation()
    {
        $supervisor = User::factory()->supervisor()->create();
        $session = $this->sessions->open($supervisor);

        $this->assertRejected(fn () => $this->sessions->addGuest($supervisor, $session, 'Gerda Gast', false), 'guest_adult');
        $this->assertRejected(fn () => $this->sessions->addGuest($supervisor, $session, '  ', true), 'guest_name');
        $entry = $this->enter($supervisor, null, guest: 'Gerda Gast', adult: true)['entry'];

        $this->assertNull($entry->user_id);
        $this->assertNull($entry->weapon_owner);
    }

    public function test_entries_cannot_be_changed_or_deleted()
    {
        $entry = $this->enter(User::factory()->supervisor()->create(), User::factory()->create())['entry'];

        try {
            $entry->update(['weapon_class' => WeaponClass::Long]);
            $this->fail('Änderung wurde nicht verhindert.');
        } catch (LogicException) {
        }

        $this->expectException(LogicException::class);
        $entry->delete();
    }

    public function test_supervisors_have_no_storno_right_only_admins_cancel_with_reason()
    {
        $supervisor = User::factory()->supervisor()->create();
        $member = User::factory()->create();
        $entry = $this->enter($supervisor, $member)['entry'];
        $admin = User::factory()->admin()->create();

        try {
            $this->logbook->cancel($supervisor, $entry, 'Fehleingabe');
            $this->fail('Storno durch die Aufsicht wurde nicht verhindert.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertRejected(fn () => $this->logbook->cancel($admin, $entry, ' '), 'reason');
        $this->logbook->cancel($admin, $entry, 'Fehleingabe');

        $this->assertSame('cancelled', $entry->fresh()->status);
        $this->assertFalse($this->enter($supervisor, $member)['duplicate']);
        $this->assertSame(1, LogEntry::active()->count());
    }

    public function test_admins_cancel_also_on_the_next_day()
    {
        $supervisor = User::factory()->supervisor()->create();
        $entry = $this->enter($supervisor, User::factory()->create())['entry'];
        $this->travel(1)->day();

        $this->logbook->cancel(User::factory()->admin()->create(), $entry, 'Korrektur Vorstand');
        $this->assertSame('cancelled', $entry->fresh()->status);
    }

    public function test_supervisors_cannot_cancel_entries_of_others()
    {
        $entry = $this->enter(User::factory()->supervisor()->create(), User::factory()->create())['entry'];

        $this->expectException(HttpException::class);
        $this->logbook->cancel(User::factory()->supervisor()->create(), $entry, 'fremd');
    }

    public function test_late_entries_only_by_admins_with_reason()
    {
        $admin = User::factory()->admin()->create();
        $members = User::factory()->count(2)->create()->all();

        $this->assertRejected(fn () => $this->logbook->lateEntries($admin, $members, today()->subDays(3)->toDateString(), WeaponClass::Short, WeaponOwner::Own, null, ''), 'reason');
        $this->assertRejected(fn () => $this->logbook->lateEntries($admin, $members, today()->addDay()->toDateString(), WeaponClass::Short, WeaponOwner::Own, null, 'Papierliste'), 'date');
        $this->assertRejected(fn () => $this->logbook->lateEntries($admin, [$admin], today()->toDateString(), WeaponClass::Short, WeaponOwner::Own, null, 'Papierliste'), 'members');

        $result = $this->logbook->lateEntries($admin, $members, today()->subDays(3)->toDateString(), WeaponClass::Short, WeaponOwner::Own, null, 'Papierliste vom Samstag, Aufsicht: H. S.');

        $this->assertSame(2, $result['created']);
        $this->assertTrue(LogEntry::get()->every(fn ($e) => $e->source === LogSource::LateEntry && $e->late_reason !== null));

        $this->expectException(HttpException::class);
        $this->logbook->lateEntries(User::factory()->supervisor()->create(), $members, today()->toDateString(), WeaponClass::Short, WeaponOwner::Own, null, 'Papierliste');
    }

    public function test_activation_writes_nothing_only_signing_off_does()
    {
        $supervisor = User::factory()->supervisor()->create();
        $day = $this->todaysDay($supervisor);
        $booking = Booking::factory()->create(['shooting_day_id' => $day->id, 'range_id' => $day->ranges->first()->id, 'user_id' => User::factory()->create()->id, 'club_weapon' => true]);
        $session = $this->sessions->open($supervisor);

        $participant = app(Bookings::class)->checkIn($supervisor, $booking->fresh(), adultChecked: true);

        $this->assertSame($booking->id, $participant->booking_id);
        $this->assertSame(BookingStatus::CheckedIn, $booking->fresh()->status);
        $this->assertSame(0, LogEntry::count());

        $this->sessions->stage($supervisor, $participant, EntryVia::Stand, WeaponClass::Short, WeaponOwner::Club, 25);
        $this->sessions->signOff($supervisor, $session, $this->pending($session->id));

        $entry = LogEntry::sole();
        $this->assertNull($entry->booking_id);
        $this->assertSame(WeaponOwner::Club, $entry->weapon_owner);
    }

    public function test_activation_needs_an_open_session()
    {
        $supervisor = User::factory()->supervisor()->create();
        $day = $this->todaysDay($supervisor);
        $booking = Booking::factory()->create(['shooting_day_id' => $day->id, 'range_id' => $day->ranges->first()->id, 'user_id' => User::factory()->create()->id]);

        $this->assertRejected(fn () => app(Bookings::class)->checkIn($supervisor, $booking->fresh()), 'booking');
        $this->assertSame(BookingStatus::Booked, $booking->fresh()->status);
    }

    public function test_supervisor_cannot_activate_their_own_booking()
    {
        $supervisor = User::factory()->supervisor()->create();
        $day = $this->todaysDay($supervisor);
        $own = Booking::factory()->create(['shooting_day_id' => $day->id, 'range_id' => $day->ranges->first()->id, 'user_id' => $supervisor->id]);
        $this->sessions->open($supervisor);

        $this->assertRejected(fn () => app(Bookings::class)->checkIn($supervisor, $own->fresh(), adultChecked: true), 'user_id');
        $this->assertSame(0, SessionParticipant::count());
    }

    public function test_stand_page_runs_a_session_over_http_and_storno_route_is_gone()
    {
        $supervisor = User::factory()->supervisor()->create();
        $member = User::factory()->create();

        $this->actingAs($supervisor)->post(route('range-office.session.start'))->assertSessionHasNoErrors();
        $this->actingAs($supervisor)->post(route('range-office.enter'), [
            'user_id' => $member->id,
            'adult_confirmed' => true,
            'weapon_class' => 'long',
            'weapon_owner' => 'club',
            'shots' => 30,
        ])->assertSessionHasNoErrors();

        $staged = SessionEntry::sole();
        $this->assertSame(0, LogEntry::count(), 'Erfassen schreibt noch nicht in die Kladde.');
        $this->actingAs($supervisor)->get(route('range-office.show'))
            ->assertInertia(fn ($page) => $page->where('canEnter', true)->where('day', null)
                ->where('session.participants.0.entries.0.id', $staged->id)
                ->where('session.participants.0.entries.0.via', 'Aufsicht')
                ->where('session.participants.0.entries.0.shots', 30));

        $session = $staged->participant->session;
        $this->actingAs($supervisor)->post(route('range-office.session.sign-off', $session), ['entry_ids' => [$staged->id]])->assertSessionHasNoErrors();

        $this->assertSame(30, LogEntry::sole()->shots);
        $this->assertFalse(Route::has('range-office.cancel-entry'));
    }

    public function test_members_see_only_their_own_entries_and_admins_everything()
    {
        $supervisor = User::factory()->supervisor()->create();
        $member = User::factory()->create();
        $this->enter($supervisor, $member);
        $this->enter($supervisor, User::factory()->create());
        $this->enter($supervisor, null, guest: 'Gast', adult: true);

        $this->actingAs($member)->get(route('record.index'))
            ->assertInertia(fn ($page) => $page->has('entries', 1)->where('totals.0.days', 1)->where('totals.0.own_days', 1));

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.record.index'))
            ->assertInertia(fn ($page) => $page->has('entries', 3));

        $this->actingAs($member)->get(route('admin.record.index'))->assertForbidden();
    }

    public function test_member_filter_comes_back_as_number_for_the_extract_button()
    {
        $member = User::factory()->create();

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.record.index', ['user' => (string) $member->id]))
            ->assertInertia(fn ($page) => $page->where('filters.user', $member->id));
    }

    public function test_extract_pdf_downloads_for_member_and_admin()
    {
        $member = User::factory()->create();
        $this->enter(User::factory()->supervisor()->create(), $member);

        $own = $this->actingAs($member)->get(route('record.pdf'));
        $own->assertOk();
        $this->assertStringStartsWith('%PDF', $own->getContent());

        $admin = $this->actingAs(User::factory()->admin()->create())->get(route('admin.record.member-pdf', $member));
        $admin->assertOk();
        $this->assertStringStartsWith('%PDF', $admin->getContent());
    }

    public function test_record_pdf_note_is_neutral_and_marks_rows_taken_over_from_bookings()
    {
        $supervisor = User::factory()->supervisor()->create(['name' => 'Hanna Aufsicht']);
        $member = User::factory()->create();
        $this->enter($supervisor, $member);
        // Backfill aus …_000900: Bestätiger = Mitglied selbst
        DB::table('log_entries')->insert(['user_id' => $member->id, 'shot_on' => today()->subMonth()->toDateString(), 'weapon_class' => 'long', 'weapon_owner' => 'own', 'recorded_by_id' => $member->id, 'source' => 'stand', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        $html = view('pdf.record', ShootingRecordController::pdfData($member, today()->subYear(), today(), $member))->render();

        $this->assertStringContainsString('Bestätigt von', $html);
        $this->assertStringNotContainsString('Eingetragen von', $html);
        $this->assertStringContainsString('Hanna Aufsicht', $html);
        $this->assertStringContainsString('aus Buchung übernommen, ohne Bestätigung durch eine Aufsicht', $html);
        $this->assertStringContainsString('seitdem durch Abzeichnen der vom Mitglied oder von der Aufsicht erfassten Einträge', $html);
        $this->assertStringContainsString('bis '.today()->format('d.m.Y').' durch Eintragen am Stand', $html);
        $this->assertStringContainsString('bescheinigen muss der Verband', $html);
    }

    public function test_record_pdf_note_omits_the_switchover_before_the_first_signed_session()
    {
        $member = User::factory()->create();

        $html = view('pdf.record', ShootingRecordController::pdfData($member, today()->subYear(), today(), $member))->render();

        $this->assertStringContainsString('im Aufsichtsregister geführten Aufsicht bestätigt (§ 10 Abs. 3 AWaffV).', $html);
        $this->assertStringNotContainsString('seitdem durch Abzeichnen', $html);
    }

    public function test_admin_late_entry_over_http()
    {
        $admin = User::factory()->admin()->create();
        $members = User::factory()->count(2)->create();

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => now()->unix()])->post(route('admin.record.late'), [
            'members' => $members->pluck('id')->all(),
            'date' => today()->subDay()->toDateString(),
            'weapon_class' => 'short',
            'weapon_owner' => 'own',
            'reason' => 'Papierliste vom Vortag, Aufsicht: H. S.',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, LogEntry::where('source', 'late_entry')->count());
    }

    public function test_membership_certificate_still_available()
    {
        $member = User::factory()->create(['member_since' => '2010-04-01']);

        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.record.certificate', $member));

        $response->assertOk();
    }

    public function test_membership_certificate_names_federation_and_issue_date()
    {
        config(['verein.name' => 'SV Musterdorf', 'verein.federation' => 'BSSB', 'verein.club_number' => 'V-1234']);
        $member = User::factory()->create(['member_since' => '2010-04-01']);

        $html = view('pdf.membership', [
            'user' => $member,
            'club' => 'SV Musterdorf',
            'clubAddress' => null,
            'federation' => 'BSSB',
            'clubNumber' => 'V-1234',
            'createdAt' => now()->toImmutable(),
            'createdBy' => 'Vorstand Test',
        ])->render();

        $this->assertStringContainsString('seit dem 01.04.2010', $html);
        $this->assertStringContainsString('am Ausstellungstag ('.now()->format('d.m.Y').') Mitglied ist', $html);
        $this->assertStringContainsString('BSSB', $html);
        $this->assertStringContainsString('V-1234', $html);
        $this->assertStringContainsString('von Vorstand Test', $html);
    }

    public function test_admin_downloads_the_paper_fallback_list()
    {
        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.record.paper-list'));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    // --- Befunde der zweiten Durchsicht ---

    public function test_activation_only_on_the_shooting_day_itself_even_for_admins()
    {
        $admin = User::factory()->admin()->supervisor()->create();
        $day = ShootingDay::factory()->withRange()->create(['date' => today()->subDays(5)->toDateString(), 'starts_at' => '17:00', 'ends_at' => '21:00']);
        $booking = Booking::factory()->create(['shooting_day_id' => $day->id, 'range_id' => $day->ranges->first()->id, 'user_id' => User::factory()->create()->id]);
        $this->sessions->open($admin);

        $this->assertRejected(fn () => app(Bookings::class)->checkIn($admin, $booking->fresh(), adultChecked: true), 'booking');
        $this->assertSame(0, SessionParticipant::count());
    }

    public function test_double_tap_on_activation_is_not_an_error()
    {
        $supervisor = User::factory()->supervisor()->create();
        $day = $this->todaysDay($supervisor);
        $booking = Booking::factory()->create(['shooting_day_id' => $day->id, 'range_id' => $day->ranges->first()->id, 'user_id' => User::factory()->create()->id]);
        $this->sessions->open($supervisor);

        app(Bookings::class)->checkIn($supervisor, $booking->fresh(), adultChecked: true);
        app(Bookings::class)->checkIn($supervisor, $booking->fresh());

        $this->assertSame(1, SessionParticipant::count());
        $this->assertSame(0, LogEntry::count());
    }

    public function test_admin_storno_leaves_the_activated_booking_alone()
    {
        $supervisor = User::factory()->supervisor()->create();
        $day = $this->todaysDay($supervisor);
        $member = User::factory()->create();
        $booking = Booking::factory()->create(['shooting_day_id' => $day->id, 'range_id' => $day->ranges->first()->id, 'user_id' => $member->id]);
        $session = $this->sessions->open($supervisor);
        $participant = app(Bookings::class)->checkIn($supervisor, $booking->fresh(), adultChecked: true);
        $this->sessions->stage($supervisor, $participant, EntryVia::Stand, WeaponClass::Short, WeaponOwner::Own, 20);
        $this->sessions->signOff($supervisor, $session, $this->pending($session->id));

        $this->logbook->cancel(User::factory()->admin()->create(), LogEntry::sole(), 'Fehleingabe');

        $this->assertSame(BookingStatus::CheckedIn, $booking->fresh()->status);
    }

    public function test_database_rejects_changes_and_deletes_outside_the_model()
    {
        $entry = $this->enter(User::factory()->supervisor()->create(), User::factory()->create())['entry'];

        try {
            LogEntry::query()->whereKey($entry->id)->update(['weapon_class' => 'long']);
            $this->fail('Massen-Update wurde nicht abgewiesen.');
        } catch (QueryException) {
        }

        try {
            LogEntry::query()->whereKey($entry->id)->delete();
            $this->fail('Massen-Löschen wurde nicht abgewiesen.');
        } catch (QueryException) {
        }

        $this->expectException(QueryException::class);
        Qualification::query()->delete();
    }

    public function test_a_second_storno_is_rejected_by_the_database()
    {
        $entry = $this->enter(User::factory()->supervisor()->create(), User::factory()->create())['entry'];
        LogEntry::query()->whereKey($entry->id)->update(['status' => 'cancelled', 'cancel_reason' => 'eins', 'cancelled_by_id' => User::factory()->admin()->create()->id, 'cancelled_at' => now()]);

        $this->expectException(QueryException::class);
        LogEntry::query()->whereKey($entry->id)->update(['cancel_reason' => 'zwei']);
    }

    public function test_shot_on_is_stored_as_plain_date()
    {
        $entry = $this->enter(User::factory()->supervisor()->create(), User::factory()->create())['entry'];

        $this->assertSame(today()->toDateString(), DB::table('log_entries')->where('id', $entry->id)->value('shot_on'));
    }

    public function test_stand_page_hides_member_list_without_registration()
    {
        $this->actingAs(User::factory()->create())->get(route('range-office.show'))
            ->assertInertia(fn ($page) => $page->where('canEnter', false)->where('members', [])->where('session', null));
    }

    public function test_nobody_registers_themselves_as_supervisor()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.users.register', $admin), [
            'scope' => 'licensed',
            'valid_until' => now()->addYear()->toDateString(),
            'checked_on' => now()->toDateString(),
            'confirmed' => true,
        ])->assertSessionHasErrors('scope');

        $this->assertFalse($admin->fresh()->canSupervise());
    }
}
