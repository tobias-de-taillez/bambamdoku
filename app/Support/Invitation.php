<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\URL;

/**
 * Einladungslinks: signiert, 7 Tage gültig und nur einmal nutzbar, weil der
 * Link einen Fingerabdruck des aktuellen Passwort-Hashes trägt. Sobald das
 * Mitglied sein Passwort setzt, passt der Fingerabdruck nicht mehr.
 */
class Invitation
{
    public const VALID_DAYS = 7;

    public static function url(User $user): string
    {
        return URL::temporarySignedRoute('invitation.show', now()->addDays(self::VALID_DAYS), [
            'user' => $user->id,
            'fingerprint' => self::fingerprint($user),
        ]);
    }

    public static function fingerprint(User $user): string
    {
        return substr(hash_hmac('sha256', $user->password, (string) config('app.key')), 0, 16);
    }

    public static function matches(User $user, string $fingerprint): bool
    {
        return hash_equals(self::fingerprint($user), $fingerprint);
    }
}
