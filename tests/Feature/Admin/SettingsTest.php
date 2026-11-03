<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\ClubSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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

    public function test_admin_sets_the_extract_header_without_server_access()
    {
        $this->actingAs(User::factory()->admin()->create())->put(route('admin.settings.update'), [
            'name' => 'SV Musterdorf',
            'federation' => 'BSSB',
            'club_number' => 'V-1234',
            'digital_since' => '2026-11-01',
            'slot_minutes' => 60,
            'cancel_minutes_before' => 60,
            'booking_horizon_days' => 60,
        ])->assertSessionHasNoErrors();

        config(['verein.federation' => null, 'verein.club_number' => null, 'verein.digital_since' => null]);
        ClubSettings::apply();

        $this->assertSame('BSSB', config('verein.federation'));
        $this->assertSame('V-1234', config('verein.club_number'));
        $this->assertSame('2026-11-01', config('verein.digital_since'));
    }

    public function test_digital_since_cannot_lie_in_the_future()
    {
        $this->actingAs(User::factory()->admin()->create())->put(route('admin.settings.update'), [
            'name' => 'SV',
            'digital_since' => now()->addDay()->toDateString(),
            'slot_minutes' => 60,
            'cancel_minutes_before' => 60,
            'booking_horizon_days' => 60,
        ])->assertSessionHasErrors('digital_since');
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

    public function test_settings_page_reports_mail_status()
    {
        config(['mail.default' => 'log']);

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.settings.edit'))
            ->assertInertia(fn ($page) => $page->where('mail.delivers', false)->where('mail.mailer', 'log'));
    }

    public function test_admin_sends_a_test_mail_to_themselves()
    {
        config(['mail.default' => 'array']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.settings.test-mail'))->assertRedirect(route('admin.settings.edit'));

        $sent = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);
        $this->assertSame($admin->email, $sent[0]->getEnvelope()->getRecipients()[0]->getAddress());
    }

    public function test_imprint_page_exists_only_once_the_board_fills_it_in()
    {
        $this->get(route('imprint'))->assertNotFound();
        $this->get(route('login'))->assertInertia(fn ($page) => $page->where('hasImprint', false));

        $this->actingAs(User::factory()->admin()->create())->put(route('admin.settings.update'), [
            'name' => 'SV Musterdorf',
            'imprint' => "SV Musterdorf e. V.\nAm Schießstand 1, 12345 Musterdorf",
            'slot_minutes' => 60,
            'cancel_minutes_before' => 60,
            'booking_horizon_days' => 60,
        ])->assertSessionHasNoErrors();

        auth()->logout();

        $this->get(route('imprint'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Imprint')->where('text', "SV Musterdorf e. V.\nAm Schießstand 1, 12345 Musterdorf"));
        $this->get(route('login'))->assertInertia(fn ($page) => $page->where('hasImprint', true));
    }
}
