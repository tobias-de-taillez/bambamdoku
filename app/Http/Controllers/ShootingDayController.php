<?php

namespace App\Http\Controllers;

use App\Domain\Bookings;
use App\Domain\Schedule;
use App\Enums\BookingStatus;
use App\Enums\WeaponType;
use App\Models\Booking;
use App\Models\Range;
use App\Models\ShootingDay;
use App\Models\Supervision;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShootingDayController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $days = ShootingDay::query()
            ->upcoming()
            ->whereDate('date', '<=', today()->addDays((int) config('verein.booking_horizon_days')))
            ->with(['ranges', 'supervisions.user', 'bookings'])
            ->limit(60)
            ->get()
            ->map(fn (ShootingDay $day) => $this->summary($day, $user));

        return Inertia::render('shooting-days/Index', ['days' => $days]);
    }

    public function show(Request $request, ShootingDay $shootingDay, Bookings $bookings): Response
    {
        /** @var User $user */
        $user = $request->user();
        $shootingDay->load(['ranges', 'supervisions.user', 'supervisions.range', 'bookings.range', 'bookings.user']);
        $schedule = new Schedule($shootingDay);

        $mine = $shootingDay->bookings
            ->filter(fn (Booking $b) => ($b->user_id === $user->id || $b->host_id === $user->id) && $b->status !== BookingStatus::Cancelled)
            ->sortBy('starts_at')
            ->values()
            ->map(fn (Booking $b) => [
                'id' => $b->id,
                'range' => $b->range->name,
                'starts_at' => substr($b->starts_at, 0, 5),
                'ends_at' => substr($b->ends_at, 0, 5),
                'weapon_type' => $b->weapon_type->value,
                'club_weapon' => $b->club_weapon,
                'guest_name' => $b->guest_name,
                'status' => $b->status->value,
                'can_cancel' => $bookings->canCancel($user, $b),
            ]);

        return Inertia::render('shooting-days/Show', [
            'day' => [
                ...$this->summary($shootingDay, $user),
                'notes' => $shootingDay->notes,
            ],
            'grid' => $schedule->grid(),
            'slotMinutes' => Schedule::slotMinutes(),
            'supervisions' => $shootingDay->supervisions
                ->sortBy('starts_at')
                ->values()
                ->map(fn (Supervision $s) => [
                    'id' => $s->id,
                    'name' => $s->user->name,
                    'range' => $s->range?->name,
                    'starts_at' => substr($s->starts_at, 0, 5),
                    'ends_at' => substr($s->ends_at, 0, 5),
                    'mine' => $s->user_id === $user->id,
                    'can_remove' => $user->isAdmin() || $s->user_id === $user->id,
                ]),
            'myBookings' => $mine,
            // licensed: Kurz- und Langwaffe sind nur unter einer Aufsicht mit Umfang erlaubnispflichtig buchbar.
            'weaponTypes' => array_map(fn (WeaponType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'licensed' => $type->requiresLicense(),
            ], WeaponType::cases()),
            'canSupervise' => $user->canSupervise($shootingDay->date),
            'supervisorCandidates' => $user->isAdmin()
                ? User::query()->where('active', true)->whereDate('supervisor_until', '>=', $shootingDay->date)->orderBy('name')->get(['id', 'name'])
                : [],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(ShootingDay $day, User $user): array
    {
        $schedule = new Schedule($day);
        $window = $schedule->window();
        $active = $day->bookings->filter(fn (Booking $b) => $b->status->occupiesLane());

        return [
            'id' => $day->id,
            'date' => $day->date->toDateString(),
            'starts_at' => $window->startString(),
            'ends_at' => $window->endString(),
            'title' => $day->title,
            'cancelled' => $day->isCancelled(),
            'cancel_reason' => $day->cancel_reason,
            'guests_allowed' => $day->guests_allowed,
            'over' => $day->isOver(),
            'ranges' => $day->ranges->map(fn (Range $range) => $range->name)->values(),
            'capacity' => $day->ranges->sum('lanes'),
            'booked' => $active->count(),
            'supervision' => $schedule->state(),
            'supervisors' => $day->supervisions->map(fn (Supervision $s) => $s->user->name)->unique()->values(),
            'my_booking_count' => $active->filter(fn (Booking $b) => $b->user_id === $user->id)->count(),
            'i_supervise' => $day->supervisions->contains('user_id', $user->id),
        ];
    }
}
