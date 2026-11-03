<?php

namespace App\Console\Commands;

use App\Domain\Applications;
use Illuminate\Console\Command;

class PurgeApplications extends Command
{
    /**
     * @var string
     */
    protected $signature = 'verein:antraege-loeschen';

    /**
     * @var string
     */
    protected $description = 'Löscht Mitgliedsanträge, die älter als 30 Tage sind (nie Mitglieder, auch keine deaktivierten)';

    public function handle(Applications $applications): int
    {
        $count = $applications->purge();

        $this->info(sprintf('%d Antrag/Anträge gelöscht.', $count));

        return self::SUCCESS;
    }
}
