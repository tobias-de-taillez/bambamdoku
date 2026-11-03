<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Startseite unter „/“: Gäste sehen den Verein, angemeldete Nutzer landen auf der Übersicht.
 * Die Links rendert Svelte; geprüft werden hier die Props, an denen sie hängen.
 */
class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_see_the_landing_page_with_the_club_name(): void
    {
        config(['verein.name' => 'SV Musterdorf', 'verein.address' => 'Schützenweg 1, 12345 Musterdorf']);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->where('club', 'SV Musterdorf')
                ->where('address', 'Schützenweg 1, 12345 Musterdorf'));
    }

    public function test_signed_in_users_are_sent_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('home'))
            ->assertRedirect(route('dashboard'));
    }

    public function test_membership_application_is_offered_only_while_registration_is_open(): void
    {
        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->where('canRegister', false));

        config(['verein.registration_open' => 1]);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->where('canRegister', true));
    }

    public function test_imprint_link_depends_on_imprint_text(): void
    {
        config(['verein.imprint' => null]);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->where('hasImprint', false));

        config(['verein.imprint' => "SV Musterdorf e. V.\nVertreten durch den Vorstand"]);

        $this->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->where('hasImprint', true));
    }

    public function test_landing_page_shows_no_personal_data(): void
    {
        $member = User::factory()->create(['name' => 'Karla Kladde']);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee($member->name)
            ->assertDontSee($member->email);
    }
}
