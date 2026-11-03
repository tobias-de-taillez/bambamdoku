<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Applications;
use App\Domain\Disciplines;
use App\Domain\ShootingRecord;
use App\Enums\Role;
use App\Enums\SupervisionScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\AuditEntry;
use App\Models\Qualification;
use App\Models\User;
use App\Notifications\UserInvited;
use App\Support\Invitation;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'active');

        $users = User::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('member_number', 'like', "%{$search}%")))
            ->when($status === 'applicants', fn ($query) => $query->where('role', Role::Applicant->value), fn ($query) => $query->members())
            ->when($status === 'active', fn ($query) => $query->where('active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('active', false))
            ->when($status === 'supervisors', fn ($query) => $query->where('active', true)->whereDate('supervisor_until', '>=', today()))
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->present($user));

        return Inertia::render('admin/users/Index', [
            'users' => $users,
            'filters' => ['search' => $search, 'status' => $status],
            'today' => today()->toDateString(),
            'invitation' => $request->session()->get('invitation'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/users/Form', [
            'user' => null,
            'roles' => $this->roles(),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = new User;
        $user->forceFill([
            ...$this->attributes($data),
            // Unbekanntes Zufallspasswort; das Mitglied setzt sein eigenes über den Einladungslink.
            'password' => $data['email'] ?? null ? Hash::make(Str::random(40)) : null,
            // E12: „volljährig geprüft“ wird einmal gespeichert.
            'adult_confirmed_at' => ($data['adult_confirmed'] ?? false) ? now() : null,
        ])->save();
        AuditEntry::record($request->user(), 'user.created', $user, sprintf('Mitglied angelegt: %s (%s)', $user->name, $user->role->value));

        // Ohne E-Mail-Adresse kein Konto und keine Einladung.
        if ($user->email === null) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __(':name angelegt (ohne Konto).', ['name' => $user->name])]);

            return to_route('admin.users.index');
        }

        $url = Invitation::url($user);
        $mailed = (bool) ($data['send_invitation'] ?? true);

        if ($mailed) {
            $user->notify(new UserInvited($url));
        }

        return to_route('admin.users.index')->with('invitation', [
            'name' => $user->name,
            'url' => $url,
            'mailed' => $mailed,
        ]);
    }

    public function edit(User $user): Response|RedirectResponse
    {
        if ($user->isApplicant()) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('Einen Antrag nehmen Sie auf oder lehnen ihn ab; bearbeiten lässt er sich nicht.')]);

            return to_route('admin.users.index', ['status' => 'applicants']);
        }

        return Inertia::render('admin/users/Form', [
            'user' => $this->present($user),
            'register' => $user->qualifications()->with('checkedBy:id,name')->get()->map(fn (Qualification $q) => [
                'id' => $q->id,
                'scope' => $q->scope->value,
                'scope_label' => $q->scope->label(),
                'valid_until' => $q->valid_until?->toDateString(),
                'checked_on' => $q->checked_on->toDateString(),
                'checked_by' => $q->checkedBy?->name,
                'note' => $q->note,
            ]),
            'scopes' => SupervisionScope::options(),
            'roles' => $this->roles(),
            // Teilnahmenachweis je Disziplin (a): Disziplinen der letzten 24 Monate
            'catalog' => Disciplines::all() !== [],
            'disciplines' => ShootingRecord::disciplines(ShootingRecord::for($user)->entries(CarbonImmutable::today()->subMonthsNoOverflow(24)->addDay(), CarbonImmutable::today())),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        // Das Formular schreibt Rolle und active; ein Antrag wird nur über „Aufnehmen“ Mitglied.
        $this->denyApplicant($user);

        $data = $request->validated();
        $attributes = $this->attributes($data);

        if ($user->is($request->user()) && ($attributes['role'] !== Role::Admin || ! $attributes['active'])) {
            throw ValidationException::withMessages([
                'role' => __('Sie können sich nicht selbst die Admin-Rechte entziehen oder sich deaktivieren.'),
            ]);
        }

        $user->forceFill($attributes);

        if (($data['adult_confirmed'] ?? false) && $user->adult_confirmed_at === null) {
            $user->forceFill(['adult_confirmed_at' => now()]);
        }

        $changes = $this->changes($user);
        $user->save();

        if ($changes !== []) {
            AuditEntry::record($request->user(), 'user.updated', $user, sprintf('Mitglied geändert: %s (%s)', $user->name, implode('; ', $changes)));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mitglied gespeichert.')]);

        return to_route('admin.users.index');
    }

    /**
     * Neue Zeile im Aufsichtsregister (§ 10 Abs. 3 AWaffV). Nur anhängen, es gilt die jüngste.
     */
    public function register(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'scope' => ['required', Rule::enum(SupervisionScope::class)],
            'valid_until' => ['nullable', 'required_unless:scope,revoked', 'date', 'after_or_equal:checked_on'],
            'checked_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
            'confirmed' => ['exclude_if:scope,revoked', 'accepted'],
        ], [
            'confirmed.accepted' => __('Bitte bestätigen Sie Volljährigkeit und Sachkunde.'),
            'valid_until.required_unless' => __('Bitte angeben, bis wann die Registrierung gilt.'),
        ], [
            'scope' => __('Umfang'),
            'valid_until' => __('gültig bis'),
            'checked_on' => __('geprüft am'),
        ]);

        /** @var User $actor */
        $actor = $request->user();
        $this->denyApplicant($user);

        if ($actor->is($user)) {
            throw ValidationException::withMessages(['scope' => __('Die eigene Registrierung trägt ein anderes Vorstandsmitglied ein.')]);
        }

        Qualification::register($user, SupervisionScope::from($data['scope']), $data['valid_until'] ?? null, $data['checked_on'], $actor, $data['note'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Aufsichtsregister ergänzt.')]);

        return to_route('admin.users.edit', $user);
    }

    public function invite(User $user): RedirectResponse
    {
        $this->denyApplicant($user);

        if ($user->email === null) {
            throw ValidationException::withMessages(['email' => __('Für eine Einladung braucht das Mitglied eine E-Mail-Adresse.')]);
        }

        if ($user->password === null) {
            $user->forceFill(['password' => Hash::make(Str::random(40))])->save();
        }

        $url = Invitation::url($user);
        $user->notify(new UserInvited($url));

        return to_route('admin.users.index')->with('invitation', [
            'name' => $user->name,
            'url' => $url,
            'mailed' => true,
        ]);
    }

    /**
     * Antrag aufnehmen (E7): nur mit dem Haken „Identität und Volljährigkeit geprüft“.
     */
    public function admit(Request $request, User $user, Applications $applications): RedirectResponse
    {
        abort_unless($user->isApplicant(), 404);

        $data = $request->validate([
            'confirmed' => ['accepted'],
            'member_number' => ['nullable', 'string', 'max:50', Rule::unique(User::class)->ignore($user->id)],
            'member_since' => ['nullable', 'date', 'before_or_equal:today'],
        ], [
            'confirmed.accepted' => __('Bitte bestätigen Sie, dass Identität und Volljährigkeit geprüft sind.'),
        ], [
            'member_number' => __('Mitgliedsnummer'),
            'member_since' => __('Mitglied seit'),
        ]);

        /** @var User $admin */
        $admin = $request->user();
        $applications->admit($admin, $user, $data['member_number'] ?? null, $data['member_since'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name ist aufgenommen und kann sich jetzt anmelden.', ['name' => $user->name])]);

        return to_route('admin.users.index', ['status' => 'applicants']);
    }

    /**
     * Antrag ablehnen: Der Datensatz wird gelöscht.
     */
    public function reject(Request $request, User $user, Applications $applications): RedirectResponse
    {
        abort_unless($user->isApplicant(), 404);

        /** @var User $admin */
        $admin = $request->user();
        $applications->reject($admin, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Antrag abgelehnt und gelöscht.')]);

        return to_route('admin.users.index', ['status' => 'applicants']);
    }

    /**
     * Für Anträge gibt es kein Bearbeiten, keine Einladung und keinen Registereintrag.
     */
    private function denyApplicant(User $user): void
    {
        abort_if($user->isApplicant(), 403, __('Einen Antrag nehmen Sie auf oder lehnen ihn ab.'));
    }

    /**
     * Geänderte Felder mit altem und neuem Wert für das Protokoll.
     *
     * @return list<string>
     */
    private function changes(User $user): array
    {
        $labels = [
            'name' => 'Name',
            'email' => 'E-Mail',
            'member_number' => 'Nr.',
            'member_since' => 'Mitglied seit',
            'role' => 'Rolle',
            'active' => 'aktiv',
            'adult_confirmed_at' => 'volljährig geprüft',
        ];
        $format = fn (mixed $value): string => match (true) {
            $value === null || $value === '' => '–',
            $value instanceof Role => $value->value,
            is_bool($value) => $value ? 'ja' : 'nein',
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            default => (string) $value,
        };

        $changes = [];

        foreach (array_keys($user->getDirty()) as $key) {
            if (! isset($labels[$key])) {
                continue;
            }

            // Die E-Mail-Adresse steht nur als Feldname im Protokoll.
            $changes[] = match ($key) {
                'email' => $labels[$key],
                'adult_confirmed_at' => $labels[$key].': ja',
                default => sprintf('%s: %s → %s', $labels[$key], $format($user->getOriginal($key)), $format($user->getAttribute($key))),
            };
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'role' => Role::from($data['role']),
            'member_number' => $data['member_number'] ?? null,
            'member_since' => $data['member_since'] ?? null,
            'active' => (bool) ($data['active'] ?? true),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'active' => $user->active,
            'member_number' => $user->member_number,
            'supervisor_until' => $user->supervisor_until?->toDateString(),
            'supervisor_scope' => $user->supervisor_scope?->value,
            'member_since' => $user->member_since?->toDateString(),
            'can_supervise' => $user->canSupervise(),
            'has_logged_in' => $user->email_verified_at !== null,
            'adult_confirmed' => $user->adult_confirmed_at !== null,
            'applied_at' => $user->isApplicant() ? $user->created_at?->toDateString() : null,
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function roles(): array
    {
        return [
            ['value' => Role::Member->value, 'label' => __('Mitglied')],
            ['value' => Role::Admin->value, 'label' => __('Administrator')],
        ];
    }
}
