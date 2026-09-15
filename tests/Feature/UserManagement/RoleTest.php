<?php

namespace Tests\Feature\UserManagement;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        Permission::create(['name' => 'role.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'role.create', 'guard_name' => 'web']);
        Permission::create(['name' => 'role.update', 'guard_name' => 'web']);
        Permission::create(['name' => 'role.delete', 'guard_name' => 'web']);
        Permission::create(['name' => 'user.view', 'guard_name' => 'web']);

        // Create roles
        $adminRole = Role::create(['name' => 'Super Administrator', 'guard_name' => 'web']);
        $adminRole->syncPermissions(Permission::all());
    }

    public function test_authorized_users_can_view_roles(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $user->assignRole('Super Administrator');

        $this->actingAs($user);

        $response = $this->get('/roles');

        $response->assertStatus(200);
    }

    public function test_authorized_users_can_create_role(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $user->assignRole('Super Administrator');

        $this->actingAs($user);

        $response = $this->post('/roles', [
            'name' => 'Test Role',
            'permissions' => ['user.view'],
        ]);

        $response->assertRedirect(route('roles.index'));
        $this->assertDatabaseHas('roles', ['name' => 'Test Role']);
    }

    public function test_authorized_users_can_update_role(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $user->assignRole('Super Administrator');

        $role = Role::create(['name' => 'Old Role Name', 'guard_name' => 'web']);

        $this->actingAs($user);

        $response = $this->put("/roles/{$role->id}", [
            'name' => 'New Role Name',
            'permissions' => ['user.view'],
        ]);

        $response->assertRedirect(route('roles.index'));
        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'New Role Name']);
    }

    public function test_authorized_users_can_delete_role(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $user->assignRole('Super Administrator');

        $role = Role::create(['name' => 'Delete Me', 'guard_name' => 'web']);

        $this->actingAs($user);

        $response = $this->delete("/roles/{$role->id}");

        $response->assertRedirect(route('roles.index'));
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_super_administrator_role_cannot_be_deleted(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $user->assignRole('Super Administrator');

        $this->actingAs($user);

        $response = $this->delete('/roles/1');

        $response->assertRedirect();
        $this->assertDatabaseHas('roles', ['name' => 'Super Administrator']);
    }
}
