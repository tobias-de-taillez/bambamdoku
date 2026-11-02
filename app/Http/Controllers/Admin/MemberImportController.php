<?php

namespace App\Http\Controllers\Admin;

use App\Domain\MemberImport;
use App\Http\Controllers\Controller;
use App\Notifications\UserInvited;
use App\Support\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MemberImportController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('admin/users/Import', [
            'preview' => $request->session()->get('import.preview'),
        ]);
    }

    /**
     * Schritt 1: Datei prüfen und Vorschau zeigen, noch nichts speichern.
     */
    public function preview(Request $request, MemberImport $import): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:2048', 'mimes:csv,txt']], [], ['file' => __('Datei')]);

        $result = $import->parse((string) $request->file('file')?->get());
        $request->session()->put('import.preview', $result);

        return to_route('admin.users.import');
    }

    /**
     * Schritt 2: die geprüften Zeilen übernehmen.
     */
    public function store(Request $request, MemberImport $import): RedirectResponse
    {
        $data = $request->validate(['send_invitations' => ['boolean']]);
        $preview = $request->session()->pull('import.preview');

        if (! is_array($preview) || ($preview['rows'] ?? []) === []) {
            return to_route('admin.users.import');
        }

        $result = $import->import($request->user(), $preview['rows']);

        if ($data['send_invitations'] ?? false) {
            foreach ($result['users'] as $user) {
                $user->notify(new UserInvited(Invitation::url($user)));
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':created neu angelegt, :updated aktualisiert.', [
            'created' => $result['created'],
            'updated' => $result['updated'],
        ])]);

        return to_route('admin.users.index');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget('import.preview');

        return to_route('admin.users.import');
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            echo "\xEF\xBB\xBF";
            echo "Vorname;Nachname;E-Mail;Mitgliedsnummer;Eintritt;Aufsicht bis\r\n";
            echo "Erika;Musterfrau;erika@example.org;1001;01.03.2015;31.12.2027\r\n";
        }, 'mitglieder-vorlage.csv', ['Content-Type' => 'text/csv; charset=utf-8']);
    }
}
