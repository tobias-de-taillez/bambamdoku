<?php

namespace App\Http\Controllers;

use App\Domain\ShootingRecord;
use App\Models\Booking;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Die eigene Schießkladde eines Mitglieds. Mitglieder sehen nur ihre eigenen Einträge.
 */
class ShootingRecordController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        [$from, $to] = $this->period($request);
        $record = ShootingRecord::for($user);

        return Inertia::render('record/Index', [
            ...self::payload($record, $from, $to),
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }

    public function pdf(Request $request): HttpResponse
    {
        /** @var User $user */
        $user = $request->user();
        [$from, $to] = $this->period($request);

        return self::download(ShootingRecord::for($user), $from, $to);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function period(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $to = isset($validated['to']) ? CarbonImmutable::parse($validated['to']) : CarbonImmutable::today();
        $from = isset($validated['from']) ? CarbonImmutable::parse($validated['from']) : $to->subMonthsNoOverflow(24)->addDay();

        return [$from, $to];
    }

    /**
     * @return array<string, mixed>
     */
    public static function payload(ShootingRecord $record, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return [
            'entries' => $record->entries($from, $to)->map(fn (Booking $b) => self::entry($b))->values(),
            'totals' => $record->totalsByWeaponType($from, $to),
            'acquisition' => $record->acquisitionCheck(),
            'possession' => $record->possessionCheck(),
            'member' => [
                'name' => $record->user->name,
                'member_number' => $record->user->member_number,
                'member_since' => $record->user->member_since?->toDateString(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function entry(Booking $b): array
    {
        return [
            'id' => $b->id,
            'date' => $b->shootingDay->date->toDateString(),
            'starts_at' => substr($b->starts_at, 0, 5),
            'ends_at' => substr($b->ends_at, 0, 5),
            'range' => $b->range->name,
            'weapon_type' => $b->weapon_type->value,
            'weapon_label' => $b->weapon_type->label(),
            'club_weapon' => $b->club_weapon,
            'confirmed_by' => $b->checkedInBy?->name,
            'confirmed_at' => $b->checked_in_at?->toIso8601String(),
        ];
    }

    public static function download(ShootingRecord $record, CarbonImmutable $from, CarbonImmutable $to): HttpResponse
    {
        $user = $record->user;
        $data = [
            ...self::payload($record, $from, $to),
            'from' => $from,
            'to' => $to,
            'club' => config('verein.name'),
            'clubAddress' => config('verein.address'),
            'createdAt' => CarbonImmutable::now(),
        ];

        $name = sprintf('Schiesskladde-%s-%s.pdf', str($user->name)->slug(), $to->format('Y-m-d'));

        return Pdf::loadView('pdf.record', $data)->setPaper('a4')->download($name);
    }
}
