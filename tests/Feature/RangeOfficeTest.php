<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Enums\WeaponType;
use App\Models\Booking;
use App\Models\Range;
use App\Models\ShootingDay;
use App\Models\Supervision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RangeOfficeTest extends TestCase
{
    use RefreshDatabase;

    private Range $range;

    private ShootingDay $day;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();
        config(['verein.slot_minutes' => 60]);
        $this->travelTo(now()->setTime(18, 15));
        $this->range = Range::factory()->create(['lanes' => 4, 'weapon_types' => [WeaponType::Pistol]]);
        $this->day = ShootingDay::factory()->withRange($this->range)->create([
            'date' => today()->toDateString(),
            'starts_at' => '17:00',
            'ends_at' => '21:00',
        ]);
        $this->supervisor = User::factory()->create(['supervisor_scope' => 'licensed', 'supervisor_until' => now()->addYear()]);
        Supervision::create(['shooting_day_id' => $this->day->id, 'user_id' => $this->supervisor->id, 'starts_at' => '17:00', 'ends_at' => '21:00']);
    }

    private function booking(array $attributes = []): Booking
    {
        return Booking::factory()->create([
            'shooting_day_id' => $this->day->id,
            'range_id' => $this->range->id,
            'user_id' => User::factory()->create()->id,
            ...$attributes,
        ]);
    }

    public function test_supervisor_sees_todays_range_office()
    {
        $this->booking();

        $this->actingAs($this->supervisor)->get(route('range-office.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('range-office/Show')
                ->where('day.id', $this->day->id)
                ->has('ranges.0.bookings', 1)
                ->where('ranges.0.bookings.0.now', true)
                ->where('ranges.0.free_now', 3));
    }

    public function test_members_without_duty_see_an_empty_office()
    {
        $this->actingAs(User::factory()->create())->get(route('range-office.show'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('day', null));
    }

    public function test_supervisor_checks_in_and_can_undo()
    {
        $booking = $this->booking();

        $this->actingAs($this->supervisor)->post(route('range-office.check-in', $booking))->assertRedirect();
        $booking->refresh();
        $this->assertSame(BookingStatus::CheckedIn, $booking->status);
        $this->assertSame($this->supervisor->id, $booking->checked_in_by_id);

        $this->actingAs($this->supervisor)->post(route('range-office.reset', $booking));
        $this->assertSame(BookingStatus::Booked, $booking->fresh()->status);
    }

    public function test_supervisor_marks_no_show()
    {
        $booking = $this->booking();

        $this->actingAs($this->supervisor)->post(route('range-office.no-show', $booking));

        $this->assertSame(BookingStatus::NoShow, $booking->fresh()->status);
    }

    public function test_other_members_cannot_check_in()
    {
        $booking = $this->booking();

        $this->actingAs(User::factory()->create())->post(route('range-office.check-in', $booking))->assertForbidden();
        $this->assertSame(BookingStatus::Booked, $booking->fresh()->status);
    }

    public function test_supervisor_cannot_check_in_on_another_day()
    {
        $this->travelTo(now()->subDay());
        $booking = $this->booking();

        $this->actingAs($this->supervisor)->post(route('range-office.check-in', $booking))->assertForbidden();
    }

    public function test_walk_in_member_is_booked_and_checked_in()
    {
        $member = User::factory()->create();

        $this->actingAs($this->supervisor)->post(route('range-office.walk-in', $this->day), [
            'range_id' => $this->range->id,
            'user_id' => $member->id,
            'starts_at' => '18:00',
            'ends_at' => '20:00',
            'weapon_type' => 'kurzwaffe',
            'club_weapon' => true,
            'check_in' => true,
        ])->assertSessionHasNoErrors();

        $booking = Booking::sole();
        $this->assertSame($member->id, $booking->user_id);
        $this->assertSame(BookingStatus::CheckedIn, $booking->status);
        $this->assertSame($this->supervisor->id, $booking->booked_by_id);
    }

    public function test_walk_in_guest_with_host()
    {
        $host = User::factory()->create();

        $this->actingAs($this->supervisor)->post(route('range-office.walk-in', $this->day), [
            'range_id' => $this->range->id,
            'guest_name' => 'Gustav Gast',
            'host_id' => $host->id,
            'starts_at' => '18:00',
            'ends_at' => '19:00',
            'weapon_type' => 'kurzwaffe',
        ])->assertSessionHasNoErrors();

        $booking = Booking::sole();
        $this->assertSame('Gustav Gast', $booking->guest_name);
        $this->assertSame($host->id, $booking->host_id);
    }

    public function test_walk_in_needs_member_or_guest()
    {
        $this->actingAs($this->supervisor)->post(route('range-office.walk-in', $this->day), [
            'range_id' => $this->range->id,
            'starts_at' => '18:00',
            'ends_at' => '19:00',
            'weapon_type' => 'kurzwaffe',
        ])->assertSessionHasErrors(['user_id']);
    }

    public function test_members_cannot_use_walk_in()
    {
        $this->actingAs(User::factory()->create())->post(route('range-office.walk-in', $this->day), [
            'range_id' => $this->range->id,
            'guest_name' => 'X',
            'starts_at' => '18:00',
            'ends_at' => '19:00',
            'weapon_type' => 'kurzwaffe',
        ])->assertForbidden();
    }

    public function test_walk_in_respects_capacity()
    {
        foreach (range(1, 4) as $i) {
            $this->booking(['starts_at' => '18:00', 'ends_at' => '19:00']);
        }

        $this->actingAs($this->supervisor)->post(route('range-office.walk-in', $this->day), [
            'range_id' => $this->range->id,
            'guest_name' => 'Zu viel',
            'starts_at' => '18:00',
            'ends_at' => '19:00',
            'weapon_type' => 'kurzwaffe',
        ])->assertSessionHasErrors('starts_at');
    }

    public function test_navigation_flag_for_todays_supervisors()
    {
        $this->actingAs($this->supervisor)->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('auth.supervisesToday', true));
    }
}
