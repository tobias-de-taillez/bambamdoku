<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Bestätigung der E-Mail-Adresse eines Mitgliedsantrags. Der Antrag kann sich nicht
 * anmelden, deshalb ein eigener signierter Link statt der Fortify-Verifizierung.
 */
class ConfirmApplicationEmail extends Notification
{
    public const VALID_DAYS = 7;

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute('application.confirm-email', now()->addDays(self::VALID_DAYS), [
            'user' => $notifiable->id,
            'hash' => sha1((string) $notifiable->email),
        ]);

        return (new MailMessage)
            ->subject(__('E-Mail-Adresse für Ihren Mitgliedsantrag bestätigen'))
            ->greeting(__('Hallo :name,', ['name' => $notifiable->name]))
            ->line(__('bitte bestätigen Sie Ihre E-Mail-Adresse. Erst danach kann der Vorstand Ihren Antrag aufnehmen.'))
            ->action(__('E-Mail-Adresse bestätigen'), $url)
            ->line(__('Der Link ist :days Tage gültig.', ['days' => self::VALID_DAYS]));
    }
}
