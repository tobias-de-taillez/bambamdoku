<?php

namespace Tests\Feature;

use App\Domain\HelpRequests;
use App\Models\HelpRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HelpRequestTest extends TestCase
{
    use RefreshDatabase;

    private const KIOSK_TOKEN = 'AbCdEfGhIjKlMnOpQrStUvWxYz0123456789AbCdEfGhIjKl';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $state = [], array $overrides = []): array
    {
        return [
            'message' => 'Der Knopf „Buchen“ reagiert nicht.',
            'state' => json_encode($state + [
                'url' => 'http://localhost/schiesstage/3',
                'page' => ['component' => 'shooting-days/Show', 'url' => '/schiesstage/3', 'version' => 'a1b2c3d4e5f60718293a4b5c6d7e8f90', 'props' => ['day' => ['id' => 3]]],
                'client' => ['userAgent' => 'Mozilla/5.0 Test', 'language' => 'de-DE', 'timeZone' => 'Europe/Berlin'],
            ]),
            'screenshot' => UploadedFile::fake()->image('screenshot.jpg', 40, 30),
            ...$overrides,
        ];
    }

    private function onlyFolder(): string
    {
        $dirs = Storage::disk('local')->allDirectories('hilfe');
        $leaves = array_values(array_filter($dirs, fn ($d) => substr_count($d, '/') === 2));
        $this->assertCount(1, $leaves);

        return $leaves[0];
    }

    public function test_guest_can_report_and_it_is_stored_on_disk_and_indexed()
    {
        $this->travelTo(now()->setDate(2026, 11, 3)->setTime(14, 30, 12));

        $response = $this->postJson(route('help-requests.store'), $this->payload())->assertCreated();
        $reference = $response->json('reference');
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{6}$/', $reference);

        $folder = $this->onlyFolder();
        $this->assertSame("hilfe/2026-11-03/143012-{$reference}", $folder);
        Storage::disk('local')->assertExists("{$folder}/screenshot.jpg");

        $report = json_decode(Storage::disk('local')->get("{$folder}/meldung.json"), true);
        $this->assertSame($reference, $report['kennung']);
        $this->assertSame('Gast', $report['nutzer']);
        $this->assertSame('127.0.0.0', $report['ip']);
        $this->assertSame('shooting-days/Show', $report['komponente']);
        $this->assertSame('http://localhost/schiesstage/3', $report['url']);
        $this->assertSame('Mozilla/5.0 Test', $report['browser']['userAgent']);
        $this->assertSame('a1b2c3d4e5f60718293a4b5c6d7e8f90', $report['state']['page']['version']);
        $this->assertSame('screenshot.jpg', $report['bildschirmfoto']);

        $row = HelpRequest::sole();
        $this->assertSame($reference, $row->reference);
        $this->assertNull($row->user_id);
        $this->assertSame($folder, $row->path);
        $this->assertSame('offen', $row->status);
        $this->assertSame('shooting-days/Show', $row->component);
    }

    public function test_member_report_names_the_account_and_works_without_screenshot_and_state()
    {
        $member = User::factory()->create(['name' => 'Max Muster']);

        $this->actingAs($member)
            ->postJson(route('help-requests.store'), ['message' => 'Bitte größere Schrift.'])
            ->assertCreated();

        $folder = $this->onlyFolder();
        Storage::disk('local')->assertMissing("{$folder}/screenshot.jpg");
        $report = json_decode(Storage::disk('local')->get("{$folder}/meldung.json"), true);
        $this->assertSame(['id' => $member->id, 'name' => 'Max Muster', 'rolle' => 'member'], $report['nutzer']);
        $this->assertNull($report['bildschirmfoto']);
        $this->assertSame($member->id, HelpRequest::sole()->user_id);
    }

    public function test_secrets_are_redacted_on_the_server()
    {
        $this->postJson(route('help-requests.store'), $this->payload([
            'url' => 'http://localhost/kiosk/'.self::KIOSK_TOKEN.'?signature=abc123&tab=2',
            'page' => [
                'component' => 'kiosk/Show',
                'url' => '/kiosk/'.self::KIOSK_TOKEN,
                'props' => [
                    'token' => 'geheim-kurz',
                    'kiosk_token' => 'x',
                    'auth' => ['user' => ['name' => 'Erika', 'two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'rememberToken' => 'r']],
                    'form' => ['password' => 'hunter2', 'pin' => '1234', 'current_password' => 'alt'],
                    'kioskUrl' => 'http://localhost/kiosk/'.self::KIOSK_TOKEN,
                    'csrf_token' => 'c',
                    'XSRF-TOKEN' => 'x',
                    'note' => 'harmlos',
                ],
            ],
            'errors' => [['message' => 'fetch http://localhost/einladung/3/'.str_repeat('f', 40).'?expires=1&signature=deadbeef failed']],
        ]))->assertCreated();

        $folder = $this->onlyFolder();
        $json = Storage::disk('local')->get("{$folder}/meldung.json");
        $row = HelpRequest::sole();

        foreach ([self::KIOSK_TOKEN, 'geheim-kurz', 'JBSWY3DPEHPK3PXP', 'hunter2', '1234', 'deadbeef', 'abc123', str_repeat('f', 40)] as $secret) {
            $this->assertStringNotContainsString($secret, $json, $secret);
            $this->assertStringNotContainsString($secret, $row->url, $secret);
        }

        $report = json_decode($json, true);
        $props = $report['state']['page']['props'];
        $this->assertSame('[geschwärzt]', $props['token']);
        $this->assertSame('[geschwärzt]', $props['kiosk_token']);
        $this->assertSame('[geschwärzt]', $props['auth']['user']['two_factor_secret']);
        $this->assertSame('[geschwärzt]', $props['auth']['user']['rememberToken']);
        $this->assertSame('[geschwärzt]', $props['form']['password']);
        $this->assertSame('[geschwärzt]', $props['form']['pin']);
        $this->assertSame('[geschwärzt]', $props['csrf_token']);
        $this->assertSame('[geschwärzt]', $props['XSRF-TOKEN']);
        $this->assertSame('Erika', $props['auth']['user']['name']);
        $this->assertSame('harmlos', $props['note']);
        $this->assertSame('http://localhost/kiosk/[geschwärzt]?signature=[geschwärzt]&tab=2', $report['url']);
        $this->assertSame($report['url'], $row->url);
    }

    public function test_message_is_required_and_limits_are_enforced()
    {
        $this->postJson(route('help-requests.store'), $this->payload(overrides: ['message' => '  ']))
            ->assertUnprocessable()->assertJsonValidationErrors('message');
        $this->postJson(route('help-requests.store'), $this->payload(overrides: ['message' => str_repeat('a', 5001)]))
            ->assertUnprocessable()->assertJsonValidationErrors('message');
        $this->postJson(route('help-requests.store'), $this->payload(overrides: ['state' => str_repeat('a', 1_000_001)]))
            ->assertUnprocessable()->assertJsonValidationErrors('state');
        $this->postJson(route('help-requests.store'), $this->payload(overrides: ['state' => '{kein json']))
            ->assertUnprocessable()->assertJsonValidationErrors('state');
        $this->postJson(route('help-requests.store'), $this->payload(overrides: ['screenshot' => UploadedFile::fake()->image('big.jpg')->size(4097)]))
            ->assertUnprocessable()->assertJsonValidationErrors('screenshot');
        // Echter Inhalt statt Dateiendung entscheidet (wie beim Upload aus dem Browser)
        $fake = tempnam(sys_get_temp_dir(), 'hilfe');
        file_put_contents($fake, '<?php echo 1;');
        $this->postJson(route('help-requests.store'), $this->payload(overrides: ['screenshot' => new UploadedFile($fake, 'screenshot.jpg', 'image/jpeg', null, true)]))
            ->assertUnprocessable()->assertJsonValidationErrors('screenshot');

        $this->postJson(route('help-requests.store'), $this->payload(overrides: ['message' => str_repeat('a', 5000)]))->assertCreated();
        $this->assertSame(1, HelpRequest::count());
        $this->onlyFolder();
    }

    public function test_reports_are_throttled_per_ip_and_per_account()
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson(route('help-requests.store'), ['message' => "Meldung {$i}"])->assertCreated();
        }
        $this->postJson(route('help-requests.store'), ['message' => 'zu viel'])->assertTooManyRequests();

        // Ein Konto kommt auch über andere IPs nicht über 10 je Stunde.
        $member = User::factory()->create();
        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($member)->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
                ->postJson(route('help-requests.store'), ['message' => "Konto {$i}"])->assertCreated();
        }
        $this->actingAs($member)->withServerVariables(['REMOTE_ADDR' => '10.0.1.1'])
            ->postJson(route('help-requests.store'), ['message' => 'zu viel'])->assertTooManyRequests();

        $this->assertSame(20, HelpRequest::count());
    }

    public function test_only_admins_see_the_overview_detail_and_screenshot()
    {
        $this->postJson(route('help-requests.store'), $this->payload())->assertCreated();
        $help = HelpRequest::sole();
        $member = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $this->get(route('admin.help-requests.index'))->assertRedirect(route('login'));
        $this->get(route('admin.help-requests.screenshot', $help))->assertRedirect(route('login'));
        $this->actingAs($member)->get(route('admin.help-requests.index'))->assertForbidden();
        $this->actingAs($member)->get(route('admin.help-requests.show', $help))->assertForbidden();
        $this->actingAs($member)->get(route('admin.help-requests.screenshot', $help))->assertForbidden();
        $this->actingAs($member)->put(route('admin.help-requests.update', $help), ['status' => 'erledigt'])->assertForbidden();

        $this->actingAs($admin)->get(route('admin.help-requests.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/help-requests/Index')
                ->where('status', 'offen')
                ->where('openCount', 1)
                ->has('requests.data', 1)
                ->where('requests.data.0.reference', $help->reference));

        $this->actingAs($admin)->get(route('admin.help-requests.show', $help))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/help-requests/Show')
                ->where('helpRequest.hasScreenshot', true)
                ->where('helpRequest.component', 'shooting-days/Show')
                ->where('stateJson', fn (string $json) => str_contains($json, 'shooting-days/Show')));

        $this->actingAs($admin)->get(route('admin.help-requests.screenshot', $help))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        $this->actingAs($admin)->put(route('admin.help-requests.update', $help), ['status' => 'erledigt'])->assertRedirect();
        $help->refresh();
        $this->assertSame('erledigt', $help->status);
        $this->assertNotNull($help->resolved_at);
        $report = json_decode(Storage::disk('local')->get($help->path.'/meldung.json'), true);
        $this->assertSame('erledigt', $report['status']);

        $this->actingAs($admin)->get(route('admin.help-requests.index'))
            ->assertInertia(fn (Assert $page) => $page->has('requests.data', 0));
        $this->actingAs($admin)->get(route('admin.help-requests.index', ['status' => 'erledigt']))
            ->assertInertia(fn (Assert $page) => $page->has('requests.data', 1));
    }

    public function test_admin_deletes_a_report_with_its_folder_after_an_objection()
    {
        $this->postJson(route('help-requests.store'), $this->payload())->assertCreated();
        $help = HelpRequest::sole();
        $disk = Storage::disk('local');
        $this->assertTrue($disk->exists($help->path.'/meldung.json'));

        $this->actingAs(User::factory()->create())->delete(route('admin.help-requests.destroy', $help))->assertForbidden();
        $this->assertModelExists($help);

        $this->actingAs(User::factory()->admin()->create())->delete(route('admin.help-requests.destroy', $help))
            ->assertRedirect(route('admin.help-requests.index'));

        $this->assertModelMissing($help);
        $this->assertFalse($disk->exists($help->path));
        $this->assertDatabaseHas('audit_entries', ['action' => 'help_request.deleted']);
    }

    public function test_purge_removes_done_after_90_days_and_everything_after_365_days()
    {
        $disk = Storage::disk('local');
        $make = function (int $daysAgo, string $status, ?string $resolvedAt = null) use ($disk): HelpRequest {
            $this->travelTo(now()->subDays($daysAgo));
            $this->postJson(route('help-requests.store'), ['message' => 'x'])->assertCreated();
            $this->travelBack();
            $help = HelpRequest::query()->latest('id')->firstOrFail();
            $help->forceFill(['status' => $status, 'resolved_at' => $resolvedAt])->save();
            $this->assertTrue($disk->exists($help->path.'/meldung.json'));

            return $help;
        };

        $doneOld = $make(100, 'erledigt', now()->subDays(91)->toDateTimeString());
        $doneRecent = $make(100, 'erledigt', now()->subDays(10)->toDateTimeString());
        $openOld = $make(200, 'offen');
        $ancient = $make(400, 'offen');
        // Ordner ohne Index-Zeile (Datenbank war beim Melden weg)
        $orphan = 'hilfe/'.now()->subDays(400)->format('Y-m-d').'/120000-ORPHAN';
        $disk->put($orphan.'/meldung.json', '{}');

        $this->artisan('verein:hilfe-aufraeumen')->assertSuccessful();

        $this->assertEqualsCanonicalizing([$doneRecent->id, $openOld->id], HelpRequest::pluck('id')->all());
        $this->assertFalse($disk->exists($doneOld->path));
        $this->assertFalse($disk->exists($ancient->path));
        $this->assertFalse($disk->exists($orphan));
        $this->assertTrue($disk->exists($doneRecent->path.'/meldung.json'));
        $this->assertTrue($disk->exists($openOld->path.'/meldung.json'));
        $this->assertSame(HelpRequests::DONE_RETENTION_DAYS, 90);
    }

    public function test_expired_cache_rows_are_removed_hourly()
    {
        config(['cache.default' => 'database']);
        DB::table('cache')->insert([
            ['key' => 'alt', 'value' => 'x', 'expiration' => now()->subMinute()->getTimestamp()],
            ['key' => 'frisch', 'value' => 'x', 'expiration' => now()->addHour()->getTimestamp()],
        ]);

        $this->artisan('schedule:list')->expectsOutputToContain('cache-aufraeumen')->assertSuccessful();
        $this->travelTo(now()->startOfHour()->addHour());
        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertSame(['frisch'], DB::table('cache')->pluck('key')->all());
    }

    public function test_purge_is_scheduled_daily()
    {
        $this->artisan('schedule:list')->expectsOutputToContain('verein:hilfe-aufraeumen')->assertSuccessful();
    }

    public function test_unknown_pages_render_the_app_error_page()
    {
        $this->get('/gibt-es-nicht')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 404)->where('hasImprint', false));

        config(['verein.imprint' => 'Verein, Anschrift']);
        $this->get('/gibt-es-nicht')->assertInertia(fn (Assert $page) => $page->where('hasImprint', true));

        $this->actingAs(User::factory()->create())->get(route('admin.help-requests.index'))
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->component('Error')->where('status', 403));
    }
}
