<?php

namespace Tests\Feature\Admin;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Range;
use App\Models\ShootingDay;
use App\Models\Supervision;
use App\Models\User;
use App\Notifications\ShootingDayCancelled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Support\SessionKey;
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

    public function test_editing_a_weekly_day_changes_only_that_day()
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
        ])->assertRedirect();

        // Die Serien-Verknüpfung ist stillgelegt: Änderungen gelten nur für den bearbeiteten Termin.
        $this->assertNull(ShootingDay::orderBy('date')->value('series'));
        $this->assertSame(['18:00:00', '19:00:00', '18:00:00'], ShootingDay::orderBy('date')->pluck('starts_at')->map(fn ($t) => strlen($t) === 5 ? "$t:00" : $t)->all());
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

    public function test_former_group_days_are_open_to_all_members()
    {
        // Gruppen sind stillgelegt; die Spalte bleibt, wirkt aber nicht mehr.
        $day = ShootingDay::factory()->withRange()->create();
        DB::table('shooting_days')->where('id', $day->id)->update(['restricted_to_groups' => true]);

        $this->actingAs(User::factory()->create())->get(route('shooting-days.show', $day))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('shooting-days.index'))
            ->assertInertia(fn ($page) => $page->has('days', 1));
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

    public function test_cancel_message_only_claims_notification_with_a_real_mailer()
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $toast = function () use ($admin): string {
            $day = ShootingDay::factory()->withRange()->create();
            Booking::factory()->create(['shooting_day_id' => $day->id, 'range_id' => $day->ranges->first()->id, 'user_id' => User::factory()->create()->id]);

            $response = $this->actingAs($admin)->post(route('admin.shooting-days.cancel', $day));

            return $response->getSession()->get(SessionKey::FLASH_DATA)['toast']['message'];
        };

        config(['mail.default' => 'log']);
        $message = $toast();
        $this->assertStringNotContainsString('und benachrichtigt', $message);
        $this->assertStringContainsString('niemand wurde benachrichtigt', $message);

        config(['mail.default' => 'smtp']);
        $this->assertSame('Schießtag abgesagt, eine Buchung storniert und benachrichtigt.', $toast());
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

    public function test_admin_list_shows_supervision_and_bookings()
    {
        $admin = User::factory()->admin()->create();
        $day = ShootingDay::factory()->withRange()->create();
        $supervisor = User::factory()->create(['name' => 'Sabine', 'supervisor_scope' => 'licensed', 'supervisor_until' => now()->addYear()]);
        Supervision::create(['shooting_day_id' => $day->id, 'user_id' => $supervisor->id, 'starts_at' => '17:00', 'ends_at' => '21:00']);
        Booking::factory()->create(['shooting_day_id' => $day->id, 'range_id' => $day->ranges->first()->id, 'user_id' => User::factory()->create()->id]);

        $this->actingAs($admin)->get(route('admin.shooting-days.index'))
            ->assertInertia(fn ($page) => $page->where('days.0.booked', 1)->where('days.0.supervisors.0', 'Sabine'));
    }

    public function test_days_without_bookings_can_be_deleted()
    {
        $admin = User::factory()->admin()->create();
        $empty = ShootingDay::factory()->withRange()->create();
        $booked = ShootingDay::factory()->withRange()->create();
        Booking::factory()->create(['shooting_day_id' => $booked->id, 'range_id' => $booked->ranges->first()->id, 'user_id' => User::factory()->create()->id]);

        $this->actingAs($admin)->delete(route('admin.shooting-days.destroy', $empty))->assertRedirect(route('admin.shooting-days.index'));
        $this->actingAs($admin)->delete(route('admin.shooting-days.destroy', $booked))->assertSessionHasErrors('shooting_day');

        $this->assertModelMissing($empty);
        $this->assertModelExists($booked);
    }
}
