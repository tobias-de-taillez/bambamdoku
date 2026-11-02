<?php

namespace App\Http\Controllers\Admin;

use App\Domain\ShootingRecord;
use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ShootingRecordController;
use App\Models\Booking;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Die Schießkladde des Vereins: alle bestätigten Besuche, auch von Gästen.
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

        $entries = Booking::query()
            ->where('status', BookingStatus::CheckedIn)
            ->when($validated['user'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->whereHas('shootingDay', fn ($q) => $q->whereDate('date', '>=', $from)->whereDate('date', '<=', $to))
            ->with(['shootingDay', 'range', 'user', 'host', 'checkedInBy'])
            ->get()
            ->sortByDesc(fn (Booking $b) => $b->shootingDay->date->toDateString().$b->starts_at)
            ->values();

        return Inertia::render('admin/record/Index', [
            'entries' => $entries->map(fn (Booking $b) => [
                ...ShootingRecordController::entry($b),
                'user_id' => $b->user_id,
                'name' => $b->shooterName(),
                'member_number' => $b->user?->member_number,
                'guest' => $b->isGuest(),
                'host' => $b->host?->name,
            ]),
            'members' => User::query()->orderBy('name')->get(['id', 'name', 'member_number']),
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'user' => $validated['user'] ?? null,
            ],
        ]);
    }

    public function memberPdf(Request $request, User $user): HttpResponse
    {
        $to = $request->date('to')?->toImmutable() ?? CarbonImmutable::today();
        $from = $request->date('from')?->toImmutable() ?? $to->subMonthsNoOverflow(24)->addDay();

        return ShootingRecordController::download(ShootingRecord::for($user), $from, $to);
    }

    /**
     * Mitgliedschaftsbescheinigung für die Folgeprüfung nach § 14 Abs. 4 Satz 3 WaffG.
     */
    public function certificate(User $user): HttpResponse
    {
        $pdf = Pdf::loadView('pdf.membership', [
            'user' => $user,
            'club' => config('verein.name'),
            'clubAddress' => config('verein.address'),
            'createdAt' => CarbonImmutable::now(),
        ])->setPaper('a4');

        return $pdf->download(sprintf('Mitgliedschaftsbescheinigung-%s.pdf', str($user->name)->slug()));
    }
}
