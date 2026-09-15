<?php

namespace Tests\Feature\UserManagement;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions
        Permission::create(['name' => 'permission.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'permission.assign', 'guard_name' => 'web']);
        Permission::create(['name' => 'user.view', 'guard_name' => 'web']);

        // Create roles
        $adminRole = Role::create(['name' => 'Super Administrator', 'guard_name' => 'web']);
        $adminRole->syncPermissions(Permission::all());
    }

    public function test_authorized_users_can_view_permissions(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $user->assignRole('Super Administrator');

        $this->actingAs($user);

        $response = $this->get('/permissions');

        $response->assertStatus(200);
    }

    public function test_permissions_can_be_assigned_to_role(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $user->assignRole('Super Administrator');

        $role = Role::create(['name' => 'Test Role', 'guard_name' => 'web']);

        $this->actingAs($user);

        $response = $this->post("/permissions/{$role->id}", [
            'permissions' => ['user.view', 'permission.view'],
        ]);

        $response->assertRedirect(route('permissions.index'));
        $this->assertTrue($role->fresh()->hasPermissionTo('user.view'));
        $this->assertTrue($role->fresh()->hasPermissionTo('permission.view'));
    }

    public function test_user_inherits_role_permissions(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);

        $role = Role::create(['name' => 'Test Role', 'guard_name' => 'web']);
        $role->syncPermissions(['user.view']);

        $user->assignRole('Test Role');

        $this->assertTrue($user->hasPermissionTo('user.view'));
    }

    public function test_super_admin_has_all_permissions(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $user->assignRole('Super Administrator');

        $this->assertTrue($user->hasAllPermissions(Permission::all()->pluck('name')->toArray()));
    }
}
