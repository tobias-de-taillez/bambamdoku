<?php

namespace Tests\Feature;

use App\Domain\Bookings;
use App\Domain\Sessions;
use App\Domain\TimeWindow;
use App\Enums\EntryVia;
use App\Enums\SupervisionScope;
use App\Enums\WeaponClass;
use App\Enums\WeaponOwner;
use App\Enums\WeaponType;
use App\Models\Qualification;
use App\Models\Range;
use App\Models\SessionParticipant;
use App\Models\ShootingDay;
use App\Models\ShootingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * „Am Stand“ (/stand) und der Stand im Aufsichtsregister (V6, G9), geprüft an den Inertia-Props:
 * Nach Entzug oder Ablauf gibt es statt der Session nur `lockedSession` mit Startzeit und Zahl der
 * offenen Einträge; nach einer Herabstufung ist `canEnterLicensed` falsch und der reine Kurzwaffenstand
 * fehlt; nach dem Wiedereintrag ist die Session zurück. Das Polling lädt `canEnter`, `canEnterLicensed`
 * und `lockedSession` mit. Was die Seite im Browser daraus macht, prüft der E2E-Test.
 */
class RangeOfficeRegisterTest extends TestCase
{
    use RefreshDatabase;

    /** Was range-office/Show.svelte mit usePoll nachlädt */
    private const POLL = ['ranges', 'now', 'arrivalUrl', 'session', 'signedSessions', 'canEnter', 'canEnterLicensed', 'lockedSession'];

    private Sessions $sessions;

    private Range $pistol;

    private Range $air;

    private User $helga;

    protected function setUp(): void
    {
        parent::setUp();

        config(['verein.slot_minutes' => 60]);
        $this->travelTo(now()->setTime(18, 0));
        $this->sessions = app(Sessions::class);
        $this->pistol = Range::factory()->create(['name' => '25 m Kurzwaffe', 'lanes' => 3, 'position' => 1, 'weapon_types' => [WeaponType::Pistol]]);
        $this->air = Range::factory()->create(['name' => '10 m Luft', 'lanes' => 3, 'position' => 2, 'weapon_types' => [WeaponType::Airgun]]);
        $this->helga = User::factory()->supervisor()->create(['name' => 'Helga Schießwart']);
    }

    /**
     * Heutiger Schießtag mit beiden Ständen; die Aufsicht trägt sich für die ganze Anlage ein.
     */
    private function supervisedDay(User $supervisor): ShootingDay
    {
        $day = ShootingDay::factory()->create(['date' => today()->toDateString(), 'starts_at' => '17:00', 'ends_at' => '21:00']);
        $day->ranges()->attach([$this->pistol->id, $this->air->id]);
        app(Bookings::class)->assignSupervision($supervisor, $day->fresh(), $supervisor, null, TimeWindow::fromStrings('17:00', '21:00'));

        return $day->fresh();
    }

    private function register(User $user, SupervisionScope $scope, ?string $until = null, ?string $note = null): void
    {
        Qualification::register($user, $scope, $until ?? now()->addYear()->toDateString(), now()->toDateString(), User::factory()->admin()->create(), $note);
    }

    private function revoke(User $user): void
    {
        Qualification::register($user, SupervisionScope::Revoked, null, now()->toDateString(), User::factory()->admin()->create(), 'entzogen');
    }

    private function activate(ShootingSession $session): SessionParticipant
    {
        return $this->sessions->addMember($this->helga, $session, User::factory()->create(['adult_confirmed_at' => now()]));
    }

    private function stage(SessionParticipant $participant, WeaponClass $class): void
    {
        $this->sessions->stage(actor: $this->helga, participant: $participant, via: EntryVia::Stand, class: $class, owner: WeaponOwner::Own, shots: 30);
    }

    /**
     * Teilweises Nachladen wie usePoll auf /stand.
     */
    private function poll(User $user): TestResponse
    {
        $version = $this->actingAs($user)->get(route('range-office.show'))->viewData('page')['version'];

        return $this->actingAs($user)->get(route('range-office.show'), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $version,
            'X-Inertia-Partial-Component' => 'range-office/Show',
            'X-Inertia-Partial-Data' => implode(',', self::POLL),
        ])->assertOk();
    }

    public function test_revocation_locks_the_open_session_with_its_pending_entries()
    {
        $this->supervisedDay($this->helga);
        $session = $this->sessions->open($this->helga);
        $anna = $this->activate($session);
        $bert = $this->activate($session);
        $this->stage($anna, WeaponClass::Short);
        $this->stage($anna, WeaponClass::Exempt);
        $this->stage($bert, WeaponClass::Exempt);
        $this->sessions->reject($this->helga, $bert->entries()->sole(), 'Nicht geschossen');

        $this->actingAs($this->helga)->get(route('range-office.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canEnter', true)
                ->where('session.id', $session->id)
                ->where('lockedSession', null)
                ->has('ranges', 2));

        $this->revoke($this->helga);

        // Gezählt werden nur offene Einträge, nicht der abgelehnte.
        $this->actingAs($this->helga)->get(route('range-office.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canEnter', false)
                ->where('canEnterLicensed', false)
                ->where('session', null)
                ->where('members', [])
                ->has('ranges', 0)
                ->where('lockedSession', ['started_at' => '18:00', 'pending' => 2]));
    }

    public function test_polling_reloads_register_state_and_locked_session()
    {
        $this->supervisedDay($this->helga);
        $session = $this->sessions->open($this->helga);
        $this->stage($this->activate($session), WeaponClass::Exempt);
        $this->revoke($this->helga);

        $response = $this->poll($this->helga)
            ->assertJsonPath('props.canEnter', false)
            ->assertJsonPath('props.canEnterLicensed', false)
            ->assertJsonPath('props.session', null)
            ->assertJsonPath('props.ranges', [])
            ->assertJsonPath('props.lockedSession.started_at', '18:00')
            ->assertJsonPath('props.lockedSession.pending', 1)
            ->assertJsonMissingPath('props.members');

        // Jede nachgeladene Prop kommt mit, auch eine, die null ist.
        foreach (self::POLL as $prop) {
            $this->assertArrayHasKey($prop, $response->json('props'));
        }
    }

    public function test_exempt_supervisor_after_revocation_is_no_longer_allowed_at_all()
    {
        $ben = User::factory()->supervisor(SupervisionScope::Exempt)->create(['name' => 'Ben Bogen']);
        $this->supervisedDay($ben);

        // Vorher: nur der Luftdruckstand, ohne Umfang erlaubnispflichtig
        $this->poll($ben)
            ->assertJsonPath('props.canEnter', true)
            ->assertJsonPath('props.canEnterLicensed', false)
            ->assertJsonPath('props.ranges.0.name', '10 m Luft')
            ->assertJsonCount(1, 'props.ranges');

        $this->revoke($ben);

        // Danach fehlt die Berechtigung ganz, nicht nur der Umfang.
        $this->poll($ben)
            ->assertJsonPath('props.canEnter', false)
            ->assertJsonPath('props.canEnterLicensed', false)
            ->assertJsonPath('props.ranges', [])
            ->assertJsonPath('props.lockedSession', null);
    }

    public function test_downgrade_clears_licensed_scope_and_the_pure_pistol_range()
    {
        $this->supervisedDay($this->helga);
        $session = $this->sessions->open($this->helga);

        $this->poll($this->helga)
            ->assertJsonPath('props.canEnterLicensed', true)
            ->assertJsonCount(2, 'props.ranges');

        $this->register($this->helga, SupervisionScope::Exempt, note: 'nur noch erlaubnisfrei');

        // Die Session bleibt; nur der Kurzwaffenstand und der Umfang fallen weg.
        $this->poll($this->helga)
            ->assertJsonPath('props.canEnter', true)
            ->assertJsonPath('props.canEnterLicensed', false)
            ->assertJsonCount(1, 'props.ranges')
            ->assertJsonPath('props.ranges.0.name', '10 m Luft')
            ->assertJsonPath('props.session.id', $session->id)
            ->assertJsonPath('props.lockedSession', null);
    }

    public function test_reinstatement_brings_the_session_back()
    {
        $this->supervisedDay($this->helga);
        $session = $this->sessions->open($this->helga);
        $this->stage($this->activate($session), WeaponClass::Short);
        $this->revoke($this->helga);

        $this->poll($this->helga)->assertJsonPath('props.lockedSession.pending', 1);

        $this->register($this->helga, SupervisionScope::Licensed);

        $response = $this->poll($this->helga)
            ->assertJsonPath('props.canEnter', true)
            ->assertJsonPath('props.canEnterLicensed', true)
            ->assertJsonPath('props.session.id', $session->id)
            ->assertJsonPath('props.session.participants.0.entries.0.status', 'pending')
            ->assertJsonCount(2, 'props.ranges');

        // lockedSession kommt als null mit, sonst behielte der Client beim Polling den alten Wert.
        $this->assertArrayHasKey('lockedSession', $response->json('props'));
        $this->assertNull($response->json('props.lockedSession'));
    }
}
