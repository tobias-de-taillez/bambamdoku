<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\Invitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitation_page_is_shown_for_a_valid_link()
    {
        $user = User::factory()->unverified()->create();

        $this->get(Invitation::url($user))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('auth/AcceptInvitation')->where('email', $user->email));
    }

    public function test_member_sets_password_and_is_logged_in()
    {
        $user = User::factory()->unverified()->create();
        $url = Invitation::url($user);

        $this->post($url, [
            'password' => 'ein-sicheres-passwort',
            'password_confirmation' => 'ein-sicheres-passwort',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_invitation_link_works_only_once()
    {
        $user = User::factory()->unverified()->create();
        $url = Invitation::url($user);

        $this->post($url, [
            'password' => 'ein-sicheres-passwort',
            'password_confirmation' => 'ein-sicheres-passwort',
        ]);
        auth()->logout();

        $this->get($url)->assertRedirect(route('login'));
        $this->post($url, [
            'password' => 'noch-ein-passwort',
            'password_confirmation' => 'noch-ein-passwort',
        ])->assertForbidden();
    }

    public function test_tampered_link_is_rejected()
    {
        $user = User::factory()->create();

        $this->get(Invitation::url($user).'x')->assertForbidden();
    }

    public function test_inactive_members_cannot_log_in()
    {
        $user = User::factory()->create(['active' => false]);

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

        $this->assertGuest();
    }

    public function test_deactivated_members_lose_their_session()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->forceFill(['active' => false])->save();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
