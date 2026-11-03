<?php

namespace Tests\Feature\Admin;

use App\Models\LogEntry;
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
        $supervisor = User::factory()->create();
        LogEntry::create(['user_id' => $member->id, 'shot_on' => '2026-03-04', 'weapon_class' => 'short', 'weapon_owner' => 'own', 'recorded_by_id' => $supervisor->id, 'source' => 'stand']);
        LogEntry::create(['guest_name' => 'Gast', 'shot_on' => '2026-03-04', 'weapon_class' => 'short', 'recorded_by_id' => $supervisor->id, 'source' => 'stand']);

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

    public function test_visits_count_pairs_of_person_and_day_not_rows()
    {
        $this->travelTo(now()->setDate(2026, 11, 15));
        $member = User::factory()->create();
        $supervisor = User::factory()->create();
        $row = ['user_id' => $member->id, 'shot_on' => '2026-03-04', 'weapon_class' => 'short', 'weapon_owner' => 'own', 'recorded_by_id' => $supervisor->id, 'source' => 'session'];
        LogEntry::create([...$row, 'discipline' => 'BDS:1.10']);
        LogEntry::create([...$row, 'discipline' => 'BDS:1.20']);
        LogEntry::create([...$row, 'weapon_class' => 'long']);
        LogEntry::create([...$row, 'shot_on' => '2026-03-05']);

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.statistics', ['jahr' => 2026]))
            ->assertInertia(fn ($page) => $page
                ->where('totals.visits', 2)
                ->where('months.2.visits', 2)
                ->where('classes.0.visits', 2)
                ->where('classes.1.visits', 1)
                ->where('topMembers.0.visits', 2));
    }

    public function test_members_cannot_see_statistics()
    {
        $this->actingAs(User::factory()->create())->get(route('admin.statistics'))->assertForbidden();
    }
}
