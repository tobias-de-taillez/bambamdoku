<?php

/*
 * Einstiegspunkt für den Hetzner-Cronjob (Webhosting ohne SSH).
 *
 * Läuft jede Minute und erledigt, was sonst per SSH passieren würde:
 *   1. SQLite-Datei anlegen, falls sie fehlt
 *   2. ausstehende Migrationen ausführen
 *   3. den ersten Admin anlegen, solange es noch keinen Benutzer gibt
 *   4. den Laravel-Scheduler laufen lassen
 *
 * Artisan wird im selben Prozess aufgerufen, weil exec() & Co. auf
 * Shared Hosting oft gesperrt sind.
 */

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

if (config('database.default') === 'sqlite') {
    $database = config('database.connections.sqlite.database');

    if ($database !== ':memory:' && ! file_exists($database)) {
        touch($database);
    }
}

$kernel->call('migrate', ['--force' => true]);

if (User::query()->doesntExist()) {
    $kernel->call('db:seed', ['--force' => true]);
}

$kernel->call('schedule:run');

echo $kernel->output();
