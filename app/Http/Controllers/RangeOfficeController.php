<?php

namespace App\Http\Controllers;

use App\Domain\Bookings;
use App\Domain\Schedule;
use App\Domain\TimeWindow;
use App\Enums\BookingStatus;
use App\Enums\WeaponType;
use App\Models\Booking;
use App\Models\Range;
use App\Models\ShootingDay;
use App\Models\Supervision;
use App\Models\User;
use App\Support\ArrivalCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Aufsichtsmodus: die Standansicht für den heutigen Schießtag.
 */
class RangeOfficeController extends Controller
{
    public function show(Request $request, Bookings $bookings): Response
    {
        /** @var User $user */
        $user = $request->user();

        $days = ShootingDay::query()
            ->whereDate('date', today())
            ->whereNull('cancelled_at')
            ->when(! $user->isAdmin(), fn ($q) => $q->whereHas('supervisions', fn ($q) => $q->where('user_id', $user->id)))
            ->orderBy('starts_at')
            ->get();

        $requested = $request->integer('tag');
        $day = $days->firstWhere('id', $requested)
            ?? $days->first(fn (ShootingDay $d) => ! $d->isOver())
            ?? $days->last();

        if (! $day instanceof ShootingDay) {
            return Inertia::render('range-office/Show', ['day' => null, 'days' => []]);
        }

        $day->load(['ranges', 'supervisions.user', 'bookings.user', 'bookings.host', 'bookings.range']);
        $schedule = new Schedule($day);
        $now = TimeWindow::minutes(now()->format('H:i'));

        $myRanges = $user->isAdmin()
            ? $day->ranges
            : $day->ranges->filter(fn (Range $range) => $day->supervisions->contains(fn (Supervision $s) => $s->user_id === $user->id && $s->covers($range)));

        $ranges = $myRanges->sortBy([['position', 'asc'], ['name', 'asc']])->values()->map(function (Range $range) use ($day, $schedule, $now, $user, $bookings) {
            $rangeBookings = $day->bookings
                ->each(fn (Booking $b) => $b->setRelation('shootingDay', $day))
                ->filter(fn (Booking $b) => $b->range_id === $range->id && $b->status !== BookingStatus::Cancelled)
                ->sortBy([['starts_at', 'asc'], ['id', 'asc']])
                ->values();

            $current = collect($schedule->slots())->first(fn (TimeWindow $slot) => $slot->start <= $now && $now < $slot->end);

            return [
                'id' => $range->id,
                'name' => $range->name,
                'lanes' => $range->lanes,
                'weapon_types' => $range->weapon_types->map->value->values(),
                'present' => $rangeBookings->filter(fn (Booking $b) => $b->status === BookingStatus::CheckedIn && $b->window()->start <= $now && $now < $b->window()->end)->count(),
                'free_now' => $current ? $schedule->freeLanes($range, $current) : null,
                'bookings' => $rangeBookings->map(fn (Booking $b) => [
                    'id' => $b->id,
                    'name' => $b->shooterName(),
                    'member_number' => $b->user?->member_number,
                    'guest' => $b->isGuest(),
                    'host' => $b->host?->name,
                    'starts_at' => substr($b->starts_at, 0, 5),
                    'ends_at' => substr($b->ends_at, 0, 5),
                    'weapon_type' => $b->weapon_type->value,
                    'club_weapon' => $b->club_weapon,
                    'status' => $b->status->value,
                    'arrived' => $b->arrived_at !== null,
                    'now' => $b->window()->start <= $now && $now < $b->window()->end,
                    'can_manage' => $bookings->canRunTheRange($user, $b),
                ])->values(),
            ];
        });

        $slots = array_map(fn (TimeWindow $slot) => ['start' => $slot->startString(), 'end' => $slot->endString()], $schedule->slots());

        return Inertia::render('range-office/Show', [
            'day' => [
                'id' => $day->id,
                'date' => $day->date->toDateString(),
                'starts_at' => substr($day->starts_at, 0, 5),
                'ends_at' => substr($day->ends_at, 0, 5),
                'title' => $day->title,
                'guests_allowed' => $day->guests_allowed,
                'supervisors' => $day->supervisions->map(fn (Supervision $s) => [
                    'name' => $s->user->name,
                    'starts_at' => substr($s->starts_at, 0, 5),
                    'ends_at' => substr($s->ends_at, 0, 5),
                    'range' => $s->range_id ? $day->ranges->firstWhere('id', $s->range_id)?->name : null,
                ])->values(),
            ],
            'days' => $days->map(fn (ShootingDay $d) => ['id' => $d->id, 'label' => substr($d->starts_at, 0, 5).'–'.substr($d->ends_at, 0, 5).($d->title ? ' '.$d->title : '')])->values(),
            'ranges' => $ranges,
            'slots' => $slots,
            'now' => now()->format('H:i'),
            'arrivalUrl' => route('range-office.arrive', ['shootingDay' => $day->id, 'code' => ArrivalCode::current($day)]),
            'members' => User::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'member_number']),
            'weaponTypes' => WeaponType::options(),
        ]);
    }

    public function walkIn(Request $request, ShootingDay $shootingDay, Bookings $bookings): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $data = $request->validate([
            'range_id' => ['required', 'integer', 'exists:ranges,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'required_without:guest_name'],
            'guest_name' => ['nullable', 'string', 'max:100', 'required_without:user_id'],
            'host_id' => ['nullable', 'integer', 'exists:users,id'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'weapon_type' => ['required', Rule::enum(WeaponType::class)],
            'club_weapon' => ['boolean'],
            'check_in' => ['boolean'],
        ], [
            'user_id.required_without' => __('Bitte ein Mitglied wählen oder einen Gast eintragen.'),
            'guest_name.required_without' => __('Bitte ein Mitglied wählen oder einen Gast eintragen.'),
        ], [
            'range_id' => __('Stand'),
            'weapon_type' => __('Waffenart'),
        ]);

        $range = Range::query()->findOrFail((int) $data['range_id']);
        $shootingDay->load('supervisions');
        $supervises = $shootingDay->supervisions->contains(fn (Supervision $s) => $s->user_id === $actor->id && $s->covers($range));
        abort_unless($actor->isAdmin() || ($supervises && $shootingDay->date->isToday()), 403);

        $shooter = isset($data['user_id']) ? User::query()->findOrFail((int) $data['user_id']) : null;
        $host = isset($data['host_id']) ? User::query()->findOrFail((int) $data['host_id']) : null;

        $booking = $bookings->book(
            actor: $actor,
            day: $shootingDay,
            range: $range,
            window: TimeWindow::fromStrings($data['starts_at'], $data['ends_at']),
            weaponType: WeaponType::from($data['weapon_type']),
            clubWeapon: (bool) ($data['club_weapon'] ?? false),
            shooter: $shooter,
            guestName: $shooter ? null : $data['guest_name'],
            host: $host,
        );

        if ($data['check_in'] ?? true) {
            $bookings->checkIn($actor, $booking->load(['shootingDay.supervisions', 'range']));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name eingetragen.', ['name' => $booking->shooterName()])]);

        return back();
    }

    public function checkIn(Request $request, Booking $booking, Bookings $bookings): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $bookings->checkIn($actor, $booking);

        return back();
    }

    public function noShow(Request $request, Booking $booking, Bookings $bookings): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $bookings->markNoShow($actor, $booking);

        return back();
    }

    public function reset(Request $request, Booking $booking, Bookings $bookings): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $bookings->reset($actor, $booking);

        return back();
    }
}
