<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\Group;
use App\Models\User;
use App\Notifications\UserInvited;
use App\Support\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
            ->with('groups:id,name')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('member_number', 'like', "%{$search}%")))
            ->when($status === 'active', fn ($query) => $query->where('active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('active', false))
            ->when($status === 'supervisors', fn ($query) => $query->where('active', true)->whereDate('supervisor_until', '>=', today()))
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->present($user));

        return Inertia::render('admin/users/Index', [
            'users' => $users,
            'filters' => ['search' => $search, 'status' => $status],
            'invitation' => $request->session()->get('invitation'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/users/Form', [
            'user' => null,
            'groups' => Group::orderBy('name')->get(['id', 'name']),
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
            'password' => Hash::make(Str::random(40)),
        ])->save();
        $user->groups()->sync($data['groups'] ?? []);

        $url = Invitation::url($user);

        if ($data['send_invitation'] ?? true) {
            $user->notify(new UserInvited($url));
        }

        return to_route('admin.users.index')->with('invitation', [
            'name' => $user->name,
            'url' => $url,
            'mailed' => (bool) ($data['send_invitation'] ?? true),
        ]);
    }

    public function edit(User $user): Response
    {
        return Inertia::render('admin/users/Form', [
            'user' => $this->present($user->load('groups:id,name')),
            'groups' => Group::orderBy('name')->get(['id', 'name']),
            'roles' => $this->roles(),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $attributes = $this->attributes($data);

        if ($user->is($request->user()) && ($attributes['role'] !== Role::Admin || ! $attributes['active'])) {
            throw ValidationException::withMessages([
                'role' => __('Sie können sich nicht selbst die Admin-Rechte entziehen oder sich deaktivieren.'),
            ]);
        }

        $user->forceFill($attributes)->save();
        $user->groups()->sync($data['groups'] ?? []);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mitglied gespeichert.')]);

        return to_route('admin.users.index');
    }

    public function invite(User $user): RedirectResponse
    {
        $url = Invitation::url($user);
        $user->notify(new UserInvited($url));

        return to_route('admin.users.index')->with('invitation', [
            'name' => $user->name,
            'url' => $url,
            'mailed' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => Role::from($data['role']),
            'member_number' => $data['member_number'] ?? null,
            'supervisor_until' => $data['supervisor_until'] ?? null,
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
            'can_supervise' => $user->canSupervise(),
            'groups' => $user->groups->map(fn (Group $group) => ['id' => $group->id, 'name' => $group->name])->values(),
            'has_logged_in' => $user->email_verified_at !== null,
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
            ['value' => Role::Guest->value, 'label' => __('Gast')],
        ];
    }
}
