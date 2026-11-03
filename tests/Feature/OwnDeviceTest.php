<?php

namespace Tests\Feature;

use App\Domain\Sessions;
use App\Enums\EntryVia;
use App\Enums\SessionEntryStatus;
use App\Enums\SupervisionScope;
use App\Enums\WeaponClass;
use App\Enums\WeaponOwner;
use App\Models\LogEntry;
use App\Models\SessionEntry;
use App\Models\SessionParticipant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Erfassen am eigenen Gerät („Heute schießen“, PR 5) und Ablehnen durch die Inhaberin.
 */
class OwnDeviceTest extends TestCase
{
    use RefreshDatabase;

    private Sessions $sessions;

    private User $supervisor;

    private User $member;

    private const ENTRY = ['weapon_class' => 'short', 'weapon_owner' => 'own', 'shots' => 30];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(18, 0));
        $this->sessions = app(Sessions::class);
        $this->supervisor = User::factory()->supervisor()->create(['name' => 'Aufsicht A']);
        $this->member = User::factory()->create(['adult_confirmed_at' => now()]);
    }

    private function activate(?User $member = null, ?User $supervisor = null): SessionParticipant
    {
        $supervisor ??= $this->supervisor;

        return $this->sessions->addMember($supervisor, $this->sessions->open($supervisor), $member ?? $this->member);
    }

    public function test_dashboard_card_only_after_activation()
    {
        $this->actingAs($this->member)->get(route('dashboard'))->assertInertia(fn ($page) => $page->where('today', null));

        $this->activate();

        $this->actingAs($this->member)->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('today.open', true)->where('today.supervisor', 'Aufsicht A')->where('today.pending', 0));
    }

    public function test_page_without_activation_offers_no_form_and_post_is_refused()
    {
        $this->actingAs($this->member)->get(route('today.show'))->assertInertia(fn ($page) => $page->component('today/Show')->where('participation', null));

        $this->actingAs($this->member)->post(route('today.store'), self::ENTRY)->assertForbidden();
        $this->assertSame(0, SessionEntry::count());
    }

    public function test_member_enters_for_herself_and_sees_the_entry()
    {
        $participant = $this->activate();

        $this->actingAs($this->member)->post(route('today.store'), self::ENTRY)->assertRedirect(route('today.show'))->assertSessionHasNoErrors();

        $entry = SessionEntry::sole();
        $this->assertSame($participant->id, $entry->participant_id);
        $this->assertSame(EntryVia::OwnDevice, $entry->via);
        $this->assertSame($this->member->id, $entry->entered_by_id);
        $this->assertSame(SessionEntryStatus::Pending, $entry->status);
        $this->assertSame(0, LogEntry::count(), 'Vor dem Abzeichnen entsteht keine Kladde-Zeile.');

        $this->actingAs($this->member)->get(route('today.show'))
            ->assertInertia(fn ($page) => $page->where('participation.open', true)->where('canEnterLicensed', true)->has('participation.entries', 1)->where('participation.entries.0.can_withdraw', true));
    }

    public function test_only_for_oneself_even_with_a_foreign_participant_or_user_in_the_request()
    {
        $other = User::factory()->create(['adult_confirmed_at' => now()]);
        $otherParticipant = $this->activate($other);
        $own = $this->activate();

        $this->actingAs($this->member)->post(route('today.store'), [...self::ENTRY, 'participant_id' => $otherParticipant->id, 'user_id' => $other->id])->assertSessionHasNoErrors();

        $this->assertSame($own->id, SessionEntry::sole()->participant_id);
        $this->assertSame(0, $otherParticipant->entries()->count());

        // ein Mitglied ohne Aktivierung kann nicht für andere erfassen
        $stranger = User::factory()->create(['adult_confirmed_at' => now()]);
        $this->actingAs($stranger)->post(route('today.store'), self::ENTRY)->assertForbidden();
    }

    public function test_writes_into_the_latest_activation_of_today()
    {
        $first = $this->activate();
        $this->sessions->signOff($this->supervisor, $first->session, []);
        $second = User::factory()->supervisor()->create();
        $latest = $this->activate(supervisor: $second);

        $this->actingAs($this->member)->post(route('today.store'), self::ENTRY)->assertSessionHasNoErrors();

        $this->assertSame($latest->id, SessionEntry::sole()->participant_id);
    }

    public function test_only_today_in_an_open_session()
    {
        $participant = $this->activate();
        $this->sessions->signOff($this->supervisor, $participant->session, []);

        $this->actingAs($this->member)->post(route('today.store'), self::ENTRY)->assertSessionHasErrors('entry');
        $this->actingAs($this->member)->get(route('today.show'))->assertInertia(fn ($page) => $page->where('participation.open', false)->where('participation.signed', true));

        // Session von gestern: heute keine Aktivierung mehr
        $this->travelTo(now()->addDay()->setTime(18, 0));
        $this->actingAs($this->member)->get(route('today.show'))->assertInertia(fn ($page) => $page->where('participation', null));
        $this->actingAs($this->member)->post(route('today.store'), self::ENTRY)->assertForbidden();
    }

    public function test_expired_session_from_yesterday_refuses_entries()
    {
        $this->activate();
        $this->travelTo(now()->addDay()->setTime(9, 0));

        $this->actingAs($this->member)->post(route('today.store'), self::ENTRY)->assertForbidden();
        $this->assertSame(0, SessionEntry::count());
    }

    public function test_not_while_leading_an_own_open_session()
    {
        $member = User::factory()->supervisor()->create(['adult_confirmed_at' => now()]);
        $this->activate($member);
        $this->sessions->open($member);

        $this->actingAs($member)->post(route('today.store'), self::ENTRY)->assertSessionHasErrors('entry');
        $this->assertSame(0, SessionEntry::count());
    }

    public function test_shots_are_required_between_1_and_999_and_category_and_owner_too()
    {
        $this->activate();

        $this->actingAs($this->member)->post(route('today.store'), [...self::ENTRY, 'shots' => null])->assertSessionHasErrors('shots');
        $this->actingAs($this->member)->post(route('today.store'), [...self::ENTRY, 'shots' => 0])->assertSessionHasErrors('shots');
        $this->actingAs($this->member)->post(route('today.store'), [...self::ENTRY, 'shots' => 1000])->assertSessionHasErrors('shots');
        $this->actingAs($this->member)->post(route('today.store'), [...self::ENTRY, 'weapon_owner' => null])->assertSessionHasErrors('weapon_owner');
        $this->actingAs($this->member)->post(route('today.store'), [...self::ENTRY, 'weapon_class' => null])->assertSessionHasErrors('weapon_class');
        $this->assertSame(0, SessionEntry::count());
    }

    public function test_short_and_long_need_the_licensed_scope_of_the_supervisor()
    {
        $exempt = User::factory()->supervisor(SupervisionScope::Exempt)->create();
        $this->activate(supervisor: $exempt);

        $this->actingAs($this->member)->get(route('today.show'))->assertInertia(fn ($page) => $page->where('canEnterLicensed', false));
        $this->actingAs($this->member)->post(route('today.store'), self::ENTRY)->assertSessionHasErrors('weapon_class');
        $this->actingAs($this->member)->post(route('today.store'), [...self::ENTRY, 'weapon_class' => 'exempt'])->assertSessionHasNoErrors();
    }

    public function test_same_entry_twice_reports_already_entered_with_shots()
    {
        $this->activate();

        $this->actingAs($this->member)->post(route('today.store'), self::ENTRY);
        $this->actingAs($this->member)->post(route('today.store'), [...self::ENTRY, 'shots' => 50])
            ->assertInertiaFlash('toast.type', 'info')
            ->assertInertiaFlash('toast.message', 'Schon erfasst (30 Schüsse) – zurückziehen und neu erfassen?');

        $this->assertSame(30, SessionEntry::sole()->shots);

        // von der Aufsicht erfasst: kein Angebot zum Zurückziehen
        $this->sessions->stage($this->supervisor, SessionEntry::sole()->participant, EntryVia::Stand, WeaponClass::Long, WeaponOwner::Club, 20);
        $this->actingAs($this->member)->post(route('today.store'), ['weapon_class' => 'long', 'weapon_owner' => 'club', 'shots' => 22])
            ->assertInertiaFlash('toast.message', 'Schon erfasst (20 Schüsse) über Aufsicht. Ändern nur über die Aufsicht.');
    }

    public function test_withdraw_own_pending_entry_until_sign_off_and_enter_again()
    {
        $participant = $this->activate();
        $this->actingAs($this->member)->post(route('today.store'), self::ENTRY);
        $entry = SessionEntry::sole();

        $this->actingAs($this->member)->post(route('today.withdraw', $entry))->assertSessionHasNoErrors();
        $this->assertSame(SessionEntryStatus::Withdrawn, $entry->fresh()->status);

        $this->actingAs($this->member)->post(route('today.store'), [...self::ENTRY, 'shots' => 45])->assertSessionHasNoErrors();
        $again = SessionEntry::query()->where('status', 'pending')->sole();
        $this->assertSame(45, $again->shots);

        $this->sessions->signOff($this->supervisor, $participant->session, [$again->id]);
        $this->actingAs($this->member)->post(route('today.withdraw', $again))->assertSessionHasErrors('entry');
        $this->assertSame(SessionEntryStatus::Signed, $again->fresh()->status);
    }

    public function test_cannot_withdraw_entries_of_others_or_made_at_the_stand()
    {
        $participant = $this->activate();
        $other = User::factory()->create(['adult_confirmed_at' => now()]);
        $this->activate($other);

        $this->actingAs($other)->post(route('today.store'), self::ENTRY);
        $foreign = SessionEntry::sole();
        $stand = $this->sessions->stage($this->supervisor, $participant, EntryVia::Stand, WeaponClass::Long, WeaponOwner::Club, 20)['entry'];

        $this->actingAs($this->member)->post(route('today.withdraw', $foreign))->assertForbidden();
        $this->actingAs($this->member)->post(route('today.withdraw', $stand))->assertForbidden();
        $this->assertSame(SessionEntryStatus::Pending, $foreign->fresh()->status);
        $this->assertSame(SessionEntryStatus::Pending, $stand->fresh()->status);

        $this->actingAs($this->member)->get(route('today.show'))->assertInertia(fn ($page) => $page->where('participation.entries.0.can_withdraw', false));
    }

    public function test_rejection_with_reason_is_visible_to_the_member()
    {
        $this->activate();
        $this->actingAs($this->member)->post(route('today.store'), self::ENTRY);
        $entry = SessionEntry::sole();

        $this->actingAs($this->supervisor)->post(route('range-office.session.reject', $entry), ['reason' => 'Falsche Schusszahl'])->assertSessionHasNoErrors();

        $this->actingAs($this->member)->get(route('today.show'))->assertInertia(fn ($page) => $page
            ->where('participation.entries.0.status', 'rejected')
            ->where('participation.entries.0.reject_reason', 'Falsche Schusszahl')
            ->where('participation.entries.0.can_withdraw', false));
        $this->actingAs($this->member)->get(route('dashboard'))->assertInertia(fn ($page) => $page->where('today.rejected', 1));

        // neu erfassen geht nach der Ablehnung
        $this->actingAs($this->member)->post(route('today.store'), [...self::ENTRY, 'shots' => 25])->assertSessionHasNoErrors();
        $this->assertSame(2, SessionEntry::count());
    }

    public function test_reject_only_by_the_owner_and_with_reason_and_offers_quick_reasons()
    {
        $this->activate();
        $this->actingAs($this->member)->post(route('today.store'), self::ENTRY);
        $entry = SessionEntry::sole();

        $this->actingAs($this->member)->post(route('range-office.session.reject', $entry), ['reason' => 'Eigener Grund'])->assertForbidden();
        $this->actingAs($this->supervisor)->post(route('range-office.session.reject', $entry), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->assertSame(SessionEntryStatus::Pending, $entry->fresh()->status);

        $this->actingAs($this->supervisor)->get(route('range-office.show'))->assertInertia(fn ($page) => $page
            ->where('session.participants.0.entries.0.via', EntryVia::OwnDevice->label())
            ->where('session.participants.0.entries.0.can_withdraw', false)
            ->where('rejectReasons', fn ($reasons) => count($reasons) >= 3));
    }

    public function test_member_entry_is_signed_off_by_the_supervisor_into_the_logbook()
    {
        $participant = $this->activate();
        $this->actingAs($this->member)->post(route('today.store'), self::ENTRY);
        $entry = SessionEntry::sole();

        $this->actingAs($this->supervisor)->post(route('range-office.session.sign-off', $participant->session), ['entry_ids' => [$entry->id]])->assertSessionHasNoErrors();

        $row = LogEntry::sole();
        $this->assertSame($this->member->id, $row->user_id);
        $this->assertSame($this->supervisor->id, $row->recorded_by_id);
        $this->assertSame(30, $row->shots);
        $this->assertSame($entry->id, $row->session_entry_id);
    }

    public function test_applicants_and_deactivated_members_are_refused()
    {
        $applicant = User::factory()->applicant()->create();
        $this->actingAs($applicant)->get(route('today.show'))->assertRedirect();
        $this->actingAs($applicant)->post(route('today.store'), self::ENTRY);
        $this->assertSame(0, SessionEntry::count());

        $this->activate();
        $this->member->forceFill(['active' => false])->save();
        $this->actingAs($this->member)->post(route('today.store'), self::ENTRY);
        $this->assertSame(0, SessionEntry::count());
        $this->assertGuest();
    }

    public function test_prefill_from_the_last_own_entry()
    {
        $this->activate();
        $this->actingAs($this->member)->get(route('today.show'))->assertInertia(fn ($page) => $page->where('last', null));

        $this->actingAs($this->member)->post(route('today.store'), ['weapon_class' => 'long', 'weapon_owner' => 'club', 'shots' => 42]);

        $this->actingAs($this->member)->get(route('today.show'))->assertInertia(fn ($page) => $page
            ->where('last.weapon_class', 'long')
            ->where('last.weapon_owner', 'club')
            ->where('last.shots', 42));
    }

    public function test_categories_and_scope_arrive_with_the_poll_after_activation()
    {
        $response = $this->actingAs($this->member)->get(route('today.show'));

        $response->assertInertia(fn ($page) => $page->where('participation', null)
            ->has('weaponClasses', count(WeaponClass::cases()))
            ->has('disciplines')
            ->where('canEnterLicensed', false));

        // Aktivierung, während die Seite offen ist: der Poll lädt nur diese Props nach.
        $this->activate();

        $response->assertInertia(fn ($page) => $page->reloadOnly(
            ['participation', 'canEnterLicensed', 'last', 'weaponClasses', 'disciplines'],
            fn ($reload) => $reload->where('participation.open', true)
                ->has('weaponClasses', count(WeaponClass::cases()))
                ->where('canEnterLicensed', true),
        ));
    }

    public function test_record_lists_own_entries_that_did_not_make_it_into_the_record()
    {
        $participant = $this->activate();
        $expired = $this->sessions->stage($this->member, $participant, EntryVia::OwnDevice, WeaponClass::Exempt, WeaponOwner::Own, 30)['entry'];
        $withdrawn = $this->sessions->stage($this->member, $participant, EntryVia::OwnDevice, WeaponClass::Short, WeaponOwner::Own, 20)['entry'];
        $this->sessions->withdraw($this->member, $withdrawn);
        $rejected = $this->sessions->stage($this->member, $participant, EntryVia::OwnDevice, WeaponClass::Long, WeaponOwner::Club, 10)['entry'];
        $this->sessions->reject($this->supervisor, $rejected, 'falsche Kategorie');

        // fremde Einträge erscheinen nie
        $other = User::factory()->create(['adult_confirmed_at' => now()]);
        $this->sessions->stage($other, $this->activate($other), EntryVia::OwnDevice, WeaponClass::Exempt, WeaponOwner::Own, 5);

        // am Session-Tag ist der offene Eintrag noch nicht verfallen
        $this->actingAs($this->member)->get(route('record.index'))
            ->assertInertia(fn ($page) => $page->has('notRecorded', 2));

        // nicht abgezeichnet: am Folgetag verfallen
        $this->travelTo(now()->addDay()->setTime(10, 0));

        $this->actingAs($this->member)->get(route('record.index'))
            ->assertInertia(fn ($page) => $page->component('record/Index')
                ->has('entries', 0)
                ->has('notRecorded', 3)
                ->where('notRecorded.0.id', $rejected->id)
                ->where('notRecorded.0.status', 'rejected')
                ->where('notRecorded.0.reject_reason', 'falsche Kategorie')
                ->where('notRecorded.0.rejected_by', 'Aufsicht A')
                ->where('notRecorded.1.id', $withdrawn->id)
                ->where('notRecorded.1.status_label', 'zurückgezogen')
                ->where('notRecorded.2.id', $expired->id)
                ->where('notRecorded.2.status_label', 'verfallen (nicht abgezeichnet)')
                ->where('notRecorded.2.shots', 30));

        // außerhalb des Zeitraums nicht
        $this->actingAs($this->member)->get(route('record.index', ['from' => now()->toDateString(), 'to' => now()->toDateString()]))
            ->assertInertia(fn ($page) => $page->has('notRecorded', 0));
    }
}
