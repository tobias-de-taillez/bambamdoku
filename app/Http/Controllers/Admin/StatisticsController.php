<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Enums\WeaponClass;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\LogEntry;
use App\Models\ShootingDay;
use App\Models\Supervision;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StatisticsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $year = max(2000, min((int) $request->query('jahr', (string) now()->year), now()->year + 1));
        $from = CarbonImmutable::create($year)->startOfYear();
        $to = $from->endOfYear();

        $bookings = Booking::query()
            ->whereHas('shootingDay', fn ($q) => $q->whereDate('date', '>=', $from)->whereDate('date', '<=', $to))
            ->get(['id', 'status']);

        // Besuche = unterschiedliche Paare aus Person und Tag in den gültigen Kladde-Einträgen
        // (nicht Buchungen, nicht Zeilen: zwei Disziplinen am selben Tag sind ein Besuch).
        $rows = LogEntry::query()
            ->active()
            ->whereDate('shot_on', '>=', $from)
            ->whereDate('shot_on', '<=', $to)
            ->get(['id', 'user_id', 'guest_name', 'shot_on', 'weapon_class', 'booking_id']);
        $personDay = fn (LogEntry $e) => ($e->user_id ?? 'g:'.$e->guest_name).'|'.$e->shot_on->toDateString();
        $visits = $rows->unique($personDay)->values();

        $months = collect(range(1, 12))->map(fn (int $m) => [
            'month' => $m,
            'visits' => $visits->filter(fn (LogEntry $e) => $e->shot_on->month === $m)->count(),
            'guests' => $visits->filter(fn (LogEntry $e) => $e->shot_on->month === $m && $e->user_id === null)->count(),
        ]);

        $days = ShootingDay::query()
            ->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)
            ->get(['id', 'date', 'cancelled_at']);

        $classes = collect(WeaponClass::cases())->map(fn (WeaponClass $class) => [
            'name' => $class->label(),
            'visits' => $rows->filter(fn (LogEntry $e) => $e->weapon_class === $class)->unique($personDay)->count(),
        ])->filter(fn (array $r) => $r['visits'] > 0)->values();

        $topMembers = $visits->whereNotNull('user_id')
            ->groupBy('user_id')
            ->map(fn ($group, $userId) => ['user_id' => (int) $userId, 'visits' => $group->map(fn (LogEntry $e) => $e->shot_on->toDateString())->unique()->count()])
            ->sortByDesc('visits')
            ->take(10)
            ->values();
        $names = User::whereIn('id', $topMembers->pluck('user_id'))->pluck('name', 'id');

        $duties = Supervision::query()
            ->whereHas('shootingDay', fn ($q) => $q->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)->whereNull('cancelled_at'))
            ->with('user:id,name')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($group) => ['name' => $group->first()->user->name, 'duties' => $group->count(), 'hours' => round($group->sum(fn (Supervision $s) => $s->window()->duration()) / 60)])
            ->sortByDesc('duties')
            ->take(10)
            ->values();

        return Inertia::render('admin/Statistics', [
            'year' => $year,
            'totals' => [
                'days' => $days->whereNull('cancelled_at')->count(),
                'cancelled' => $days->whereNotNull('cancelled_at')->count(),
                'visits' => $visits->count(),
                'guests' => $visits->whereNull('user_id')->count(),
                'members' => $visits->whereNotNull('user_id')->pluck('user_id')->unique()->count(),
                'no_shows' => $bookings->filter(fn (Booking $b) => $b->status === BookingStatus::NoShow)->count(),
            ],
            'months' => $months,
            'classes' => $classes,
            'topMembers' => $topMembers->map(fn (array $m) => ['name' => $names[$m['user_id']] ?? '?', 'visits' => $m['visits']]),
            'duties' => $duties,
        ]);
    }
}
