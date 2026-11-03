<?php

namespace App\Domain;

use App\Enums\Role;
use App\Enums\SupervisionScope;
use App\Models\AuditEntry;
use App\Models\Qualification;
use App\Models\User;
use App\Support\CsvReader;
use App\Support\UserChanges;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Normalizer;
use Throwable;
use UnexpectedValueException;

/**
 * Importiert Mitglieder aus einer CSV-Datei (Export aus Vereinsverwaltung oder Tabellenkalkulation).
 *
 * Erkennt Trennzeichen (; oder ,) und gängige deutsche Spaltennamen. Jede Zeile braucht einen
 * Namen und eine E-Mail-Adresse oder Mitgliedsnummer (Mitglieder ohne Konto, K4). Bestehende
 * Mitglieder (gleiche E-Mail, sonst gleiche Mitgliedsnummer, auch ohne führende Nullen, und
 * gleicher Name) werden aktualisiert, nie gelöscht. Passwörter und Rollen werden nie importiert;
 * Anträge nimmt der Vorstand einzeln auf.
 *
 * Mit „Aufsicht“ entsteht eine Zeile im Aufsichtsregister (§ 10 Abs. 3 AWaffV), mit denselben
 * Regeln wie beim Eintragen von Hand: gültig bis und geprüft am sind Pflicht, Volljährigkeit und
 * Sachkunde bestätigt der Vorstand, die eigene Registrierung trägt ein anderes Vorstandsmitglied ein.
 * Eine entzogene Aufsicht trägt nur der Vorstand einzeln wieder ein, nie der Import.
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
 *     matched_by: 'email'|'number'|null,
 *     current_name: string|null,
 *     current_email: string|null,
 *     current_member_number: string|null,
 *     current_member_since: string|null,
 *     register_id: int|null,
 *     current_scope: string|null,
 *     current_until: string|null,
 *     current_checked_on: string|null,
 *     registers: bool,
 *     adult_confirmed: bool,
 *     adult_sets: bool,
 *     hints: list<string>,
 *     register_hint: string|null,
 * }
 * @phpstan-type Members array{email: array<string, User>, number: array<string, User>, trimmed: array<string, list<User>>, register: array<int, Qualification>}
 */
class MemberImport
{
    public const MAX_ROWS = 2000;

    /**
     * Spaltennamen in der Datei => Feld. „Volljährig“ allein zählt nicht: Eine berechnete Spalte
     * aus einem anderen System ist keine Prüfung durch den Vorstand (E12).
     *
     * @var array<string, string>
     */
    private const COLUMNS = [
        'name' => 'name', 'vollständiger name' => 'name',
        'vorname' => 'first_name', 'nachname' => 'last_name', 'familienname' => 'last_name',
        'email' => 'email', 'e-mail' => 'email', 'e-mail-adresse' => 'email', 'mail' => 'email',
        'mitgliedsnummer' => 'member_number', 'mitgliedsnr' => 'member_number', 'mitgliedsnr.' => 'member_number', 'nr' => 'member_number', 'nr.' => 'member_number',
        'eintritt' => 'member_since', 'eintrittsdatum' => 'member_since', 'mitglied seit' => 'member_since',
        'volljährig geprüft' => 'adult', 'volljaehrig geprueft' => 'adult', 'volljährig geprueft' => 'adult', 'volljaehrig geprüft' => 'adult',
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

    private const WEAPONS_EXPERTISE = ', einschließlich Waffensachkunde für die als erlaubnispflichtig markierten Personen';

    /**
     * Akademische Titel, die beim Namensvergleich wegfallen („Dr. Hans Meier“ = „Hans Meier“).
     *
     * @var list<string>
     */
    private const TITLES = ['dr', 'prof', 'dipl'];

    /**
     * Vorlage zum Herunterladen: UTF-8 mit BOM (für Excel), Semikolon, feste Beispielzeilen.
     * Die Beispielzeilen treffen nie ein echtes Mitglied: Nummern „Beispiel-…“ und @example.org
     * weist der Import als Fehler ab.
     */
    public static function template(): string
    {
        return "\xEF\xBB\xBF"
            ."Vorname;Nachname;E-Mail;Mitgliedsnummer;Eintritt;Volljährig geprüft;Aufsicht;Aufsicht gültig bis;Aufsicht geprüft am;Aufsicht Bemerkung\r\n"
            ."Erika;Musterfrau;erika.musterfrau@example.org;Beispiel-1;01.03.2015;ja;erlaubnisfrei;31.12.2027;01.11.2026;Lehrgang Standaufsicht (Beispiel)\r\n"
            ."Karl;Ohnemail;;Beispiel-2;15.06.2019;nein;;;;\r\n";
    }

    /**
     * Datei prüfen, noch nichts speichern. Mit $actor greift das Vier-Augen-Prinzip beim Register.
     *
     * @return array{rows: list<ImportRow>, errors: list<string>, columns: list<string>}
     */
    public function parse(string $contents, ?User $actor = null): array
    {
        // Nicht geschlossenes Anführungszeichen: Die folgenden Mitglieder wären still verschluckt
        // und stünden als Text in der Bemerkung. Dann wird nichts übernommen.
        try {
            $records = CsvReader::records($contents);
        } catch (UnexpectedValueException $exception) {
            return ['rows' => [], 'errors' => [$exception->getMessage()], 'columns' => []];
        }

        $header = array_shift($records);

        if ($header === null || $records === []) {
            return ['rows' => [], 'errors' => ['Die Datei braucht eine Kopfzeile und mindestens ein Mitglied.'], 'columns' => []];
        }

        $headings = array_map(fn (string $name) => mb_strtolower(trim($name)), $header['values']);
        $columns = array_map(fn (string $name) => self::COLUMNS[$name] ?? null, $headings);

        // „Name;Vorname“ ohne „Nachname“: „Name“ ist dann der Nachname. Sonst fiele der Vorname
        // still weg und ein über die E-Mail gefundenes Mitglied hieße nur noch wie die Familie.
        if (in_array('first_name', $columns, true) && ! in_array('last_name', $columns, true)) {
            foreach ($headings as $i => $heading) {
                if ($heading === 'name') {
                    $columns[$i] = 'last_name';
                }
            }
        }

        $known = array_values(array_filter($columns));

        $hasName = in_array('name', $known, true) || in_array('last_name', $known, true);
        $hasKey = in_array('email', $known, true) || in_array('member_number', $known, true);

        if (! $hasName || ! $hasKey) {
            return ['rows' => [], 'errors' => ['Es fehlen Spalten. Benötigt werden „Name“ (oder „Vorname“ und „Nachname“) und „E-Mail“ oder „Mitgliedsnummer“.'], 'columns' => $known];
        }

        // Vor der ersten Datenbankabfrage: Eine riesige Datei bremst die Vorschau und bläht die Sitzung auf.
        if (count($records) > self::MAX_ROWS) {
            return ['rows' => [], 'errors' => [sprintf('Die Datei hat mehr als %d Mitglieder. Bitte in mehrere Dateien aufteilen.', self::MAX_ROWS)], 'columns' => $known];
        }

        $members = $this->members();
        $today = CarbonImmutable::today()->toDateString();
        $rows = [];
        $errors = [];
        $seen = [];
        $seenNumbers = [];
        $seenUsers = [];

        foreach ($records as $values) {
            $number = $values['line'];
            $record = [];

            foreach ($columns as $i => $field) {
                if ($field !== null) {
                    $record[$field] = trim($values['values'][$i] ?? '');
                }
            }

            $email = mb_strtolower($record['email'] ?? '');
            $memberNumber = $this->nullable($record['member_number'] ?? '');

            // Unveränderte Zeilen aus der Vorlage dürfen kein echtes Mitglied treffen.
            if (str_ends_with($email, '@example.org') || str_starts_with(mb_strtolower($memberNumber ?? ''), 'beispiel')) {
                $errors[] = sprintf('Zeile %d: Beispielzeile aus der Vorlage – bitte löschen oder ersetzen.', $number);

                continue;
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
                'email' => $email,
                'member_number' => $memberNumber,
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

            $match = $this->match($members, $row['email'], $row['member_number']);

            if (is_string($match)) {
                $errors[] = sprintf('Zeile %d: %s', $number, $match);

                continue;
            }

            [$user, $matchedBy, $row['member_number'], $hints] = $match;

            // Zwei Zeilen über verschiedene Schlüssel für dasselbe Mitglied: die zweite wäre ein stilles Überschreiben.
            if ($user !== null && isset($seenUsers[$user->id])) {
                $errors[] = sprintf('Zeile %d: %s steht doppelt in der Datei (zuerst Zeile %d).', $number, $user->name, $seenUsers[$user->id]);

                continue;
            }

            // Nur über die Nummer gefunden: Ein Tippfehler in der Nummer träfe sonst ein fremdes
            // Mitglied. Lieber eine Zeile zu viel abweisen als die falsche Person ändern.
            if ($user !== null && $matchedBy === 'number' && $this->normalizedName($user->name) !== $this->normalizedName($row['name'])) {
                $errors[] = sprintf(
                    'Zeile %d: Mitgliedsnummer %s gehört „%s“, in der Datei steht „%s“ – Name weicht ab (verglichen ohne Groß-/Kleinschreibung, Satzzeichen, Titel und Reihenfolge, ä = ae, ß = ss). Nummer prüfen oder das Mitglied einzeln bearbeiten.',
                    $number,
                    $row['member_number'],
                    $user->name,
                    $row['name'],
                );

                continue;
            }

            $latest = $user !== null ? ($members['register'][$user->id] ?? null) : null;
            $registers = $scope !== null && ! $this->unchanged($latest, $scope, $row['valid_until']);
            $registerProblem = $scope !== null ? $this->registerProblem($latest, (string) $row['checked_on'], $registers) : null;

            if ($registerProblem !== null) {
                $errors[] = sprintf('Zeile %d: %s', $number, $registerProblem);

                continue;
            }

            // Unveränderte Aufsicht mit älterem Prüfdatum (z. B. Lehrgang statt Prüfung von Hand):
            // Es wird nichts eingetragen, die Zeile bleibt gültig, die Vorschau sagt es.
            $registerHint = $scope !== null && ! $registers && $latest !== null && $this->olderThan($latest, (string) $row['checked_on'])
                ? sprintf(
                    '„Aufsicht geprüft am“ %s liegt vor der jüngsten Zeile im Aufsichtsregister (geprüft am %s) – bleibt unverändert, es wird nichts eingetragen.',
                    CarbonImmutable::parse((string) $row['checked_on'])->format('d.m.Y'),
                    $latest->checked_on->format('d.m.Y'),
                )
                : null;

            // Vier-Augen-Prinzip wie beim Eintragen von Hand.
            if ($registers && $actor !== null && $user !== null && $user->is($actor)) {
                $errors[] = sprintf('Zeile %d: Die eigene Registrierung trägt ein anderes Vorstandsmitglied ein.', $number);

                continue;
            }

            if ($user !== null) {
                $seenUsers[$user->id] = $number;
            }

            if ($row['email'] !== '') {
                $seen[$row['email']] = $number;
            }

            if ($memberNumber !== null) {
                $seenNumbers[$memberNumber] = $number;
            }

            if ($row['member_number'] !== null) {
                $seenNumbers[$row['member_number']] ??= $number;
            }

            $confirmed = $user?->adult_confirmed_at !== null;

            $rows[] = [
                ...$row,
                'exists' => $user !== null,
                // Die Zuordnung aus der Vorschau gilt auch beim Übernehmen, auch wenn
                // frühere Zeilen der Datei inzwischen Nummern geändert haben.
                'user_id' => $user?->id,
                'matched_by' => $matchedBy,
                // Bisherige Werte für die Vorschau und zum Erkennen späterer Änderungen.
                'current_name' => $user?->name,
                'current_email' => $user?->email,
                'current_member_number' => $user?->member_number,
                'current_member_since' => $user?->member_since?->toDateString(),
                'register_id' => $latest?->id,
                'current_scope' => $latest?->scope->value,
                'current_until' => $latest?->valid_until?->toDateString(),
                'current_checked_on' => $latest?->checked_on->toDateString(),
                'registers' => $registers,
                'adult_confirmed' => $confirmed,
                'adult_sets' => ! $confirmed && ($row['adult'] === true || $registers),
                'hints' => $hints,
                'register_hint' => $registerHint,
            ];
        }

        return ['rows' => $rows, 'errors' => $errors, 'columns' => $known];
    }

    /**
     * Übernimmt die geprüften Zeilen in einer Transaktion. Was sich seit der Vorschau geändert
     * hat (Mitglied gelöscht oder geändert, Adresse oder Nummer inzwischen vergeben, offener
     * Antrag, neue Zeile im Aufsichtsregister), fällt raus.
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
                $user = $row['user_id'] !== null ? User::query()->find($row['user_id']) : null;
                $isNew = $row['user_id'] === null;

                if ((! $isNew && ($user === null || $this->changedSincePreview($user, $row)))
                    || $user?->isApplicant()
                    || $this->taken($user, $row['email'], $row['member_number'])) {
                    $skipped++;

                    continue;
                }

                $scope = $row['scope'] !== null ? SupervisionScope::from($row['scope']) : null;
                $latest = $user?->qualifications()->first();

                $registers = $scope !== null && ! $this->unchanged($latest, $scope, $row['valid_until']);

                // Register seit der Vorschau ergänzt (z. B. Aufsicht entzogen) oder die neue Zeile
                // wäre älter als die jüngste: nichts überschreiben. Gleiche Regeln wie in der Vorschau.
                if ($scope !== null && ($latest?->id !== $row['register_id'] || $this->registerProblem($latest, (string) $row['checked_on'], $registers) !== null)) {
                    $skipped++;

                    continue;
                }

                if ($registers && $user !== null && $user->is($actor)) {
                    $skipped++;

                    continue;
                }

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
                $setsAdult = ($row['adult'] === true || $registers) && $user->adult_confirmed_at === null;

                // Die Änderungen vor „volljährig geprüft“ festhalten: Das steht schon als eigener
                // Eintrag „user.adult_confirmed“ im Protokoll, nicht noch einmal in „user.updated“.
                $user->forceFill($attributes);
                $changes = $isNew ? [] : UserChanges::of($user);

                if ($setsAdult) {
                    $user->forceFill(['adult_confirmed_at' => now()]);
                }

                $user->save();

                if ($isNew) {
                    AuditEntry::record($actor, 'user.created', $user, sprintf('Mitglied angelegt (CSV-Import): %s (%s)', $user->name, $user->role->value));
                    $newUsers[] = $user;
                    $created++;
                } else {
                    if ($changes !== []) {
                        AuditEntry::record($actor, 'user.updated', $user, sprintf('Mitglied geändert (CSV-Import): %s (%s)', $user->name, implode('; ', $changes)));
                    }

                    $updated++;
                }

                if ($setsAdult) {
                    AuditEntry::record($actor, 'user.adult_confirmed', $user, sprintf('Volljährig geprüft: %s (beim CSV-Import)', $user->name));
                    $adult++;
                }

                if ($registers) {
                    Qualification::register($user, $scope, $row['valid_until'], (string) $row['checked_on'], $actor, $row['note']);
                    $registered++;
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
     * Was der Vorstand vor dem Übernehmen bestätigen muss, wie beim Eintragen von Hand: die
     * Volljährigkeit, wenn eine Zeile sie neu setzt (E12, nicht umkehrbar), Volljährigkeit und
     * Sachkunde, wenn eine Zeile eine Aufsicht einträgt (§ 10 Abs. 3 AWaffV), einschließlich
     * Waffensachkunde, sobald eine Zeile eine erlaubnispflichtige Aufsicht neu einträgt.
     * Unveränderte Zeilen tragen nichts ein und verlangen nichts. Null: nichts zu bestätigen.
     *
     * @param  list<ImportRow>  $rows
     * @return array{text: string, message: string}|null
     */
    public function confirmation(array $rows): ?array
    {
        $adult = array_filter($rows, fn (array $row) => $row['adult'] === true && ! $row['adult_confirmed']) !== [];
        $supervisors = array_filter($rows, fn (array $row) => $row['registers']) !== [];
        $licensed = array_filter($rows, fn (array $row) => $row['registers'] && $row['scope'] === SupervisionScope::Licensed->value) !== [];

        if (! $adult && ! $supervisors) {
            return null;
        }

        $texts = [];
        $messages = [];

        if ($adult) {
            $texts[] = 'Für alle als volljährig geprüft markierten Personen habe ich die Volljährigkeit geprüft.';
            $messages[] = 'die Volljährigkeit für alle als volljährig geprüft markierten Personen';
        }

        if ($supervisors) {
            $expertise = $licensed ? self::WEAPONS_EXPERTISE : '';
            $texts[] = 'Für alle als Aufsicht markierten Personen habe ich Volljährigkeit und Sachkunde geprüft'.$expertise.'.';
            $messages[] = 'Volljährigkeit und Sachkunde für alle als Aufsicht markierten Personen'.$expertise;
        }

        return [
            'text' => implode(' ', $texts),
            'message' => 'Bitte bestätigen Sie '.implode(' sowie ', $messages).'.',
        ];
    }

    /**
     * Alle Mitglieder (auch Anträge) und die jüngste Registerzeile je Person, einmal geladen
     * statt mehrerer Abfragen je Zeile. „trimmed“: Nummern ohne führende Nullen, je Nummer alle
     * Mitglieder, damit eine mehrdeutige Nummer auffällt.
     *
     * @return Members
     */
    private function members(): array
    {
        $byEmail = [];
        $byNumber = [];
        $byTrimmed = [];

        foreach (User::query()->orderBy('id')->get() as $user) {
            if ($user->email !== null) {
                $byEmail[mb_strtolower($user->email)] ??= $user;
            }

            if ($user->member_number !== null) {
                $byNumber[$user->member_number] ??= $user;
                $byTrimmed[$this->withoutLeadingZeros($user->member_number)][] = $user;
            }
        }

        $register = [];

        foreach (Qualification::query()->whereIn('id', Qualification::query()->selectRaw('max(id)')->groupBy('user_id'))->get() as $row) {
            $register[$row->user_id] = $row;
        }

        return ['email' => $byEmail, 'number' => $byNumber, 'trimmed' => $byTrimmed, 'register' => $register];
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
     * Die jüngste Registerzeile sperrt den Import: Eine entzogene Aufsicht trägt nur der Vorstand
     * einzeln wieder ein (immer), und eine ältere Prüfung darf nicht zur jüngsten Zeile werden
     * (nur wenn die Zeile etwas einträgt; eine unveränderte Aufsicht trägt nichts ein).
     */
    private function registerProblem(?Qualification $latest, string $checkedOn, bool $registers): ?string
    {
        if ($latest === null) {
            return null;
        }

        if ($latest->scope === SupervisionScope::Revoked) {
            return sprintf('Aufsicht am %s entzogen – erneut eintragen nur einzeln beim Mitglied.', $latest->checked_on->format('d.m.Y'));
        }

        if ($registers && $this->olderThan($latest, $checkedOn)) {
            return sprintf(
                '„Aufsicht geprüft am“ %s liegt vor der jüngsten Zeile im Aufsichtsregister (geprüft am %s).',
                CarbonImmutable::parse($checkedOn)->format('d.m.Y'),
                $latest->checked_on->format('d.m.Y'),
            );
        }

        return null;
    }

    private function olderThan(Qualification $latest, string $checkedOn): bool
    {
        return $checkedOn < $latest->checked_on->toDateString();
    }

    /**
     * Gleicher Umfang und gleiches Ende wie die jüngste Registerzeile: kein neuer Eintrag,
     * damit ein erneuter Import das Register nicht mit gleichen Zeilen füllt.
     */
    private function unchanged(?Qualification $latest, SupervisionScope $scope, ?string $validUntil): bool
    {
        return $latest !== null
            && $latest->scope === $scope
            && $latest->valid_until?->toDateString() === $validUntil;
    }

    /**
     * Name, E-Mail oder Nummer des Mitglieds haben sich seit der Vorschau geändert: Die
     * Zuordnung aus der Vorschau gilt nicht mehr sicher.
     *
     * @param  ImportRow  $row
     */
    private function changedSincePreview(User $user, array $row): bool
    {
        return $user->name !== $row['current_name']
            || $user->email !== $row['current_email']
            || $user->member_number !== $row['current_member_number'];
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
     * Ein String ist ein Konflikt, der die Zeile ausschließt. Sonst: Mitglied (oder null für
     * neu), gefunden über, Mitgliedsnummer für den Import, Hinweise für die Vorschau.
     *
     * @param  Members  $members
     * @return string|array{0: User|null, 1: 'email'|'number'|null, 2: string|null, 3: list<string>}
     */
    private function match(array $members, string $email, ?string $memberNumber): string|array
    {
        $byEmail = $email !== '' ? ($members['email'][$email] ?? null) : null;
        $hints = [];

        // Excel macht aus 0042 gern 42: Bei einem über die E-Mail gefundenen Mitglied bleibt die alte Nummer.
        if ($byEmail !== null && $byEmail->member_number !== null && $memberNumber !== null
            && $memberNumber !== $byEmail->member_number
            && ltrim($memberNumber, '0') === ltrim($byEmail->member_number, '0')) {
            $hints[] = sprintf('Nr. %s in der Datei, bleibt %s (führende Nullen).', $memberNumber, $byEmail->member_number);
            $memberNumber = $byEmail->member_number;
        }

        $byNumber = $memberNumber !== null ? ($members['number'][$memberNumber] ?? null) : null;

        // Passt die Nummer nur ohne führende Nullen (0042 gespeichert, 42 in der Datei, oder
        // umgekehrt), wird nicht zugeordnet: Es kann dasselbe Mitglied sein, nach einem
        // Excel-Durchlauf, oder eine andere Person mit fast gleicher Nummer, etwa Vater und Sohn.
        // Ein Fehler ist besser als ein Duplikat oder Daten beim falschen Mitglied.
        if ($byNumber === null && $memberNumber !== null) {
            $candidates = array_values(array_filter(
                $members['trimmed'][$this->withoutLeadingZeros($memberNumber)] ?? [],
                fn (User $candidate) => $byEmail === null || ! $candidate->is($byEmail),
            ));

            if ($candidates !== []) {
                return sprintf(
                    'Nummer %s passt nur ohne führende Nullen zu %s. Ist es dieselbe Person, die Nummer genau wie gespeichert angeben; sonst das Mitglied einzeln anlegen.',
                    $memberNumber,
                    implode(', ', array_map(fn (User $candidate) => sprintf('%s („%s“)', $candidate->member_number, $candidate->name), $candidates)),
                );
            }
        }

        if ($byEmail !== null && $byNumber !== null && ! $byEmail->is($byNumber)) {
            return sprintf('Mitgliedsnummer %s gehört bereits einem anderen Mitglied.', $memberNumber);
        }

        if ($byEmail === null && $byNumber !== null && $email !== '' && $byNumber->email !== null) {
            return sprintf('Mitgliedsnummer %s gehört bereits einem anderen Mitglied (andere E-Mail-Adresse).', $memberNumber);
        }

        $user = $byEmail ?? $byNumber;

        // Anträge werden nie über den Import Mitglied (E7).
        if ($user?->isApplicant()) {
            return 'Für diese Person liegt ein offener Mitgliedsantrag vor. Antrag zuerst aufnehmen oder ablehnen.';
        }

        return [$user, match (true) {
            $byEmail !== null => 'email',
            $byNumber !== null => 'number',
            default => null,
        }, $memberNumber, $hints];
    }

    /**
     * Name zum Vergleichen bei einem nur über die Nummer gefundenen Mitglied. Gleich gelten
     * Schreibweisen derselben Person: Unicode-Form (ü aus Excel oder macOS), Groß- und
     * Kleinschreibung (auch ß und SS), ä/ae, ö/oe, ü/ue, Bindestriche, Kommas, Punkte und
     * Apostrophe, akademische Titel, Leerzeichen und die Reihenfolge („Meier, Hans“).
     */
    private function normalizedName(string $name): string
    {
        $name = Normalizer::normalize($name, Normalizer::FORM_C) ?: $name;
        $name = strtr(mb_convert_case($name, MB_CASE_FOLD), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue']);
        $name = (string) preg_replace('/[\p{Pd},.\'’ʼ‘]+/u', ' ', $name);
        $tokens = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        // „med“ und „ing“ sind nur nach „Dr.“ bzw. „Dipl.“ Titel, sonst Namensteile.
        $withoutTitles = [];

        foreach ($tokens as $i => $token) {
            $previous = $tokens[$i - 1] ?? null;
            $title = in_array($token, self::TITLES, true)
                || ($token === 'med' && $previous === 'dr')
                || ($token === 'ing' && $previous === 'dipl');

            if (! $title) {
                $withoutTitles[] = $token;
            }
        }

        // Nur Titel: dann mit Titeln vergleichen, sonst wären „Dr.“ und „Prof.“ derselbe Name.
        $tokens = $withoutTitles !== [] ? $withoutTitles : $tokens;
        sort($tokens);

        return implode(' ', $tokens);
    }

    /**
     * Mitgliedsnummer ohne führende Nullen zum Vergleichen; „000“ bleibt „0“.
     */
    private function withoutLeadingZeros(string $memberNumber): string
    {
        $trimmed = ltrim($memberNumber, '0');

        return $trimmed === '' ? '0' : $trimmed;
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
