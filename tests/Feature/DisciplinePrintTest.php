<?php

namespace Tests\Feature;

use App\Domain\Logbook;
use App\Domain\Sessions;
use App\Enums\EntryVia;
use App\Enums\WeaponClass;
use App\Enums\WeaponOwner;
use App\Http\Controllers\ShootingRecordController;
use App\Models\LogEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Teilnahmenachweis je Disziplin (a), PR 8b: Teilnahme, kein Urteil (E9).
 */
class DisciplinePrintTest extends TestCase
{
    use RefreshDatabase;

    private Sessions $sessions;

    private User $supervisor;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(18, 0));
        $this->sessions = app(Sessions::class);
        $this->supervisor = User::factory()->supervisor()->create(['name' => 'Hanna Aufsicht']);
        $this->member = User::factory()->create(['name' => 'Max Muster', 'member_number' => '0815', 'adult_confirmed_at' => now()]);
    }

    private function catalog(): void
    {
        config(['verein.discipline_catalog' => 'test', 'disziplinen.test' => ['BDS:1.10' => 'Pistole', 'BDS:1.20' => 'Revolver']]);
    }

    /**
     * @param  list<array{0: string, 1: int, 2: EntryVia}>  $entries  Disziplin, Schüsse, erfasst über
     * @return list<LogEntry>
     */
    private function signed(array $entries): array
    {
        $session = $this->sessions->open($this->supervisor);
        $participant = $this->sessions->addMember($this->supervisor, $session, $this->member);
        $ids = [];

        foreach ($entries as [$discipline, $shots, $via]) {
            $actor = $via === EntryVia::OwnDevice ? $this->member : $this->supervisor;
            $ids[] = $this->sessions->stage($actor, $participant, $via, WeaponClass::Short, WeaponOwner::Own, $shots, $discipline)['entry']->id;
        }

        $this->sessions->signOff($this->supervisor, $session, $ids);

        return LogEntry::whereIn('session_entry_id', $ids)->orderBy('id')->get()->all();
    }

    private function html(?string $discipline = 'BDS:1.10', bool $forInformation = false): string
    {
        return view('pdf.discipline', ShootingRecordController::disciplineData($this->member, $discipline, today()->subMonths(24), today(), $this->member, $forInformation))->render();
    }

    public function test_only_valid_rows_of_the_chosen_discipline_with_confirmer_and_via()
    {
        $this->catalog();
        $this->signed([['BDS:1.10', 31, EntryVia::OwnDevice], ['BDS:1.20', 42, EntryVia::Stand]]);
        // verfallene Session: Eintrag bleibt offen und erscheint nicht
        $this->travel(1)->day();
        $session = $this->sessions->open($this->supervisor);
        $this->sessions->stage($this->supervisor, $this->sessions->addMember($this->supervisor, $session, $this->member), EntryVia::Stand, WeaponClass::Long, WeaponOwner::Own, 53, 'BDS:1.10');
        $this->travel(1)->day();

        $html = $this->html();

        $this->assertStringContainsString('Teilnahmenachweis aus der Schießkladde', $html);
        $this->assertStringContainsString('BDS:1.10</strong> Pistole', $html);
        $this->assertStringContainsString('Mitgliedsnummer 0815', $html);
        $this->assertStringContainsString('<td class="num">31</td>', $html);
        $this->assertStringNotContainsString('<td class="num">42</td>', $html);
        $this->assertStringNotContainsString('<td class="num">53</td>', $html);
        $this->assertStringContainsString('<td>Mitglied (eigenes Gerät)</td>', $html);
        $this->assertStringContainsString('<td>Hanna Aufsicht</td>', $html);
        $this->assertStringContainsString('Teilnahme an 1 Tag', $html);
        $this->assertStringContainsString('stornierte und nicht abgezeichnete', mb_strtolower($html));
        $this->assertStringContainsString('bescheinigen muss der Verband', $html);
        $this->assertStringContainsString('zur Vorlage beim Verband', $html);
        $this->assertStringContainsString('zur Information des Mitglieds', $this->html(forInformation: true));
    }

    public function test_no_judging_words()
    {
        $this->catalog();
        $this->signed([['BDS:1.10', 31, EntryVia::Stand]]);

        $html = mb_strtolower($this->html());

        foreach (['erfüllt', 'bedürfnis', 'glaubhaft', 'regelmäßig', 'erfolgreich', 'absolviert'] as $word) {
            $this->assertStringNotContainsString($word, $html, $word);
        }
    }

    public function test_correction_names_the_original_supervisor()
    {
        $this->catalog();
        [$row] = $this->signed([['BDS:1.10', 31, EntryVia::Stand]]);
        $admin = User::factory()->admin()->create(['name' => 'Petra Vorstand']);
        app(Logbook::class)->correct($admin, $row, WeaponClass::Short, WeaponOwner::Club, 'BDS:1.10', 30, 'Vereinswaffe laut Aufsicht');

        $html = $this->html();

        $this->assertStringContainsString('Hanna Aufsicht, korrigiert von Petra Vorstand: Vereinswaffe laut Aufsicht', $html);
        $this->assertSame(1, substr_count($html, '<td class="num">'));
    }

    public function test_without_catalog_not_available()
    {
        $html = $this->html(null);

        $this->assertStringContainsString('nicht verfügbar, solange kein Disziplinkatalog gewählt ist', $html);

        $this->actingAs($this->member)->get(route('record.discipline-pdf', ['discipline' => 'BDS:1.10']))->assertNotFound();
        $this->actingAs($this->member)->get(route('record.index'))
            ->assertInertia(fn (Assert $page) => $page->where('catalog', false)->where('disciplines', []));
    }

    public function test_routes_for_member_and_admin_deliver_pdfs()
    {
        $this->catalog();
        $this->signed([['BDS:1.10', 31, EntryVia::Stand]]);

        $own = $this->actingAs($this->member)->get(route('record.discipline-pdf', ['discipline' => 'BDS:1.10']));
        $own->assertOk();
        $this->assertStringStartsWith('%PDF', $own->getContent());
        $this->actingAs($this->member)->get(route('record.discipline-pdf', ['discipline' => 'XYZ:9']))->assertNotFound();

        $admin = User::factory()->admin()->create();
        $response = $this->actingAs($admin)->get(route('admin.record.member-discipline-pdf', ['user' => $this->member, 'discipline' => 'BDS:1.10']));
        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $this->actingAs($this->member)->get(route('admin.record.member-discipline-pdf', ['user' => $this->member, 'discipline' => 'BDS:1.10']))->assertForbidden();

        $this->actingAs($this->member)->get(route('record.index'))
            ->assertInertia(fn (Assert $page) => $page->where('catalog', true)->where('disciplines.0.key', 'BDS:1.10')->where('disciplines.0.days', 1));
        $this->actingAs($admin)->get(route('admin.users.edit', $this->member))
            ->assertInertia(fn (Assert $page) => $page->where('catalog', true)->where('disciplines.0.label', 'BDS:1.10 Pistole'));
    }
}
