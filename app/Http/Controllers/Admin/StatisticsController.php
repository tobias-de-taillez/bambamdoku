<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Range;
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
            ->with(['shootingDay:id,date', 'range:id,name'])
            ->get(['id', 'shooting_day_id', 'range_id', 'user_id', 'status', 'weapon_type', 'starts_at', 'ends_at']);

        $visits = $bookings->filter(fn (Booking $b) => $b->status === BookingStatus::CheckedIn);

        $months = collect(range(1, 12))->map(fn (int $m) => [
            'month' => $m,
            'visits' => $visits->filter(fn (Booking $b) => $b->shootingDay->date->month === $m)->count(),
            'guests' => $visits->filter(fn (Booking $b) => $b->shootingDay->date->month === $m && $b->user_id === null)->count(),
        ]);

        $days = ShootingDay::query()
            ->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)
            ->get(['id', 'date', 'cancelled_at']);

        $ranges = Range::ordered()->get()->map(fn (Range $range) => [
            'name' => $range->name,
            'visits' => $visits->where('range_id', $range->id)->count(),
            'hours' => round($visits->where('range_id', $range->id)->sum(fn (Booking $b) => $b->window()->duration()) / 60),
        ])->filter(fn (array $r) => $r['visits'] > 0)->values();

        $topMembers = $visits->whereNotNull('user_id')
            ->groupBy('user_id')
            ->map(fn ($group, $userId) => ['user_id' => (int) $userId, 'visits' => $group->map(fn (Booking $b) => $b->shootingDay->date->toDateString())->unique()->count()])
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
            'ranges' => $ranges,
            'topMembers' => $topMembers->map(fn (array $m) => ['name' => $names[$m['user_id']] ?? '?', 'visits' => $m['visits']]),
            'duties' => $duties,
        ]);
    }
}
