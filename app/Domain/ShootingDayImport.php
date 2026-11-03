<?php

namespace App\Domain;

use App\Models\AuditEntry;
use App\Models\Range;
use App\Models\ShootingDay;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Importiert Schießtage aus einer CSV-Datei (Terminplan aus der Tabellenkalkulation).
 *
 * Spalten: Datum, Beginn, Ende (Pflicht), Titel, Stände, Gäste erlaubt, Hinweise. Trennzeichen
 * Semikolon oder Komma, UTF-8 (mit oder ohne BOM) oder Windows-1252. Jede Zeile wird geprüft wie
 * im Formular: ab heute, höchstens ein Jahr im Voraus, Ende nach Beginn, nur Stände in Betrieb,
 * keine Überschneidung mit bestehenden Tagen oder anderen Zeilen auf demselben Stand.
 */
class ShootingDayImport
{
    public const MAX_ROWS = 500;

    /**
     * Spaltennamen in der Datei (klein geschrieben) => Feld.
     *
     * @var array<string, string>
     */
    private const COLUMNS = [
        'datum' => 'date',
        'beginn' => 'starts_at', 'von' => 'starts_at',
        'ende' => 'ends_at', 'bis' => 'ends_at',
        'titel' => 'title',
        'stände' => 'ranges', 'staende' => 'ranges', 'stand' => 'ranges',
        'gäste erlaubt' => 'guests_allowed', 'gaeste erlaubt' => 'guests_allowed', 'gäste' => 'guests_allowed', 'gastschützen erlaubt' => 'guests_allowed',
        'hinweise' => 'notes', 'hinweis' => 'notes', 'hinweise für mitglieder' => 'notes',
    ];

    private const YES = ['', 'ja', 'j', 'x', '1', 'yes', 'true', 'wahr'];

    private const NO = ['nein', 'n', '0', 'no', 'false', 'falsch', '-'];

    /**
     * @return array{rows: list<array<string, mixed>>, errors: list<string>, skipped: int}
     */
    public function parse(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;

        if (! mb_check_encoding($contents, 'UTF-8')) {
            $contents = mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
        }

        $contents = str_replace(["\r\n", "\r"], "\n", trim($contents));
        $firstLine = strtok($contents, "\n") ?: '';
        $delimiter = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';

        $records = $this->records($contents, $delimiter);
        $header = array_shift($records);

        if ($header === null || $records === []) {
            return $this->failed('Die Datei braucht eine Kopfzeile und mindestens einen Termin.');
        }

        $columns = array_map(fn ($name) => self::COLUMNS[mb_strtolower(trim((string) $name))] ?? null, $header['values']);
        $missing = array_diff_key(['date' => 'Datum', 'starts_at' => 'Beginn', 'ends_at' => 'Ende'], array_flip(array_filter($columns)));

        if ($missing !== []) {
            return $this->failed(sprintf('Es fehlen Spalten: %s. Bitte die Vorlage verwenden.', implode(', ', array_map(fn ($label) => "„{$label}“", $missing))));
        }

        if (count($records) > self::MAX_ROWS) {
            return $this->failed(sprintf('Die Datei hat mehr als %d Termine. Bitte in mehrere Dateien aufteilen.', self::MAX_ROWS));
        }

        $ranges = Range::ordered()->get(['id', 'name', 'active']);
        $rows = [];
        $errors = [];

        foreach ($records as $record) {
            $values = [];

            foreach ($columns as $i => $field) {
                if ($field !== null && ! isset($values[$field])) {
                    $values[$field] = trim((string) ($record['values'][$i] ?? ''));
                }
            }

            [$row, $problems] = $this->row($record['line'], $values, $ranges);

            if ($problems === []) {
                $problems = $this->overlaps($row, $rows);
            }

            if ($problems !== []) {
                $errors[] = sprintf('Zeile %d: %s', $record['line'], implode(' ', $problems));

                continue;
            }

            $rows[] = $row;
        }

        return ['rows' => $rows, 'errors' => $errors, 'skipped' => count($errors)];
    }

    /**
     * Legt die geprüften Zeilen an. Was sich seit der Vorschau geändert hat (Stand außer Betrieb,
     * inzwischen belegter Termin, Datum vorbei), wird ausgelassen und gezählt.
     *
     * @param  array<int, mixed>  $rows
     * @return array{created: int, skipped: int}
     */
    public function import(User $actor, array $rows): array
    {
        return DB::transaction(function () use ($actor, $rows) {
            $active = Range::where('active', true)->get(['id'])->map(fn (Range $range) => $range->id)->all();
            $created = [];
            $skipped = 0;

            foreach ($rows as $row) {
                if (! $this->stillValid($row, $active)) {
                    $skipped++;

                    continue;
                }

                $day = ShootingDay::create([
                    'date' => $row['date'],
                    'starts_at' => $row['starts_at'],
                    'ends_at' => $row['ends_at'],
                    'title' => $row['title'] ?? null,
                    'notes' => $row['notes'] ?? null,
                    'guests_allowed' => (bool) ($row['guests_allowed'] ?? true),
                ]);
                $day->ranges()->sync($row['ranges']);
                $created[] = $row['date'];
            }

            sort($created);

            AuditEntry::record($actor, 'shooting_day.imported', null, sprintf(
                'CSV-Import: %s%s%s',
                count($created) === 1 ? '1 Schießtag angelegt' : count($created).' Schießtage angelegt',
                $created !== [] ? sprintf(' (%s bis %s)', CarbonImmutable::parse($created[0])->format('d.m.Y'), CarbonImmutable::parse(end($created))->format('d.m.Y')) : '',
                $skipped > 0 ? sprintf(', %d ausgelassen', $skipped) : '',
            ));

            return ['created' => count($created), 'skipped' => $skipped];
        });
    }

    /**
     * Datensätze mit der Zeilennummer, in der sie beginnen (Kopfzeile = Zeile 1).
     * fgetcsv liest auch Hinweise mit Zeilenumbruch in Anführungszeichen richtig.
     *
     * @return list<array{line: int, values: list<string|null>}>
     */
    private function records(string $contents, string $delimiter): array
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            return [];
        }

        fwrite($stream, $contents);
        rewind($stream);

        $records = [];
        $line = 1;
        $position = 0;

        while (($values = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            $next = (int) ftell($stream);
            $start = $line;
            $line += substr_count($contents, "\n", $position, max(0, min($next, strlen($contents)) - $position));
            $position = $next;

            if (implode('', array_map(fn ($value) => trim((string) $value), $values)) === '') {
                continue;
            }

            $records[] = ['line' => $start, 'values' => $values];
        }

        fclose($stream);

        return $records;
    }

    /**
     * Eine Zeile lesen und prüfen wie das Formular.
     *
     * @param  array<string, string>  $values
     * @param  Collection<int, Range>  $ranges
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    private function row(int $line, array $values, Collection $ranges): array
    {
        $problems = [];

        $rawDate = $values['date'] ?? '';
        $date = $this->date($rawDate);

        if ($rawDate === '') {
            $problems[] = 'Datum fehlt.';
        } elseif ($date === null) {
            $problems[] = sprintf('Datum „%s“ nicht lesbar, bitte als TT.MM.JJJJ angeben.', $this->short($rawDate));
        } elseif ($date->lt(today())) {
            $problems[] = sprintf('Der %s liegt in der Vergangenheit.', $date->format('d.m.Y'));
        } elseif ($date->gt(today()->addYear())) {
            $problems[] = sprintf('Der %s liegt mehr als ein Jahr im Voraus.', $date->format('d.m.Y'));
        }

        $times = [];

        foreach (['starts_at' => 'Beginn', 'ends_at' => 'Ende'] as $field => $label) {
            $raw = $values[$field] ?? '';
            $times[$field] = $this->time($raw);

            if ($raw === '') {
                $problems[] = "$label fehlt.";
            } elseif ($times[$field] === null) {
                $problems[] = sprintf('%s „%s“ nicht lesbar, bitte als HH:MM angeben.', $label, $this->short($raw));
            }
        }

        if ($times['starts_at'] !== null && $times['ends_at'] !== null && $times['ends_at'] <= $times['starts_at']) {
            $problems[] = 'Das Ende muss nach dem Beginn liegen.';
        }

        $title = $values['title'] ?? '';
        $notes = $values['notes'] ?? '';

        if (mb_strlen($title) > 100) {
            $problems[] = 'Der Titel ist länger als 100 Zeichen.';
        }

        if (mb_strlen($notes) > 2000) {
            $problems[] = 'Die Hinweise sind länger als 2000 Zeichen.';
        }

        [$rangeIds, $rangeNames, $rangeProblems] = $this->ranges($values['ranges'] ?? '', $ranges);
        array_push($problems, ...$rangeProblems);

        $guests = $this->guests($values['guests_allowed'] ?? '');

        if ($guests === null) {
            $problems[] = sprintf('„Gäste erlaubt“ ist „%s“, bitte ja oder nein angeben.', $this->short($values['guests_allowed'] ?? ''));
        }

        return [[
            'line' => $line,
            'date' => $date?->toDateString(),
            'starts_at' => $times['starts_at'],
            'ends_at' => $times['ends_at'],
            'title' => $title !== '' ? $title : null,
            'notes' => $notes !== '' ? $notes : null,
            'guests_allowed' => $guests ?? true,
            'ranges' => $rangeIds,
            'range_names' => $rangeNames,
        ], $problems];
    }

    /**
     * Stände per Name (ohne Groß-/Kleinschreibung), getrennt durch „|“ oder Komma. Leer = alle Stände in Betrieb.
     *
     * @param  Collection<int, Range>  $ranges
     * @return array{0: list<int>, 1: list<string>, 2: list<string>}
     */
    private function ranges(string $cell, Collection $ranges): array
    {
        $active = $ranges->where('active', true)->values();

        if ($cell === '') {
            return $active->isEmpty()
                ? [[], [], ['Es ist kein Stand in Betrieb.']]
                : [$this->ids($active), $this->names($active), []];
        }

        $byName = $ranges->keyBy(fn (Range $range) => mb_strtolower(trim($range->name)));

        // Ein Name mit Komma bleibt ganz, wenn es ihn so gibt.
        $names = str_contains($cell, '|') || ! $byName->has(mb_strtolower($cell))
            ? preg_split(str_contains($cell, '|') ? '/\|/' : '/,/', $cell) ?: []
            : [$cell];

        $found = collect();
        $problems = [];

        foreach (array_filter(array_map('trim', $names), fn ($name) => $name !== '') as $name) {
            $range = $byName->get(mb_strtolower($name));

            if ($range === null) {
                $problems[] = sprintf('Stand „%s“ gibt es nicht.', $this->short($name));
            } elseif (! $range->active) {
                $problems[] = sprintf('Stand „%s“ ist außer Betrieb.', $range->name);
            } else {
                $found->put($range->id, $range);
            }
        }

        if ($problems === [] && $found->isEmpty()) {
            $problems[] = 'Keine Stände angegeben.';
        }

        // Reihenfolge wie in der Standverwaltung.
        $found = $ranges->whereIn('id', $found->keys()->all())->values();

        return [$this->ids($found), $this->names($found), $problems];
    }

    /**
     * @param  Collection<int, Range>  $ranges
     * @return list<int>
     */
    private function ids(Collection $ranges): array
    {
        return array_values($ranges->map(fn (Range $range) => $range->id)->all());
    }

    /**
     * @param  Collection<int, Range>  $ranges
     * @return list<string>
     */
    private function names(Collection $ranges): array
    {
        return array_values($ranges->map(fn (Range $range) => $range->name)->all());
    }

    /**
     * Überschneidung mit bestehenden Tagen und mit früheren Zeilen der Datei.
     *
     * @param  array<string, mixed>  $row
     * @param  list<array<string, mixed>>  $accepted
     * @return list<string>
     */
    private function overlaps(array $row, array $accepted): array
    {
        $window = TimeWindow::fromStrings($row['starts_at'], $row['ends_at']);
        $clash = ShootingDayOverlap::find($row['date'], $window, $row['ranges']);

        if ($clash !== null) {
            return [ShootingDayOverlap::message($clash)];
        }

        foreach ($accepted as $other) {
            if ($other['date'] === $row['date']
                && array_intersect($other['ranges'], $row['ranges']) !== []
                && TimeWindow::fromStrings($other['starts_at'], $other['ends_at'])->overlaps($window)) {
                return [sprintf('Überschneidet sich mit Zeile %d (selber Tag, selber Stand).', $other['line'])];
            }
        }

        return [];
    }

    /**
     * Beim Übernehmen erneut prüfen, was sich seit der Vorschau geändert haben kann.
     *
     * @param  array<int, int>  $active
     */
    private function stillValid(mixed $row, array $active): bool
    {
        if (! is_array($row) || ! isset($row['date'], $row['starts_at'], $row['ends_at'], $row['ranges']) || ! is_array($row['ranges']) || $row['ranges'] === []) {
            return false;
        }

        if (CarbonImmutable::parse($row['date'])->lt(today()) || array_diff($row['ranges'], $active) !== []) {
            return false;
        }

        return ShootingDayOverlap::find($row['date'], TimeWindow::fromStrings($row['starts_at'], $row['ends_at']), $row['ranges']) === null;
    }

    private function date(string $value): ?CarbonImmutable
    {
        foreach (['d.m.Y', 'j.n.Y', 'j.m.Y', 'd.n.Y', 'Y-m-d', 'd.m.y'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $value);

                if ($date !== null && $date->format($format) === $value) {
                    return $date;
                }
            } catch (Throwable) {
                // nächstes Format versuchen
            }
        }

        return null;
    }

    /**
     * „9:00“, „09:00“, „09:00:00“ (Excel) oder „9.00“ als HH:MM.
     */
    private function time(string $value): ?string
    {
        if (! preg_match('/^(\d{1,2})[:.](\d{2})(?::00)?$/', $value, $match)) {
            return null;
        }

        [$hours, $minutes] = [(int) $match[1], (int) $match[2]];

        return $hours < 24 && $minutes < 60 ? sprintf('%02d:%02d', $hours, $minutes) : null;
    }

    private function guests(string $value): ?bool
    {
        $value = mb_strtolower($value);

        return match (true) {
            in_array($value, self::YES, true) => true,
            in_array($value, self::NO, true) => false,
            default => null,
        };
    }

    private function short(string $value): string
    {
        return mb_strlen($value) > 40 ? mb_substr($value, 0, 40).'…' : $value;
    }

    /**
     * @return array{rows: list<array<string, mixed>>, errors: list<string>, skipped: int}
     */
    private function failed(string $message): array
    {
        return ['rows' => [], 'errors' => [$message], 'skipped' => 0];
    }
}
