<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

// Läuft über cron.php, das der Hetzner-Cronjob jede Minute aufruft.
Schedule::command('verein:backup')->dailyAt('03:30')->withoutOverlapping();
// Verpasst (Deploy, Ausfall des Cronjobs um 03:30)? Dann zur nächsten vollen halben Stunde nachholen.
Schedule::command('verein:backup --falls-fehlt')->hourlyAt(30)->between('4:00', '23:59')->withoutOverlapping();
// Mitgliedsanträge, die der Vorstand 30 Tage lang nicht bearbeitet hat (Datenschutzhinweis).
Schedule::command('verein:antraege-loeschen')->dailyAt('04:15')->withoutOverlapping();
// Hilfe-Meldungen: erledigte nach 90 Tagen, alle nach 365 Tagen (Datenschutzhinweis).
// Abgelaufene Einträge im Datenbank-Cache (Drosseln mit IP und eingegebener E-Mail) entfernt Laravel nicht von selbst.
Schedule::call(function () {
    if (config('cache.default') === 'database') {
        DB::table((string) config('cache.stores.database.table', 'cache'))->where('expiration', '<', now()->getTimestamp())->delete();
    }
})->name('cache-aufraeumen')->hourly()->withoutOverlapping();
Schedule::command('verein:hilfe-aufraeumen')->dailyAt('04:25')->withoutOverlapping();

// Aufbewahrungsfristen (Q6): Kladde und Session-Daten nach Jahren löschen, Gastnamen nach Monaten anonymisieren.
Schedule::command('verein:aufbewahrung')->dailyAt('04:30')->withoutOverlapping();
