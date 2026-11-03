<?php

namespace Tests\Feature\Admin;

use App\Console\Commands\BackupDatabase;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupTest extends TestCase
{
    // Ohne umschließende Transaktion, denn VACUUM INTO ist darin nicht erlaubt.
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        File::deleteDirectory(BackupDatabase::directory());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(BackupDatabase::directory());
        parent::tearDown();
    }

    public function test_backup_command_creates_a_copy_and_prunes_old_ones()
    {
        File::ensureDirectoryExists(BackupDatabase::directory());
        $old = BackupDatabase::directory().'/bambamdoku-2020-01-01-000000.sqlite';
        File::put($old, 'alt');
        touch($old, now()->subDays(40)->getTimestamp());

        $this->artisan('verein:backup')->assertSuccessful();

        $this->assertFileDoesNotExist($old);
        $this->assertCount(1, BackupDatabase::list());
    }

    public function test_missed_nightly_backup_is_caught_up_once()
    {
        $this->travelTo(today()->setTime(10, 30));
        $this->artisan('verein:backup --falls-fehlt')->assertSuccessful();
        $this->assertCount(1, BackupDatabase::list());

        // Gibt es heute schon eine Sicherung, passiert nichts.
        $this->travel(1)->hours();
        $this->artisan('verein:backup --falls-fehlt')->assertSuccessful();
        $this->assertCount(1, BackupDatabase::list());
    }

    public function test_admin_creates_and_downloads_a_backup()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.settings.backup'))->assertRedirect();
        $name = BackupDatabase::list()[0]['name'];

        $this->actingAs($admin)->get(route('admin.settings.backup-download', $name))->assertOk()->assertDownload($name);
        $this->actingAs($admin)->get(route('admin.settings.backup-download', '..%2F.env'))->assertNotFound();
    }

    public function test_members_cannot_download_backups()
    {
        $this->actingAs(User::factory()->create())->post(route('admin.settings.backup'))->assertForbidden();
    }

    public function test_only_the_backup_is_scheduled()
    {
        // Erinnerung am Vortag und „Aufsicht gesucht“ sind stillgelegt.
        $this->artisan('schedule:list')
            ->expectsOutputToContain('verein:backup')
            ->doesntExpectOutputToContain('verein:erinnern')
            ->doesntExpectOutputToContain('verein:aufsicht-gesucht');
    }
}
