<?php

namespace App\Console\Commands;

use App\Domain\Schedule;
use App\Models\Range;
use App\Models\ShootingDay;
use App\Models\User;
use App\Notifications\SupervisionNeeded;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class RequestSupervision extends Command
{
    /**
     * @var string
     */
    protected $signature = 'verein:aufsicht-gesucht {--tage=3 : Wie viele Tage im Voraus}';

    /**
     * @var string
     */
    protected $description = 'Bittet qualifizierte Mitglieder um Aufsicht für Schießtage, an denen sie noch fehlt';

    public function handle(): int
    {
        $date = today()->addDays((int) $this->option('tage'));

        $days = ShootingDay::query()
            ->whereDate('date', $date)
            ->whereNull('cancelled_at')
            ->with(['ranges', 'supervisions'])
            ->get();

        $sent = 0;

        foreach ($days as $day) {
            $schedule = new Schedule($day);
            $window = $schedule->window();

            if ($day->ranges->every(fn (Range $range) => $schedule->isSupervised($range, $window))) {
                continue;
            }

            $assigned = $day->supervisions->pluck('user_id');
            $recipients = User::query()
                ->where('active', true)
                ->whereDate('supervisor_until', '>=', $day->date)
                ->whereNotIn('id', $assigned)
                ->get();

            Notification::send($recipients, new SupervisionNeeded($day, $schedule->hasAnySupervision()));
            $sent += $recipients->count();
        }

        $this->info(sprintf('%d Anfragen verschickt.', $sent));

        return self::SUCCESS;
    }
}
