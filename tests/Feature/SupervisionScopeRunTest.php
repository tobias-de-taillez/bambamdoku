<?php

namespace Tests\Feature;

use App\Domain\Bookings;
use App\Domain\Sessions;
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
 * Umfang der Aufsicht nach dem Einteilen (G9, zweite Runde): Eine Aufsicht zählt für eine Buchung
 * oder einen Stand nur, wenn sie den Stand abdeckt und den Umfang hat, den die Waffenart braucht.
 * Dieselbe Regel gilt in der Standansicht (/stand), beim Aktivieren, Markieren und Stornieren,
 * beim Austragen und in „Aufsicht gesucht“ auf der Übersicht.
 */
class SupervisionScopeRunTest extends TestCase
{
    use RefreshDatabase;

    private Bookings $bookings;

    private Range $pistol;

    private Range $air;

    private Range $mixed;

    private User $exempt;

    private User $licensed;

    protected function setUp(): void
    {
        parent::setUp();

        config(['verein.slot_minutes' => 60]);
        $this->travelTo(now()->setTime(17, 30));
        $this->bookings = app(Bookings::class);
        $this->pistol = Range::factory()->create(['name' => '25 m Kurzwaffe', 'lanes' => 3, 'position' => 1, 'weapon_types' => [WeaponType::Pistol]]);
        $this->air = Range::factory()->create(['name' => '10 m Luft', 'lanes' => 3, 'position' => 2, 'weapon_types' => [WeaponType::Airgun]]);
        $this->mixed = Range::factory()->create(['name' => 'Mehrzweckstand', 'lanes' => 3, 'position' => 3, 'weapon_types' => [WeaponType::Airgun, WeaponType::Pistol]]);
        $this->exempt = User::factory()->supervisor(SupervisionScope::Exempt)->create(['name' => 'Ben Bogen']);
        $this->licensed = User::factory()->supervisor()->create(['name' => 'Helga Schießwart']);
    }

    private function day(string $date, Range ...$ranges): ShootingDay
    {
        $day = ShootingDay::factory()->create(['date' => $date, 'starts_at' => '17:00', 'ends_at' => '21:00']);
        $day->ranges()->attach(collect($ranges)->pluck('id'));

        return $day->fresh();
    }

    private function today(Range ...$ranges): ShootingDay
    {
        return $this->day(today()->toDateString(), ...$ranges);
    }

    private function supervise(ShootingDay $day, User $supervisor, ?Range $range = null, string $from = '17:00', string $to = '21:00'): Supervision
    {
        return $this->bookings->assignSupervision($supervisor, $day->fresh(), $supervisor, $range, TimeWindow::fromStrings($from, $to));
    }

    private function book(ShootingDay $day, Range $range, WeaponType $type, string $from = '18:00', string $to = '19:00'): Booking
    {
        $member = User::factory()->create(['adult_confirmed_at' => now()]);

        return $this->bookings->book($member, $day->fresh(), $range, TimeWindow::fromStrings($from, $to), $type, shooter: $member);
    }

    private function downgrade(User $user): void
    {
        Qualification::register($user, SupervisionScope::Exempt, now()->addYear()->toDateString(), now()->toDateString(), User::factory()->admin()->create(), 'nur noch erlaubnisfrei');
    }

    // --- G9a: Standansicht und Rechte an der Buchung ---

    public function test_exempt_day_wide_supervisor_does_not_run_the_pure_pistol_range()
    {
        $day = $this->today($this->pistol, $this->air);
        $this->supervise($day, $this->exempt);
        $this->supervise($day, $this->licensed, $this->pistol);
        $pistol = $this->book($day, $this->pistol, WeaponType::Pistol);
        $air = $this->book($day, $this->air, WeaponType::Airgun);

        // Die Kurzwaffe-Buchung führt nur die Aufsicht mit Umfang erlaubnispflichtig.
        $this->assertFalse($this->bookings->canRunTheRange($this->exempt, $pistol->fresh()));
        $this->assertFalse($this->bookings->supervises($this->exempt, $pistol->fresh()));
        $this->assertFalse($this->bookings->canCancel($this->exempt, $pistol->fresh()));
        $this->assertTrue($this->bookings->canRunTheRange($this->licensed, $pistol->fresh()));
        $this->assertTrue($this->bookings->supervises($this->licensed, $pistol->fresh()));
        $this->assertTrue($this->bookings->canCancel($this->licensed, $pistol->fresh()));

        // Luftdruck bleibt bei der Aufsicht für die ganze Anlage; die Standaufsicht Kurzwaffe hat dort nichts.
        $this->assertTrue($this->bookings->canRunTheRange($this->exempt, $air->fresh()));
        $this->assertFalse($this->bookings->canRunTheRange($this->licensed, $air->fresh()));

        $this->actingAs($this->exempt)->get(route('range-office.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('ranges', 1)
                ->where('ranges.0.name', '10 m Luft')
                ->where('ranges.0.bookings.0.can_manage', true));

        $this->actingAs($this->licensed)->get(route('range-office.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('ranges', 1)
                ->where('ranges.0.name', '25 m Kurzwaffe')
                ->where('ranges.0.bookings.0.can_manage', true));
    }

    public function test_exempt_supervisor_cannot_activate_mark_or_cancel_a_pistol_booking_over_http()
    {
        $day = $this->today($this->pistol, $this->air);
        $this->supervise($day, $this->exempt);
        $this->supervise($day, $this->licensed, $this->pistol);
        $pistol = $this->book($day, $this->pistol, WeaponType::Pistol);
        $air = $this->book($day, $this->air, WeaponType::Airgun);

        $this->actingAs($this->exempt)->post(route('range-office.session.start'))->assertSessionHasNoErrors();

        $this->actingAs($this->exempt)->post(route('range-office.check-in', $pistol), ['adult_confirmed' => true])->assertForbidden();
        $this->actingAs($this->exempt)->post(route('range-office.no-show', $pistol))->assertForbidden();
        $this->actingAs($this->exempt)->delete(route('bookings.destroy', $pistol))->assertForbidden();
        $this->assertSame(BookingStatus::Booked, $pistol->fresh()->status);

        // Die eigene Luftdruck-Buchung führt sie weiter.
        $this->actingAs($this->exempt)->post(route('range-office.no-show', $air))->assertSessionHasNoErrors();
        $this->assertSame(BookingStatus::NoShow, $air->fresh()->status);

        // Die zuständige Aufsicht mit Umfang erlaubnispflichtig behält ihre Buchung.
        $this->actingAs($this->licensed)->post(route('range-office.no-show', $pistol))->assertSessionHasNoErrors();
        $this->assertSame(BookingStatus::NoShow, $pistol->fresh()->status);
    }

    public function test_on_a_mixed_range_exempt_supervisor_manages_airgun_but_not_pistol()
    {
        $day = $this->today($this->mixed);
        $this->supervise($day, $this->exempt);
        $this->supervise($day, $this->licensed, $this->mixed);
        $pistol = $this->book($day, $this->mixed, WeaponType::Pistol);
        $air = $this->book($day, $this->mixed, WeaponType::Airgun);

        $this->actingAs($this->exempt)->get(route('range-office.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('ranges', 1)
                ->where('ranges.0.name', 'Mehrzweckstand')
                ->has('ranges.0.bookings', 2)
                ->where('ranges.0.bookings.0.id', $pistol->id)
                ->where('ranges.0.bookings.0.can_manage', false)
                ->where('ranges.0.bookings.1.id', $air->id)
                ->where('ranges.0.bookings.1.can_manage', true));

        $this->actingAs($this->licensed)->get(route('range-office.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('ranges.0.bookings.0.can_manage', true)
                ->where('ranges.0.bookings.1.can_manage', true));
    }

    public function test_downgraded_supervisor_no_longer_runs_the_pistol_range()
    {
        $day = $this->today($this->pistol);
        $this->supervise($day, $this->licensed, $this->pistol);
        $booking = $this->book($day, $this->pistol, WeaponType::Pistol);

        $this->downgrade($this->licensed);

        $this->assertFalse($this->bookings->canRunTheRange($this->licensed->fresh(), $booking->fresh()));
        $this->actingAs($this->licensed->fresh())->get(route('range-office.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('day.id', $day->id)->has('ranges', 0));
        $this->actingAs($this->licensed->fresh())->post(route('range-office.no-show', $booking))->assertForbidden();
    }

    // --- G9b: Austragen zählt nur Aufsichten, die für die Buchung zählen ---

    public function test_exempt_supervision_can_be_removed_when_it_never_counted_for_the_pistol_booking()
    {
        $day = $this->day(now()->addDays(2)->toDateString(), $this->mixed);
        $exempt = $this->supervise($day, $this->exempt);
        $this->supervise($day, $this->licensed, $this->mixed);
        $this->book($day, $this->mixed, WeaponType::Pistol);

        $this->downgrade($this->licensed);

        $this->actingAs($this->exempt)->delete(route('supervisions.destroy', $exempt))->assertSessionHasNoErrors();
        $this->assertModelMissing($exempt);
    }

    public function test_removal_is_still_refused_where_the_supervision_counts()
    {
        $day = $this->day(now()->addDays(2)->toDateString(), $this->mixed);
        $exempt = $this->supervise($day, $this->exempt);
        $this->book($day, $this->mixed, WeaponType::Airgun);

        try {
            $this->bookings->removeSupervision($this->exempt, $exempt);
            $this->fail('Das Austragen wurde nicht abgewiesen.');
        } catch (ValidationException $e) {
            $this->assertSame('Eine Buchung wäre dann ohne Aufsicht. Bitte zuerst eine Vertretung eintragen.', $e->errors()['supervision'][0]);
        }

        $this->assertModelExists($exempt);
    }

    public function test_a_supervision_that_no_longer_counts_does_not_block_but_the_one_that_counts_does()
    {
        $other = User::factory()->supervisor()->create(['name' => 'Otto Ober']);
        $day = $this->day(now()->addDays(2)->toDateString(), $this->pistol);
        $first = $this->supervise($day, $this->licensed);
        $second = $this->supervise($day, $other);
        $this->book($day, $this->pistol, WeaponType::Pistol);

        Qualification::register($this->licensed, SupervisionScope::Revoked, null, now()->toDateString(), User::factory()->admin()->create(), 'entzogen');

        try {
            $this->bookings->removeSupervision($other, $second);
            $this->fail('Das Austragen wurde nicht abgewiesen.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('supervision', $e->errors());
        }

        $this->bookings->removeSupervision($this->licensed, $first);
        $this->assertModelMissing($first);
        $this->assertModelExists($second);
    }

    // --- G9c: „Aufsicht gesucht“ auf der Übersicht wie die Tagesseite ---

    public function test_dashboard_asks_for_supervision_when_the_pistol_range_has_only_exempt_coverage()
    {
        $day = $this->day(now()->addDays(2)->toDateString(), $this->pistol, $this->air);
        $this->supervise($day, $this->exempt);
        $reader = User::factory()->supervisor()->create();

        $this->actingAs($reader)->get(route('shooting-days.show', $day))
            ->assertInertia(fn (Assert $page) => $page->where('day.supervision', 'partial'));
        $this->actingAs($reader)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('needsSupervision', 1)
                ->where('needsSupervision.0.id', $day->id)
                ->where('needsSupervision.0.has_any', true)
                // Die Liste der nächsten Tage zeigt denselben Stand wie die Tagesseite, nicht grün.
                ->where('nextDays.0.id', $day->id)
                ->where('nextDays.0.supervision', 'partial'));

        $this->supervise($day, $this->licensed, $this->pistol);

        $this->actingAs($reader)->get(route('shooting-days.show', $day))
            ->assertInertia(fn (Assert $page) => $page->where('day.supervision', 'full'));
        $this->actingAs($reader)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('needsSupervision', 0)
                ->where('nextDays.0.supervision', 'full'));
    }

    // --- G9d: „Aufsicht gesucht“ nur mit Aufsichten, die zählen, und nur, wo man einspringen kann ---

    /**
     * @return list<int>
     */
    private function askedFor(User $user): array
    {
        $ids = [];

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$ids) {
                $ids = collect($page->toArray()['props']['needsSupervision'])->pluck('id')->all();
            });

        return $ids;
    }

    public function test_dashboard_says_nobody_when_the_only_supervisor_no_longer_counts()
    {
        $day = $this->day(now()->addDays(2)->toDateString(), $this->pistol);
        $this->supervise($day, $this->licensed);
        $this->downgrade($this->licensed);
        $reader = User::factory()->supervisor()->create();

        $this->actingAs($reader)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('needsSupervision', 1)
                ->where('needsSupervision.0.id', $day->id)
                // „(niemand)“ statt „(teilweise besetzt)“: Kein Slot ist buchbar.
                ->where('needsSupervision.0.has_any', false)
                ->where('nextDays.0.id', $day->id)
                ->where('nextDays.0.supervision', 'none'));
    }

    public function test_inactive_range_does_not_put_the_day_under_needs_supervision()
    {
        $this->pistol->forceFill(['active' => false])->save();
        $day = $this->day(now()->addDays(2)->toDateString(), $this->air, $this->pistol);
        $this->supervise($day, $this->exempt);

        $this->actingAs($this->licensed)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('needsSupervision', 0)
                ->where('nextDays.0.id', $day->id)
                ->where('nextDays.0.supervision', 'full'));
    }

    public function test_day_without_active_ranges_still_asks_for_supervision()
    {
        $this->air->forceFill(['active' => false])->save();
        $day = $this->day(now()->addDays(2)->toDateString(), $this->air);

        $this->assertSame([$day->id], $this->askedFor($this->licensed));
        $this->assertSame([$day->id], $this->askedFor($this->exempt));
    }

    public function test_exempt_supervisor_is_only_asked_where_the_own_scope_can_step_in()
    {
        $pistolDay = $this->day(now()->addDays(2)->toDateString(), $this->pistol);
        $mixedDay = $this->day(now()->addDays(3)->toDateString(), $this->pistol, $this->air);
        // Luftdruck besetzt, es fehlt nur der reine Kurzwaffenstand.
        $airCovered = $this->day(now()->addDays(4)->toDateString(), $this->pistol, $this->air);
        $this->supervise($airCovered, User::factory()->supervisor(SupervisionScope::Exempt)->create(), $this->air);

        $this->assertSame([$mixedDay->id], $this->askedFor($this->exempt));
        $this->assertSame([$pistolDay->id, $mixedDay->id, $airCovered->id], $this->askedFor($this->licensed));

        // Der Vorstand teilt auch andere ein und sieht weiter alle Tage, auch nur erlaubnisfrei registriert.
        $this->assertSame([$pistolDay->id, $mixedDay->id, $airCovered->id], $this->askedFor(User::factory()->admin()->create()));
        $this->assertSame([$pistolDay->id, $mixedDay->id, $airCovered->id], $this->askedFor(User::factory()->admin()->supervisor(SupervisionScope::Exempt)->create()));
    }

    // --- Karte „Heute schießen“ nach Entzug (V6) ---

    public function test_today_card_does_not_invite_when_the_session_supervisor_is_no_longer_registered()
    {
        $this->travelTo(now()->setTime(18, 0));
        $sessions = app(Sessions::class);
        $member = User::factory()->create(['adult_confirmed_at' => now()]);
        $session = $sessions->open($this->licensed);
        $sessions->addMember($this->licensed, $session, $member);

        $this->actingAs($member)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('today.open', true)->where('today.unregistered', false));

        Qualification::register($this->licensed, SupervisionScope::Revoked, null, now()->toDateString(), User::factory()->admin()->create(), 'entzogen');

        $this->actingAs($member)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('today.open', false)
                ->where('today.unregistered', true)
                ->where('today.supervisor', 'Helga Schießwart'));

        Qualification::register($this->licensed, SupervisionScope::Licensed, now()->addYear()->toDateString(), now()->toDateString(), User::factory()->admin()->create());

        $this->actingAs($member)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('today.open', true)->where('today.unregistered', false));

        $sessions->signOff($this->licensed->fresh(), $session, []);

        $this->actingAs($member)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('today.open', false)->where('today.unregistered', false));
    }
}
