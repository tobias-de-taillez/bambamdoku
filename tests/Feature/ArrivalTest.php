<?php

namespace Tests\Feature;

use App\Domain\Sessions;
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
        $supervisor = User::factory()->create(['supervisor_scope' => 'licensed', 'supervisor_until' => now()->addYear()]);
        Supervision::create(['shooting_day_id' => $this->day->id, 'user_id' => $supervisor->id, 'starts_at' => '17:00', 'ends_at' => '21:00']);
        $this->booking->forceFill(['arrived_at' => now()])->save();

        $this->actingAs($supervisor)->get(route('range-office.show'))
            ->assertInertia(fn ($page) => $page
                ->where('ranges.0.bookings.0.arrived', true)
                ->where('arrivalUrl', $this->url()));
    }

    public function test_state_follows_the_activation_not_the_booking()
    {
        $sessions = app(Sessions::class);
        $supervisor = User::factory()->supervisor()->create();
        $session = $sessions->open($supervisor);

        // über die Suche aktiviert: Buchung bleibt „gebucht“, die Seite meldet trotzdem aktiviert
        $sessions->addMember($supervisor, $session, $this->member, adultChecked: true);
        $this->actingAs($this->member)->get($this->url())
            ->assertInertia(fn ($page) => $page->where('state', 'confirmed')->where('booking.range', $this->booking->range->name));

        // abgezeichnet: kein „aktiviert“ mehr; die nie aktivierte Buchung meldet wieder „ist da“
        $sessions->signOff($supervisor, $session, []);
        $this->actingAs($this->member)->get($this->url())
            ->assertInertia(fn ($page) => $page->where('state', 'arrived'));
    }

    public function test_activated_without_booking_is_not_sent_to_the_supervisor()
    {
        $sessions = app(Sessions::class);
        $supervisor = User::factory()->supervisor()->create();
        $other = User::factory()->create();
        $sessions->addMember($supervisor, $sessions->open($supervisor), $other, adultChecked: true);

        $this->actingAs($other)->get($this->url())
            ->assertInertia(fn ($page) => $page->where('state', 'confirmed')->where('booking', null));
    }

    public function test_after_sign_off_a_later_booking_still_reports_arrival()
    {
        $sessions = app(Sessions::class);
        $supervisor = User::factory()->supervisor()->create();
        $session = $sessions->open($supervisor);
        $sessions->activate($supervisor, $session, $this->booking, adultChecked: true);
        $sessions->signOff($supervisor, $session, []);

        $this->actingAs($this->member)->get($this->url())
            ->assertInertia(fn ($page) => $page->where('state', 'signed'));

        $later = Booking::factory()->create(['shooting_day_id' => $this->day->id, 'range_id' => $this->day->ranges->first()->id, 'user_id' => $this->member->id, 'starts_at' => '19:00', 'ends_at' => '20:00']);
        $this->travelTo(now()->setTime(19, 5));

        $this->actingAs($this->member)->get($this->url())
            ->assertInertia(fn ($page) => $page->where('state', 'arrived')->where('booking.starts_at', '19:00'));
        $this->assertNotNull($later->fresh()->arrived_at);
    }
}
