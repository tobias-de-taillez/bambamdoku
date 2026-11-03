<?php

namespace Tests\Feature;

use App\Domain\Logbook;
use App\Domain\SessionProtocol;
use App\Domain\Sessions;
use App\Enums\EntryVia;
use App\Enums\WeaponClass;
use App\Enums\WeaponOwner;
use App\Models\LogEntry;
use App\Models\ShootingDay;
use App\Models\ShootingSession;
use App\Models\Supervision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Session-Protokoll (c), PR 9: nur für den Admin (E14).
 */
class SessionProtocolTest extends TestCase
{
    use RefreshDatabase;

    private Sessions $sessions;

    private User $supervisor;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setTime(18, 0));
        $this->sessions = app(Sessions::class);
        $this->supervisor = User::factory()->supervisor()->create(['name' => 'Hanna Aufsicht']);
        $this->admin = User::factory()->admin()->create(['name' => 'Petra Vorstand']);
    }

    private function adult(string $name): User
    {
        return User::factory()->create(['name' => $name, 'adult_confirmed_at' => now()]);
    }

    private function html(ShootingSession $session): string
    {
        return view('pdf.session', SessionProtocol::data($session->fresh(), $this->admin))->render();
    }

    public function test_signed_session_with_rejected_and_withdrawn_entries_and_supervisor_time()
    {
        $day = ShootingDay::factory()->withRange()->create(['date' => today()->toDateString(), 'starts_at' => '17:00', 'ends_at' => '21:00']);
        Supervision::create(['shooting_day_id' => $day->id, 'user_id' => $this->supervisor->id, 'starts_at' => '17:00', 'ends_at' => '21:00']);

        $session = $this->sessions->open($this->supervisor);
        $participant = $this->sessions->addMember($this->supervisor, $session, $this->adult('Max Muster'));
        $guest = $this->sessions->addGuest($this->supervisor, $session, 'Gerd Gast', true);
        $rejected = $this->sessions->stage($this->supervisor, $participant, EntryVia::Stand, WeaponClass::Short, WeaponOwner::Own, 11)['entry'];
        $this->sessions->reject($this->supervisor, $rejected, 'falsche Kategorie');
        $withdrawn = $this->sessions->stage($this->supervisor, $participant, EntryVia::Stand, WeaponClass::Long, WeaponOwner::Own, 22)['entry'];
        $this->sessions->withdraw($this->supervisor, $withdrawn);
        $kept = $this->sessions->stage($this->supervisor, $participant, EntryVia::Stand, WeaponClass::Long, WeaponOwner::Own, 33)['entry'];
        $guestEntry = $this->sessions->stage($this->supervisor, $guest, EntryVia::Stand, WeaponClass::Exempt, null, null)['entry'];
        $this->travelTo(now()->setTime(20, 15));
        $this->sessions->signOff($this->supervisor, $session, [$kept->id, $guestEntry->id]);

        $html = $this->html($session);

        $this->assertStringContainsString('Session-Protokoll', $html);
        $this->assertStringContainsString('Gestartet um</td><td>18:00 Uhr', $html);
        $this->assertStringContainsString('ganze Anlage, 17:00–21:00 Uhr', $html);
        $this->assertStringContainsString('laut Einteilung', $html);
        $this->assertStringContainsString('<strong>Gast: Gerd Gast</strong>', $html);
        $this->assertStringContainsString('ohne Buchung', $html);
        $this->assertStringContainsString('abgelehnt', $html);
        $this->assertStringContainsString('von Hanna Aufsicht; Grund: falsche Kategorie', $html);
        $this->assertStringContainsString('zurückgezogen', $html);
        $this->assertStringContainsString('Abgezeichnet von Hanna Aufsicht am '.today()->format('d.m.Y').' um 20:15 Uhr, bestätigt bis 20:15 Uhr.', $html);
        $this->assertStringContainsString('Keine Stornos oder Korrekturen.', $html);
    }

    public function test_expired_session_is_marked()
    {
        $session = $this->sessions->open($this->supervisor);
        $participant = $this->sessions->addMember($this->supervisor, $session, $this->adult('Max Muster'));
        $this->sessions->stage($this->supervisor, $participant, EntryVia::Stand, WeaponClass::Short, WeaponOwner::Own, 11);
        $this->travel(1)->day();

        $html = $this->html($session);

        $this->assertStringContainsString('verfallen, nicht abgezeichnet', $html);
        $this->assertStringNotContainsString('Abgezeichnet von', $html);
    }

    public function test_later_storno_and_correction_appear()
    {
        $session = $this->sessions->open($this->supervisor);
        $first = $this->sessions->addMember($this->supervisor, $session, $this->adult('Max Muster'));
        $second = $this->sessions->addMember($this->supervisor, $session, $this->adult('Lena Lang'));
        $a = $this->sessions->stage($this->supervisor, $first, EntryVia::Stand, WeaponClass::Short, WeaponOwner::Own, 30)['entry'];
        $b = $this->sessions->stage($this->supervisor, $second, EntryVia::Stand, WeaponClass::Long, WeaponOwner::Own, 40)['entry'];
        $this->sessions->signOff($this->supervisor, $session, [$a->id, $b->id]);

        $logbook = app(Logbook::class);
        $logbook->correct($this->admin, LogEntry::where('session_entry_id', $a->id)->sole(), WeaponClass::Long, WeaponOwner::Club, null, 25, 'Kategorie falsch erfasst');
        $logbook->cancel($this->admin, LogEntry::where('session_entry_id', $b->id)->sole(), 'doppelt erfasst');

        $html = $this->html($session);

        $this->assertStringContainsString('Max Muster, Kurzwaffe (ohne Disziplin): korrigiert von Petra Vorstand: Kategorie falsch erfasst; neu: Langwaffe, Verein, ohne Disziplin, Schüsse 25', $html);
        $this->assertStringContainsString('Lena Lang, Langwaffe (ohne Disziplin): storniert von Petra Vorstand: doppelt erfasst', $html);
    }

    public function test_only_participants_of_this_session()
    {
        $session = $this->sessions->open($this->supervisor);
        $this->sessions->addMember($this->supervisor, $session, $this->adult('Max Muster'));

        $other = User::factory()->supervisor()->create(['name' => 'Otto Aufsicht']);
        $otherSession = $this->sessions->open($other);
        $this->sessions->addMember($other, $otherSession, $this->adult('Fremde Person'));

        $html = $this->html($session);

        $this->assertStringContainsString('Max Muster', $html);
        $this->assertStringNotContainsString('Fremde Person', $html);
        $this->assertStringNotContainsString('Otto Aufsicht', $html);
        $this->assertStringContainsString('Teilnehmer (1)', $html);
    }

    public function test_only_the_admin_prints_not_even_the_owning_supervisor()
    {
        $session = $this->sessions->open($this->supervisor);

        $response = $this->actingAs($this->admin)->get(route('admin.record.session-pdf', $session));
        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $this->actingAs($this->supervisor)->get(route('admin.record.session-pdf', $session))->assertForbidden();
        $this->actingAs($this->adult('Max Muster'))->get(route('admin.record.session-pdf', $session))->assertForbidden();
    }
}
