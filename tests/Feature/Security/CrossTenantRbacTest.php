<?php

namespace Tests\Feature\Security;

use App\Models\Organization;
use App\Models\User;
use App\Services\UserService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CrossTenantRbacTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $orgA;
    protected Organization $orgB;
    protected User $adminA;
    protected User $orgBUser;
    protected User $superAdmin;
    protected User $superAdminTarget;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->orgA = Organization::factory()->create(['status' => 'active']);
        $this->orgB = Organization::factory()->create(['status' => 'active']);

        // Org A administrator (scoped to org A only)
        $this->adminA = User::factory()->create();
        $this->adminA->assignRole('Organization Administrator');
        $this->adminA->organizations()->attach($this->orgA->id);

        // A normal user belonging to org B
        $this->orgBUser = User::factory()->create();
        $this->orgBUser->assignRole('VICOBA Member');
        $this->orgBUser->organizations()->attach($this->orgB->id);

        $this->superAdmin = User::factory()->create();
        $this->superAdmin->assignRole('Super Administrator');

        $this->superAdminTarget = User::factory()->create();
        $this->superAdminTarget->assignRole('Super Administrator');
    }

    private function validUserPayload(array $overrides = []): array
    {
        return array_merge([
            'fullname' => 'Alice Mahenge',
            'username' => 'alice.mahenge',
            'email' => 'alice@example.com',
            'nida_number' => '19850110012345678901',
            'password' => 'password',
            'password_confirmation' => 'password',
            'roles' => ['VICOBA Member'],
        ], $overrides);
    }

    public function test_org_admin_cannot_create_user_with_super_administrator_role(): void
    {
        $this->actingAs($this->adminA);

        $response = $this->post('/users', $this->validUserPayload([
            'roles' => ['Super Administrator'],
        ]));

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'alice@example.com']);
    }

    public function test_org_admin_cannot_update_super_administrator_account(): void
    {
        $this->actingAs($this->adminA);

        $response = $this->put("/users/{$this->superAdminTarget->id}", [
            'fullname' => 'Hacked Name',
            'username' => $this->superAdminTarget->username,
            'email' => $this->superAdminTarget->email,
            'nida_number' => $this->superAdminTarget->nida_number,
            'status' => 'active',
            'roles' => ['Super Administrator'],
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $this->superAdminTarget->id, 'fullname' => $this->superAdminTarget->fullname]);
    }

    public function test_org_admin_cannot_remove_super_administrator_role_from_target(): void
    {
        $this->actingAs($this->adminA);

        $response = $this->put("/users/{$this->superAdminTarget->id}", [
            'fullname' => $this->superAdminTarget->fullname,
            'username' => $this->superAdminTarget->username,
            'email' => $this->superAdminTarget->email,
            'nida_number' => $this->superAdminTarget->nida_number,
            'status' => 'active',
            'roles' => ['VICOBA Member'],
        ]);

        $response->assertStatus(403);
        $this->assertTrue($this->superAdminTarget->fresh()->hasRole('Super Administrator'));
    }

    public function test_org_admin_cannot_update_user_from_other_organization(): void
    {
        $this->actingAs($this->adminA);

        $response = $this->put("/users/{$this->orgBUser->id}", [
            'fullname' => 'Cross Tenant Edit',
            'username' => $this->orgBUser->username,
            'email' => $this->orgBUser->email,
            'nida_number' => $this->orgBUser->nida_number,
            'status' => 'active',
            'roles' => ['VICOBA Member'],
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $this->orgBUser->id, 'fullname' => $this->orgBUser->fullname]);
    }

    public function test_org_admin_cannot_view_other_organization_user(): void
    {
        $this->actingAs($this->adminA);

        $response = $this->get("/users/{$this->orgBUser->id}");

        $response->assertStatus(403);
    }

    public function test_org_admin_cannot_create_role(): void
    {
        $this->actingAs($this->adminA);

        $response = $this->post('/roles', [
            'name' => 'Malicious Role',
            'permissions' => ['role.create'],
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('roles', ['name' => 'Malicious Role']);
    }

    public function test_org_admin_cannot_update_role(): void
    {
        $role = Role::create(['name' => 'Global Role', 'guard_name' => 'web']);

        $this->actingAs($this->adminA);

        $response = $this->put("/roles/{$role->id}", [
            'name' => 'Renamed Global Role',
            'permissions' => ['user.view'],
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'Global Role']);
    }

    public function test_org_admin_cannot_delete_role(): void
    {
        $role = Role::create(['name' => 'Global Role', 'guard_name' => 'web']);

        $this->actingAs($this->adminA);

        $response = $this->delete("/roles/{$role->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_super_admin_can_assign_super_administrator_role(): void
    {
        $this->actingAs($this->superAdmin);

        $response = $this->post('/users', $this->validUserPayload([
            'roles' => ['Super Administrator'],
        ]));

        $response->assertRedirect(route('users.index'));

        $created = User::where('email', 'alice@example.com')->firstOrFail();
        $this->assertTrue($created->hasRole('Super Administrator'));
    }

    public function test_org_admin_can_manage_non_privileged_user_in_own_organization(): void
    {
        $ownUser = User::factory()->create();
        $ownUser->assignRole('VICOBA Member');
        $ownUser->organizations()->attach($this->orgA->id);

        $this->actingAs($this->adminA);

        $response = $this->put("/users/{$ownUser->id}", [
            'fullname' => 'Updated Own User',
            'username' => $ownUser->username,
            'email' => $ownUser->email,
            'nida_number' => $ownUser->nida_number,
            'status' => 'active',
            'roles' => ['VICOBA Member'],
        ]);

        $response->assertRedirect(route('users.index'));
        $this->assertDatabaseHas('users', ['id' => $ownUser->id, 'fullname' => 'Updated Own User']);
    }

    public function test_user_service_guard_blocks_super_administrator_assignment(): void
    {
        $this->actingAs($this->adminA);

        $target = User::factory()->create();

        $this->expectException(AuthorizationException::class);

        app(UserService::class)->syncRoles($target, ['Super Administrator']);
    }
}