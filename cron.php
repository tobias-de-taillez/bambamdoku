<?php

/*
 * Einstiegspunkt für den Hetzner-Cronjob (Webhosting ohne SSH).
 *
 * Läuft jede Minute und erledigt, was sonst per SSH passieren würde:
 *   1. beim allerersten Start die SQLite-Datei anlegen
 *   2. ausstehende Migrationen ausführen
 *   3. beim allerersten Start den ersten Admin anlegen
 *   4. den Laravel-Scheduler laufen lassen
 *
 * Nach dem ersten erfolgreichen Lauf entsteht eine Marker-Datei. Fehlt danach
 * die Datenbank, bricht das Skript laut ab, statt still eine leere neue
 * anzulegen – sonst liefe ein verlorener Datenbestand unbemerkt leer weiter.
 *
 * Artisan wird im selben Prozess aufgerufen, weil exec() & Co. auf
 * Shared Hosting oft gesperrt sind.
 */

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

// Deploy (scripts/deploy-hetzner.sh): Während des Uploads liegt scripts/wartung.php als
// maintenance.php – dann nichts tun, der Code ist unvollständig. Ist der Upload fertig
// (deploy-fertig), zuerst migrieren und erst danach die Wartungsseite abschalten.
$maintenance = __DIR__.'/storage/framework/maintenance.php';
$deployDone = __DIR__.'/storage/framework/deploy-fertig';

if (file_exists($maintenance) && ! file_exists($deployDone)) {
    exit(0);
}

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$marker = storage_path('app/private/installed');
$firstStart = ! file_exists($marker);

if (config('database.default') === 'sqlite') {
    $database = config('database.connections.sqlite.database');

    $missing = $database !== ':memory:' && (! file_exists($database) || filesize($database) === 0);

    if ($missing && ! $firstStart) {
        fwrite(STDERR, "FEHLER: Datenbank {$database} fehlt oder ist leer. Bitte aus einer Sicherung (storage/app/private/backups) wiederherstellen.\n");
        exit(1);
    }

    if ($database !== ':memory:' && ! file_exists($database)) {
        touch($database);
    }
}

$kernel->call('migrate', ['--force' => true]);

if (file_exists($deployDone)) {
    @unlink($maintenance);
    @unlink($deployDone);
}

if ($firstStart) {
    if (User::query()->doesntExist()) {
        $kernel->call('db:seed', ['--force' => true]);
    }

    @mkdir(dirname($marker), 0755, true);
    file_put_contents($marker, now()->toIso8601String()."\n");
}

$kernel->call('schedule:run');

echo $kernel->output();
