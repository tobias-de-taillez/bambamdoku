<?php

namespace Tests\Feature\Admin;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Group;
use App\Models\Range;
use App\Models\ShootingDay;
use App\Models\User;
use App\Notifications\ShootingDayCancelled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ShootingDayManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_manages_ranges()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.ranges.store'), [
            'name' => '50 m Gewehr',
            'lanes' => 8,
            'weapon_types' => ['langwaffe', 'luftdruck'],
        ])->assertRedirect(route('admin.ranges.index'));

        $range = Range::sole();
        $this->assertSame(8, $range->lanes);
        $this->assertCount(2, $range->weapon_types);

        $this->actingAs($admin)->put(route('admin.ranges.update', $range), [
            'name' => '50 m Gewehr',
            'lanes' => 6,
            'weapon_types' => ['langwaffe'],
            'active' => false,
        ])->assertRedirect();

        $this->assertFalse($range->fresh()->active);
    }

    public function test_range_needs_a_weapon_type()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.ranges.store'), ['name' => 'X', 'lanes' => 2, 'weapon_types' => []])
            ->assertSessionHasErrors('weapon_types');
    }

    public function test_members_cannot_manage_shooting_days()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.shooting-days.index'))
            ->assertForbidden();
    }

    public function test_admin_creates_a_weekly_series()
    {
        $admin = User::factory()->admin()->create();
        $range = Range::factory()->create();
        $start = now()->next('Wednesday');

        $this->actingAs($admin)->post(route('admin.shooting-days.store'), [
            'date' => $start->toDateString(),
            'repeat_until' => $start->copy()->addWeeks(3)->toDateString(),
            'starts_at' => '18:00',
            'ends_at' => '21:00',
            'ranges' => [$range->id],
        ])->assertRedirect(route('admin.shooting-days.index'));

        $days = ShootingDay::orderBy('date')->get();
        $this->assertCount(4, $days);
        $this->assertCount(1, $days->pluck('series')->unique());
        $this->assertTrue($days->every(fn ($day) => $day->ranges->pluck('id')->all() === [$range->id]));
    }

    public function test_end_must_be_after_start()
    {
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.shooting-days.store'), [
            'date' => now()->addDay()->toDateString(),
            'starts_at' => '20:00',
            'ends_at' => '18:00',
            'ranges' => [Range::factory()->create()->id],
        ])->assertSessionHasErrors('ends_at');
    }

    public function test_restricted_day_needs_groups()
    {
        $this->actingAs(User::factory()->admin()->create())->post(route('admin.shooting-days.store'), [
            'date' => now()->addDay()->toDateString(),
            'starts_at' => '18:00',
            'ends_at' => '20:00',
            'ranges' => [Range::factory()->create()->id],
            'restricted_to_groups' => true,
            'groups' => [],
        ])->assertSessionHasErrors('groups');
    }

    public function test_editing_can_apply_to_following_days_of_the_series()
    {
        $admin = User::factory()->admin()->create();
        $range = Range::factory()->create();
        $start = now()->next('Friday');

        $this->actingAs($admin)->post(route('admin.shooting-days.store'), [
            'date' => $start->toDateString(),
            'repeat_until' => $start->copy()->addWeeks(2)->toDateString(),
            'starts_at' => '18:00',
            'ends_at' => '21:00',
            'ranges' => [$range->id],
        ]);
        $second = ShootingDay::orderBy('date')->skip(1)->first();

        $this->actingAs($admin)->put(route('admin.shooting-days.update', $second), [
            'date' => $second->date->toDateString(),
            'starts_at' => '19:00',
            'ends_at' => '22:00',
            'ranges' => [$range->id],
            'apply_to_series' => true,
        ])->assertRedirect();

        $this->assertSame(['18:00:00', '19:00:00', '19:00:00'], ShootingDay::orderBy('date')->pluck('starts_at')->map(fn ($t) => strlen($t) === 5 ? "$t:00" : $t)->all());
    }

    public function test_admin_cancels_and_reopens_a_day()
    {
        $admin = User::factory()->admin()->create();
        $day = ShootingDay::factory()->withRange()->create();

        $this->actingAs($admin)->post(route('admin.shooting-days.cancel', $day), ['reason' => 'Unwetter']);
        $this->assertTrue($day->fresh()->isCancelled());
        $this->assertSame('Unwetter', $day->fresh()->cancel_reason);

        $this->actingAs($admin)->post(route('admin.shooting-days.reopen', $day));
        $this->assertFalse($day->fresh()->isCancelled());
    }

    public function test_restricted_days_are_only_visible_to_their_groups()
    {
        $youth = Group::create(['name' => 'Jugend']);
        $day = ShootingDay::factory()->create(['restricted_to_groups' => true]);
        $day->groups()->attach($youth);

        $member = User::factory()->create();
        $youngster = User::factory()->create();
        $youngster->groups()->attach($youth);

        $this->assertSame(0, ShootingDay::visibleTo($member)->count());
        $this->assertSame(1, ShootingDay::visibleTo($youngster)->count());
        $this->assertFalse($day->isOpenFor($member));
        $this->assertTrue($day->fresh()->isOpenFor($youngster));
    }

    public function test_cancelling_a_day_cancels_bookings_and_notifies_members()
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $day = ShootingDay::factory()->withRange()->create();
        $member = User::factory()->create();
        $booking = Booking::factory()->create(['shooting_day_id' => $day->id, 'range_id' => $day->ranges->first()->id, 'user_id' => $member->id]);

        $this->actingAs($admin)->post(route('admin.shooting-days.cancel', $day), ['reason' => 'Wartung']);

        $this->assertSame(BookingStatus::Cancelled, $booking->fresh()->status);
        Notification::assertSentTo($member, ShootingDayCancelled::class);
    }

    public function test_editing_cannot_push_bookings_out_of_the_day()
    {
        $admin = User::factory()->admin()->create();
        $day = ShootingDay::factory()->withRange()->create(['starts_at' => '17:00', 'ends_at' => '21:00']);
        $range = $day->ranges->first();
        Booking::factory()->create(['shooting_day_id' => $day->id, 'range_id' => $range->id, 'user_id' => User::factory()->create()->id, 'starts_at' => '20:00', 'ends_at' => '21:00']);

        $this->actingAs($admin)->put(route('admin.shooting-days.update', $day), [
            'date' => $day->date->toDateString(),
            'starts_at' => '17:00',
            'ends_at' => '19:00',
            'ranges' => [$range->id],
        ])->assertSessionHasErrors('starts_at');

        $this->assertSame('21:00', substr($day->fresh()->ends_at, 0, 5));
    }

    public function test_editing_cannot_move_a_booked_day()
    {
        $admin = User::factory()->admin()->create();
        $day = ShootingDay::factory()->withRange()->create();
        Booking::factory()->create(['shooting_day_id' => $day->id, 'range_id' => $day->ranges->first()->id, 'user_id' => User::factory()->create()->id]);

        $this->actingAs($admin)->put(route('admin.shooting-days.update', $day), [
            'date' => $day->date->addDay()->toDateString(),
            'starts_at' => '17:00',
            'ends_at' => '21:00',
            'ranges' => [$day->ranges->first()->id],
        ])->assertSessionHasErrors('starts_at');
    }
}
