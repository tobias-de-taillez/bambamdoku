<?php

namespace Tests\Feature\Admin;

use App\Enums\BookingStatus;
use App\Models\AuditEntry;
use App\Models\Booking;
use App\Models\Range;
use App\Models\ShootingDay;
use App\Models\Supervision;
use App\Models\User;
use App\Notifications\ShootingDayCancelled;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function createSeries(User $admin, array $data): TestResponse
    {
        return $this->actingAs($admin)->post(route('admin.shooting-days.store'), [
            'starts_at' => '10:00',
            'ends_at' => '14:00',
            'ranges' => [Range::factory()->create()->id],
            ...$data,
        ]);
    }

    /**
     * @return list<string>
     */
    private function createdDates(): array
    {
        return ShootingDay::orderBy('date')->get()->map(fn (ShootingDay $day) => $day->date->toDateString())->all();
    }

    public function test_admin_creates_first_and_third_saturday_series()
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00'));
        $admin = User::factory()->admin()->create();

        $response = $this->createSeries($admin, [
            'date' => '2026-11-07',
            'repeat' => 'monthly_weekday',
            'repeat_weeks' => ['1', '3'],
            'repeat_until' => '2027-01-31',
        ])->assertRedirect(route('admin.shooting-days.index'))->assertSessionHasNoErrors();

        $this->assertSame(
            ['2026-11-07', '2026-11-21', '2026-12-05', '2026-12-19', '2027-01-02', '2027-01-16'],
            $this->createdDates(),
        );
        $this->assertSame('6 Schießtage angelegt.', $response->getSession()->get(SessionKey::FLASH_DATA)['toast']['message']);
        $this->assertSame(
            'Schießtag angelegt: 07.11.2026 (+5, jeden 1. und 3. Samstag bis 31.01.2027), 10:00–14:00 Uhr',
            AuditEntry::where('action', 'shooting_day.created')->sole()->summary,
        );
    }

    public function test_admin_creates_a_last_friday_series()
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00'));

        $this->createSeries(User::factory()->admin()->create(), [
            'date' => '2026-11-27',
            'repeat' => 'monthly_weekday',
            'repeat_weeks' => ['4', 'last'],
            'repeat_until' => '2027-01-31',
        ])->assertSessionHasNoErrors();

        $this->assertSame(['2026-11-27', '2026-12-25', '2027-01-22', '2027-01-29'], $this->createdDates());
    }

    public function test_admin_creates_biweekly_and_monthly_day_series()
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00'));
        $admin = User::factory()->admin()->create();

        $this->createSeries($admin, ['date' => '2026-11-04', 'repeat' => 'biweekly', 'repeat_until' => '2026-12-16'])->assertSessionHasNoErrors();
        $this->assertSame(['2026-11-04', '2026-11-18', '2026-12-02', '2026-12-16'], $this->createdDates());

        ShootingDay::query()->delete();
        $this->createSeries($admin, ['date' => '2026-12-31', 'repeat' => 'monthly_day', 'repeat_until' => '2027-05-31'])->assertSessionHasNoErrors();
        $this->assertSame(['2026-12-31', '2027-01-31', '2027-03-31', '2027-05-31'], $this->createdDates());
        $this->assertStringContainsString('(+3, monatlich am 31. bis 31.05.2027)', AuditEntry::where('action', 'shooting_day.created')->latest('id')->first()->summary);
    }

    public function test_no_repetition_creates_a_single_day_even_with_an_end_date()
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00'));

        $this->createSeries(User::factory()->admin()->create(), [
            'date' => '2026-11-07',
            'repeat' => 'none',
            'repeat_until' => '2026-12-31',
        ])->assertSessionHasNoErrors();

        $this->assertSame(['2026-11-07'], $this->createdDates());
        $this->assertSame('Schießtag angelegt: 07.11.2026, 10:00–14:00 Uhr', AuditEntry::sole()->summary);
    }

    public function test_monthly_series_must_start_on_a_ticked_week()
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00'));

        $this->createSeries(User::factory()->admin()->create(), [
            'date' => '2026-11-14',
            'repeat' => 'monthly_weekday',
            'repeat_weeks' => ['1', '3'],
            'repeat_until' => '2027-01-31',
        ])->assertSessionHasErrors(['repeat_weeks' => 'Der 14.11.2026 ist der 2. Samstag im Monat. Bitte diese Woche ankreuzen oder ein anderes Startdatum wählen.']);

        $this->assertSame(0, ShootingDay::count());
    }

    public function test_monthly_series_needs_valid_weeks()
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00'));
        $admin = User::factory()->admin()->create();
        $series = ['date' => '2026-11-07', 'repeat' => 'monthly_weekday', 'repeat_until' => '2027-01-31'];

        $this->createSeries($admin, [...$series, 'repeat_weeks' => []])->assertSessionHasErrors('repeat_weeks');
        $this->createSeries($admin, [...$series, 'repeat_weeks' => ['5']])->assertSessionHasErrors('repeat_weeks.0');
        $this->createSeries($admin, [...$series, 'repeat_weeks' => ['1', '1']])->assertSessionHasErrors('repeat_weeks.0');

        $this->assertSame(0, ShootingDay::count());
    }

    public function test_series_need_an_end_date_within_one_year()
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00'));
        $admin = User::factory()->admin()->create();

        $this->createSeries($admin, ['date' => '2026-11-07', 'repeat' => 'weekly'])
            ->assertSessionHasErrors(['repeat_until' => 'Bitte angeben, bis wann wiederholt wird.']);
        $this->createSeries($admin, ['date' => '2026-11-07', 'repeat' => 'monthly_day', 'repeat_until' => '2027-11-03'])
            ->assertSessionHasErrors(['repeat_until' => 'Serien reichen höchstens ein Jahr im Voraus.']);
        $this->createSeries($admin, ['date' => '2026-11-07', 'repeat' => 'weekly', 'repeat_until' => '2026-11-07'])
            ->assertSessionHasErrors('repeat_until');
        $this->createSeries($admin, ['date' => '2026-11-07', 'repeat' => 'daily', 'repeat_until' => '2026-12-01'])
            ->assertSessionHasErrors('repeat');

        $this->assertSame(0, ShootingDay::count());
    }

    public function test_a_series_clashing_with_an_existing_day_creates_nothing()
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00'));
        $range = Range::factory()->create();
        ShootingDay::factory()->withRange($range)->create(['date' => '2026-12-19', 'starts_at' => '13:00', 'ends_at' => '16:00']);

        $this->createSeries(User::factory()->admin()->create(), [
            'date' => '2026-11-07',
            'repeat' => 'monthly_weekday',
            'repeat_weeks' => ['1', '3'],
            'repeat_until' => '2027-01-31',
            'ranges' => [$range->id],
        ])->assertSessionHasErrors(['starts_at' => 'Am 19.12.2026 gibt es schon einen Schießtag (13:00–16:00 Uhr) auf denselben Ständen.']);

        $this->assertSame(['2026-12-19'], $this->createdDates());
    }

    public function test_old_forms_with_a_yes_no_repeat_still_create_weekly_series()
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00'));
        $admin = User::factory()->admin()->create();

        $this->createSeries($admin, ['date' => '2026-11-07', 'repeat' => true, 'repeat_until' => '2026-11-21'])->assertSessionHasNoErrors();
        $this->assertSame(['2026-11-07', '2026-11-14', '2026-11-21'], $this->createdDates());

        ShootingDay::query()->delete();
        $this->createSeries($admin, ['date' => '2026-11-07', 'repeat' => false, 'repeat_until' => null])->assertSessionHasNoErrors();
        $this->assertSame(['2026-11-07'], $this->createdDates());
    }

    public function test_editing_ignores_repetition()
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00'));
        $day = ShootingDay::factory()->withRange()->create(['date' => '2026-11-07']);

        $this->actingAs(User::factory()->admin()->create())->put(route('admin.shooting-days.update', $day), [
            'date' => '2026-11-07',
            'repeat' => 'weekly',
            'repeat_until' => '2026-12-31',
            'starts_at' => '10:00',
            'ends_at' => '12:00',
            'ranges' => [$day->ranges->first()->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['2026-11-07'], $this->createdDates());
    }
}
