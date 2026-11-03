<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirm_password_screen_can_be_rendered()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('password.confirm'));

        $response->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('auth/ConfirmPassword'),
        );
    }

    public function test_password_confirmation_requires_authentication()
    {
        $response = $this->get(route('password.confirm'));

        $response->assertRedirect(route('login'));
    }

    public function test_password_confirmation_is_throttled_per_user()
    {
        $user = User::factory()->create();

        foreach (range(1, 5) as $i) {
            $this->actingAs($user)->from(route('password.confirm'))
                ->post(route('password.confirm.store'), ['password' => 'falsch'.$i])
                ->assertSessionHasErrors('password');
        }

        // der sechste Versuch in der Minute wird gedrosselt, auch mit dem richtigen Passwort
        $this->actingAs($user)->post(route('password.confirm.store'), ['password' => 'password'])->assertStatus(429);
        $this->assertNull(session('auth.password_confirmed_at'));

        // ein anderer Nutzer ist nicht betroffen
        $other = User::factory()->create();
        $this->actingAs($other)->post(route('password.confirm.store'), ['password' => 'password'])->assertSessionHasNoErrors();
        $this->assertNotNull(session('auth.password_confirmed_at'));

        $this->travel(61)->seconds();
        $this->actingAs($user)->post(route('password.confirm.store'), ['password' => 'password'])->assertSessionHasNoErrors();
    }
}
