<?php

namespace App\Http\Controllers;

use App\Domain\Schedule;
use App\Domain\Sessions;
use App\Enums\BookingStatus;
use App\Enums\SessionEntryStatus;
use App\Models\Booking;
use App\Models\Range;
use App\Models\ShootingDay;
use App\Models\Supervision;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, Sessions $sessions): Response
    {
        /** @var User $user */
        $user = $request->user();

        // Karte „Heute schießen“: sichtbar, sobald die Person heute aktiviert wurde.
        $participation = $sessions->latestParticipation($user);
        $participation?->load('session.supervisor:id,name');
        // Die Session nimmt nach Entzug oder Ablauf der Registerzeile nichts mehr an (V6).
        $unregistered = $participation !== null
            && $participation->session->isOpenToday()
            && ! $sessions->supervisorRegistered($participation->session);

        $nextDays = ShootingDay::query()
            ->upcoming()
            ->whereNull('cancelled_at')
            ->with(['ranges', 'supervisions.user', 'bookings'])
            ->limit(4)
            ->get()
            ->filter(fn (ShootingDay $day) => ! $day->isOver())
            ->values();

        $myBookings = Booking::query()
            ->where('user_id', $user->id)
            ->where('status', BookingStatus::Booked)
            ->whereHas('shootingDay', fn ($q) => $q->whereDate('date', '>=', today())->whereNull('cancelled_at'))
            ->with(['shootingDay', 'range'])
            ->get()
            ->filter(fn (Booking $b) => ! $b->shootingDay->isOver())
            ->sortBy(fn (Booking $b) => $b->shootingDay->date->toDateString().$b->starts_at)
            ->take(5)
            ->values();

        $myDuties = Supervision::query()
            ->where('user_id', $user->id)
            ->whereHas('shootingDay', fn ($q) => $q->whereDate('date', '>=', today())->whereNull('cancelled_at'))
            ->with(['shootingDay', 'range'])
            ->get()
            ->sortBy(fn (Supervision $s) => $s->shootingDay->date->toDateString().$s->starts_at)
            ->take(5)
            ->values();

        // Tage in den nächsten zwei Wochen, an denen noch Aufsicht fehlt.
        $needsSupervision = $user->canSupervise() || $user->isAdmin()
            ? ShootingDay::query()
                ->upcoming()
                ->whereNull('cancelled_at')
                ->whereDate('date', '<=', today()->addDays(14))
                ->with(['ranges', 'supervisions.user', 'bookings'])
                ->get()
                ->filter(function (ShootingDay $day) {
                    $schedule = new Schedule($day);

                    // Wie die Tagesseite: Ein Stand nur für Kurz- oder Langwaffe braucht eine Aufsicht
                    // mit Umfang erlaubnispflichtig, sonst fehlt dort noch Aufsicht (G9).
                    return ! $day->isOver() && ! $day->ranges->every(fn (Range $r) => $schedule->isSupervisedForRange($r, $schedule->window()));
                })
                ->values()
                ->map(fn (ShootingDay $day) => [
                    'id' => $day->id,
                    'date' => $day->date->toDateString(),
                    'starts_at' => substr($day->starts_at, 0, 5),
                    'ends_at' => substr($day->ends_at, 0, 5),
                    'title' => $day->title,
                    'has_any' => $day->supervisions->isNotEmpty(),
                ])
            : [];

        return Inertia::render('Dashboard', [
            'nextDays' => $nextDays->map(fn (ShootingDay $day) => [
                'id' => $day->id,
                'date' => $day->date->toDateString(),
                'starts_at' => substr($day->starts_at, 0, 5),
                'ends_at' => substr($day->ends_at, 0, 5),
                'title' => $day->title,
                // Wie die Tagesseite, sonst stünde ein Tag grün hier und zugleich unter „Aufsicht gesucht“.
                'supervision' => (new Schedule($day))->state(),
                'booked' => $day->bookings->filter(fn (Booking $b) => $b->status->occupiesLane())->count(),
            ]),
            'myBookings' => $myBookings->map(fn (Booking $b) => [
                'id' => $b->id,
                'shooting_day_id' => $b->shooting_day_id,
                'date' => $b->shootingDay->date->toDateString(),
                'starts_at' => substr($b->starts_at, 0, 5),
                'ends_at' => substr($b->ends_at, 0, 5),
                'range' => $b->range->name,
            ]),
            'myDuties' => $myDuties->map(fn (Supervision $s) => [
                'id' => $s->id,
                'shooting_day_id' => $s->shooting_day_id,
                'date' => $s->shootingDay->date->toDateString(),
                'starts_at' => substr($s->starts_at, 0, 5),
                'ends_at' => substr($s->ends_at, 0, 5),
                'range' => $s->range?->name,
            ]),
            'needsSupervision' => $needsSupervision,
            'today' => $participation ? [
                'open' => $participation->session->isOpenToday() && ! $unregistered,
                // Offen, aber die Aufsicht ist nicht mehr im Register: nicht zum Erfassen einladen (V6).
                'unregistered' => $unregistered,
                'supervisor' => $participation->session->supervisor->name,
                'pending' => $participation->entries()->where('status', SessionEntryStatus::Pending->value)->count(),
                'rejected' => $participation->entries()->where('status', SessionEntryStatus::Rejected->value)->count(),
            ] : null,
            'canSupervise' => $user->canSupervise(),
            'qualificationExpires' => $user->supervisor_until !== null
                && $user->supervisor_until->isFuture()
                && $user->supervisor_until->lte(today()->addDays(60))
                    ? $user->supervisor_until->toDateString()
                    : null,
            'expiringQualifications' => $user->isAdmin()
                ? User::query()
                    ->where('active', true)
                    ->whereDate('supervisor_until', '>=', today())
                    ->whereDate('supervisor_until', '<=', today()->addDays(60))
                    ->orderBy('supervisor_until')
                    ->get(['id', 'name', 'supervisor_until'])
                    ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name, 'until' => $u->supervisor_until?->toDateString()])
                : [],
        ]);
    }
}
