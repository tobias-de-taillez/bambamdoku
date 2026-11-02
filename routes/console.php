<?php

use Illuminate\Support\Facades\Schedule;

// Läuft über cron.php, das der Hetzner-Cronjob jede Minute aufruft.
Schedule::command('verein:erinnern')->dailyAt('17:00')->withoutOverlapping();
Schedule::command('verein:aufsicht-gesucht')->dailyAt('18:00')->withoutOverlapping();
