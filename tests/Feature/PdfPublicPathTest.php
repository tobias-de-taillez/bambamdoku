<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PdfPublicPathTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Wie auf dem Webhosting: Der Docroot liegt außerhalb der App, base_path('public') gibt es nicht.
     */
    public function test_pdfs_work_when_the_docroot_lies_outside_the_app()
    {
        $docroot = sys_get_temp_dir().'/bambamdoku-docroot-'.uniqid();
        File::ensureDirectoryExists($docroot);
        $this->app->usePublicPath($docroot);
        $this->app->setBasePath(base_path());
        config(['dompdf.public_path' => public_path()]);

        try {
            $response = $this->actingAs(User::factory()->admin()->create())->get(route('admin.record.paper-list'));

            $response->assertOk();
            $this->assertStringStartsWith('%PDF', $response->getContent());
            $this->assertSame(realpath($docroot), realpath(config('dompdf.public_path')));
        } finally {
            File::deleteDirectory($docroot);
        }
    }

    public function test_the_app_points_dompdf_at_its_public_path()
    {
        $this->assertSame(public_path(), config('dompdf.public_path'));
    }
}
