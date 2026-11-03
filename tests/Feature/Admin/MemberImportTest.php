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
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Support\SessionKey;
use Tests\TestCase;

class MemberImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = "Vorname;Nachname;E-Mail;Mitgliedsnummer;Eintritt;Volljährig geprüft;Aufsicht;Aufsicht gültig bis;Aufsicht geprüft am;Aufsicht Bemerkung\r\n";

    private const CSV = "\xEF\xBB\xBFVorname;Nachname;E-Mail;Mitgliedsnummer;Eintritt\r\n"
        ."Erika;Musterfrau;Erika@Example.org;1001;01.03.2015\r\n"
        ."Max;Mustermann;max@example.org;1002;\r\n"
        .";;keine-mail;;\r\n"
        ."Doppelt;Erika;erika@example.org;;\r\n";

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
        $this->assertSame('erika@example.org', $result['rows'][0]['email']);
        $this->assertSame('2015-03-01', $result['rows'][0]['member_since']);
        $this->assertNull($result['rows'][0]['scope']);
        $this->assertCount(2, $result['errors']);
        $this->assertStringContainsString('Zeile 5', $result['errors'][1]);
    }

    public function test_comma_separated_files_and_windows_encoding_work()
    {
        $csv = mb_convert_encoding("Name,E-Mail,VOLLJÄHRIG GEPRÜFT\r\nJürgen Größer,juergen@example.org,Ja\r\n", 'Windows-1252', 'UTF-8');

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
        $existing = User::factory()->create(['email' => 'max@example.org', 'name' => 'Alter Name', 'member_number' => null]);
        $password = $existing->password;

        $this->upload($admin, self::CSV)->assertRedirect(route('admin.users.import'));

        $this->assertFalse(User::where('email', 'erika@example.org')->exists());

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

        $erika = User::where('email', 'erika@example.org')->sole();
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
        $other = User::factory()->admin()->create(['email' => 'vorstand@example.org']);
        $untouched = User::factory()->create();

        $this->upload($admin, "Name;E-Mail\nVorstand Neu;vorstand@example.org\n");
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
        $existing = User::factory()->create(['email' => 'max@example.org']);

        $this->upload($admin, self::CSV);
        $this->actingAs($admin)->get(route('admin.users.import'))->assertInertia(fn ($page) => $page->where('mailDelivers', false));

        $response = $this->confirm($admin, ['send_invitations' => true]);

        $erika = User::where('email', 'erika@example.org')->sole();
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

        $this->upload($admin, "Name;E-Mail\nErika Musterfrau;erika@example.org\nKlaus Beispiel;klaus@example.org\n");
        $response = $this->confirm($admin, ['send_invitations' => true]);

        $this->assertSame('2 neu angelegt, 0 aktualisiert. 2 Einladungen per E-Mail verschickt.', $this->toast($response));
        $response->assertSessionHas('member_import.result', fn (array $result) => $result['mailed'] === true && count($result['invitations']) === 2);
        Notification::assertSentTo(User::where('email', 'klaus@example.org')->sole(), UserInvited::class);
    }

    public function test_without_invitation_option_nothing_is_sent()
    {
        Notification::fake();
        config(['mail.default' => 'smtp']);
        $admin = User::factory()->admin()->create();

        $this->upload($admin, "Name;E-Mail\nErika Musterfrau;erika@example.org\n");
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

        // Die Vorlage selbst ist eine gültige Datei.
        $result = app(MemberImport::class)->parse($content);
        $this->assertSame([], $result['errors']);
        $this->assertSame([SupervisionScope::Exempt->value, null], array_column($result['rows'], 'scope'));
    }

    public function test_members_without_email_are_imported_by_member_number()
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $known = User::factory()->create(['email' => null, 'password' => null, 'member_number' => '2002', 'name' => 'Alt']);
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
        $known = User::factory()->create(['email' => null, 'password' => null, 'member_number' => '3001']);
        User::factory()->create(['email' => 'other@example.org', 'member_number' => '3002']);

        $result = app(MemberImport::class)->parse("Name;E-Mail;Mitgliedsnummer\nNeu Mit Mail;neu@example.org;3001\nFremd;fremd@example.org;3002\n");

        $this->assertCount(1, $result['rows']);
        $this->assertTrue($result['rows'][0]['exists']);
        $this->assertStringContainsString('andere E-Mail-Adresse', $result['errors'][0]);

        app(MemberImport::class)->import(User::factory()->admin()->create(), $result['rows']);
        $this->assertSame('neu@example.org', $known->fresh()->email);
    }

    public function test_file_without_email_column_but_with_member_numbers_is_accepted()
    {
        $result = app(MemberImport::class)->parse("Vorname;Nachname;Nr.\nKarl;Ohnemail;77\n");

        $this->assertSame([], $result['errors']);
        $this->assertSame('Karl Ohnemail', $result['rows'][0]['name']);
    }

    public function test_import_keeps_the_match_from_the_preview()
    {
        $member = User::factory()->create(['email' => 'a@example.org', 'member_number' => '5']);

        // Zeile 1 ändert die Nummer, Zeile 2 meint dasselbe Mitglied über die alte Nummer.
        $result = app(MemberImport::class)->parse("Name;E-Mail;Mitgliedsnummer\nAnna;a@example.org;6\nAnna Neu;;5\n");
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

        $this->upload($admin, "Name;E-Mail;Mitgliedsnummer\nNeu Eins;eins@example.org;7001\nNeu Zwei;zwei@example.org;7002\n");

        // Inzwischen hat jemand die Nummer 7002 vergeben.
        User::factory()->create(['member_number' => '7002']);

        $response = $this->confirm($admin);

        $this->assertStringContainsString('Eine Zeile ausgelassen', $this->toast($response));
        $this->assertTrue(User::where('email', 'eins@example.org')->exists());
        $this->assertFalse(User::where('email', 'zwei@example.org')->exists());
    }

    public function test_email_matching_ignores_case()
    {
        User::factory()->create(['email' => 'Erika@Example.org', 'member_number' => '1']);

        $result = app(MemberImport::class)->parse("Name;E-Mail;Mitgliedsnummer\nErika;erika@example.org;1\n");

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
        $applicant = User::factory()->applicant()->create(['email' => 'antrag@example.org']);
        $admin = User::factory()->admin()->create();

        $result = app(MemberImport::class)->parse("Name;E-Mail;Mitgliedsnummer\nAntrag Person;antrag@example.org;4711\n", $admin);

        $this->assertSame([], $result['rows']);
        $this->assertStringContainsString('Zeile 2', $result['errors'][0]);
        $this->assertStringContainsString('Antrag zuerst aufnehmen oder ablehnen', $result['errors'][0]);

        $this->upload($admin, "Name;E-Mail;Mitgliedsnummer\nAntrag Person;antrag@example.org;4711\n");
        $this->confirm($admin);

        $applicant->refresh();
        $this->assertSame(Role::Applicant, $applicant->role);
        $this->assertNull($applicant->member_number);
    }

    public function test_adult_flag_is_only_ever_set()
    {
        $admin = User::factory()->admin()->create();
        $confirmed = User::factory()->create(['email' => 'geprueft@example.org', 'adult_confirmed_at' => now()->subYear()]);
        $open = User::factory()->create(['email' => 'offen@example.org']);

        $this->upload($admin, "Name;E-Mail;Volljährig geprüft\n"
            ."Geprüft;geprueft@example.org;nein\n"
            ."Offen;offen@example.org;\n"
            ."Neu Ja;neu-ja@example.org;ja\n"
            ."Neu Leer;neu-leer@example.org;\n"
            ."Neu Vielleicht;neu-vielleicht@example.org;vielleicht\n");

        $this->actingAs($admin)->get(route('admin.users.import'))
            ->assertInertia(fn ($page) => $page->has('preview.rows', 4)->where('preview.rows.2.adult_sets', true)
                ->where('preview.errors.0', fn (string $error) => str_contains($error, 'Zeile 6') && str_contains($error, 'ja, nein oder leer')));

        $this->confirm($admin);

        $this->assertTrue($confirmed->fresh()->adult_confirmed_at->isSameDay(now()->subYear()));
        $this->assertNull($open->fresh()->adult_confirmed_at);
        $this->assertNotNull(User::where('email', 'neu-ja@example.org')->sole()->adult_confirmed_at);
        $this->assertNull(User::where('email', 'neu-leer@example.org')->sole()->adult_confirmed_at);
        $this->assertStringEndsWith('1 volljährig geprüft', AuditEntry::where('action', 'user.imported')->sole()->summary);
    }

    public function test_supervision_is_registered_with_confirmation()
    {
        $admin = User::factory()->admin()->create();
        $existing = User::factory()->create(['email' => 'max@example.org', 'member_number' => '1002']);

        $this->upload($admin, self::HEADER
            ."Erika;Musterfrau;erika@example.org;1001;01.03.2015;;Erlaubnispflichtig;31.12.2030;{$this->today()};Lehrgang Standaufsicht\r\n"
            ."Max;Mustermann;max@example.org;1002;;;erlaubnisfrei;30.06.2029;{$this->today()};\r\n");

        $this->actingAs($admin)->get(route('admin.users.import'))
            ->assertInertia(fn ($page) => $page
                ->where('preview.rows.0.scope', 'licensed')
                ->where('preview.rows.0.registers', true)
                ->where('preview.rows.1.scope', 'exempt')
                ->where('preview.errors', []));

        $response = $this->confirm($admin, ['confirmed' => true])->assertSessionHasNoErrors();
        $this->assertSame('1 neu angelegt, 1 aktualisiert. 2 im Aufsichtsregister eingetragen.', $this->toast($response));

        $erika = User::where('email', 'erika@example.org')->sole();
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
            ."Erika;Musterfrau;erika@example.org;1001;;;erlaubnisfrei;31.12.2030;{$this->today()};\r\n"
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
            ."Ohne;Bis;a@example.org;;;;erlaubnisfrei;;{$this->today()};\r\n"
            ."Ohne;Geprüft;b@example.org;;;;erlaubnispflichtig;31.12.2030;;\r\n"
            ."Ohne;Beides;c@example.org;;;;erlaubnisfrei;;;\r\n");

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
            ."Zu;Früh;a@example.org;;;;erlaubnisfrei;{$yesterday};{$this->today()};\r\n"
            ."In;Zukunft;b@example.org;;;;erlaubnisfrei;31.12.2030;{$tomorrow};\r\n"
            ."Gleicher;Tag;c@example.org;;;;erlaubnisfrei;{$this->today()};{$this->today()};\r\n"
            ."Kaputt;Datum;d@example.org;;;;erlaubnisfrei;31.02.2030;{$this->today()};\r\n");

        $this->assertSame(['c@example.org'], array_column($result['rows'], 'email'));
        $this->assertStringContainsString('„Aufsicht gültig bis“ darf nicht vor „Aufsicht geprüft am“ liegen.', $result['errors'][0]);
        $this->assertStringContainsString('„Aufsicht geprüft am“ darf nicht in der Zukunft liegen.', $result['errors'][1]);
        $this->assertStringContainsString('Datum nicht lesbar (Aufsicht gültig bis „31.02.2030“)', $result['errors'][2]);
    }

    public function test_supervision_scope_values()
    {
        $result = app(MemberImport::class)->parse(self::HEADER
            ."Groß;Schreibung;a@example.org;;;;ERLAUBNISFREI;31.12.2030;{$this->today()};\r\n"
            ."Entzug;Versuch;b@example.org;;;;entzogen;31.12.2030;{$this->today()};\r\n"
            ."Nur;Ja;c@example.org;;;;ja;31.12.2030;{$this->today()};\r\n"
            ."Ohne;Umfang;d@example.org;;;;;31.12.2030;{$this->today()};\r\n"
            ."Nicht;Volljährig;e@example.org;;;nein;erlaubnisfrei;31.12.2030;{$this->today()};\r\n");

        $this->assertSame(['exempt'], array_column($result['rows'], 'scope'));
        $this->assertStringContainsString('„Aufsicht“ kennt nur erlaubnispflichtig, erlaubnisfrei oder leer, nicht „entzogen“.', $result['errors'][0]);
        $this->assertStringContainsString('nicht „ja“', $result['errors'][1]);
        $this->assertStringContainsString('gelten nur zusammen mit „Aufsicht“', $result['errors'][2]);
        $this->assertStringContainsString('Eine Aufsicht muss volljährig sein', $result['errors'][3]);
    }

    public function test_old_aufsicht_bis_column_needs_a_scope()
    {
        $result = app(MemberImport::class)->parse("Vorname;Nachname;E-Mail;Aufsicht bis\nErika;Musterfrau;erika@example.org;31.12.2027\n");

        $this->assertSame([], $result['rows']);
        $this->assertStringContainsString('gelten nur zusammen mit „Aufsicht“', $result['errors'][0]);
    }

    public function test_own_registration_is_an_error_four_eyes()
    {
        $admin = User::factory()->admin()->create(['email' => 'vorstand@example.org', 'name' => 'Vorstand Alt']);

        $result = app(MemberImport::class)->parse(self::HEADER
            ."Vorstand;Selbst;vorstand@example.org;;;;erlaubnispflichtig;31.12.2030;{$this->today()};\r\n", $admin);

        $this->assertSame([], $result['rows']);
        $this->assertSame('Zeile 2: Die eigene Registrierung trägt ein anderes Vorstandsmitglied ein.', $result['errors'][0]);

        // Ohne Aufsicht darf die eigene Zeile die Stammdaten ändern.
        $this->upload($admin, "Name;E-Mail\nVorstand Neu;vorstand@example.org\n");
        $this->confirm($admin)->assertSessionHasNoErrors();
        $this->assertSame('Vorstand Neu', $admin->fresh()->name);
        $this->assertSame(0, Qualification::count());
    }

    public function test_own_registration_is_also_refused_when_the_register_changed_after_the_preview()
    {
        $admin = User::factory()->admin()->create(['email' => 'vorstand@example.org']);
        $other = User::factory()->admin()->create();
        Qualification::register($admin, SupervisionScope::Exempt, '2030-12-31', now()->toDateString(), $other);

        // In der Vorschau unverändert, also kein Eintrag und kein Fehler.
        $result = app(MemberImport::class)->parse(self::HEADER
            ."Vorstand;Selbst;vorstand@example.org;;;;erlaubnisfrei;31.12.2030;{$this->today()};\r\n", $admin);
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
        $csv = self::HEADER."Erika;Musterfrau;erika@example.org;1001;;;erlaubnisfrei;31.12.2030;{$this->today()};\r\n";

        $this->upload($admin, $csv);
        $this->confirm($admin, ['confirmed' => true]);
        $this->assertSame(1, Qualification::count());

        $this->upload($admin, $csv);
        $this->actingAs($admin)->get(route('admin.users.import'))
            ->assertInertia(fn ($page) => $page
                ->where('preview.rows.0.exists', true)
                ->where('preview.rows.0.registers', false)
                ->where('preview.rows.0.current_scope', 'exempt'));
        $this->confirm($admin, ['confirmed' => true])->assertSessionHasNoErrors();

        $this->assertSame(1, Qualification::count());
        $this->assertSame(1, AuditEntry::where('action', 'qualification.registered')->count());

        // Ein anderes Ende ist eine neue Prüfung und damit eine neue Zeile.
        $this->upload($admin, self::HEADER."Erika;Musterfrau;erika@example.org;1001;;;erlaubnisfrei;31.12.2031;{$this->today()};\r\n");
        $this->confirm($admin, ['confirmed' => true]);
        $this->assertSame(2, Qualification::count());
        $this->assertSame('2031-12-31', User::where('email', 'erika@example.org')->sole()->supervisor_until->toDateString());
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
}
