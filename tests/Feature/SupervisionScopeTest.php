<?php

namespace Tests\Feature;

use App\Domain\Bookings;
use App\Domain\Schedule;
use App\Domain\TimeWindow;
use App\Enums\BookingStatus;
use App\Enums\SupervisionScope;
use App\Enums\WeaponType;
use App\Models\Booking;
use App\Models\Range;
use App\Models\ShootingDay;
use App\Models\Supervision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Umfang der Aufsicht bei Planung und Buchung (Befund G9): Kurz- und Langwaffe nur unter
 * einer Aufsicht mit Umfang erlaubnispflichtig, wie am Tor zum Eintrag.
 */
class SupervisionScopeTest extends TestCase
{
    use RefreshDatabase;

    private Bookings $bookings;

    private Range $pistol;

    private Range $mixed;

    private User $exempt;

    private User $licensed;

    protected function setUp(): void
    {
        parent::setUp();

        config(['verein.slot_minutes' => 60]);
        $this->bookings = app(Bookings::class);
        $this->pistol = Range::factory()->create(['name' => '25 m Kurzwaffe', 'lanes' => 3, 'position' => 1, 'weapon_types' => [WeaponType::Pistol]]);
        $this->mixed = Range::factory()->create(['name' => 'Mehrzweckstand', 'lanes' => 3, 'position' => 2, 'weapon_types' => [WeaponType::Airgun, WeaponType::Pistol]]);
        $this->exempt = User::factory()->supervisor(SupervisionScope::Exempt)->create(['name' => 'Ben Bogen']);
        $this->licensed = User::factory()->supervisor()->create(['name' => 'Helga Schießwart']);
    }

    private function day(Range ...$ranges): ShootingDay
    {
        $day = ShootingDay::factory()->create([
            'date' => now()->addDays(2)->toDateString(),
            'starts_at' => '17:00',
            'ends_at' => '21:00',
        ]);
        $day->ranges()->attach(collect($ranges)->pluck('id'));

        return $day->fresh();
    }

    private function supervise(ShootingDay $day, User $supervisor, ?Range $range = null, string $from = '17:00', string $to = '21:00', ?User $actor = null): Supervision
    {
        return $this->bookings->assignSupervision($actor ?? $supervisor, $day->fresh(), $supervisor, $range, TimeWindow::fromStrings($from, $to));
    }

    private function book(ShootingDay $day, Range $range, WeaponType $type, string $from = '18:00', string $to = '19:00'): Booking
    {
        $member = User::factory()->create();

        return $this->bookings->book($member, $day->fresh(), $range, TimeWindow::fromStrings($from, $to), $type, shooter: $member);
    }

    private function assertRejected(callable $action, string $field, ?string $message = null): void
    {
        try {
            $action();
            $this->fail('Es wurde keine ValidationException geworfen.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors(), json_encode($e->errors()));

            if ($message !== null) {
                $this->assertStringContainsString($message, $e->errors()[$field][0]);
            }
        }
    }

    // --- Planung ---

    public function test_exempt_supervisor_cannot_take_a_pure_pistol_range()
    {
        $day = $this->day($this->pistol, $this->mixed);

        $this->assertRejected(
            fn () => $this->supervise($day, $this->exempt, $this->pistol),
            'range_id',
            'Für Stand „25 m Kurzwaffe“ braucht die Aufsicht den Umfang erlaubnispflichtig. Sie sind nur für erlaubnisfreie Waffen registriert.',
        );
        $this->assertSame(0, Supervision::count());
    }

    public function test_exempt_supervisor_cannot_take_the_whole_day_when_only_licensed_ranges_are_open()
    {
        $rifle = Range::factory()->create(['name' => '50 m Gewehr', 'weapon_types' => [WeaponType::Rifle, WeaponType::Pistol]]);
        $day = $this->day($this->pistol, $rifle);

        $this->assertRejected(
            fn () => $this->supervise($day, $this->exempt),
            'range_id',
            'An diesem Tag sind nur Stände für erlaubnispflichtige Waffen geöffnet.',
        );
        $this->assertSame(0, Supervision::count());
    }

    public function test_inactive_ranges_do_not_open_the_whole_day_for_exempt_supervisors()
    {
        $air = Range::factory()->create(['name' => '10 m Luftdruck', 'weapon_types' => [WeaponType::Airgun], 'active' => false]);
        $day = $this->day($this->pistol, $air);

        $this->assertRejected(fn () => $this->supervise($day, $this->exempt), 'range_id');
    }

    public function test_exempt_supervisor_may_take_a_mixed_range_and_a_mixed_day()
    {
        $day = $this->day($this->pistol, $this->mixed);

        $this->assertNotNull($this->supervise($day, $this->exempt, $this->mixed, '17:00', '19:00'));
        $this->assertNotNull($this->supervise($day, $this->exempt, null, '19:00', '21:00'));
        $this->assertSame(2, Supervision::count());
    }

    public function test_licensed_supervisor_may_take_any_range()
    {
        $day = $this->day($this->pistol, $this->mixed);

        $this->assertNotNull($this->supervise($day, $this->licensed, $this->pistol, '17:00', '19:00'));
        $this->assertNotNull($this->supervise($day, $this->licensed, null, '19:00', '21:00'));
    }

    public function test_board_cannot_assign_an_exempt_supervisor_to_a_pistol_range()
    {
        $admin = User::factory()->admin()->create();
        $day = $this->day($this->pistol);

        $this->assertRejected(
            fn () => $this->supervise($day, $this->exempt, $this->pistol, actor: $admin),
            'range_id',
            'Ben Bogen ist nur für erlaubnisfreie Waffen registriert.',
        );
        $this->assertRejected(fn () => $this->supervise($day, $this->exempt, actor: $admin), 'range_id');

        $this->actingAs($admin)->post(route('supervisions.store', $day), [
            'user_id' => $this->exempt->id,
            'range_id' => $this->pistol->id,
            'starts_at' => '17:00',
            'ends_at' => '21:00',
        ])->assertSessionHasErrors('range_id');

        $this->assertSame(0, Supervision::count());
    }

    public function test_exempt_member_taking_over_a_pistol_range_sees_the_error_at_the_range()
    {
        $day = $this->day($this->pistol);

        // range_id, nicht user_id: Das Feld Aufsicht sehen nur Admins.
        $this->actingAs($this->exempt)->post(route('supervisions.store', $day), [
            'range_id' => $this->pistol->id,
            'starts_at' => '17:00',
            'ends_at' => '21:00',
        ])->assertSessionHasErrors('range_id');

        $this->actingAs($this->exempt)->post(route('supervisions.store', $day), [
            'starts_at' => '17:00',
            'ends_at' => '21:00',
        ])->assertSessionHasErrors('range_id');

        $this->assertSame(0, Supervision::count());
    }

    // --- Buchung ---

    public function test_pistol_cannot_be_booked_under_exempt_coverage_only()
    {
        $day = $this->day($this->pistol, $this->mixed);
        $this->supervise($day, $this->exempt);

        $this->assertRejected(fn () => $this->book($day, $this->mixed, WeaponType::Pistol), 'weapon_type', 'Kurzwaffe nur unter einer Aufsicht mit Umfang erlaubnispflichtig.');
        $this->assertRejected(fn () => $this->book($day, $this->pistol, WeaponType::Pistol), 'weapon_type');
        $this->assertSame(0, Booking::count());
    }

    public function test_airgun_can_be_booked_under_exempt_coverage()
    {
        $day = $this->day($this->pistol, $this->mixed);
        $this->supervise($day, $this->exempt);

        $booking = $this->book($day, $this->mixed, WeaponType::Airgun);

        $this->assertSame(BookingStatus::Booked, $booking->status);
    }

    public function test_licensed_supervisor_allows_pistol_and_airgun()
    {
        $day = $this->day($this->pistol, $this->mixed);
        $this->supervise($day, $this->licensed);

        $this->assertNotNull($this->book($day, $this->pistol, WeaponType::Pistol));
        $this->assertNotNull($this->book($day, $this->mixed, WeaponType::Pistol));
        $this->assertNotNull($this->book($day, $this->mixed, WeaponType::Airgun));
    }

    public function test_pistol_needs_licensed_coverage_for_the_whole_window()
    {
        $day = $this->day($this->mixed);
        $this->supervise($day, $this->licensed, null, '17:00', '19:00');
        $this->supervise($day, $this->exempt, null, '19:00', '21:00');

        $this->assertRejected(fn () => $this->book($day, $this->mixed, WeaponType::Pistol, '18:00', '20:00'), 'weapon_type');
        $this->assertNotNull($this->book($day, $this->mixed, WeaponType::Pistol, '17:00', '19:00'));
        $this->assertNotNull($this->book($day, $this->mixed, WeaponType::Airgun, '18:00', '20:00'));
    }

    public function test_booking_by_http_shows_the_error_at_the_weapon_type()
    {
        $day = $this->day($this->mixed);
        $this->supervise($day, $this->exempt);

        $this->actingAs(User::factory()->create())->post(route('bookings.store', $day), [
            'range_id' => $this->mixed->id,
            'starts_at' => '18:00',
            'ends_at' => '19:00',
            'weapon_type' => 'kurzwaffe',
        ])->assertSessionHasErrors('weapon_type');

        $this->assertSame(0, Booking::count());
    }

    public function test_downgraded_scope_no_longer_covers_pistol()
    {
        $day = $this->day($this->pistol);
        $this->supervise($day, $this->licensed);
        $this->licensed->forceFill(['supervisor_scope' => SupervisionScope::Exempt])->save();

        $this->assertRejected(fn () => $this->book($day, $this->pistol, WeaponType::Pistol), 'weapon_type');
    }

    // --- Raster ---

    public function test_grid_shows_licensed_coverage_per_slot()
    {
        $day = $this->day($this->pistol, $this->mixed);
        $this->supervise($day, $this->exempt);

        $this->actingAs(User::factory()->create())->get(route('shooting-days.show', $day))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('grid.0.name', '25 m Kurzwaffe')
                ->where('grid.0.slots.0.supervised', false)
                ->where('grid.0.slots.0.licensed', false)
                ->where('grid.1.name', 'Mehrzweckstand')
                ->where('grid.1.slots.0.supervised', true)
                ->where('grid.1.slots.0.licensed', false)
                ->where('day.supervision', 'partial')
                ->where('weaponTypes.0.value', 'kurzwaffe')
                ->where('weaponTypes.0.licensed', true)
                ->where('weaponTypes.2.value', 'luftdruck')
                ->where('weaponTypes.2.licensed', false));

        $this->supervise($day, $this->licensed, $this->pistol, '17:00', '18:00');

        $grid = (new Schedule($day->fresh()))->grid();
        $this->assertSame(['17:00' => true, '18:00' => false, '19:00' => false, '20:00' => false], collect($grid[0]['slots'])->pluck('supervised', 'start')->all());
        $this->assertSame(['17:00' => true, '18:00' => false, '19:00' => false, '20:00' => false], collect($grid[0]['slots'])->pluck('licensed', 'start')->all());
        $this->assertFalse(collect($grid[1]['slots'])->contains('licensed', true));
    }

    // --- Austragen und Zurücksetzen ---

    public function test_licensed_supervision_cannot_be_removed_when_only_exempt_coverage_would_remain_for_pistol()
    {
        $day = $this->day($this->mixed);
        $licensed = $this->supervise($day, $this->licensed);
        $this->supervise($day, $this->exempt);
        $this->book($day, $this->mixed, WeaponType::Pistol);

        $this->assertRejected(fn () => $this->bookings->removeSupervision($this->licensed, $licensed), 'supervision');
        $this->assertModelExists($licensed);
    }

    public function test_licensed_supervision_can_be_removed_when_exempt_coverage_suffices_for_airgun()
    {
        $day = $this->day($this->mixed);
        $licensed = $this->supervise($day, $this->licensed);
        $this->supervise($day, $this->exempt);
        $this->book($day, $this->mixed, WeaponType::Airgun);

        $this->bookings->removeSupervision($this->licensed, $licensed);

        $this->assertModelMissing($licensed);
    }

    public function test_no_show_pistol_booking_is_not_reset_under_exempt_coverage_only()
    {
        $admin = User::factory()->admin()->create();
        $day = $this->day($this->mixed);
        $licensed = $this->supervise($day, $this->licensed);
        $this->supervise($day, $this->exempt);
        $booking = $this->book($day, $this->mixed, WeaponType::Pistol);

        $this->bookings->markNoShow($admin, $booking);
        $this->bookings->removeSupervision($admin, $licensed);

        $this->assertRejected(fn () => $this->bookings->reset($admin, $booking->fresh()), 'booking', 'Umfang erlaubnispflichtig');
        $this->assertSame(BookingStatus::NoShow, $booking->fresh()->status);
    }
}
