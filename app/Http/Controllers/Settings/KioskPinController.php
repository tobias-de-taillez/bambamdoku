<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditEntry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

/**
 * Die eigene Kiosk-PIN (E2): 4 bis 6 Ziffern, gespeichert nur als Hash.
 */
class KioskPinController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'pin' => ['required', 'string', 'regex:/^\d{4,6}$/', 'confirmed'],
        ], [
            'pin.regex' => __('Die PIN besteht aus 4 bis 6 Ziffern.'),
        ], ['pin' => __('PIN')]);

        $user->forceFill(['kiosk_pin_hash' => Hash::make($data['pin'])])->save();
        AuditEntry::record($user, 'user.kiosk_pin_set', $user, sprintf('Kiosk-PIN gesetzt: %s', $user->name));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Kiosk-PIN gespeichert.')]);

        return back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->forceFill(['kiosk_pin_hash' => null])->save();
        AuditEntry::record($user, 'user.kiosk_pin_removed', $user, sprintf('Kiosk-PIN gelöscht: %s', $user->name));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Kiosk-PIN gelöscht.')]);

        return back();
    }
}
