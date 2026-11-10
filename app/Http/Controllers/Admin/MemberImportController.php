<?php

namespace App\Http\Controllers\Admin;

use App\Domain\MemberImport;
use App\Enums\InvitationDelivery;
use App\Http\Controllers\Controller;
use App\Models\User;
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

    public function create(Request $request, MemberImport $import): Response
    {
        $preview = $request->session()->get(self::PREVIEW);

        return Inertia::render('admin/users/Import', [
            'preview' => $preview,
            // Text der Bestätigung aus derselben Quelle wie die Prüfung beim Übernehmen.
            'confirmation' => is_array($preview) ? $import->confirmation($preview['rows'] ?? [])['text'] ?? null : null,
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

        // Wie beim Eintragen von Hand: ohne Bestätigung keine Aufsicht (§ 10 Abs. 3 AWaffV) und
        // kein „volljährig geprüft“ (E12). Die Vorschau bleibt erhalten, damit die Datei nicht
        // neu hochgeladen werden muss.
        $confirmation = $import->confirmation($preview['rows']);
        $data = $request->validate([
            'send_invitations' => ['boolean'],
            'confirmed' => $confirmation !== null ? ['accepted'] : ['boolean'],
        ], [
            'confirmed.accepted' => $confirmation['message'] ?? '',
        ]);

        $request->session()->forget(self::PREVIEW);

        /** @var User $actor */
        $actor = $request->user();
        $result = $import->import($actor, $preview['rows']);

        $invited = (bool) ($data['send_invitations'] ?? false);
        // Ohne E-Mail kein Konto und keine Einladung (K4).
        $withEmail = array_values(array_filter($result['users'], fn (User $user) => $user->email !== null));
        $invitations = [];
        $failed = 0;

        if ($invited) {
            foreach ($withEmail as $user) {
                $url = Invitation::url($user);
                $delivery = Invitation::send($user, $url);
                $invitations[] = [
                    'name' => $user->name,
                    'email' => $user->email,
                    'url' => $url,
                    'mailed' => $delivery === InvitationDelivery::Sent,
                ];
                $failed += $delivery === InvitationDelivery::Failed ? 1 : 0;
            }
        }

        // „verschickt“ nur, wenn jede Mail wirklich hinausging: nicht bei MAIL_MAILER=log oder
        // array und nicht, wenn das Senden mit einem Fehler abbrach.
        $sent = count(array_filter($invitations, fn (array $invitation) => $invitation['mailed']));
        $mailed = $invitations !== [] && $sent === count($invitations);

        $message = __(':created neu angelegt, :updated aktualisiert.', [
            'created' => $result['created'],
            'updated' => $result['updated'],
        ]);

        if ($result['registered'] > 0) {
            $message .= ' '.__(':registered im Aufsichtsregister eingetragen.', ['registered' => $result['registered']]);
        }

        if ($invitations !== []) {
            $message .= ' '.match (true) {
                $mailed => trans_choice('{1} Eine Einladung per E-Mail verschickt.|[2,*] :count Einladungen per E-Mail verschickt.', count($invitations), ['count' => count($invitations)]),
                $failed === 0 => __('Der Mailversand ist nicht eingerichtet: Bitte geben Sie die Einladungslinks selbst weiter.'),
                $sent === 0 => trans_choice('{1} Die Einladung konnte nicht per E-Mail verschickt werden: Bitte geben Sie den Einladungslink selbst weiter.|[2,*] Die Einladungen konnten nicht per E-Mail verschickt werden: Bitte geben Sie die Einladungslinks selbst weiter.', count($invitations)),
                default => __(':sent von :count Einladungen per E-Mail verschickt, bei :failed ist der Versand fehlgeschlagen: Bitte geben Sie diese Links selbst weiter.', [
                    'sent' => $sent,
                    'count' => count($invitations),
                    'failed' => $failed,
                ]),
            };
        }

        if ($result['skipped'] > 0) {
            $message .= ' '.trans_choice(
                '{1} Eine Zeile ausgelassen, weil sich die Daten seit der Vorschau geändert haben.|[2,*] :count Zeilen ausgelassen, weil sich die Daten seit der Vorschau geändert haben.',
                $result['skipped'],
                ['count' => $result['skipped']],
            );
        }

        Inertia::flash('toast', ['type' => $result['skipped'] > 0 || $failed > 0 ? 'warning' : 'success', 'message' => $message]);

        return to_route('admin.users.import')->with(self::RESULT, [
            'created' => $result['created'],
            'updated' => $result['updated'],
            'skipped' => $result['skipped'],
            'registered' => $result['registered'],
            'invited' => $invited,
            'mailed' => $mailed,
            'failed' => $failed,
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
