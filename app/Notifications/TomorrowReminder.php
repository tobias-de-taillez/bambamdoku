<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Erinnerung am Vortag: Buchungen und Aufsichtsdienste eines Mitglieds.
 */
class TomorrowReminder extends Notification
{
    /**
     * @param  list<string>  $bookings  z. B. "18:00–19:00 Uhr, 25 m Kurzwaffe"
     * @param  list<string>  $duties  z. B. "17:00–21:00 Uhr, ganze Anlage"
     */
    public function __construct(public string $date, public array $bookings, public array $duties) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->duties !== []
                ? __('Erinnerung: Standaufsicht am :date', ['date' => $this->date])
                : __('Erinnerung: Ihre Buchung am :date', ['date' => $this->date]))
            ->greeting(__('Hallo :name,', ['name' => $notifiable->name]));

        if ($this->duties !== []) {
            $mail->line(__('Sie sind morgen als Standaufsicht eingetragen:'));
            foreach ($this->duties as $duty) {
                $mail->line('• '.$duty);
            }
        }

        if ($this->bookings !== []) {
            $mail->line(__('Ihre Buchungen für morgen:'));
            foreach ($this->bookings as $booking) {
                $mail->line('• '.$booking);
            }
        }

        return $mail
            ->action(__('Schießtage ansehen'), route('shooting-days.index'))
            ->line(__('Falls Sie nicht kommen können, stornieren Sie bitte rechtzeitig bzw. tragen Sie eine Vertretung ein.'));
    }
}
