<?php

namespace App\Notifications;

use App\Models\ShootingDay;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ShootingDayCancelled extends Notification
{
    public function __construct(public ShootingDay $day) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $date = $this->day->date->translatedFormat('l, j. F');

        $mail = (new MailMessage)
            ->subject(__('Abgesagt: Schießtag am :date', ['date' => $date]))
            ->greeting(__('Hallo :name,', ['name' => $notifiable->name]))
            ->line(__('der Schießtag am :date (:from–:to Uhr) wurde abgesagt. Ihre Buchung ist damit storniert.', [
                'date' => $date,
                'from' => substr($this->day->starts_at, 0, 5),
                'to' => substr($this->day->ends_at, 0, 5),
            ]));

        if ($this->day->cancel_reason) {
            $mail->line(__('Grund: :reason', ['reason' => $this->day->cancel_reason]));
        }

        return $mail->action(__('Andere Schießtage ansehen'), route('shooting-days.index'));
    }
}
