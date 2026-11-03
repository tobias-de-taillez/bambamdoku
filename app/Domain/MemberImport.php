<?php

namespace App\Domain;

use App\Enums\Role;
use App\Enums\SupervisionScope;
use App\Models\AuditEntry;
use App\Models\Qualification;
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
 * Erkennt Trennzeichen (; oder ,) und gängige deutsche Spaltennamen. Jede Zeile braucht einen
 * Namen und eine E-Mail-Adresse oder Mitgliedsnummer (Mitglieder ohne Konto, K4). Bestehende
 * Mitglieder (gleiche E-Mail, sonst gleiche Mitgliedsnummer) werden aktualisiert, nie gelöscht.
 * Passwörter und Rollen werden nie importiert; Anträge nimmt der Vorstand einzeln auf.
 *
 * Mit „Aufsicht“ entsteht eine Zeile im Aufsichtsregister (§ 10 Abs. 3 AWaffV), mit denselben
 * Regeln wie beim Eintragen von Hand: gültig bis und geprüft am sind Pflicht, Volljährigkeit und
 * Sachkunde bestätigt der Vorstand, die eigene Registrierung trägt ein anderes Vorstandsmitglied ein.
 *
 * @phpstan-type ImportRow array{
 *     line: int,
 *     name: string,
 *     email: string,
 *     member_number: string|null,
 *     member_since: string|null,
 *     adult: bool|null,
 *     scope: string|null,
 *     valid_until: string|null,
 *     checked_on: string|null,
 *     note: string|null,
 *     exists: bool,
 *     user_id: int|null,
 *     registers: bool,
 *     current_scope: string|null,
 *     current_until: string|null,
 *     adult_confirmed: bool,
 *     adult_sets: bool,
 * }
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
        'volljährig geprüft' => 'adult', 'volljaehrig geprueft' => 'adult', 'volljährig' => 'adult',
        'aufsicht' => 'scope', 'standaufsicht' => 'scope', 'aufsicht umfang' => 'scope',
        'aufsicht gültig bis' => 'valid_until', 'aufsicht gueltig bis' => 'valid_until', 'aufsicht bis' => 'valid_until', 'standaufsicht bis' => 'valid_until',
        'aufsicht geprüft am' => 'checked_on', 'aufsicht geprueft am' => 'checked_on',
        'aufsicht bemerkung' => 'note', 'aufsicht vermerk' => 'note',
    ];

    /**
     * Werte der Spalte „Aufsicht“ => Umfang im Register. „entzogen“ geht nur von Hand.
     *
     * @var array<string, SupervisionScope>
     */
    private const SCOPES = [
        'erlaubnispflichtig' => SupervisionScope::Licensed,
        'erlaubnisfrei' => SupervisionScope::Exempt,
    ];

    /**
     * Vorlage zum Herunterladen: UTF-8 mit BOM (für Excel), Semikolon, feste Beispielzeilen.
     */
    public static function template(): string
    {
        return "\xEF\xBB\xBF"
            ."Vorname;Nachname;E-Mail;Mitgliedsnummer;Eintritt;Volljährig geprüft;Aufsicht;Aufsicht gültig bis;Aufsicht geprüft am;Aufsicht Bemerkung\r\n"
            ."Erika;Musterfrau;erika.musterfrau@example.org;1001;01.03.2015;ja;erlaubnisfrei;31.12.2027;01.11.2026;Lehrgang Standaufsicht (Beispiel)\r\n"
            ."Karl;Ohnemail;;1002;15.06.2019;nein;;;;\r\n";
    }

    /**
     * Datei prüfen, noch nichts speichern. Mit $actor greift das Vier-Augen-Prinzip beim Register.
     *
     * @return array{rows: list<ImportRow>, errors: list<string>, columns: list<string>}
     */
    public function parse(string $contents, ?User $actor = null): array
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
        $header = array_map(fn ($h) => self::COLUMNS[mb_strtolower(trim((string) $h))] ?? null, str_getcsv($lines[0], $delimiter, '"', ''));
        $known = array_values(array_filter($header));

        $hasName = in_array('name', $known, true) || in_array('last_name', $known, true);
        $hasKey = in_array('email', $known, true) || in_array('member_number', $known, true);

        if (! $hasName || ! $hasKey) {
            return ['rows' => [], 'errors' => ['Es fehlen Spalten. Benötigt werden „Name“ (oder „Vorname“ und „Nachname“) und „E-Mail“ oder „Mitgliedsnummer“.'], 'columns' => $known];
        }

        $today = CarbonImmutable::today()->toDateString();
        $rows = [];
        $errors = [];
        $seen = [];
        $seenNumbers = [];
        $seenUsers = [];

        foreach (array_slice($lines, 1) as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $number = $index + 2;
            $values = str_getcsv($line, $delimiter, '"', '');
            $record = [];

            foreach ($header as $i => $field) {
                if ($field !== null) {
                    $record[$field] = trim((string) ($values[$i] ?? ''));
                }
            }

            $name = $record['name'] ?? trim(($record['first_name'] ?? '').' '.($record['last_name'] ?? ''));

            $rawDates = [
                'Eintritt' => [$record['member_since'] ?? '', true],
                'Aufsicht gültig bis' => [$record['valid_until'] ?? '', false],
                'Aufsicht geprüft am' => [$record['checked_on'] ?? '', true],
            ];
            $unreadable = collect($rawDates)
                ->filter(fn (array $date) => $date[0] !== '' && $this->date($date[0], past: $date[1]) === null)
                ->map(fn (array $date, string $label) => sprintf('%s „%s“', $label, $date[0]));

            if ($unreadable->isNotEmpty()) {
                $errors[] = sprintf('Zeile %d: Datum nicht lesbar (%s). Bitte als TT.MM.JJJJ angeben.', $number, $unreadable->implode(', '));

                continue;
            }

            $adult = mb_strtolower($record['adult'] ?? '');
            $scope = self::SCOPES[mb_strtolower($record['scope'] ?? '')] ?? null;

            $row = [
                'line' => $number,
                'name' => $name,
                'email' => mb_strtolower($record['email'] ?? ''),
                'member_number' => $this->nullable($record['member_number'] ?? ''),
                'member_since' => $this->date($record['member_since'] ?? '', past: true),
                'adult' => $adult === '' ? null : $adult === 'ja',
                'scope' => $scope?->value,
                'valid_until' => $this->date($record['valid_until'] ?? ''),
                'checked_on' => $this->date($record['checked_on'] ?? '', past: true),
                'note' => $this->nullable($record['note'] ?? ''),
            ];

            $validator = Validator::make($row, [
                'name' => ['required', 'max:255'],
                'email' => ['nullable', 'email', 'max:255'],
                'member_number' => ['nullable', 'max:50'],
            ], [], ['name' => 'Name', 'email' => 'E-Mail', 'member_number' => 'Mitgliedsnummer']);

            $problems = $validator->errors()->all();

            if ($row['member_since'] !== null && $row['member_since'] > $today) {
                $problems[] = '„Eintritt“ darf nicht in der Zukunft liegen.';
            }

            if (! in_array($adult, ['', 'ja', 'nein'], true)) {
                $problems[] = sprintf('„Volljährig geprüft“ kennt nur ja, nein oder leer, nicht „%s“.', $record['adult'] ?? '');
            }

            array_push($problems, ...$this->supervisionProblems($row, $record['scope'] ?? '', $today));

            if ($problems !== []) {
                $errors[] = sprintf('Zeile %d: %s', $number, implode(' ', $problems));

                continue;
            }

            if ($row['email'] === '' && $row['member_number'] === null) {
                $errors[] = sprintf('Zeile %d: Bitte E-Mail-Adresse oder Mitgliedsnummer angeben, sonst lässt sich das Mitglied später nicht wiederfinden.', $number);

                continue;
            }

            if ($row['email'] !== '' && isset($seen[$row['email']])) {
                $errors[] = sprintf('Zeile %d: %s steht doppelt in der Datei (zuerst Zeile %d).', $number, $row['email'], $seen[$row['email']]);

                continue;
            }

            if ($row['member_number'] !== null && isset($seenNumbers[$row['member_number']])) {
                $errors[] = sprintf('Zeile %d: Mitgliedsnummer %s steht doppelt in der Datei (zuerst Zeile %d).', $number, $row['member_number'], $seenNumbers[$row['member_number']]);

                continue;
            }

            $match = $this->match($row['email'], $row['member_number']);

            if (is_string($match)) {
                $errors[] = sprintf('Zeile %d: %s', $number, $match);

                continue;
            }

            // Zwei Zeilen über verschiedene Schlüssel für dasselbe Mitglied: die zweite wäre ein stilles Überschreiben.
            if ($match !== null && isset($seenUsers[$match->id])) {
                $errors[] = sprintf('Zeile %d: %s steht doppelt in der Datei (zuerst Zeile %d).', $number, $match->name, $seenUsers[$match->id]);

                continue;
            }

            $registers = $scope !== null && ! $this->unchanged($match, $scope, $row['valid_until']);

            // Vier-Augen-Prinzip wie beim Eintragen von Hand.
            if ($registers && $actor !== null && $match !== null && $match->is($actor)) {
                $errors[] = sprintf('Zeile %d: Die eigene Registrierung trägt ein anderes Vorstandsmitglied ein.', $number);

                continue;
            }

            if ($match !== null) {
                $seenUsers[$match->id] = $number;
            }

            if ($row['email'] !== '') {
                $seen[$row['email']] = $number;
            }

            if ($row['member_number'] !== null) {
                $seenNumbers[$row['member_number']] = $number;
            }

            $confirmed = $match?->adult_confirmed_at !== null;

            $rows[] = [
                ...$row,
                'exists' => $match !== null,
                // Die Zuordnung aus der Vorschau gilt auch beim Übernehmen, auch wenn
                // frühere Zeilen der Datei inzwischen Nummern geändert haben.
                'user_id' => $match?->id,
                'registers' => $registers,
                'current_scope' => $match?->supervisor_scope?->value,
                'current_until' => $match?->supervisor_until?->toDateString(),
                'adult_confirmed' => $confirmed,
                'adult_sets' => ! $confirmed && ($row['adult'] === true || $registers),
            ];
        }

        return ['rows' => $rows, 'errors' => $errors, 'columns' => $known];
    }

    /**
     * Übernimmt die geprüften Zeilen in einer Transaktion. Was sich seit der Vorschau geändert
     * hat (Mitglied gelöscht, Adresse oder Nummer inzwischen vergeben, offener Antrag), fällt raus.
     *
     * @param  list<ImportRow>  $rows
     * @return array{created: int, updated: int, skipped: int, registered: int, adult: int, users: list<User>}
     */
    public function import(User $actor, array $rows): array
    {
        return DB::transaction(function () use ($actor, $rows) {
            $created = 0;
            $updated = 0;
            $skipped = 0;
            $registered = 0;
            $adult = 0;
            $newUsers = [];

            foreach ($rows as $row) {
                if (isset($row['user_id'])) {
                    $user = User::query()->whereKey($row['user_id'])->first() ?? false;
                } else {
                    $match = $this->match($row['email'], $row['member_number']);
                    $user = is_string($match) ? false : $match;
                }

                if ($user === false || $user?->isApplicant() || $this->taken($user, $row['email'], $row['member_number'])) {
                    $skipped++;

                    continue;
                }

                $scope = $row['scope'] !== null ? SupervisionScope::from($row['scope']) : null;
                $registers = $scope !== null && ! $this->unchanged($user, $scope, $row['valid_until']);

                if ($registers && $user !== null && $user->is($actor)) {
                    $skipped++;

                    continue;
                }

                $isNew = $user === null;
                $user ??= new User;

                $attributes = array_filter([
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'member_number' => $row['member_number'],
                    'member_since' => $row['member_since'],
                ], fn ($value) => $value !== null && $value !== '');

                if ($isNew) {
                    // Unbekanntes Zufallspasswort; ohne E-Mail-Adresse kein Konto.
                    $attributes += ['role' => Role::Member, 'password' => $row['email'] !== '' ? Hash::make(Str::random(40)) : null];
                }

                // E12: „volljährig geprüft“ wird einmal gesetzt, nie zurückgenommen. Eine Registrierung
                // als Aufsicht setzt die bestätigte Prüfung der Volljährigkeit voraus.
                if (($row['adult'] === true || $registers) && $user->adult_confirmed_at === null) {
                    $attributes['adult_confirmed_at'] = now();
                    $adult++;
                }

                $user->forceFill($attributes)->save();
                $isNew ? $created++ : $updated++;

                if ($registers) {
                    Qualification::register($user, $scope, $row['valid_until'], (string) $row['checked_on'], $actor, $row['note']);
                    $registered++;
                }

                if ($isNew) {
                    $newUsers[] = $user;
                }
            }

            AuditEntry::record($actor, 'user.imported', null, sprintf(
                'CSV-Import: %d neu, %d aktualisiert, %d ausgelassen, %d Aufsicht eingetragen, %d volljährig geprüft',
                $created,
                $updated,
                $skipped,
                $registered,
                $adult,
            ));

            return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'registered' => $registered, 'adult' => $adult, 'users' => $newUsers];
        });
    }

    /**
     * Mindestens eine Zeile trägt eine Aufsicht ein: Dann bestätigt der Vorstand Volljährigkeit
     * und Sachkunde, auch für Zeilen, die das Register unverändert lassen.
     *
     * @param  list<ImportRow>  $rows
     */
    public function marksSupervisors(array $rows): bool
    {
        return array_filter($rows, fn (array $row) => $row['scope'] !== null) !== [];
    }

    /**
     * Regeln des Aufsichtsregisters je Zeile, wie beim Eintragen von Hand.
     *
     * @param  array{adult: bool|null, scope: string|null, valid_until: string|null, checked_on: string|null, note: string|null}  $row
     * @return list<string>
     */
    private function supervisionProblems(array $row, string $scopeText, string $today): array
    {
        if ($scopeText !== '' && $row['scope'] === null) {
            return [sprintf('„Aufsicht“ kennt nur erlaubnispflichtig, erlaubnisfrei oder leer, nicht „%s“.', $scopeText)];
        }

        if ($row['scope'] === null) {
            return $row['valid_until'] !== null || $row['checked_on'] !== null || $row['note'] !== null
                ? ['„Aufsicht gültig bis“, „Aufsicht geprüft am“ und „Aufsicht Bemerkung“ gelten nur zusammen mit „Aufsicht“ (erlaubnispflichtig oder erlaubnisfrei).']
                : [];
        }

        $problems = [];

        if ($row['valid_until'] === null) {
            $problems[] = 'Bitte „Aufsicht gültig bis“ angeben.';
        }

        if ($row['checked_on'] === null) {
            $problems[] = 'Bitte „Aufsicht geprüft am“ angeben.';
        } elseif ($row['checked_on'] > $today) {
            $problems[] = '„Aufsicht geprüft am“ darf nicht in der Zukunft liegen.';
        }

        if ($row['valid_until'] !== null && $row['checked_on'] !== null && $row['valid_until'] < $row['checked_on']) {
            $problems[] = '„Aufsicht gültig bis“ darf nicht vor „Aufsicht geprüft am“ liegen.';
        }

        if ($row['adult'] === false) {
            $problems[] = 'Eine Aufsicht muss volljährig sein, „Volljährig geprüft“ steht aber auf nein.';
        }

        if ($row['note'] !== null && mb_strlen($row['note']) > 255) {
            $problems[] = '„Aufsicht Bemerkung“ ist zu lang (höchstens 255 Zeichen).';
        }

        return $problems;
    }

    /**
     * Gleicher Umfang und gleiches Ende wie die jüngste Registerzeile: kein neuer Eintrag,
     * damit ein erneuter Import das Register nicht mit gleichen Zeilen füllt.
     */
    private function unchanged(?User $user, SupervisionScope $scope, ?string $validUntil): bool
    {
        return $user !== null
            && $user->supervisor_scope === $scope
            && $user->supervisor_until?->toDateString() === $validUntil;
    }

    /**
     * E-Mail oder Mitgliedsnummer gehört seit der Vorschau einem anderen Konto.
     */
    private function taken(?User $user, string $email, ?string $memberNumber): bool
    {
        $others = fn () => User::query()->when($user !== null, fn ($query) => $query->whereKeyNot($user?->id));

        return ($email !== '' && $others()->whereRaw('lower(email) = ?', [$email])->exists())
            || ($memberNumber !== null && $others()->where('member_number', $memberNumber)->exists());
    }

    /**
     * Bestehendes Mitglied zur Zeile: zuerst über die E-Mail, sonst über die Mitgliedsnummer.
     * Ein String ist ein Konflikt, der die Zeile ausschließt.
     */
    private function match(string $email, ?string $memberNumber): User|string|null
    {
        $byEmail = $email !== '' ? User::whereRaw('lower(email) = ?', [$email])->first() : null;
        $byNumber = $memberNumber !== null ? User::where('member_number', $memberNumber)->first() : null;

        if ($byEmail && $byNumber && ! $byEmail->is($byNumber)) {
            return sprintf('Mitgliedsnummer %s gehört bereits einem anderen Mitglied.', $memberNumber);
        }

        if (! $byEmail && $byNumber && $email !== '' && $byNumber->email !== null) {
            return sprintf('Mitgliedsnummer %s gehört bereits einem anderen Mitglied (andere E-Mail-Adresse).', $memberNumber);
        }

        $user = $byEmail ?? $byNumber;

        // Anträge werden nie über den Import Mitglied (E7).
        if ($user?->isApplicant()) {
            return 'Für diese Person liegt ein offener Mitgliedsantrag vor. Antrag zuerst aufnehmen oder ablehnen.';
        }

        return $user;
    }

    private function nullable(string $value): ?string
    {
        return $value === '' ? null : $value;
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
