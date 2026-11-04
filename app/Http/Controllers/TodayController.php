<?php

namespace App\Http\Controllers;

use App\Domain\Disciplines;
use App\Domain\Sessions;
use App\Enums\EntryVia;
use App\Enums\SessionEntryStatus;
use App\Enums\WeaponClass;
use App\Enums\WeaponOwner;
use App\Models\LogEntry;
use App\Models\SessionEntry;
use App\Models\SessionParticipant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * „Heute schießen“: Erfassen am eigenen Gerät (UMBAU-SESSION.md, Ablauf am Stand Schritt 4).
 *
 * Der Teilnehmer wird nie vom Client übergeben, sondern immer aus der jüngsten Aktivierung
 * der angemeldeten Person von heute bestimmt. Damit trägt jedes Mitglied nur für sich ein.
 */
class TodayController extends Controller
{
    public function show(Request $request, Sessions $sessions): Response
    {
        /** @var User $user */
        $user = $request->user();
        $participant = $sessions->latestParticipation($user);

        // Kategorien, Vorbelegung und Umfang der Aufsicht gehen immer mit (auch beim Polling),
        // damit nach einer Aktivierung ohne Neuladen das vollständige Formular erscheint.
        $catalog = [
            'last' => fn () => $this->lastEntry($user),
            'weaponClasses' => WeaponClass::options(),
            'disciplines' => Disciplines::options(),
        ];

        if ($participant === null) {
            return Inertia::render('today/Show', ['participation' => null, 'canEnterLicensed' => false, 'unregistered' => false, ...$catalog]);
        }

        $participant->load(['session.supervisor', 'entries' => fn ($q) => $q->orderBy('id')]);
        $session = $participant->session;
        $open = $session->isOpenToday();

        // Alle Kladde-Zeilen der Person an diesem Tag in einer Abfrage: eigene Zeilen, Zeilen, in die
        // ein Eintrag beim Abzeichnen zusammengeführt wurde, und deren Korrekturen (Person und Datum bleiben).
        $rows = $participant->entries->contains(fn (SessionEntry $e) => $e->status === SessionEntryStatus::Signed)
            ? LogEntry::query()
                ->where('user_id', $user->id)
                ->whereDate('shot_on', $session->shot_on->toDateString())
                ->with(['recordedBy:id,name', 'cancelledBy:id,name'])
                ->orderBy('id')
                ->get()
            : new EloquentCollection;
        $corrections = $rows->whereNotNull('corrects_id')->keyBy('corrects_id');

        return Inertia::render('today/Show', [
            'participation' => [
                // Kennung der Aktivierung: Eine neue Aktivierung baut das Formular neu auf (Vorbelegung, Umfang).
                'id' => $participant->id,
                'open' => $open,
                'signed' => ! $session->isOpen(),
                'supervisor' => $session->supervisor->name,
                'activated_at' => $participant->created_at->format('H:i'),
                'entries' => $participant->entries->map(function (SessionEntry $e) use ($open, $user, $rows, $corrections, $session) {
                    $record = $e->status === SessionEntryStatus::Signed
                        ? self::recordState(self::rowOf($e, $rows, $session->signed_at ?? $e->updated_at), $corrections)
                        : null;

                    return [
                        'id' => $e->id,
                        'time' => $e->created_at->format('H:i'),
                        'via' => $e->via->label(),
                        'weapon_class' => $e->weapon_class->label(),
                        'weapon_owner' => $e->weapon_owner?->label(),
                        'discipline' => Disciplines::label($e->discipline),
                        'shots' => $e->shots,
                        'status' => $e->status->value,
                        // nach dem Abzeichnen zählt der Stand der Kladde-Zeile, nicht der Vorstufe (K8)
                        'status_label' => $record !== null && $record['state'] === 'cancelled' ? 'storniert' : $e->status->label(),
                        'reject_reason' => $e->reject_reason,
                        'can_withdraw' => $open && $e->isPending() && $e->via === EntryVia::OwnDevice && $e->entered_by_id === $user->id,
                        'record' => $record,
                    ];
                })->values(),
            ],
            'canEnterLicensed' => $session->supervisor->canSupervise(CarbonImmutable::today(), licensed: true),
            // Aufsicht nicht mehr im Aufsichtsregister (Entzug, Ablauf): die Session nimmt nichts mehr an (V6)
            'unregistered' => ! $sessions->supervisorRegistered($session),
            ...$catalog,
        ]);
    }

    /**
     * Die Kladde-Zeile eines abgezeichneten Eintrags: die eigene, sonst die gleiche Zeile, in die das
     * Abzeichnen ihn zusammengeführt hat (Doppelschutz K5, wie Logbook::findActive): gleiche Person und
     * gleicher Tag (schon in $rows), gleiche Kategorie, eigen/Verein und Disziplin. In der Vorstufe steht
     * '' statt null, das Model liefert null; verglichen wird daher als Text. Unter mehreren passenden
     * Zeilen (Storno, danach Nachtrag oder Korrektur mit gleichem Schlüssel) zählt die, die beim Abzeichnen
     * gültig war; bei gleicher Sekunde die älteste.
     *
     * @param  Collection<array-key, LogEntry>  $rows  Kladde-Zeilen der Person an diesem Tag, nach id
     */
    private static function rowOf(SessionEntry $entry, Collection $rows, CarbonInterface $signedAt): ?LogEntry
    {
        return $rows->first(fn (LogEntry $row) => $row->session_entry_id === $entry->id)
            ?? $rows->first(fn (LogEntry $row) => $row->weapon_class === $entry->weapon_class
                && $row->weapon_owner === $entry->weapon_owner
                && (string) $row->discipline === (string) $entry->discipline
                && $row->created_at->lte($signedAt)
                && ($row->cancelled_at === null || $row->cancelled_at->gte($signedAt)));
    }

    /**
     * Stand der Kladde-Zeile nach dem Abzeichnen (K8), über die ganze Korrekturkette wie in der Kladde
     * des Vereins: maßgeblich ist die letzte Zeile. null bei unveränderter Zeile und wenn keine
     * Kladde-Zeile zu finden ist.
     *
     * @param  Collection<array-key, LogEntry>  $corrections  Korrekturzeilen nach corrects_id
     * @return array{state: 'cancelled', reason: string|null, by: string|null}|array{state: 'corrected', reason: string|null, by: string, weapon_class: string, weapon_owner: string|null, discipline: string|null, shots: int|null}|null
     */
    private static function recordState(?LogEntry $row, Collection $corrections): ?array
    {
        if ($row === null) {
            return null;
        }

        // Je Zeile höchstens eine Korrektur: Korrigiert wird nur eine gültige Zeile, die dabei storniert wird.
        $last = $row;

        for ($depth = 0; $depth < 50 && ! $last->isActive(); $depth++) {
            $next = $corrections->get($last->id);

            if ($next === null) {
                break;
            }

            $last = $next;
        }

        if (! $last->isActive()) {
            return ['state' => 'cancelled', 'reason' => $last->cancel_reason, 'by' => $last->cancelledBy?->name];
        }

        if ($last->is($row)) {
            return null;
        }

        return [
            'state' => 'corrected',
            'reason' => $last->late_reason,
            'by' => $last->recordedBy->name,
            'weapon_class' => $last->weapon_class->label(),
            'weapon_owner' => $last->weapon_owner?->label(),
            'discipline' => Disciplines::label($last->discipline),
            'shots' => $last->shots,
        ];
    }

    public function store(Request $request, Sessions $sessions): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = self::validateEntry($request);

        $participant = $sessions->latestParticipation($user);
        abort_if($participant === null, 403, __('Sie sind heute in keiner Session aktiviert.'));

        $result = $sessions->stage(
            actor: $user,
            participant: $participant,
            via: EntryVia::OwnDevice,
            class: WeaponClass::from($data['weapon_class']),
            owner: WeaponOwner::from($data['weapon_owner']),
            shots: (int) $data['shots'],
            discipline: $data['discipline'] ?? null,
        );

        $entry = $result['entry'];

        // Zurückziehen kann das Mitglied nur die eigene Erfassung am eigenen Gerät.
        $ownEntry = $entry->via === EntryVia::OwnDevice && $entry->entered_by_id === $user->id;

        Inertia::flash('toast', ['type' => $result['duplicate'] ? 'info' : 'success', 'message' => match (true) {
            ! $result['duplicate'] => __('Erfasst um :time Uhr. Zählt nach dem Abzeichnen durch die Aufsicht.', ['time' => $entry->created_at->format('H:i')]),
            $ownEntry => __('Schon erfasst (:shots Schüsse) – zurückziehen und neu erfassen?', ['shots' => $entry->shots ?? '–']),
            default => __('Schon erfasst (:shots Schüsse) über :via. Ändern nur über die Aufsicht.', ['shots' => $entry->shots ?? '–', 'via' => $entry->via->label()]),
        }]);

        return to_route('today.show');
    }

    public function withdraw(Request $request, SessionEntry $entry, Sessions $sessions): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Hier nur die eigene Erfassung am eigenen Gerät; Stand-Einträge zieht die Aufsicht zurück.
        abort_unless($entry->via === EntryVia::OwnDevice && $entry->entered_by_id === $user->id, 403);

        $sessions->withdraw($user, $entry);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Eintrag zurückgezogen. Sie können neu erfassen.')]);

        return to_route('today.show');
    }

    /**
     * Gemeinsame Regeln für das Formular am eigenen Gerät und am Kiosk: Mitglieder geben
     * Kategorie, eigen/Verein und Schüsse (1–999) immer an (E1).
     *
     * @return array{weapon_class: string, weapon_owner: string, shots: int|string, discipline?: string|null}
     */
    public static function validateEntry(Request $request): array
    {
        /** @var array{weapon_class: string, weapon_owner: string, shots: int|string, discipline?: string|null} */
        return $request->validate([
            'weapon_class' => ['required', Rule::enum(WeaponClass::class)],
            'weapon_owner' => ['required', Rule::enum(WeaponOwner::class)],
            'shots' => ['required', 'integer', 'min:1', 'max:'.Sessions::MAX_SHOTS],
            'discipline' => ['nullable', 'string', 'max:50'],
        ], [], [
            'weapon_class' => __('Kategorie'),
            'weapon_owner' => __('Waffe'),
            'shots' => __('Schüsse'),
        ]);
    }

    /**
     * Vorbelegung aus dem letzten eigenen Eintrag: zuerst die Vorstufe, sonst die Kladde.
     *
     * @return array{weapon_class: string, weapon_owner: string|null, discipline: string|null, shots: int|null}|null
     */
    public static function lastEntry(User $user): ?array
    {
        $entry = SessionEntry::query()
            ->whereIn('participant_id', SessionParticipant::query()->select('id')->where('user_id', $user->id))
            ->latest('id')
            ->first();

        if ($entry) {
            return [
                'weapon_class' => $entry->weapon_class->value,
                'weapon_owner' => $entry->weapon_owner?->value,
                'discipline' => $entry->discipline,
                'shots' => $entry->shots,
            ];
        }

        $row = LogEntry::query()->active()->where('user_id', $user->id)->latest('id')->first();

        return $row ? [
            'weapon_class' => $row->weapon_class->value,
            'weapon_owner' => $row->weapon_owner?->value,
            'discipline' => $row->discipline,
            'shots' => $row->shots,
        ] : null;
    }
}
