<?php

namespace Tests\Feature;

use App\Models\Range;
use App\Models\ShootingDay;
use App\Models\Supervision;
use App\Models\User;
use App\Notifications\SupervisionNeeded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SupervisionRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_qualified_members_are_asked_when_supervision_is_missing()
    {
        Notification::fake();
        $day = ShootingDay::factory()->withRange(Range::factory()->create())->create(['date' => today()->addDays(3)->toDateString(), 'starts_at' => '17:00', 'ends_at' => '21:00']);
        $qualified = User::factory()->create(['supervisor_scope' => 'licensed', 'supervisor_until' => now()->addYear()]);
        $expired = User::factory()->create(['supervisor_scope' => 'licensed', 'supervisor_until' => now()->subDay()]);
        $member = User::factory()->create();

        $this->artisan('verein:aufsicht-gesucht')->assertSuccessful();

        Notification::assertSentTo($qualified, SupervisionNeeded::class, fn ($n) => $n->partial === false && $n->day->is($day));
        Notification::assertNotSentTo([$expired, $member], SupervisionNeeded::class);
    }

    public function test_nobody_is_asked_when_the_day_is_covered()
    {
        Notification::fake();
        $day = ShootingDay::factory()->withRange(Range::factory()->create())->create(['date' => today()->addDays(3)->toDateString(), 'starts_at' => '17:00', 'ends_at' => '21:00']);
        $supervisor = User::factory()->create(['supervisor_scope' => 'licensed', 'supervisor_until' => now()->addYear()]);
        User::factory()->create(['supervisor_scope' => 'licensed', 'supervisor_until' => now()->addYear()]);
        Supervision::create(['shooting_day_id' => $day->id, 'user_id' => $supervisor->id, 'starts_at' => '17:00', 'ends_at' => '21:00']);

        $this->artisan('verein:aufsicht-gesucht')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_partial_coverage_asks_the_others()
    {
        Notification::fake();
        $day = ShootingDay::factory()->withRange(Range::factory()->create())->create(['date' => today()->addDays(3)->toDateString(), 'starts_at' => '17:00', 'ends_at' => '21:00']);
        $supervisor = User::factory()->create(['supervisor_scope' => 'licensed', 'supervisor_until' => now()->addYear()]);
        $other = User::factory()->create(['supervisor_scope' => 'licensed', 'supervisor_until' => now()->addYear()]);
        Supervision::create(['shooting_day_id' => $day->id, 'user_id' => $supervisor->id, 'starts_at' => '17:00', 'ends_at' => '19:00']);

        $this->artisan('verein:aufsicht-gesucht')->assertSuccessful();

        Notification::assertSentTo($other, SupervisionNeeded::class, fn ($n) => $n->partial === true);
        Notification::assertNotSentTo($supervisor, SupervisionNeeded::class);
    }
}
