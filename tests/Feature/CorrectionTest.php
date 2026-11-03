<?php

namespace Tests\Feature;

use App\Domain\Logbook;
use App\Domain\Sessions;
use App\Enums\EntryVia;
use App\Enums\LogSource;
use App\Enums\WeaponClass;
use App\Enums\WeaponOwner;
use App\Models\LogEntry;
use App\Models\SessionEntry;
use App\Models\ShootingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Änderungen nach dem Abzeichnen nur durch den Admin (PR 7, E3, E6): Storno, Korrektur, Nachtrag.
 */
class CorrectionTest extends TestCase
{
    use RefreshDatabase;

    private Logbook $logbook;

    private Sessions $sessions;

    private User $supervisor;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(18, 0));
        $this->logbook = app(Logbook::class);
        $this->sessions = app(Sessions::class);
        $this->supervisor = User::factory()->supervisor()->create(['name' => 'Hanna Aufsicht']);
        $this->admin = User::factory()->admin()->create(['name' => 'Petra Vorstand']);
    }

    /**
     * Eine abgezeichnete Session-Zeile, erfasst am Stand.
     */
    private function signedRow(?User $member = null, WeaponClass $class = WeaponClass::Short, ?User $supervisor = null, ?string $guest = null): LogEntry
    {
        $supervisor ??= $this->supervisor;
        $session = $this->sessions->open($supervisor);
        $participant = $guest !== null
            ? $this->sessions->addGuest($supervisor, $session, $guest, true)
            : $this->sessions->addMember($supervisor, $session, $member ?? User::factory()->create(), adultChecked: true);
        $staged = $this->sessions->stage($supervisor, $participant, EntryVia::Stand, $class, $guest !== null ? null : WeaponOwner::Own, 30);
        $this->sessions->signOff($supervisor, $session, [$staged['entry']->id]);

        return LogEntry::where('session_entry_id', $staged['entry']->id)->firstOrFail();
    }

    private function assertRejected(callable $action, string $field): void
    {
        try {
            $action();
            $this->fail('Aktion wurde nicht abgewiesen.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors(), json_encode($e->errors()));
        }
    }

    private function confirmed(?User $user = null): self
    {
        return $this->actingAs($user ?? $this->admin)->withSession(['auth.password_confirmed_at' => now()->unix()]);
    }

    public function test_correction_cancels_the_old_row_and_creates_a_late_entry_with_corrects_id()
    {
        $member = User::factory()->create();
        $old = $this->signedRow($member);

        $new = $this->logbook->correct($this->admin, $old, WeaponClass::Long, WeaponOwner::Club, null, 25, 'Kategorie falsch erfasst');

        $old->refresh();
        $this->assertSame('cancelled', $old->status);
        $this->assertSame('Korrektur: Kategorie falsch erfasst', $old->cancel_reason);
        $this->assertSame($this->admin->id, $old->cancelled_by_id);

        $this->assertSame($old->id, $new->corrects_id);
        $this->assertSame(LogSource::LateEntry, $new->source);
        $this->assertSame('Kategorie falsch erfasst', $new->late_reason);
        $this->assertSame($this->admin->id, $new->recorded_by_id);
        $this->assertSame($member->id, $new->user_id);
        $this->assertTrue($new->shot_on->isSameDay($old->shot_on));
        $this->assertSame(WeaponClass::Long, $new->weapon_class);
        $this->assertSame(WeaponOwner::Club, $new->weapon_owner);
        $this->assertSame(25, $new->shots);
        $this->assertNull($new->session_entry_id);
        $this->assertSame(1, LogEntry::active()->count());
        $this->assertSame(2, LogEntry::count());
    }

    public function test_short_and_long_only_if_the_original_row_was_short_or_long()
    {
        $exempt = $this->signedRow(class: WeaponClass::Exempt);

        $this->assertRejected(fn () => $this->logbook->correct($this->admin, $exempt, WeaponClass::Short, WeaponOwner::Own, null, 30, 'eigentlich Kurzwaffe'), 'weapon_class');
        $this->assertSame('active', $exempt->fresh()->status);

        $short = $this->signedRow(class: WeaponClass::Short);
        $this->assertSame(WeaponClass::Exempt, $this->logbook->correct($this->admin, $short, WeaponClass::Exempt, WeaponOwner::Own, null, 30, 'war Luftgewehr')->weapon_class);
    }

    public function test_correction_needs_a_reason_and_a_change()
    {
        $row = $this->signedRow();

        $this->assertRejected(fn () => $this->logbook->correct($this->admin, $row, WeaponClass::Long, WeaponOwner::Own, null, 30, ' '), 'reason');
        $this->assertRejected(fn () => $this->logbook->correct($this->admin, $row, WeaponClass::Short, WeaponOwner::Own, null, 30, 'nichts geändert'), 'entry');
        $this->assertRejected(fn () => $this->logbook->correct($this->admin, $row, WeaponClass::Short, WeaponOwner::Own, null, 1000, 'zu viele Schüsse'), 'shots');
        $this->assertSame(1, LogEntry::count());
    }

    public function test_correction_onto_an_existing_row_rolls_the_storno_back()
    {
        $member = User::factory()->create();
        $short = $this->signedRow($member, WeaponClass::Short);
        $this->logbook->lateEntries($this->admin, [$member], today()->toDateString(), WeaponClass::Long, WeaponOwner::Own, null, 'Papierliste vom Tag', 40);

        $this->assertRejected(fn () => $this->logbook->correct(User::factory()->admin()->create(), $short, WeaponClass::Long, WeaponOwner::Own, null, 30, 'war Langwaffe'), 'entry');

        $this->assertSame('active', $short->fresh()->status);
        $this->assertSame(2, LogEntry::active()->count());
    }

    public function test_guest_rows_keep_no_owner_and_no_discipline()
    {
        $row = $this->signedRow(guest: 'Gerd Gast');

        $new = $this->logbook->correct($this->admin, $row, WeaponClass::Long, WeaponOwner::Own, 'DSB:1.10', 20, 'Gast schoss Langwaffe');

        $this->assertNull($new->user_id);
        $this->assertSame('Gerd Gast', $new->guest_name);
        $this->assertNull($new->weapon_owner);
        $this->assertNull($new->discipline);
    }

    public function test_only_admins_cancel_and_correct()
    {
        $row = $this->signedRow();

        foreach ([fn () => $this->logbook->correct($this->supervisor, $row, WeaponClass::Long, WeaponOwner::Own, null, 30, 'Aufsicht will ändern'), fn () => $this->logbook->cancel($this->supervisor, $row, 'Aufsicht will stornieren')] as $action) {
            try {
                $action();
                $this->fail('Nicht abgewiesen.');
            } catch (HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }

        $this->assertSame('active', $row->fresh()->status);
    }

    public function test_admin_never_changes_rows_with_himself_as_member_or_confirmer()
    {
        $this->admin->forceFill(['adult_confirmed_at' => now()])->save();
        $asMember = $this->signedRow($this->admin);

        $member = User::factory()->create();
        $this->logbook->lateEntries($this->admin, [$member], today()->subDay()->toDateString(), WeaponClass::Short, WeaponOwner::Own, null, 'Papierliste vom Vortag');
        $asConfirmer = LogEntry::where('source', LogSource::LateEntry->value)->sole();

        foreach ([$asMember, $asConfirmer] as $row) {
            $this->assertRejected(fn () => $this->logbook->cancel($this->admin, $row, 'eigene Zeile'), 'entry');
            $this->assertRejected(fn () => $this->logbook->correct($this->admin, $row, WeaponClass::Long, WeaponOwner::Own, null, 30, 'eigene Zeile'), 'entry');
            $this->assertSame('active', $row->fresh()->status);
        }

        // ein zweiter Admin darf
        $second = User::factory()->admin()->create();
        $this->logbook->cancel($second, $asMember, 'durch zweiten Admin');
        $this->assertSame('cancelled', $asMember->fresh()->status);

        // die Korrektur trägt den korrigierenden Admin als Bestätiger: ihn sperrt sie für weitere Änderungen
        $new = $this->logbook->correct($second, $asConfirmer, WeaponClass::Long, WeaponOwner::Own, null, 30, 'durch zweiten Admin');
        $this->assertRejected(fn () => $this->logbook->cancel($second, $new, 'noch einmal'), 'entry');
    }

    public function test_cancel_correct_and_late_need_password_confirmation()
    {
        $row = $this->signedRow();
        $member = User::factory()->create();
        $kladde = route('admin.record.index');

        $requests = [
            route('admin.record.cancel', $row) => ['reason' => 'Fehleingabe'],
            route('admin.record.correct', $row) => ['weapon_class' => 'long', 'weapon_owner' => 'own', 'shots' => 30, 'reason' => 'Kategorie falsch'],
            route('admin.record.late') => ['members' => [$member->id], 'date' => today()->toDateString(), 'weapon_class' => 'short', 'weapon_owner' => 'own', 'reason' => 'Papierliste vom Tag'],
        ];

        foreach ($requests as $url => $payload) {
            $this->flushSession();
            $response = $this->actingAs($this->admin)->from($kladde)->post($url, $payload);
            $response->assertRedirect(route('password.confirm'));
            // nach dem Bestätigen zurück zur Kladde, nicht auf die POST-Adresse
            $this->assertSame($kladde, session('url.intended'));
        }

        $this->assertSame(1, LogEntry::count());
        $this->assertSame('active', $row->fresh()->status);

        $this->confirmed()->from($kladde)->post(route('admin.record.correct', $row), $requests[route('admin.record.correct', $row)])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $row->fresh()->status);
        $this->assertSame(1, LogEntry::whereNotNull('corrects_id')->count());
    }

    public function test_password_is_confirmed_before_filling_a_form_and_leads_back_to_the_kladde()
    {
        $this->actingAs($this->admin)->get(route('admin.record.index'))
            ->assertInertia(fn (Assert $page) => $page->where('passwordConfirmed', false));

        $this->actingAs($this->admin)->get(route('admin.record.confirm', ['from' => '2026-10-01']))
            ->assertRedirect(route('password.confirm'));
        $this->assertSame(route('admin.record.confirm', ['from' => '2026-10-01']), session('url.intended'));

        $this->confirmed()->get(route('admin.record.confirm', ['from' => '2026-10-01']))
            ->assertRedirect(route('admin.record.index', ['from' => '2026-10-01']));
        $this->confirmed()->get(route('admin.record.index'))
            ->assertInertia(fn (Assert $page) => $page->where('passwordConfirmed', true));
    }

    public function test_late_entries_take_optional_shots()
    {
        $members = User::factory()->count(2)->create();

        $this->confirmed()->post(route('admin.record.late'), [
            'members' => $members->pluck('id')->all(),
            'date' => today()->subDay()->toDateString(),
            'weapon_class' => 'short',
            'weapon_owner' => 'own',
            'shots' => 40,
            'reason' => 'Papierliste vom Vortag, Aufsicht: H. S.',
        ])->assertSessionHasNoErrors();

        $this->assertSame([40, 40], LogEntry::pluck('shots')->all());

        $this->confirmed()->post(route('admin.record.late'), [
            'members' => [$members->first()->id],
            'date' => today()->subDays(2)->toDateString(),
            'weapon_class' => 'short',
            'weapon_owner' => 'own',
            'shots' => 1000,
            'reason' => 'Papierliste',
        ])->assertSessionHasErrors('shots');
    }

    public function test_admin_kladde_lists_expired_sessions_and_rejected_and_withdrawn_entries()
    {
        $member = User::factory()->create(['adult_confirmed_at' => now(), 'name' => 'Max Abgelehnt']);
        $session = $this->sessions->open($this->supervisor);
        $participant = $this->sessions->addMember($this->supervisor, $session, $member);
        $rejected = $this->sessions->stage($this->supervisor, $participant, EntryVia::Stand, WeaponClass::Short, WeaponOwner::Own, 30)['entry'];
        $this->sessions->reject($this->supervisor, $rejected, 'falsche Kategorie');
        $withdrawn = $this->sessions->stage($this->supervisor, $participant, EntryVia::Stand, WeaponClass::Long, WeaponOwner::Own, 20)['entry'];
        $this->sessions->withdraw($this->supervisor, $withdrawn);
        $this->sessions->stage($this->supervisor, $participant, EntryVia::Stand, WeaponClass::Exempt, WeaponOwner::Own, 10);

        $this->travel(1)->day();

        $this->assertTrue($session->fresh()->isExpired());
        $this->assertSame(0, LogEntry::count());

        $this->actingAs($this->admin)->get(route('admin.record.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/record/Index')
                ->has('sessions', 1)
                ->where('sessions.0.state', 'expired')
                ->where('sessions.0.pending', 1)
                ->has('dropped', 2)
                ->where('dropped.0.status', 'zurückgezogen')
                ->where('dropped.1.status', 'abgelehnt')
                ->where('dropped.1.reason', 'falsche Kategorie')
                ->where('dropped.1.by', 'Hanna Aufsicht'));
    }

    public function test_admin_kladde_marks_corrections_and_shows_both_rows()
    {
        $old = $this->signedRow();
        $new = $this->logbook->correct($this->admin, $old, WeaponClass::Long, WeaponOwner::Own, null, 30, 'Kategorie falsch');

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.record.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('entries', 2)
                ->where('entries.0.id', $new->id)
                ->where('entries.0.correction', true)
                ->where('entries.0.corrects_id', $old->id)
                ->where('entries.0.shots', 30)
                ->where('entries.1.active', false)
                ->where('entries.1.corrected_by_entry', $new->id));
    }

    public function test_sessions_of_the_period_without_signing_show_open()
    {
        $this->sessions->open($this->supervisor);

        $this->actingAs($this->admin)->get(route('admin.record.index'))
            ->assertInertia(fn (Assert $page) => $page->where('sessions.0.state', 'open'));

        $this->assertSame(1, ShootingSession::count());
        $this->assertSame(0, SessionEntry::count());
    }

    public function test_the_original_confirmer_stays_locked_out_after_a_correction_by_a_second_admin()
    {
        // A ist Aufsicht und Admin, zeichnet ab (R1); B korrigiert zu R2. A bleibt Bestätiger der Kette (E3).
        $a = User::factory()->admin()->supervisor()->create(['name' => 'Admin A']);
        $b = User::factory()->admin()->create(['name' => 'Admin B']);
        $r1 = $this->signedRow(supervisor: $a);
        $this->assertSame($a->id, $r1->recorded_by_id);

        $r2 = $this->logbook->correct($b, $r1, WeaponClass::Long, WeaponOwner::Own, null, 30, 'Kategorie falsch');
        $this->assertSame($b->id, $r2->recorded_by_id);
        $this->assertSame($r1->id, $r2->corrects_id);

        $this->assertRejected(fn () => $this->logbook->correct($a, $r2->fresh(), WeaponClass::Short, WeaponOwner::Own, null, 25, 'zurück, Aufsicht A'), 'entry');
        $this->assertRejected(fn () => $this->logbook->cancel($a, $r2->fresh(), 'weg damit'), 'entry');

        // auch über HTTP, und die Kladde blendet A die Knöpfe an R2 aus
        $this->confirmed($a)->post(route('admin.record.cancel', $r2), ['reason' => 'Storno durch A'])->assertSessionHasErrors('entry');
        $this->assertSame('active', $r2->fresh()->status);
        $this->confirmed($a)->get(route('admin.record.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('entries', fn ($entries) => collect($entries)->firstWhere('id', $r2->id)['may_change'] === false));
        // ein dritter Admin darf weiter
        $c = User::factory()->admin()->create();
        $this->confirmed($c)->get(route('admin.record.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('entries', fn ($entries) => collect($entries)->firstWhere('id', $r2->id)['may_change'] === true));
        $this->logbook->cancel($c, $r2->fresh(), 'durch dritten Admin');
        $this->assertSame('cancelled', $r2->fresh()->status);
    }

    public function test_late_entries_without_catalog_store_no_discipline_and_stay_duplicate_safe()
    {
        config(['verein.discipline_catalog' => '']);
        $member = User::factory()->create();

        $first = $this->logbook->lateEntries($this->admin, [$member], today()->toDateString(), WeaponClass::Short, WeaponOwner::Own, null, 'Papierliste vom Tag');
        $second = $this->logbook->lateEntries($this->admin, [$member], today()->toDateString(), WeaponClass::Short, WeaponOwner::Own, 'Freitext', 'Papierliste vom Tag');

        $this->assertSame(['created' => 1, 'duplicates' => 0], $first);
        $this->assertSame(['created' => 0, 'duplicates' => 1], $second);
        $this->assertSame([null], LogEntry::active()->where('user_id', $member->id)->pluck('discipline')->all());
    }
}
