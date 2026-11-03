<?php

namespace App\Http\Controllers\Admin;

use App\Domain\ShootingDayImport;
use App\Http\Controllers\Controller;
use App\Models\Range;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShootingDayImportController extends Controller
{
    /** Eigener Schlüssel, damit sich die Vorschau nicht mit dem Mitglieder-Import mischt. */
    private const PREVIEW = 'shooting_days_import.preview';

    public function create(Request $request): Response
    {
        return Inertia::render('admin/shooting-days/Import', [
            'preview' => $request->session()->get(self::PREVIEW),
            'ranges' => Range::ordered()->where('active', true)->pluck('name'),
        ]);
    }

    /**
     * Kopfzeile und zwei Beispielzeilen mit festen Inhalten (keine Formeln, keine echten Daten).
     */
    public function template(): StreamedResponse
    {
        $month = CarbonImmutable::today()->addMonthNoOverflow()->startOfMonth();
        $first = $month->nthOfMonth(1, CarbonImmutable::SATURDAY);
        $third = $month->nthOfMonth(3, CarbonImmutable::SATURDAY);

        return response()->streamDownload(function () use ($first, $third) {
            echo "\xEF\xBB\xBF";
            echo "Datum;Beginn;Ende;Titel;Stände;Gäste erlaubt;Hinweise\r\n";
            echo ($first ? $first->format('d.m.Y') : '').";10:00;14:00;Training;25 m Kurzwaffe|50 m Gewehr;ja;\r\n";
            echo ($third ? $third->format('d.m.Y') : '').";14:00;18:00;Vereinsmeisterschaft;50 m Gewehr;nein;Anmeldung bis Donnerstag\r\n";
        }, 'schiesstage-vorlage.csv', ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    /**
     * Schritt 1: Datei prüfen und Vorschau zeigen, noch nichts speichern.
     */
    public function preview(Request $request, ShootingDayImport $import): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:2048', 'mimes:csv,txt']], [], ['file' => __('Datei')]);

        $request->session()->put(self::PREVIEW, $import->parse((string) $request->file('file')?->get()));

        return to_route('admin.shooting-days.import');
    }

    /**
     * Schritt 2: die geprüften Zeilen in einem Rutsch anlegen.
     */
    public function store(Request $request, ShootingDayImport $import): RedirectResponse
    {
        $preview = $request->session()->pull(self::PREVIEW);

        if (! is_array($preview) || ! is_array($preview['rows'] ?? null) || $preview['rows'] === []) {
            return to_route('admin.shooting-days.import');
        }

        $result = $import->import($request->user(), $preview['rows']);

        $message = trans_choice('{0} Kein Schießtag angelegt.|{1} Schießtag angelegt.|[2,*] :count Schießtage angelegt.', $result['created'], ['count' => $result['created']]);

        if ($result['skipped'] > 0) {
            $message .= ' '.trans_choice(
                '{1} Eine Zeile ausgelassen, weil sich die Daten seit der Vorschau geändert haben.|[2,*] :count Zeilen ausgelassen, weil sich die Daten seit der Vorschau geändert haben.',
                $result['skipped'],
                ['count' => $result['skipped']],
            );
        }

        Inertia::flash('toast', ['type' => $result['skipped'] > 0 ? 'warning' : 'success', 'message' => $message]);

        return to_route('admin.shooting-days.index');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget(self::PREVIEW);

        return to_route('admin.shooting-days.import');
    }
}
