<?php

namespace Tests\Feature\Admin;

use App\Domain\MemberImport;
use App\Enums\Role;
use App\Enums\SupervisionScope;
use App\Models\AuditEntry;
use App\Models\Qualification;
use App\Models\User;
use App\Notifications\UserInvited;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Support\SessionKey;
use Tests\TestCase;

class MemberImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = "Vorname;Nachname;E-Mail;Mitgliedsnummer;Eintritt;Volljährig geprüft;Aufsicht;Aufsicht gültig bis;Aufsicht geprüft am;Aufsicht Bemerkung\r\n";

    private const CSV = "\xEF\xBB\xBFVorname;Nachname;E-Mail;Mitgliedsnummer;Eintritt\r\n"
        ."Erika;Musterfrau;Erika@Verein.test;1001;01.03.2015\r\n"
        ."Max;Mustermann;max@verein.test;1002;\r\n"
        .";;keine-mail;;\r\n"
        ."Doppelt;Erika;erika@verein.test;;\r\n";

    private function today(): string
    {
        return now()->format('d.m.Y');
    }

    private function upload(User $admin, string $csv): TestResponse
    {
        return $this->actingAs($admin)->post(route('admin.users.import-preview'), [
            'file' => UploadedFile::fake()->createWithContent('mitglieder.csv', $csv),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function confirm(User $admin, array $data = []): TestResponse
    {
        return $this->actingAs($admin)->post(route('admin.users.import-store'), $data);
    }

    private function toast(TestResponse $response): string
    {
        return $response->getSession()->get(SessionKey::FLASH_DATA)['toast']['message'];
    }

    public function test_parse_maps_german_columns_and_reports_errors()
    {
        $result = app(MemberImport::class)->parse(self::CSV);

        $this->assertCount(2, $result['rows']);
        $this->assertSame('Erika Musterfrau', $result['rows'][0]['name']);
        $this->assertSame('erika@verein.test', $result['rows'][0]['email']);
        $this->assertSame('2015-03-01', $result['rows'][0]['member_since']);
        $this->assertNull($result['rows'][0]['scope']);
        $this->assertCount(2, $result['errors']);
        $this->assertStringContainsString('Zeile 5', $result['errors'][1]);
    }

    public function test_comma_separated_files_and_windows_encoding_work()
    {
        $csv = mb_convert_encoding("Name,E-Mail,VOLLJÄHRIG GEPRÜFT\r\nJürgen Größer,juergen@verein.test,Ja\r\n", 'Windows-1252', 'UTF-8');

        $result = app(MemberImport::class)->parse($csv);

        $this->assertSame([], $result['errors']);
        $this->assertSame('Jürgen Größer', $result['rows'][0]['name']);
        $this->assertTrue($result['rows'][0]['adult']);
    }

    public function test_missing_required_columns_are_reported()
    {
        $result = app(MemberImport::class)->parse("Vorname;Telefon\nErika;123\n");

        $this->assertSame([], $result['rows']);
        $this->assertStringContainsString('E-Mail', $result['errors'][0]);
    }

    public function test_admin_previews_then_imports_creating_and_updating()
    {
        $admin = User::factory()->admin()->create();
        $existing = User::factory()->create(['email' => 'max@verein.test', 'name' => 'Alter Name', 'member_number' => null]);
        $password = $existing->password;

        $this->upload($admin, self::CSV)->assertRedirect(route('admin.users.import'));

        $this->assertFalse(User::where('email', 'erika@verein.test')->exists());

        $this->actingAs($admin)->get(route('admin.users.import'))
            ->assertInertia(fn ($page) => $page
                ->component('admin/users/Import')
                ->has('preview.rows', 2)
                ->where('preview.rows.0.exists', false)
                ->where('preview.rows.1.exists', true)
                ->has('preview.errors', 2));

        $response = $this->confirm($admin)->assertRedirect(route('admin.users.import'));
        $this->assertSame('1 neu angelegt, 1 aktualisiert.', $this->toast($response));
        $response->assertSessionHas('member_import.result', fn (array $result) => $result['created'] === 1 && $result['updated'] === 1 && $result['invitations'] === [] && $result['uninvited'] === 1);
        $this->assertNull(session('member_import.preview'));

        $erika = User::where('email', 'erika@verein.test')->sole();
        $this->assertSame('1001', $erika->member_number);
        $this->assertSame(Role::Member, $erika->role);
        $this->assertNotNull($erika->password);
        $this->assertNull($erika->adult_confirmed_at);
        $this->assertSame(0, $erika->qualifications()->count());

        $existing->refresh();
        $this->assertSame('Max Mustermann', $existing->name);
        $this->assertSame('1002', $existing->member_number);
        $this->assertSame($password, $existing->password);

        $audit = AuditEntry::where('action', 'user.imported')->sole();
        $this->assertSame($admin->id, $audit->actor_id);
        $this->assertSame('CSV-Import: 1 neu, 1 aktualisiert, 0 ausgelassen, 0 Aufsicht eingetragen, 0 volljährig geprüft', $audit->summary);
    }

    public function test_import_never_changes_roles_and_never_deletes()
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create(['email' => 'vorstand@verein.test']);
        $untouched = User::factory()->create();

        $this->upload($admin, "Name;E-Mail\nVorstand Neu;vorstand@verein.test\n");
        $this->confirm($admin)->assertSessionHasNoErrors();

        $this->assertSame(Role::Admin, $other->fresh()->role);
        $this->assertSame('Vorstand Neu', $other->fresh()->name);
        $this->assertNotNull($untouched->fresh());
        $this->assertSame(3, User::count());
    }

    public function test_invitations_without_real_mailer_return_links_instead_of_claiming_sent()
    {
        Notification::fake();
        config(['mail.default' => 'array']);
        $admin = User::factory()->admin()->create();
        $existing = User::factory()->create(['email' => 'max@verein.test']);

        $this->upload($admin, self::CSV);
        $this->actingAs($admin)->get(route('admin.users.import'))->assertInertia(fn ($page) => $page->where('mailDelivers', false));

        $response = $this->confirm($admin, ['send_invitations' => true]);

        $erika = User::where('email', 'erika@verein.test')->sole();
        $message = $this->toast($response);
        $this->assertStringNotContainsString('verschickt', $message);
        $this->assertStringContainsString('Der Mailversand ist nicht eingerichtet', $message);
        $response->assertSessionHas('member_import.result', fn (array $result) => $result['mailed'] === false
            && count($result['invitations']) === 1
            && $result['invitations'][0]['name'] === 'Erika Musterfrau'
            && str_contains($result['invitations'][0]['url'], '/einladung/'));
        Notification::assertSentTo($erika, UserInvited::class);
        Notification::assertNotSentTo($existing, UserInvited::class);

        // Die Ergebnisseite zeigt die Links genau einmal.
        $this->actingAs($admin)->get(route('admin.users.import'))
            ->assertInertia(fn ($page) => $page->has('result.invitations', 1)->where('result.mailed', false)->where('preview', null));
        $this->actingAs($admin)->get(route('admin.users.import'))
            ->assertInertia(fn ($page) => $page->where('result', null));
    }

    public function test_invitations_with_real_mailer_say_sent()
    {
        Notification::fake();
        config(['mail.default' => 'smtp']);
        $admin = User::factory()->admin()->create();

        $this->upload($admin, "Name;E-Mail\nErika Musterfrau;erika@verein.test\nKlaus Beispiel;klaus@verein.test\n");
        $response = $this->confirm($admin, ['send_invitations' => true]);

        $this->assertSame('2 neu angelegt, 0 aktualisiert. 2 Einladungen per E-Mail verschickt.', $this->toast($response));
        $response->assertSessionHas('member_import.result', fn (array $result) => $result['mailed'] === true && count($result['invitations']) === 2);
        Notification::assertSentTo(User::where('email', 'klaus@verein.test')->sole(), UserInvited::class);
    }

    public function test_without_invitation_option_nothing_is_sent()
    {
        Notification::fake();
        config(['mail.default' => 'smtp']);
        $admin = User::factory()->admin()->create();

        $this->upload($admin, "Name;E-Mail\nErika Musterfrau;erika@verein.test\n");
        $response = $this->confirm($admin, ['send_invitations' => false]);

        $this->assertStringNotContainsString('verschickt', $this->toast($response));
        $response->assertSessionHas('member_import.result', fn (array $result) => $result['invitations'] === [] && $result['uninvited'] === 1);
        Notification::assertNothingSent();
    }

    public function test_members_cannot_import()
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get(route('admin.users.import'))->assertForbidden();
        $this->actingAs($member)->get(route('admin.users.import-template'))->assertForbidden();
        $this->actingAs($member)->post(route('admin.users.import-preview'), [
            'file' => UploadedFile::fake()->createWithContent('mitglieder.csv', self::CSV),
        ])->assertForbidden();
        $this->actingAs($member)->post(route('admin.users.import-store'))->assertForbidden();
        $this->actingAs($member)->delete(route('admin.users.import-cancel'))->assertForbidden();

        $this->assertSame(1, User::count());
    }

    public function test_upload_must_be_a_small_csv_file()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.users.import-preview'), [])->assertSessionHasErrors('file');
        $this->actingAs($admin)->post(route('admin.users.import-preview'), [
            'file' => UploadedFile::fake()->create('bild.png', 10, 'image/png'),
        ])->assertSessionHasErrors('file');
        $this->actingAs($admin)->post(route('admin.users.import-preview'), [
            'file' => UploadedFile::fake()->create('gross.csv', 3000, 'text/csv'),
        ])->assertSessionHasErrors('file');

        $this->assertNull(session('member_import.preview'));
    }

    public function test_template_download()
    {
        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.users.import-template'))
            ->assertOk()
            ->assertDownload('mitglieder-vorlage.csv');

        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $lines = explode("\r\n", trim(substr($content, 3)));
        $this->assertCount(3, $lines);
        $this->assertSame(
            ['Vorname', 'Nachname', 'E-Mail', 'Mitgliedsnummer', 'Eintritt', 'Volljährig geprüft', 'Aufsicht', 'Aufsicht gültig bis', 'Aufsicht geprüft am', 'Aufsicht Bemerkung'],
            explode(';', $lines[0]),
        );
        $this->assertSame('erlaubnisfrei', explode(';', $lines[1])[6]);
        $this->assertSame('', explode(';', $lines[2])[6]);
        $this->assertStringContainsString('@example.org', $content);

        // Die Beispielzeilen treffen nie ein echtes Mitglied, auch nicht über Nummer 1001 oder 1002.
        $this->assertSame('Beispiel-1', explode(';', $lines[1])[3]);
        $this->assertSame('Beispiel-2', explode(';', $lines[2])[3]);
        $result = app(MemberImport::class)->parse($content);
        $this->assertSame([], $result['rows']);
        $this->assertSame([
            'Zeile 2: Beispielzeile aus der Vorlage – bitte löschen oder ersetzen.',
            'Zeile 3: Beispielzeile aus der Vorlage – bitte löschen oder ersetzen.',
        ], $result['errors']);
    }

    public function test_members_without_email_are_imported_by_member_number()
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        // Gleicher Name bis auf Schreibweise und Leerzeichen
        $known = User::factory()->create(['email' => null, 'password' => null, 'member_number' => '2002', 'name' => 'bekannt  ohne MAIL']);
        $csv = "Name;E-Mail;Mitgliedsnummer\n"
            ."Ohne Mail;;2001\n"
            ."Bekannt Ohne Mail;;2002\n"
            ."Weder noch;;\n";

        $result = app(MemberImport::class)->parse($csv);
        $this->assertSame([false, true], array_column($result['rows'], 'exists'));
        $this->assertStringContainsString('E-Mail-Adresse oder Mitgliedsnummer', $result['errors'][0]);

        $this->upload($admin, $csv);
        $this->confirm($admin, ['send_invitations' => true])->assertRedirect();

        $new = User::where('member_number', '2001')->firstOrFail();
        $this->assertNull($new->email);
        // Ohne E-Mail kein Konto: kein Passwort, keine Einladung.
        $this->assertNull($new->password);
        $this->assertSame('Bekannt Ohne Mail', $known->fresh()->name);
        $this->assertSame(1, User::where('member_number', '2002')->count());
        Notification::assertNothingSent();
    }

    public function test_member_number_import_adds_email_to_a_member_without_account()
    {
        $known = User::factory()->create(['email' => null, 'password' => null, 'member_number' => '3001', 'name' => 'Neu Mit Mail']);
        User::factory()->create(['email' => 'other@verein.test', 'member_number' => '3002']);

        $result = app(MemberImport::class)->parse("Name;E-Mail;Mitgliedsnummer\nNeu Mit Mail;neu@verein.test;3001\nFremd;fremd@verein.test;3002\n");

        $this->assertCount(1, $result['rows']);
        $this->assertTrue($result['rows'][0]['exists']);
        $this->assertStringContainsString('andere E-Mail-Adresse', $result['errors'][0]);

        app(MemberImport::class)->import(User::factory()->admin()->create(), $result['rows']);
        $this->assertSame('neu@verein.test', $known->fresh()->email);
    }

    public function test_file_without_email_column_but_with_member_numbers_is_accepted()
    {
        $result = app(MemberImport::class)->parse("Vorname;Nachname;Nr.\nKarl;Ohnemail;77\n");

        $this->assertSame([], $result['errors']);
        $this->assertSame('Karl Ohnemail', $result['rows'][0]['name']);
    }

    public function test_import_keeps_the_match_from_the_preview()
    {
        $member = User::factory()->create(['email' => 'a@verein.test', 'member_number' => '5']);

        // Zeile 1 ändert die Nummer, Zeile 2 meint dasselbe Mitglied über die alte Nummer.
        $result = app(MemberImport::class)->parse("Name;E-Mail;Mitgliedsnummer\nAnna;a@verein.test;6\nAnna Neu;;5\n");
        $this->assertCount(1, $result['rows']);
        $this->assertStringContainsString('steht doppelt in der Datei (zuerst Zeile 2)', $result['errors'][0] ?? '');

        $imported = app(MemberImport::class)->import(User::factory()->admin()->create(), $result['rows']);

        $this->assertSame(0, $imported['created']);
        $this->assertSame(1, User::where('name', 'like', 'Anna%')->count());
        $this->assertSame($member->id, User::where('member_number', '6')->value('id'));
    }

    public function test_rows_that_changed_since_the_preview_are_skipped()
    {
        $admin = User::factory()->admin()->create();

        $this->upload($admin, "Name;E-Mail;Mitgliedsnummer\nNeu Eins;eins@verein.test;7001\nNeu Zwei;zwei@verein.test;7002\n");

        // Inzwischen hat jemand die Nummer 7002 vergeben.
        User::factory()->create(['member_number' => '7002']);

        $response = $this->confirm($admin);

        $this->assertStringContainsString('Eine Zeile ausgelassen', $this->toast($response));
        $this->assertTrue(User::where('email', 'eins@verein.test')->exists());
        $this->assertFalse(User::where('email', 'zwei@verein.test')->exists());
    }

    public function test_email_matching_ignores_case()
    {
        User::factory()->create(['email' => 'Erika@Verein.test', 'member_number' => '1']);

        $result = app(MemberImport::class)->parse("Name;E-Mail;Mitgliedsnummer\nErika;erika@verein.test;1\n");

        $this->assertSame([], $result['errors']);
        $this->assertTrue($result['rows'][0]['exists']);
    }

    public function test_entry_date_must_not_be_in_the_future()
    {
        $result = app(MemberImport::class)->parse("Name;Nr.;Eintritt\nKarl Zukunft;9;".now()->addDay()->format('d.m.Y')."\n");

        $this->assertSame([], $result['rows']);
        $this->assertStringContainsString('„Eintritt“ darf nicht in der Zukunft liegen.', $result['errors'][0]);
    }

    public function test_applicant_rows_are_errors()
    {
        $applicant = User::factory()->applicant()->create(['email' => 'antrag@verein.test']);
        $admin = User::factory()->admin()->create();

        $result = app(MemberImport::class)->parse("Name;E-Mail;Mitgliedsnummer\nAntrag Person;antrag@verein.test;4711\n", $admin);

        $this->assertSame([], $result['rows']);
        $this->assertStringContainsString('Zeile 2', $result['errors'][0]);
        $this->assertStringContainsString('Antrag zuerst aufnehmen oder ablehnen', $result['errors'][0]);

        $this->upload($admin, "Name;E-Mail;Mitgliedsnummer\nAntrag Person;antrag@verein.test;4711\n");
        $this->confirm($admin);

        $applicant->refresh();
        $this->assertSame(Role::Applicant, $applicant->role);
        $this->assertNull($applicant->member_number);
    }

    public function test_adult_flag_is_only_ever_set()
    {
        $admin = User::factory()->admin()->create();
        $confirmed = User::factory()->create(['email' => 'geprueft@verein.test', 'adult_confirmed_at' => now()->subYear()]);
        $open = User::factory()->create(['email' => 'offen@verein.test']);

        $this->upload($admin, "Name;E-Mail;Volljährig geprüft\n"
            ."Geprüft;geprueft@verein.test;nein\n"
            ."Offen;offen@verein.test;\n"
            ."Neu Ja;neu-ja@verein.test;ja\n"
            ."Neu Leer;neu-leer@verein.test;\n"
            ."Neu Vielleicht;neu-vielleicht@verein.test;vielleicht\n");

        $this->actingAs($admin)->get(route('admin.users.import'))
            ->assertInertia(fn ($page) => $page->has('preview.rows', 4)->where('preview.rows.2.adult_sets', true)
                ->where('preview.errors.0', fn (string $error) => str_contains($error, 'Zeile 6') && str_contains($error, 'ja, nein oder leer')));

        $this->confirm($admin, ['confirmed' => true])->assertSessionHasNoErrors();

        $this->assertTrue($confirmed->fresh()->adult_confirmed_at->isSameDay(now()->subYear()));
        $this->assertNull($open->fresh()->adult_confirmed_at);
        $this->assertNotNull(User::where('email', 'neu-ja@verein.test')->sole()->adult_confirmed_at);
        $this->assertNull(User::where('email', 'neu-leer@verein.test')->sole()->adult_confirmed_at);
        $this->assertStringEndsWith('1 volljährig geprüft', AuditEntry::where('action', 'user.imported')->sole()->summary);
    }

    public function test_supervision_is_registered_with_confirmation()
    {
        $admin = User::factory()->admin()->create();
        $existing = User::factory()->create(['email' => 'max@verein.test', 'member_number' => '1002']);

        $this->upload($admin, self::HEADER
            ."Erika;Musterfrau;erika@verein.test;1001;01.03.2015;;Erlaubnispflichtig;31.12.2030;{$this->today()};Lehrgang Standaufsicht\r\n"
            ."Max;Mustermann;max@verein.test;1002;;;erlaubnisfrei;30.06.2029;{$this->today()};\r\n");

        $this->actingAs($admin)->get(route('admin.users.import'))
            ->assertInertia(fn ($page) => $page
                ->where('preview.rows.0.scope', 'licensed')
                ->where('preview.rows.0.registers', true)
                ->where('preview.rows.1.scope', 'exempt')
                ->where('preview.errors', []));

        $response = $this->confirm($admin, ['confirmed' => true])->assertSessionHasNoErrors();
        $this->assertSame('1 neu angelegt, 1 aktualisiert. 2 im Aufsichtsregister eingetragen.', $this->toast($response));

        $erika = User::where('email', 'erika@verein.test')->sole();
        $this->assertTrue($erika->canSupervise(licensed: true));
        $this->assertSame('2030-12-31', $erika->supervisor_until->toDateString());
        $row = $erika->qualifications()->sole();
        $this->assertSame(SupervisionScope::Licensed, $row->scope);
        $this->assertSame(now()->toDateString(), $row->checked_on->toDateString());
        $this->assertSame($admin->id, $row->checked_by_id);
        $this->assertSame('Lehrgang Standaufsicht', $row->note);

        $existing->refresh();
        $this->assertTrue($existing->canSupervise());
        $this->assertFalse($existing->canSupervise(licensed: true));

        // Eine Registrierung setzt die geprüfte Volljährigkeit voraus.
        $this->assertNotNull($erika->adult_confirmed_at);
        $this->assertNotNull($existing->adult_confirmed_at);

        // Je Registrierung ein eigener Protokolleintrag, dazu die Zusammenfassung.
        $this->assertSame(2, AuditEntry::where('action', 'qualification.registered')->where('actor_id', $admin->id)->count());
        $this->assertSame(
            'CSV-Import: 1 neu, 1 aktualisiert, 0 ausgelassen, 2 Aufsicht eingetragen, 2 volljährig geprüft',
            AuditEntry::where('action', 'user.imported')->sole()->summary,
        );
    }

    public function test_supervision_needs_the_confirmation_checkbox()
    {
        $admin = User::factory()->admin()->create();
        $this->upload($admin, self::HEADER
            ."Erika;Musterfrau;erika@verein.test;1001;;;erlaubnisfrei;31.12.2030;{$this->today()};\r\n"
            ."Karl;Ohnemail;;1002;;;;;;\r\n");

        $this->confirm($admin)->assertSessionHasErrors(['confirmed' => 'Bitte bestätigen Sie Volljährigkeit und Sachkunde für alle als Aufsicht markierten Personen.']);
        $this->confirm($admin, ['confirmed' => false])->assertSessionHasErrors('confirmed');

        // Nichts übernommen, die Vorschau bleibt für den zweiten Versuch.
        $this->assertSame(1, User::count());
        $this->assertSame(0, Qualification::count());
        $this->assertFalse(AuditEntry::where('action', 'user.imported')->exists());
        $this->assertCount(2, session('member_import.preview')['rows']);

        $this->confirm($admin, ['confirmed' => true])->assertSessionHasNoErrors();
        $this->assertSame(3, User::count());
        $this->assertSame(1, Qualification::count());
    }

    public function test_without_supervision_rows_no_confirmation_is_needed()
    {
        $admin = User::factory()->admin()->create();
        $this->upload($admin, self::HEADER."Karl;Ohnemail;;1002;;;;;;\r\n");

        $this->confirm($admin)->assertSessionHasNoErrors();
        $this->assertTrue(User::where('member_number', '1002')->exists());
    }

    public function test_supervision_requires_both_dates()
    {
        $result = app(MemberImport::class)->parse(self::HEADER
            ."Ohne;Bis;a@verein.test;;;;erlaubnisfrei;;{$this->today()};\r\n"
            ."Ohne;Geprüft;b@verein.test;;;;erlaubnispflichtig;31.12.2030;;\r\n"
            ."Ohne;Beides;c@verein.test;;;;erlaubnisfrei;;;\r\n");

        $this->assertSame([], $result['rows']);
        $this->assertSame('Zeile 2: Bitte „Aufsicht gültig bis“ angeben.', $result['errors'][0]);
        $this->assertSame('Zeile 3: Bitte „Aufsicht geprüft am“ angeben.', $result['errors'][1]);
        $this->assertSame('Zeile 4: Bitte „Aufsicht gültig bis“ angeben. Bitte „Aufsicht geprüft am“ angeben.', $result['errors'][2]);
    }

    public function test_supervision_dates_must_be_in_order()
    {
        $tomorrow = now()->addDay()->format('d.m.Y');
        $yesterday = now()->subDay()->format('d.m.Y');

        $result = app(MemberImport::class)->parse(self::HEADER
            ."Zu;Früh;a@verein.test;;;;erlaubnisfrei;{$yesterday};{$this->today()};\r\n"
            ."In;Zukunft;b@verein.test;;;;erlaubnisfrei;31.12.2030;{$tomorrow};\r\n"
            ."Gleicher;Tag;c@verein.test;;;;erlaubnisfrei;{$this->today()};{$this->today()};\r\n"
            ."Kaputt;Datum;d@verein.test;;;;erlaubnisfrei;31.02.2030;{$this->today()};\r\n");

        $this->assertSame(['c@verein.test'], array_column($result['rows'], 'email'));
        $this->assertStringContainsString('„Aufsicht gültig bis“ darf nicht vor „Aufsicht geprüft am“ liegen.', $result['errors'][0]);
        $this->assertStringContainsString('„Aufsicht geprüft am“ darf nicht in der Zukunft liegen.', $result['errors'][1]);
        $this->assertStringContainsString('Datum nicht lesbar (Aufsicht gültig bis „31.02.2030“)', $result['errors'][2]);
    }

    public function test_supervision_scope_values()
    {
        $result = app(MemberImport::class)->parse(self::HEADER
            ."Groß;Schreibung;a@verein.test;;;;ERLAUBNISFREI;31.12.2030;{$this->today()};\r\n"
            ."Entzug;Versuch;b@verein.test;;;;entzogen;31.12.2030;{$this->today()};\r\n"
            ."Nur;Ja;c@verein.test;;;;ja;31.12.2030;{$this->today()};\r\n"
            ."Ohne;Umfang;d@verein.test;;;;;31.12.2030;{$this->today()};\r\n"
            ."Nicht;Volljährig;e@verein.test;;;nein;erlaubnisfrei;31.12.2030;{$this->today()};\r\n");

        $this->assertSame(['exempt'], array_column($result['rows'], 'scope'));
        $this->assertStringContainsString('„Aufsicht“ kennt nur erlaubnispflichtig, erlaubnisfrei oder leer, nicht „entzogen“.', $result['errors'][0]);
        $this->assertStringContainsString('nicht „ja“', $result['errors'][1]);
        $this->assertStringContainsString('gelten nur zusammen mit „Aufsicht“', $result['errors'][2]);
        $this->assertStringContainsString('Eine Aufsicht muss volljährig sein', $result['errors'][3]);
    }

    public function test_old_aufsicht_bis_column_needs_a_scope()
    {
        $result = app(MemberImport::class)->parse("Vorname;Nachname;E-Mail;Aufsicht bis\nErika;Musterfrau;erika@verein.test;31.12.2027\n");

        $this->assertSame([], $result['rows']);
        $this->assertStringContainsString('gelten nur zusammen mit „Aufsicht“', $result['errors'][0]);
    }

    public function test_own_registration_is_an_error_four_eyes()
    {
        $admin = User::factory()->admin()->create(['email' => 'vorstand@verein.test', 'name' => 'Vorstand Alt']);

        $result = app(MemberImport::class)->parse(self::HEADER
            ."Vorstand;Selbst;vorstand@verein.test;;;;erlaubnispflichtig;31.12.2030;{$this->today()};\r\n", $admin);

        $this->assertSame([], $result['rows']);
        $this->assertSame('Zeile 2: Die eigene Registrierung trägt ein anderes Vorstandsmitglied ein.', $result['errors'][0]);

        // Ohne Aufsicht darf die eigene Zeile die Stammdaten ändern.
        $this->upload($admin, "Name;E-Mail\nVorstand Neu;vorstand@verein.test\n");
        $this->confirm($admin)->assertSessionHasNoErrors();
        $this->assertSame('Vorstand Neu', $admin->fresh()->name);
        $this->assertSame(0, Qualification::count());
    }

    public function test_own_registration_is_also_refused_when_the_register_changed_after_the_preview()
    {
        $admin = User::factory()->admin()->create(['email' => 'vorstand@verein.test']);
        $other = User::factory()->admin()->create();
        Qualification::register($admin, SupervisionScope::Exempt, '2030-12-31', now()->toDateString(), $other);

        // In der Vorschau unverändert, also kein Eintrag und kein Fehler.
        $result = app(MemberImport::class)->parse(self::HEADER
            ."Vorstand;Selbst;vorstand@verein.test;;;;erlaubnisfrei;31.12.2030;{$this->today()};\r\n", $admin);
        $this->assertSame([], $result['errors']);
        $this->assertFalse($result['rows'][0]['registers']);

        // Bis zum Übernehmen hat jemand den Umfang geändert: Der Import würde sich selbst eintragen.
        Qualification::register($admin, SupervisionScope::Licensed, '2030-12-31', now()->toDateString(), $other);
        $imported = app(MemberImport::class)->import($admin, $result['rows']);

        $this->assertSame(1, $imported['skipped']);
        $this->assertSame(0, Qualification::where('checked_by_id', $admin->id)->count());
        $this->assertSame(SupervisionScope::Licensed, $admin->fresh()->supervisor_scope);
    }

    public function test_reimport_does_not_add_identical_register_rows()
    {
        $admin = User::factory()->admin()->create();
        $csv = self::HEADER."Erika;Musterfrau;erika@verein.test;1001;;;erlaubnisfrei;31.12.2030;{$this->today()};\r\n";

        $this->upload($admin, $csv);
        $this->confirm($admin, ['confirmed' => true]);
        $this->assertSame(1, Qualification::count());

        $this->upload($admin, $csv);
        $this->actingAs($admin)->get(route('admin.users.import'))
            ->assertInertia(fn ($page) => $page
                ->where('preview.rows.0.exists', true)
                ->where('preview.rows.0.registers', false)
                ->where('preview.rows.0.current_scope', 'exempt')
                // Nichts Neues im Register, nichts neu volljährig: nichts zu bestätigen.
                ->where('confirmation', null));
        $this->confirm($admin)->assertSessionHasNoErrors();

        $this->assertSame(1, Qualification::count());
        $this->assertSame(1, AuditEntry::where('action', 'qualification.registered')->count());

        // Ein anderes Ende ist eine neue Prüfung und damit eine neue Zeile.
        $this->upload($admin, self::HEADER."Erika;Musterfrau;erika@verein.test;1001;;;erlaubnisfrei;31.12.2031;{$this->today()};\r\n");
        $this->confirm($admin, ['confirmed' => true]);
        $this->assertSame(2, Qualification::count());
        $this->assertSame('2031-12-31', User::where('email', 'erika@verein.test')->sole()->supervisor_until->toDateString());
    }

    public function test_cancel_discards_the_preview()
    {
        $admin = User::factory()->admin()->create();
        $this->upload($admin, self::CSV);

        $this->actingAs($admin)->delete(route('admin.users.import-cancel'))->assertRedirect(route('admin.users.import'));
        $this->assertNull(session('member_import.preview'));

        $this->confirm($admin)->assertRedirect(route('admin.users.import'));
        $this->assertSame(1, User::count());
    }

    private function until(): string
    {
        return now()->addYear()->endOfYear()->format('d.m.Y');
    }

    public function test_reimport_after_a_manual_revocation_is_an_error()
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $checked = now()->subMonth()->format('d.m.Y');
        $csv = self::HEADER."Erika;Musterfrau;erika@verein.test;1001;;ja;erlaubnispflichtig;{$this->until()};{$checked};\r\n";

        $this->upload($admin, $csv);
        $this->confirm($admin, ['confirmed' => true])->assertSessionHasNoErrors();
        $erika = User::where('email', 'erika@verein.test')->sole();
        $this->assertTrue($erika->canSupervise(licensed: true));

        // Ein anderes Vorstandsmitglied entzieht die Aufsicht von Hand.
        $revokedOn = now()->subDay();
        Qualification::register($erika, SupervisionScope::Revoked, null, $revokedOn->toDateString(), $other);

        // Dieselbe Datei noch einmal: Der Entzug bleibt, die Zeile ist ein Fehler.
        $this->upload($admin, $csv);
        $this->actingAs($admin)->get(route('admin.users.import'))
            ->assertInertia(fn ($page) => $page
                ->has('preview.rows', 0)
                ->where('preview.errors', ['Zeile 2: Aufsicht am '.$revokedOn->format('d.m.Y').' entzogen – erneut eintragen nur einzeln beim Mitglied.'])
                ->where('confirmation', null));
        $this->confirm($admin, ['confirmed' => true]);

        $this->assertFalse($erika->fresh()->canSupervise());
        $this->assertSame(2, Qualification::count());
        $this->assertSame(SupervisionScope::Revoked, $erika->qualifications()->first()?->scope);

        // Ohne Aufsicht in der Datei zeigt die Vorschau den Entzug als bisherigen Stand.
        $result = app(MemberImport::class)->parse("Name;E-Mail\nErika Musterfrau;erika@verein.test\n");
        $this->assertSame([], $result['errors']);
        $this->assertSame('revoked', $result['rows'][0]['current_scope']);
        $this->assertSame($revokedOn->toDateString(), $result['rows'][0]['current_checked_on']);
    }

    public function test_revocation_between_preview_and_import_skips_the_row()
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $erika = User::factory()->create(['name' => 'Erika Musterfrau', 'email' => 'erika@verein.test']);
        Qualification::register($erika, SupervisionScope::Licensed, now()->addYear()->toDateString(), now()->subMonth()->toDateString(), $other);

        // Eine Zeile unverändert, eine mit neuem Ende: beide treffen danach auf den Entzug.
        $same = app(MemberImport::class)->parse(self::HEADER
            .'Erika;Musterfrau;erika@verein.test;;;;erlaubnispflichtig;'.now()->addYear()->format('d.m.Y').';'.now()->subMonth()->format('d.m.Y').";\r\n");
        $longer = app(MemberImport::class)->parse(self::HEADER
            ."Erika;Musterfrau;erika@verein.test;;;;erlaubnispflichtig;{$this->until()};{$this->today()};\r\n");
        $this->assertFalse($same['rows'][0]['registers']);
        $this->assertTrue($longer['rows'][0]['registers']);
        $this->assertSame('licensed', $longer['rows'][0]['current_scope']);
        $this->assertSame(now()->addYear()->toDateString(), $longer['rows'][0]['current_until']);

        Qualification::register($erika, SupervisionScope::Revoked, null, now()->toDateString(), $other);

        $this->assertSame(1, app(MemberImport::class)->import($admin, $same['rows'])['skipped']);
        $this->assertSame(1, app(MemberImport::class)->import($admin, $longer['rows'])['skipped']);
        $this->assertFalse($erika->fresh()->canSupervise());
        $this->assertSame(2, Qualification::count());
    }

    public function test_an_older_check_than_the_latest_register_row_is_an_error()
    {
        $other = User::factory()->admin()->create();
        $erika = User::factory()->create(['name' => 'Erika Musterfrau', 'email' => 'erika@verein.test']);
        Qualification::register($erika, SupervisionScope::Exempt, now()->addYear()->toDateString(), now()->subDays(5)->toDateString(), $other);
        $older = now()->subDays(10)->format('d.m.Y');

        $result = app(MemberImport::class)->parse(self::HEADER
            ."Erika;Musterfrau;erika@verein.test;;;;erlaubnispflichtig;{$this->until()};{$older};\r\n");

        $this->assertSame([], $result['rows']);
        $this->assertSame(
            ["Zeile 2: „Aufsicht geprüft am“ {$older} liegt vor der jüngsten Zeile im Aufsichtsregister (geprüft am ".now()->subDays(5)->format('d.m.Y').').'],
            $result['errors'],
        );
    }

    public function test_unchanged_supervision_with_an_older_check_date_is_no_error()
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $sven = User::factory()->create(['name' => 'Sven Alt', 'email' => 'sven@verein.test', 'member_number' => '12']);
        $checked = now()->subDays(2);
        Qualification::register($sven, SupervisionScope::Exempt, now()->addYear()->endOfYear()->toDateString(), $checked->toDateString(), $other);
        // Der Export trägt das Datum des Lehrgangs, nicht das der Prüfung von Hand.
        $course = now()->subMonths(5)->format('d.m.Y');

        $result = app(MemberImport::class)->parse(self::HEADER
            ."Sven;Neu;sven@verein.test;13;;;erlaubnisfrei;{$this->until()};{$course};Lehrgang\r\n", $admin);

        $this->assertSame([], $result['errors']);
        $this->assertFalse($result['rows'][0]['registers']);
        $this->assertSame(
            "„Aufsicht geprüft am“ {$course} liegt vor der jüngsten Zeile im Aufsichtsregister (geprüft am {$checked->format('d.m.Y')}) – bleibt unverändert, es wird nichts eingetragen.",
            $result['rows'][0]['register_hint'],
        );
        $this->assertNull(app(MemberImport::class)->confirmation($result['rows']));

        // Auch beim Übernehmen: Name und Nummer ändern sich, das Register bleibt.
        $imported = app(MemberImport::class)->import($admin, $result['rows']);
        $this->assertSame(0, $imported['skipped']);
        $this->assertSame(1, $imported['updated']);
        $this->assertSame(0, $imported['registered']);
        $this->assertSame('Sven Neu', $sven->fresh()->name);
        $this->assertSame('13', $sven->fresh()->member_number);
        $this->assertSame(1, Qualification::count());

        // Ein neues Ende mit dem älteren Datum würde eingetragen: das bleibt ein Fehler.
        $longer = app(MemberImport::class)->parse(self::HEADER
            .'Sven;Neu;sven@verein.test;13;;;erlaubnisfrei;'.now()->addYears(2)->endOfYear()->format('d.m.Y').";{$course};\r\n", $admin);
        $this->assertSame([], $longer['rows']);
        $this->assertStringContainsString('liegt vor der jüngsten Zeile im Aufsichtsregister', $longer['errors'][0] ?? '');

        // Ohne älteres Datum kein Hinweis.
        $same = app(MemberImport::class)->parse(self::HEADER
            ."Sven;Neu;sven@verein.test;13;;;erlaubnisfrei;{$this->until()};{$checked->format('d.m.Y')};\r\n", $admin);
        $this->assertNull($same['rows'][0]['register_hint']);
    }

    public function test_a_member_number_with_another_name_is_an_error()
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['name' => 'Bernd Meier', 'email' => null, 'password' => null, 'member_number' => '1001']);
        $max = User::factory()->create(['name' => 'Max Real', 'email' => null, 'password' => null, 'member_number' => '1002']);
        $csv = self::HEADER
            ."Erika;Musterfrau;erika@verein.test;1002;01.03.2015;ja;erlaubnisfrei;{$this->until()};{$this->today()};\r\n"
            ."Karl;Ohnemail;;1001;15.06.2019;nein;;;;\r\n";

        $result = app(MemberImport::class)->parse($csv);
        $this->assertSame([], $result['rows']);
        $this->assertSame([
            'Zeile 2: Mitgliedsnummer 1002 gehört „Max Real“, in der Datei steht „Erika Musterfrau“ – Name weicht ab (verglichen ohne Groß-/Kleinschreibung, Satzzeichen, Titel und Reihenfolge, ä = ae, ß = ss). Nummer prüfen oder das Mitglied einzeln bearbeiten.',
            'Zeile 3: Mitgliedsnummer 1001 gehört „Bernd Meier“, in der Datei steht „Karl Ohnemail“ – Name weicht ab (verglichen ohne Groß-/Kleinschreibung, Satzzeichen, Titel und Reihenfolge, ä = ae, ß = ss). Nummer prüfen oder das Mitglied einzeln bearbeiten.',
        ], $result['errors']);

        $this->upload($admin, $csv);
        $this->confirm($admin, ['confirmed' => true]);
        $max->refresh();
        $this->assertSame('Max Real', $max->name);
        $this->assertNull($max->email);
        $this->assertNull($max->adult_confirmed_at);
        $this->assertSame(0, Qualification::count());
    }

    public function test_names_are_compared_without_case_spaces_and_unicode_form()
    {
        $juergen = User::factory()->create(['name' => 'Jürgen Größer', 'email' => null, 'password' => null, 'member_number' => '1003']);

        // „ü“ als u + Trema (NFD, z. B. aus macOS), dazu andere Schreibung und Leerzeichen.
        $result = app(MemberImport::class)->parse("Name;Mitgliedsnummer\n Ju\u{0308}rgen   GRÖßER ;1003\n");
        $this->assertSame([], $result['errors']);
        $this->assertSame($juergen->id, $result['rows'][0]['user_id']);
        $this->assertSame('number', $result['rows'][0]['matched_by']);
    }

    /**
     * Schreibweisen derselben Person aus Vereinsverwaltung, Excel oder von Hand: gespeichert, in der Datei.
     *
     * @return list<array{0: string, 1: string}>
     */
    private static function sameNames(): array
    {
        return [
            ['Jürgen Größer', 'JÜRGEN GRÖSSER'],
            ['Jürgen Größer', 'Juergen Groesser'],
            ['Hans Strauß', 'Hans STRAUSS'],
            ['Hans Meier', 'Dr. Hans Meier'],
            ['Hans Meier', 'Prof. Dr. med. Hans Meier'],
            ['Hans Meier', 'Dipl.-Ing. Hans Meier'],
            ['Hans Peter Meier', 'Hans-Peter Meier'],
            ['Hans Meier', 'Meier, Hans'],
            ['Anna Meier-Schulz', 'Anna Meier - Schulz'],
            ['Anna Meier-Schulz', 'Anna Meier–Schulz'],
            ['Liam O’Brien', "Liam O'Brien"],
        ];
    }

    public function test_spelling_variants_of_the_same_name_match_the_member_number()
    {
        $member = User::factory()->create(['email' => null, 'password' => null, 'member_number' => '500']);

        foreach (self::sameNames() as [$stored, $file]) {
            // In beide Richtungen: gespeichert wie in der Vereinsverwaltung oder wie in der Datei.
            foreach ([[$stored, $file], [$file, $stored]] as [$name, $inFile]) {
                $member->forceFill(['name' => $name])->save();

                $result = app(MemberImport::class)->parse("Name;Mitgliedsnummer\n{$inFile};500\n");

                $this->assertSame([], $result['errors'], "{$name} / {$inFile}");
                $this->assertSame($member->id, $result['rows'][0]['user_id'], "{$name} / {$inFile}");
            }
        }
    }

    public function test_a_really_different_name_with_the_member_number_stays_an_error()
    {
        $admin = User::factory()->admin()->create();
        $hans = User::factory()->create(['name' => 'Hans Meier', 'email' => null, 'password' => null, 'member_number' => '500']);
        $anna = User::factory()->create(['name' => 'Anna Meier', 'email' => null, 'password' => null, 'member_number' => '501']);
        $csv = "Name;Mitgliedsnummer\nHans Meyer;500\nAnna Meier-Schulz;501\n";

        $result = app(MemberImport::class)->parse($csv);

        $this->assertSame([], $result['rows']);
        $this->assertSame([
            'Zeile 2: Mitgliedsnummer 500 gehört „Hans Meier“, in der Datei steht „Hans Meyer“ – Name weicht ab (verglichen ohne Groß-/Kleinschreibung, Satzzeichen, Titel und Reihenfolge, ä = ae, ß = ss). Nummer prüfen oder das Mitglied einzeln bearbeiten.',
            'Zeile 3: Mitgliedsnummer 501 gehört „Anna Meier“, in der Datei steht „Anna Meier-Schulz“ – Name weicht ab (verglichen ohne Groß-/Kleinschreibung, Satzzeichen, Titel und Reihenfolge, ä = ae, ß = ss). Nummer prüfen oder das Mitglied einzeln bearbeiten.',
        ], $result['errors']);

        $this->upload($admin, $csv);
        $this->confirm($admin);
        $this->assertSame('Hans Meier', $hans->fresh()->name);
        $this->assertSame('Anna Meier', $anna->fresh()->name);
        $this->assertSame(3, User::count());
    }

    public function test_name_next_to_vorname_is_the_last_name()
    {
        $admin = User::factory()->admin()->create();
        $juergen = User::factory()->create(['name' => 'Jürgen Größer', 'email' => 'j@verein.test']);
        $csv = "Name;Vorname;E-Mail\nGrößer;Jürgen;j@verein.test\nMusterfrau;Erika;erika@verein.test\n";

        $result = app(MemberImport::class)->parse($csv);

        $this->assertSame([], $result['errors']);
        $this->assertSame(['Jürgen Größer', 'Erika Musterfrau'], array_column($result['rows'], 'name'));
        $this->assertSame(['last_name', 'first_name', 'email'], $result['columns']);

        $this->upload($admin, $csv);
        $this->confirm($admin)->assertSessionHasNoErrors();
        $this->assertSame('Jürgen Größer', $juergen->fresh()->name);
        $this->assertFalse(AuditEntry::where('action', 'user.updated')->exists());
        $this->assertSame('Erika Musterfrau', User::where('email', 'erika@verein.test')->value('name'));

        // „Vollständiger Name“ ist immer der ganze Name, auch neben „Vorname“.
        $result = app(MemberImport::class)->parse("Vollständiger Name;Vorname;E-Mail\nJürgen Größer;Jürgen;j@verein.test\n");
        $this->assertSame('Jürgen Größer', $result['rows'][0]['name']);
    }

    public function test_template_example_rows_never_touch_real_members()
    {
        $admin = User::factory()->admin()->create();
        $bernd = User::factory()->create(['name' => 'Bernd Meier', 'email' => null, 'password' => null, 'member_number' => '1001']);
        $max = User::factory()->create(['name' => 'Max Real', 'email' => null, 'password' => null, 'member_number' => '1002']);

        $this->upload($admin, MemberImport::template());
        $this->actingAs($admin)->get(route('admin.users.import'))
            ->assertInertia(fn ($page) => $page->has('preview.rows', 0)->has('preview.errors', 2));
        $this->confirm($admin, ['confirmed' => true]);

        $this->assertSame('Bernd Meier', $bernd->fresh()->name);
        $this->assertSame('Max Real', $max->fresh()->name);
        $this->assertNull($max->fresh()->email);
        $this->assertSame(3, User::count());

        // Auch abgewandelt: Adresse bei example.org oder Nummer „Beispiel…“.
        $result = app(MemberImport::class)->parse("Name;E-Mail;Mitgliedsnummer\nMax Real;MAX@EXAMPLE.ORG;1002\nMax Real;;beispiel-9\n");
        $this->assertSame([], $result['rows']);
        $this->assertSame([
            'Zeile 2: Beispielzeile aus der Vorlage – bitte löschen oder ersetzen.',
            'Zeile 3: Beispielzeile aus der Vorlage – bitte löschen oder ersetzen.',
        ], $result['errors']);
    }

    public function test_leading_zeros_of_a_member_found_by_email_are_kept()
    {
        $admin = User::factory()->admin()->create();
        $mia = User::factory()->create(['name' => 'Mia', 'email' => 'mia@verein.test', 'member_number' => '0042']);
        // Ohne die Regel wäre 42 ein Konflikt mit diesem Mitglied.
        User::factory()->create(['email' => 'zweiundvierzig@verein.test', 'member_number' => '42']);

        $result = app(MemberImport::class)->parse("Name;E-Mail;Mitgliedsnummer\nMia;mia@verein.test;42\n");

        $this->assertSame([], $result['errors']);
        $this->assertSame('0042', $result['rows'][0]['member_number']);
        $this->assertSame(['Nr. 42 in der Datei, bleibt 0042 (führende Nullen).'], $result['rows'][0]['hints']);

        app(MemberImport::class)->import($admin, $result['rows']);
        $this->assertSame('0042', $mia->fresh()->member_number);
        $this->assertFalse(AuditEntry::where('action', 'user.updated')->exists());
    }

    public function test_numbers_that_match_only_without_leading_zeros_are_an_error()
    {
        $admin = User::factory()->admin()->create();
        $opa = User::factory()->create(['name' => 'Opa Ohne', 'email' => null, 'password' => null, 'member_number' => '0042', 'member_since' => '2010-01-01']);
        // Vater und Sohn heißen gleich; der Sohn darf nicht in den Datensatz des Vaters laufen.
        $vater = User::factory()->create(['name' => 'Hans Meier', 'email' => null, 'password' => null, 'member_number' => '7', 'member_since' => '1990-01-01']);
        // Über die E-Mail gefunden, aber die Nummer gibt es schon mit Nullen bei jemand anderem.
        $anna = User::factory()->create(['name' => 'Anna Neu', 'email' => 'anna@verein.test', 'member_number' => null]);
        User::factory()->create(['name' => 'Ede Eins', 'email' => null, 'password' => null, 'member_number' => '0001']);
        User::factory()->create(['name' => 'Ede Eins', 'email' => null, 'password' => null, 'member_number' => '001']);
        $csv = "Name;E-Mail;Mitgliedsnummer;Eintritt\nOpa Ohne;;42;01.03.2015\nHans Meier;sohn@verein.test;007;01.05.2024\nAnna Neu;anna@verein.test;42;\nEde Eins;;1;\n";

        $result = app(MemberImport::class)->parse($csv);

        $this->assertSame([], $result['rows']);
        $this->assertSame([
            'Zeile 2: Nummer 42 passt nur ohne führende Nullen zu 0042 („Opa Ohne“). Ist es dieselbe Person, die Nummer genau wie gespeichert angeben; sonst das Mitglied einzeln anlegen.',
            'Zeile 3: Nummer 007 passt nur ohne führende Nullen zu 7 („Hans Meier“). Ist es dieselbe Person, die Nummer genau wie gespeichert angeben; sonst das Mitglied einzeln anlegen.',
            'Zeile 4: Nummer 42 passt nur ohne führende Nullen zu 0042 („Opa Ohne“). Ist es dieselbe Person, die Nummer genau wie gespeichert angeben; sonst das Mitglied einzeln anlegen.',
            'Zeile 5: Nummer 1 passt nur ohne führende Nullen zu 0001 („Ede Eins“), 001 („Ede Eins“). Ist es dieselbe Person, die Nummer genau wie gespeichert angeben; sonst das Mitglied einzeln anlegen.',
        ], $result['errors']);

        $this->upload($admin, $csv);
        $this->confirm($admin);

        // Nichts verändert, kein Duplikat.
        $this->assertSame(6, User::count());
        $this->assertSame('2010-01-01', $opa->fresh()->member_since->toDateString());
        $this->assertNull($vater->fresh()->email);
        $this->assertSame('1990-01-01', $vater->fresh()->member_since->toDateString());
        $this->assertNull($anna->fresh()->member_number);
    }

    public function test_preview_shows_current_values_and_an_attached_email()
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['name' => 'Max Real', 'email' => null, 'password' => null, 'member_number' => '1002', 'member_since' => '2010-01-01']);
        User::factory()->create(['name' => 'Anna Alt', 'email' => 'anna@verein.test', 'member_number' => '7']);

        $this->upload($admin, "Name;E-Mail;Mitgliedsnummer;Eintritt\nMax Real;max@verein.test;1002;01.03.2015\nAnna Neu;anna@verein.test;8;\n");

        $this->actingAs($admin)->get(route('admin.users.import'))
            ->assertInertia(fn ($page) => $page
                ->where('preview.errors', [])
                ->where('preview.rows.0.exists', true)
                ->where('preview.rows.0.matched_by', 'number')
                ->where('preview.rows.0.email', 'max@verein.test')
                ->where('preview.rows.0.current_email', null)
                ->where('preview.rows.0.current_name', 'Max Real')
                ->where('preview.rows.0.current_member_number', '1002')
                ->where('preview.rows.0.current_member_since', '2010-01-01')
                ->where('preview.rows.0.member_since', '2015-03-01')
                ->where('preview.rows.1.matched_by', 'email')
                ->where('preview.rows.1.current_name', 'Anna Alt')
                ->where('preview.rows.1.current_member_number', '7')
                ->where('preview.rows.1.member_number', '8'));
    }

    public function test_import_writes_an_audit_entry_per_member_with_old_and_new_values()
    {
        $admin = User::factory()->admin()->create();
        $max = User::factory()->create(['name' => 'Max Real', 'email' => null, 'password' => null, 'member_number' => '1002', 'member_since' => '2010-01-01']);
        $anna = User::factory()->create(['name' => 'Anna Alt', 'email' => 'anna@verein.test', 'member_number' => '7']);
        $same = User::factory()->create(['name' => 'Gleich Bleibt', 'email' => 'gleich@verein.test', 'member_number' => '9']);

        $this->upload($admin, "Name;E-Mail;Mitgliedsnummer;Eintritt\n"
            ."Max Real;max@verein.test;1002;01.03.2015\n"
            ."Anna Neu;anna@verein.test;8;\n"
            ."Gleich Bleibt;gleich@verein.test;9;\n"
            ."Nina Neu;nina@verein.test;10;\n");
        $this->confirm($admin)->assertSessionHasNoErrors();

        $updated = AuditEntry::where('action', 'user.updated')->orderBy('id')->get();
        $this->assertCount(2, $updated);
        $this->assertSame([$max->id, $anna->id], $updated->pluck('subject_id')->all());
        $this->assertSame((new User)->getMorphClass(), $updated[0]->subject_type);
        $this->assertSame($admin->id, $updated[0]->actor_id);
        $this->assertSame('Mitglied geändert (CSV-Import): Max Real (E-Mail; Mitglied seit: 2010-01-01 → 2015-03-01)', $updated[0]->summary);
        $this->assertSame('Mitglied geändert (CSV-Import): Anna Neu (Name: Anna Alt → Anna Neu; Nr.: 7 → 8)', $updated[1]->summary);
        $this->assertFalse(AuditEntry::where('subject_id', $same->id)->exists());

        $nina = User::where('email', 'nina@verein.test')->sole();
        $created = AuditEntry::where('action', 'user.created')->sole();
        $this->assertSame($nina->id, $created->subject_id);
        $this->assertSame('Mitglied angelegt (CSV-Import): Nina Neu (member)', $created->summary);
        $this->assertSame('CSV-Import: 1 neu, 3 aktualisiert, 0 ausgelassen, 0 Aufsicht eingetragen, 0 volljährig geprüft', AuditEntry::where('action', 'user.imported')->sole()->summary);
    }

    public function test_member_changed_since_the_preview_is_skipped()
    {
        $admin = User::factory()->admin()->create();
        $max = User::factory()->create(['name' => 'Max Real', 'email' => null, 'password' => null, 'member_number' => '1002']);

        $result = app(MemberImport::class)->parse("Name;E-Mail;Mitgliedsnummer\nMax Real;max@verein.test;1002\n");
        $this->assertSame([], $result['errors']);

        // Inzwischen hat jemand das Mitglied von Hand geändert.
        $max->forceFill(['name' => 'Max Anders'])->save();

        $this->assertSame(1, app(MemberImport::class)->import($admin, $result['rows'])['skipped']);
        $this->assertNull($max->fresh()->email);
    }

    public function test_adult_check_without_confirmation_fails()
    {
        $admin = User::factory()->admin()->create();
        $this->upload($admin, "Name;E-Mail;Mitgliedsnummer;Volljährig geprüft\nMia;mia@verein.test;42;ja\nKurt;kurt@verein.test;43;ja\n");

        $this->actingAs($admin)->get(route('admin.users.import'))
            ->assertInertia(fn ($page) => $page->where('confirmation', 'Für alle als volljährig geprüft markierten Personen habe ich die Volljährigkeit geprüft.'));

        $this->confirm($admin)->assertSessionHasErrors(['confirmed' => 'Bitte bestätigen Sie die Volljährigkeit für alle als volljährig geprüft markierten Personen.']);
        $this->confirm($admin, ['confirmed' => false])->assertSessionHasErrors('confirmed');

        $this->assertSame(1, User::count());
        $this->assertFalse(AuditEntry::where('action', 'user.imported')->exists());
    }

    public function test_adult_check_with_confirmation_sets_the_flag_and_writes_an_audit_entry_per_member()
    {
        $admin = User::factory()->admin()->create();
        $this->upload($admin, "Name;E-Mail;Mitgliedsnummer;Volljaehrig geprueft\nMia;mia@verein.test;42;ja\nKurt;kurt@verein.test;43;ja\n");

        $this->confirm($admin, ['confirmed' => true])->assertSessionHasNoErrors();

        $mia = User::where('email', 'mia@verein.test')->sole();
        $kurt = User::where('email', 'kurt@verein.test')->sole();
        $this->assertNotNull($mia->adult_confirmed_at);
        $this->assertNotNull($kurt->adult_confirmed_at);

        $entries = AuditEntry::where('action', 'user.adult_confirmed')->orderBy('id')->get();
        $this->assertSame([$mia->id, $kurt->id], $entries->pluck('subject_id')->all());
        $this->assertSame([$admin->id, $admin->id], $entries->pluck('actor_id')->all());
        $this->assertSame('Volljährig geprüft: Mia (beim CSV-Import)', $entries[0]->summary);
        $this->assertSame(2, AuditEntry::where('action', 'user.created')->count());
        $this->assertStringEndsWith('2 volljährig geprüft', AuditEntry::where('action', 'user.imported')->sole()->summary);
    }

    public function test_adult_check_is_logged_once_and_not_again_as_a_change()
    {
        $admin = User::factory()->admin()->create();
        $anna = User::factory()->create(['name' => 'Anna Alt', 'email' => 'anna@verein.test', 'adult_confirmed_at' => null]);
        $berta = User::factory()->create(['name' => 'Berta Gleich', 'email' => 'berta@verein.test', 'adult_confirmed_at' => null]);

        $this->upload($admin, "Name;E-Mail;Volljährig geprüft\nAnna Neu;anna@verein.test;ja\nBerta Gleich;berta@verein.test;ja\n");
        $this->confirm($admin, ['confirmed' => true])->assertSessionHasNoErrors();

        $this->assertNotNull($anna->fresh()->adult_confirmed_at);
        $this->assertNotNull($berta->fresh()->adult_confirmed_at);

        // Der Name steht in „user.updated“, die Volljährigkeit nur in „user.adult_confirmed“.
        $updated = AuditEntry::where('action', 'user.updated')->sole();
        $this->assertSame($anna->id, $updated->subject_id);
        $this->assertSame('Mitglied geändert (CSV-Import): Anna Neu (Name: Anna Alt → Anna Neu)', $updated->summary);
        $this->assertSame(
            ['Volljährig geprüft: Anna Neu (beim CSV-Import)', 'Volljährig geprüft: Berta Gleich (beim CSV-Import)'],
            AuditEntry::where('action', 'user.adult_confirmed')->orderBy('id')->pluck('summary')->all(),
        );
        $this->assertSame(0, AuditEntry::where('summary', 'like', '%volljährig geprüft: ja%')->count());
        $this->assertStringContainsString('2 aktualisiert', AuditEntry::where('action', 'user.imported')->sole()->summary);
    }

    public function test_a_plain_volljaehrig_column_is_no_adult_check()
    {
        $admin = User::factory()->admin()->create();
        $csv = "Name;E-Mail;Mitgliedsnummer;Volljährig\nMia;mia@verein.test;42;ja\nKurt;kurt@verein.test;43;ja\n";

        $result = app(MemberImport::class)->parse($csv);
        $this->assertNotContains('adult', $result['columns']);
        $this->assertSame([null, null], array_column($result['rows'], 'adult'));
        $this->assertNull(app(MemberImport::class)->confirmation($result['rows']));

        $this->upload($admin, $csv);
        $this->confirm($admin)->assertSessionHasNoErrors();
        $this->assertSame(0, User::whereNotNull('adult_confirmed_at')->whereKeyNot($admin->id)->count());
        $this->assertFalse(AuditEntry::where('action', 'user.adult_confirmed')->exists());

        // Schon geprüfte Mitglieder: „ja“ ändert nichts, also nichts zu bestätigen.
        User::where('email', 'mia@verein.test')->update(['adult_confirmed_at' => now()]);
        $result = app(MemberImport::class)->parse("Name;E-Mail;Volljährig geprüft\nMia;mia@verein.test;ja\n");
        $this->assertNull(app(MemberImport::class)->confirmation($result['rows']));
    }

    public function test_licensed_rows_need_the_weapons_expertise_confirmation()
    {
        $admin = User::factory()->admin()->create();
        $this->upload($admin, self::HEADER."Erika;Musterfrau;erika@verein.test;;;;erlaubnispflichtig;{$this->until()};{$this->today()};\r\n");

        $this->actingAs($admin)->get(route('admin.users.import'))
            ->assertInertia(fn ($page) => $page->where('confirmation', 'Für alle als Aufsicht markierten Personen habe ich Volljährigkeit und Sachkunde geprüft, einschließlich Waffensachkunde für die als erlaubnispflichtig markierten Personen.'));
        $this->confirm($admin)->assertSessionHasErrors(['confirmed' => 'Bitte bestätigen Sie Volljährigkeit und Sachkunde für alle als Aufsicht markierten Personen, einschließlich Waffensachkunde für die als erlaubnispflichtig markierten Personen.']);
        $this->assertSame(0, Qualification::count());

        $this->confirm($admin, ['confirmed' => true])->assertSessionHasNoErrors();
        $this->assertTrue(User::where('email', 'erika@verein.test')->sole()->canSupervise(licensed: true));
    }

    public function test_confirmation_text_covers_what_the_file_sets()
    {
        $import = app(MemberImport::class);

        $exempt = $import->parse(self::HEADER."Erika;Musterfrau;erika@verein.test;;;;erlaubnisfrei;{$this->until()};{$this->today()};\r\n");
        $this->assertSame([
            'text' => 'Für alle als Aufsicht markierten Personen habe ich Volljährigkeit und Sachkunde geprüft.',
            'message' => 'Bitte bestätigen Sie Volljährigkeit und Sachkunde für alle als Aufsicht markierten Personen.',
        ], $import->confirmation($exempt['rows']));

        $both = $import->parse(self::HEADER
            ."Erika;Musterfrau;erika@verein.test;;;;erlaubnisfrei;{$this->until()};{$this->today()};\r\n"
            ."Paul;Pfeil;paul@verein.test;;;ja;;;;\r\n");
        $this->assertSame([
            'text' => 'Für alle als volljährig geprüft markierten Personen habe ich die Volljährigkeit geprüft. Für alle als Aufsicht markierten Personen habe ich Volljährigkeit und Sachkunde geprüft.',
            'message' => 'Bitte bestätigen Sie die Volljährigkeit für alle als volljährig geprüft markierten Personen sowie Volljährigkeit und Sachkunde für alle als Aufsicht markierten Personen.',
        ], $import->confirmation($both['rows']));

        // Eine erlaubnispflichtige Zeile ohne neuen Eintrag trägt nichts ein: keine Waffensachkunde im Text.
        $lena = User::factory()->create(['name' => 'Lena Lang', 'email' => 'lena@verein.test']);
        Qualification::register($lena, SupervisionScope::Licensed, now()->addYear()->toDateString(), now()->subMonth()->toDateString(), User::factory()->admin()->create());
        $lenaRow = 'Lena;Lang;lena@verein.test;;;;erlaubnispflichtig;'.now()->addYear()->format('d.m.Y').';'.now()->subMonth()->format('d.m.Y').";\r\n";
        $mixed = $import->parse(self::HEADER
            ."Erika;Musterfrau;erika@verein.test;;;;erlaubnisfrei;{$this->until()};{$this->today()};\r\n"
            .$lenaRow);
        $this->assertSame([true, false], array_column($mixed['rows'], 'registers'));
        $this->assertSame([
            'text' => 'Für alle als Aufsicht markierten Personen habe ich Volljährigkeit und Sachkunde geprüft.',
            'message' => 'Bitte bestätigen Sie Volljährigkeit und Sachkunde für alle als Aufsicht markierten Personen.',
        ], $import->confirmation($mixed['rows']));

        // Nur die unveränderte Zeile: nichts zu bestätigen.
        $this->assertNull($import->confirmation($import->parse(self::HEADER.$lenaRow)['rows']));

        $nothing = $import->parse("Name;E-Mail\nPaul Pfeil;paul@verein.test\n");
        $this->assertNull($import->confirmation($nothing['rows']));
    }

    public function test_unclosed_quote_rejects_the_whole_file()
    {
        $admin = User::factory()->admin()->create();
        $csv = self::HEADER
            ."Erika;Muster;erika@verein.test;1001;;ja;erlaubnisfrei;{$this->until()};{$this->today()};\"Lehrgang 2019\r\n"
            ."Otto;Zwei;otto@verein.test;1002;;ja;;;;\r\n"
            ."Pia;Drei;pia@verein.test;1003;;;;;;\r\n";

        $result = app(MemberImport::class)->parse($csv);

        // Ohne die Prüfung stünden Otto und Pia als Text in Erikas Bemerkung und fehlten still.
        $this->assertSame([], $result['rows']);
        $this->assertSame(['Zeile 2: Anführungszeichen nicht geschlossen – bitte die Datei prüfen. Ein einzelnes " bitte verdoppeln oder entfernen.'], $result['errors']);

        $this->upload($admin, $csv);
        $this->actingAs($admin)->get(route('admin.users.import'))
            ->assertInertia(fn ($page) => $page
                ->has('preview.rows', 0)
                ->where('preview.errors', ['Zeile 2: Anführungszeichen nicht geschlossen – bitte die Datei prüfen. Ein einzelnes " bitte verdoppeln oder entfernen.'])
                ->where('confirmation', null));
        $this->confirm($admin, ['confirmed' => true]);

        $this->assertSame(1, User::count());
        $this->assertSame(0, Qualification::count());
    }

    public function test_quoted_note_with_a_line_break_stays_whole()
    {
        $admin = User::factory()->admin()->create();
        $csv = self::HEADER
            ."Erika;Muster;erika@verein.test;1001;;;erlaubnisfrei;{$this->until()};{$this->today()};\"Lehrgang\r\nStandaufsicht DSB\"\r\n"
            ."Karl;Kaputt;karl@verein.test;;;;;;;Bemerkung ohne Aufsicht\r\n";

        $result = app(MemberImport::class)->parse($csv);

        $this->assertCount(1, $result['rows']);
        $this->assertSame("Lehrgang\nStandaufsicht DSB", $result['rows'][0]['note']);
        $this->assertTrue($result['rows'][0]['registers']);
        // Die Bemerkung belegt Zeile 2 und 3, die nächste Zeile ist 4 wie in Excel.
        $this->assertCount(1, $result['errors']);
        $this->assertStringStartsWith('Zeile 4: ', $result['errors'][0]);

        $this->upload($admin, $csv);
        $this->confirm($admin, ['confirmed' => true])->assertSessionHasNoErrors();
        $this->assertSame("Lehrgang\nStandaufsicht DSB", Qualification::sole()->note);
    }

    public function test_rows_of_only_delimiters_are_skipped()
    {
        $result = app(MemberImport::class)->parse("Name;E-Mail;Mitgliedsnummer\nErika Muster;erika@verein.test;1\n;;\n;;;;\n ; ; \n\nKarl Kurz;;2\n;;\n");

        $this->assertSame([], $result['errors']);
        $this->assertSame([2, 7], array_column($result['rows'], 'line'));
    }

    public function test_too_many_rows_are_rejected_before_any_query()
    {
        $csv = "Name;E-Mail\n".implode("\n", array_map(fn (int $i) => "Person {$i};p{$i}@verein.test", range(1, MemberImport::MAX_ROWS + 1)))."\n";

        DB::enableQueryLog();
        DB::flushQueryLog();
        $result = app(MemberImport::class)->parse($csv);

        $this->assertSame([], DB::getQueryLog());
        $this->assertSame([], $result['rows']);
        $this->assertSame(['Die Datei hat mehr als 2000 Mitglieder. Bitte in mehrere Dateien aufteilen.'], $result['errors']);
    }

    public function test_preview_loads_members_once_instead_of_per_row()
    {
        User::factory()->count(5)->create();
        $csv = "Name;E-Mail;Mitgliedsnummer\n".implode("\n", array_map(fn (int $i) => "Person {$i};p{$i}@verein.test;{$i}", range(1, 50)))."\n";

        DB::enableQueryLog();
        DB::flushQueryLog();
        $result = app(MemberImport::class)->parse($csv);

        $this->assertCount(50, $result['rows']);
        // Mitglieder und jüngste Registerzeilen, unabhängig von der Zahl der Zeilen.
        $this->assertCount(2, DB::getQueryLog());
    }
}
