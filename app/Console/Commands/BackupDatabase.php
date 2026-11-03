<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Tägliche Sicherung der SQLite-Datenbank nach storage/app/private/backups.
 *
 * Nutzt VACUUM INTO, damit die Kopie auch bei laufendem Betrieb konsistent ist.
 * Bei MariaDB übernimmt das der Hoster; dann tut der Befehl nichts.
 */
class BackupDatabase extends Command
{
    /**
     * @var string
     */
    protected $signature = 'verein:backup
        {--behalten=30 : Wie viele Tage Sicherungen aufbewahrt werden}
        {--falls-fehlt : Nur sichern, wenn es heute noch keine Sicherung gibt}';

    /**
     * @var string
     */
    protected $description = 'Sichert die SQLite-Datenbank und löscht alte Sicherungen';

    public function handle(): int
    {
        if (config('database.default') !== 'sqlite') {
            $this->info('Keine SQLite-Datenbank, nichts zu tun.');

            return self::SUCCESS;
        }

        $directory = self::directory();
        File::ensureDirectoryExists($directory);

        if ($this->option('falls-fehlt') && ! empty(File::glob($directory.'/bambamdoku-'.now()->format('Y-m-d').'-*.sqlite'))) {
            return self::SUCCESS;
        }

        $target = $directory.'/bambamdoku-'.now()->format('Y-m-d-His').'.sqlite';
        DB::statement('VACUUM INTO ?', [$target]);

        $keep = now()->subDays((int) $this->option('behalten'));

        foreach (File::files($directory) as $file) {
            if ($file->getExtension() === 'sqlite' && $file->getMTime() < $keep->getTimestamp()) {
                File::delete($file->getPathname());
            }
        }

        $this->info('Gesichert: '.basename($target));

        return self::SUCCESS;
    }

    public static function directory(): string
    {
        return storage_path('app/private/backups');
    }

    /**
     * @return array<int, array{name: string, size: int, created_at: string}>
     */
    public static function list(): array
    {
        if (! File::isDirectory(self::directory())) {
            return [];
        }

        return collect(File::files(self::directory()))
            ->filter(fn ($file) => $file->getExtension() === 'sqlite')
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->values()
            ->map(fn ($file) => [
                'name' => $file->getFilename(),
                'size' => (int) $file->getSize(),
                'created_at' => date(DATE_ATOM, $file->getMTime()),
            ])
            ->all();
    }
}
