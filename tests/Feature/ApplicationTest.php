<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AuditEntry;
use App\Models\LogEntry;
use App\Models\SessionParticipant;
use App\Models\ShootingDay;
use App\Models\ShootingSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Mitgliedsantrag und Aufnahme durch den Verein (UMBAU-SESSION.md, PR 1, E7, E12).
 */
class ApplicationTest extends TestCase
{
    use RefreshDatabase;

    private const PENDING = 'Ihr Antrag liegt beim Vorstand';

    public function test_an_application_is_an_inactive_applicant()
    {
        $applicant = User::factory()->applicant()->create();

        $this->assertSame(Role::Applicant, $applicant->role);
        $this->assertFalse($applicant->active);
        $this->assertTrue($applicant->isApplicant());
    }

    public function test_login_with_correct_password_is_rejected_with_the_pending_message()
    {
        $applicant = User::factory()->applicant()->create();

        $this->post(route('login.store'), ['email' => $applicant->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => __('Ihr Antrag liegt beim Vorstand. Sie können sich anmelden, sobald der Verein Sie aufgenommen hat.')]);

        $this->assertGuest();
    }

    public function test_login_with_wrong_password_does_not_reveal_the_application()
    {
        $applicant = User::factory()->applicant()->create();

        $this->post(route('login.store'), ['email' => $applicant->email, 'password' => 'falsch'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->assertGuest();
    }

    public function test_a_running_session_of_an_applicant_ends_with_the_pending_message()
    {
        $applicant = User::factory()->applicant()->create();

        $this->actingAs($applicant)->get(route('dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', fn (string $status) => str_contains($status, self::PENDING));

        $this->assertGuest();
    }

    public function test_applicants_appear_in_no_person_list()
    {
        $this->travelTo(now()->setTime(18, 0));
        $admin = User::factory()->admin()->supervisor()->create();
        $member = User::factory()->create(['name' => 'Anna Mitglied']);
        $applicant = User::factory()->applicant()->create(['name' => 'Bernd Antrag']);
        $day = ShootingDay::factory()->withRange()->create(['date' => today()->toDateString(), 'starts_at' => '17:00', 'ends_at' => '21:00']);

        $ids = fn (array $rows) => collect($rows)->pluck('id')->all();

        $this->actingAs($admin)->get(route('range-office.show'))
            ->assertInertia(fn ($page) => $page->where('members', fn ($rows) => in_array($member->id, $ids($rows->all()), true) && ! in_array($applicant->id, $ids($rows->all()), true)));

        $this->actingAs($admin)->get(route('admin.record.index'))
            ->assertInertia(fn ($page) => $page->where('members', fn ($rows) => in_array($member->id, $ids($rows->all()), true) && ! in_array($applicant->id, $ids($rows->all()), true)));

        $this->actingAs($admin)->get(route('shooting-days.show', $day))
            ->assertInertia(fn ($page) => $page->where('supervisorCandidates', fn ($rows) => ! in_array($applicant->id, $ids($rows->all()), true)));

        foreach (['active', 'inactive', 'supervisors', 'all'] as $status) {
            $this->actingAs($admin)->get(route('admin.users.index', ['status' => $status]))
                ->assertInertia(fn ($page) => $page->where('users', fn ($rows) => ! in_array($applicant->id, $ids($rows->all()), true)));
        }

        $this->actingAs($admin)->get(route('admin.users.index', ['status' => 'applicants']))
            ->assertInertia(fn ($page) => $page
                ->where('users', fn ($rows) => $ids($rows->all()) === [$applicant->id])
                ->where('auth.applicantCount', 1));
    }

    public function test_inactive_filter_shows_deactivated_members_but_no_applications()
    {
        $admin = User::factory()->admin()->create();
        $former = User::factory()->create(['active' => false]);
        User::factory()->applicant()->create();

        $this->actingAs($admin)->get(route('admin.users.index', ['status' => 'inactive']))
            ->assertInertia(fn ($page) => $page->where('users', fn ($rows) => collect($rows)->pluck('id')->all() === [$former->id]));
    }

    public function test_applicant_count_is_only_shared_with_admins()
    {
        User::factory()->applicant()->count(2)->create();

        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('auth.applicantCount', 0));

        $this->actingAs(User::factory()->admin()->create())->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('auth.applicantCount', 2));
    }

    public function test_the_edit_form_is_locked_for_applications()
    {
        $admin = User::factory()->admin()->create();
        $applicant = User::factory()->applicant()->create(['name' => 'Bernd Antrag']);

        $this->actingAs($admin)->get(route('admin.users.edit', $applicant))
            ->assertRedirect(route('admin.users.index', ['status' => 'applicants']));

        $this->actingAs($admin)->put(route('admin.users.update', $applicant), [
            'name' => 'Bernd Mitglied',
            'email' => $applicant->email,
            'role' => 'admin',
            'active' => true,
        ])->assertForbidden();

        $applicant->refresh();
        $this->assertSame(Role::Applicant, $applicant->role);
        $this->assertFalse($applicant->active);
        $this->assertSame('Bernd Antrag', $applicant->name);
    }

    public function test_no_invitation_and_no_register_line_for_applications()
    {
        $admin = User::factory()->admin()->create();
        $applicant = User::factory()->applicant()->create();

        $this->actingAs($admin)->post(route('admin.users.invite', $applicant))->assertForbidden();
        $this->actingAs($admin)->post(route('admin.users.register', $applicant), [
            'scope' => 'licensed',
            'valid_until' => today()->addYear()->toDateString(),
            'checked_on' => today()->toDateString(),
            'confirmed' => true,
        ])->assertForbidden();

        $this->assertSame(0, $applicant->qualifications()->count());
    }

    public function test_admitting_requires_the_check_and_sets_all_fields()
    {
        $this->travelTo(now()->setDate(2026, 11, 3)->setTime(10, 0));
        $admin = User::factory()->admin()->create();
        $applicant = User::factory()->applicant()->create(['name' => 'Bernd Antrag']);

        $this->actingAs($admin)->post(route('admin.users.admit', $applicant), ['member_number' => '0815'])
            ->assertSessionHasErrors('confirmed');
        $this->assertTrue($applicant->fresh()->isApplicant());

        $this->actingAs($admin)->post(route('admin.users.admit', $applicant), ['confirmed' => true, 'member_number' => '0815'])
            ->assertSessionHasNoErrors();

        $applicant->refresh();
        $this->assertSame(Role::Member, $applicant->role);
        $this->assertTrue($applicant->active);
        $this->assertSame('0815', $applicant->member_number);
        $this->assertSame('2026-11-03', $applicant->member_since?->toDateString());
        $this->assertNotNull($applicant->email_verified_at);
        $this->assertNotNull($applicant->adult_confirmed_at);

        $audit = AuditEntry::where('action', 'application.admitted')->sole();
        $this->assertSame($admin->id, $audit->actor_id);
        $this->assertSame($applicant->id, $audit->subject_id);
        $this->assertStringContainsString('Bernd Antrag', $audit->summary);

        // Danach normale Anmeldung mit dem eigenen Passwort.
        auth()->logout();
        $this->post(route('login.store'), ['email' => $applicant->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($applicant);
    }

    public function test_admitting_accepts_a_member_since_date_and_rejects_members()
    {
        $admin = User::factory()->admin()->create();
        $applicant = User::factory()->applicant()->create();
        $member = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.users.admit', $applicant), ['confirmed' => true, 'member_since' => '2020-01-15'])
            ->assertSessionHasNoErrors();
        $this->assertSame('2020-01-15', $applicant->fresh()->member_since?->toDateString());

        $this->actingAs($admin)->post(route('admin.users.admit', $member), ['confirmed' => true])->assertNotFound();
        $this->actingAs($admin)->post(route('admin.users.admit', $applicant), ['confirmed' => true])->assertNotFound();
    }

    public function test_only_admins_admit_or_reject()
    {
        $applicant = User::factory()->applicant()->create();
        $member = User::factory()->supervisor()->create();

        $this->actingAs($member)->post(route('admin.users.admit', $applicant), ['confirmed' => true])->assertForbidden();
        $this->actingAs($member)->delete(route('admin.users.reject', $applicant))->assertForbidden();

        $this->assertTrue($applicant->fresh()->isApplicant());
    }

    public function test_rejecting_deletes_the_application_without_personal_data_in_the_audit()
    {
        $admin = User::factory()->admin()->create();
        $applicant = User::factory()->applicant()->create(['name' => 'Bernd Antrag', 'email' => 'bernd@example.com']);
        DB::table('password_reset_tokens')->insert(['email' => 'bernd@example.com', 'token' => 'x', 'created_at' => now()]);

        $this->actingAs($admin)->delete(route('admin.users.reject', $applicant))->assertSessionHasNoErrors();

        $this->assertNull($applicant->fresh());
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
        $audit = AuditEntry::where('action', 'application.rejected')->sole();
        $this->assertNull($audit->subject_id);
        $this->assertStringNotContainsString('Bernd', $audit->summary);
        $this->assertStringNotContainsString('bernd@example.com', $audit->summary);
    }

    public function test_rejecting_never_deletes_members()
    {
        $admin = User::factory()->admin()->create();
        $former = User::factory()->create(['active' => false]);

        $this->actingAs($admin)->delete(route('admin.users.reject', $former))->assertNotFound();

        $this->assertNotNull($former->fresh());
    }

    public function test_stand_entry_and_late_entry_reject_application_ids()
    {
        $this->travelTo(now()->setTime(18, 0));
        $supervisor = User::factory()->supervisor()->create();
        $admin = User::factory()->admin()->create();
        $applicant = User::factory()->applicant()->create();

        $this->actingAs($supervisor)->post(route('range-office.session.start'))->assertSessionHasNoErrors();
        $session = ShootingSession::query()->firstOrFail();

        // Im Session-Modell scheitert ein Antrag schon beim Aktivieren: kein aktives Mitglied.
        $this->actingAs($supervisor)->post(route('range-office.session.member', $session), [
            'user_id' => $applicant->id,
            'adult_confirmed' => true,
        ])->assertSessionHasErrors('user_id');
        $this->assertSame(0, SessionParticipant::count());

        $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => now()->unix()])->post(route('admin.record.late'), [
            'members' => [$applicant->id],
            'date' => today()->subDay()->toDateString(),
            'weapon_class' => 'exempt',
            'weapon_owner' => 'own',
            'reason' => 'Papierliste vom Vortag',
        ])->assertSessionHasErrors('members');

        $this->assertSame(0, LogEntry::count());
    }

    public function test_applications_get_neither_certificate_nor_extract()
    {
        $admin = User::factory()->admin()->create();
        $applicant = User::factory()->applicant()->create();

        $this->actingAs($admin)->get(route('admin.record.certificate', $applicant))->assertNotFound();
        $this->actingAs($admin)->get(route('admin.record.member-pdf', $applicant))->assertNotFound();
    }

    public function test_creating_a_member_with_the_adult_check_stores_it_once()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Clara Geprüft',
            'role' => 'member',
            'adult_confirmed' => true,
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Dora Offen',
            'role' => 'member',
        ])->assertSessionHasNoErrors();

        $this->assertNotNull(User::where('name', 'Clara Geprüft')->sole()->adult_confirmed_at);
        $this->assertNull(User::where('name', 'Dora Offen')->sole()->adult_confirmed_at);
    }

    public function test_update_audit_lists_old_and_new_values()
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create(['name' => 'Eva Alt', 'member_number' => '1', 'member_since' => '2020-01-01']);

        $this->actingAs($admin)->put(route('admin.users.update', $member), [
            'name' => 'Eva Neu',
            'email' => $member->email,
            'role' => 'admin',
            'member_number' => '2',
            'member_since' => '2021-02-02',
            'active' => false,
        ])->assertSessionHasNoErrors();

        $summary = AuditEntry::where('action', 'user.updated')->sole()->summary;
        $this->assertStringContainsString('Name: Eva Alt → Eva Neu', $summary);
        $this->assertStringContainsString('Nr.: 1 → 2', $summary);
        $this->assertStringContainsString('Mitglied seit: 2020-01-01 → 2021-02-02', $summary);
        $this->assertStringContainsString('Rolle: member → admin', $summary);
        $this->assertStringContainsString('aktiv: ja → nein', $summary);
    }

    public function test_purge_deletes_only_applications_older_than_30_days()
    {
        $old = User::factory()->applicant()->create(['created_at' => now()->subDays(31)]);
        $fresh = User::factory()->applicant()->create(['created_at' => now()->subDays(29)]);
        $former = User::factory()->create(['active' => false, 'created_at' => now()->subDays(100)]);
        $member = User::factory()->create(['created_at' => now()->subDays(100)]);

        $this->artisan('verein:antraege-loeschen')->assertSuccessful();

        $this->assertNull($old->fresh());
        $this->assertNotNull($fresh->fresh());
        $this->assertNotNull($former->fresh());
        $this->assertNotNull($member->fresh());

        $this->travelTo(now()->addDays(2));
        $this->artisan('verein:antraege-loeschen')->assertSuccessful();
        $this->assertNull($fresh->fresh());
        $this->assertNotNull($former->fresh());
    }

    public function test_purge_is_scheduled_daily()
    {
        $this->artisan('schedule:list')->expectsOutputToContain('verein:antraege-loeschen')->assertSuccessful();
    }
}
