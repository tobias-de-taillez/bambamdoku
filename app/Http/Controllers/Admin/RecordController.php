<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Disciplines;
use App\Domain\Logbook;
use App\Enums\WeaponClass;
use App\Enums\WeaponOwner;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ShootingRecordController;
use App\Models\LogEntry;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Die Schießkladde des Vereins (K12): alle Einträge inkl. Gäste und Stornos, Nachträge, Auszüge.
 */
class RecordController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'user' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $to = isset($validated['to']) ? CarbonImmutable::parse($validated['to']) : CarbonImmutable::today();
        $from = isset($validated['from']) ? CarbonImmutable::parse($validated['from']) : $to->startOfMonth()->subMonthNoOverflow();

        $entries = LogEntry::query()
            ->when($validated['user'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->whereDate('shot_on', '>=', $from)
            ->whereDate('shot_on', '<=', $to)
            ->with(['user:id,name,member_number', 'recordedBy:id,name', 'cancelledBy:id,name'])
            ->orderByDesc('shot_on')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('admin/record/Index', [
            'entries' => $entries->map(fn (LogEntry $e) => [
                ...ShootingRecordController::entry($e),
                'user_id' => $e->user_id,
                'name' => $e->shooterName(),
                'member_number' => $e->user?->member_number,
                'guest' => $e->user_id === null,
                'cancelled_by' => $e->cancelledBy?->name,
            ]),
            'members' => User::query()->members()->orderBy('name')->get(['id', 'name', 'member_number', 'active']),
            'weaponClasses' => WeaponClass::options(),
            'disciplines' => Disciplines::options(),
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                // Aus der Query kommt ein String; das Frontend vergleicht mit der numerischen ID.
                'user' => isset($validated['user']) ? (int) $validated['user'] : null,
            ],
        ]);
    }

    /**
     * Nachtrag (K9): nur Admins, mit Pflichtgrund, mehrere Personen in einem Formular.
     */
    public function storeLate(Request $request, Logbook $logbook): RedirectResponse
    {
        $data = $request->validate([
            'members' => ['required', 'array', 'min:1'],
            'members.*' => ['integer', 'exists:users,id'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'weapon_class' => ['required', Rule::enum(WeaponClass::class)],
            'weapon_owner' => ['required', Rule::enum(WeaponOwner::class)],
            'discipline' => ['nullable', 'string', 'max:50'],
            'reason' => ['required', 'string', 'min:5', 'max:255'],
        ], [], [
            'members' => __('Mitglieder'),
            'date' => __('Datum'),
            'weapon_class' => __('Kategorie'),
            'weapon_owner' => __('Waffe'),
            'reason' => __('Grund'),
        ]);

        /** @var User $admin */
        $admin = $request->user();
        $members = User::query()->whereIn('id', $data['members'])->get()->all();

        $result = $logbook->lateEntries($admin, array_values($members), $data['date'], WeaponClass::from($data['weapon_class']), WeaponOwner::from($data['weapon_owner']), $data['discipline'] ?? null, $data['reason']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':created nachgetragen:duplicates.', [
            'created' => $result['created'],
            'duplicates' => $result['duplicates'] > 0 ? ', '.$result['duplicates'].' waren schon eingetragen' : '',
        ])]);

        return back();
    }

    public function cancel(Request $request, LogEntry $entry, Logbook $logbook): RedirectResponse
    {
        /** @var User $admin */
        $admin = $request->user();
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], [], ['reason' => __('Grund')]);

        $logbook->cancel($admin, $entry, $data['reason']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Eintrag storniert.')]);

        return back();
    }

    public function memberPdf(Request $request, User $user): HttpResponse
    {
        abort_if($user->isApplicant(), 404);

        [$from, $to] = ShootingRecordController::period($request);

        /** @var User $admin */
        $admin = $request->user();

        return ShootingRecordController::download($user, $from, $to, $admin);
    }

    /**
     * Leere Papier-Ersatzliste für Ausfälle am Stand; Spalten wie beim Nachtrag.
     */
    public function paperList(): HttpResponse
    {
        return Pdf::loadView('pdf.paper-list', [
            'club' => config('verein.name'),
            'clubAddress' => config('verein.address'),
        ])->setPaper('a4')->download('papier-ersatzliste.pdf');
    }

    /**
     * Mitgliedschaftsbescheinigung für die Folgeprüfung nach § 14 Abs. 4 Satz 3 WaffG.
     */
    public function certificate(Request $request, User $user): HttpResponse
    {
        // § 14 Abs. 4 S. 3 WaffG: nur für Mitglieder, nie für Anträge.
        abort_if($user->isApplicant(), 404);

        /** @var User $admin */
        $admin = $request->user();

        $pdf = Pdf::loadView('pdf.membership', [
            'user' => $user,
            'club' => config('verein.name'),
            'clubAddress' => config('verein.address'),
            'federation' => config('verein.federation'),
            'clubNumber' => config('verein.club_number'),
            'createdAt' => CarbonImmutable::now(),
            'createdBy' => $admin->name,
        ])->setPaper('a4');

        return $pdf->download(sprintf('Mitgliedschaftsbescheinigung-%s.pdf', str($user->name)->slug()));
    }
}
