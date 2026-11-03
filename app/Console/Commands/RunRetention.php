<?php

namespace App\Console\Commands;

use App\Domain\Retention;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Console\Command;

class RunRetention extends Command
{
    /**
     * @var string
     */
    protected $signature = 'verein:aufbewahrung
        {--dry-run : Nur zählen, nichts ändern}';

    /**
     * @var string
     */
    protected $description = 'Löscht Kladde, Sessions und Buchungen nach der Aufbewahrungsfrist und anonymisiert ältere Gastnamen (Einstellungen → Aufbewahrung)';

    public function handle(Retention $retention): int
    {
        $dryRun = (bool) $this->option('dry-run');

        try {
            $result = $retention->run($dryRun);
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(match (true) {
            $dryRun => 'Probelauf, nichts geändert. Ein Lauf würde ändern:',
            $result['run_id'] === null => 'Nichts zu tun.',
            default => sprintf('Löschlauf #%d abgeschlossen:', $result['run_id']),
        });

        $this->table(['', 'Anzahl'], [
            [sprintf('Kladde-Zeilen vor %s gelöscht', $this->day($result['record_cutoff'])), $result['deleted_entries']],
            ['Sessions gelöscht (mit Teilnehmern und Vorstufe)', $result['deleted_sessions']],
            ['Buchungen gelöscht', $result['deleted_bookings']],
            [sprintf('Gastnamen vor %s anonymisiert', $this->day($result['guest_cutoff'])), $result['anonymized_guests']],
            ['Protokolltexte anonymisiert', $result['anonymized_texts']],
        ]);

        return self::SUCCESS;
    }

    private function day(string $date): string
    {
        return CarbonImmutable::parse($date)->format('d.m.Y');
    }
}
