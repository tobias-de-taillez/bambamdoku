<?php

namespace App\Http\Controllers;

use App\Domain\Bookings;
use App\Domain\Disciplines;
use App\Domain\Logbook;
use App\Domain\Schedule;
use App\Domain\TimeWindow;
use App\Enums\BookingStatus;
use App\Enums\WeaponClass;
use App\Enums\WeaponOwner;
use App\Models\Booking;
use App\Models\LogEntry;
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
            return Inertia::render('range-office/Show', [
                'day' => null,
                'days' => [],
                ...$this->entering($user),
            ]);
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
            ...$this->entering($user),
        ]);
    }

    /**
     * Was die Ansicht zum Eintragen braucht: Berechtigung, Auswahllisten und die heutigen Einträge.
     *
     * @return array<string, mixed>
     */
    private function entering(User $user): array
    {
        $today = today();

        $entries = LogEntry::query()
            ->whereDate('shot_on', $today)
            ->when(! $user->isAdmin(), fn ($q) => $q->where('recorded_by_id', $user->id))
            ->with(['user:id,name,member_number', 'recordedBy:id,name'])
            ->latest('id')
            ->get();

        // Vorbelegung je Mitglied aus dessen letztem Eintrag (K3).
        $last = LogEntry::query()
            ->active()
            ->whereNotNull('user_id')
            ->whereIn('id', LogEntry::query()->selectRaw('max(id)')->whereNotNull('user_id')->groupBy('user_id'))
            ->get(['user_id', 'weapon_class', 'weapon_owner', 'discipline'])
            ->keyBy('user_id');

        return [
            'canEnter' => $user->canSupervise($today),
            'canEnterLicensed' => $user->canSupervise($today, licensed: true),
            'members' => User::query()
                ->where('active', true)
                ->whereKeyNot($user->id)
                ->orderBy('name')
                ->get(['id', 'name', 'member_number'])
                ->map(fn (User $m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'member_number' => $m->member_number,
                    'last' => isset($last[$m->id]) ? [
                        'weapon_class' => $last[$m->id]->weapon_class->value,
                        'weapon_owner' => $last[$m->id]->weapon_owner?->value,
                        'discipline' => $last[$m->id]->discipline,
                    ] : null,
                ]),
            'weaponClasses' => WeaponClass::options(),
            'disciplines' => Disciplines::options(),
            'todayEntries' => $entries->map(fn (LogEntry $e) => [
                'id' => $e->id,
                'name' => $e->shooterName(),
                'member_number' => $e->user?->member_number,
                'guest' => $e->user_id === null,
                'weapon_class' => $e->weapon_class->label(),
                'weapon_owner' => $e->weapon_owner?->label(),
                'discipline' => Disciplines::label($e->discipline),
                'recorded_by' => $e->recordedBy->name,
                'time' => $e->created_at->format('H:i'),
                'active' => $e->isActive(),
                'cancel_reason' => $e->cancel_reason,
                'can_cancel' => $e->isActive() && ($user->isAdmin() || $e->recorded_by_id === $user->id),
            ]),
        ];
    }

    /**
     * Eintragen am Stand ohne Buchung (K1): Mitglied per Name oder Mitgliedsnummer, oder Gast.
     */
    public function enter(Request $request, Logbook $logbook): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'required_without:guest_name'],
            'guest_name' => ['nullable', 'string', 'max:100', 'required_without:user_id'],
            'guest_adult' => ['boolean'],
            'weapon_class' => ['required', Rule::enum(WeaponClass::class)],
            'weapon_owner' => ['nullable', Rule::enum(WeaponOwner::class)],
            'discipline' => ['nullable', 'string', 'max:50'],
        ], [
            'user_id.required_without' => __('Bitte ein Mitglied wählen oder einen Gast eintragen.'),
            'guest_name.required_without' => __('Bitte ein Mitglied wählen oder einen Gast eintragen.'),
        ], [
            'weapon_class' => __('Kategorie'),
            'weapon_owner' => __('Waffe'),
        ]);

        $member = isset($data['user_id']) ? User::query()->findOrFail((int) $data['user_id']) : null;

        $result = $logbook->record(
            actor: $actor,
            member: $member,
            guestName: $data['guest_name'] ?? null,
            class: WeaponClass::from($data['weapon_class']),
            owner: isset($data['weapon_owner']) ? WeaponOwner::from($data['weapon_owner']) : null,
            discipline: $data['discipline'] ?? null,
            guestIsAdult: (bool) ($data['guest_adult'] ?? false),
        );

        $name = $result['entry']->shooterName();
        Inertia::flash('toast', ['type' => 'success', 'message' => $result['duplicate']
            ? __(':name ist heute schon eingetragen.', ['name' => $name])
            : __(':name eingetragen, :time Uhr.', ['name' => $name, 'time' => now()->format('H:i')])]);

        return back();
    }

    public function cancelEntry(Request $request, LogEntry $entry, Logbook $logbook): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], [], ['reason' => __('Grund')]);

        $logbook->cancel($actor, $entry, $data['reason']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Eintrag storniert.')]);

        return back();
    }

    public function checkIn(Request $request, Booking $booking, Bookings $bookings): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $data = $request->validate([
            'discipline' => ['nullable', 'string', 'max:50'],
            'guest_adult' => ['boolean'],
        ]);

        $bookings->checkIn($actor, $booking, $data['discipline'] ?? null, (bool) ($data['guest_adult'] ?? false));

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
