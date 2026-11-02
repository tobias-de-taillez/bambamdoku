<?php

namespace App\Http\Controllers;

use App\Domain\Disciplines;
use App\Domain\ShootingRecord;
use App\Enums\LogSource;
use App\Models\LogEntry;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Die eigenen Einträge eines Mitglieds in der Kladde des Vereins (zur Information).
 */
class ShootingRecordController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        [$from, $to] = self::period($request);
        $entries = ShootingRecord::for($user)->entries($from, $to);

        return Inertia::render('record/Index', [
            'entries' => $entries->map(fn (LogEntry $e) => self::entry($e))->values(),
            'totals' => ShootingRecord::totals($entries),
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ]);
    }

    public function pdf(Request $request): HttpResponse
    {
        /** @var User $user */
        $user = $request->user();
        [$from, $to] = self::period($request);

        return self::download($user, $from, $to, $user, forInformation: true);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function period(Request $request): array
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
    public static function entry(LogEntry $e): array
    {
        return [
            'id' => $e->id,
            'date' => $e->shot_on->toDateString(),
            'weapon_class' => $e->weapon_class->value,
            'weapon_label' => $e->weapon_class->label(),
            'weapon_owner' => $e->weapon_owner?->label(),
            'club_weapon' => $e->weapon_owner?->value === 'club',
            'discipline' => Disciplines::label($e->discipline),
            'recorded_by' => $e->recordedBy->name,
            'late' => $e->source === LogSource::LateEntry,
            'late_reason' => $e->late_reason,
            'active' => $e->isActive(),
            'cancel_reason' => $e->cancel_reason,
        ];
    }

    /**
     * Auszug zur Vorlage beim Verband (K13): nur gültige Einträge, Zählung je Kategorie,
     * keine Bewertung; Nachweiswert erst mit Unterschrift und Stempel des Vorstands.
     */
    public static function download(User $member, CarbonImmutable $from, CarbonImmutable $to, User $creator, bool $forInformation = false): HttpResponse
    {
        $entries = ShootingRecord::for($member)->entries($from, $to)->sortBy(fn (LogEntry $e) => $e->shot_on->toDateString().'-'.$e->id)->values();

        $pdf = Pdf::loadView('pdf.record', [
            'member' => $member,
            'entries' => $entries->map(fn (LogEntry $e) => self::entry($e)),
            'totals' => ShootingRecord::totals($entries),
            'from' => $from,
            'to' => $to,
            'createdAt' => CarbonImmutable::now(),
            'createdBy' => $creator->name,
            'forInformation' => $forInformation,
            'club' => config('verein.name'),
            'clubAddress' => config('verein.address'),
            'federation' => config('verein.federation'),
            'clubNumber' => config('verein.club_number'),
            'digitalSince' => config('verein.digital_since'),
        ])->setPaper('a4');

        return $pdf->download(sprintf('Schiesskladde-%s-%s.pdf', str($member->name)->slug(), $to->format('Y-m-d')));
    }
}
