<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\ShootingDay;
use App\Models\Supervision;
use App\Models\User;
use App\Notifications\TomorrowReminder;
use Illuminate\Console\Command;

class SendReminders extends Command
{
    /**
     * @var string
     */
    protected $signature = 'verein:erinnern';

    /**
     * @var string
     */
    protected $description = 'Erinnert Mitglieder an ihre Buchungen und Aufsichtsdienste am nächsten Tag';

    public function handle(): int
    {
        $days = ShootingDay::query()
            ->whereDate('date', today()->addDay())
            ->whereNull('cancelled_at')
            ->with(['bookings.range', 'bookings.user', 'supervisions.range', 'supervisions.user'])
            ->get();

        /** @var array<int, array{user: User, bookings: list<string>, duties: list<string>}> $perUser */
        $perUser = [];

        foreach ($days as $day) {
            foreach ($day->bookings as $booking) {
                /** @var Booking $booking */
                if ($booking->status !== BookingStatus::Booked || ! $booking->user || ! $booking->user->active) {
                    continue;
                }

                $perUser[$booking->user->id] ??= ['user' => $booking->user, 'bookings' => [], 'duties' => []];
                $perUser[$booking->user->id]['bookings'][] = sprintf('%s–%s Uhr, %s', substr($booking->starts_at, 0, 5), substr($booking->ends_at, 0, 5), $booking->range->name);
            }

            foreach ($day->supervisions as $supervision) {
                /** @var Supervision $supervision */
                if (! $supervision->user->active) {
                    continue;
                }

                $perUser[$supervision->user->id] ??= ['user' => $supervision->user, 'bookings' => [], 'duties' => []];
                $perUser[$supervision->user->id]['duties'][] = sprintf('%s–%s Uhr, %s', substr($supervision->starts_at, 0, 5), substr($supervision->ends_at, 0, 5), $supervision->range->name ?? 'ganze Anlage');
            }
        }

        $date = today()->addDay()->translatedFormat('l, j. F');

        foreach ($perUser as $entry) {
            $entry['user']->notify(new TomorrowReminder($date, $entry['bookings'], $entry['duties']));
        }

        $this->info(sprintf('%d Erinnerungen verschickt.', count($perUser)));

        return self::SUCCESS;
    }
}
