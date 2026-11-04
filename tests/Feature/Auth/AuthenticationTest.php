<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Fortify\Features;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }

    public function test_users_can_authenticate_using_the_login_screen()
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_with_two_factor_enabled_are_redirected_to_two_factor_challenge()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->withTwoFactor()->create();

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $response->assertSessionHas('login.id', $user->id);
        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_deactivated_member_with_correct_password_is_told_the_access_is_deactivated()
    {
        $user = User::factory()->create(['active' => false]);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Ihr Zugang ist deaktiviert. Bitte wenden Sie sich an den Vorstand.']);

        $this->assertGuest();
    }

    public function test_deactivated_member_with_wrong_password_gets_only_the_general_message()
    {
        $user = User::factory()->create(['active' => false]);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'falsch'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->assertGuest();
    }

    public function test_unknown_email_gets_only_the_general_message()
    {
        $this->post(route('login.store'), ['email' => 'niemand@example.test', 'password' => 'password'])
            ->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->assertGuest();
    }

    public function test_deactivated_member_with_two_factor_gets_the_notice_without_the_challenge()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        $user = User::factory()->withTwoFactor()->create(['active' => false]);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'Ihr Zugang ist deaktiviert. Bitte wenden Sie sich an den Vorstand.'])
            ->assertSessionMissing('login.id');

        $this->assertGuest();
    }

    public function test_deactivated_notice_counts_like_a_failed_attempt()
    {
        $deactivated = User::factory()->create(['active' => false]);
        $wrong = User::factory()->create();
        $key = fn (User $user) => Str::transliterate(Str::lower($user->email).'|127.0.0.1');

        $this->post(route('login.store'), ['email' => $deactivated->email, 'password' => 'password']);
        $this->post(route('login.store'), ['email' => $wrong->email, 'password' => 'falsch']);

        // Fortifys Zähler für Fehlversuche: gleich wie bei der allgemeinen Meldung.
        $this->assertSame(1, RateLimiter::attempts($key($deactivated)));
        $this->assertSame(1, RateLimiter::attempts($key($wrong)));

        // Die Drossel der Route (5 je Minute) greift auch mit richtigem Passwort.
        foreach (range(2, 5) as $attempt) {
            $this->post(route('login.store'), ['email' => $deactivated->email, 'password' => 'password'])
                ->assertSessionHasErrors('email');
        }

        $this->post(route('login.store'), ['email' => $deactivated->email, 'password' => 'password'])
            ->assertTooManyRequests();
        $this->assertGuest();
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_users_are_rate_limited()
    {
        $user = User::factory()->create();

        RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertTooManyRequests();
    }

    /**
     * Ohne Konto oder ohne Passwort wird trotzdem einmal gehasht, damit die Antwortzeit nicht verrät,
     * ob es zu der Adresse ein Konto gibt.
     */
    public function test_unknown_email_and_member_without_password_still_cost_one_hash()
    {
        $withoutPassword = User::factory()->create(['password' => null]);
        Hash::spy();

        foreach (['niemand@example.test', $withoutPassword->email] as $email) {
            $this->post(route('login.store'), ['email' => $email, 'password' => 'irgendwas'])
                ->assertSessionHasErrors(['email' => __('auth.failed')]);
        }

        Hash::shouldHaveReceived('make')->twice()->with('irgendwas');
    }
}
