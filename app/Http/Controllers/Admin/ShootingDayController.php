<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ShootingDayRequest;
use App\Models\Group;
use App\Models\Range;
use App\Models\ShootingDay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ShootingDayController extends Controller
{
    public function index(Request $request): Response
    {
        $past = $request->boolean('past');

        $days = ShootingDay::query()
            ->with(['ranges:id,name', 'groups:id,name'])
            ->when($past,
                fn ($query) => $query->whereDate('date', '<', today())->orderByDesc('date'),
                fn ($query) => $query->whereDate('date', '>=', today())->orderBy('date')->orderBy('starts_at'))
            ->limit(120)
            ->get()
            ->map(fn (ShootingDay $day) => $this->present($day));

        return Inertia::render('admin/shooting-days/Index', [
            'days' => $days,
            'past' => $past,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/shooting-days/Form', [
            'day' => null,
            ...$this->options(),
        ]);
    }

    public function store(ShootingDayRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $dates = $this->dates($data['date'], $data['repeat_until'] ?? null);
        $series = count($dates) > 1 ? (string) Str::uuid() : null;

        DB::transaction(function () use ($dates, $data, $series) {
            foreach ($dates as $date) {
                $day = ShootingDay::create([...$this->attributes($data), 'date' => $date, 'series' => $series]);
                $this->syncRelations($day, $data);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('{1} Schießtag angelegt.|[2,*] :count Schießtage angelegt.', count($dates), ['count' => count($dates)])]);

        return to_route('admin.shooting-days.index');
    }

    public function edit(ShootingDay $shootingDay): Response
    {
        return Inertia::render('admin/shooting-days/Form', [
            'day' => $this->present($shootingDay->load(['ranges:id,name', 'groups:id,name'])),
            'seriesCount' => $shootingDay->series
                ? ShootingDay::where('series', $shootingDay->series)->whereDate('date', '>=', $shootingDay->date)->count()
                : 0,
            ...$this->options(),
        ]);
    }

    public function update(ShootingDayRequest $request, ShootingDay $shootingDay): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($shootingDay, $data) {
            $days = ($data['apply_to_series'] ?? false) && $shootingDay->series
                ? ShootingDay::where('series', $shootingDay->series)->whereDate('date', '>=', $shootingDay->date)->get()
                : collect([$shootingDay]);

            foreach ($days as $day) {
                $attributes = $this->attributes($data);

                if ($day->is($shootingDay)) {
                    $attributes['date'] = $data['date'];
                }

                $day->update($attributes);
                $this->syncRelations($day, $data);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Schießtag gespeichert.')]);

        return to_route('admin.shooting-days.index');
    }

    public function cancel(Request $request, ShootingDay $shootingDay): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $shootingDay->forceFill(['cancelled_at' => now(), 'cancel_reason' => $data['reason'] ?? null])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Schießtag abgesagt.')]);

        return back();
    }

    public function reopen(ShootingDay $shootingDay): RedirectResponse
    {
        $shootingDay->forceFill(['cancelled_at' => null, 'cancel_reason' => null])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Schießtag wieder geöffnet.')]);

        return back();
    }

    /**
     * Wöchentliche Wiederholung ab dem Startdatum.
     *
     * @return list<string>
     */
    private function dates(string $from, ?string $until): array
    {
        $date = Carbon::parse($from);
        $end = $until ? Carbon::parse($until) : $date->copy();
        $dates = [];

        while ($date->lte($end)) {
            $dates[] = $date->toDateString();
            $date->addWeek();
        }

        return $dates;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'title' => $data['title'] ?? null,
            'notes' => $data['notes'] ?? null,
            'restricted_to_groups' => (bool) ($data['restricted_to_groups'] ?? false),
            'guests_allowed' => (bool) ($data['guests_allowed'] ?? true),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncRelations(ShootingDay $day, array $data): void
    {
        $day->ranges()->sync($data['ranges']);
        $day->groups()->sync(($data['restricted_to_groups'] ?? false) ? ($data['groups'] ?? []) : []);
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'ranges' => Range::ordered()->where('active', true)->get(['id', 'name', 'lanes']),
            'groups' => Group::orderBy('name')->get(['id', 'name']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ShootingDay $day): array
    {
        return [
            'id' => $day->id,
            'series' => $day->series,
            'date' => $day->date->toDateString(),
            'starts_at' => substr($day->starts_at, 0, 5),
            'ends_at' => substr($day->ends_at, 0, 5),
            'title' => $day->title,
            'notes' => $day->notes,
            'restricted_to_groups' => $day->restricted_to_groups,
            'guests_allowed' => $day->guests_allowed,
            'cancelled' => $day->isCancelled(),
            'cancel_reason' => $day->cancel_reason,
            'ranges' => $day->ranges->map(fn (Range $range) => ['id' => $range->id, 'name' => $range->name])->values(),
            'groups' => $day->groups->map(fn (Group $group) => ['id' => $group->id, 'name' => $group->name])->values(),
        ];
    }
}
