<?php

namespace App\Domain;

use App\Enums\Role;
use App\Models\AuditEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

/**
 * Importiert Mitglieder aus einer CSV-Datei (Export aus Vereinsverwaltung oder Tabellenkalkulation).
 *
 * Erkennt Trennzeichen (; oder ,) und gängige deutsche Spaltennamen. Bestehende Mitglieder
 * (gleiche E-Mail) werden aktualisiert, nie gelöscht. Passwörter werden nie importiert.
 */
class MemberImport
{
    /**
     * Spaltennamen in der Datei => Feld.
     *
     * @var array<string, string>
     */
    private const COLUMNS = [
        'name' => 'name', 'vollständiger name' => 'name',
        'vorname' => 'first_name', 'nachname' => 'last_name', 'familienname' => 'last_name',
        'email' => 'email', 'e-mail' => 'email', 'e-mail-adresse' => 'email', 'mail' => 'email',
        'mitgliedsnummer' => 'member_number', 'mitgliedsnr' => 'member_number', 'mitgliedsnr.' => 'member_number', 'nr' => 'member_number', 'nr.' => 'member_number',
        'eintritt' => 'member_since', 'eintrittsdatum' => 'member_since', 'mitglied seit' => 'member_since',
        'aufsicht bis' => 'supervisor_until', 'standaufsicht bis' => 'supervisor_until',
    ];

    /**
     * @return array{rows: list<array<string, mixed>>, errors: list<string>, columns: list<string>}
     */
    public function parse(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;

        if (! mb_check_encoding($contents, 'UTF-8')) {
            $contents = mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
        }

        $lines = preg_split('/\r\n|\r|\n/', trim($contents)) ?: [];

        if (count($lines) < 2) {
            return ['rows' => [], 'errors' => ['Die Datei braucht eine Kopfzeile und mindestens ein Mitglied.'], 'columns' => []];
        }

        $delimiter = substr_count($lines[0], ';') >= substr_count($lines[0], ',') ? ';' : ',';
        $header = array_map(fn ($h) => self::COLUMNS[mb_strtolower(trim($h))] ?? null, str_getcsv($lines[0], $delimiter, '"', ''));
        $known = array_values(array_filter($header));

        if (! in_array('email', $known, true) || (! in_array('name', $known, true) && ! in_array('last_name', $known, true))) {
            return ['rows' => [], 'errors' => ['Es fehlen Spalten. Benötigt werden „E-Mail“ und „Name“ (oder „Vorname“ und „Nachname“).'], 'columns' => $known];
        }

        $rows = [];
        $errors = [];
        $seen = [];
        $seenNumbers = [];

        foreach (array_slice($lines, 1) as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $number = $index + 2;
            $values = str_getcsv($line, $delimiter, '"', '');
            $record = [];

            foreach ($header as $i => $field) {
                if ($field !== null) {
                    $record[$field] = trim($values[$i] ?? '');
                }
            }

            $name = $record['name'] ?? trim(($record['first_name'] ?? '').' '.($record['last_name'] ?? ''));

            $rawDates = ['Eintritt' => [$record['member_since'] ?? '', true], 'Aufsicht bis' => [$record['supervisor_until'] ?? '', false]];
            $unreadable = collect($rawDates)
                ->filter(fn (array $date) => $date[0] !== '' && $this->date($date[0], past: $date[1]) === null)
                ->map(fn (array $date, string $label) => sprintf('%s „%s“', $label, $date[0]));

            if ($unreadable->isNotEmpty()) {
                $errors[] = sprintf('Zeile %d: Datum nicht lesbar (%s). Bitte als TT.MM.JJJJ angeben.', $number, $unreadable->implode(', '));

                continue;
            }
            $row = [
                'line' => $number,
                'name' => $name,
                'email' => mb_strtolower($record['email'] ?? ''),
                'member_number' => ($record['member_number'] ?? '') ?: null,
                'member_since' => $this->date($record['member_since'] ?? '', past: true),
                'supervisor_until' => $this->date($record['supervisor_until'] ?? ''),
            ];

            $validator = Validator::make($row, [
                'name' => ['required', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
            ], [], ['name' => 'Name', 'email' => 'E-Mail']);

            if ($validator->fails()) {
                $errors[] = sprintf('Zeile %d: %s', $number, implode(' ', $validator->errors()->all()));

                continue;
            }

            if (isset($seen[$row['email']])) {
                $errors[] = sprintf('Zeile %d: %s steht doppelt in der Datei (zuerst Zeile %d).', $number, $row['email'], $seen[$row['email']]);

                continue;
            }

            if ($row['member_number'] !== null) {
                if (isset($seenNumbers[$row['member_number']])) {
                    $errors[] = sprintf('Zeile %d: Mitgliedsnummer %s steht doppelt in der Datei (zuerst Zeile %d).', $number, $row['member_number'], $seenNumbers[$row['member_number']]);

                    continue;
                }

                $owner = User::where('member_number', $row['member_number'])->value('email');

                if ($owner !== null && $owner !== $row['email']) {
                    $errors[] = sprintf('Zeile %d: Mitgliedsnummer %s gehört bereits einem anderen Mitglied.', $number, $row['member_number']);

                    continue;
                }

                $seenNumbers[$row['member_number']] = $number;
            }

            $seen[$row['email']] = $number;
            $row['exists'] = User::where('email', $row['email'])->exists();
            $rows[] = $row;
        }

        return ['rows' => $rows, 'errors' => $errors, 'columns' => $known];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, updated: int, users: list<User>}
     */
    public function import(User $actor, array $rows): array
    {
        return DB::transaction(function () use ($actor, $rows) {
            $created = 0;
            $updated = 0;
            $newUsers = [];

            foreach ($rows as $row) {
                $user = User::where('email', $row['email'])->first();
                $isNew = $user === null;
                $user ??= new User;

                $attributes = array_filter([
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'member_number' => $row['member_number'],
                    'member_since' => $row['member_since'],
                ], fn ($value) => $value !== null && $value !== '');

                if ($isNew) {
                    $attributes += ['role' => Role::Member, 'password' => Hash::make(Str::random(40))];
                }

                $user->forceFill($attributes)->save();
                $isNew ? $created++ : $updated++;

                // „Aufsicht bis“ wird bewusst nicht übernommen: Das Register (§ 10 Abs. 3 AWaffV)
                // braucht Umfang und eine geprüfte Bestätigung je Person und darf nicht durch
                // einen erneuten Import überschrieben oder reaktiviert werden.

                if ($isNew) {
                    $newUsers[] = $user;
                }
            }

            AuditEntry::record($actor, 'user.imported', null, sprintf('CSV-Import: %d neu, %d aktualisiert', $created, $updated));

            return ['created' => $created, 'updated' => $updated, 'users' => $newUsers];
        });
    }

    private function date(string $value, bool $past = false): ?string
    {
        if ($value === '') {
            return null;
        }

        foreach (['d.m.Y', 'j.n.Y', 'j.m.Y', 'd.n.Y', 'Y-m-d', 'd.m.y'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $value);

                if ($date !== null && $date->format($format) === $value) {
                    // Zweistellige Jahre: was in der Zukunft läge, gehört ins vorige Jahrhundert (Eintritt 65 = 1965).
                    if ($past && $format === 'd.m.y' && $date->isAfter(now()->endOfYear())) {
                        $date = $date->subCentury();
                    }

                    return $date->toDateString();
                }
            } catch (Throwable) {
                // nächstes Format versuchen
            }
        }

        return null;
    }
}
