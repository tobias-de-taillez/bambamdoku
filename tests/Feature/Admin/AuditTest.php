<?php

namespace Tests\Feature\Admin;

use App\Domain\Bookings;
use App\Domain\Sessions;
use App\Domain\TimeWindow;
use App\Enums\WeaponType;
use App\Models\AuditEntry;
use App\Models\Range;
use App\Models\ShootingDay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_lifecycle_is_recorded()
    {
        $this->travelTo(now()->setTime(17, 30));
        config(['verein.slot_minutes' => 60]);
        $range = Range::factory()->create();
        $day = ShootingDay::factory()->withRange($range)->create(['date' => today()->toDateString(), 'starts_at' => '17:00', 'ends_at' => '21:00']);
        $supervisor = User::factory()->create(['supervisor_scope' => 'licensed', 'supervisor_until' => now()->addYear(), 'name' => 'Sabine Aufsicht']);
        $member = User::factory()->create(['name' => 'Max Muster', 'adult_confirmed_at' => now()]);
        $bookings = app(Bookings::class);

        $bookings->assignSupervision($supervisor, $day, $supervisor, null, TimeWindow::fromStrings('17:00', '21:00'));
        $booking = $bookings->book($member, $day->fresh(), $range, TimeWindow::fromStrings('18:00', '19:00'), WeaponType::Pistol, shooter: $member);
        app(Sessions::class)->open($supervisor);
        $bookings->checkIn($supervisor, $booking->fresh());

        $this->assertSame(
            ['supervision.assigned', 'booking.created', 'session.opened', 'booking.checked_in'],
            AuditEntry::orderBy('id')->pluck('action')->all(),
        );
        $this->assertStringContainsString('Max Muster', AuditEntry::where('action', 'booking.checked_in')->sole()->summary);
        $this->assertSame($supervisor->id, AuditEntry::where('action', 'booking.checked_in')->sole()->actor_id);
    }

    public function test_admin_reads_and_searches_the_log()
    {
        $admin = User::factory()->admin()->create();
        AuditEntry::record($admin, 'user.created', null, 'Mitglied angelegt: Erika');
        AuditEntry::record($admin, 'user.created', null, 'Mitglied angelegt: Max');

        $this->actingAs($admin)->get(route('admin.audit.index', ['search' => 'Erika']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('admin/Audit')->has('entries.data', 1));
    }

    public function test_members_cannot_read_the_log()
    {
        $this->actingAs(User::factory()->create())->get(route('admin.audit.index'))->assertForbidden();
    }

    public function test_administrative_changes_are_recorded()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.ranges.store'), ['name' => '25 m', 'lanes' => 4, 'weapon_types' => ['kurzwaffe']]);
        $this->actingAs($admin)->post(route('admin.shooting-days.store'), [
            'date' => now()->addDay()->toDateString(),
            'starts_at' => '17:00',
            'ends_at' => '20:00',
            'ranges' => [Range::sole()->id],
        ]);

        $this->assertSame(
            ['range.created', 'shooting_day.created'],
            AuditEntry::orderBy('id')->pluck('action')->all(),
        );
    }
}
