<?php

namespace Tests\Feature\UserManagement;

use App\Enums\UserStatus;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BranchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create(['name' => 'branch.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'branch.create', 'guard_name' => 'web']);
        Permission::create(['name' => 'branch.update', 'guard_name' => 'web']);
        Permission::create(['name' => 'branch.delete', 'guard_name' => 'web']);
        Permission::create(['name' => 'organization.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'group.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'user.view', 'guard_name' => 'web']);

        $adminRole = Role::create(['name' => 'Super Administrator', 'guard_name' => 'web']);
        $adminRole->syncPermissions(Permission::all());
    }

    public function test_authorized_users_can_view_branches(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $user->assignRole('Super Administrator');

        $this->actingAs($user);

        $response = $this->get('/branches');
        $response->assertStatus(200);
    }

    public function test_unauthorized_users_cannot_view_branches(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);

        $this->actingAs($user);

        $response = $this->get('/branches');
        $response->assertStatus(403);
    }

    public function test_authorized_users_can_create_branch(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $user->assignRole('Super Administrator');

        $org = Organization::create(['name' => 'Test Org', 'registration_number' => 'ORG001', 'status' => 'active']);

        $this->actingAs($user);

        $response = $this->post('/branches', [
            'organization_id' => $org->id,
            'code' => 'BR001',
            'name' => 'Test Branch',
            'phone' => '+255700000001',
            'manager' => 'John Doe',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('branches.index'));
        $this->assertDatabaseHas('branches', ['code' => 'BR001']);
    }

    public function test_duplicate_branch_code_is_rejected(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $user->assignRole('Super Administrator');

        $org = Organization::create(['name' => 'Test Org', 'registration_number' => 'ORG002', 'status' => 'active']);
        Branch::create([
            'organization_id' => $org->id,
            'code' => 'DUP',
            'name' => 'Existing Branch',
            'status' => 'active',
        ]);

        $this->actingAs($user);

        $response = $this->post('/branches', [
            'organization_id' => $org->id,
            'code' => 'DUP',
            'name' => 'Duplicate Branch',
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_authorized_users_can_update_branch(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $user->assignRole('Super Administrator');

        $org = Organization::create(['name' => 'Test Org', 'registration_number' => 'ORG003', 'status' => 'active']);
        $branch = Branch::create([
            'organization_id' => $org->id,
            'code' => 'UPD',
            'name' => 'Old Name',
            'status' => 'active',
        ]);

        $this->actingAs($user);

        $response = $this->put("/branches/{$branch->id}", [
            'organization_id' => $org->id,
            'code' => 'UPD',
            'name' => 'New Name',
        ]);

        $response->assertRedirect(route('branches.index'));
        $this->assertDatabaseHas('branches', ['id' => $branch->id, 'name' => 'New Name']);
    }

    public function test_authorized_users_can_delete_branch(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $user->assignRole('Super Administrator');

        $org = Organization::create(['name' => 'Test Org', 'registration_number' => 'ORG004', 'status' => 'active']);
        $branch = Branch::create([
            'organization_id' => $org->id,
            'code' => 'DEL',
            'name' => 'Delete Me',
            'status' => 'active',
        ]);

        $this->actingAs($user);

        $response = $this->delete("/branches/{$branch->id}");
        $response->assertRedirect(route('branches.index'));
        $this->assertDatabaseMissing('branches', ['id' => $branch->id]);
    }

    public function test_org_admin_can_see_org_branches(): void
    {
        $admin = User::factory()->create(['status' => UserStatus::Active]);
        $admin->assignRole('Super Administrator');

        $org = Organization::create(['name' => 'Test Org', 'registration_number' => 'ISO005', 'status' => 'active']);
        $org->users()->attach($admin->id);

        $branch1 = Branch::create(['organization_id' => $org->id, 'code' => 'B1', 'name' => 'Branch 1', 'status' => 'active']);
        $branch2 = Branch::create(['organization_id' => $org->id, 'code' => 'B2', 'name' => 'Branch 2', 'status' => 'active']);

        $this->actingAs($admin);

        $response = $this->get('/branches');
        $response->assertSee('Branch 1');
        $response->assertSee('Branch 2');
    }

    public function test_user_can_be_assigned_to_branch(): void
    {
        $admin = User::factory()->create(['status' => UserStatus::Active]);
        $admin->assignRole('Super Administrator');

        $org = Organization::create(['name' => 'Test Org', 'registration_number' => 'ORG006', 'status' => 'active']);
        $branch = Branch::create(['organization_id' => $org->id, 'code' => 'BU', 'name' => 'Test Branch', 'status' => 'active']);
        $user = User::factory()->create(['status' => UserStatus::Active]);

        $this->actingAs($admin);

        $response = $this->post("/branches/{$branch->id}/assign-user", [
            'user_id' => $user->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('branch_user', [
            'branch_id' => $branch->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_user_can_be_removed_from_branch(): void
    {
        $admin = User::factory()->create(['status' => UserStatus::Active]);
        $admin->assignRole('Super Administrator');

        $org = Organization::create(['name' => 'Test Org', 'registration_number' => 'ORG007', 'status' => 'active']);
        $branch = Branch::create(['organization_id' => $org->id, 'code' => 'RU', 'name' => 'Test Branch', 'status' => 'active']);
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $branch->users()->attach($user->id);

        $this->actingAs($admin);

        $response = $this->post("/branches/{$branch->id}/remove-user", [
            'user_id' => $user->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('branch_user', [
            'branch_id' => $branch->id,
            'user_id' => $user->id,
        ]);
    }
}
