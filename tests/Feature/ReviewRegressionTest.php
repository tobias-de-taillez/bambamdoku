<?php

namespace Tests\Feature;

use App\Domain\Bookings;
use App\Domain\MemberImport;
use App\Domain\TimeWindow;
use App\Enums\BookingStatus;
use App\Enums\Role;
use App\Enums\WeaponType;
use App\Models\Booking;
use App\Models\Group;
use App\Models\Range;
use App\Models\ShootingDay;
use App\Models\Supervision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Regressionstests zu den Befunden der Code-Durchsicht vom 03.11.2026.
 */
class ReviewRegressionTest extends TestCase
{
    use RefreshDatabase;

    private Bookings $bookings;

    protected function setUp(): void
    {
        parent::setUp();
        config(['verein.slot_minutes' => 60]);
        $this->bookings = app(Bookings::class);
    }

    private function day(int $lanes = 1, string $date = '+2 days'): ShootingDay
    {
        return ShootingDay::factory()->withRange(Range::factory()->create(['lanes' => $lanes]))->create([
            'date' => now()->modify($date)->toDateString(),
            'starts_at' => '17:00',
            'ends_at' => '21:00',
        ]);
    }

    private function supervise(ShootingDay $day, ?User $supervisor = null): User
    {
        $supervisor ??= User::factory()->supervisor()->create();
        Supervision::create(['shooting_day_id' => $day->id, 'user_id' => $supervisor->id, 'starts_at' => '17:00', 'ends_at' => '21:00']);

        return $supervisor;
    }

    private function book(User $member, ShootingDay $day, string $from = '18:00', string $to = '19:00'): Booking
    {
        return $this->bookings->book($member, $day->fresh(), $day->ranges->first(), TimeWindow::fromStrings($from, $to), WeaponType::Pistol, shooter: $member);
    }

    public function test_deactivated_supervisors_no_longer_cover_bookings()
    {
        $day = $this->day();
        $supervisor = $this->supervise($day);
        $supervisor->forceFill(['active' => false])->save();

        $this->expectException(ValidationException::class);
        $this->book(User::factory()->create(), $day);
    }

    public function test_bringing_back_a_no_show_rechecks_the_lane()
    {
        $this->travelTo(now()->setTime(17, 30));
        $day = $this->day(lanes: 1, date: 'today');
        $supervisor = $this->supervise($day);
        $a = $this->book(User::factory()->create(), $day);
        $this->bookings->markNoShow($supervisor, $a->fresh());
        $this->book(User::factory()->create(), $day);

        try {
            $this->bookings->checkIn($supervisor, $a->fresh());
            $this->fail('Doppelt belegte Bahn wurde akzeptiert.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('booking', $e->errors());
        }

        $this->assertSame(BookingStatus::NoShow, $a->fresh()->status);
    }

    public function test_statistics_include_new_years_eve()
    {
        $this->travelTo(now()->setDate(2027, 1, 5));
        ShootingDay::factory()->withRange()->create(['date' => '2026-12-31']);
        ShootingDay::factory()->withRange()->create(['date' => '2026-06-30']);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.statistics', ['jahr' => 2026]))
            ->assertInertia(fn ($page) => $page->where('totals.days', 2));
    }

    public function test_overlapping_days_cannot_share_a_range()
    {
        $range = Range::factory()->create();
        $admin = User::factory()->admin()->create();
        $payload = ['date' => now()->addDays(3)->toDateString(), 'starts_at' => '17:00', 'ends_at' => '21:00', 'ranges' => [$range->id]];

        $this->actingAs($admin)->post(route('admin.shooting-days.store'), $payload)->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.shooting-days.store'), [...$payload, 'starts_at' => '20:00', 'ends_at' => '22:00'])
            ->assertSessionHasErrors('starts_at');

        $this->assertSame(1, ShootingDay::count());
    }

    public function test_same_person_cannot_book_two_days_at_the_same_time()
    {
        $first = $this->day(lanes: 2);
        $second = ShootingDay::factory()->withRange(Range::factory()->create())->create(['date' => $first->date->toDateString(), 'starts_at' => '17:00', 'ends_at' => '21:00']);
        $this->supervise($first);
        $this->supervise($second);
        $member = User::factory()->create();
        $this->book($member, $first);

        $this->expectException(ValidationException::class);
        $this->book($member, $second);
    }

    public function test_series_edit_is_not_blocked_by_bookings_on_other_days()
    {
        $admin = User::factory()->admin()->create();
        $range = Range::factory()->create();
        $start = now()->next('Monday');
        $this->actingAs($admin)->post(route('admin.shooting-days.store'), [
            'date' => $start->toDateString(),
            'repeat_until' => $start->copy()->addWeeks(2)->toDateString(),
            'starts_at' => '17:00',
            'ends_at' => '21:00',
            'ranges' => [$range->id],
        ]);
        [$first, $second] = ShootingDay::orderBy('date')->take(2)->get()->all();
        Booking::factory()->create(['shooting_day_id' => $second->id, 'range_id' => $range->id, 'user_id' => User::factory()->create()->id]);

        $this->actingAs($admin)->put(route('admin.shooting-days.update', $first), [
            'date' => $first->date->toDateString(),
            'starts_at' => '17:00',
            'ends_at' => '22:00',
            'ranges' => [$range->id],
            'apply_to_series' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame('22:00', substr($second->fresh()->ends_at, 0, 5));
    }

    public function test_guest_role_accounts_count_as_guests()
    {
        $day = $this->day(lanes: 2);
        $day->update(['guests_allowed' => false]);
        $this->supervise($day);
        $guest = User::factory()->create(['role' => Role::Guest]);

        $this->expectException(ValidationException::class);
        $this->book($guest, $day);
    }

    public function test_range_cannot_shrink_below_future_bookings()
    {
        $day = $this->day(lanes: 2);
        $this->supervise($day);
        $range = $day->ranges->first();
        $this->book(User::factory()->create(), $day);
        $this->book(User::factory()->create(), $day);

        $this->actingAs(User::factory()->admin()->create())->put(route('admin.ranges.update', $range), [
            'name' => $range->name,
            'lanes' => 1,
            'weapon_types' => ['kurzwaffe'],
        ])->assertSessionHasErrors('lanes');

        $this->assertSame(2, $range->fresh()->lanes);
    }

    public function test_past_slots_of_today_cannot_be_booked()
    {
        $this->travelTo(now()->setTime(19, 30));
        $day = $this->day(lanes: 2, date: 'today');
        $this->supervise($day);

        $this->expectException(ValidationException::class);
        $this->book(User::factory()->create(), $day, '17:00', '18:00');
    }

    public function test_supervisors_cannot_join_days_of_other_groups()
    {
        $day = $this->day();
        $day->update(['restricted_to_groups' => true]);
        $day->groups()->attach(Group::create(['name' => 'Jugend']));
        $qualified = User::factory()->supervisor()->create();

        $this->actingAs($qualified)->post(route('supervisions.store', $day), ['starts_at' => '17:00', 'ends_at' => '21:00'])->assertForbidden();
    }

    public function test_import_reports_unreadable_dates_and_old_two_digit_years()
    {
        User::factory()->create(['member_number' => '7', 'email' => 'alt@example.org']);
        $result = app(MemberImport::class)->parse("Name;E-Mail;Eintritt;Aufsicht bis;Mitgliedsnummer\n"
            ."Anna;anna@example.org;01.03.65;31.12.27;\n"
            ."Bert;bert@example.org;irgendwann;;\n"
            ."Carl;carl@example.org;;;7\n");

        $this->assertSame('1965-03-01', $result['rows'][0]['member_since']);
        $this->assertSame('2027-12-31', $result['rows'][0]['supervisor_until']);
        $this->assertCount(1, $result['rows']);
        $this->assertStringContainsString('nicht lesbar', $result['errors'][0]);
        $this->assertStringContainsString('anderen Mitglied', $result['errors'][1]);
    }
}
