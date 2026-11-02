<?php

namespace Tests\Feature;

use App\Http\Controllers\CalendarController;
use App\Models\Booking;
use App\Models\Range;
use App\Models\ShootingDay;
use App\Models\Supervision;
use App\Models\User;
use App\Notifications\TomorrowReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RemindersAndCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function tomorrow(): ShootingDay
    {
        return ShootingDay::factory()->withRange(Range::factory()->create(['name' => '25 m']))->create([
            'date' => today()->addDay()->toDateString(),
            'starts_at' => '17:00',
            'ends_at' => '21:00',
        ]);
    }

    public function test_members_and_supervisors_are_reminded_the_day_before()
    {
        Notification::fake();
        $day = $this->tomorrow();
        $member = User::factory()->create();
        $supervisor = User::factory()->create(['supervisor_scope' => 'licensed', 'supervisor_until' => now()->addYear()]);
        Booking::factory()->create(['shooting_day_id' => $day->id, 'range_id' => $day->ranges->first()->id, 'user_id' => $member->id]);
        Supervision::create(['shooting_day_id' => $day->id, 'user_id' => $supervisor->id, 'starts_at' => '17:00', 'ends_at' => '21:00']);

        $this->artisan('verein:erinnern')->assertSuccessful();

        Notification::assertSentTo($member, TomorrowReminder::class, fn ($n) => count($n->bookings) === 1 && $n->duties === []);
        Notification::assertSentTo($supervisor, TomorrowReminder::class, fn ($n) => count($n->duties) === 1);
    }

    public function test_no_reminders_for_cancelled_days()
    {
        Notification::fake();
        $day = $this->tomorrow();
        $day->forceFill(['cancelled_at' => now()])->save();
        Booking::factory()->create(['shooting_day_id' => $day->id, 'range_id' => $day->ranges->first()->id, 'user_id' => User::factory()->create()->id]);

        $this->artisan('verein:erinnern')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_reminder_is_scheduled()
    {
        $this->artisan('schedule:list')->expectsOutputToContain('verein:erinnern');
    }

    public function test_calendar_feed_lists_bookings_and_duties()
    {
        $day = $this->tomorrow();
        $member = User::factory()->create(['supervisor_scope' => 'licensed', 'supervisor_until' => now()->addYear()]);
        Booking::factory()->create(['shooting_day_id' => $day->id, 'range_id' => $day->ranges->first()->id, 'user_id' => $member->id]);
        Supervision::create(['shooting_day_id' => $day->id, 'user_id' => $member->id, 'starts_at' => '17:00', 'ends_at' => '21:00']);

        $response = $this->get(CalendarController::url($member));

        $response->assertOk();
        $this->assertStringContainsString('text/calendar', $response->headers->get('content-type'));
        $this->assertSame(2, substr_count($response->getContent(), 'BEGIN:VEVENT'));
        $this->assertStringContainsString('Standaufsicht: ganze Anlage', $response->getContent());
    }

    public function test_calendar_token_is_secret_and_can_be_rotated()
    {
        $member = User::factory()->create();
        $old = CalendarController::url($member);

        $this->actingAs($member)->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->missing('auth.user.calendar_token'));

        $this->actingAs($member)->post(route('calendar.regenerate'));

        $this->get($old)->assertNotFound();
        $this->get(CalendarController::url($member->fresh()))->assertOk();
    }

    public function test_unknown_token_is_not_found()
    {
        $this->get('/kalender/'.str_repeat('a', 48).'.ics')->assertNotFound();
    }
}
