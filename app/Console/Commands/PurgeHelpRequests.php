<?php

namespace App\Console\Commands;

use App\Domain\HelpRequests;
use Illuminate\Console\Command;

class PurgeHelpRequests extends Command
{
    /**
     * @var string
     */
    protected $signature = 'verein:hilfe-aufraeumen';

    /**
     * @var string
     */
    protected $description = 'Löscht Hilfe-Meldungen („Report Bug / Request Feature“): erledigte nach 90 Tagen, alle nach 365 Tagen (Ordner und Zeile)';

    public function handle(HelpRequests $helpRequests): int
    {
        $count = $helpRequests->purge();

        $this->info(sprintf('%d Hilfe-Meldung(en) gelöscht.', $count));

        return self::SUCCESS;
    }
}
