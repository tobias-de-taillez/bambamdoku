<?php

namespace App\Http\Controllers;

use App\Domain\Bookings;
use App\Domain\TimeWindow;
use App\Enums\BookingStatus;
use App\Enums\WeaponType;
use App\Models\Booking;
use App\Models\Range;
use App\Models\ShootingDay;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function index(Request $request, Bookings $bookings): Response
    {
        /** @var User $user */
        $user = $request->user();

        $all = Booking::query()
            ->where(fn ($query) => $query->where('user_id', $user->id)->orWhere('host_id', $user->id))
            ->with(['shootingDay', 'range'])
            ->join('shooting_days', 'shooting_days.id', '=', 'bookings.shooting_day_id')
            ->select('bookings.*')
            ->orderByDesc('shooting_days.date')
            ->orderBy('bookings.starts_at')
            ->limit(200)
            ->get();

        $present = fn (Booking $b) => [
            'id' => $b->id,
            'shooting_day_id' => $b->shooting_day_id,
            'date' => $b->shootingDay->date->toDateString(),
            'range' => $b->range->name,
            'starts_at' => substr($b->starts_at, 0, 5),
            'ends_at' => substr($b->ends_at, 0, 5),
            'weapon_type' => $b->weapon_type->value,
            'club_weapon' => $b->club_weapon,
            'guest_name' => $b->guest_name,
            'status' => $b->status->value,
            'can_cancel' => $bookings->canCancel($user, $b),
        ];

        [$upcoming, $past] = $all->partition(fn (Booking $b) => ! $b->shootingDay->isOver() && $b->status !== BookingStatus::Cancelled);

        return Inertia::render('bookings/Index', [
            'upcoming' => $upcoming->sortBy(fn (Booking $b) => $b->shootingDay->date->toDateString().$b->starts_at)->values()->map($present),
            'past' => $past->values()->map($present),
        ]);
    }

    public function store(Request $request, ShootingDay $shootingDay, Bookings $bookings): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'range_id' => ['required', 'integer', 'exists:ranges,id'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'weapon_type' => ['required', Rule::enum(WeaponType::class)],
            'club_weapon' => ['boolean'],
            'guest_name' => ['nullable', 'string', 'max:100'],
        ], [], [
            'range_id' => __('Stand'),
            'starts_at' => __('Beginn'),
            'ends_at' => __('Ende'),
            'weapon_type' => __('Waffenart'),
            'guest_name' => __('Name des Gastes'),
        ]);

        $guestName = trim((string) ($data['guest_name'] ?? ''));

        $bookings->book(
            actor: $user,
            day: $shootingDay,
            range: Range::query()->findOrFail((int) $data['range_id']),
            window: TimeWindow::fromStrings($data['starts_at'], $data['ends_at']),
            weaponType: WeaponType::from($data['weapon_type']),
            clubWeapon: (bool) ($data['club_weapon'] ?? false),
            shooter: $guestName === '' ? $user : null,
            guestName: $guestName === '' ? null : $guestName,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => $guestName === ''
            ? __('Gebucht: :from–:to Uhr.', ['from' => $data['starts_at'], 'to' => $data['ends_at']])
            : __('Gast :name gebucht.', ['name' => $guestName])]);

        return back();
    }

    public function destroy(Request $request, Booking $booking, Bookings $bookings): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($user->isAdmin() || $booking->user_id === $user->id || $booking->host_id === $user->id || $bookings->supervises($user, $booking), 403);

        $bookings->cancel($user, $booking);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Buchung storniert.')]);

        return back();
    }
}
