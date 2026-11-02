<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_are_members()
    {
        $user = User::factory()->create();

        $this->assertSame(Role::Member, $user->fresh()->role);
        $this->assertFalse($user->isAdmin());
    }

    public function test_seeded_first_user_is_admin()
    {
        config(['app.admin.email' => 'chef@example.test', 'app.admin.password' => 'geheim-geheim']);

        $this->seed();

        $this->assertTrue(User::where('email', 'chef@example.test')->sole()->isAdmin());
    }
}
