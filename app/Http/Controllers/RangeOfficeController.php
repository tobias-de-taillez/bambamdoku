<?php

namespace App\Http\Controllers;

use App\Domain\Bookings;
use App\Domain\Disciplines;
use App\Domain\Schedule;
use App\Domain\Sessions;
use App\Domain\TimeWindow;
use App\Enums\BookingStatus;
use App\Enums\EntryVia;
use App\Enums\SessionEntryStatus;
use App\Enums\SessionStatus;
use App\Enums\WeaponClass;
use App\Enums\WeaponOwner;
use App\Models\Booking;
use App\Models\LogEntry;
use App\Models\Range;
use App\Models\SessionEntry;
use App\Models\SessionParticipant;
use App\Models\ShootingDay;
use App\Models\ShootingSession;
use App\Models\Supervision;
use App\Models\User;
use App\Support\ArrivalCode;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Aufsichtsmodus: die Standansicht für den heutigen Schießtag mit der Schieß-Session der Aufsicht.
 */
class RangeOfficeController extends Controller
{
    public function show(Request $request, Bookings $bookings, Sessions $sessions): Response
    {
        /** @var User $user */
        $user = $request->user();

        $days = ShootingDay::query()
            ->whereDate('date', today())
            ->whereNull('cancelled_at')
            ->when(! $user->isAdmin(), fn ($q) => $q->whereHas('supervisions', fn ($q) => $q->where('user_id', $user->id)))
            ->orderBy('starts_at')
            ->get();

        $requested = $request->integer('tag');
        $day = $days->firstWhere('id', $requested)
            ?? $days->first(fn (ShootingDay $d) => ! $d->isOver())
            ?? $days->last();

        if (! $day instanceof ShootingDay) {
            return Inertia::render('range-office/Show', [
                'day' => null,
                'days' => [],
                ...$this->entering($user, $sessions),
            ]);
        }

        $day->load(['ranges', 'supervisions.user', 'bookings.user', 'bookings.host', 'bookings.range']);
        $schedule = new Schedule($day);
        $now = TimeWindow::minutes(now()->format('H:i'));

        // Nur Stände, für die die eigene Aufsicht zählt (G9): einen Stand nur für Kurz- oder Langwaffe
        // sieht nicht, wer nur erlaubnisfrei registriert ist, auch nicht mit Aufsicht für die ganze Anlage.
        $myRanges = $user->isAdmin()
            ? $day->ranges
            : $day->ranges->filter(fn (Range $range) => $day->supervisions->contains(fn (Supervision $s) => $s->user_id === $user->id && Schedule::countsForRange($s, $range, $day->date)));

        $activated = $sessions->openSessionOf($user)?->participants()->whereNotNull('booking_id')->pluck('booking_id')->all() ?? [];

        $ranges = $myRanges->sortBy([['position', 'asc'], ['name', 'asc']])->values()->map(function (Range $range) use ($day, $schedule, $now, $user, $bookings, $activated) {
            $rangeBookings = $day->bookings
                ->each(fn (Booking $b) => $b->setRelation('shootingDay', $day))
                ->filter(fn (Booking $b) => $b->range_id === $range->id && $b->status !== BookingStatus::Cancelled)
                ->sortBy([['starts_at', 'asc'], ['id', 'asc']])
                ->values();

            $current = collect($schedule->slots())->first(fn (TimeWindow $slot) => $slot->start <= $now && $now < $slot->end);

            return [
                'id' => $range->id,
                'name' => $range->name,
                'lanes' => $range->lanes,
                'weapon_types' => $range->weapon_types->map->value->values(),
                'present' => $rangeBookings->filter(fn (Booking $b) => $b->status === BookingStatus::CheckedIn && $b->window()->start <= $now && $now < $b->window()->end)->count(),
                'free_now' => $current ? $schedule->freeLanes($range, $current) : null,
                'bookings' => $rangeBookings->map(fn (Booking $b) => [
                    'id' => $b->id,
                    'name' => $b->shooterName(),
                    'member_number' => $b->user?->member_number,
                    'guest' => $b->isGuest(),
                    'host' => $b->host?->name,
                    'starts_at' => substr($b->starts_at, 0, 5),
                    'ends_at' => substr($b->ends_at, 0, 5),
                    'weapon_type' => $b->weapon_type->value,
                    'club_weapon' => $b->club_weapon,
                    'status' => $b->status->value,
                    'in_session' => in_array($b->id, $activated, true),
                    'adult_confirmed' => $b->user === null || $b->user->adult_confirmed_at !== null,
                    'arrived' => $b->arrived_at !== null,
                    'now' => $b->window()->start <= $now && $now < $b->window()->end,
                    'can_manage' => $bookings->canRunTheRange($user, $b),
                ])->values(),
            ];
        });

        $slots = array_map(fn (TimeWindow $slot) => ['start' => $slot->startString(), 'end' => $slot->endString()], $schedule->slots());

        return Inertia::render('range-office/Show', [
            'day' => [
                'id' => $day->id,
                'date' => $day->date->toDateString(),
                'starts_at' => substr($day->starts_at, 0, 5),
                'ends_at' => substr($day->ends_at, 0, 5),
                'title' => $day->title,
                'guests_allowed' => $day->guests_allowed,
                'supervisors' => $day->supervisions->map(fn (Supervision $s) => [
                    'name' => $s->user->name,
                    'starts_at' => substr($s->starts_at, 0, 5),
                    'ends_at' => substr($s->ends_at, 0, 5),
                    'range' => $s->range_id ? $day->ranges->firstWhere('id', $s->range_id)?->name : null,
                ])->values(),
            ],
            'days' => $days->map(fn (ShootingDay $d) => ['id' => $d->id, 'label' => substr($d->starts_at, 0, 5).'–'.substr($d->ends_at, 0, 5).($d->title ? ' '.$d->title : '')])->values(),
            'ranges' => $ranges,
            'slots' => $slots,
            'now' => now()->format('H:i'),
            'arrivalUrl' => route('range-office.arrive', ['shootingDay' => $day->id, 'code' => ArrivalCode::current($day)]),
            ...$this->entering($user, $sessions),
        ]);
    }

    /**
     * Was die Ansicht für die Session braucht: Berechtigung, die offene Session mit Teilnehmern und
     * Einträgen („erfasst um“, „erfasst über“), die heute abgezeichneten und die Personensuche.
     * Ohne gültige Registerzeile statt der Session nur `lockedSession`: seit wann sie läuft und wie
     * viele Einträge offen sind (V6). Immer gesetzt, auch als null, damit das Polling sie leert.
     *
     * @return array<string, mixed>
     */
    private function entering(User $user, Sessions $sessions): array
    {
        $today = CarbonImmutable::today();
        $canEnter = $user->canSupervise($today);
        $open = $sessions->openSessionOf($user);
        $session = $canEnter ? $open : null;
        $locked = $canEnter ? null : $open;

        // Vorbelegung je Mitglied aus dessen gültigem Kladde-Eintrag vom jüngsten Schießtag (K3). Ist die jüngste
        // Zeile storniert, gilt die davor; die Vorstufe zählt hier nicht (abgelehnte Einträge kommen nie in die
        // Kladde). Nach Schießtag, nicht nach id: Korrekturen und Nachträge für ältere Tage sind neue Zeilen.
        $ranked = LogEntry::query()
            ->active()
            ->whereNotNull('user_id')
            ->selectRaw('id, row_number() over (partition by user_id order by shot_on desc, id desc) as nr');
        $last = LogEntry::query()
            ->whereIn('id', DB::query()->fromSub($ranked, 'ranked')->where('nr', 1)->select('id'))
            ->get(['user_id', 'weapon_class', 'weapon_owner', 'discipline', 'shots'])
            ->keyBy('user_id');

        $signed = ! $canEnter ? collect() : ShootingSession::query()
            ->where('supervisor_id', $user->id)
            ->where('status', SessionStatus::Signed->value)
            ->whereDate('shot_on', $today)
            ->withCount(['entries as signed_count' => fn ($q) => $q->where('session_entries.status', SessionEntryStatus::Signed->value)])
            ->orderBy('signed_at')
            ->get();

        return [
            'canEnter' => $canEnter,
            'canEnterLicensed' => $user->canSupervise($today, licensed: true),
            'session' => $session ? $this->presentSession($session, $user) : null,
            'lockedSession' => $locked ? [
                'started_at' => $locked->created_at->format('H:i'),
                'pending' => SessionEntry::query()
                    ->whereHas('participant', fn ($q) => $q->where('session_id', $locked->id))
                    ->where('status', SessionEntryStatus::Pending->value)
                    ->count(),
            ] : null,
            'signedSessions' => $signed->map(fn (ShootingSession $s) => [
                'id' => $s->id,
                'started_at' => $s->created_at->format('H:i'),
                'signed_at' => $s->signed_at?->format('H:i'),
                'entries' => (int) $s->getAttribute('signed_count'),
            ])->values(),
            // Namen nur für Personen, die eine Session führen dürfen; nie die eigene Person.
            'members' => ! $canEnter ? [] : User::query()
                ->where('active', true)
                ->whereKeyNot($user->id)
                ->orderBy('name')
                ->get(['id', 'name', 'member_number', 'adult_confirmed_at'])
                ->map(fn (User $m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'member_number' => $m->member_number,
                    'adult_confirmed' => $m->adult_confirmed_at !== null,
                    'last' => isset($last[$m->id]) ? [
                        'weapon_class' => $last[$m->id]->weapon_class->value,
                        'weapon_owner' => $last[$m->id]->weapon_owner?->value,
                        'discipline' => $last[$m->id]->discipline,
                        'shots' => $last[$m->id]->shots,
                    ] : null,
                ]),
            'weaponClasses' => WeaponClass::options(),
            'disciplines' => Disciplines::options(),
            'rejectReasons' => self::REJECT_REASONS,
        ];
    }

    /** Schnellgründe zum Ablehnen; ein eigener Grund geht immer. */
    private const REJECT_REASONS = ['Falsche Kategorie', 'Falsche Schusszahl', 'Doppelt erfasst', 'Nicht geschossen'];

    /**
     * @return array<string, mixed>
     */
    private function presentSession(ShootingSession $session, User $user): array
    {
        $session->load(['participants' => fn ($q) => $q->orderBy('created_at')->orderBy('id'), 'participants.user:id,name,member_number', 'participants.entries.enteredBy:id,name']);

        return [
            'id' => $session->id,
            'started_at' => $session->created_at->format('H:i'),
            'participants' => $session->participants->map(fn (SessionParticipant $p) => [
                'id' => $p->id,
                'user_id' => $p->user_id,
                'name' => $p->name(),
                'member_number' => $p->user?->member_number,
                'guest' => $p->isGuest(),
                'from_booking' => $p->booking_id !== null,
                'activated_at' => $p->created_at->format('H:i'),
                'removable' => $p->entries->isEmpty(),
                'entries' => $p->entries->sortBy('id')->values()->map(fn (SessionEntry $e) => [
                    'id' => $e->id,
                    'time' => $e->created_at->format('H:i'),
                    'via' => $e->via->label(),
                    'entered_by' => $e->enteredBy?->name,
                    'weapon_class' => $e->weapon_class->label(),
                    'weapon_owner' => $e->weapon_owner?->label(),
                    'discipline' => Disciplines::label($e->discipline),
                    'shots' => $e->shots,
                    'status' => $e->status->value,
                    'status_label' => $e->status->label(),
                    'reject_reason' => $e->reject_reason,
                    'can_withdraw' => $e->isPending() && $e->entered_by_id === $user->id && $e->via === EntryVia::Stand,
                ]),
            ])->values(),
        ];
    }

    public function startSession(Request $request, Sessions $sessions): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $session = $sessions->open($actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Session läuft seit :time Uhr.', ['time' => $session->created_at->format('H:i')])]);

        return back();
    }

    /**
     * Kiosk-Link für ein anderes Gerät. Das Token gibt es nur jetzt im Klartext; ein neuer Link ersetzt den alten.
     */
    public function kioskLink(Request $request, ShootingSession $shootingSession, Sessions $sessions): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $token = $sessions->issueKioskToken($actor, $shootingSession);

        Inertia::flash('kioskUrl', route('kiosk.show', $token));

        return back();
    }

    /**
     * „Kiosk auf diesem Gerät“: meldet nur dieses Gerät ab und öffnet den Kiosk-Link.
     * Bewusst nicht logout(), das das Remember-Token des Kontos auf allen Geräten wechselt.
     */
    public function kioskHere(Request $request, ShootingSession $shootingSession, Sessions $sessions): SymfonyResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $token = $sessions->issueKioskToken($actor, $shootingSession);

        Auth::guard('web')->logoutCurrentDevice();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Inertia::location(route('kiosk.show', $token));
    }

    public function addMember(Request $request, ShootingSession $shootingSession, Sessions $sessions): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'adult_confirmed' => ['boolean'],
        ]);

        $participant = $sessions->addMember($actor, $shootingSession, User::query()->findOrFail((int) $data['user_id']), (bool) ($data['adult_confirmed'] ?? false));

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name aktiviert.', ['name' => $participant->name()])]);

        return back();
    }

    public function addGuest(Request $request, ShootingSession $shootingSession, Sessions $sessions): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $data = $request->validate([
            'guest_name' => ['required', 'string', 'max:100'],
            'guest_adult' => ['boolean'],
        ], [], ['guest_name' => __('Name des Gastes')]);

        $participant = $sessions->addGuest($actor, $shootingSession, $data['guest_name'], (bool) ($data['guest_adult'] ?? false));

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name aktiviert.', ['name' => $participant->name()])]);

        return back();
    }

    public function removeParticipant(Request $request, SessionParticipant $participant, Sessions $sessions): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $sessions->removeParticipant($actor, $participant);

        return back();
    }

    /**
     * Erfassen durch die Aufsicht (`via=stand`), z. B. für Gäste und Mitglieder ohne PIN.
     */
    public function stage(Request $request, SessionParticipant $participant, Sessions $sessions): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $data = $this->validateEntry($request);

        $result = $sessions->stage(
            actor: $actor,
            participant: $participant,
            via: EntryVia::Stand,
            class: WeaponClass::from($data['weapon_class']),
            owner: isset($data['weapon_owner']) ? WeaponOwner::from($data['weapon_owner']) : null,
            shots: isset($data['shots']) ? (int) $data['shots'] : null,
            discipline: $data['discipline'] ?? null,
        );

        $this->flashStaged($result['entry'], $result['duplicate'], $participant->name());

        return back();
    }

    /**
     * Eintragen in einem Schritt: aktiviert die Person in der offenen Session (falls noch nicht)
     * und erfasst den Eintrag über die Aufsicht.
     */
    public function enter(Request $request, Sessions $sessions): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'required_without:guest_name'],
            'guest_name' => ['nullable', 'string', 'max:100', 'required_without:user_id'],
            'guest_adult' => ['boolean'],
            'adult_confirmed' => ['boolean'],
        ], [
            'user_id.required_without' => __('Bitte ein Mitglied wählen oder einen Gast eintragen.'),
            'guest_name.required_without' => __('Bitte ein Mitglied wählen oder einen Gast eintragen.'),
        ]);
        $entry = $this->validateEntry($request);

        $session = $sessions->openSessionOf($actor);

        if ($session === null) {
            throw ValidationException::withMessages(['entry' => __('Bitte zuerst die Session starten.')]);
        }

        $participant = isset($data['user_id'])
            ? $sessions->addMember($actor, $session, User::query()->findOrFail((int) $data['user_id']), (bool) ($data['adult_confirmed'] ?? false))
            : $sessions->addGuest($actor, $session, (string) $data['guest_name'], (bool) ($data['guest_adult'] ?? false));

        $result = $sessions->stage(
            actor: $actor,
            participant: $participant,
            via: EntryVia::Stand,
            class: WeaponClass::from($entry['weapon_class']),
            owner: isset($entry['weapon_owner']) ? WeaponOwner::from($entry['weapon_owner']) : null,
            shots: isset($entry['shots']) ? (int) $entry['shots'] : null,
            discipline: $entry['discipline'] ?? null,
        );

        $this->flashStaged($result['entry'], $result['duplicate'], $participant->name());

        return back();
    }

    public function withdraw(Request $request, SessionEntry $entry, Sessions $sessions): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $sessions->withdraw($actor, $entry);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Eintrag zurückgezogen.')]);

        return back();
    }

    public function reject(Request $request, SessionEntry $entry, Sessions $sessions): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], [], ['reason' => __('Grund')]);

        $sessions->reject($actor, $entry, $data['reason']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Eintrag abgelehnt.')]);

        return back();
    }

    /**
     * Abzeichnen mit den IDs der Einträge, die der Dialog angezeigt hat.
     */
    public function signOff(Request $request, ShootingSession $shootingSession, Sessions $sessions): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $data = $request->validate([
            'entry_ids' => ['present', 'array'],
            'entry_ids.*' => ['integer'],
        ]);

        $result = $sessions->signOff($actor, $shootingSession, array_values(array_map('intval', $data['entry_ids'])));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Session abgezeichnet, :time Uhr: :count Einträge in der Kladde.', [
            'time' => now()->format('H:i'),
            'count' => $result['created'] + $result['merged'],
        ])]);

        return back();
    }

    /**
     * @return array{weapon_class: string, weapon_owner?: string|null, shots?: int|string|null, discipline?: string|null}
     */
    private function validateEntry(Request $request): array
    {
        /** @var array{weapon_class: string, weapon_owner?: string|null, shots?: int|string|null, discipline?: string|null} */
        return $request->validate([
            'weapon_class' => ['required', Rule::enum(WeaponClass::class)],
            'weapon_owner' => ['nullable', Rule::enum(WeaponOwner::class)],
            'shots' => ['nullable', 'integer', 'min:1', 'max:'.Sessions::MAX_SHOTS],
            'discipline' => ['nullable', 'string', 'max:50'],
        ], [], [
            'weapon_class' => __('Kategorie'),
            'weapon_owner' => __('Waffe'),
            'shots' => __('Schüsse'),
        ]);
    }

    private function flashStaged(SessionEntry $entry, bool $duplicate, string $name): void
    {
        Inertia::flash('toast', ['type' => $duplicate ? 'info' : 'success', 'message' => $duplicate
            ? __(':name: schon erfasst (:shots). Zum Ändern zurückziehen und neu erfassen.', ['name' => $name, 'shots' => TodayController::shotsText($entry->shots)])
            : __(':name erfasst, :time Uhr.', ['name' => $name, 'time' => $entry->created_at->format('H:i')])]);
    }

    public function checkIn(Request $request, Booking $booking, Bookings $bookings): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $data = $request->validate([
            'adult_confirmed' => ['boolean'],
            'guest_adult' => ['boolean'],
        ]);

        $participant = $bookings->checkIn($actor, $booking, (bool) ($data['adult_confirmed'] ?? false), (bool) ($data['guest_adult'] ?? false));

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name aktiviert.', ['name' => $participant->name()])]);

        return back();
    }

    public function noShow(Request $request, Booking $booking, Bookings $bookings): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $bookings->markNoShow($actor, $booking);

        return back();
    }

    public function reset(Request $request, Booking $booking, Bookings $bookings): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $bookings->reset($actor, $booking);

        return back();
    }
}
