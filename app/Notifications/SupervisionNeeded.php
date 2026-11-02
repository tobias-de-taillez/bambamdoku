<?php

namespace App\Notifications;

use App\Models\ShootingDay;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupervisionNeeded extends Notification
{
    public function __construct(public ShootingDay $day, public bool $partial) {}

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

        return (new MailMessage)
            ->subject(__('Standaufsicht gesucht: :date', ['date' => $date]))
            ->greeting(__('Hallo :name,', ['name' => $notifiable->name]))
            ->line($this->partial
                ? __('für den Schießtag am :date (:from–:to Uhr) ist die Standaufsicht noch nicht durchgehend besetzt.', ['date' => $date, 'from' => substr($this->day->starts_at, 0, 5), 'to' => substr($this->day->ends_at, 0, 5)])
                : __('für den Schießtag am :date (:from–:to Uhr) hat sich noch keine Standaufsicht eingetragen.', ['date' => $date, 'from' => substr($this->day->starts_at, 0, 5), 'to' => substr($this->day->ends_at, 0, 5)]))
            ->line(__('Ohne Aufsicht können Mitglieder nicht buchen. Können Sie einspringen?'))
            ->action(__('Aufsicht übernehmen'), route('shooting-days.show', $this->day));
    }
}
