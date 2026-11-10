<?php

namespace App\Http\Controllers\Admin;

use App\Domain\HelpRequests;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\AuditEntry;
use App\Models\HelpRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rückmeldungen aus „Report Bug / Request Feature“ für den Vorstand.
 */
class HelpRequestController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->query('status');
        $status = in_array($status, [HelpRequest::OPEN, HelpRequest::DONE, 'alle'], true) ? $status : HelpRequest::OPEN;

        $requests = HelpRequest::query()
            ->with('user:id,name')
            ->when($status !== 'alle', fn ($q) => $q->where('status', $status))
            ->latest('created_at')
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (HelpRequest $h) => [
                'id' => $h->id,
                'reference' => $h->reference,
                'at' => $h->created_at->toIso8601String(),
                'user' => $h->user?->name,
                'url' => $h->url,
                'component' => $h->component,
                'message' => mb_strimwidth($h->message, 0, 160, '…'),
                'status' => $h->status,
            ]);

        return Inertia::render('admin/help-requests/Index', [
            'requests' => $requests,
            'status' => $status,
            'openCount' => HelpRequest::query()->where('status', HelpRequest::OPEN)->count(),
        ]);
    }

    public function show(HelpRequest $helpRequest, HelpRequests $helpRequests): Response
    {
        $report = $helpRequests->report($helpRequest);

        return Inertia::render('admin/help-requests/Show', [
            'helpRequest' => [
                'id' => $helpRequest->id,
                'reference' => $helpRequest->reference,
                'at' => $helpRequest->created_at->toIso8601String(),
                'user' => $helpRequest->user?->name,
                'role' => self::roleLabel(data_get($report, 'nutzer.rolle')),
                'url' => $helpRequest->url,
                'component' => $helpRequest->component,
                'message' => $helpRequest->message,
                'status' => $helpRequest->status,
                'resolvedAt' => $helpRequest->resolved_at?->toIso8601String(),
                'path' => $helpRequest->path,
                'ip' => data_get($report, 'ip'),
                'browser' => data_get($report, 'browser'),
                'hasScreenshot' => $helpRequests->screenshotPath($helpRequest) !== null,
            ],
            // Als Text, damit der Zustand einer Meldung nicht als Props der Seite in die nächste Meldung wandert.
            'stateJson' => $report === null ? null : (string) json_encode($report['state'] ?? null, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    /**
     * Die Rolle aus meldung.json auf Deutsch, wie in der Mitgliederverwaltung. Gespeichert ist der Wert
     * des Enums („member“); übersetzt wird erst beim Anzeigen, damit auch ältere Meldungen passen.
     * Ein unbekannter Wert bleibt, wie er ist.
     */
    private static function roleLabel(mixed $role): ?string
    {
        if (! is_string($role) || $role === '') {
            return null;
        }

        return match (Role::tryFrom($role)) {
            Role::Admin => __('Administrator'),
            Role::Member => __('Mitglied'),
            Role::Guest => __('Gast'),
            Role::Applicant => __('Antrag'),
            null => $role,
        };
    }

    public function screenshot(HelpRequest $helpRequest, HelpRequests $helpRequests): StreamedResponse
    {
        $path = $helpRequests->screenshotPath($helpRequest);
        abort_if($path === null, 404);

        return HelpRequests::disk()->response($path, 'screenshot-'.$helpRequest->reference.'.jpg', [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function update(Request $request, HelpRequest $helpRequest, HelpRequests $helpRequests): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([HelpRequest::OPEN, HelpRequest::DONE])],
        ]);

        $helpRequests->setStatus($helpRequest, $data['status']);

        Inertia::flash('toast', ['type' => 'success', 'message' => $data['status'] === HelpRequest::DONE
            ? __('Rückmeldung :ref als erledigt markiert.', ['ref' => $helpRequest->reference])
            : __('Rückmeldung :ref wieder offen.', ['ref' => $helpRequest->reference])]);

        return back();
    }

    public function destroy(Request $request, HelpRequest $helpRequest, HelpRequests $helpRequests): RedirectResponse
    {
        $reference = $helpRequest->reference;
        $helpRequests->delete($helpRequest);
        AuditEntry::record($request->user(), 'help_request.deleted', null, sprintf('Rückmeldung %s gelöscht', $reference));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rückmeldung :ref gelöscht.', ['ref' => $reference])]);

        return to_route('admin.help-requests.index');
    }
}
