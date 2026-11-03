<?php

namespace App\Http\Controllers;

use App\Domain\Disciplines;
use App\Domain\Sessions;
use App\Enums\EntryVia;
use App\Enums\WeaponClass;
use App\Enums\WeaponOwner;
use App\Models\LogEntry;
use App\Models\SessionEntry;
use App\Models\SessionParticipant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            return Inertia::render('today/Show', ['participation' => null, 'canEnterLicensed' => false, ...$catalog]);
        }

        $participant->load(['session.supervisor', 'entries' => fn ($q) => $q->orderBy('id')]);
        $session = $participant->session;
        $open = $session->isOpenToday();

        return Inertia::render('today/Show', [
            'participation' => [
                'open' => $open,
                'signed' => ! $session->isOpen(),
                'supervisor' => $session->supervisor->name,
                'activated_at' => $participant->created_at->format('H:i'),
                'entries' => $participant->entries->map(fn (SessionEntry $e) => [
                    'id' => $e->id,
                    'time' => $e->created_at->format('H:i'),
                    'via' => $e->via->label(),
                    'weapon_class' => $e->weapon_class->label(),
                    'weapon_owner' => $e->weapon_owner?->label(),
                    'discipline' => Disciplines::label($e->discipline),
                    'shots' => $e->shots,
                    'status' => $e->status->value,
                    'status_label' => $e->status->label(),
                    'reject_reason' => $e->reject_reason,
                    'can_withdraw' => $open && $e->isPending() && $e->via === EntryVia::OwnDevice && $e->entered_by_id === $user->id,
                ])->values(),
            ],
            'canEnterLicensed' => $session->supervisor->canSupervise(CarbonImmutable::today(), licensed: true),
            ...$catalog,
        ]);
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
