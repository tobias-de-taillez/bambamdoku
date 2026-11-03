<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEntry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Der Vorstand kann eine Kiosk-PIN nur löschen, nie sehen (E2).
 */
class KioskPinController extends Controller
{
    public function destroy(Request $request, User $user): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();

        if ($user->kiosk_pin_hash !== null) {
            $user->forceFill(['kiosk_pin_hash' => null])->save();
            AuditEntry::record($actor, 'user.kiosk_pin_removed', $user, sprintf('Kiosk-PIN gelöscht durch den Vorstand: %s', $user->name));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Kiosk-PIN von :name gelöscht.', ['name' => $user->name])]);

        return back();
    }
}
