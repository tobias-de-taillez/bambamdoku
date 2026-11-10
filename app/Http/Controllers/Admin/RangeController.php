<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Schedule;
use App\Domain\TimeWindow;
use App\Enums\WeaponType;
use App\Http\Controllers\Controller;
use App\Models\AuditEntry;
use App\Models\Booking;
use App\Models\Range;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RangeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/ranges/Index', [
            'ranges' => Range::ordered()->get()->map(fn (Range $range) => [
                'id' => $range->id,
                'name' => $range->name,
                'lanes' => $range->lanes,
                'weapon_types' => $range->weapon_types->map(fn (WeaponType $type) => $type->value)->values(),
                'notes' => $range->notes,
                'position' => $range->position,
                'active' => $range->active,
            ]),
            'weaponTypes' => WeaponType::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $range = Range::create([...$this->validated($request), 'position' => (int) Range::max('position') + 1]);
        AuditEntry::record($request->user(), 'range.created', $range, sprintf('Stand angelegt: %s (%d Bahnen)', $range->name, $range->lanes));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Stand angelegt.')]);

        return to_route('admin.ranges.index');
    }

    public function update(Request $request, Range $range): RedirectResponse
    {
        $data = $this->validated($request);
        $this->ensureFutureBookingsFit($range, (int) $data['lanes'], $data['weapon_types'], (bool) ($data['active'] ?? true));
        $range->update($data);
        AuditEntry::record($request->user(), 'range.updated', $range, sprintf('Stand geändert: %s (%d Bahnen%s)', $range->name, $range->lanes, $range->active ? '' : ', außer Betrieb'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Stand gespeichert.')]);

        return to_route('admin.ranges.index');
    }

    /**
     * Bahnen, Waffenarten oder Außerbetriebnahme dürfen keine künftigen Buchungen ungültig machen.
     *
     * @param  array<int, string>  $weaponTypes
     */
    private function ensureFutureBookingsFit(Range $range, int $lanes, array $weaponTypes, bool $active): void
    {
        $future = Booking::query()
            ->where('range_id', $range->id)
            ->occupying()
            ->whereHas('shootingDay', fn ($q) => $q->whereDate('date', '>=', today())->whereNull('cancelled_at'))
            ->with('shootingDay')
            ->get();

        if ($future->isEmpty()) {
            return;
        }

        $problem = match (true) {
            ! $active => trans_choice('{1} Auf diesem Stand gibt es noch eine künftige Buchung. Bitte zuerst stornieren oder den Schießtag absagen.|[2,*] Auf diesem Stand gibt es noch :count künftige Buchungen. Bitte zuerst stornieren oder die Schießtage absagen.', $future->count(), ['count' => $future->count()]),
            $future->contains(fn (Booking $b) => ! in_array($b->weapon_type->value, $weaponTypes, true)) => __('Künftige Buchungen nutzen eine Waffenart, die dann nicht mehr zugelassen wäre.'),
            $future->groupBy('shooting_day_id')->contains(fn ($bookings) => (new Schedule($bookings->first()->shootingDay))->peakOccupancy($range, TimeWindow::fromStrings($bookings->first()->shootingDay->starts_at, $bookings->first()->shootingDay->ends_at)) > $lanes) => __('Künftige Buchungen belegen mehr Bahnen gleichzeitig, als es dann gäbe.'),
            default => null,
        };

        if ($problem !== null) {
            throw ValidationException::withMessages(['lanes' => $problem]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'lanes' => ['required', 'integer', 'min:1', 'max:100'],
            'weapon_types' => ['required', 'array', 'min:1'],
            'weapon_types.*' => [Rule::enum(WeaponType::class)],
            'notes' => ['nullable', 'string', 'max:255'],
            'active' => ['boolean'],
        ], [], [
            'lanes' => __('Bahnen'),
            'weapon_types' => __('Waffenarten'),
            'notes' => __('Hinweis'),
        ]);
    }
}
