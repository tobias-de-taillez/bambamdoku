<?php

namespace Tests\Feature;

use App\Domain\Sessions;
use App\Domain\ShootingRecord;
use App\Enums\BookingStatus;
use App\Enums\EntryVia;
use App\Enums\LogSource;
use App\Enums\SessionEntryStatus;
use App\Enums\SessionStatus;
use App\Enums\SupervisionScope;
use App\Enums\WeaponClass;
use App\Enums\WeaponOwner;
use App\Models\AuditEntry;
use App\Models\Booking;
use App\Models\LogEntry;
use App\Models\Qualification;
use App\Models\SessionEntry;
use App\Models\SessionParticipant;
use App\Models\ShootingDay;
use App\Models\ShootingSession;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Invarianten der Schieß-Session (UMBAU-SESSION.md, Invarianten als Tests; PR 4a).
 */
class SessionSignOffTest extends TestCase
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
        $this->supervisor = User::factory()->supervisor()->create(['name' => 'Aufsicht A']);
        $this->member = $this->adult();
    }

    private function adult(array $attributes = []): User
    {
        return User::factory()->create(['adult_confirmed_at' => now(), ...$attributes]);
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

    private function assertForbidden(callable $action): void
    {
        try {
            $action();
            $this->fail('Aktion wurde nicht mit 403 abgewiesen.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    /**
     * @return array{session: ShootingSession, participant: SessionParticipant}
     */
    private function activated(?User $member = null, ?User $supervisor = null): array
    {
        $supervisor ??= $this->supervisor;
        $session = $this->sessions->open($supervisor);

        return ['session' => $session, 'participant' => $this->sessions->addMember($supervisor, $session, $member ?? $this->member)];
    }

    private function stage(SessionParticipant $participant, ?User $actor = null, EntryVia $via = EntryVia::Stand, WeaponClass $class = WeaponClass::Short, ?WeaponOwner $owner = WeaponOwner::Own, ?int $shots = 30, ?string $discipline = null): array
    {
        return $this->sessions->stage($actor ?? $this->supervisor, $participant, $via, $class, $owner, $shots, $discipline);
    }

    private function pendingIds(ShootingSession $session): array
    {
        return SessionEntry::query()->whereHas('participant', fn ($q) => $q->where('session_id', $session->id))->where('status', 'pending')->pluck('id')->all();
    }

    private function signOff(ShootingSession $session, ?User $actor = null): array
    {
        return $this->sessions->signOff($actor ?? $this->supervisor, $session, $this->pendingIds($session));
    }

    private function catalog(): void
    {
        config(['verein.discipline_catalog' => 'test', 'disziplinen.test' => ['BDS:1.10' => 'Pistole', 'BDS:1.20' => 'Revolver']]);
    }

    // --- Starten ---

    public function test_only_registered_supervisors_open_sessions_even_admins()
    {
        $this->assertRejected(fn () => $this->sessions->open(User::factory()->create()), 'session');
        $this->assertRejected(fn () => $this->sessions->open(User::factory()->admin()->create()), 'session');
        $this->assertRejected(fn () => $this->sessions->open(User::factory()->supervisor(until: now()->subDay()->toDateString())->create()), 'session');

        $session = $this->sessions->open($this->supervisor);

        $this->assertSame(SessionStatus::Open, $session->status);
        $this->assertSame(today()->toDateString(), DB::table('shooting_sessions')->value('shot_on'));
        $this->assertTrue($this->sessions->open($this->supervisor)->is($session), 'Zweiter Tap startet keine zweite Session.');
        $this->assertSame(1, ShootingSession::count());
    }

    // --- Aktivieren ---

    public function test_supervisor_is_never_a_participant_of_her_own_session()
    {
        $session = $this->sessions->open($this->supervisor);

        $this->assertRejected(fn () => $this->sessions->addMember($this->supervisor, $session, $this->supervisor, true), 'user_id');
        $this->assertSame(0, SessionParticipant::count());
    }

    public function test_only_the_owner_activates()
    {
        $session = $this->sessions->open($this->supervisor);

        $this->assertForbidden(fn () => $this->sessions->addMember(User::factory()->supervisor()->create(), $session, $this->member));
        $this->assertForbidden(fn () => $this->sessions->addGuest(User::factory()->admin()->create(), $session, 'Gerda Gast', true));
    }

    public function test_deactivated_members_and_applicants_are_rejected()
    {
        $session = $this->sessions->open($this->supervisor);
        $inactive = $this->adult(['active' => false]);

        $this->assertRejected(fn () => $this->sessions->addMember($this->supervisor, $session, $inactive), 'user_id');
    }

    public function test_adult_check_is_asked_once_stored_and_logged()
    {
        $session = $this->sessions->open($this->supervisor);
        $young = User::factory()->create(['adult_confirmed_at' => null]);

        $this->assertRejected(fn () => $this->sessions->addMember($this->supervisor, $session, $young), 'adult_confirmed');
        $this->sessions->addMember($this->supervisor, $session, $young, adultChecked: true);

        $this->assertNotNull($young->fresh()->adult_confirmed_at);
        $this->assertTrue(AuditEntry::where('action', 'user.adult_confirmed')->where('subject_id', $young->id)->exists());

        // danach wird nicht mehr gefragt
        $this->sessions->signOff($this->supervisor, $session, []);
        $next = $this->sessions->open($this->supervisor);
        $this->assertNotNull($this->sessions->addMember($this->supervisor, $next, $young));
    }

    public function test_guests_need_a_name_and_adult_confirmation()
    {
        $session = $this->sessions->open($this->supervisor);

        $this->assertRejected(fn () => $this->sessions->addGuest($this->supervisor, $session, 'Gerda Gast', false), 'guest_adult');
        $this->assertRejected(fn () => $this->sessions->addGuest($this->supervisor, $session, '  ', true), 'guest_name');
        $guest = $this->sessions->addGuest($this->supervisor, $session, 'Gerda Gast', true);

        $this->assertTrue($guest->isGuest());
        $this->assertTrue($this->sessions->addGuest($this->supervisor, $session, 'Gerda Gast', true)->is($guest));
    }

    public function test_activation_from_a_booking_works_regardless_of_its_status()
    {
        $day = ShootingDay::factory()->withRange()->create(['date' => today()->toDateString(), 'starts_at' => '17:00', 'ends_at' => '21:00']);
        $make = fn (BookingStatus $status) => Booking::factory()->create(['shooting_day_id' => $day->id, 'range_id' => $day->ranges->first()->id, 'user_id' => $this->adult()->id, 'status' => $status]);
        $session = $this->sessions->open($this->supervisor);

        foreach ([BookingStatus::Booked, BookingStatus::CheckedIn] as $status) {
            $booking = $make($status);
            $participant = $this->sessions->activate($this->supervisor, $session, $booking);

            $this->assertSame($booking->id, $participant->booking_id);
            $this->assertSame(BookingStatus::CheckedIn, $booking->fresh()->status);
        }

        $this->assertRejected(fn () => $this->sessions->activate($this->supervisor, $session, $make(BookingStatus::Cancelled)), 'booking');
        $this->assertSame(0, LogEntry::count(), 'Aktivieren schreibt nichts in die Kladde.');
    }

    public function test_removing_a_participant_without_entries_reopens_the_booking()
    {
        $day = ShootingDay::factory()->withRange()->create(['date' => today()->toDateString(), 'starts_at' => '17:00', 'ends_at' => '21:00']);
        $booking = Booking::factory()->create(['shooting_day_id' => $day->id, 'range_id' => $day->ranges->first()->id, 'user_id' => $this->member->id]);
        $session = $this->sessions->open($this->supervisor);
        $participant = $this->sessions->activate($this->supervisor, $session, $booking);
        $other = $this->sessions->addMember($this->supervisor, $session, $this->adult());
        $this->stage($other);

        $this->assertRejected(fn () => $this->sessions->removeParticipant($this->supervisor, $other), 'participant');
        $this->sessions->removeParticipant($this->supervisor, $participant);

        $this->assertSame(BookingStatus::Booked, $booking->fresh()->status);
        $this->assertSame(1, SessionParticipant::count());
    }

    // --- Wer beaufsichtigt, schießt nicht ---

    public function test_who_leads_an_open_session_cannot_be_activated_nor_stage()
    {
        $b = User::factory()->supervisor()->create(['adult_confirmed_at' => now()]);
        $a = $this->sessions->open($this->supervisor);
        $bInA = $this->sessions->addMember($this->supervisor, $a, $b);

        $own = $this->sessions->open($b);

        // A kann B nicht mehr nutzen, B kann A nicht aktivieren
        $this->assertRejected(fn () => $this->stage($bInA), 'entry');
        $this->assertRejected(fn () => $this->sessions->addMember($b, $own, $this->supervisor, true), 'user_id');
        $this->assertSame(0, SessionEntry::count());
    }

    public function test_two_supervisors_trying_to_activate_each_other_in_quick_succession()
    {
        $b = User::factory()->supervisor()->create(['adult_confirmed_at' => now()]);
        $this->supervisor->forceFill(['adult_confirmed_at' => now()])->save();
        $sessionA = $this->sessions->open($this->supervisor);
        $sessionB = $this->sessions->open($b);

        $this->assertRejected(fn () => $this->sessions->addMember($this->supervisor, $sessionA, $b), 'user_id');
        $this->assertRejected(fn () => $this->sessions->addMember($b, $sessionB, $this->supervisor), 'user_id');
        $this->assertSame(0, SessionParticipant::count());
    }

    public function test_supervisor_change_with_an_already_activated_booking()
    {
        $b = User::factory()->supervisor()->create(['adult_confirmed_at' => now()]);
        $day = ShootingDay::factory()->withRange()->create(['date' => today()->toDateString(), 'starts_at' => '17:00', 'ends_at' => '21:00']);
        $booking = Booking::factory()->create(['shooting_day_id' => $day->id, 'range_id' => $day->ranges->first()->id, 'user_id' => $this->member->id]);

        $sessionA = $this->sessions->open($this->supervisor);
        $inA = $this->sessions->activate($this->supervisor, $sessionA, $booking);
        $this->stage($inA, discipline: null);
        $this->assertSame(['created' => 1, 'merged' => 0], $this->signOff($sessionA));

        $sessionB = $this->sessions->open($b);
        $inB = $this->sessions->activate($b, $sessionB, $booking->fresh());
        $this->assertRejected(fn () => $this->stage($inA), 'entry');
        $this->stage($inB, $b, class: WeaponClass::Long);
        $this->signOff($sessionB, $b);

        $this->assertSame([$this->supervisor->id, $b->id], LogEntry::orderBy('id')->pluck('recorded_by_id')->all());
    }

    // --- Erfassen ---

    public function test_own_device_only_for_oneself_in_the_latest_activation()
    {
        ['session' => $session, 'participant' => $participant] = $this->activated();
        $stranger = $this->adult();

        $this->assertForbidden(fn () => $this->stage($participant, $stranger, EntryVia::OwnDevice));
        $entry = $this->stage($participant, $this->member, EntryVia::OwnDevice)['entry'];
        $this->assertSame($this->member->id, $entry->entered_by_id);
        $this->assertSame(EntryVia::OwnDevice, $entry->via);

        // Standwechsel: neue Einträge nur in der jüngsten Aktivierung
        $b = User::factory()->supervisor()->create();
        $sessionB = $this->sessions->open($b);
        $this->travel(1)->minute();
        $this->sessions->addMember($b, $sessionB, $this->member);
        $this->assertRejected(fn () => $this->stage($participant, $this->member, EntryVia::OwnDevice, WeaponClass::Long), 'entry');
    }

    public function test_deactivated_member_cannot_stage()
    {
        ['participant' => $participant] = $this->activated();
        $this->member->forceFill(['active' => false])->save();

        $this->assertRejected(fn () => $this->stage($participant, $this->member, EntryVia::OwnDevice), 'entry');
    }

    public function test_kiosk_needs_a_pin_and_only_for_oneself()
    {
        ['participant' => $participant] = $this->activated();

        $this->assertForbidden(fn () => $this->stage($participant, $this->member, EntryVia::Kiosk));
        $this->member->forceFill(['kiosk_pin_hash' => bcrypt('1234')])->save();
        $entry = $this->stage($participant, $this->member->fresh(), EntryVia::Kiosk)['entry'];

        $this->assertSame(EntryVia::Kiosk, $entry->via);
        $this->assertForbidden(fn () => $this->sessions->withdraw($this->member, $entry));
    }

    public function test_members_need_category_owner_and_shots_up_to_999_guests_not()
    {
        ['session' => $session, 'participant' => $participant] = $this->activated();

        $this->assertRejected(fn () => $this->stage($participant, owner: null), 'weapon_owner');
        $this->assertRejected(fn () => $this->stage($participant, shots: null), 'shots');
        $this->assertRejected(fn () => $this->stage($participant, shots: 1000), 'shots');

        $guest = $this->sessions->addGuest($this->supervisor, $session, 'Gerda Gast', true);
        $entry = $this->stage($guest, owner: WeaponOwner::Club, shots: null)['entry'];
        $this->assertNull($entry->weapon_owner);
        $this->assertSame('', DB::table('session_entries')->where('id', $entry->id)->value('weapon_owner'));
    }

    public function test_short_and_long_only_with_licensed_scope_at_stage_and_sign_off()
    {
        $exempt = User::factory()->supervisor(SupervisionScope::Exempt)->create();
        ['participant' => $p] = $this->activated(supervisor: $exempt);

        $this->assertRejected(fn () => $this->stage($p, $exempt, class: WeaponClass::Long), 'weapon_class');
        $this->assertNotNull($this->stage($p, $exempt, class: WeaponClass::Exempt)['entry']);

        // Umfang geht während der Session verloren: Abzeichnen prüft noch einmal
        ['session' => $session, 'participant' => $q] = $this->activated($this->adult());
        $this->stage($q, class: WeaponClass::Short);
        Qualification::register($this->supervisor, SupervisionScope::Exempt, now()->addYear()->toDateString(), now()->toDateString(), null, 'herabgestuft');

        $this->assertRejected(fn () => $this->signOff($session), 'session');
        $this->assertSame(0, LogEntry::count());
    }

    public function test_double_tap_returns_the_same_entry_with_its_shots()
    {
        ['participant' => $participant] = $this->activated();

        $first = $this->stage($participant, shots: 40);
        $second = $this->stage($participant, shots: 25);

        $this->assertFalse($first['duplicate']);
        $this->assertTrue($second['duplicate']);
        $this->assertSame(40, $second['entry']->shots);
        $this->assertSame(1, SessionEntry::count());
    }

    public function test_without_catalog_discipline_is_empty_with_catalog_only_keys()
    {
        ['participant' => $participant] = $this->activated();
        $entry = $this->stage($participant, discipline: 'Freitext')['entry'];
        $this->assertNull($entry->discipline);

        $this->catalog();
        $this->assertRejected(fn () => $this->stage($participant, class: WeaponClass::Long, discipline: null), 'discipline');
        $this->assertRejected(fn () => $this->stage($participant, class: WeaponClass::Long, discipline: 'BDS:9.99'), 'discipline');
        $this->assertSame('BDS:1.10', $this->stage($participant, class: WeaponClass::Long, discipline: 'BDS:1.10')['entry']->discipline);
    }

    public function test_exempt_entries_need_no_discipline_but_only_catalog_keys()
    {
        // Verbandskataloge wie der des BDS führen keine erlaubnisfreien Disziplinen (Luftdruck, Bogen).
        ['participant' => $participant] = $this->activated();
        $this->catalog();

        $this->assertNull($this->stage($participant, class: WeaponClass::Exempt, discipline: null)['entry']->discipline);
        $this->assertRejected(fn () => $this->stage($participant, class: WeaponClass::Exempt, discipline: 'BDS:9.99'), 'discipline');
        $this->assertSame('BDS:1.20', $this->stage($participant, class: WeaponClass::Exempt, discipline: 'BDS:1.20')['entry']->discipline);
    }

    public function test_open_entries_are_never_edited()
    {
        ['participant' => $participant] = $this->activated();
        $entry = $this->stage($participant)['entry'];

        try {
            $entry->update(['shots' => 99]);
            $this->fail('Bearbeiten wurde nicht verhindert.');
        } catch (LogicException) {
        }

        $this->expectException(QueryException::class);
        SessionEntry::query()->whereKey($entry->id)->update(['weapon_class' => 'long']);
    }

    public function test_withdraw_only_by_who_entered_reject_only_by_owner_with_reason()
    {
        ['participant' => $participant] = $this->activated();
        $own = $this->stage($participant, $this->member, EntryVia::OwnDevice)['entry'];
        $byStand = $this->stage($participant, class: WeaponClass::Long)['entry'];

        $this->assertForbidden(fn () => $this->sessions->withdraw($this->supervisor, $own));
        $this->assertForbidden(fn () => $this->sessions->withdraw($this->member, $byStand));
        $this->sessions->withdraw($this->member, $own);
        $this->assertSame(SessionEntryStatus::Withdrawn, $own->fresh()->status);

        $this->assertForbidden(fn () => $this->sessions->reject($this->member, $byStand, 'falsch'));
        $this->assertRejected(fn () => $this->sessions->reject($this->supervisor, $byStand, ' '), 'reason');
        $this->sessions->reject($this->supervisor, $byStand, 'Kategorie falsch');

        $rejected = $byStand->fresh();
        $this->assertSame(SessionEntryStatus::Rejected, $rejected->status);
        $this->assertSame('Kategorie falsch', $rejected->reject_reason);
        $this->assertSame($this->supervisor->id, $rejected->rejected_by_id);

        // nach Ablehnung neu erfassen geht
        $this->assertFalse($this->stage($participant, class: WeaponClass::Long)['duplicate']);
    }

    // --- Abzeichnen ---

    public function test_sign_off_writes_exactly_the_shown_entries_to_the_logbook()
    {
        ['session' => $session, 'participant' => $participant] = $this->activated();
        $entry = $this->stage($participant, shots: 50)['entry'];
        $guest = $this->sessions->addGuest($this->supervisor, $session, 'Gerda Gast', true);
        $this->stage($guest, class: WeaponClass::Exempt, shots: null);

        $this->assertSame(['created' => 2, 'merged' => 0], $this->signOff($session));

        $row = LogEntry::where('session_entry_id', $entry->id)->sole();
        $this->assertSame(LogSource::Session, $row->source);
        $this->assertSame($this->supervisor->id, $row->recorded_by_id);
        $this->assertSame(50, $row->shots);
        $this->assertNull($row->booking_id);
        $this->assertTrue($row->shot_on->isSameDay($session->shot_on));
        $this->assertSame(SessionEntryStatus::Signed, $entry->fresh()->status);
        $this->assertSame(SessionStatus::Signed, $session->fresh()->status);
        $this->assertNotNull($session->fresh()->signed_at);
    }

    public function test_sign_off_is_rejected_when_the_list_changed_after_the_dialog()
    {
        ['session' => $session, 'participant' => $participant] = $this->activated();
        $first = $this->stage($participant)['entry'];
        $shown = [$first->id];

        $this->stage($participant, class: WeaponClass::Long);
        $this->assertRejected(fn () => $this->sessions->signOff($this->supervisor, $session, $shown), 'entry_ids');

        $shown = $this->pendingIds($session);
        $this->sessions->withdraw($this->supervisor, $first);
        $this->assertRejected(fn () => $this->sessions->signOff($this->supervisor, $session, $shown), 'entry_ids');

        $this->assertSame(0, LogEntry::count());
        $this->assertSame(SessionStatus::Open, $session->fresh()->status);
    }

    public function test_only_the_owner_signs_off()
    {
        ['session' => $session, 'participant' => $participant] = $this->activated();
        $this->stage($participant);

        $this->assertForbidden(fn () => $this->signOff($session, User::factory()->admin()->supervisor()->create()));
        $this->assertSame(0, LogEntry::count());
    }

    public function test_signing_twice_creates_no_second_row()
    {
        ['session' => $session, 'participant' => $participant] = $this->activated();
        $this->stage($participant);
        $shown = $this->pendingIds($session);

        $this->sessions->signOff($this->supervisor, $session, $shown);
        $this->assertSame(['created' => 0, 'merged' => 0], $this->sessions->signOff($this->supervisor, $session, $shown));

        $this->assertSame(1, LogEntry::count());
    }

    public function test_sign_off_after_midnight_is_rejected_and_the_session_expires()
    {
        ['session' => $session, 'participant' => $participant] = $this->activated();
        $this->stage($participant);
        $shown = $this->pendingIds($session);

        $this->travelTo(today()->addDay()->setTime(0, 5));

        $this->assertTrue($session->fresh()->isExpired());
        $this->assertRejected(fn () => $this->sessions->signOff($this->supervisor, $session, $shown), 'session');
        $this->assertRejected(fn () => $this->stage($participant, class: WeaponClass::Long), 'entry');
        $this->assertRejected(fn () => $this->sessions->reject($this->supervisor, SessionEntry::first(), 'zu spät'), 'entry');
        $this->assertSame(0, LogEntry::count());
        $this->assertSame(SessionEntryStatus::Pending, SessionEntry::first()->status);
    }

    public function test_an_expired_session_from_yesterday_blocks_nothing_today()
    {
        ['session' => $yesterday] = $this->activated();
        $b = User::factory()->supervisor()->create();
        $this->travelTo(today()->addDay()->setTime(18, 0));

        $today = $this->sessions->open($this->supervisor);
        $this->assertFalse($today->is($yesterday));

        // wer gestern eine Session offen ließ, kann heute aktiviert werden und erfassen
        $sessionB = $this->sessions->open($b);
        $this->supervisor->forceFill(['adult_confirmed_at' => now()])->save();
        $this->sessions->signOff($this->supervisor, $today, []);
        $p = $this->sessions->addMember($b, $sessionB, $this->supervisor);
        $this->assertFalse($this->stage($p, $b)['duplicate']);
    }

    public function test_revoked_registration_during_the_session_blocks_sign_off()
    {
        ['session' => $session, 'participant' => $participant] = $this->activated();
        $this->stage($participant);
        Qualification::register($this->supervisor, SupervisionScope::Revoked, null, now()->toDateString(), null, 'entzogen');

        $this->assertRejected(fn () => $this->signOff($session), 'session');
        $this->assertSame(0, LogEntry::count());
        $this->assertSame(SessionEntryStatus::Pending, SessionEntry::first()->status);
    }

    public function test_two_disciplines_two_rows_one_day_and_second_session_same_key_rejected()
    {
        $this->catalog();
        ['session' => $session, 'participant' => $participant] = $this->activated();
        $this->stage($participant, discipline: 'BDS:1.10');
        $this->stage($participant, discipline: 'BDS:1.20');
        $this->signOff($session);

        $this->assertSame(2, LogEntry::count());
        $totals = collect(ShootingRecord::totals(ShootingRecord::for($this->member)->entries(today()->subYear(), today())))->keyBy('class');
        $this->assertSame(1, $totals['short']['days']);

        $second = $this->sessions->open($this->supervisor);
        $p2 = $this->sessions->addMember($this->supervisor, $second, $this->member);
        $this->assertRejected(fn () => $this->stage($p2, discipline: 'BDS:1.10'), 'entry');
    }

    public function test_race_at_sign_off_signs_the_entry_but_keeps_one_row()
    {
        ['session' => $session, 'participant' => $participant] = $this->activated();
        $entry = $this->stage($participant)['entry'];

        // zwischen Erfassen und Abzeichnen trägt der Vorstand dasselbe nach
        LogEntry::create(['user_id' => $this->member->id, 'shot_on' => today()->toDateString(), 'weapon_class' => 'short', 'weapon_owner' => 'own', 'recorded_by_id' => User::factory()->admin()->create()->id, 'source' => 'late_entry', 'late_reason' => 'Papierliste']);

        $this->assertSame(['created' => 0, 'merged' => 1], $this->signOff($session));
        $this->assertSame(SessionEntryStatus::Signed, $entry->fresh()->status);
        $this->assertSame(1, LogEntry::count());
    }

    public function test_rejected_withdrawn_and_expired_entries_never_reach_the_logbook()
    {
        ['session' => $session, 'participant' => $participant] = $this->activated();
        $rejected = $this->stage($participant, class: WeaponClass::Short)['entry'];
        $withdrawn = $this->stage($participant, class: WeaponClass::Long)['entry'];
        $this->sessions->reject($this->supervisor, $rejected, 'falsch');
        $this->sessions->withdraw($this->supervisor, $withdrawn);
        $this->signOff($session);

        ['participant' => $expired] = $this->activated($this->adult());
        $this->stage($expired);
        $this->travelTo(today()->addDay()->setTime(10, 0));

        $this->assertSame(0, LogEntry::count());
        $this->assertSame(0, LogEntry::where('source', 'session')->count());
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.statistics', ['jahr' => now()->year]))
            ->assertInertia(fn ($page) => $page->where('totals.visits', 0));
    }

    public function test_signed_session_cannot_be_changed_through_the_models()
    {
        ['session' => $session, 'participant' => $participant] = $this->activated();
        $this->stage($participant);
        $this->signOff($session);

        $this->assertRejected(fn () => $this->sessions->addMember($this->supervisor, $session, $this->adult()), 'session');
        $this->assertRejected(fn () => $this->stage($participant, class: WeaponClass::Long), 'entry');
        $this->expectException(LogicException::class);
        $session->fresh()->forceFill(['kiosk_token_hash' => str_repeat('b', 64)])->save();
    }

    // --- Kiosk-Link ---

    public function test_kiosk_token_is_stored_as_hash_replaced_and_expires()
    {
        $session = $this->sessions->open($this->supervisor);

        $this->assertForbidden(fn () => $this->sessions->issueKioskToken(User::factory()->supervisor()->create(), $session));
        $first = $this->sessions->issueKioskToken($this->supervisor, $session);
        $this->assertSame(hash('sha256', $first), $session->fresh()->kiosk_token_hash);
        $this->assertTrue($this->sessions->sessionForKioskToken($first)?->is($session));

        $second = $this->sessions->issueKioskToken($this->supervisor, $session);
        $this->assertNull($this->sessions->sessionForKioskToken($first), 'Ersetzter Link gilt nicht mehr.');
        $this->assertNull($this->sessions->sessionForKioskToken(''));

        $this->travelTo(today()->addDay()->setTime(9, 0));
        $this->assertNull($this->sessions->sessionForKioskToken($second), 'Am Folgetag gilt der Link nicht mehr.');
    }

    public function test_kiosk_token_ends_with_sign_off()
    {
        $session = $this->sessions->open($this->supervisor);
        $token = $this->sessions->issueKioskToken($this->supervisor, $session);
        $this->sessions->signOff($this->supervisor, $session, []);

        $this->assertNull($this->sessions->sessionForKioskToken($token));
    }
}
