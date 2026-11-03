<?php

namespace Tests\Feature;

use App\Domain\Sessions;
use App\Enums\EntryVia;
use App\Enums\SessionEntryStatus;
use App\Enums\SessionStatus;
use App\Enums\SupervisionScope;
use App\Enums\WeaponClass;
use App\Enums\WeaponOwner;
use App\Models\Qualification;
use App\Models\SessionEntry;
use App\Models\SessionParticipant;
use App\Models\ShootingSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * V6: Verliert die Aufsicht während der Session ihre Registerzeile (Entzug, Ablauf), nimmt die Session
 * auf keinem Weg mehr Einträge an. Offene Einträge bleiben stehen und verfallen um Mitternacht.
 */
class SessionSupervisorRevokedTest extends TestCase
{
    use RefreshDatabase;

    private const PIN = '4711';

    private const ENTRY = ['weapon_class' => 'short', 'weapon_owner' => 'own', 'shots' => 30];

    private Sessions $sessions;

    private User $supervisor;

    private User $member;

    private ShootingSession $session;

    private SessionParticipant $participant;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(18, 0));
        $this->sessions = app(Sessions::class);
        $this->supervisor = User::factory()->supervisor()->create(['name' => 'Aufsicht A']);
        $this->member = User::factory()->create(['name' => 'Erika Muster', 'adult_confirmed_at' => now(), 'kiosk_pin_hash' => Hash::make(self::PIN)]);
        $this->session = $this->sessions->open($this->supervisor);
        $this->participant = $this->sessions->addMember($this->supervisor, $this->session, $this->member);
        $this->token = $this->sessions->issueKioskToken($this->supervisor, $this->session);
    }

    private function revoke(): void
    {
        Qualification::register($this->supervisor, SupervisionScope::Revoked, null, now()->toDateString(), User::factory()->admin()->create(), 'entzogen');
    }

    private function register(SupervisionScope $scope, string $until): void
    {
        Qualification::register($this->supervisor, $scope, $until, now()->toDateString(), User::factory()->admin()->create());
    }

    private function kiosk(array $data = [])
    {
        return $this->post(route('kiosk.store', $this->token), [...self::ENTRY, 'participant_id' => $this->participant->id, 'pin' => self::PIN, ...$data]);
    }

    public function test_kiosk_shows_no_list_after_revocation_and_switches_back_after_new_registration()
    {
        $this->get(route('kiosk.show', $this->token))->assertInertia(fn (Assert $page) => $page->where('unregistered', false)->has('participants', 1));

        $this->revoke();

        $this->get(route('kiosk.show', $this->token))
            ->assertOk()
            ->assertDontSee('Erika Muster')
            ->assertInertia(fn (Assert $page) => $page
                ->component('kiosk/Show')
                ->where('ended', false)
                ->where('unregistered', true)
                ->has('participants', 0)
                ->where('token', $this->token));

        $this->register(SupervisionScope::Licensed, now()->addYear()->toDateString());

        $this->get(route('kiosk.show', $this->token))->assertInertia(fn (Assert $page) => $page->where('unregistered', false)->has('participants', 1));
    }

    public function test_kiosk_refuses_pin_and_entry_after_revocation()
    {
        $this->revoke();

        $this->post(route('kiosk.verify', $this->token), ['participant_id' => $this->participant->id, 'pin' => self::PIN])
            ->assertRedirect(route('kiosk.show', $this->token))
            ->assertInertiaFlashMissing('kiosk');

        $this->kiosk()->assertSessionHasErrors(['entry' => Sessions::SUPERVISOR_NOT_REGISTERED]);
        $this->kiosk(['weapon_class' => 'exempt'])->assertSessionHasErrors(['entry' => Sessions::SUPERVISOR_NOT_REGISTERED]);
        $this->assertSame(0, SessionEntry::count());
    }

    public function test_own_device_refuses_entry_after_revocation_with_message()
    {
        $this->revoke();

        $this->actingAs($this->member)->post(route('today.store'), self::ENTRY)
            ->assertRedirect()
            ->assertSessionHasErrors(['entry' => Sessions::SUPERVISOR_NOT_REGISTERED]);
        $this->actingAs($this->member)->post(route('today.store'), [...self::ENTRY, 'weapon_class' => 'exempt'])
            ->assertSessionHasErrors(['entry' => Sessions::SUPERVISOR_NOT_REGISTERED]);
        $this->assertSame(0, SessionEntry::count());
    }

    public function test_supervisor_cannot_stage_or_enter_after_revocation()
    {
        $guest = $this->sessions->addGuest($this->supervisor, $this->session, 'Gerda Gast', true);
        $this->revoke();

        $this->actingAs($this->supervisor)->post(route('range-office.session.stage', $this->participant), self::ENTRY)->assertSessionHasErrors('entry');
        $this->actingAs($this->supervisor)->post(route('range-office.session.stage', $guest), ['weapon_class' => 'exempt'])->assertSessionHasErrors('entry');
        $this->actingAs($this->supervisor)->post(route('range-office.enter'), ['user_id' => $this->member->id, ...self::ENTRY])->assertSessionHasErrors('session');
        $this->assertSame(0, SessionEntry::count());
    }

    public function test_wrong_actor_still_gets_403_not_the_register_message()
    {
        $this->revoke();
        $other = User::factory()->supervisor()->create();

        $this->actingAs($other)->post(route('range-office.session.stage', $this->participant), self::ENTRY)->assertForbidden();
        $this->assertSame(0, SessionEntry::count());
    }

    public function test_expired_registration_blocks_like_revocation()
    {
        $this->register(SupervisionScope::Licensed, now()->subDay()->toDateString());

        $this->assertFalse($this->sessions->supervisorRegistered($this->session));
        $this->get(route('kiosk.show', $this->token))->assertInertia(fn (Assert $page) => $page->where('unregistered', true));
        $this->actingAs($this->member)->post(route('today.store'), self::ENTRY)->assertSessionHasErrors(['entry' => Sessions::SUPERVISOR_NOT_REGISTERED]);
        $this->assertSame(0, SessionEntry::count());
    }

    public function test_deactivated_supervisor_blocks_entries()
    {
        $this->supervisor->forceFill(['active' => false])->save();

        $this->kiosk()->assertSessionHasErrors(['entry' => Sessions::SUPERVISOR_NOT_REGISTERED]);
        $this->assertSame(0, SessionEntry::count());
    }

    public function test_open_entries_stay_pending_and_visible_then_expire_at_midnight()
    {
        $this->kiosk()->assertSessionHasNoErrors();
        $entry = SessionEntry::sole();
        $this->revoke();

        $this->kiosk(['weapon_class' => 'long'])->assertSessionHasErrors('entry');

        $this->assertSame(SessionEntryStatus::Pending, $entry->fresh()->status);
        $this->assertSame(SessionStatus::Open, $this->session->fresh()->status);
        $this->assertTrue($this->session->fresh()->isOpenToday());

        // Das Mitglied sieht den offenen Eintrag, der Vorstand die offene Session.
        $this->actingAs($this->member)->get(route('today.show'))
            ->assertInertia(fn (Assert $page) => $page->where('participation.open', true)->has('participation.entries', 1)->where('participation.entries.0.status', 'pending'));
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.record.index'))
            ->assertInertia(fn (Assert $page) => $page->where('sessions.0.state', 'open')->where('sessions.0.pending', 1));

        // Um Mitternacht verfällt die Session wie bisher.
        $this->travelTo(now()->addDay()->setTime(9, 0));

        $this->assertTrue($this->session->fresh()->isExpired());
        $this->assertSame(SessionEntryStatus::Pending, $entry->fresh()->status);
        $this->actingAs($admin)->get(route('admin.record.index'))
            ->assertInertia(fn (Assert $page) => $page->where('sessions.0.state', 'expired')->where('sessions.0.pending', 1));
        $this->assertSame([$entry->id], $this->sessions->entriesOutsideRecord($this->member, CarbonImmutable::today()->subWeek(), CarbonImmutable::today())->pluck('id')->all());
    }

    public function test_licensed_to_exempt_keeps_exempt_entries_and_refuses_short_on_the_scope()
    {
        $this->register(SupervisionScope::Exempt, now()->addYear()->toDateString());

        $this->assertTrue($this->sessions->supervisorRegistered($this->session));
        $this->get(route('kiosk.show', $this->token))->assertInertia(fn (Assert $page) => $page->where('unregistered', false)->where('canEnterLicensed', false));

        $this->kiosk()->assertSessionHasErrors('weapon_class')->assertSessionDoesntHaveErrors('entry');
        $this->kiosk(['weapon_class' => 'exempt'])->assertSessionHasNoErrors();

        $entry = SessionEntry::sole();
        $this->assertSame(WeaponClass::Exempt, $entry->weapon_class);
        $this->assertSame(EntryVia::Kiosk, $entry->via);
    }

    public function test_domain_refuses_every_path_after_revocation()
    {
        $this->revoke();

        foreach ([[$this->member, EntryVia::OwnDevice], [$this->member, EntryVia::Kiosk], [$this->supervisor, EntryVia::Stand]] as [$actor, $via]) {
            try {
                $this->sessions->stage($actor, $this->participant, $via, WeaponClass::Exempt, WeaponOwner::Own, 10);
                $this->fail('Eintrag über '.$via->value.' wurde nicht abgewiesen.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('entry', $e->errors());
            }
        }

        $this->assertSame(0, SessionEntry::count());
    }
}
