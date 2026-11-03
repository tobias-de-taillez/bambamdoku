<?php

namespace Tests\Feature\Auth;

use App\Actions\Fortify\CreateNewUser;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Selbstregistrierung = Mitgliedsantrag (UMBAU-SESSION.md, PR 2, E7, E8).
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Anna Antrag',
            'email' => 'anna@example.com',
            'password' => 'ein-sicheres-Passwort-1',
            'password_confirmation' => 'ein-sicheres-Passwort-1',
            'adult' => true,
            ...$overrides,
        ];
    }

    private function open(): void
    {
        config(['verein.registration_open' => 1]);
    }

    public function test_registration_is_closed_by_default()
    {
        $this->assertSame(0, config('verein.registration_open'));

        $this->get(route('register'))->assertNotFound();
        $this->post(route('register.store'), $this->payload())->assertNotFound();
        $this->get(route('login'))->assertInertia(fn ($page) => $page->where('canRegister', false));

        $this->assertSame(0, User::count());
    }

    public function test_open_registration_shows_the_form_and_the_login_link()
    {
        $this->open();

        $this->get(route('register'))->assertOk()->assertInertia(fn ($page) => $page->component('auth/Register'));
        $this->get(route('login'))->assertInertia(fn ($page) => $page->where('canRegister', true));
    }

    public function test_registration_creates_an_application_and_logs_out_immediately()
    {
        $this->open();
        Notification::fake();

        $this->post(route('register.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'Ihr Antrag liegt beim Vorstand'));

        $this->assertGuest();

        $user = User::sole();
        $this->assertSame(Role::Applicant, $user->role);
        $this->assertFalse($user->active);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->adult_confirmed_at);
        // Ohne Anmeldung keine Bestätigungsmail; die Aufnahme setzt email_verified_at.
        Notification::assertNothingSent();

        // Auch die nächste Anfrage ist nicht angemeldet.
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_role_and_active_cannot_be_manipulated()
    {
        $this->open();

        $this->post(route('register.store'), $this->payload([
            'role' => 'admin',
            'active' => true,
            'email_verified_at' => now()->toDateTimeString(),
            'adult_confirmed_at' => now()->toDateTimeString(),
            'member_number' => '1',
        ]))->assertSessionHasNoErrors();

        $user = User::sole();
        $this->assertSame(Role::Applicant, $user->role);
        $this->assertFalse($user->active);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($user->adult_confirmed_at);
        $this->assertNull($user->member_number);
    }

    public function test_adult_checkbox_is_required()
    {
        $this->open();

        $this->post(route('register.store'), $this->payload(['adult' => false]))->assertSessionHasErrors('adult');
        $this->post(route('register.store'), $this->payload(['adult' => null]))->assertSessionHasErrors('adult');

        $this->assertSame(0, User::count());
    }

    public function test_an_existing_email_address_is_rejected()
    {
        $this->open();
        User::factory()->create(['email' => 'anna@example.com']);

        $this->post(route('register.store'), $this->payload())->assertSessionHasErrors('email');

        $this->assertSame(1, User::count());
    }

    public function test_the_applicant_cannot_log_in_until_admitted()
    {
        $this->open();
        $this->post(route('register.store'), $this->payload());

        $this->post(route('login.store'), ['email' => 'anna@example.com', 'password' => 'ein-sicheres-Passwort-1'])
            ->assertSessionHasErrors(['email' => __('Ihr Antrag liegt beim Vorstand. Sie können sich anmelden, sobald der Verein Sie aufgenommen hat.')]);
        $this->assertGuest();
    }

    public function test_registration_is_throttled_per_ip()
    {
        $this->open();

        for ($i = 0; $i < CreateNewUser::MAX_ATTEMPTS; $i++) {
            $this->post(route('register.store'), $this->payload(['email' => "a{$i}@example.com", 'adult' => false]));
        }

        $this->post(route('register.store'), $this->payload())->assertSessionHasErrors('email');
        $this->assertSame(0, User::count());

        // Eine andere IP ist nicht betroffen.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->post(route('register.store'), $this->payload())
            ->assertSessionHasNoErrors();
        $this->assertSame(1, User::count());
    }

    public function test_closing_registration_again_returns_404()
    {
        $this->open();
        $this->get(route('register'))->assertOk();

        config(['verein.registration_open' => 0]);
        $this->get(route('register'))->assertNotFound();
    }
}
