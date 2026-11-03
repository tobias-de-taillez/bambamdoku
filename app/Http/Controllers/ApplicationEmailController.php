<?php

namespace App\Http\Controllers;

use App\Models\AuditEntry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * Bestätigt die E-Mail-Adresse eines Mitgliedsantrags über den signierten Link.
 */
class ApplicationEmailController extends Controller
{
    public function __invoke(User $user, string $hash): RedirectResponse
    {
        abort_unless($user->isApplicant() && $user->email !== null && hash_equals(sha1($user->email), $hash), 404);

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
            AuditEntry::record(null, 'application.email_confirmed', $user, sprintf('E-Mail-Adresse zum Antrag #%d bestätigt', $user->id));
        }

        return to_route('login')->with('status', __('Danke, Ihre E-Mail-Adresse ist bestätigt. Ihr Antrag liegt beim Vorstand.'));
    }
}
