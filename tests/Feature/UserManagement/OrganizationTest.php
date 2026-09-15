<?php

namespace Tests\Feature\UserManagement;

use App\Enums\UserStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create(['name' => 'organization.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'organization.create', 'guard_name' => 'web']);
        Permission::create(['name' => 'organization.update', 'guard_name' => 'web']);
        Permission::create(['name' => 'organization.delete', 'guard_name' => 'web']);
        Permission::create(['name' => 'branch.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'branch.create', 'guard_name' => 'web']);
        Permission::create(['name' => 'group.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'group.create', 'guard_name' => 'web']);
        Permission::create(['name' => 'user.view', 'guard_name' => 'web']);

        $adminRole = Role::create(['name' => 'Super Administrator', 'guard_name' => 'web']);
        $adminRole->syncPermissions(Permission::all());

        $orgAdminRole = Role::create(['name' => 'Organization Administrator', 'guard_name' => 'web']);
        $orgAdminRole->syncPermissions(Permission::all()->filter(fn ($p) => ! str_contains($p->name, 'audit.')));
    }

    public function test_authorized_users_can_view_organizations(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $user->assignRole('Super Administrator');

        $this->actingAs($user);

        $response = $this->get('/organizations');
        $response->assertStatus(200);
    }

    public function test_unauthorized_users_cannot_view_organizations(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);

        $this->actingAs($user);

        $response = $this->get('/organizations');
        $response->assertStatus(403);
    }

    public function test_authorized_users_can_create_organization(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $user->assignRole('Super Administrator');

        $this->actingAs($user);

        $response = $this->post('/organizations', [
            'name' => 'Test Organization',
            'registration_number' => 'REG001',
            'phone' => '+255700000001',
            'email' => 'org@test.com',
            'region' => 'Dar es Salaam',
            'district' => 'Kinondoni',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('organizations.index'));
        $this->assertDatabaseHas('organizations', ['registration_number' => 'REG001']);
    }

    public function test_duplicate_registration_number_is_rejected(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $user->assignRole('Super Administrator');

        Organization::create([
            'name' => 'Existing Org',
            'registration_number' => 'DUP001',
            'status' => 'active',
        ]);

        $this->actingAs($user);

        $response = $this->post('/organizations', [
            'name' => 'Duplicate Org',
            'registration_number' => 'DUP001',
        ]);

        $response->assertSessionHasErrors('registration_number');
    }

    public function test_authorized_users_can_update_organization(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $user->assignRole('Super Administrator');

        $org = Organization::create([
            'name' => 'Old Name',
            'registration_number' => 'UPD001',
            'status' => 'active',
        ]);

        $this->actingAs($user);

        $response = $this->put("/organizations/{$org->id}", [
            'name' => 'New Name',
            'registration_number' => 'UPD001',
        ]);

        $response->assertRedirect(route('organizations.index'));
        $this->assertDatabaseHas('organizations', ['id' => $org->id, 'name' => 'New Name']);
    }

    public function test_authorized_users_can_delete_organization(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $user->assignRole('Super Administrator');

        $org = Organization::create([
            'name' => 'Delete Me',
            'registration_number' => 'DEL001',
            'status' => 'active',
        ]);

        $this->actingAs($user);

        $response = $this->delete("/organizations/{$org->id}");
        $response->assertRedirect(route('organizations.index'));
        $this->assertDatabaseMissing('organizations', ['id' => $org->id]);
    }

    public function test_org_admin_can_only_see_own_organization(): void
    {
        $admin = User::factory()->create(['status' => UserStatus::Active]);
        $admin->assignRole('Organization Administrator');

        $org1 = Organization::create(['name' => 'Org 1', 'registration_number' => 'ISO001', 'status' => 'active']);
        $org2 = Organization::create(['name' => 'Org 2', 'registration_number' => 'ISO002', 'status' => 'active']);

        $org1->users()->attach($admin->id);

        $this->actingAs($admin);

        $response = $this->get('/organizations');
        $response->assertStatus(200);
        $response->assertSee('Org 1');
        $response->assertDontSee('Org 2');
    }

    public function test_super_admin_can_see_all_organizations(): void
    {
        $admin = User::factory()->create(['status' => UserStatus::Active]);
        $admin->assignRole('Super Administrator');

        Organization::create(['name' => 'Org A', 'registration_number' => 'SA001', 'status' => 'active']);
        Organization::create(['name' => 'Org B', 'registration_number' => 'SA002', 'status' => 'active']);

        $this->actingAs($admin);

        $response = $this->get('/organizations');
        $response->assertSee('Org A');
        $response->assertSee('Org B');
    }

    public function test_user_can_be_assigned_to_organization(): void
    {
        $admin = User::factory()->create(['status' => UserStatus::Active]);
        $admin->assignRole('Super Administrator');

        $org = Organization::create(['name' => 'Test Org', 'registration_number' => 'UA001', 'status' => 'active']);
        $user = User::factory()->create(['status' => UserStatus::Active]);

        $this->actingAs($admin);

        $response = $this->post("/organizations/{$org->id}/assign-user", [
            'user_id' => $user->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $org->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_user_can_be_removed_from_organization(): void
    {
        $admin = User::factory()->create(['status' => UserStatus::Active]);
        $admin->assignRole('Super Administrator');

        $org = Organization::create(['name' => 'Test Org', 'registration_number' => 'UR001', 'status' => 'active']);
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $org->users()->attach($user->id);

        $this->actingAs($admin);

        $response = $this->post("/organizations/{$org->id}/remove-user", [
            'user_id' => $user->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('organization_user', [
            'organization_id' => $org->id,
            'user_id' => $user->id,
        ]);
    }
}
