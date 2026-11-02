<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Group;
use App\Models\LogEntry;
use App\Models\User;
use App\Notifications\UserInvited;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_cannot_open_the_member_administration()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_admins_see_the_member_list()
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['name' => 'Erika Muster']);

        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/users/Index')
                ->has('users', 2));
    }

    public function test_search_filters_by_name_email_and_member_number()
    {
        $admin = User::factory()->admin()->create(['name' => 'Admin']);
        User::factory()->create(['name' => 'Erika Muster', 'member_number' => 'M-17']);
        User::factory()->create(['name' => 'Max Beispiel']);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['search' => 'M-17']))
            ->assertInertia(fn ($page) => $page
                ->has('users', 1)
                ->where('users.0.name', 'Erika Muster'));
    }

    public function test_admin_creates_a_member_and_sends_an_invitation()
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $group = Group::create(['name' => 'Jugend']);

        $this->actingAs($admin)
            ->post(route('admin.users.store'), [
                'name' => 'Erika Muster',
                'email' => 'erika@example.test',
                'role' => 'member',
                'member_number' => '0042',
                'supervisor_scope' => 'licensed', 'supervisor_until' => now()->addYear()->toDateString(),
                'groups' => [$group->id],
                'send_invitation' => true,
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('invitation.url');

        $user = User::where('email', 'erika@example.test')->sole();
        $this->assertSame(Role::Member, $user->role);
        $this->assertFalse($user->canSupervise());
        $this->assertSame(['Jugend'], $user->groups->pluck('name')->all());
        Notification::assertSentTo($user, UserInvited::class);
    }

    public function test_invitation_can_be_created_without_mail()
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Ohne Mail',
            'email' => 'ohne@example.test',
            'role' => 'member',
            'send_invitation' => false,
        ])->assertSessionHas('invitation.mailed', false);

        Notification::assertNothingSent();
    }

    public function test_admin_cannot_demote_or_deactivate_themselves()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('admin.users.update', $admin), [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => 'member',
                'active' => true,
            ])
            ->assertSessionHasErrors('role');

        $this->assertTrue($admin->fresh()->isAdmin());
    }

    public function test_admin_deactivates_a_member()
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();

        $this->actingAs($admin)->put(route('admin.users.update', $member), [
            'name' => $member->name,
            'email' => $member->email,
            'role' => 'member',
            'active' => false,
        ])->assertRedirect(route('admin.users.index'));

        $this->assertFalse($member->fresh()->active);
    }

    public function test_member_number_must_be_unique()
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['member_number' => '7']);

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Doppelt',
            'email' => 'doppelt@example.test',
            'role' => 'member',
            'member_number' => '7',
        ])->assertSessionHasErrors('member_number');
    }

    public function test_expired_qualification_cannot_supervise()
    {
        $user = User::factory()->create(['supervisor_scope' => 'licensed', 'supervisor_until' => now()->subDay()]);

        $this->assertFalse($user->canSupervise());
    }

    public function test_groups_can_be_managed()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.groups.store'), ['name' => 'Bogen'])->assertRedirect();
        $group = Group::where('name', 'Bogen')->sole();

        $this->actingAs($admin)->put(route('admin.groups.update', $group), ['name' => 'Bogensport'])->assertRedirect();
        $this->assertSame('Bogensport', $group->fresh()->name);

        $this->actingAs($admin)->delete(route('admin.groups.destroy', $group))->assertRedirect();
        $this->assertModelMissing($group);
    }

    public function test_admin_registers_and_revokes_a_supervisor()
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.users.register', $member), [
            'scope' => 'exempt',
            'valid_until' => now()->addYear()->toDateString(),
            'checked_on' => now()->toDateString(),
            'confirmed' => true,
        ])->assertRedirect(route('admin.users.edit', $member));

        $member->refresh();
        $this->assertTrue($member->canSupervise());
        $this->assertFalse($member->canSupervise(licensed: true));

        $this->actingAs($admin)->post(route('admin.users.register', $member), [
            'scope' => 'revoked',
            'checked_on' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $member->refresh();
        $this->assertFalse($member->canSupervise());
        $this->assertSame(2, $member->qualifications()->count());
        $this->assertSame($admin->id, $member->qualifications()->first()->checked_by_id);
    }

    public function test_registration_needs_confirmation_and_validity()
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.users.register', $member), [
            'scope' => 'licensed',
            'checked_on' => now()->toDateString(),
        ])->assertSessionHasErrors(['valid_until', 'confirmed']);

        $this->assertSame(0, $member->qualifications()->count());
    }

    public function test_register_rows_cannot_be_changed_or_deleted()
    {
        $member = User::factory()->supervisor()->create();
        $row = $member->qualifications()->first();

        $this->expectException(\LogicException::class);
        $row->update(['note' => 'geändert']);
    }

    public function test_register_rows_cannot_be_deleted()
    {
        $row = User::factory()->supervisor()->create()->qualifications()->first();

        $this->expectException(\LogicException::class);
        $row->delete();
    }

    public function test_members_can_be_created_without_email_and_account()
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Otto ohne Mail',
            'role' => 'member',
            'member_number' => '0815',
        ])->assertRedirect(route('admin.users.index'))->assertSessionMissing('invitation');

        $otto = User::where('member_number', '0815')->sole();
        $this->assertNull($otto->email);
        $this->assertNull($otto->password);
        Notification::assertNothingSent();

        $this->actingAs($admin)->post(route('admin.users.invite', $otto))->assertSessionHasErrors('email');
    }

    public function test_member_without_account_can_be_entered_at_the_stand()
    {
        $otto = User::factory()->create(['email' => null, 'password' => null]);
        $supervisor = User::factory()->supervisor()->create();

        $this->actingAs($supervisor)->post(route('range-office.enter'), [
            'user_id' => $otto->id,
            'weapon_class' => 'short',
            'weapon_owner' => 'own',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $otto->fresh()->hasMany(LogEntry::class)->count());
    }
}
