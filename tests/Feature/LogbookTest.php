<?php

namespace Tests\Feature;

use App\Domain\Bookings;
use App\Domain\Logbook;
use App\Enums\BookingStatus;
use App\Enums\LogSource;
use App\Enums\SupervisionScope;
use App\Enums\WeaponClass;
use App\Enums\WeaponOwner;
use App\Models\Booking;
use App\Models\LogEntry;
use App\Models\Qualification;
use App\Models\ShootingDay;
use App\Models\Supervision;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Rechtliche Invarianten der Schießkladde (Wettbewerbsanalyse, Abschnitt 7.1).
 */
class LogbookTest extends TestCase
{
    use RefreshDatabase;

    private Logbook $logbook;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(18, 0));
        $this->logbook = app(Logbook::class);
    }

    private function enter(User $actor, ?User $member, WeaponClass $class = WeaponClass::Short, ?string $guest = null, bool $adult = false): array
    {
        return $this->logbook->record($actor, $member, $guest, $class, $member ? WeaponOwner::Own : null, guestIsAdult: $adult);
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

    public function test_registered_supervisor_enters_a_member_for_today()
    {
        $supervisor = User::factory()->supervisor()->create();
        $member = User::factory()->create();

        $entry = $this->enter($supervisor, $member)['entry'];

        $this->assertTrue($entry->shot_on->isToday());
        $this->assertSame($supervisor->id, $entry->recorded_by_id);
        $this->assertSame(LogSource::Stand, $entry->source);
    }

    public function test_nobody_without_register_entry_can_enter()
    {
        $this->assertRejected(fn () => $this->enter(User::factory()->create(), User::factory()->create()), 'entry');
        $this->assertRejected(fn () => $this->enter(User::factory()->admin()->create(), User::factory()->create()), 'entry');
        $this->assertSame(0, LogEntry::count());
    }

    public function test_expired_registration_cannot_enter()
    {
        $supervisor = User::factory()->supervisor(until: now()->subDay()->toDateString())->create();

        $this->assertRejected(fn () => $this->enter($supervisor, User::factory()->create()), 'entry');
    }

    public function test_exempt_scope_only_allows_exempt_weapons()
    {
        $supervisor = User::factory()->supervisor(SupervisionScope::Exempt)->create();
        $member = User::factory()->create();

        $this->assertRejected(fn () => $this->enter($supervisor, $member, WeaponClass::Long), 'weapon_class');
        $this->assertNotNull($this->enter($supervisor, $member, WeaponClass::Exempt)['entry']);
    }

    public function test_nobody_enters_themselves()
    {
        $supervisor = User::factory()->supervisor()->create();

        $this->assertRejected(fn () => $this->enter($supervisor, $supervisor), 'user_id');
    }

    public function test_double_tap_creates_exactly_one_entry()
    {
        $supervisor = User::factory()->supervisor()->create();
        $member = User::factory()->create();

        $first = $this->enter($supervisor, $member);
        $second = $this->enter($supervisor, $member);

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

        $this->assertRejected(fn () => $this->enter($supervisor, null, guest: 'Gerda Gast'), 'guest_adult');
        $this->assertRejected(fn () => $this->enter($supervisor, null, guest: '  ', adult: true), 'guest_name');
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

    public function test_supervisor_cancels_own_entry_today_with_reason_and_can_reenter()
    {
        $supervisor = User::factory()->supervisor()->create();
        $member = User::factory()->create();
        $entry = $this->enter($supervisor, $member)['entry'];

        $this->assertRejected(fn () => $this->logbook->cancel($supervisor, $entry, ' '), 'reason');
        $this->logbook->cancel($supervisor, $entry, 'Fehleingabe');

        $this->assertSame('cancelled', $entry->fresh()->status);
        $this->assertFalse($this->enter($supervisor, $member)['duplicate']);
        $this->assertSame(1, LogEntry::active()->count());
    }

    public function test_from_the_next_day_only_admins_cancel()
    {
        $supervisor = User::factory()->supervisor()->create();
        $entry = $this->enter($supervisor, User::factory()->create())['entry'];
        $this->travel(1)->day();

        try {
            $this->logbook->cancel($supervisor, $entry, 'zu spät');
            $this->fail('Storno am Folgetag wurde nicht verhindert.');
        } catch (HttpException) {
        }

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

    public function test_check_in_creates_an_entry_and_storno_reopens_the_booking()
    {
        $day = ShootingDay::factory()->withRange()->create(['date' => today()->toDateString(), 'starts_at' => '17:00', 'ends_at' => '21:00']);
        $supervisor = User::factory()->supervisor()->create();
        Supervision::create(['shooting_day_id' => $day->id, 'user_id' => $supervisor->id, 'starts_at' => '17:00', 'ends_at' => '21:00']);
        $booking = Booking::factory()->create(['shooting_day_id' => $day->id, 'range_id' => $day->ranges->first()->id, 'user_id' => User::factory()->create()->id, 'club_weapon' => true]);

        app(Bookings::class)->checkIn($supervisor, $booking->fresh());

        $entry = LogEntry::sole();
        $this->assertSame($booking->id, $entry->booking_id);
        $this->assertSame(WeaponOwner::Club, $entry->weapon_owner);
        $this->assertSame(BookingStatus::CheckedIn, $booking->fresh()->status);

        $this->logbook->cancel($supervisor, $entry, 'Fehleingabe');
        $this->assertSame(BookingStatus::Booked, $booking->fresh()->status);
    }

    public function test_supervisor_cannot_check_in_their_own_booking()
    {
        $day = ShootingDay::factory()->withRange()->create(['date' => today()->toDateString(), 'starts_at' => '17:00', 'ends_at' => '21:00']);
        $supervisor = User::factory()->supervisor()->create();
        Supervision::create(['shooting_day_id' => $day->id, 'user_id' => $supervisor->id, 'starts_at' => '17:00', 'ends_at' => '21:00']);
        $own = Booking::factory()->create(['shooting_day_id' => $day->id, 'range_id' => $day->ranges->first()->id, 'user_id' => $supervisor->id]);

        $this->assertRejected(fn () => app(Bookings::class)->checkIn($supervisor, $own->fresh()), 'user_id');
        $this->assertSame(0, LogEntry::count());
    }

    public function test_stand_page_enters_and_cancels_over_http()
    {
        $supervisor = User::factory()->supervisor()->create();
        $member = User::factory()->create();

        $this->actingAs($supervisor)->post(route('range-office.enter'), [
            'user_id' => $member->id,
            'weapon_class' => 'long',
            'weapon_owner' => 'club',
        ])->assertSessionHasNoErrors();

        $entry = LogEntry::sole();
        $this->actingAs($supervisor)->get(route('range-office.show'))
            ->assertInertia(fn ($page) => $page->where('canEnter', true)->where('todayEntries.0.id', $entry->id)->where('day', null));

        $this->actingAs($supervisor)->post(route('range-office.cancel-entry', $entry), ['reason' => 'Fehleingabe'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $entry->fresh()->status);
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

    public function test_admin_late_entry_over_http()
    {
        $admin = User::factory()->admin()->create();
        $members = User::factory()->count(2)->create();

        $this->actingAs($admin)->post(route('admin.record.late'), [
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

    public function test_admin_downloads_the_paper_fallback_list()
    {
        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.record.paper-list'));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    // --- Befunde der zweiten Durchsicht ---

    public function test_check_in_only_on_the_shooting_day_itself_even_for_admins()
    {
        $admin = User::factory()->admin()->supervisor()->create();
        $day = ShootingDay::factory()->withRange()->create(['date' => today()->subDays(5)->toDateString(), 'starts_at' => '17:00', 'ends_at' => '21:00']);
        $booking = Booking::factory()->create(['shooting_day_id' => $day->id, 'range_id' => $day->ranges->first()->id, 'user_id' => User::factory()->create()->id]);

        $this->assertRejected(fn () => app(Bookings::class)->checkIn($admin, $booking->fresh()), 'booking');
        $this->assertSame(0, LogEntry::count());
    }

    public function test_double_tap_on_check_in_is_not_an_error()
    {
        $day = ShootingDay::factory()->withRange()->create(['date' => today()->toDateString(), 'starts_at' => '17:00', 'ends_at' => '21:00']);
        $supervisor = User::factory()->supervisor()->create();
        Supervision::create(['shooting_day_id' => $day->id, 'user_id' => $supervisor->id, 'starts_at' => '17:00', 'ends_at' => '21:00']);
        $booking = Booking::factory()->create(['shooting_day_id' => $day->id, 'range_id' => $day->ranges->first()->id, 'user_id' => User::factory()->create()->id]);

        app(Bookings::class)->checkIn($supervisor, $booking->fresh());
        app(Bookings::class)->checkIn($supervisor, $booking->fresh());

        $this->assertSame(1, LogEntry::count());
    }

    public function test_storno_of_a_manual_entry_reopens_the_matching_booking()
    {
        $day = ShootingDay::factory()->withRange()->create(['date' => today()->toDateString(), 'starts_at' => '17:00', 'ends_at' => '21:00']);
        $supervisor = User::factory()->supervisor()->create();
        Supervision::create(['shooting_day_id' => $day->id, 'user_id' => $supervisor->id, 'starts_at' => '17:00', 'ends_at' => '21:00']);
        $member = User::factory()->create();
        $booking = Booking::factory()->create(['shooting_day_id' => $day->id, 'range_id' => $day->ranges->first()->id, 'user_id' => $member->id]);

        $manual = $this->enter($supervisor, $member)['entry'];
        app(Bookings::class)->checkIn($supervisor, $booking->fresh());
        $this->assertSame(1, LogEntry::count());

        $this->logbook->cancel($supervisor, $manual, 'Fehleingabe');

        $this->assertSame(BookingStatus::Booked, $booking->fresh()->status);
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
        LogEntry::query()->whereKey($entry->id)->update(['status' => 'cancelled', 'cancel_reason' => 'eins']);

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
            ->assertInertia(fn ($page) => $page->where('canEnter', false)->where('members', []));
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
