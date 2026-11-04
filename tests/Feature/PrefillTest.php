<?php

namespace Tests\Feature;

use App\Domain\Logbook;
use App\Domain\Sessions;
use App\Enums\EntryVia;
use App\Enums\WeaponClass;
use App\Enums\WeaponOwner;
use App\Models\LogEntry;
use App\Models\SessionEntry;
use App\Models\ShootingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Vorbelegung des Formulars („Heute schießen“, Kiosk, Stand) nur aus Einträgen, die zählen oder noch
 * zählen können (Rollen-Rundgang: Kiosk mit 999 aus einem abgelehnten Eintrag vorbelegt).
 */
class PrefillTest extends TestCase
{
    use RefreshDatabase;

    private const PIN = '4711';

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
        $this->member = User::factory()->create(['name' => 'Anna Muster', 'adult_confirmed_at' => now(), 'kiosk_pin_hash' => Hash::make(self::PIN)]);
        $this->admin = User::factory()->admin()->create(['name' => 'Petra Vorstand']);
    }

    /**
     * Ein Eintrag am eigenen Gerät in einer neuen Session der Aufsicht; auf Wunsch abgezeichnet.
     */
    private function stage(WeaponClass $class, WeaponOwner $owner, int $shots, bool $sign = false): SessionEntry
    {
        $session = $this->sessions->openSessionOf($this->supervisor) ?? $this->sessions->open($this->supervisor);
        $participant = $this->sessions->addMember($this->supervisor, $session, $this->member);
        $entry = $this->sessions->stage($this->member, $participant, EntryVia::OwnDevice, $class, $owner, $shots)['entry'];

        if ($sign) {
            $this->sessions->signOff($this->supervisor, $session, [$entry->id]);
        }

        return $entry;
    }

    private function rowOf(SessionEntry $entry): LogEntry
    {
        return LogEntry::where('session_entry_id', $entry->id)->firstOrFail();
    }

    /**
     * @param  array{0: string, 1: string, 2: int}  $expected  Kategorie, eigen/Verein, Schüsse
     */
    private function assertTodayPrefill(array $expected): void
    {
        $this->actingAs($this->member)->get(route('today.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('last.weapon_class', $expected[0])
                ->where('last.weapon_owner', $expected[1])
                ->where('last.shots', $expected[2]));
    }

    public function test_rejected_and_withdrawn_entries_do_not_prefill()
    {
        $this->stage(WeaponClass::Long, WeaponOwner::Own, 40, sign: true);
        $typo = $this->stage(WeaponClass::Short, WeaponOwner::Club, 999);
        $this->sessions->reject($this->supervisor, $typo, 'Fehleingabe: 999 Schüsse');

        $this->assertTodayPrefill(['long', 'own', 40]);

        // Ein offener Eintrag der heute offenen Session zählt noch und belegt vor.
        $pending = $this->stage(WeaponClass::Exempt, WeaponOwner::Club, 15);
        $this->assertTodayPrefill(['exempt', 'club', 15]);

        $this->sessions->withdraw($this->member, $pending);
        $this->assertTodayPrefill(['long', 'own', 40]);
    }

    public function test_kiosk_prefills_from_the_same_source_without_the_rejected_entry()
    {
        $this->stage(WeaponClass::Long, WeaponOwner::Own, 40, sign: true);
        $typo = $this->stage(WeaponClass::Short, WeaponOwner::Club, 999);
        $this->sessions->reject($this->supervisor, $typo, 'Fehleingabe: 999 Schüsse');

        $session = $this->sessions->openSessionOf($this->supervisor);
        $this->assertInstanceOf(ShootingSession::class, $session);
        $participant = $session->participants()->where('user_id', $this->member->id)->sole();
        $token = $this->sessions->issueKioskToken($this->supervisor, $session);

        $this->post(route('kiosk.verify', $token), ['participant_id' => $participant->id, 'pin' => self::PIN])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('kiosk.last.weapon_class', 'long')
            ->assertInertiaFlash('kiosk.last.weapon_owner', 'own')
            ->assertInertiaFlash('kiosk.last.shots', 40);
    }

    /**
     * Nach dem Abzeichnen gilt die Kladde: ein storniertes Ergebnis belegt nicht vor, eine Korrektur schon (K8).
     */
    public function test_after_sign_off_the_record_decides_with_cancel_and_correction()
    {
        $long = $this->stage(WeaponClass::Long, WeaponOwner::Own, 40, sign: true);
        $short = $this->stage(WeaponClass::Short, WeaponOwner::Club, 25, sign: true);
        $this->assertTodayPrefill(['short', 'club', 25]);

        $this->logbook->cancel($this->admin, $this->rowOf($short), 'Nicht geschossen');
        $this->assertTodayPrefill(['long', 'own', 40]);

        $this->logbook->correct($this->admin, $this->rowOf($long), WeaponClass::Long, WeaponOwner::Club, null, 45, 'Schusszahl falsch erfasst');
        $this->assertTodayPrefill(['long', 'club', 45]);
    }

    /**
     * Ein offener Eintrag einer Session, die nicht am selben Tag abgezeichnet wurde, ist verfallen und zählt nie.
     */
    public function test_pending_entry_of_an_expired_session_does_not_prefill()
    {
        $this->stage(WeaponClass::Long, WeaponOwner::Own, 40, sign: true);
        $this->stage(WeaponClass::Short, WeaponOwner::Club, 30);
        $this->assertTodayPrefill(['short', 'club', 30]);

        $this->travel(1)->day();
        $this->assertTodayPrefill(['long', 'own', 40]);
    }

    /**
     * Am Stand kommt die Vorbelegung aus der Kladde (K3). Ist die jüngste Zeile storniert, gilt die davor,
     * statt gar keiner Vorbelegung.
     */
    public function test_stand_prefill_skips_a_cancelled_newest_row()
    {
        $this->stage(WeaponClass::Long, WeaponOwner::Own, 40, sign: true);
        $short = $this->stage(WeaponClass::Short, WeaponOwner::Club, 25, sign: true);
        $this->logbook->cancel($this->admin, $this->rowOf($short), 'Nicht geschossen');

        $this->actingAs($this->supervisor)->get(route('range-office.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canEnter', true)
                ->where('members.0.id', $this->member->id)
                ->where('members.0.last.weapon_class', 'long')
                ->where('members.0.last.weapon_owner', 'own')
                ->where('members.0.last.shots', 40));
    }

    /**
     * Eine Korrektur oder ein Nachtrag für einen älteren Tag ist eine neue Zeile mit höherer id. Vorbelegt
     * wird trotzdem der jüngste Schießtag, am Stand wie auf „Heute schießen“.
     */
    public function test_correction_and_late_entry_for_an_older_day_do_not_replace_the_latest_visit()
    {
        $this->travel(-10)->days();
        $old = $this->stage(WeaponClass::Short, WeaponOwner::Club, 25, sign: true);
        $this->travel(10)->days();
        $this->stage(WeaponClass::Long, WeaponOwner::Own, 40, sign: true);

        $this->logbook->correct($this->admin, $this->rowOf($old), WeaponClass::Short, WeaponOwner::Club, null, 20, 'Schusszahl falsch erfasst');
        $this->logbook->lateEntries($this->admin, [$this->member], now()->subWeek()->toDateString(), WeaponClass::Short, WeaponOwner::Club, null, 'Papierliste nachgetragen', 30);

        $this->assertTodayPrefill(['long', 'own', 40]);

        $this->actingAs($this->supervisor)->get(route('range-office.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('members.0.id', $this->member->id)
                ->where('members.0.last.weapon_class', 'long')
                ->where('members.0.last.weapon_owner', 'own')
                ->where('members.0.last.shots', 40));
    }
}
