<?php

namespace Tests\Feature;

use App\Domain\Logbook;
use App\Domain\Sessions;
use App\Enums\EntryVia;
use App\Enums\LogSource;
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
 * Storno und Korrektur nach dem Abzeichnen in der Sicht des Mitglieds (Rollen-Rundgang K8):
 * „Heute schießen“ zeigt den Stand der Kladde-Zeile, die eigene Kladde zeigt Stornos durchgestrichen,
 * ohne Zählung. Der Auszug bleibt bei den gültigen Einträgen (K13).
 */
class RecordStateViewTest extends TestCase
{
    use RefreshDatabase;

    private Sessions $sessions;

    private Logbook $logbook;

    private User $supervisor;

    private User $member;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(18, 0));
        $this->sessions = app(Sessions::class);
        $this->logbook = app(Logbook::class);
        $this->supervisor = User::factory()->supervisor()->create(['name' => 'Hanna Aufsicht']);
        $this->member = User::factory()->create(['adult_confirmed_at' => now()]);
        $this->admin = User::factory()->admin()->create(['name' => 'Petra Vorstand']);
    }

    /**
     * Am eigenen Gerät erfasst, von der Aufsicht abgezeichnet.
     */
    private function signedRow(WeaponClass $class = WeaponClass::Short, int $shots = 30, ?string $discipline = null): LogEntry
    {
        $session = $this->sessions->open($this->supervisor);
        $participant = $this->sessions->addMember($this->supervisor, $session, $this->member);
        $entry = $this->sessions->stage($this->member, $participant, EntryVia::OwnDevice, $class, WeaponOwner::Own, $shots, $discipline)['entry'];
        $this->sessions->signOff($this->supervisor, $session, [$entry->id]);

        return LogEntry::where('session_entry_id', $entry->id)->firstOrFail();
    }

    public function test_today_shows_a_cancelled_entry_as_cancelled_with_reason_and_admin()
    {
        $row = $this->signedRow();

        $this->actingAs($this->member)->get(route('today.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('participation.entries.0.status_label', 'abgezeichnet')
                ->where('participation.entries.0.record', null));

        $this->logbook->cancel($this->admin, $row, 'Fehleingabe am Handy');

        $this->actingAs($this->member)->get(route('today.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('participation.entries', 1)
                ->where('participation.entries.0.status', 'signed')
                ->where('participation.entries.0.status_label', 'storniert')
                ->where('participation.entries.0.record.state', 'cancelled')
                ->where('participation.entries.0.record.reason', 'Fehleingabe am Handy')
                ->where('participation.entries.0.record.by', 'Petra Vorstand')
                ->where('participation.entries.0.can_withdraw', false));
    }

    public function test_today_shows_the_corrected_values_marked_as_correction()
    {
        $row = $this->signedRow(WeaponClass::Short, 30);

        $this->logbook->correct($this->admin, $row, WeaponClass::Long, WeaponOwner::Club, null, 25, 'Kategorie falsch erfasst');

        $this->actingAs($this->member)->get(route('today.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('participation.entries', 1)
                // erfasst war Kurzwaffe; die Vorstufe bleibt, wie sie war
                ->where('participation.entries.0.weapon_class', 'Kurzwaffe')
                ->where('participation.entries.0.shots', 30)
                ->where('participation.entries.0.status_label', 'abgezeichnet')
                ->where('participation.entries.0.record.state', 'corrected')
                ->where('participation.entries.0.record.weapon_class', 'Langwaffe')
                ->where('participation.entries.0.record.weapon_owner', 'Verein')
                ->where('participation.entries.0.record.shots', 25)
                ->where('participation.entries.0.record.reason', 'Kategorie falsch erfasst')
                ->where('participation.entries.0.record.by', 'Petra Vorstand'));
    }

    public function test_today_follows_the_correction_chain_to_a_later_storno()
    {
        $row = $this->signedRow();
        $correction = $this->logbook->correct($this->admin, $row, WeaponClass::Long, WeaponOwner::Own, null, 30, 'Kategorie falsch erfasst');

        // E3: wer korrigiert hat, storniert die Korrektur nicht selbst
        $second = User::factory()->admin()->create(['name' => 'Karl Kassenwart']);
        $this->logbook->cancel($second, $correction, 'doppelt erfasst');

        $this->actingAs($this->member)->get(route('today.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('participation.entries.0.status_label', 'storniert')
                ->where('participation.entries.0.record.state', 'cancelled')
                ->where('participation.entries.0.record.reason', 'doppelt erfasst')
                ->where('participation.entries.0.record.by', 'Karl Kassenwart'));
    }

    /**
     * Rollen-Rundgang p3: Das Mitglied ist nach einer Übergabe in zwei Sessions desselben Tages aktiviert
     * und erfasst in beiden dasselbe. Die zweite Session führt den Eintrag beim Abzeichnen mit der Zeile der
     * ersten zusammen ({created: 0, merged: 1}); er hat keine eigene Kladde-Zeile.
     *
     * @return array{0: LogEntry, 1: User} die Zeile aus der ersten Session und die zweite Aufsicht
     */
    private function mergedIntoFirstSession(?string $discipline = null, ?callable $beforeSecondSignOff = null): array
    {
        $first = $this->sessions->open($this->supervisor);
        $p1 = $this->sessions->addMember($this->supervisor, $first, $this->member);
        $e1 = $this->sessions->stage($this->member, $p1, EntryVia::OwnDevice, WeaponClass::Short, WeaponOwner::Own, 30, $discipline)['entry'];

        $this->travel(5)->minutes();
        $other = User::factory()->supervisor()->create(['name' => 'Bernd Aufsicht']);
        $second = $this->sessions->open($other);
        $p2 = $this->sessions->addMember($other, $second, $this->member);
        $e2 = $this->sessions->stage($this->member, $p2, EntryVia::OwnDevice, WeaponClass::Short, WeaponOwner::Own, 30, $discipline)['entry'];

        $this->travel(5)->minutes();
        $this->assertSame(['created' => 1, 'merged' => 0], $this->sessions->signOff($this->supervisor, $first, [$e1->id]));

        if ($beforeSecondSignOff !== null) {
            $beforeSecondSignOff();
        }

        $this->travel(5)->minutes();
        $this->assertSame(['created' => 0, 'merged' => 1], $this->sessions->signOff($other, $second, [$e2->id]));
        $this->assertNull(LogEntry::where('session_entry_id', $e2->id)->first(), 'Der zusammengeführte Eintrag hat keine eigene Zeile.');
        $this->travel(5)->minutes();

        return [LogEntry::where('session_entry_id', $e1->id)->firstOrFail(), $other];
    }

    public function test_today_shows_the_storno_of_the_row_a_signed_entry_was_merged_into()
    {
        [$row] = $this->mergedIntoFirstSession();

        $this->actingAs($this->member)->get(route('today.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('participation.supervisor', 'Bernd Aufsicht')
                ->has('participation.entries', 1)
                ->where('participation.entries.0.status_label', 'abgezeichnet')
                ->where('participation.entries.0.record', null));

        $this->logbook->cancel($this->admin, $row, 'doppelt erfasst');

        $this->actingAs($this->member)->get(route('today.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('participation.supervisor', 'Bernd Aufsicht')
                ->has('participation.entries', 1)
                ->where('participation.entries.0.status', 'signed')
                ->where('participation.entries.0.status_label', 'storniert')
                ->where('participation.entries.0.record.state', 'cancelled')
                ->where('participation.entries.0.record.reason', 'doppelt erfasst')
                ->where('participation.entries.0.record.by', 'Petra Vorstand'));
    }

    public function test_today_shows_the_correction_of_the_row_a_signed_entry_was_merged_into()
    {
        [$row] = $this->mergedIntoFirstSession();

        $this->logbook->correct($this->admin, $row, WeaponClass::Long, WeaponOwner::Club, null, 25, 'Kategorie falsch erfasst');

        $this->actingAs($this->member)->get(route('today.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('participation.entries.0.weapon_class', 'Kurzwaffe')
                ->where('participation.entries.0.status_label', 'abgezeichnet')
                ->where('participation.entries.0.record.state', 'corrected')
                ->where('participation.entries.0.record.weapon_class', 'Langwaffe')
                ->where('participation.entries.0.record.weapon_owner', 'Verein')
                ->where('participation.entries.0.record.shots', 25)
                ->where('participation.entries.0.record.by', 'Petra Vorstand'));
    }

    /**
     * Ändert die Korrektur nur die Schusszahl, hat die neue Zeile denselben Schlüssel. Sie entstand nach
     * dem Abzeichnen; maßgeblich bleibt die Zeile von damals und damit „Korrektur“, nicht „abgezeichnet“.
     */
    public function test_a_correction_with_the_same_key_still_shows_as_correction()
    {
        [$row] = $this->mergedIntoFirstSession();

        $this->logbook->correct($this->admin, $row, WeaponClass::Short, WeaponOwner::Own, null, 40, 'Schusszahl falsch erfasst');

        $this->actingAs($this->member)->get(route('today.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('participation.entries.0.record.state', 'corrected')
                ->where('participation.entries.0.record.weapon_class', 'Kurzwaffe')
                ->where('participation.entries.0.record.shots', 40));
    }

    /**
     * Ein späterer Nachtrag mit demselben Schlüssel ist eine andere Zeile: Der Storno bleibt sichtbar.
     */
    public function test_a_later_late_entry_with_the_same_key_does_not_hide_the_storno()
    {
        [$row] = $this->mergedIntoFirstSession();

        $this->logbook->cancel($this->admin, $row, 'doppelt erfasst');
        $this->travel(5)->minutes();
        $this->logbook->lateEntries($this->admin, [$this->member], today()->toDateString(), WeaponClass::Short, WeaponOwner::Own, null, 'Papierliste von heute', 30);
        $this->assertSame(1, LogEntry::query()->active()->where('user_id', $this->member->id)->count());

        $this->actingAs($this->member)->get(route('today.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('participation.entries.0.status_label', 'storniert')
                ->where('participation.entries.0.record.state', 'cancelled')
                ->where('participation.entries.0.record.reason', 'doppelt erfasst'));
    }

    /**
     * Eine vor dem Abzeichnen stornierte Zeile ist nicht die, in die zusammengeführt wurde.
     */
    public function test_a_row_cancelled_before_the_sign_off_is_not_the_merge_target()
    {
        $first = $this->sessions->open($this->supervisor);
        $p1 = $this->sessions->addMember($this->supervisor, $first, $this->member);
        $e1 = $this->sessions->stage($this->member, $p1, EntryVia::OwnDevice, WeaponClass::Short, WeaponOwner::Own, 30)['entry'];
        $other = User::factory()->supervisor()->create(['name' => 'Bernd Aufsicht']);
        $second = $this->sessions->open($other);
        $p2 = $this->sessions->addMember($other, $second, $this->member);
        $e2 = $this->sessions->stage($this->member, $p2, EntryVia::OwnDevice, WeaponClass::Short, WeaponOwner::Own, 30)['entry'];
        $this->sessions->signOff($this->supervisor, $first, [$e1->id]);

        $this->travel(5)->minutes();
        $this->logbook->cancel($this->admin, LogEntry::where('session_entry_id', $e1->id)->firstOrFail(), 'falsche Person');
        $this->travel(5)->minutes();
        $this->logbook->lateEntries($this->admin, [$this->member], today()->toDateString(), WeaponClass::Short, WeaponOwner::Own, null, 'Papierliste von heute', 30);

        $this->travel(5)->minutes();
        $this->assertSame(['created' => 0, 'merged' => 1], $this->sessions->signOff($other, $second, [$e2->id]));

        $this->actingAs($this->member)->get(route('today.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('participation.entries.0.status_label', 'abgezeichnet')
                ->where('participation.entries.0.record', null));
    }

    /**
     * In der Vorstufe steht '' statt null (Unique-Index), das Model liefert null; eine Altbestand-Zeile
     * kann '' tragen. Der Doppelschutz vergleicht coalesce(discipline, ''), die Ansicht ebenso.
     */
    public function test_merge_target_is_found_whether_the_discipline_is_empty_or_null()
    {
        $session = $this->sessions->open($this->supervisor);
        $participant = $this->sessions->addMember($this->supervisor, $session, $this->member);
        $entry = $this->sessions->stage($this->member, $participant, EntryVia::OwnDevice, WeaponClass::Short, WeaponOwner::Own, 30)['entry'];
        $this->assertSame('', $entry->getRawOriginal('discipline'));

        $this->travel(5)->minutes();
        $legacy = LogEntry::create([
            'user_id' => $this->member->id,
            'shot_on' => today()->toDateString(),
            'weapon_class' => WeaponClass::Short,
            'weapon_owner' => WeaponOwner::Own,
            'discipline' => '',
            'shots' => 30,
            'recorded_by_id' => $this->supervisor->id,
            'source' => LogSource::Stand,
        ]);

        $this->travel(5)->minutes();
        $this->assertSame(['created' => 0, 'merged' => 1], $this->sessions->signOff($this->supervisor, $session, [$entry->id]));

        $this->travel(5)->minutes();
        $this->logbook->cancel($this->admin, $legacy, 'Altbestand doppelt');

        $this->actingAs($this->member)->get(route('today.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('participation.entries.0.status_label', 'storniert')
                ->where('participation.entries.0.record.reason', 'Altbestand doppelt'));
    }

    public function test_merged_entries_with_a_discipline_find_their_row()
    {
        config(['verein.discipline_catalog' => 'test', 'disziplinen.test' => ['BDS:1.10' => 'Pistole']]);
        [$row] = $this->mergedIntoFirstSession('BDS:1.10');

        $this->logbook->cancel($this->admin, $row, 'doppelt erfasst');

        $this->actingAs($this->member)->get(route('today.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('participation.entries.0.discipline', 'BDS:1.10 Pistole')
                ->where('participation.entries.0.status_label', 'storniert'));
    }

    /**
     * Eine Abfrage auf die Kladde je Seite, gleich wie viele Einträge abgezeichnet sind (kein N+1).
     */
    public function test_today_reads_the_record_with_one_query()
    {
        config(['verein.discipline_catalog' => 'test', 'disziplinen.test' => ['BDS:1.10' => 'Pistole', 'BDS:1.20' => 'Revolver', 'BDS:2.10' => 'Gewehr']]);
        $session = $this->sessions->open($this->supervisor);
        $participant = $this->sessions->addMember($this->supervisor, $session, $this->member);

        $ids = [];
        foreach ([[WeaponClass::Short, 'BDS:1.10'], [WeaponClass::Short, 'BDS:1.20'], [WeaponClass::Long, 'BDS:2.10']] as [$class, $discipline]) {
            $ids[] = $this->sessions->stage($this->member, $participant, EntryVia::OwnDevice, $class, WeaponOwner::Own, 30, $discipline)['entry']->id;
        }

        // Ein Nachtrag mit gleichem Schlüssel vor dem Abzeichnen: Ein Eintrag wird zusammengeführt.
        $this->logbook->lateEntries($this->admin, [$this->member], today()->toDateString(), WeaponClass::Short, WeaponOwner::Own, 'BDS:1.10', 'Papierliste von heute', 30);
        $this->travel(5)->minutes();
        $this->assertSame(['created' => 2, 'merged' => 1], $this->sessions->signOff($this->supervisor, $session, $ids));
        $this->logbook->cancel($this->admin, LogEntry::where('session_entry_id', $ids[1])->firstOrFail(), 'doppelt erfasst');

        DB::enableQueryLog();
        $this->actingAs($this->member)->get(route('today.show'))->assertInertia(fn (Assert $page) => $page
            ->has('participation.entries', 3)
            ->where('participation.entries.0.record', null)
            ->where('participation.entries.1.record.state', 'cancelled')
            ->where('participation.entries.2.record', null));
        $queries = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $q) => str_contains($q, 'from "log_entries"'));
        DB::disableQueryLog();

        // Vorbelegung „last“ liest die Kladde nur ohne Eintrag in der Vorstufe; hier bleibt es bei einer Abfrage.
        $this->assertCount(1, $queries, $queries->implode("\n"));
    }

    public function test_own_record_shows_cancelled_rows_without_counting_them()
    {
        config(['verein.discipline_catalog' => 'test', 'disziplinen.test' => ['BDS:1.10' => 'Pistole', 'BDS:2.10' => 'Gewehr']]);
        $this->logbook->lateEntries($this->admin, [$this->member], today()->subDay()->toDateString(), WeaponClass::Long, WeaponOwner::Own, 'BDS:2.10', 'Papierliste vom Vortag', 40);
        $row = $this->signedRow(WeaponClass::Short, 30, 'BDS:1.10');

        $this->logbook->cancel($this->admin, $row, 'Fehleingabe am Handy');

        $this->actingAs($this->member)->get(route('record.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('record/Index')
                ->has('entries', 2)
                ->where('entries.0.id', $row->id)
                ->where('entries.0.active', false)
                ->where('entries.0.cancel_reason', 'Fehleingabe am Handy')
                ->where('entries.0.cancelled_by', 'Petra Vorstand')
                ->where('entries.1.active', true)
                ->where('entries.1.cancelled_by', null)
                // Kurzwaffe nur storniert: zählt nicht, auch nicht für den Teilnahmenachweis
                ->where('totals.0.class', 'short')
                ->where('totals.0.days', 0)
                ->where('totals.0.shots', 0)
                ->where('totals.1.class', 'long')
                ->where('totals.1.days', 1)
                ->has('disciplines', 1)
                ->where('disciplines.0.key', 'BDS:2.10'));
    }

    public function test_own_record_shows_a_corrected_row_only_once_as_the_correction()
    {
        $row = $this->signedRow();
        $correction = $this->logbook->correct($this->admin, $row, WeaponClass::Long, WeaponOwner::Own, null, 30, 'Kategorie falsch erfasst');

        $this->actingAs($this->member)->get(route('record.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('entries', 1)
                ->where('entries.0.id', $correction->id)
                ->where('entries.0.active', true)
                ->where('entries.0.correction', true)
                ->where('entries.0.late_reason', 'Kategorie falsch erfasst'));

        $this->logbook->cancel(User::factory()->admin()->create(['name' => 'Karl Kassenwart']), $correction, 'doppelt erfasst');

        $this->actingAs($this->member)->get(route('record.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('entries', 1)
                ->where('entries.0.id', $correction->id)
                ->where('entries.0.active', false)
                ->where('entries.0.cancel_reason', 'doppelt erfasst')
                ->where('entries.0.cancelled_by', 'Karl Kassenwart')
                ->where('totals.1.days', 0));
    }

    public function test_extract_still_contains_only_valid_rows()
    {
        $this->logbook->lateEntries($this->admin, [$this->member], today()->subDay()->toDateString(), WeaponClass::Long, WeaponOwner::Own, null, 'Papierliste vom Vortag', 40);
        $row = $this->signedRow(WeaponClass::Short, 37);
        $this->logbook->cancel($this->admin, $row, 'Fehleingabe am Handy');

        $data = ShootingRecordController::pdfData($this->member, today()->subYear(), today(), $this->member, true);
        $this->assertCount(1, $data['entries']);
        $this->assertTrue($data['entries']->every(fn (array $e) => $e['active']));

        $html = view('pdf.record', $data)->render();
        $this->assertStringNotContainsString('Fehleingabe am Handy', $html);
        $this->assertStringContainsString('<td class="num">40</td>', $html);
        $this->assertStringNotContainsString('<td class="num">37</td>', $html);
    }
}
