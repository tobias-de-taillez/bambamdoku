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

    public function test_supervisor_checks_in_and_reset_is_refused_for_confirmed_visits()
    {
        $booking = $this->booking();

        $this->actingAs($this->supervisor)->post(route('range-office.check-in', $booking))->assertRedirect();
        $booking->refresh();
        $this->assertSame(BookingStatus::CheckedIn, $booking->status);
        $this->assertSame($this->supervisor->id, $booking->checked_in_by_id);

        // Bestätigte Besuche werden nicht zurückgesetzt, sondern in der Kladde storniert.
        $this->actingAs($this->supervisor)->post(route('range-office.reset', $booking))->assertSessionHasErrors('booking');
        $this->assertSame(BookingStatus::CheckedIn, $booking->fresh()->status);
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

    public function test_navigation_flag_for_todays_supervisors()
    {
        $this->actingAs($this->supervisor)->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('auth.supervisesToday', true));
    }
}
