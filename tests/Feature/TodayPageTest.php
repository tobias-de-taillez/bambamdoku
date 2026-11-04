<?php

namespace Tests\Feature;

use App\Domain\Sessions;
use App\Enums\SupervisionScope;
use App\Models\Qualification;
use App\Models\SessionEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * „Heute schießen“ nach dem Entzug oder Ablauf der Registerzeile der Aufsicht (V6, Rollen-Rundgang V6b),
 * geprüft an den Inertia-Props: Die Seite meldet `unregistered`, auch beim Polling. Die Antwort auf ein
 * abgewiesenes Erfassen trägt Meldung und geänderte Props zugleich und lässt die Aktivierung
 * (`participation.id`) unverändert; eine neue Aktivierung bringt eine neue Kennung. Was das Formular im
 * Browser daraus macht, prüft der E2E-Test.
 */
class TodayPageTest extends TestCase
{
    use RefreshDatabase;

    private const ENTRY = ['weapon_class' => 'short', 'weapon_owner' => 'own', 'shots' => 30];

    private Sessions $sessions;

    private User $supervisor;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(18, 0));
        $this->sessions = app(Sessions::class);
        $this->supervisor = User::factory()->supervisor()->create(['name' => 'Aufsicht A']);
        $this->member = User::factory()->create(['adult_confirmed_at' => now()]);
    }

    private function activate(): void
    {
        $this->sessions->addMember($this->supervisor, $this->sessions->open($this->supervisor), $this->member);
    }

    private function register(SupervisionScope $scope, ?string $until, ?string $note = null): void
    {
        Qualification::register($this->supervisor, $scope, $until, now()->toDateString(), User::factory()->admin()->create(), $note);
    }

    /**
     * Erfassen wie das Formular: zurück auf die Seite, deren Props die Antwort setzt.
     */
    private function submit(array $data = [])
    {
        return $this->actingAs($this->member)->from(route('today.show'))->followingRedirects()->post(route('today.store'), [...self::ENTRY, ...$data]);
    }

    public function test_without_activation_the_page_is_not_unregistered()
    {
        $this->actingAs($this->member)->get(route('today.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('today/Show')
                ->where('participation', null)
                ->where('unregistered', false));
    }

    public function test_unregistered_follows_the_register_of_the_supervisor()
    {
        $this->activate();

        $this->actingAs($this->member)->get(route('today.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('participation.open', true)
                ->where('unregistered', false)
                ->where('canEnterLicensed', true));

        $this->register(SupervisionScope::Revoked, null, 'entzogen');

        // Die Session bleibt offen; nur Einträge nimmt sie nicht mehr an.
        $this->actingAs($this->member)->get(route('today.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('participation.open', true)
                ->where('participation.supervisor', 'Aufsicht A')
                ->where('unregistered', true)
                ->where('canEnterLicensed', false));

        $this->register(SupervisionScope::Exempt, now()->addYear()->toDateString());

        $this->actingAs($this->member)->get(route('today.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('unregistered', false)
                ->where('canEnterLicensed', false));
    }

    public function test_expired_register_counts_as_unregistered()
    {
        $this->activate();
        $this->register(SupervisionScope::Licensed, now()->subDay()->toDateString());

        $this->actingAs($this->member)->get(route('today.show'))
            ->assertInertia(fn (Assert $page) => $page->where('unregistered', true));
    }

    public function test_polling_reloads_unregistered()
    {
        $this->activate();
        $this->register(SupervisionScope::Revoked, null, 'entzogen');
        $version = $this->actingAs($this->member)->get(route('today.show'))->viewData('page')['version'];

        // Teilweises Nachladen wie usePoll: nur die angeforderten Props kommen mit
        $this->actingAs($this->member)->get(route('today.show'), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $version,
            'X-Inertia-Partial-Component' => 'today/Show',
            'X-Inertia-Partial-Data' => 'participation,canEnterLicensed,unregistered,last,weaponClasses,disciplines',
        ])
            ->assertOk()
            ->assertJsonPath('props.unregistered', true)
            ->assertJsonPath('props.participation.open', true);
    }

    /**
     * Aufsicht mit Umfang „erlaubnispflichtig“ verliert das Register: Die Antwort auf das erste Erfassen
     * bringt die Meldung und zugleich canEnterLicensed = false und unregistered = true.
     */
    public function test_rejected_entry_after_revocation_carries_message_and_locked_state()
    {
        $this->activate();
        $this->register(SupervisionScope::Revoked, null, 'entzogen');

        $this->submit()
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('today/Show')
                ->where('errors.entry', Sessions::SUPERVISOR_NOT_REGISTERED)
                ->where('unregistered', true)
                ->where('canEnterLicensed', false)
                ->where('participation.open', true)
                ->has('participation.entries', 0));

        $this->assertSame(0, SessionEntry::count());
    }

    /**
     * Umfang auf „erlaubnisfrei“ zurückgestuft, während Kurzwaffe gewählt ist: Die Meldung zur Kategorie
     * kommt mit canEnterLicensed = false, die Aktivierung (`participation.id`) bleibt dieselbe.
     */
    public function test_rejected_entry_after_scope_downgrade_carries_message_and_new_scope()
    {
        $this->activate();
        $participation = $this->sessions->latestParticipation($this->member)?->id;
        $this->register(SupervisionScope::Exempt, now()->addYear()->toDateString());

        $this->submit()
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('today/Show')
                ->where('errors.weapon_class', 'Für Kurz- und Langwaffen braucht die Aufsicht der Session den Umfang „erlaubnispflichtige Waffen“ im Register.')
                ->missing('errors.entry')
                ->where('unregistered', false)
                ->where('canEnterLicensed', false)
                ->where('participation.id', $participation));

        $this->submit(['weapon_class' => 'exempt'])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('errors', [])
                ->has('participation.entries', 1)
                ->where('participation.entries.0.weapon_class', 'erlaubnisfrei'));
    }

    /**
     * Neue Aktivierung bei einer anderen Aufsicht (erst Ben mit Umfang erlaubnisfrei, dann Helga mit
     * erlaubnispflichtig): `participation.id` wechselt mit Aufsicht und Umfang, auch beim Polling.
     */
    public function test_new_activation_brings_a_new_participation_id()
    {
        $ben = User::factory()->supervisor(SupervisionScope::Exempt)->create(['name' => 'Ben Bogen']);
        $first = $this->sessions->addMember($ben, $this->sessions->open($ben), $this->member);

        $this->actingAs($this->member)->get(route('today.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('participation.id', $first->id)
                ->where('participation.supervisor', 'Ben Bogen')
                ->where('canEnterLicensed', false));

        $this->travel(5)->minutes();
        $second = $this->sessions->addMember($this->supervisor, $this->sessions->open($this->supervisor), $this->member);
        $this->assertNotSame($first->id, $second->id);
        $version = $this->actingAs($this->member)->get(route('today.show'))->viewData('page')['version'];

        $this->actingAs($this->member)->get(route('today.show'), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $version,
            'X-Inertia-Partial-Component' => 'today/Show',
            'X-Inertia-Partial-Data' => 'participation,canEnterLicensed,unregistered,last,weaponClasses,disciplines',
        ])
            ->assertOk()
            ->assertJsonPath('props.participation.id', $second->id)
            ->assertJsonPath('props.participation.supervisor', 'Aufsicht A')
            ->assertJsonPath('props.participation.open', true)
            ->assertJsonPath('props.canEnterLicensed', true);
    }

    public function test_page_shows_entries_after_revocation()
    {
        $this->activate();
        $this->submit(['weapon_class' => 'exempt'])->assertOk();
        $this->register(SupervisionScope::Revoked, null, 'entzogen');

        $this->actingAs($this->member)->get(route('today.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('unregistered', true)
                ->has('participation.entries', 1)
                ->where('participation.entries.0.status', 'pending')
                ->where('participation.entries.0.can_withdraw', true));
    }
}
