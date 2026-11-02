<?php

namespace Tests\Feature;

use App\Domain\Bookings;
use App\Domain\TimeWindow;
use App\Enums\BookingStatus;
use App\Enums\WeaponType;
use App\Models\Range;
use App\Models\ShootingDay;
use App\Models\Supervision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class BookingRulesTest extends TestCase
{
    use RefreshDatabase;

    private Bookings $bookings;

    private ShootingDay $day;

    private Range $range;

    private User $supervisor;

    protected function setUp(): void
    {
        parent::setUp();

        config(['verein.slot_minutes' => 60, 'verein.cancel_minutes_before' => 60]);
        $this->bookings = app(Bookings::class);
        $this->range = Range::factory()->create(['lanes' => 2, 'weapon_types' => [WeaponType::Pistol]]);
        $this->day = ShootingDay::factory()->withRange($this->range)->create([
            'date' => now()->addDays(2)->toDateString(),
            'starts_at' => '17:00',
            'ends_at' => '21:00',
        ]);
        $this->supervisor = User::factory()->create(['supervisor_scope' => 'licensed', 'supervisor_until' => now()->addYear()]);
    }

    private function supervise(string $from = '17:00', string $to = '21:00', ?Range $range = null): Supervision
    {
        return $this->bookings->assignSupervision($this->supervisor, $this->day, $this->supervisor, $range, TimeWindow::fromStrings($from, $to));
    }

    private function book(User $user, string $from = '18:00', string $to = '19:00', WeaponType $type = WeaponType::Pistol)
    {
        return $this->bookings->book($user, $this->day->fresh(), $this->range, TimeWindow::fromStrings($from, $to), $type, shooter: $user);
    }

    private function assertRejected(callable $action, string $field): void
    {
        try {
            $action();
            $this->fail('Es wurde keine ValidationException geworfen.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors(), json_encode($e->errors()));
        }
    }

    public function test_booking_without_supervision_is_rejected()
    {
        $this->assertRejected(fn () => $this->book(User::factory()->create()), 'starts_at');
    }

    public function test_booking_with_supervision_succeeds()
    {
        $this->supervise();

        $booking = $this->book(User::factory()->create());

        $this->assertSame(BookingStatus::Booked, $booking->status);
    }

    public function test_partial_supervision_is_not_enough()
    {
        $this->supervise('17:00', '18:30');

        $this->assertRejected(fn () => $this->book(User::factory()->create(), '18:00', '20:00'), 'starts_at');
    }

    public function test_range_specific_supervision_only_covers_that_range()
    {
        $other = Range::factory()->create();
        $this->day->ranges()->attach($other);
        $this->supervise(range: $other);

        $this->assertRejected(fn () => $this->book(User::factory()->create()), 'starts_at');
    }

    public function test_capacity_is_enforced()
    {
        $this->supervise();
        $this->book(User::factory()->create());
        $this->book(User::factory()->create(), '17:00', '19:00');

        $this->assertRejected(fn () => $this->book(User::factory()->create(), '18:00', '20:00'), 'starts_at');
        $this->assertNotNull($this->book(User::factory()->create(), '19:00', '20:00'));
    }

    public function test_cancelled_bookings_free_the_lane()
    {
        $this->supervise();
        $first = $this->book($a = User::factory()->create());
        $this->book(User::factory()->create());

        $this->bookings->cancel($a, $first);

        $this->assertNotNull($this->book(User::factory()->create()));
    }

    public function test_weapon_type_must_be_allowed_on_the_range()
    {
        $this->supervise();

        $this->assertRejected(fn () => $this->book(User::factory()->create(), type: WeaponType::Rifle), 'weapon_type');
    }

    public function test_times_must_fit_the_grid_and_the_day()
    {
        $this->supervise();
        $member = User::factory()->create();

        $this->assertRejected(fn () => $this->book($member, '18:30', '19:30'), 'starts_at');
        $this->assertRejected(fn () => $this->book($member, '20:00', '22:00'), 'starts_at');
    }

    public function test_member_cannot_double_book_the_same_time()
    {
        $this->supervise();
        $member = User::factory()->create();
        $this->book($member);

        $this->assertRejected(fn () => $this->book($member, '18:00', '20:00'), 'starts_at');
    }

    public function test_cancelled_days_cannot_be_booked()
    {
        $this->supervise();
        $this->day->forceFill(['cancelled_at' => now()])->save();

        $this->assertRejected(fn () => $this->book(User::factory()->create()), 'shooting_day');
    }

    public function test_guests_need_permission_for_the_day()
    {
        $this->supervise();
        $this->day->update(['guests_allowed' => false]);
        $host = User::factory()->create();

        $this->assertRejected(fn () => $this->bookings->book($host, $this->day->fresh(), $this->range, TimeWindow::fromStrings('18:00', '19:00'), WeaponType::Pistol, guestName: 'Gast Gustav'), 'guest_name');
    }

    public function test_guest_booking_records_the_host()
    {
        $this->supervise();
        $host = User::factory()->create();

        $booking = $this->bookings->book($host, $this->day->fresh(), $this->range, TimeWindow::fromStrings('18:00', '19:00'), WeaponType::Pistol, guestName: 'Gast Gustav');

        $this->assertTrue($booking->isGuest());
        $this->assertSame($host->id, $booking->host_id);
        $this->assertSame('Gast Gustav', $booking->shooterName());
    }

    public function test_only_qualified_members_can_supervise()
    {
        $member = User::factory()->create();

        $this->assertRejected(fn () => $this->bookings->assignSupervision($member, $this->day, $member, null, TimeWindow::fromStrings('17:00', '21:00')), 'user_id');
    }

    public function test_qualification_must_be_valid_on_the_day()
    {
        $this->supervisor->forceFill(['supervisor_scope' => 'licensed', 'supervisor_until' => now()->addDay()])->save();

        $this->assertRejected(fn () => $this->supervise(), 'user_id');
    }

    public function test_supervision_cannot_be_removed_while_bookings_depend_on_it()
    {
        $supervision = $this->supervise();
        $this->book(User::factory()->create());

        $this->assertRejected(fn () => $this->bookings->removeSupervision($this->supervisor, $supervision), 'supervision');
    }

    public function test_supervision_can_be_removed_when_someone_else_covers()
    {
        $supervision = $this->supervise();
        $this->book(User::factory()->create());
        $deputy = User::factory()->create(['supervisor_scope' => 'licensed', 'supervisor_until' => now()->addYear()]);
        $this->bookings->assignSupervision($deputy, $this->day, $deputy, null, TimeWindow::fromStrings('17:00', '21:00'));

        $this->bookings->removeSupervision($this->supervisor, $supervision);

        $this->assertModelMissing($supervision);
    }

    public function test_members_cannot_cancel_shortly_before_start()
    {
        $this->supervise();
        $member = User::factory()->create();
        $booking = $this->book($member);
        $this->travelTo($this->day->date->copy()->setTime(17, 30));

        $this->assertRejected(fn () => $this->bookings->cancel($member, $booking->fresh()), 'booking');
        $this->bookings->cancel($this->supervisor, $booking->fresh());
        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
    }

    public function test_members_only_see_restricted_days_of_their_groups()
    {
        $this->supervise();
        $this->day->update(['restricted_to_groups' => true]);

        $this->expectException(HttpException::class);
        $this->book(User::factory()->create());
    }
}
