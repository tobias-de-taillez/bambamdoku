<?php

use Illuminate\Support\Facades\Schedule;

// Läuft über cron.php, das der Hetzner-Cronjob jede Minute aufruft.
Schedule::command('verein:backup')->dailyAt('03:30')->withoutOverlapping();
// Verpasst (Deploy, Ausfall des Cronjobs um 03:30)? Dann zur nächsten vollen halben Stunde nachholen.
Schedule::command('verein:backup --falls-fehlt')->hourlyAt(30)->between('4:00', '23:59')->withoutOverlapping();
// Mitgliedsanträge, die der Vorstand 30 Tage lang nicht bearbeitet hat (Datenschutzhinweis).
Schedule::command('verein:antraege-loeschen')->dailyAt('04:15')->withoutOverlapping();
// Aufbewahrungsfristen (Q6): Kladde und Session-Daten nach Jahren löschen, Gastnamen nach Monaten anonymisieren.
Schedule::command('verein:aufbewahrung')->dailyAt('04:30')->withoutOverlapping();
