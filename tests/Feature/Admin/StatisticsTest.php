<?php

namespace Tests\Feature\Admin;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\ShootingDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_yearly_statistics()
    {
        $this->travelTo(now()->setDate(2026, 11, 15));
        $day = ShootingDay::factory()->withRange()->create(['date' => '2026-03-04']);
        $member = User::factory()->create(['name' => 'Max']);
        $visit = Booking::factory()->create(['shooting_day_id' => $day->id, 'range_id' => $day->ranges->first()->id, 'user_id' => $member->id]);
        $visit->forceFill(['status' => BookingStatus::CheckedIn])->save();
        $guest = Booking::factory()->create(['shooting_day_id' => $day->id, 'range_id' => $day->ranges->first()->id, 'guest_name' => 'Gast', 'host_id' => $member->id]);
        $guest->forceFill(['status' => BookingStatus::CheckedIn])->save();

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.statistics', ['jahr' => 2026]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/Statistics')
                ->where('totals.visits', 2)
                ->where('totals.guests', 1)
                ->where('totals.members', 1)
                ->where('months.2.visits', 2)
                ->where('topMembers.0.name', 'Max'));
    }

    public function test_members_cannot_see_statistics()
    {
        $this->actingAs(User::factory()->create())->get(route('admin.statistics'))->assertForbidden();
    }
}
