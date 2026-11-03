<?php

namespace App\Http\Controllers\Admin;

use App\Domain\TimeWindow;
use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ShootingDayRequest;
use App\Models\AuditEntry;
use App\Models\Booking;
use App\Models\Range;
use App\Models\ShootingDay;
use App\Notifications\ShootingDayCancelled;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ShootingDayController extends Controller
{
    public function index(Request $request): Response
    {
        $past = $request->boolean('past');

        $days = ShootingDay::query()
            ->with(['ranges:id,name', 'supervisions.user:id,name'])
            ->withCount(['bookings as booked_count' => fn ($q) => $q->whereIn('status', BookingStatus::occupying())])
            ->when($past,
                fn ($query) => $query->whereDate('date', '<', today())->orderByDesc('date'),
                fn ($query) => $query->whereDate('date', '>=', today())->orderBy('date')->orderBy('starts_at'))
            ->limit(120)
            ->get()
            ->map(fn (ShootingDay $day) => [
                ...$this->present($day),
                'booked' => (int) $day->getAttribute('booked_count'),
                'supervisors' => $day->supervisions->map(fn ($s) => $s->user->name)->unique()->values(),
            ]);

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

        foreach ($dates as $date) {
            $this->ensureNoOverlap($date, $data);
        }

        DB::transaction(function () use ($dates, $data) {
            foreach ($dates as $date) {
                $day = ShootingDay::create([...$this->attributes($data), 'date' => $date]);
                $this->syncRelations($day, $data);
            }
        });

        AuditEntry::record($request->user(), 'shooting_day.created', null, sprintf(
            'Schießtag angelegt: %s%s, %s–%s Uhr',
            $dates[0],
            count($dates) > 1 ? ' (+'.(count($dates) - 1).' wöchentlich)' : '',
            $data['starts_at'],
            $data['ends_at'],
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice('{1} Schießtag angelegt.|[2,*] :count Schießtage angelegt.', count($dates), ['count' => count($dates)])]);

        return to_route('admin.shooting-days.index');
    }

    public function edit(ShootingDay $shootingDay): Response
    {
        return Inertia::render('admin/shooting-days/Form', [
            'day' => $this->present($shootingDay->load('ranges:id,name')),
            'deletable' => ! $shootingDay->bookings()->exists(),
            ...$this->options(),
        ]);
    }

    public function update(ShootingDayRequest $request, ShootingDay $shootingDay): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($shootingDay, $data) {
            $this->ensureBookingsStillFit($shootingDay, $data);
            $this->ensureNoOverlap($data['date'], $data, $shootingDay);
            $shootingDay->update([...$this->attributes($data), 'date' => $data['date']]);
            $this->syncRelations($shootingDay, $data);
        });

        AuditEntry::record($request->user(), 'shooting_day.updated', $shootingDay, sprintf(
            'Schießtag geändert: %s, %s–%s Uhr',
            $shootingDay->date->format('d.m.Y'),
            $data['starts_at'],
            $data['ends_at'],
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Schießtag gespeichert.')]);

        return to_route('admin.shooting-days.index');
    }

    public function cancel(Request $request, ShootingDay $shootingDay): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);

        $affected = DB::transaction(function () use ($shootingDay, $data) {
            $shootingDay->forceFill(['cancelled_at' => now(), 'cancel_reason' => $data['reason'] ?? null])->save();

            $bookings = $shootingDay->bookings()->where('status', BookingStatus::Booked)->with(['user', 'host'])->get();
            $shootingDay->bookings()->where('status', BookingStatus::Booked)->update([
                'status' => BookingStatus::Cancelled->value,
                'cancelled_at' => now(),
            ]);

            return $bookings;
        });

        AuditEntry::record($request->user(), 'shooting_day.cancelled', $shootingDay, sprintf(
            'Schießtag abgesagt: %s%s, %d Buchungen storniert',
            $shootingDay->date->format('d.m.Y'),
            $shootingDay->cancel_reason ? ' ('.$shootingDay->cancel_reason.')' : '',
            $affected->count(),
        ));

        $recipients = $affected->map(fn (Booking $b) => $b->user ?? $b->host)->filter()->unique('id');
        Notification::send($recipients, new ShootingDayCancelled($shootingDay));

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(
            '{0} Schießtag abgesagt.|{1} Schießtag abgesagt, eine Buchung storniert und benachrichtigt.|[2,*] Schießtag abgesagt, :count Buchungen storniert und benachrichtigt.',
            $affected->count(),
            ['count' => $affected->count()],
        )]);

        return back();
    }

    /**
     * Nur Tage ohne jede Buchung lassen sich löschen, z. B. versehentlich angelegte.
     */
    public function destroy(Request $request, ShootingDay $shootingDay): RedirectResponse
    {
        if ($shootingDay->bookings()->exists()) {
            throw ValidationException::withMessages([
                'shooting_day' => __('An diesem Tag gibt es Buchungen. Sagen Sie ihn stattdessen ab.'),
            ]);
        }

        AuditEntry::record($request->user(), 'shooting_day.deleted', null, sprintf('Schießtag gelöscht: %s, %s–%s Uhr', $shootingDay->date->format('d.m.Y'), substr($shootingDay->starts_at, 0, 5), substr($shootingDay->ends_at, 0, 5)));
        $shootingDay->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Schießtag gelöscht.')]);

        return to_route('admin.shooting-days.index');
    }

    public function reopen(Request $request, ShootingDay $shootingDay): RedirectResponse
    {
        $shootingDay->forceFill(['cancelled_at' => null, 'cancel_reason' => null])->save();
        AuditEntry::record($request->user(), 'shooting_day.reopened', $shootingDay, 'Schießtag wieder geöffnet: '.$shootingDay->date->format('d.m.Y'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Schießtag wieder geöffnet.')]);

        return back();
    }

    /**
     * Zwei Schießtage am selben Datum dürfen sich nicht zeitlich überschneiden,
     * wenn sie einen Stand teilen – sonst zählten Bahnen doppelt.
     *
     * @param  array<string, mixed>  $data
     */
    private function ensureNoOverlap(string $date, array $data, ?ShootingDay $except = null): void
    {
        $window = TimeWindow::fromStrings($data['starts_at'], $data['ends_at']);
        /** @var array<int, int|string> $rangeIds */
        $rangeIds = $data['ranges'];

        $clash = ShootingDay::query()
            ->whereDate('date', $date)
            ->whereNull('cancelled_at')
            ->when($except, fn ($q) => $q->whereKeyNot($except->id))
            ->whereHas('ranges', fn ($q) => $q->whereIn('ranges.id', $rangeIds))
            ->get()
            ->first(fn (ShootingDay $other) => TimeWindow::fromStrings($other->starts_at, $other->ends_at)->overlaps($window));

        if ($clash) {
            throw ValidationException::withMessages([
                'starts_at' => __('Am :date gibt es schon einen Schießtag (:from–:to Uhr) auf denselben Ständen.', [
                    'date' => CarbonImmutable::parse($date)->format('d.m.Y'),
                    'from' => substr($clash->starts_at, 0, 5),
                    'to' => substr($clash->ends_at, 0, 5),
                ]),
            ]);
        }
    }

    /**
     * Änderungen dürfen keine bestehende Buchung aus der Öffnungszeit oder von einem Stand verdrängen.
     *
     * @param  array<string, mixed>  $data
     */
    private function ensureBookingsStillFit(ShootingDay $day, array $data): void
    {
        $window = TimeWindow::fromStrings($data['starts_at'], $data['ends_at']);
        /** @var array<int, int|string> $rangeIds */
        $rangeIds = $data['ranges'];
        $ranges = collect($rangeIds)->map(fn ($id) => (int) $id);

        $conflicts = $day->bookings()
            ->occupying()
            ->get()
            ->filter(fn (Booking $b) => ! $window->contains($b->window()) || ! $ranges->contains($b->range_id));

        $dateChanged = $day->date->toDateString() !== $data['date'] && $day->bookings()->occupying()->exists();

        if ($conflicts->isNotEmpty() || $dateChanged) {
            throw ValidationException::withMessages([
                'starts_at' => $dateChanged
                    ? __('Für :date gibt es bereits Buchungen. Bitte den Tag absagen und neu anlegen, statt das Datum zu verschieben.', ['date' => $day->date->format('d.m.Y')])
                    : trans_choice(
                        '{1} Eine Buchung am :date läge außerhalb der neuen Zeiten oder Stände. Bitte zuerst stornieren.|[2,*] :count Buchungen am :date lägen außerhalb der neuen Zeiten oder Stände. Bitte zuerst stornieren.',
                        $conflicts->count(),
                        ['count' => $conflicts->count(), 'date' => $day->date->format('d.m.Y')],
                    ),
            ]);
        }
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
            'guests_allowed' => (bool) ($data['guests_allowed'] ?? true),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncRelations(ShootingDay $day, array $data): void
    {
        $day->ranges()->sync($data['ranges']);
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'ranges' => Range::ordered()->where('active', true)->get(['id', 'name', 'lanes']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ShootingDay $day): array
    {
        return [
            'id' => $day->id,
            'date' => $day->date->toDateString(),
            'starts_at' => substr($day->starts_at, 0, 5),
            'ends_at' => substr($day->ends_at, 0, 5),
            'title' => $day->title,
            'notes' => $day->notes,
            'guests_allowed' => $day->guests_allowed,
            'cancelled' => $day->isCancelled(),
            'cancel_reason' => $day->cancel_reason,
            'ranges' => $day->ranges->map(fn (Range $range) => ['id' => $range->id, 'name' => $range->name])->values(),
        ];
    }
}
