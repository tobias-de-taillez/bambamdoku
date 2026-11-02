<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\Invitation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvited extends Notification
{
    public function __construct(public string $url) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Einladung zu :app', ['app' => config('app.name')]))
            ->greeting(__('Hallo :name,', ['name' => $notifiable->name]))
            ->line(__('Ihr Verein hat für Sie einen Zugang angelegt. Mit dem folgenden Link legen Sie Ihr Passwort fest.'))
            ->action(__('Passwort festlegen'), $this->url)
            ->line(__('Der Link ist :days Tage gültig und kann nur einmal verwendet werden.', ['days' => Invitation::VALID_DAYS]));
    }
}
