<?php

namespace Tests\Feature;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\ShootingDay;
use App\Models\Supervision;
use App\Models\User;
use App\Support\ArrivalCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArrivalTest extends TestCase
{
    use RefreshDatabase;

    private ShootingDay $day;

    private User $member;

    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(17, 50));
        $this->day = ShootingDay::factory()->withRange()->create(['date' => today()->toDateString(), 'starts_at' => '17:00', 'ends_at' => '21:00']);
        $this->member = User::factory()->create();
        $this->booking = Booking::factory()->create(['shooting_day_id' => $this->day->id, 'range_id' => $this->day->ranges->first()->id, 'user_id' => $this->member->id, 'starts_at' => '18:00', 'ends_at' => '19:00']);
    }

    private function url(?string $code = null): string
    {
        return route('range-office.arrive', ['shootingDay' => $this->day->id, 'code' => $code ?? ArrivalCode::current($this->day)]);
    }

    public function test_member_reports_arrival_with_current_code()
    {
        $this->actingAs($this->member)->get($this->url())
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('range-office/Arrived')->where('state', 'arrived'));

        $this->assertNotNull($this->booking->fresh()->arrived_at);
        $this->assertSame(BookingStatus::Booked, $this->booking->fresh()->status);
    }

    public function test_previous_code_still_works_but_older_ones_expire()
    {
        $code = ArrivalCode::current($this->day);

        $this->travel(5)->minutes();
        $this->assertTrue(ArrivalCode::isValid($this->day, $code));

        $this->travel(6)->minutes();
        $this->actingAs($this->member)->get($this->url($code))
            ->assertInertia(fn ($page) => $page->where('state', 'expired'));
        $this->assertNull($this->booking->fresh()->arrived_at);
    }

    public function test_members_without_booking_are_sent_to_the_supervisor()
    {
        $this->actingAs(User::factory()->create())->get($this->url())
            ->assertInertia(fn ($page) => $page->where('state', 'no_booking'));
    }

    public function test_supervisor_sees_arrival_and_qr_link()
    {
        $supervisor = User::factory()->create(['supervisor_until' => now()->addYear()]);
        Supervision::create(['shooting_day_id' => $this->day->id, 'user_id' => $supervisor->id, 'starts_at' => '17:00', 'ends_at' => '21:00']);
        $this->booking->forceFill(['arrived_at' => now()])->save();

        $this->actingAs($supervisor)->get(route('range-office.show'))
            ->assertInertia(fn ($page) => $page
                ->where('ranges.0.bookings.0.arrived', true)
                ->where('arrivalUrl', $this->url()));
    }
}
