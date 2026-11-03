<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_to_dashboard()
    {
        $response = $this->get(route('home'));

        $response->assertRedirect('/dashboard');
    }

    public function test_registration_is_disabled()
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_privacy_notice_is_public()
    {
        $this->get(route('privacy'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Privacy'));
    }

    public function test_help_page_renders_for_members()
    {
        $this->actingAs(User::factory()->create())->get(route('help'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Help'));
    }
}
