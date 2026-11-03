<?php

namespace App\Http\Controllers;

use App\Domain\Disciplines;
use App\Domain\Sessions;
use App\Enums\EntryVia;
use App\Enums\WeaponClass;
use App\Enums\WeaponOwner;
use App\Models\SessionParticipant;
use App\Models\ShootingSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kiosk über den Session-Link, ohne Anmeldung (UMBAU-SESSION.md, Ablauf am Stand Schritt 5, E2).
 *
 * - Nur Gäste (Middleware `guest`), Throttle je Gerät. Gültig nur bei offener Session von heute.
 * - Ein ersetzter Link gibt 404, ein abgezeichneter oder verfallener zeigt „Session beendet“.
 * - Liste nur mit Name und Mitgliedsnummer der Mitglieder dieser Session; Gäste fehlen.
 * - Eintragen nur für sich nach richtiger PIN; nur Anlegen, kein Zurückziehen.
 * - Je Session und Teilnehmer 5 PIN-Versuche, dann 15 Minuten Sperre.
 */
class KioskController extends Controller
{
    public const MAX_ATTEMPTS = 5;

    public const LOCK_SECONDS = 900;

    public function show(string $token): Response
    {
        $session = $this->resolve($token);

        if (! $session->isOpenToday()) {
            return Inertia::render('kiosk/Show', ['ended' => true, 'participants' => []]);
        }

        $session->load('supervisor');

        $participants = SessionParticipant::query()
            ->where('session_id', $session->id)
            ->whereNotNull('user_id')
            ->with('user:id,name,member_number,kiosk_pin_hash,active')
            ->get()
            ->filter(fn (SessionParticipant $p) => $p->user !== null && $p->user->active)
            ->sortBy(fn (SessionParticipant $p) => mb_strtolower((string) $p->user?->name))
            ->values();

        return Inertia::render('kiosk/Show', [
            'ended' => false,
            'token' => $token,
            'supervisor' => $session->supervisor->name,
            'participants' => $participants->map(fn (SessionParticipant $p) => [
                'id' => $p->id,
                'name' => $p->user?->name,
                'member_number' => $p->user?->member_number,
                'has_pin' => $p->user?->kiosk_pin_hash !== null,
            ]),
            'canEnterLicensed' => $session->supervisor->canSupervise(CarbonImmutable::today(), licensed: true),
            'weaponClasses' => WeaponClass::options(),
            'disciplines' => Disciplines::options(),
        ]);
    }

    /**
     * PIN vorab prüfen, damit das Formular erst nach richtiger PIN erscheint.
     * Gespeichert wird dabei nichts; das Eintragen prüft die PIN noch einmal.
     */
    public function verify(Request $request, string $token): RedirectResponse
    {
        $session = $this->resolve($token);

        if (! $session->isOpenToday()) {
            return to_route('kiosk.show', $token);
        }

        [$participant, $member] = $this->checkPin($request, $session);

        Inertia::flash('kiosk', [
            'participant_id' => $participant->id,
            'last' => TodayController::lastEntry($member),
        ]);

        return to_route('kiosk.show', $token);
    }

    public function store(Request $request, string $token, Sessions $sessions): RedirectResponse
    {
        $session = $this->resolve($token);

        if (! $session->isOpenToday()) {
            return to_route('kiosk.show', $token);
        }

        [$participant, $member] = $this->checkPin($request, $session);
        $data = TodayController::validateEntry($request);

        $result = $sessions->stage(
            actor: $member,
            participant: $participant,
            via: EntryVia::Kiosk,
            class: WeaponClass::from($data['weapon_class']),
            owner: WeaponOwner::from($data['weapon_owner']),
            shots: (int) $data['shots'],
            discipline: $data['discipline'] ?? null,
        );

        $entry = $result['entry'];

        Inertia::flash('toast', ['type' => $result['duplicate'] ? 'info' : 'success', 'message' => $result['duplicate']
            ? __('Schon erfasst (:shots Schüsse). Ändern nur über die Aufsicht.', ['shots' => $entry->shots ?? '–'])
            : __(':name erfasst um :time Uhr.', ['name' => $member->name, 'time' => $entry->created_at->format('H:i')])]);

        return to_route('kiosk.show', $token);
    }

    /**
     * Prüft Teilnahme und PIN. Während der Sperre wird auch eine richtige PIN abgewiesen.
     *
     * @return array{0: SessionParticipant, 1: User}
     */
    private function checkPin(Request $request, ShootingSession $session): array
    {
        $data = $request->validate([
            'participant_id' => ['required', 'integer'],
            'pin' => ['required', 'string', 'max:6'],
        ], [], ['pin' => __('PIN')]);

        $participant = SessionParticipant::query()
            ->where('session_id', $session->id)
            ->whereNotNull('user_id')
            ->with('user')
            ->find((int) $data['participant_id']);

        $member = $participant?->user;

        if ($participant === null || $member === null || ! $member->active) {
            abort(404);
        }

        $key = sprintf('kiosk-pin:%d:%d', $session->id, $participant->id);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages(['pin' => __('Zu viele Fehlversuche. Für :minutes Minuten gesperrt – bitte bei der Aufsicht eintragen.', [
                'minutes' => max(1, (int) ceil(RateLimiter::availableIn($key) / 60)),
            ])]);
        }

        $hash = $member->kiosk_pin_hash;

        if ($hash === null) {
            throw ValidationException::withMessages(['pin' => __('Keine Kiosk-PIN gesetzt – bitte bei der Aufsicht eintragen.')]);
        }

        if (! Hash::check((string) $data['pin'], $hash)) {
            RateLimiter::hit($key, self::LOCK_SECONDS);

            throw ValidationException::withMessages(['pin' => __('PIN falsch.')]);
        }

        RateLimiter::clear($key);

        return [$participant, $member];
    }

    /**
     * Die Session zum Link. Ein unbekannter oder ersetzter Link gibt 404.
     */
    private function resolve(string $token): ShootingSession
    {
        $session = $token === '' ? null : ShootingSession::query()->where('kiosk_token_hash', hash('sha256', $token))->first();

        abort_if($session === null, 404);

        return $session;
    }
}
