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
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Mitgliedersicht und Drucke mit Schüssen (PR 8a, Drucke (b) und (e), Invariante „Drucke (a) und (b)“).
 */
class RecordPrintTest extends TestCase
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
        $this->member = User::factory()->create(['name' => 'Max Muster', 'adult_confirmed_at' => now()]);
    }

    /**
     * Eine Session mit den gegebenen Einträgen, abgezeichnet.
     *
     * @param  list<array{0: WeaponClass, 1: int|null, 2: EntryVia}>  $entries
     * @return list<LogEntry>
     */
    private function signed(array $entries): array
    {
        $session = $this->sessions->open($this->supervisor);
        $participant = $this->sessions->addMember($this->supervisor, $session, $this->member);
        $ids = [];

        foreach ($entries as [$class, $shots, $via]) {
            $actor = $via === EntryVia::OwnDevice ? $this->member : $this->supervisor;
            $ids[] = $this->sessions->stage($actor, $participant, $via, $class, WeaponOwner::Own, $shots ?? 1)['entry']->id;
        }

        $this->sessions->signOff($this->supervisor, $session, $ids);

        return LogEntry::whereIn('session_entry_id', $ids)->orderBy('id')->get()->all();
    }

    private function html(?User $creator = null): string
    {
        return view('pdf.record', ShootingRecordController::pdfData($this->member, today()->subYear(), today(), $creator ?? $this->member, true))->render();
    }

    public function test_extract_has_shots_via_and_confirmer_per_row_and_sums_per_category()
    {
        [$short] = $this->signed([[WeaponClass::Short, 30, EntryVia::OwnDevice], [WeaponClass::Long, 20, EntryVia::Stand]]);
        // Altbestand ohne Schüsse
        DB::table('log_entries')->insert(['user_id' => $this->member->id, 'shot_on' => today()->subMonth()->toDateString(), 'weapon_class' => 'short', 'weapon_owner' => 'club', 'recorded_by_id' => $this->supervisor->id, 'source' => 'stand', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        $html = $this->html();

        $this->assertStringContainsString('<th class="num">Schüsse</th>', $html);
        $this->assertStringContainsString('Erfasst über', $html);
        $this->assertStringContainsString('Mitglied (eigenes Gerät)', $html);
        $this->assertStringContainsString('>Aufsicht<', $html);
        $this->assertStringContainsString('Stand bis zur Umstellung', $html);
        $this->assertStringContainsString('<td class="num">–</td>', $html);
        $this->assertStringContainsString('<td class="num">30</td>', $html);
        // Summe Kurzwaffe: 30 erfasst, eine Zeile ohne Angabe
        $this->assertMatchesRegularExpression('/Kurzwaffe<\/td><td class="num">2<\/td><td class="num">1<\/td><td class="num">30<br><span class="muted">davon ohne Angabe: 1<\/span>/', $html);
        $this->assertSame(3, substr_count($html, 'Hanna Aufsicht'));

        $this->assertSame(30, $short->shots);
    }

    public function test_extract_shows_only_the_correction_with_original_supervisor_and_admin()
    {
        [$row] = $this->signed([[WeaponClass::Short, 30, EntryVia::Stand]]);
        $admin = User::factory()->admin()->create(['name' => 'Petra Vorstand']);
        app(Logbook::class)->correct($admin, $row, WeaponClass::Long, WeaponOwner::Own, null, 25, 'Kategorie falsch erfasst');

        $html = $this->html();

        $this->assertStringContainsString('Hanna Aufsicht, korrigiert von Petra Vorstand: Kategorie falsch erfasst', $html);
        $this->assertStringContainsString('Korrektur; ursprünglich Aufsicht', $html);
        $this->assertStringNotContainsString('Kurzwaffe</td><td>eigene', $html);
        $this->assertStringContainsString('Termine (1)', $html);
    }

    public function test_extract_has_only_valid_rows_never_rejected_withdrawn_or_cancelled()
    {
        $session = $this->sessions->open($this->supervisor);
        $participant = $this->sessions->addMember($this->supervisor, $session, $this->member);
        $rejected = $this->sessions->stage($this->supervisor, $participant, EntryVia::Stand, WeaponClass::Short, WeaponOwner::Own, 11)['entry'];
        $this->sessions->reject($this->supervisor, $rejected, 'falsch');
        $withdrawn = $this->sessions->stage($this->supervisor, $participant, EntryVia::Stand, WeaponClass::Long, WeaponOwner::Own, 22)['entry'];
        $this->sessions->withdraw($this->supervisor, $withdrawn);
        $kept = $this->sessions->stage($this->supervisor, $participant, EntryVia::Stand, WeaponClass::Exempt, WeaponOwner::Own, 33)['entry'];
        $this->sessions->signOff($this->supervisor, $session, [$kept->id]);

        $html = $this->html();

        $this->assertStringContainsString('Termine (1)', $html);
        $this->assertStringContainsString('<td class="num">33</td>', $html);
        $this->assertStringNotContainsString('<td class="num">11</td>', $html);
        $this->assertStringNotContainsString('<td class="num">22</td>', $html);
    }

    public function test_late_entry_names_the_admin_and_the_reason()
    {
        $admin = User::factory()->admin()->create(['name' => 'Petra Vorstand']);
        app(Logbook::class)->lateEntries($admin, [$this->member], today()->subDay()->toDateString(), WeaponClass::Short, WeaponOwner::Own, null, 'Papierliste vom Vortag', 40);

        $html = $this->html();

        $this->assertStringContainsString('Petra Vorstand, Nachtrag: Papierliste vom Vortag', $html);
        $this->assertStringContainsString('<td>Nachtrag</td>', $html);
        $this->assertStringContainsString('<td class="num">40</td>', $html);
    }

    public function test_member_view_lists_shots_via_and_confirmer()
    {
        $this->signed([[WeaponClass::Short, 30, EntryVia::OwnDevice]]);

        $this->actingAs($this->member)->get(route('record.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('entries.0.shots', 30)
                ->where('entries.0.via', 'Mitglied (eigenes Gerät)')
                ->where('entries.0.confirmed_by', 'Hanna Aufsicht')
                ->where('totals.0.shots', 30)
                ->where('totals.0.shots_missing', 0));
    }

    public function test_paper_list_has_a_shots_column_and_the_new_text()
    {
        $html = view('pdf.paper-list', ['club' => 'SV Test', 'clubAddress' => null])->render();

        $this->assertStringContainsString('<th style="width:14mm">Schüsse</th>', $html);
        $this->assertStringContainsString('Mitglieder tragen sich ein, die Aufsicht zeichnet die Liste ab; der Vorstand überträgt sie als Nachtrag', $html);
        $this->assertStringNotContainsString('Niemand trägt sich selbst ein', $html);
        $this->assertStringContainsString('§ 27 Abs. 3 WaffG', $html);
    }

    public function test_pdf_downloads_still_work()
    {
        $this->signed([[WeaponClass::Short, 30, EntryVia::Stand]]);

        $response = $this->actingAs($this->member)->get(route('record.pdf'));
        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
