<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\ClubSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_updates_club_settings()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'name' => 'SV Musterdorf 1888 e. V.',
            'address' => 'Am Schießstand 1, 12345 Musterdorf',
            'slot_minutes' => 30,
            'cancel_minutes_before' => 120,
            'booking_horizon_days' => 60,
        ])->assertRedirect(route('admin.settings.edit'));

        config(['verein.name' => 'zurückgesetzt', 'verein.slot_minutes' => 60]);
        ClubSettings::apply();

        $this->assertSame('SV Musterdorf 1888 e. V.', config('verein.name'));
        $this->assertSame(30, config('verein.slot_minutes'));
        $this->assertSame(120, config('verein.cancel_minutes_before'));
    }

    public function test_slot_must_be_one_of_the_offered_values()
    {
        $this->actingAs(User::factory()->admin()->create())->put(route('admin.settings.update'), [
            'name' => 'SV',
            'slot_minutes' => 45,
            'cancel_minutes_before' => 60,
            'booking_horizon_days' => 60,
        ])->assertSessionHasErrors('slot_minutes');
    }

    public function test_members_cannot_change_settings()
    {
        $this->actingAs(User::factory()->create())->get(route('admin.settings.edit'))->assertForbidden();
    }

    public function test_club_name_is_shared_with_the_frontend()
    {
        config(['verein.name' => 'SV Test']);

        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('club', 'SV Test'));
    }
}
