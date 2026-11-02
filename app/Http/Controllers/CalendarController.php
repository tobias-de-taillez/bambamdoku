<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Supervision;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Kalender-Abo (iCalendar) mit eigenen Buchungen und Aufsichtsdiensten.
 */
class CalendarController extends Controller
{
    public function feed(string $token): Response
    {
        $user = User::query()->where('calendar_token', $token)->where('active', true)->firstOrFail();
        $since = today()->subDays(30);

        $bookings = Booking::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [BookingStatus::Booked, BookingStatus::CheckedIn])
            ->whereHas('shootingDay', fn ($q) => $q->whereDate('date', '>=', $since)->whereNull('cancelled_at'))
            ->with(['shootingDay', 'range'])
            ->get();

        $duties = Supervision::query()
            ->where('user_id', $user->id)
            ->whereHas('shootingDay', fn ($q) => $q->whereDate('date', '>=', $since)->whereNull('cancelled_at'))
            ->with(['shootingDay', 'range'])
            ->get();

        $club = (string) config('verein.name');
        $events = [];

        foreach ($bookings as $b) {
            $events[] = $this->event(
                "booking-{$b->id}",
                $b->shootingDay->date->setTimeFromTimeString($b->starts_at),
                $b->shootingDay->date->setTimeFromTimeString($b->ends_at),
                sprintf('Schießen: %s', $b->range->name),
                $club,
                route('shooting-days.show', $b->shooting_day_id),
            );
        }

        foreach ($duties as $s) {
            $events[] = $this->event(
                "supervision-{$s->id}",
                $s->shootingDay->date->setTimeFromTimeString($s->starts_at),
                $s->shootingDay->date->setTimeFromTimeString($s->ends_at),
                sprintf('Standaufsicht: %s', $s->range->name ?? 'ganze Anlage'),
                $club,
                route('shooting-days.show', $s->shooting_day_id),
            );
        }

        $body = implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//bambamdoku//DE',
            'CALSCALE:GREGORIAN',
            'X-WR-CALNAME:'.$this->escape($club),
            ...$events,
            'END:VCALENDAR',
        ])."\r\n";

        return response($body, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="schiesstermine.ics"',
        ]);
    }

    public function regenerate(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->forceFill(['calendar_token' => Str::random(48)])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Neuer Kalenderlink erstellt. Der alte funktioniert nicht mehr.')]);

        return back();
    }

    public static function url(User $user): string
    {
        if ($user->calendar_token === null) {
            $user->forceFill(['calendar_token' => Str::random(48)])->save();
        }

        return route('calendar.feed', ['token' => $user->calendar_token]);
    }

    private function event(string $uid, CarbonImmutable $start, CarbonImmutable $end, string $summary, string $location, string $url): string
    {
        $utc = fn (CarbonImmutable $time) => $time->utc()->format('Ymd\THis\Z');

        return implode("\r\n", [
            'BEGIN:VEVENT',
            'UID:'.$uid.'@'.parse_url((string) config('app.url'), PHP_URL_HOST),
            'DTSTAMP:'.$utc(CarbonImmutable::now()),
            'DTSTART:'.$utc($start),
            'DTEND:'.$utc($end),
            'SUMMARY:'.$this->escape($summary),
            'LOCATION:'.$this->escape($location),
            'URL:'.$url,
            'END:VEVENT',
        ]);
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\;', '\\,', '\\n'], $value);
    }
}
