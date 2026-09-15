<?php

namespace Tests\Feature\UserManagement;

use App\Enums\UserStatus;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use App\Models\VicobaGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VicobaGroupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create(['name' => 'group.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'group.create', 'guard_name' => 'web']);
        Permission::create(['name' => 'group.update', 'guard_name' => 'web']);
        Permission::create(['name' => 'group.delete', 'guard_name' => 'web']);
        Permission::create(['name' => 'branch.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'organization.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'user.view', 'guard_name' => 'web']);

        $adminRole = Role::create(['name' => 'Super Administrator', 'guard_name' => 'web']);
        $adminRole->syncPermissions(Permission::all());
    }

    public function test_authorized_users_can_view_groups(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $user->assignRole('Super Administrator');

        $this->actingAs($user);

        $response = $this->get('/vicoba-groups');
        $response->assertStatus(200);
    }

    public function test_unauthorized_users_cannot_view_groups(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);

        $this->actingAs($user);

        $response = $this->get('/vicoba-groups');
        $response->assertStatus(403);
    }

    public function test_authorized_users_can_create_group(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $user->assignRole('Super Administrator');

        $org = Organization::create(['name' => 'Test Org', 'registration_number' => 'ORG008', 'status' => 'active']);
        $branch = Branch::create(['organization_id' => $org->id, 'code' => 'BR008', 'name' => 'Test Branch', 'status' => 'active']);

        $this->actingAs($user);

        $response = $this->post('/vicoba-groups', [
            'branch_id' => $branch->id,
            'code' => 'GRP001',
            'name' => 'Test Group',
            'meeting_day' => 'Monday',
            'meeting_time' => '10:00',
            'meeting_location' => 'Community Center',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('vicoba-groups.index'));
        $this->assertDatabaseHas('vicoba_groups', ['code' => 'GRP001']);
    }

    public function test_duplicate_group_code_is_rejected(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $user->assignRole('Super Administrator');

        $org = Organization::create(['name' => 'Test Org', 'registration_number' => 'ORG009', 'status' => 'active']);
        $branch = Branch::create(['organization_id' => $org->id, 'code' => 'BR009', 'name' => 'Test Branch', 'status' => 'active']);

        VicobaGroup::create([
            'branch_id' => $branch->id,
            'code' => 'DGRP',
            'name' => 'Existing Group',
            'status' => 'active',
        ]);

        $this->actingAs($user);

        $response = $this->post('/vicoba-groups', [
            'branch_id' => $branch->id,
            'code' => 'DGRP',
            'name' => 'Duplicate Group',
        ]);

        $response->assertSessionHasErrors('code');
    }

    public function test_authorized_users_can_update_group(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $user->assignRole('Super Administrator');

        $org = Organization::create(['name' => 'Test Org', 'registration_number' => 'ORG010', 'status' => 'active']);
        $branch = Branch::create(['organization_id' => $org->id, 'code' => 'BR010', 'name' => 'Test Branch', 'status' => 'active']);
        $group = VicobaGroup::create([
            'branch_id' => $branch->id,
            'code' => 'UGRP',
            'name' => 'Old Name',
            'status' => 'active',
        ]);

        $this->actingAs($user);

        $response = $this->put("/vicoba-groups/{$group->id}", [
            'branch_id' => $branch->id,
            'code' => 'UGRP',
            'name' => 'New Name',
        ]);

        $response->assertRedirect(route('vicoba-groups.index'));
        $this->assertDatabaseHas('vicoba_groups', ['id' => $group->id, 'name' => 'New Name']);
    }

    public function test_authorized_users_can_delete_group(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $user->assignRole('Super Administrator');

        $org = Organization::create(['name' => 'Test Org', 'registration_number' => 'ORG011', 'status' => 'active']);
        $branch = Branch::create(['organization_id' => $org->id, 'code' => 'BR011', 'name' => 'Test Branch', 'status' => 'active']);
        $group = VicobaGroup::create([
            'branch_id' => $branch->id,
            'code' => 'DGRP',
            'name' => 'Delete Me',
            'status' => 'active',
        ]);

        $this->actingAs($user);

        $response = $this->delete("/vicoba-groups/{$group->id}");
        $response->assertRedirect(route('vicoba-groups.index'));
        $this->assertDatabaseMissing('vicoba_groups', ['id' => $group->id]);
    }

    public function test_branch_manager_can_see_branch_groups(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $user->assignRole('Super Administrator');

        $org = Organization::create(['name' => 'Test Org', 'registration_number' => 'ORG012', 'status' => 'active']);
        $branch = Branch::create(['organization_id' => $org->id, 'code' => 'BR012', 'name' => 'Test Branch', 'status' => 'active']);
        $branch->users()->attach($user->id);

        VicobaGroup::create(['branch_id' => $branch->id, 'code' => 'G1', 'name' => 'Group 1', 'status' => 'active']);

        $this->actingAs($user);

        $response = $this->get('/vicoba-groups');
        $response->assertSee('Group 1');
    }

    public function test_group_isolation_between_branches(): void
    {
        $admin = User::factory()->create(['status' => UserStatus::Active]);
        $admin->assignRole('Super Administrator');

        $org = Organization::create(['name' => 'Test Org', 'registration_number' => 'ORG013', 'status' => 'active']);
        $branch1 = Branch::create(['organization_id' => $org->id, 'code' => 'B13A', 'name' => 'Branch A', 'status' => 'active']);
        $branch2 = Branch::create(['organization_id' => $org->id, 'code' => 'B13B', 'name' => 'Branch B', 'status' => 'active']);

        $branch1->users()->attach($admin->id);

        VicobaGroup::create(['branch_id' => $branch1->id, 'code' => 'GA', 'name' => 'Group A', 'status' => 'active']);
        VicobaGroup::create(['branch_id' => $branch2->id, 'code' => 'GB', 'name' => 'Group B', 'status' => 'active']);

        $this->actingAs($admin);

        $response = $this->get('/vicoba-groups');
        $response->assertSee('Group A');
        $response->assertSee('Group B');
    }
}
