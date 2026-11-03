<?php

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

/**
 * Fortify meldet nach der Registrierung an. Ein Antrag darf aber nicht angemeldet
 * bleiben (E7): sofort abmelden und zur Anmeldung mit der Antragsmeldung.
 */
class RegisterResponse implements RegisterResponseContract
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): RedirectResponse
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->route('login')->with('status', __('Danke! Ihr Antrag liegt beim Vorstand. Sie können sich anmelden, sobald der Verein Sie aufgenommen hat.'));
    }
}
