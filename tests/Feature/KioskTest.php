<?php

namespace Tests\Feature;

use App\Domain\Sessions;
use App\Enums\EntryVia;
use App\Enums\SessionEntryStatus;
use App\Models\AuditEntry;
use App\Models\SessionEntry;
use App\Models\SessionParticipant;
use App\Models\ShootingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Kiosk über den Session-Link mit PIN je Mitglied (PR 6, E2; Invarianten „Kiosk“).
 */
class KioskTest extends TestCase
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
        $this->member = User::factory()->create(['name' => 'Erika Muster', 'member_number' => 'M-17', 'adult_confirmed_at' => now(), 'kiosk_pin_hash' => Hash::make(self::PIN)]);
        $this->session = $this->sessions->open($this->supervisor);
        $this->participant = $this->sessions->addMember($this->supervisor, $this->session, $this->member);
        $this->token = $this->sessions->issueKioskToken($this->supervisor, $this->session);
    }

    private function enter(array $data = [], ?string $token = null)
    {
        return $this->post(route('kiosk.store', $token ?? $this->token), [...self::ENTRY, 'participant_id' => $this->participant->id, 'pin' => self::PIN, ...$data]);
    }

    public function test_correct_pin_creates_a_kiosk_entry_for_the_member()
    {
        $this->post(route('kiosk.verify', $this->token), ['participant_id' => $this->participant->id, 'pin' => self::PIN])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('kiosk.participant_id', $this->participant->id);

        $this->enter()->assertRedirect(route('kiosk.show', $this->token))->assertSessionHasNoErrors();

        $entry = SessionEntry::sole();
        $this->assertSame(EntryVia::Kiosk, $entry->via);
        $this->assertSame($this->member->id, $entry->entered_by_id);
        $this->assertSame($this->participant->id, $entry->participant_id);
        $this->assertSame(SessionEntryStatus::Pending, $entry->status);
        $this->assertGuest();
    }

    public function test_lists_only_members_of_this_session_with_name_number_and_pin_flag()
    {
        $withoutPin = User::factory()->create(['name' => 'Bernd Ohnepin', 'adult_confirmed_at' => now()]);
        $this->sessions->addMember($this->supervisor, $this->session, $withoutPin);
        $this->sessions->addGuest($this->supervisor, $this->session, 'Gerda Gast', true);
        $outsider = User::factory()->create(['name' => 'Fremde Person', 'adult_confirmed_at' => now()]);
        $other = User::factory()->supervisor()->create();
        $this->sessions->addMember($other, $this->sessions->open($other), $outsider);

        $this->get(route('kiosk.show', $this->token))
            ->assertOk()
            ->assertDontSee($this->member->kiosk_pin_hash)
            ->assertDontSee('Gerda Gast')
            ->assertDontSee('Fremde Person')
            ->assertDontSee($this->member->email)
            ->assertInertia(fn ($page) => $page
                ->component('kiosk/Show')
                ->where('ended', false)
                ->where('canEnterLicensed', true)
                ->has('participants', 2)
                ->where('participants.0.name', 'Bernd Ohnepin')
                ->where('participants.0.has_pin', false)
                ->where('participants.1.name', 'Erika Muster')
                ->where('participants.1.member_number', 'M-17')
                ->where('participants.1.has_pin', true)
                ->missing('participants.1.kiosk_pin_hash')
                ->missing('participants.1.entries'));
    }

    public function test_without_token_or_with_a_replaced_token_404()
    {
        $this->get('/kiosk')->assertNotFound();
        $this->get('/kiosk/'.str_repeat('a', 48))->assertNotFound();

        $old = $this->token;
        $new = $this->sessions->issueKioskToken($this->supervisor, $this->session);

        $this->get(route('kiosk.show', $old))->assertNotFound();
        $this->enter(token: $old)->assertNotFound();
        $this->get(route('kiosk.show', $new))->assertOk();
        $this->assertSame(0, SessionEntry::count());
    }

    public function test_after_sign_off_or_on_the_next_day_the_kiosk_shows_session_ended()
    {
        $this->sessions->signOff($this->supervisor, $this->session, []);

        $this->get(route('kiosk.show', $this->token))->assertInertia(fn ($page) => $page->where('ended', true)->has('participants', 0));
        $this->enter()->assertRedirect(route('kiosk.show', $this->token));
        $this->assertSame(0, SessionEntry::count());
    }

    public function test_on_the_next_day_the_link_has_expired()
    {
        $this->travelTo(now()->addDay()->setTime(9, 0));

        $this->get(route('kiosk.show', $this->token))->assertInertia(fn ($page) => $page->where('ended', true));
        $this->enter();
        $this->assertSame(0, SessionEntry::count());
    }

    public function test_logged_in_users_are_redirected()
    {
        $this->actingAs($this->member)->get(route('kiosk.show', $this->token))->assertRedirect();
        $this->actingAs($this->supervisor)->post(route('kiosk.store', $this->token), [...self::ENTRY, 'participant_id' => $this->participant->id, 'pin' => self::PIN])->assertRedirect();
        $this->assertSame(0, SessionEntry::count());
    }

    public function test_wrong_pin_is_refused_and_never_flashed()
    {
        $this->enter(['pin' => '0000'])->assertSessionHasErrors('pin');

        $this->assertNull(session()->getOldInput('pin'));
        $this->assertSame(0, SessionEntry::count());
    }

    public function test_without_a_pin_there_is_no_kiosk_entry()
    {
        $this->member->forceFill(['kiosk_pin_hash' => null])->save();

        $this->enter()->assertSessionHasErrors('pin');
        $this->enter(['pin' => ''])->assertSessionHasErrors('pin');
        $this->assertSame(0, SessionEntry::count());
    }

    public function test_five_failures_lock_the_person_for_15_minutes_even_with_the_right_pin()
    {
        foreach (range(1, 5) as $i) {
            $this->post(route('kiosk.verify', $this->token), ['participant_id' => $this->participant->id, 'pin' => '000'.$i])->assertSessionHasErrors('pin');
        }

        $this->enter()->assertSessionHasErrors(['pin' => 'Zu viele Fehlversuche. Für 15 Minuten gesperrt – bitte bei der Aufsicht eintragen.']);
        $this->assertSame(0, SessionEntry::count());

        // Die Sperre gilt je Teilnehmer: eine andere Person derselben Session ist nicht betroffen.
        $other = User::factory()->create(['adult_confirmed_at' => now(), 'kiosk_pin_hash' => Hash::make('9876')]);
        $otherParticipant = $this->sessions->addMember($this->supervisor, $this->session, $other);
        $this->enter(['participant_id' => $otherParticipant->id, 'pin' => '9876'])->assertSessionHasNoErrors();

        $this->travel(16)->minutes();
        $this->enter()->assertSessionHasNoErrors();
        $this->assertSame(2, SessionEntry::count());
    }

    public function test_only_for_participants_of_this_session_and_only_with_their_own_pin()
    {
        $other = User::factory()->create(['adult_confirmed_at' => now(), 'kiosk_pin_hash' => Hash::make('9876')]);
        $otherParticipant = $this->sessions->addMember($this->supervisor, $this->session, $other);

        // fremde PIN für Erika gilt nicht
        $this->enter(['pin' => '9876'])->assertSessionHasErrors('pin');

        // Teilnehmer einer anderen Session über diesen Link: 404
        $foreignSupervisor = User::factory()->supervisor()->create();
        $foreign = User::factory()->create(['adult_confirmed_at' => now(), 'kiosk_pin_hash' => Hash::make('5555')]);
        $foreignParticipant = $this->sessions->addMember($foreignSupervisor, $this->sessions->open($foreignSupervisor), $foreign);
        $this->enter(['participant_id' => $foreignParticipant->id, 'pin' => '5555'])->assertNotFound();

        // Gäste stehen nicht im Kiosk
        $guest = $this->sessions->addGuest($this->supervisor, $this->session, 'Gerda Gast', true);
        $this->enter(['participant_id' => $guest->id])->assertNotFound();

        $this->assertSame(0, SessionEntry::count());
        $this->assertSame(0, $otherParticipant->entries()->count());
    }

    public function test_kiosk_only_creates_entries_no_withdraw()
    {
        $this->enter();
        $entry = SessionEntry::sole();

        // ohne Anmeldung kein Zurückziehen; angemeldet zieht auch das Mitglied Kiosk-Einträge nicht zurück
        $this->post(route('today.withdraw', $entry))->assertRedirect(route('login'));
        $this->actingAs($this->member)->post(route('today.withdraw', $entry))->assertForbidden();
        $this->assertSame(SessionEntryStatus::Pending, $entry->fresh()->status);

        // die Inhaberin kann ihn ablehnen
        $this->actingAs($this->supervisor)->post(route('range-office.session.reject', $entry), ['reason' => 'Doppelt erfasst'])->assertSessionHasNoErrors();
        $this->assertSame(SessionEntryStatus::Rejected, $entry->fresh()->status);
    }

    public function test_shots_are_required_at_the_kiosk()
    {
        $this->enter(['shots' => null])->assertSessionHasErrors('shots');
        $this->enter(['shots' => 1000])->assertSessionHasErrors('shots');
        $this->assertSame(0, SessionEntry::count());
    }

    public function test_pin_is_never_stored_or_answered_in_plain_text()
    {
        $this->actingAs($this->member)->withSession(['auth.password_confirmed_at' => now()->unix()])
            ->put(route('kiosk-pin.update'), ['pin' => '2468', 'pin_confirmation' => '2468'])
            ->assertSessionHasNoErrors();

        $stored = DB::table('users')->where('id', $this->member->id)->value('kiosk_pin_hash');
        $this->assertNotSame('2468', $stored);
        $this->assertTrue(Hash::check('2468', $stored));
        $this->assertStringNotContainsString('2468', (string) json_encode($this->member->fresh()->toArray()));
        $this->assertFalse(AuditEntry::query()->where('summary', 'like', '%2468%')->exists());
        $this->assertNull(session()->getOldInput('pin'));

        $this->actingAs($this->member)->withSession(['auth.password_confirmed_at' => now()->unix()])
            ->get(route('security.edit'))
            ->assertDontSee($stored)
            ->assertInertia(fn ($page) => $page->where('hasKioskPin', true));
    }

    public function test_setting_the_pin_requires_password_confirmation_and_4_to_6_digits()
    {
        $this->member->forceFill(['kiosk_pin_hash' => null])->save();

        $this->actingAs($this->member)->put(route('kiosk-pin.update'), ['pin' => '1234', 'pin_confirmation' => '1234'])
            ->assertRedirect(route('password.confirm'));
        $this->assertNull($this->member->fresh()->kiosk_pin_hash);

        $confirmed = fn () => $this->actingAs($this->member)->withSession(['auth.password_confirmed_at' => now()->unix()]);

        $confirmed()->put(route('kiosk-pin.update'), ['pin' => '123', 'pin_confirmation' => '123'])->assertSessionHasErrors('pin');
        $confirmed()->put(route('kiosk-pin.update'), ['pin' => '1234567', 'pin_confirmation' => '1234567'])->assertSessionHasErrors('pin');
        $confirmed()->put(route('kiosk-pin.update'), ['pin' => '12a4', 'pin_confirmation' => '12a4'])->assertSessionHasErrors('pin');
        $confirmed()->put(route('kiosk-pin.update'), ['pin' => '1234', 'pin_confirmation' => '4321'])->assertSessionHasErrors('pin');
        $this->assertNull($this->member->fresh()->kiosk_pin_hash);

        $confirmed()->put(route('kiosk-pin.update'), ['pin' => '123456', 'pin_confirmation' => '123456'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('123456', $this->member->fresh()->kiosk_pin_hash));

        session()->forget('auth.password_confirmed_at');
        $this->actingAs($this->member)->delete(route('kiosk-pin.destroy'))->assertRedirect(route('password.confirm'));
        $this->assertNotNull($this->member->fresh()->kiosk_pin_hash);
        $confirmed()->delete(route('kiosk-pin.destroy'))->assertSessionHasNoErrors();
        $this->assertNull($this->member->fresh()->kiosk_pin_hash);
    }

    public function test_board_can_delete_but_never_see_a_pin_with_audit()
    {
        $admin = User::factory()->admin()->create();
        $hash = $this->member->kiosk_pin_hash;

        $this->actingAs($admin)->get(route('admin.users.edit', $this->member))
            ->assertDontSee($hash)
            ->assertInertia(fn ($page) => $page->where('user.has_kiosk_pin', true)->missing('user.kiosk_pin_hash'));

        $this->actingAs($this->supervisor)->delete(route('admin.users.kiosk-pin.destroy', $this->member))->assertForbidden();
        $this->assertNotNull($this->member->fresh()->kiosk_pin_hash);

        $this->actingAs($admin)->delete(route('admin.users.kiosk-pin.destroy', $this->member))->assertSessionHasNoErrors();

        $this->assertNull($this->member->fresh()->kiosk_pin_hash);
        $audit = AuditEntry::query()->where('action', 'user.kiosk_pin_removed')->sole();
        $this->assertSame($admin->id, $audit->actor_id);
        $this->assertSame($this->member->id, $audit->subject_id);

        // danach kein Kiosk-Eintrag mehr
        $this->app['auth']->forgetGuards();
        $this->enter()->assertSessionHasErrors('pin');
    }

    public function test_supervisor_issues_a_link_for_another_device()
    {
        $this->actingAs($this->supervisor)->post(route('range-office.session.kiosk', $this->session))
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('kioskUrl');

        // der alte Link ist damit ersetzt
        $this->app['auth']->forgetGuards();
        $this->get(route('kiosk.show', $this->token))->assertNotFound();

        $this->actingAs($this->member)->post(route('range-office.session.kiosk', $this->session))->assertForbidden();
    }

    public function test_kiosk_on_this_device_logs_out_only_this_device()
    {
        $this->supervisor->forceFill(['remember_token' => 'remember-me-on-the-phone'])->save();

        $response = $this->actingAs($this->supervisor)->post(route('range-office.session.kiosk-here', $this->session));

        $response->assertRedirect();
        $this->assertStringContainsString('/kiosk/', (string) $response->headers->get('Location'));
        $this->assertGuest();
        $this->assertSame('remember-me-on-the-phone', $this->supervisor->fresh()->remember_token, 'Das Remember-Token anderer Geräte bleibt gültig.');

        // der neue Link funktioniert als Gast, der alte nicht mehr
        $this->get((string) $response->headers->get('Location'))->assertOk()->assertInertia(fn ($page) => $page->where('ended', false));
        $this->get(route('kiosk.show', $this->token))->assertNotFound();
    }

    public function test_expired_csrf_token_reloads_instead_of_hanging()
    {
        Route::middleware('web')->post('/_test/abgelaufen', fn () => throw new TokenMismatchException);

        $this->from(route('kiosk.show', $this->token))
            ->post('/_test/abgelaufen', [], ['X-Inertia' => 'true'])
            ->assertRedirect(route('kiosk.show', $this->token));
    }
}
