<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wer deaktiviert wird, verliert auch eine bereits laufende Sitzung.
 */
class LogoutInactiveUsers
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->active) {
            $message = $request->user()->isApplicant()
                ? __('Ihr Antrag liegt beim Vorstand. Sie können sich anmelden, sobald der Verein Sie aufgenommen hat.')
                : __('Ihr Zugang ist deaktiviert.');

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', $message);
        }

        return $next($request);
    }
}
