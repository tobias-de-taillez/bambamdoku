<?php

namespace App\Http\Controllers;

use App\Domain\Disciplines;
use App\Domain\Sessions;
use App\Domain\ShootingRecord;
use App\Enums\LogSource;
use App\Enums\SessionEntryStatus;
use App\Models\LogEntry;
use App\Models\SessionEntry;
use App\Models\ShootingSession;
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
    public function index(Request $request, Sessions $sessions): Response
    {
        /** @var User $user */
        $user = $request->user();
        [$from, $to] = self::period($request);
        $record = ShootingRecord::for($user);
        $entries = $record->entries($from, $to);
        // Stornierte Zeilen nur zum Anzeigen (durchgestrichen), Zählung und Knöpfe nur aus den gültigen (K8)
        $rows = $entries->concat($record->cancelled($from, $to))
            ->sort(fn (LogEntry $a, LogEntry $b) => [$b->shot_on->toDateString(), $b->id] <=> [$a->shot_on->toDateString(), $a->id]);

        return Inertia::render('record/Index', [
            'entries' => $rows->map(fn (LogEntry $e) => [...self::entry($e), 'cancelled_by' => $e->isActive() ? null : $e->cancelledBy?->name])->values(),
            'totals' => ShootingRecord::totals($entries),
            'catalog' => Disciplines::all() !== [],
            'disciplines' => ShootingRecord::disciplines($entries),
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            // nur eigene Einträge der Vorstufe, die nicht in die Kladde gekommen sind
            'notRecorded' => $sessions->entriesOutsideRecord($user, $from, $to)->map(fn (SessionEntry $e) => [
                'id' => $e->id,
                'date' => $e->participant->session->shot_on->toDateString(),
                'time' => $e->created_at->format('H:i'),
                'weapon_label' => $e->weapon_class->label(),
                'weapon_owner' => $e->weapon_owner?->label(),
                'discipline' => Disciplines::label($e->discipline),
                'shots' => $e->shots,
                'via' => $e->via->label(),
                'supervisor' => $e->participant->session->supervisor->name,
                'status' => $e->status->value,
                'status_label' => $e->status === SessionEntryStatus::Pending ? 'verfallen (nicht abgezeichnet)' : $e->status->label(),
                'reject_reason' => $e->reject_reason,
                'rejected_by' => $e->rejectedBy?->name,
            ])->values(),
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
     * Teilnahmenachweis je Disziplin (a), für das Mitglied zur Information.
     */
    public function disciplinePdf(Request $request): HttpResponse
    {
        /** @var User $user */
        $user = $request->user();
        [$from, $to] = self::period($request);

        return self::downloadDiscipline($user, self::discipline($request), $from, $to, $user, forInformation: true);
    }

    /**
     * Disziplin aus der Anfrage; nur Schlüssel aus dem gewählten Katalog. Ohne Katalog kein Druck (E9).
     */
    public static function discipline(Request $request): string
    {
        abort_if(Disciplines::all() === [], 404, 'Teilnahmenachweis nicht verfügbar, solange kein Disziplinkatalog gewählt ist.');

        $key = (string) $request->query('discipline', '');
        abort_unless(array_key_exists($key, Disciplines::all()), 404);

        return $key;
    }

    public static function downloadDiscipline(User $member, string $discipline, CarbonImmutable $from, CarbonImmutable $to, User $creator, bool $forInformation = false): HttpResponse
    {
        $pdf = Pdf::loadView('pdf.discipline', self::disciplineData($member, $discipline, $from, $to, $creator, $forInformation))->setPaper('a4');

        return $pdf->download(sprintf('Teilnahmenachweis-%s-%s-%s.pdf', str($member->name)->slug(), str($discipline)->slug(), $to->format('Y-m-d')));
    }

    /**
     * Daten der Vorlage pdf/discipline (a), auch für Inhaltstests als HTML.
     *
     * @return array<string, mixed>
     */
    public static function disciplineData(User $member, ?string $discipline, CarbonImmutable $from, CarbonImmutable $to, User $creator, bool $forInformation = false): array
    {
        $available = Disciplines::all() !== [] && $discipline !== null && array_key_exists($discipline, Disciplines::all());
        $entries = $available
            ? ShootingRecord::for($member)->entries($from, $to, $discipline)->sortBy(fn (LogEntry $e) => $e->shot_on->toDateString().'-'.$e->id)->values()
            : collect();

        return [
            'available' => $available,
            'member' => $member,
            'discipline' => $discipline,
            'disciplineName' => $available ? Disciplines::all()[$discipline] : null,
            'entries' => $entries->map(fn (LogEntry $e) => self::entry($e)),
            'days' => $entries->map(fn (LogEntry $e) => $e->shot_on->toDateString())->unique()->count(),
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
        ];
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
            'confirmed_by' => $e->confirmedByLabel(),
            'via' => $e->viaLabel(),
            'shots' => $e->shots,
            'late' => $e->source === LogSource::LateEntry && $e->corrects_id === null,
            'correction' => $e->corrects_id !== null,
            'corrects_id' => $e->corrects_id,
            'late_reason' => $e->late_reason,
            // nur aus dem Backfill (…_000900): Zeile ohne Bestätigung durch eine Aufsicht
            'from_booking' => $e->isFromBooking(),
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
        $pdf = Pdf::loadView('pdf.record', self::pdfData($member, $from, $to, $creator, $forInformation))->setPaper('a4');

        return $pdf->download(sprintf('Schiesskladde-%s-%s.pdf', str($member->name)->slug(), $to->format('Y-m-d')));
    }

    /**
     * Daten der Vorlage pdf/record, auch für Inhaltstests als HTML.
     *
     * @return array<string, mixed>
     */
    public static function pdfData(User $member, CarbonImmutable $from, CarbonImmutable $to, User $creator, bool $forInformation = false): array
    {
        $entries = ShootingRecord::for($member)->entries($from, $to)->sortBy(fn (LogEntry $e) => $e->shot_on->toDateString().'-'.$e->id)->values();
        $switchover = ShootingSession::query()->whereNotNull('signed_at')->min('signed_at');

        return [
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
            // Umstellung auf das Abzeichnen: erstes abgezeichnete Session, berechnet
            'switchover' => $switchover ? CarbonImmutable::parse($switchover) : null,
        ];
    }
}
