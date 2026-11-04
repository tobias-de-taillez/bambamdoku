<?php

namespace Tests\Feature;

use App\Domain\Bookings;
use App\Domain\Schedule;
use App\Domain\TimeWindow;
use App\Enums\BookingStatus;
use App\Enums\SupervisionScope;
use App\Enums\WeaponType;
use App\Models\Booking;
use App\Models\Qualification;
use App\Models\Range;
use App\Models\ShootingDay;
use App\Models\Supervision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
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

    // --- Tagesseite: Schild und Namen nur aus Aufsichten, die zählen ---

    private function register(User $user, SupervisionScope $scope): void
    {
        Qualification::register($user, $scope, $scope === SupervisionScope::Revoked ? null : now()->addYear()->toDateString(), now()->toDateString(), User::factory()->admin()->create(), 'Test');
    }

    /**
     * Tagesseite und Liste der Schießtage zeigen für den Tag „none“ ohne Namen; die Aufsicht bleibt
     * für den Vorstand in der Liste der Eingeteilten, mit Grund, warum sie nicht zählt.
     */
    private function assertShownWithoutCountingSupervision(ShootingDay $day, string $reason): void
    {
        $member = User::factory()->create();

        $this->assertSame('none', (new Schedule($day->fresh()))->state());
        $this->assertFalse((new Schedule($day->fresh()))->hasAnySupervision());

        // Der Vorstand sieht die Einteilung mit Grund, ein Mitglied gar nicht: Der Registerstand
        // einer anderen Person geht es nichts an.
        $this->actingAs(User::factory()->admin()->create())->get(route('shooting-days.show', $day))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('day.supervision', 'none')
                ->has('day.supervisors', 0)
                ->has('supervisions', 1)
                ->where('supervisions.0.not_counting', $reason));

        $this->actingAs($member)->get(route('shooting-days.show', $day))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('day.supervision', 'none')
                ->has('day.supervisors', 0)
                ->has('supervisions', 0));

        $this->actingAs($member)->get(route('shooting-days.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('days.0.id', $day->id)
                ->where('days.0.supervision', 'none')
                ->has('days.0.supervisors', 0));
    }

    public function test_pistol_day_is_none_when_the_only_supervisor_was_downgraded()
    {
        $day = $this->day($this->pistol);
        $this->supervise($day, $this->licensed);
        $this->register($this->licensed, SupervisionScope::Exempt);

        $this->assertShownWithoutCountingSupervision($day, 'scope');
    }

    public function test_pistol_day_is_none_when_the_only_supervisor_was_revoked()
    {
        $day = $this->day($this->pistol);
        $this->supervise($day, $this->licensed, $this->pistol);
        $this->register($this->licensed, SupervisionScope::Revoked);

        $this->assertShownWithoutCountingSupervision($day, 'unregistered');
    }

    public function test_legacy_exempt_supervision_on_a_pistol_range_does_not_count()
    {
        $day = $this->day($this->pistol);
        // Altdaten von vor G9: an der Prüfung beim Übernehmen vorbei angelegt.
        Supervision::create(['shooting_day_id' => $day->id, 'user_id' => $this->exempt->id, 'range_id' => $this->pistol->id, 'starts_at' => '17:00', 'ends_at' => '21:00']);

        $this->assertShownWithoutCountingSupervision($day, 'scope');
    }

    public function test_counting_supervision_keeps_its_name_next_to_one_that_does_not_count()
    {
        $air = Range::factory()->create(['name' => '10 m Luftdruck', 'position' => 3, 'weapon_types' => [WeaponType::Airgun]]);
        $day = $this->day($this->pistol, $air);
        $this->supervise($day, $this->exempt, null, '17:00', '19:00');
        $this->supervise($day, $this->licensed, $this->pistol);
        $this->register($this->licensed, SupervisionScope::Exempt);

        // Mit Grund nur für den Vorstand; Mitglieder siehe test_members_see_counting_supervisions_…
        $this->actingAs(User::factory()->admin()->create())->get(route('shooting-days.show', $day))
            ->assertInertia(fn (Assert $page) => $page
                ->where('day.supervision', 'partial')
                ->where('day.supervisors', ['Ben Bogen'])
                ->has('supervisions', 2)
                ->where('supervisions.0.name', 'Ben Bogen')
                ->where('supervisions.0.not_counting', null)
                ->where('supervisions.1.name', 'Helga Schießwart')
                ->where('supervisions.1.not_counting', 'scope'));
    }

    // --- Stände außer Betrieb ---

    public function test_inactive_pistol_range_does_not_make_the_day_partial()
    {
        $air = Range::factory()->create(['name' => '10 m Luftdruck', 'position' => 3, 'weapon_types' => [WeaponType::Airgun]]);
        $this->pistol->forceFill(['active' => false])->save();
        $day = $this->day($air, $this->pistol);
        $this->supervise($day, $this->exempt);

        $this->assertSame('full', (new Schedule($day->fresh()))->state());
        $this->actingAs(User::factory()->create())->get(route('shooting-days.show', $day))
            ->assertInertia(fn (Assert $page) => $page
                ->where('day.supervision', 'full')
                ->where('day.supervisors', ['Ben Bogen']));
    }

    public function test_supervision_only_on_an_inactive_range_does_not_count()
    {
        $air = Range::factory()->create(['name' => '10 m Luftdruck', 'position' => 3, 'weapon_types' => [WeaponType::Airgun]]);
        $day = $this->day($air, $this->mixed);
        $this->supervise($day, $this->licensed, $this->mixed);
        $this->mixed->forceFill(['active' => false])->save();

        $this->assertShownWithoutCountingSupervision($day, 'inactive');
    }

    public function test_day_without_active_ranges_still_counts_its_ranges()
    {
        $this->pistol->forceFill(['active' => false])->save();
        $day = $this->day($this->pistol);

        // Nicht „full“, nur weil kein Stand aktiv ist.
        $this->assertSame('none', (new Schedule($day->fresh()))->state());

        // Ohne aktiven Stand lässt die Planung die ganze Anlage auch erlaubnisfrei zu; für den
        // reinen Kurzwaffenstand zählt das trotzdem nicht.
        $this->supervise($day, $this->exempt);
        $this->assertSame('none', (new Schedule($day->fresh()))->state());

        $this->supervise($day, $this->licensed, null, '17:00', '19:00');
        $this->assertSame('partial', (new Schedule($day->fresh()))->state());
    }

    // --- Selbst übernehmen nur, wo der Umfang reicht ---

    public function test_exempt_member_is_not_offered_to_take_over_a_pure_pistol_day()
    {
        $day = $this->day($this->pistol);

        $this->actingAs($this->exempt)->get(route('shooting-days.show', $day))
            ->assertInertia(fn (Assert $page) => $page
                ->where('day.supervision', 'none')
                ->where('canSupervise', true)
                ->where('canTakeOver', false)
                ->where('canSuperviseLicensed', false)
                ->where('grid.0.only_licensed', true));

        $this->actingAs($this->licensed)->get(route('shooting-days.show', $day))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canTakeOver', true)
                ->where('canSuperviseLicensed', true));
    }

    public function test_exempt_member_may_take_over_a_mixed_day_but_sees_the_pistol_range_marked()
    {
        $day = $this->day($this->pistol, $this->mixed);

        $this->actingAs($this->exempt)->get(route('shooting-days.show', $day))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canTakeOver', true)
                ->where('canSuperviseLicensed', false)
                ->where('grid.0.name', '25 m Kurzwaffe')
                ->where('grid.0.only_licensed', true)
                ->where('grid.1.name', 'Mehrzweckstand')
                ->where('grid.1.only_licensed', false));
    }

    public function test_board_keeps_assigning_others_on_a_pure_pistol_day()
    {
        $day = $this->day($this->pistol);
        $board = User::factory()->admin()->create();
        $exemptBoard = User::factory()->admin()->supervisor(SupervisionScope::Exempt)->create();

        foreach ([$board, $exemptBoard] as $admin) {
            $this->actingAs($admin)->get(route('shooting-days.show', $day))
                ->assertInertia(fn (Assert $page) => $page
                    ->where('canTakeOver', false)
                    ->where('supervisorCandidates', fn ($candidates) => collect($candidates)->pluck('name')->contains('Helga Schießwart')));
        }

        $this->actingAs($exemptBoard)->post(route('supervisions.store', $day), [
            'user_id' => $this->licensed->id,
            'range_id' => $this->pistol->id,
            'starts_at' => '17:00',
            'ends_at' => '21:00',
        ])->assertSessionHasNoErrors();
    }

    /**
     * Was die Tagesseite anbietet, nimmt der Server an, und umgekehrt (Bookings::ensureScopeFits).
     */
    public function test_take_over_flag_matches_what_the_server_accepts_for_the_whole_day()
    {
        $air = Range::factory()->create(['name' => '10 m Luftdruck', 'position' => 3, 'weapon_types' => [WeaponType::Airgun]]);
        $inactivePistol = Range::factory()->create(['name' => 'Alter Kurzwaffenstand', 'position' => 4, 'weapon_types' => [WeaponType::Pistol], 'active' => false]);
        $inactiveAir = Range::factory()->create(['name' => 'Alter Luftstand', 'position' => 5, 'weapon_types' => [WeaponType::Airgun], 'active' => false]);

        foreach ([
            'nur Kurzwaffe' => [[$this->pistol], false],
            'Kurzwaffe und gemischt' => [[$this->pistol, $this->mixed], true],
            'Kurzwaffe und Luft außer Betrieb' => [[$this->pistol, $inactiveAir], false],
            'Luft und Kurzwaffe außer Betrieb' => [[$air, $inactivePistol], true],
            'nur Stände außer Betrieb' => [[$inactivePistol], true],
        ] as $case => [$ranges, $expected]) {
            $day = $this->day(...$ranges);

            $this->assertSame($expected, (new Schedule($day))->canBeTakenOverBy($this->exempt), $case);
            $this->assertTrue((new Schedule($day))->canBeTakenOverBy($this->licensed), $case);

            try {
                $this->supervise($day, $this->exempt);
                $this->assertTrue($expected, $case.': Server nimmt an, die Seite bietet es nicht an.');
            } catch (ValidationException $e) {
                $this->assertFalse($expected, $case.': Seite bietet an, der Server lehnt ab. '.json_encode($e->errors()));
                $this->assertArrayHasKey('range_id', $e->errors(), $case);
            }

            // Alle Fälle am selben Datum: sonst lehnt der nächste wegen Überschneidung ab.
            Supervision::query()->delete();
        }
    }

    public function test_the_supervisor_sees_the_own_not_counting_supervision_with_reason()
    {
        $day = $this->day($this->pistol);
        $this->supervise($day, $this->licensed);
        $this->register($this->licensed, SupervisionScope::Exempt);

        $this->actingAs($this->licensed->fresh())->get(route('shooting-days.show', $day))
            ->assertInertia(fn (Assert $page) => $page
                ->has('supervisions', 1)
                ->where('supervisions.0.mine', true)
                ->where('supervisions.0.can_remove', true)
                ->where('supervisions.0.not_counting', 'scope'));
    }

    public function test_members_see_counting_supervisions_but_not_the_one_that_no_longer_counts()
    {
        $air = Range::factory()->create(['name' => '10 m Luft', 'lanes' => 3, 'position' => 3, 'weapon_types' => [WeaponType::Airgun]]);
        $day = $this->day($this->pistol, $air);
        $this->supervise($day, $this->licensed, $this->pistol);
        $this->supervise($day, $this->exempt, $air);
        $this->register($this->licensed, SupervisionScope::Revoked);

        $this->actingAs(User::factory()->create())->get(route('shooting-days.show', $day))
            ->assertInertia(fn (Assert $page) => $page
                ->where('day.supervision', 'partial')
                ->where('day.supervisors', ['Ben Bogen'])
                ->has('supervisions', 1)
                ->where('supervisions.0.name', 'Ben Bogen')
                ->where('supervisions.0.not_counting', null));
    }

    public function test_supervision_outside_the_moved_day_does_not_count()
    {
        $day = $this->day($this->pistol);
        $this->supervise($day, $this->licensed, null, '17:00', '19:00');
        // Der Vorstand verschiebt den Tag; die Aufsicht von 17 bis 19 Uhr liegt dann außerhalb.
        $day->update(['starts_at' => '19:00', 'ends_at' => '22:00']);

        $this->assertShownWithoutCountingSupervision($day, 'outside');
    }

    public function test_supervision_for_a_range_taken_off_the_day_names_that_reason()
    {
        $air = Range::factory()->create(['name' => '10 m Luft', 'lanes' => 3, 'position' => 3, 'weapon_types' => [WeaponType::Airgun]]);
        $day = $this->day($this->pistol, $air);
        $this->supervise($day, $this->licensed, $this->pistol);
        $day->ranges()->detach($this->pistol->id);

        $this->assertShownWithoutCountingSupervision($day, 'removed');
    }
}
