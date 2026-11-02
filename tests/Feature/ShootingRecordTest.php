<?php

namespace Tests\Feature;

use App\Domain\ShootingRecord;
use App\Enums\BookingStatus;
use App\Enums\WeaponType;
use App\Models\Booking;
use App\Models\Range;
use App\Models\ShootingDay;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShootingRecordTest extends TestCase
{
    use RefreshDatabase;

    private Range $range;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00'));
        $this->range = Range::factory()->create(['weapon_types' => [WeaponType::Pistol, WeaponType::Rifle]]);
        $this->member = User::factory()->create();
    }

    private function visit(string $date, WeaponType $type = WeaponType::Pistol, bool $club = false, BookingStatus $status = BookingStatus::CheckedIn, ?User $user = null): Booking
    {
        $day = ShootingDay::firstOrCreate(['date' => $date], ['starts_at' => '17:00', 'ends_at' => '21:00']);

        $booking = Booking::factory()->create([
            'shooting_day_id' => $day->id,
            'range_id' => $this->range->id,
            'user_id' => ($user ?? $this->member)->id,
            'weapon_type' => $type,
            'club_weapon' => $club,
        ]);
        $booking->forceFill(['status' => $status])->save();

        return $booking;
    }

    public function test_only_checked_in_visits_count()
    {
        $this->visit('2026-10-01');
        $this->visit('2026-10-08', status: BookingStatus::Booked);
        $this->visit('2026-10-15', status: BookingStatus::NoShow);
        $this->visit('2026-10-22', user: User::factory()->create());

        $this->assertCount(1, ShootingRecord::for($this->member)->entries());
    }

    public function test_acquisition_every_month()
    {
        // 12 volle Monate: November 2025 bis Oktober 2026
        foreach (range(0, 11) as $i) {
            $this->visit(CarbonImmutable::parse('2025-11-10')->addMonths($i)->toDateString());
        }

        $check = ShootingRecord::for($this->member)->acquisitionCheck();

        $this->assertSame(12, $check['months_covered']);
        $this->assertTrue($check['every_month']);
        $this->assertTrue($check['fulfilled']);
    }

    public function test_acquisition_eighteen_times_without_every_month()
    {
        foreach (range(1, 18) as $i) {
            $this->visit(CarbonImmutable::parse('2026-03-01')->addDays($i * 3)->toDateString());
        }

        $check = ShootingRecord::for($this->member)->acquisitionCheck();

        $this->assertFalse($check['every_month']);
        $this->assertSame(18, $check['total']);
        $this->assertTrue($check['fulfilled']);
    }

    public function test_acquisition_counts_a_day_once_and_ignores_airguns()
    {
        $this->visit('2026-10-01');
        $this->visit('2026-10-01', WeaponType::Rifle);
        $this->visit('2026-10-02', WeaponType::Airgun);

        $this->assertSame(1, ShootingRecord::for($this->member)->acquisitionCheck()['total']);
    }

    public function test_possession_six_times_in_twelve_months_with_own_weapon()
    {
        foreach (['2025-02-01', '2025-03-01', '2025-04-01', '2025-05-01', '2025-06-01', '2025-07-01'] as $date) {
            $this->visit($date);
        }
        $this->visit('2025-08-01', club: true);

        [$pistol, $rifle] = ShootingRecord::for($this->member)->possessionCheck();

        $this->assertSame(6, $pistol['best_twelve_months']);
        $this->assertTrue($pistol['fulfilled']);
        $this->assertFalse($rifle['fulfilled']);
        $this->assertSame(0, $rifle['total']);
    }

    public function test_possession_every_three_months()
    {
        foreach (range(0, 7) as $i) {
            $this->visit(CarbonImmutable::parse('2024-12-01')->addMonths($i * 3)->toDateString(), WeaponType::Rifle);
        }

        $rifle = ShootingRecord::for($this->member)->possessionCheck()[1];

        $this->assertSame(8, $rifle['quarters_covered']);
        $this->assertTrue($rifle['every_quarter']);
    }

    public function test_club_weapons_do_not_count_for_possession()
    {
        foreach (range(1, 10) as $i) {
            $this->visit(CarbonImmutable::parse('2026-01-01')->addDays($i * 7)->toDateString(), club: true);
        }

        $this->assertFalse(ShootingRecord::for($this->member)->possessionCheck()[0]['fulfilled']);
        $this->assertSame(10, ShootingRecord::for($this->member)->acquisitionCheck()['total']);
    }

    public function test_member_sees_own_record_page()
    {
        $this->visit('2026-10-01');

        $this->actingAs($this->member)->get(route('record.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('record/Index')->has('entries', 1)->has('possession', 2));
    }

    public function test_member_downloads_pdf_extract()
    {
        $this->visit('2026-10-01');

        $response = $this->actingAs($this->member)->get(route('record.pdf'));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_members_cannot_open_the_club_record()
    {
        $this->actingAs($this->member)->get(route('admin.record.index'))->assertForbidden();
        $this->actingAs($this->member)->get(route('admin.record.member-pdf', User::factory()->create()))->assertForbidden();
    }

    public function test_admin_sees_all_visits_including_guests()
    {
        $this->visit('2026-11-02');
        $guest = $this->visit('2026-11-03');
        $guest->forceFill(['user_id' => null, 'guest_name' => 'Gustav Gast', 'host_id' => $this->member->id])->save();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.record.index', ['from' => '2026-11-01', 'to' => '2026-11-15']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('entries', 2)->where('entries.0.guest', true));
    }

    public function test_admin_downloads_membership_certificate()
    {
        $this->member->forceFill(['member_since' => '2010-04-01'])->save();

        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.record.certificate', $this->member));

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
