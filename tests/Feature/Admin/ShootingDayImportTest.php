<?php

namespace Tests\Feature\Admin;

use App\Domain\ShootingDayImport;
use App\Models\AuditEntry;
use App\Models\Range;
use App\Models\ShootingDay;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Support\SessionKey;
use Tests\TestCase;

class ShootingDayImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = "Datum;Beginn;Ende;Titel;Stände;Gäste erlaubt;Hinweise\n";

    private Range $pistol;

    private Range $rifle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00'));
        $this->pistol = Range::factory()->create(['name' => '25 m Kurzwaffe', 'position' => 1]);
        $this->rifle = Range::factory()->create(['name' => '50 m Gewehr', 'position' => 2]);
    }

    /**
     * @return array{rows: list<array<string, mixed>>, errors: list<string>, skipped: int}
     */
    private function parse(string $csv): array
    {
        return app(ShootingDayImport::class)->parse($csv);
    }

    public function test_unclosed_quote_rejects_the_whole_file()
    {
        $result = $this->parse(self::HEADER
            ."07.11.2026;10:00;14:00;Training;25 m Kurzwaffe;ja;\"Bitte Gehörschutz mitbringen\n"
            ."14.11.2026;10:00;14:00;Training;25 m Kurzwaffe;ja;\n"
            ."21.11.2026;10:00;14:00;Training;50 m Gewehr;ja;\n");

        // Ohne die Prüfung stünden die beiden letzten Termine als Text im Hinweis des ersten.
        $this->assertSame([], $result['rows']);
        $this->assertSame(['Zeile 2: Anführungszeichen nicht geschlossen – bitte die Datei prüfen. Ein einzelnes " bitte verdoppeln oder entfernen.'], $result['errors']);
    }

    public function test_parse_reads_the_template_columns()
    {
        $result = $this->parse("\xEF\xBB\xBF".self::HEADER
            ."07.11.2026;10:00;14:00;Training;25 m Kurzwaffe|50 m Gewehr;ja;\n"
            ."2026-11-21;14:00;18:00;Vereinsmeisterschaft;50 m gewehr;nein;Anmeldung bis Donnerstag\n");

        $this->assertSame([], $result['errors']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame([
            'line' => 2,
            'date' => '2026-11-07',
            'starts_at' => '10:00',
            'ends_at' => '14:00',
            'title' => 'Training',
            'notes' => null,
            'guests_allowed' => true,
            'ranges' => [$this->pistol->id, $this->rifle->id],
            'range_names' => ['25 m Kurzwaffe', '50 m Gewehr'],
        ], $result['rows'][0]);
        $this->assertSame('2026-11-21', $result['rows'][1]['date']);
        $this->assertFalse($result['rows'][1]['guests_allowed']);
        $this->assertSame([$this->rifle->id], $result['rows'][1]['ranges']);
        $this->assertSame('Anmeldung bis Donnerstag', $result['rows'][1]['notes']);
    }

    public function test_comma_delimiter_windows_encoding_and_header_case()
    {
        $csv = mb_convert_encoding("DATUM,beginn,Ende,Titel,STÄNDE,Gäste erlaubt\r\n"
            ."7.11.2026,9:00,12:00:00,Jugend,\"25 m Kurzwaffe, 50 m Gewehr\",\r\n"
            ."14.11.2026,9.30,12:00,Übung,,J\r\n", 'Windows-1252', 'UTF-8');

        $result = $this->parse($csv);

        $this->assertSame([], $result['errors']);
        $this->assertCount(2, $result['rows']);
        $this->assertSame(['2026-11-07', '09:00', '12:00'], [$result['rows'][0]['date'], $result['rows'][0]['starts_at'], $result['rows'][0]['ends_at']]);
        $this->assertSame([$this->pistol->id, $this->rifle->id], $result['rows'][0]['ranges']);
        $this->assertSame('Übung', $result['rows'][1]['title']);
        $this->assertSame('09:30', $result['rows'][1]['starts_at']);
        // Leere Stände = alle Stände in Betrieb.
        $this->assertSame([$this->pistol->id, $this->rifle->id], $result['rows'][1]['ranges']);
    }

    public function test_quoted_notes_with_line_breaks_keep_line_numbers()
    {
        $result = $this->parse(self::HEADER
            ."07.11.2026;10:00;14:00;;;;\"Erste Zeile\nzweite Zeile\"\n"
            ."08.11.2026;14:00;12:00;;;;\n");

        $this->assertSame("Erste Zeile\nzweite Zeile", $result['rows'][0]['notes']);
        $this->assertSame(['Zeile 4: Das Ende muss nach dem Beginn liegen.'], $result['errors']);
    }

    public function test_missing_columns_and_empty_files_are_reported()
    {
        $this->assertSame(['Es fehlen Spalten: „Beginn“, „Ende“. Bitte die Vorlage verwenden.'], $this->parse("Datum;Titel\n07.11.2026;Training\n")['errors']);
        $this->assertSame(['Die Datei braucht eine Kopfzeile und mindestens einen Termin.'], $this->parse(self::HEADER)['errors']);
        $this->assertSame(['Die Datei braucht eine Kopfzeile und mindestens einen Termin.'], $this->parse('')['errors']);
    }

    public function test_too_many_rows_are_rejected()
    {
        $csv = self::HEADER.str_repeat("07.11.2026;10:00;14:00;;;;\n", ShootingDayImport::MAX_ROWS + 1);

        $result = $this->parse($csv);

        $this->assertSame([], $result['rows']);
        $this->assertStringContainsString('mehr als 500 Termine', $result['errors'][0]);
    }

    public function test_every_row_is_validated_like_the_form()
    {
        Range::factory()->create(['name' => 'Bogenwiese', 'active' => false]);

        $result = $this->parse(self::HEADER
            .";10:00;14:00;;;;\n"
            ."32.11.2026;10:00;14:00;;;;\n"
            ."01.11.2026;10:00;14:00;;;;\n"
            ."03.11.2027;10:00;14:00;;;;\n"
            ."07.11.2026;;14:00;;;;\n"
            ."07.11.2026;10 Uhr;25:00;;;;\n"
            ."07.11.2026;14:00;14:00;;;;\n"
            .'07.11.2026;10:00;14:00;'.str_repeat('x', 101).";;;\n"
            .'07.11.2026;10:00;14:00;;;;'.str_repeat('x', 2001)."\n"
            ."07.11.2026;10:00;14:00;;Luftgewehr 10 m;;\n"
            ."07.11.2026;10:00;14:00;;Bogenwiese;;\n"
            ."07.11.2026;10:00;14:00;;|;;\n"
            ."07.11.2026;10:00;14:00;;;vielleicht;\n"
            ."02.11.2026;10:00;14:00;;;;\n"
            ."02.11.2027;10:00;14:00;;;;\n");

        $this->assertSame([
            'Zeile 2: Datum fehlt.',
            'Zeile 3: Datum „32.11.2026“ nicht lesbar, bitte als TT.MM.JJJJ angeben.',
            'Zeile 4: Der 01.11.2026 liegt in der Vergangenheit.',
            'Zeile 5: Der 03.11.2027 liegt mehr als ein Jahr im Voraus.',
            'Zeile 6: Beginn fehlt.',
            'Zeile 7: Beginn „10 Uhr“ nicht lesbar, bitte als HH:MM angeben. Ende „25:00“ nicht lesbar, bitte als HH:MM angeben.',
            'Zeile 8: Das Ende muss nach dem Beginn liegen.',
            'Zeile 9: Der Titel ist länger als 100 Zeichen.',
            'Zeile 10: Die Hinweise sind länger als 2000 Zeichen.',
            'Zeile 11: Stand „Luftgewehr 10 m“ gibt es nicht.',
            'Zeile 12: Stand „Bogenwiese“ ist außer Betrieb.',
            'Zeile 13: Keine Stände angegeben.',
            'Zeile 14: „Gäste erlaubt“ ist „vielleicht“, bitte ja oder nein angeben.',
        ], $result['errors']);
        $this->assertSame(13, $result['skipped']);
        // Heute und genau ein Jahr im Voraus sind erlaubt.
        $this->assertSame(['2026-11-02', '2027-11-02'], array_column($result['rows'], 'date'));
    }

    public function test_rows_need_a_stand_in_operation()
    {
        Range::query()->update(['active' => false]);

        $this->assertSame(['Zeile 2: Es ist kein Stand in Betrieb.'], $this->parse(self::HEADER."07.11.2026;10:00;14:00;;;;\n")['errors']);
    }

    public function test_overlaps_within_the_file_are_reported()
    {
        $result = $this->parse(self::HEADER
            ."07.11.2026;10:00;14:00;;25 m Kurzwaffe;;\n"
            ."07.11.2026;13:00;16:00;;25 m Kurzwaffe|50 m Gewehr;;\n"
            ."07.11.2026;13:00;16:00;;50 m Gewehr;;\n"
            ."07.11.2026;14:00;16:00;;25 m Kurzwaffe;;\n"
            ."14.11.2026;10:00;14:00;;25 m Kurzwaffe;;\n");

        $this->assertSame(['Zeile 3: Überschneidet sich mit Zeile 2 (selber Tag, selber Stand).'], $result['errors']);
        // Anderer Stand, direkt anschließend oder anderer Tag: kein Konflikt.
        $this->assertSame([2, 4, 5, 6], array_column($result['rows'], 'line'));
    }

    public function test_overlaps_with_existing_days_are_reported()
    {
        ShootingDay::factory()->withRange($this->pistol)->create(['date' => '2026-11-07', 'starts_at' => '12:00', 'ends_at' => '15:00']);
        $cancelled = ShootingDay::factory()->withRange($this->pistol)->create(['date' => '2026-11-14', 'starts_at' => '10:00', 'ends_at' => '14:00']);
        $cancelled->forceFill(['cancelled_at' => now()])->save();

        $result = $this->parse(self::HEADER
            ."07.11.2026;10:00;14:00;;25 m Kurzwaffe;;\n"
            ."07.11.2026;10:00;14:00;;50 m Gewehr;;\n"
            ."14.11.2026;10:00;14:00;;25 m Kurzwaffe;;\n");

        $this->assertSame(['Zeile 2: Am 07.11.2026 gibt es schon einen Schießtag (12:00–15:00 Uhr) auf denselben Ständen.'], $result['errors']);
        $this->assertSame([3, 4], array_column($result['rows'], 'line'));
    }

    public function test_admin_previews_then_imports()
    {
        $admin = User::factory()->admin()->create();
        $csv = self::HEADER
            ."07.11.2026;10:00;14:00;Training;25 m Kurzwaffe|50 m Gewehr;ja;\n"
            ."21.11.2026;14:00;18:00;Vereinsmeisterschaft;50 m Gewehr;nein;Anmeldung bis Donnerstag\n"
            ."01.01.2020;10:00;14:00;;;;\n";

        $this->actingAs($admin)->post(route('admin.shooting-days.import-preview'), [
            'file' => UploadedFile::fake()->createWithContent('termine.csv', $csv),
        ])->assertRedirect(route('admin.shooting-days.import'));

        $this->assertSame(0, ShootingDay::count());

        $this->actingAs($admin)->get(route('admin.shooting-days.import'))
            ->assertInertia(fn ($page) => $page->component('admin/shooting-days/Import')
                ->has('preview.rows', 2)
                ->where('preview.skipped', 1)
                ->where('preview.errors.0', 'Zeile 4: Der 01.01.2020 liegt in der Vergangenheit.')
                ->where('preview.rows.0.range_names', ['25 m Kurzwaffe', '50 m Gewehr'])
                ->where('ranges', ['25 m Kurzwaffe', '50 m Gewehr']));

        $response = $this->actingAs($admin)->post(route('admin.shooting-days.import-store'))
            ->assertRedirect(route('admin.shooting-days.index'));

        $this->assertSame('2 Schießtage angelegt.', $response->getSession()->get(SessionKey::FLASH_DATA)['toast']['message']);

        $days = ShootingDay::with('ranges')->orderBy('date')->get();
        $this->assertCount(2, $days);
        $this->assertSame('Training', $days[0]->title);
        $this->assertSame([$this->pistol->id, $this->rifle->id], $days[0]->ranges->pluck('id')->sort()->values()->all());
        $this->assertTrue($days[0]->guests_allowed);
        $this->assertSame('14:00', substr($days[1]->starts_at, 0, 5));
        $this->assertSame('18:00', substr($days[1]->ends_at, 0, 5));
        $this->assertFalse($days[1]->guests_allowed);
        $this->assertSame([$this->rifle->id], $days[1]->ranges->pluck('id')->all());
        $this->assertSame('Anmeldung bis Donnerstag', $days[1]->notes);

        $audit = AuditEntry::where('action', 'shooting_day.imported')->sole();
        $this->assertSame('CSV-Import: 2 Schießtage angelegt (07.11.2026 bis 21.11.2026)', $audit->summary);
        $this->assertSame($admin->id, $audit->actor_id);

        // Die Vorschau ist verbraucht: ein zweites Absenden legt nichts doppelt an.
        $this->actingAs($admin)->post(route('admin.shooting-days.import-store'))->assertRedirect(route('admin.shooting-days.import'));
        $this->assertSame(2, ShootingDay::count());
    }

    public function test_rows_changed_since_the_preview_are_skipped()
    {
        $admin = User::factory()->admin()->create();
        $csv = self::HEADER
            ."07.11.2026;10:00;14:00;;25 m Kurzwaffe;;\n"
            ."14.11.2026;10:00;14:00;;50 m Gewehr;;\n"
            ."21.11.2026;10:00;14:00;;25 m Kurzwaffe;;\n";

        $this->actingAs($admin)->post(route('admin.shooting-days.import-preview'), [
            'file' => UploadedFile::fake()->createWithContent('termine.csv', $csv),
        ]);

        // Inzwischen von Hand angelegt bzw. Stand stillgelegt.
        ShootingDay::factory()->withRange($this->pistol)->create(['date' => '2026-11-07', 'starts_at' => '09:00', 'ends_at' => '11:00']);
        $this->rifle->update(['active' => false]);

        $response = $this->actingAs($admin)->post(route('admin.shooting-days.import-store'));

        $this->assertSame(
            'Schießtag angelegt. 2 Zeilen ausgelassen, weil sich die Daten seit der Vorschau geändert haben.',
            $response->getSession()->get(SessionKey::FLASH_DATA)['toast']['message'],
        );
        $this->assertSame('warning', $response->getSession()->get(SessionKey::FLASH_DATA)['toast']['type']);
        $this->assertSame(['2026-11-07', '2026-11-21'], ShootingDay::orderBy('date')->get()->map(fn ($day) => $day->date->toDateString())->all());
        $this->assertStringEndsWith(', 2 ausgelassen', AuditEntry::where('action', 'shooting_day.imported')->sole()->summary);
    }

    public function test_without_valid_rows_nothing_is_created()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.shooting-days.import-preview'), [
            'file' => UploadedFile::fake()->createWithContent('termine.csv', self::HEADER."01.01.2020;10:00;14:00;;;;\n"),
        ]);
        $this->actingAs($admin)->get(route('admin.shooting-days.import'))
            ->assertInertia(fn ($page) => $page->has('preview.rows', 0)->has('preview.errors', 1));

        $this->actingAs($admin)->post(route('admin.shooting-days.import-store'))->assertRedirect(route('admin.shooting-days.import'));

        $this->assertSame(0, ShootingDay::count());
        $this->assertSame(0, AuditEntry::where('action', 'shooting_day.imported')->count());
    }

    public function test_cancel_clears_the_preview()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.shooting-days.import-preview'), [
            'file' => UploadedFile::fake()->createWithContent('termine.csv', self::HEADER."07.11.2026;10:00;14:00;;;;\n"),
        ]);
        $this->actingAs($admin)->delete(route('admin.shooting-days.import-cancel'))->assertRedirect(route('admin.shooting-days.import'));

        $this->actingAs($admin)->get(route('admin.shooting-days.import'))->assertInertia(fn ($page) => $page->where('preview', null));
        $this->actingAs($admin)->post(route('admin.shooting-days.import-store'))->assertRedirect(route('admin.shooting-days.import'));
        $this->assertSame(0, ShootingDay::count());
    }

    public function test_the_upload_must_be_a_small_csv_file()
    {
        $admin = User::factory()->admin()->create();
        $route = route('admin.shooting-days.import-preview');

        $this->actingAs($admin)->post($route, [])->assertSessionHasErrors('file');
        $this->actingAs($admin)->post($route, ['file' => UploadedFile::fake()->create('termine.pdf', 10, 'application/pdf')])->assertSessionHasErrors('file');
        $this->actingAs($admin)->post($route, ['file' => UploadedFile::fake()->create('termine.csv', 3000, 'text/csv')])->assertSessionHasErrors('file');
    }

    public function test_template_download()
    {
        $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.shooting-days.import-template'))
            ->assertOk()
            ->assertDownload('schiesstage-vorlage.csv')
            ->assertHeader('Content-Type', 'text/csv; charset=utf-8');

        $content = $response->streamedContent();
        $lines = explode("\r\n", trim(substr($content, 3)));

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertSame('Datum;Beginn;Ende;Titel;Stände;Gäste erlaubt;Hinweise', $lines[0]);
        $this->assertCount(3, $lines);
        // Beispieltermine: 1. und 3. Samstag im nächsten Monat.
        $this->assertStringStartsWith('05.12.2026;10:00;14:00;Training;25 m Kurzwaffe|50 m Gewehr;ja;', $lines[1]);
        $this->assertStringStartsWith('19.12.2026;', $lines[2]);

        // Die Vorlage selbst lässt sich mit den Demo-Ständen importieren.
        $result = $this->parse($content);
        $this->assertSame([], $result['errors']);
        $this->assertCount(2, $result['rows']);
    }

    public function test_members_cannot_import()
    {
        $member = User::factory()->create();

        $this->actingAs($member)->get(route('admin.shooting-days.import'))->assertForbidden();
        $this->actingAs($member)->get(route('admin.shooting-days.import-template'))->assertForbidden();
        $this->actingAs($member)->post(route('admin.shooting-days.import-preview'), [
            'file' => UploadedFile::fake()->createWithContent('termine.csv', self::HEADER."07.11.2026;10:00;14:00;;;;\n"),
        ])->assertForbidden();
        $this->actingAs($member)->post(route('admin.shooting-days.import-store'))->assertForbidden();
        $this->actingAs($member)->delete(route('admin.shooting-days.import-cancel'))->assertForbidden();

        $this->assertSame(0, ShootingDay::count());
    }
}
