<?php

namespace App\Domain;

use App\Models\HelpRequest;
use App\Models\User;
use App\Support\HelpRedactor;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\IpUtils;
use Throwable;

/**
 * Hilfe-Meldungen („Report Bug / Request Feature“): jede Meldung ist ein eigener Ordner
 * storage/app/private/hilfe/<JJJJ-MM-TT>/<HHMMSS>-<Kennung>/ mit meldung.json und screenshot.jpg.
 * Die Tabelle help_requests ist nur der Index für die Verwaltung; scheitert sie, bleibt die Meldung trotzdem liegen.
 */
class HelpRequests
{
    public const DIRECTORY = 'hilfe';

    /**
     * Erledigte Meldungen verschwinden so viele Tage nach dem Erledigen.
     */
    public const DONE_RETENTION_DAYS = 90;

    /**
     * Alle Meldungen verschwinden spätestens so viele Tage nach dem Eingang.
     */
    public const MAX_RETENTION_DAYS = 365;

    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * @param  array<mixed>  $state  Zustand der Seite aus dem Browser (wird hier noch einmal geschwärzt)
     * @return string Kennung der Meldung
     */
    public function store(?User $user, string $message, array $state, ?UploadedFile $screenshot, ?string $ip): string
    {
        $disk = self::disk();
        $now = CarbonImmutable::now();

        do {
            $reference = $this->reference();
            $path = sprintf('%s/%s/%s-%s', self::DIRECTORY, $now->format('Y-m-d'), $now->format('His'), $reference);
        } while ($disk->exists($path) || HelpRequest::query()->where('reference', $reference)->exists());

        // Die Asset-Version ist ein Hash, aber kein Geheimnis; sie hilft, den Stand des Frontends zuzuordnen.
        $version = data_get($state, 'page.version');
        $state = (array) HelpRedactor::value($state);
        if (is_string($version) && preg_match('/^[a-f0-9]{1,64}$/', $version)) {
            data_set($state, 'page.version', $version);
        }

        $url = HelpRedactor::string(mb_substr((string) (data_get($state, 'url') ?: data_get($state, 'page.url') ?: ''), 0, 2000));
        $component = data_get($state, 'page.component');
        $component = is_string($component) ? mb_substr($component, 0, 255) : null;

        $hasScreenshot = $screenshot !== null && $disk->putFileAs($path, $screenshot, 'screenshot.jpg') !== false;

        $report = [
            'kennung' => $reference,
            'zeitpunkt' => $now->toIso8601String(),
            'status' => HelpRequest::OPEN,
            'text' => $message,
            'nutzer' => $user === null ? 'Gast' : [
                'id' => $user->id,
                'name' => $user->name,
                'rolle' => $user->role->value,
            ],
            'ip' => $ip !== null && filter_var($ip, FILTER_VALIDATE_IP) ? IpUtils::anonymize($ip) : null,
            'url' => $url,
            'komponente' => $component,
            'browser' => data_get($state, 'client'),
            'bildschirmfoto' => $hasScreenshot ? 'screenshot.jpg' : null,
            'state' => $state,
        ];

        $disk->put($path.'/meldung.json', (string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR));

        try {
            HelpRequest::query()->create([
                'reference' => $reference,
                'user_id' => $user?->id,
                'url' => $url,
                'component' => $component,
                'message' => $message,
                'path' => $path,
                'status' => HelpRequest::OPEN,
            ]);
        } catch (Throwable $e) {
            // Ohne Index-Zeile fehlt die Meldung nur in der Übersicht; der Ordner reicht den Entwicklern.
            report($e);
        }

        return $reference;
    }

    public function setStatus(HelpRequest $helpRequest, string $status): void
    {
        $helpRequest->forceFill([
            'status' => $status,
            'resolved_at' => $status === HelpRequest::DONE ? now() : null,
        ])->save();

        // Den Stand auch im Ordner vermerken, damit die abgeholte Kopie ihn kennt.
        $disk = self::disk();
        $file = $helpRequest->path.'/meldung.json';
        $report = json_decode((string) $disk->get($file), true);
        if (is_array($report)) {
            $report['status'] = $status;
            $disk->put($file, (string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR));
        }
    }

    /**
     * Löscht eine Meldung sofort mit Ordner (Bildschirmfoto, Zustand), etwa nach einem
     * Widerspruch nach Art. 21 DSGVO.
     */
    public function delete(HelpRequest $helpRequest): void
    {
        self::disk()->deleteDirectory($helpRequest->path);
        $helpRequest->delete();
    }

    /**
     * @return array<mixed>|null
     */
    public function report(HelpRequest $helpRequest): ?array
    {
        $report = json_decode((string) self::disk()->get($helpRequest->path.'/meldung.json'), true);

        return is_array($report) ? $report : null;
    }

    public function screenshotPath(HelpRequest $helpRequest): ?string
    {
        $file = $helpRequest->path.'/screenshot.jpg';

        return self::disk()->exists($file) ? $file : null;
    }

    /**
     * Löschlauf: erledigte Meldungen nach 90 Tagen, alle nach 365 Tagen – Ordner und Zeile.
     * Ordner ohne Zeile (Index-Eintrag gescheitert) fallen über ihr Datum nach 365 Tagen weg.
     */
    public function purge(): int
    {
        $disk = self::disk();
        $doneBefore = now()->subDays(self::DONE_RETENTION_DAYS);
        $allBefore = now()->subDays(self::MAX_RETENTION_DAYS);
        $count = 0;

        HelpRequest::query()
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('status', HelpRequest::DONE)->where('resolved_at', '<', $doneBefore))
                ->orWhere('created_at', '<', $allBefore))
            ->each(function (HelpRequest $helpRequest) use ($disk, &$count) {
                $disk->deleteDirectory($helpRequest->path);
                $helpRequest->delete();
                $count++;
            });

        foreach ($disk->directories(self::DIRECTORY) as $day) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', basename($day)) !== 1) {
                continue;
            }
            if (CarbonImmutable::parse(basename($day))->lt($allBefore->startOfDay())) {
                $count += count($disk->directories($day));
                $disk->deleteDirectory($day);
            } elseif ($disk->allFiles($day) === []) {
                $disk->deleteDirectory($day);
            }
        }

        return $count;
    }

    public static function disk(): Filesystem
    {
        return Storage::disk('local');
    }

    private function reference(): string
    {
        $reference = '';
        for ($i = 0; $i < 6; $i++) {
            $reference .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $reference;
    }
}
