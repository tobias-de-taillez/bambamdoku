<?php

namespace App\Http\Controllers\Admin;

use App\Domain\MemberImport;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\UserInvited;
use App\Support\Invitation;
use App\Support\MailDelivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mitglieder per CSV: erst prüfen (Vorschau in der Sitzung), dann übernehmen.
 */
class MemberImportController extends Controller
{
    private const PREVIEW = 'member_import.preview';

    private const RESULT = 'member_import.result';

    public function create(Request $request): Response
    {
        return Inertia::render('admin/users/Import', [
            'preview' => $request->session()->get(self::PREVIEW),
            'result' => $request->session()->get(self::RESULT),
            // Ohne echten Mailer sagt die Seite vorher, dass die Links weitergegeben werden müssen.
            'mailDelivers' => MailDelivery::active(),
        ]);
    }

    /**
     * Schritt 1: Datei prüfen und Vorschau zeigen, noch nichts speichern.
     */
    public function preview(Request $request, MemberImport $import): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:2048', 'mimes:csv,txt']], [], ['file' => __('Datei')]);

        /** @var User $actor */
        $actor = $request->user();
        $result = $import->parse((string) $request->file('file')?->get(), $actor);
        $request->session()->put(self::PREVIEW, $result);

        return to_route('admin.users.import');
    }

    /**
     * Schritt 2: die geprüften Zeilen übernehmen. Zeilen mit Fehlern bleiben draußen.
     */
    public function store(Request $request, MemberImport $import): RedirectResponse
    {
        $preview = $request->session()->get(self::PREVIEW);

        if (! is_array($preview) || ($preview['rows'] ?? []) === []) {
            return to_route('admin.users.import');
        }

        // Wie beim Eintragen von Hand (§ 10 Abs. 3 AWaffV): ohne Bestätigung keine Aufsicht.
        // Die Vorschau bleibt erhalten, damit die Datei nicht neu hochgeladen werden muss.
        $data = $request->validate([
            'send_invitations' => ['boolean'],
            'confirmed' => $import->marksSupervisors($preview['rows']) ? ['accepted'] : ['boolean'],
        ], [
            'confirmed.accepted' => __('Bitte bestätigen Sie Volljährigkeit und Sachkunde für alle als Aufsicht markierten Personen.'),
        ]);

        $request->session()->forget(self::PREVIEW);

        /** @var User $actor */
        $actor = $request->user();
        $result = $import->import($actor, $preview['rows']);

        $invited = (bool) ($data['send_invitations'] ?? false);
        // Ohne E-Mail kein Konto und keine Einladung (K4).
        $withEmail = array_values(array_filter($result['users'], fn (User $user) => $user->email !== null));
        $invitations = [];

        if ($invited) {
            foreach ($withEmail as $user) {
                $url = Invitation::url($user);
                $user->notify(new UserInvited($url));
                $invitations[] = ['name' => $user->name, 'email' => $user->email, 'url' => $url];
            }
        }

        // „verschickt“ nur, wenn die Mail wirklich rausgeht (nicht bei MAIL_MAILER=log oder array).
        $mailed = $invitations !== [] && MailDelivery::active();

        $message = __(':created neu angelegt, :updated aktualisiert.', [
            'created' => $result['created'],
            'updated' => $result['updated'],
        ]);

        if ($result['registered'] > 0) {
            $message .= ' '.__(':registered im Aufsichtsregister eingetragen.', ['registered' => $result['registered']]);
        }

        if ($invitations !== []) {
            $message .= ' '.($mailed
                ? trans_choice('{1} Eine Einladung per E-Mail verschickt.|[2,*] :count Einladungen per E-Mail verschickt.', count($invitations), ['count' => count($invitations)])
                : __('Der Mailversand ist nicht eingerichtet: Bitte geben Sie die Einladungslinks selbst weiter.'));
        }

        if ($result['skipped'] > 0) {
            $message .= ' '.trans_choice(
                '{1} Eine Zeile ausgelassen, weil sich die Daten seit der Vorschau geändert haben.|[2,*] :count Zeilen ausgelassen, weil sich die Daten seit der Vorschau geändert haben.',
                $result['skipped'],
                ['count' => $result['skipped']],
            );
        }

        Inertia::flash('toast', ['type' => $result['skipped'] > 0 ? 'warning' : 'success', 'message' => $message]);

        return to_route('admin.users.import')->with(self::RESULT, [
            'created' => $result['created'],
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
            'registered' => $result['registered'],
            'invited' => $invited,
            'mailed' => $mailed,
            'invitations' => $invitations,
            'uninvited' => $invited ? 0 : count($withEmail),
        ]);
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget(self::PREVIEW);

        return to_route('admin.users.import');
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function () {
            echo MemberImport::template();
        }, 'mitglieder-vorlage.csv', ['Content-Type' => 'text/csv; charset=utf-8']);
    }
}
