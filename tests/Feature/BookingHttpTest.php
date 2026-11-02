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

class BookingHttpTest extends TestCase
{
    use RefreshDatabase;

    private Range $range;

    private ShootingDay $day;

    protected function setUp(): void
    {
        parent::setUp();
        config(['verein.slot_minutes' => 60]);
        $this->range = Range::factory()->create(['lanes' => 3, 'weapon_types' => [WeaponType::Pistol, WeaponType::Rifle]]);
        $this->day = ShootingDay::factory()->withRange($this->range)->create([
            'date' => now()->addDays(2)->toDateString(),
            'starts_at' => '17:00',
            'ends_at' => '21:00',
        ]);
    }

    private function supervisor(): User
    {
        return User::factory()->create(['supervisor_scope' => 'licensed', 'supervisor_until' => now()->addYear()]);
    }

    public function test_day_list_and_detail_render()
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get(route('shooting-days.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('shooting-days/Index')->has('days', 1)->where('days.0.supervision', 'none'));

        $this->actingAs($member)->get(route('shooting-days.show', $this->day))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('shooting-days/Show')
                ->has('grid.0.slots', 4)
                ->where('grid.0.slots.0.supervised', false)
                ->where('canSupervise', false));
    }

    public function test_qualified_member_takes_over_supervision_and_others_can_book()
    {
        $supervisor = $this->supervisor();
        $member = User::factory()->create();

        $this->actingAs($supervisor)->post(route('supervisions.store', $this->day), [
            'starts_at' => '17:00',
            'ends_at' => '21:00',
        ])->assertSessionHasNoErrors();

        $this->actingAs($member)->post(route('bookings.store', $this->day), [
            'range_id' => $this->range->id,
            'starts_at' => '18:00',
            'ends_at' => '20:00',
            'weapon_type' => 'kurzwaffe',
            'club_weapon' => true,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $booking = Booking::sole();
        $this->assertSame($member->id, $booking->user_id);
        $this->assertTrue($booking->club_weapon);

        $this->actingAs($member)->get(route('shooting-days.show', $this->day))
            ->assertInertia(fn ($page) => $page
                ->has('myBookings', 1)
                ->where('grid.0.slots.1.free', 2)
                ->where('grid.0.slots.0.free', 3));
    }

    public function test_booking_without_supervision_shows_an_error()
    {
        $this->actingAs(User::factory()->create())->post(route('bookings.store', $this->day), [
            'range_id' => $this->range->id,
            'starts_at' => '18:00',
            'ends_at' => '19:00',
            'weapon_type' => 'kurzwaffe',
        ])->assertSessionHasErrors('starts_at');

        $this->assertSame(0, Booking::count());
    }

    public function test_unqualified_member_cannot_supervise()
    {
        $this->actingAs(User::factory()->create())->post(route('supervisions.store', $this->day), [
            'starts_at' => '17:00',
            'ends_at' => '21:00',
        ])->assertSessionHasErrors('user_id');
    }

    public function test_members_cannot_assign_others_as_supervisor()
    {
        $this->actingAs(User::factory()->create())->post(route('supervisions.store', $this->day), [
            'user_id' => $this->supervisor()->id,
            'starts_at' => '17:00',
            'ends_at' => '21:00',
        ])->assertForbidden();
    }

    public function test_admin_assigns_a_supervisor_for_one_range()
    {
        $admin = User::factory()->admin()->create();
        $supervisor = $this->supervisor();

        $this->actingAs($admin)->post(route('supervisions.store', $this->day), [
            'user_id' => $supervisor->id,
            'range_id' => $this->range->id,
            'starts_at' => '17:00',
            'ends_at' => '19:00',
        ])->assertSessionHasNoErrors();

        $supervision = Supervision::sole();
        $this->assertSame($supervisor->id, $supervision->user_id);
        $this->assertSame($admin->id, $supervision->assigned_by_id);
    }

    public function test_guest_booking_by_member()
    {
        Supervision::create(['shooting_day_id' => $this->day->id, 'user_id' => $this->supervisor()->id, 'starts_at' => '17:00', 'ends_at' => '21:00']);
        $host = User::factory()->create();

        $this->actingAs($host)->post(route('bookings.store', $this->day), [
            'range_id' => $this->range->id,
            'starts_at' => '17:00',
            'ends_at' => '18:00',
            'weapon_type' => 'langwaffe',
            'guest_name' => 'Gerda Gast',
        ])->assertSessionHasNoErrors();

        $booking = Booking::sole();
        $this->assertNull($booking->user_id);
        $this->assertSame($host->id, $booking->host_id);
    }

    public function test_member_cancels_own_booking_but_not_others()
    {
        Supervision::create(['shooting_day_id' => $this->day->id, 'user_id' => $this->supervisor()->id, 'starts_at' => '17:00', 'ends_at' => '21:00']);
        $member = User::factory()->create();
        $booking = Booking::factory()->create(['shooting_day_id' => $this->day->id, 'range_id' => $this->range->id, 'user_id' => $member->id]);

        $this->actingAs(User::factory()->create())->delete(route('bookings.destroy', $booking))->assertForbidden();
        $this->actingAs($member)->delete(route('bookings.destroy', $booking))->assertRedirect();

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
    }

    public function test_supervision_with_dependent_bookings_cannot_be_removed()
    {
        $supervisor = $this->supervisor();
        $supervision = Supervision::create(['shooting_day_id' => $this->day->id, 'user_id' => $supervisor->id, 'starts_at' => '17:00', 'ends_at' => '21:00']);
        Booking::factory()->create(['shooting_day_id' => $this->day->id, 'range_id' => $this->range->id, 'user_id' => User::factory()->create()->id]);

        $this->actingAs($supervisor)->delete(route('supervisions.destroy', $supervision))->assertSessionHasErrors('supervision');
        $this->assertModelExists($supervision);
    }

    public function test_my_bookings_page_lists_upcoming_and_past()
    {
        $member = User::factory()->create();
        Booking::factory()->create(['shooting_day_id' => $this->day->id, 'range_id' => $this->range->id, 'user_id' => $member->id]);
        $old = ShootingDay::factory()->withRange($this->range)->create(['date' => now()->subWeek()->toDateString()]);
        Booking::factory()->create(['shooting_day_id' => $old->id, 'range_id' => $this->range->id, 'user_id' => $member->id]);

        $this->actingAs($member)->get(route('bookings.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('bookings/Index')->has('upcoming', 1)->has('past', 1));
    }

    public function test_dashboard_shows_missing_supervision_to_qualified_members()
    {
        $this->actingAs($this->supervisor())->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Dashboard')->has('needsSupervision', 1)->has('nextDays', 1));
    }

    public function test_restricted_days_are_hidden_from_other_members()
    {
        $this->day->update(['restricted_to_groups' => true]);

        $this->actingAs(User::factory()->create())->get(route('shooting-days.show', $this->day))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('shooting-days.index'))
            ->assertInertia(fn ($page) => $page->has('days', 0));
    }

    public function test_dashboard_warns_about_expiring_qualifications()
    {
        $supervisor = User::factory()->create(['supervisor_scope' => 'licensed', 'supervisor_until' => now()->addDays(20)]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($supervisor)->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('qualificationExpires', now()->addDays(20)->toDateString()));

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->has('expiringQualifications', 1)->where('expiringQualifications.0.id', $supervisor->id));
    }
}
