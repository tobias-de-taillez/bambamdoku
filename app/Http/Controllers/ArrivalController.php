<?php

namespace App\Http\Controllers;

use App\Domain\TimeWindow;
use App\Enums\BookingStatus;
use App\Models\AuditEntry;
use App\Models\Booking;
use App\Models\ShootingDay;
use App\Models\User;
use App\Support\ArrivalCode;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Selbst-Check-in: Das Mitglied scannt am Stand den QR-Code der Aufsicht und meldet sich an.
 * Bestätigt wird weiterhin von der Aufsicht (erst dann zählt der Besuch für die Kladde).
 */
class ArrivalController extends Controller
{
    public function __invoke(Request $request, ShootingDay $shootingDay, string $code): Response
    {
        /** @var User $user */
        $user = $request->user();

        if (! $shootingDay->date->isToday() || ! ArrivalCode::isValid($shootingDay, $code)) {
            return $this->result('expired');
        }

        $now = TimeWindow::minutes(now()->format('H:i'));

        // Buchung, die gerade läuft oder in der nächsten halben Stunde beginnt.
        $booking = $shootingDay->bookings()
            ->where('user_id', $user->id)
            ->whereIn('status', [BookingStatus::Booked, BookingStatus::CheckedIn])
            ->with('range')
            ->get()
            ->filter(fn (Booking $b) => $b->window()->start - 30 <= $now && $now < $b->window()->end)
            ->sortBy('starts_at')
            ->first();

        if (! $booking instanceof Booking) {
            return $this->result('no_booking');
        }

        if ($booking->status === BookingStatus::Booked && $booking->arrived_at === null) {
            $booking->forceFill(['arrived_at' => now()])->save();
            AuditEntry::record($user, 'booking.arrived', $booking, sprintf('Am Stand gemeldet (QR): %s, %s–%s Uhr', $user->name, substr($booking->starts_at, 0, 5), substr($booking->ends_at, 0, 5)));
        }

        return $this->result($booking->status === BookingStatus::CheckedIn ? 'confirmed' : 'arrived', $booking);
    }

    private function result(string $state, ?Booking $booking = null): Response
    {
        return Inertia::render('range-office/Arrived', [
            'state' => $state,
            'booking' => $booking ? [
                'range' => $booking->range->name,
                'starts_at' => substr($booking->starts_at, 0, 5),
                'ends_at' => substr($booking->ends_at, 0, 5),
            ] : null,
        ]);
    }
}
