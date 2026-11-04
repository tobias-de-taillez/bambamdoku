<?php

namespace App\Support;

use App\Enums\InvitationDelivery;
use App\Models\User;
use App\Notifications\UserInvited;
use Illuminate\Support\Facades\URL;
use Throwable;

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

    /**
     * Einladungsmail senden und ehrlich melden, ob sie hinausging. Die Mail geht auch ohne
     * Mailversand an den Mailer (dann ins Log). Ein Fehler beim Senden bricht nichts ab:
     * Mitglied und Link sind schon angelegt, der Vorstand gibt den Link dann selbst weiter.
     */
    public static function send(User $user, string $url): InvitationDelivery
    {
        try {
            $user->notify(new UserInvited($url));
        } catch (Throwable $e) {
            report($e);

            return InvitationDelivery::Failed;
        }

        return MailDelivery::active() ? InvitationDelivery::Sent : InvitationDelivery::NotConfigured;
    }

    public static function fingerprint(User $user): string
    {
        return substr(hash_hmac('sha256', (string) $user->password, (string) config('app.key')), 0, 16);
    }

    public static function matches(User $user, string $fingerprint): bool
    {
        return hash_equals(self::fingerprint($user), $fingerprint);
    }
}
