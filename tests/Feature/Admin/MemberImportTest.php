<?php

namespace Tests\Feature\Admin;

use App\Domain\MemberImport;
use App\Models\User;
use App\Notifications\UserInvited;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MemberImportTest extends TestCase
{
    use RefreshDatabase;

    private const CSV = "\xEF\xBB\xBFVorname;Nachname;E-Mail;Mitgliedsnummer;Eintritt;Aufsicht bis\r\n"
        ."Erika;Musterfrau;Erika@Example.org;1001;01.03.2015;31.12.2027\r\n"
        ."Max;Mustermann;max@example.org;1002;;\r\n"
        .";;keine-mail;;;\r\n"
        ."Doppelt;Erika;erika@example.org;;;\r\n";

    public function test_parse_maps_german_columns_and_reports_errors()
    {
        $result = app(MemberImport::class)->parse(self::CSV);

        $this->assertCount(2, $result['rows']);
        $this->assertSame('Erika Musterfrau', $result['rows'][0]['name']);
        $this->assertSame('erika@example.org', $result['rows'][0]['email']);
        $this->assertSame('2015-03-01', $result['rows'][0]['member_since']);
        $this->assertSame('2027-12-31', $result['rows'][0]['supervisor_until']);
        $this->assertCount(2, $result['errors']);
        $this->assertStringContainsString('Zeile 5', $result['errors'][1]);
    }

    public function test_comma_separated_files_and_windows_encoding_work()
    {
        $csv = mb_convert_encoding("Name,E-Mail\r\nJürgen Größer,juergen@example.org\r\n", 'Windows-1252', 'UTF-8');

        $result = app(MemberImport::class)->parse($csv);

        $this->assertSame('Jürgen Größer', $result['rows'][0]['name']);
    }

    public function test_missing_required_columns_are_reported()
    {
        $result = app(MemberImport::class)->parse("Vorname;Telefon\nErika;123\n");

        $this->assertSame([], $result['rows']);
        $this->assertStringContainsString('E-Mail', $result['errors'][0]);
    }

    public function test_admin_previews_then_imports_with_invitations()
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $existing = User::factory()->create(['email' => 'max@example.org', 'name' => 'Alter Name']);

        $this->actingAs($admin)->post(route('admin.users.import-preview'), [
            'file' => UploadedFile::fake()->createWithContent('mitglieder.csv', self::CSV),
        ])->assertRedirect(route('admin.users.import'));

        $this->assertFalse(User::where('email', 'erika@example.org')->exists());

        $this->actingAs($admin)->get(route('admin.users.import'))
            ->assertInertia(fn ($page) => $page->has('preview.rows', 2)->where('preview.rows.1.exists', true));

        $this->actingAs($admin)->post(route('admin.users.import-store'), ['send_invitations' => true])
            ->assertRedirect(route('admin.users.index'));

        $erika = User::where('email', 'erika@example.org')->sole();
        $this->assertSame('1001', $erika->member_number);
        // „Aufsicht bis“ wird nicht ins Register übernommen.
        $this->assertFalse($erika->canSupervise());
        $this->assertSame(0, $erika->qualifications()->count());
        $this->assertSame('Max Mustermann', $existing->fresh()->name);
        Notification::assertSentTo($erika, UserInvited::class);
        Notification::assertNotSentTo($existing, UserInvited::class);
    }

    public function test_members_cannot_import()
    {
        $this->actingAs(User::factory()->create())->get(route('admin.users.import'))->assertForbidden();
    }

    public function test_template_download()
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.users.import-template'))
            ->assertOk()
            ->assertDownload('mitglieder-vorlage.csv');
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

        $this->actingAs($admin)->post(route('admin.users.import-preview'), [
            'file' => UploadedFile::fake()->createWithContent('mitglieder.csv', $csv),
        ]);
        $this->actingAs($admin)->post(route('admin.users.import-store'), ['send_invitations' => true])->assertRedirect();

        $new = User::where('member_number', '2001')->firstOrFail();
        $this->assertNull($new->email);
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
}
