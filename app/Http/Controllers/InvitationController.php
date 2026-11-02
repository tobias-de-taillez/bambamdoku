<?php

namespace App\Http\Controllers;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use App\Support\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    use PasswordValidationRules;

    public function show(Request $request, User $user, string $fingerprint): Response|RedirectResponse
    {
        if (! $user->active || ! Invitation::matches($user, $fingerprint)) {
            return to_route('login')->with('status', __('Dieser Einladungslink ist nicht mehr gültig. Bitte wenden Sie sich an Ihren Verein.'));
        }

        return Inertia::render('auth/AcceptInvitation', [
            'name' => $user->name,
            'email' => $user->email,
            'action' => $request->fullUrl(),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(Request $request, User $user, string $fingerprint): RedirectResponse
    {
        abort_unless($user->active && Invitation::matches($user, $fingerprint), 403);

        $validated = $request->validate(['password' => $this->passwordRules()]);

        $user->forceFill([
            'password' => $validated['password'],
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        Auth::login($user);
        $request->session()->regenerate();

        return to_route('dashboard');
    }
}
