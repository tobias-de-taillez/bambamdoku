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
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', __('Ihr Zugang ist deaktiviert.'));
        }

        return $next($request);
    }
}
