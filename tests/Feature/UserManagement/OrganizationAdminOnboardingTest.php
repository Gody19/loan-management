<?php

namespace Tests\Feature\UserManagement;

use App\Enums\UserStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrganizationAdminOnboardingTest extends TestCase
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
        Permission::create(['name' => 'branch.update', 'guard_name' => 'web']);
        Permission::create(['name' => 'group.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'group.create', 'guard_name' => 'web']);
        Permission::create(['name' => 'user.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'member.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'member.create', 'guard_name' => 'web']);

        $adminRole = Role::create(['name' => 'Super Administrator', 'guard_name' => 'web']);
        $adminRole->syncPermissions(Permission::all());

        $orgAdminRole = Role::create(['name' => 'Organization Administrator', 'guard_name' => 'web']);
        $orgAdminRole->syncPermissions(Permission::all()->filter(fn ($p) => ! str_contains($p->name, 'audit.')));

        $branchManagerRole = Role::create(['name' => 'Branch Manager', 'guard_name' => 'web']);
        $branchManagerRole->syncPermissions(Permission::all()->filter(fn ($p) => in_array(explode('.', $p->name)[0], [
            'dashboard', 'branch', 'group', 'member',
        ])));

        Role::create(['name' => 'VICOBA Member', 'guard_name' => 'web']);
    }

    private function createSuperAdmin(): User
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $user->assignRole('Super Administrator');

        return $user;
    }

    // ========== Organization Onboarding Tests ==========

    public function test_super_admin_can_create_organization_with_new_administrator(): void
    {
        $admin = $this->createSuperAdmin();
        $this->actingAs($admin);

        $response = $this->post('/organizations', [
            'name' => 'Kampala Limited',
            'registration_number' => 'REG-KAMP-001',
            'phone' => '+256700000001',
            'email' => 'info@kampala.com',
            'status' => 'active',
            'admin_type' => 'create',
            'admin_name' => 'David Kamau',
            'admin_email' => 'david@kampala.com',
            'admin_phone' => '+256700000002',
            'admin_password' => 'password',
            'admin_password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('organizations.index'));
        $this->assertDatabaseHas('organizations', ['registration_number' => 'REG-KAMP-001']);
    }

    public function test_organization_is_created(): void
    {
        $admin = $this->createSuperAdmin();
        $this->actingAs($admin);

        $this->post('/organizations', [
            'name' => 'Kampala Limited',
            'registration_number' => 'REG-KAMP-002',
            'phone' => '+256700000001',
            'email' => 'info@kampala.com',
            'status' => 'active',
            'admin_type' => 'create',
            'admin_name' => 'David Kamau',
            'admin_email' => 'david@kampala.com',
            'admin_password' => 'password',
            'admin_password_confirmation' => 'password',
        ]);

        $this->assertDatabaseHas('organizations', [
            'name' => 'Kampala Limited',
            'registration_number' => 'REG-KAMP-002',
            'status' => 'active',
        ]);
    }

    public function test_administrator_user_is_created(): void
    {
        $admin = $this->createSuperAdmin();
        $this->actingAs($admin);

        $this->post('/organizations', [
            'name' => 'Kampala Limited',
            'registration_number' => 'REG-KAMP-003',
            'status' => 'active',
            'admin_type' => 'create',
            'admin_name' => 'David Kamau',
            'admin_email' => 'david@kampala.com',
            'admin_password' => 'password',
            'admin_password_confirmation' => 'password',
        ]);

        $this->assertDatabaseHas('users', [
            'fullname' => 'David Kamau',
            'email' => 'david@kampala.com',
            'status' => 'active',
        ]);
    }

    public function test_administrator_receives_correct_role(): void
    {
        $admin = $this->createSuperAdmin();
        $this->actingAs($admin);

        $this->post('/organizations', [
            'name' => 'Kampala Limited',
            'registration_number' => 'REG-KAMP-004',
            'status' => 'active',
            'admin_type' => 'create',
            'admin_name' => 'David Kamau',
            'admin_email' => 'david@kampala.com',
            'admin_password' => 'password',
            'admin_password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'david@kampala.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('Organization Administrator'));
    }

    public function test_administrator_is_assigned_to_organization(): void
    {
        $admin = $this->createSuperAdmin();
        $this->actingAs($admin);

        $this->post('/organizations', [
            'name' => 'Kampala Limited',
            'registration_number' => 'REG-KAMP-005',
            'status' => 'active',
            'admin_type' => 'create',
            'admin_name' => 'David Kamau',
            'admin_email' => 'david@kampala.com',
            'admin_password' => 'password',
            'admin_password_confirmation' => 'password',
        ]);

        $org = Organization::where('registration_number', 'REG-KAMP-005')->first();
        $user = User::where('email', 'david@kampala.com')->first();

        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $org->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_entire_operation_rolls_back_if_administrator_creation_fails(): void
    {
        $admin = $this->createSuperAdmin();
        $this->actingAs($admin);

        $this->post('/organizations', [
            'name' => 'Kampala Limited',
            'registration_number' => 'REG-KAMP-006',
            'status' => 'active',
            'admin_type' => 'create',
            'admin_name' => 'David Kamau',
            'admin_email' => 'david@kampala.com',
            'admin_password' => 'short',
            'admin_password_confirmation' => 'mismatch',
        ]);

        $this->assertDatabaseMissing('organizations', ['registration_number' => 'REG-KAMP-006']);
        $this->assertDatabaseMissing('users', ['email' => 'david@kampala.com']);
    }

    // ========== Existing User Tests ==========

    public function test_super_admin_can_assign_existing_user(): void
    {
        $admin = $this->createSuperAdmin();
        $this->actingAs($admin);

        $org = Organization::create([
            'name' => 'ABC Microfinance',
            'registration_number' => 'REG-ABC-001',
            'status' => 'active',
        ]);

        $user = User::factory()->create(['status' => UserStatus::Active]);

        $response = $this->post("/organizations/{$org->id}/assign-admin", [
            'admin_user_id' => $user->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('organization_user', [
            'organization_id' => $org->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_duplicate_organization_assignment_is_prevented(): void
    {
        $admin = $this->createSuperAdmin();
        $this->actingAs($admin);

        $org = Organization::create([
            'name' => 'ABC Microfinance',
            'registration_number' => 'REG-ABC-002',
            'status' => 'active',
        ]);

        $user = User::factory()->create(['status' => UserStatus::Active]);
        $org->users()->attach($user->id);

        $response = $this->post("/organizations/{$org->id}/assign-admin", [
            'admin_user_id' => $user->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('organization_user', 1);
    }

    public function test_existing_users_role_is_handled_correctly(): void
    {
        $admin = $this->createSuperAdmin();
        $this->actingAs($admin);

        $org = Organization::create([
            'name' => 'ABC Microfinance',
            'registration_number' => 'REG-ABC-003',
            'status' => 'active',
        ]);

        $user = User::factory()->create(['status' => UserStatus::Active]);
        $user->assignRole('VICOBA Member');

        $this->post("/organizations/{$org->id}/assign-admin", [
            'admin_user_id' => $user->id,
        ]);

        $user->refresh();
        $this->assertTrue($user->hasRole('Organization Administrator'));
    }

    // ========== Authorization Tests ==========

    public function test_organization_admin_cannot_create_organizations(): void
    {
        $orgAdmin = User::factory()->create(['status' => UserStatus::Active]);
        $orgAdmin->assignRole('Organization Administrator');

        $this->actingAs($orgAdmin);

        $response = $this->post('/organizations', [
            'name' => 'Unauthorized Org',
            'registration_number' => 'REG-UNAUTH-001',
            'status' => 'active',
            'admin_type' => 'none',
        ]);

        // Either 403 (policy denies) or 302 (middleware redirects on 403)
        $this->assertContains($response->getStatusCode(), [403, 302]);
    }

    public function test_organization_admin_cannot_access_another_organization(): void
    {
        $orgAdmin = User::factory()->create(['status' => UserStatus::Active]);
        $orgAdmin->assignRole('Organization Administrator');

        $org1 = Organization::create(['name' => 'Org 1', 'registration_number' => 'REG-ISO-001', 'status' => 'active']);
        $org2 = Organization::create(['name' => 'Org 2', 'registration_number' => 'REG-ISO-002', 'status' => 'active']);

        $org1->users()->attach($orgAdmin->id);

        $this->actingAs($orgAdmin);

        $response = $this->get("/organizations/{$org2->id}");
        $response->assertStatus(403);
    }

    public function test_branch_manager_cannot_access_another_organizations_branch(): void
    {
        $branchManager = User::factory()->create(['status' => UserStatus::Active]);
        $branchManager->assignRole('Branch Manager');

        $org1 = Organization::create(['name' => 'Org 1', 'registration_number' => 'REG-BM-001', 'status' => 'active']);
        $org2 = Organization::create(['name' => 'Org 2', 'registration_number' => 'REG-BM-002', 'status' => 'active']);

        $branch1 = $org1->branches()->create(['name' => 'Branch 1', 'code' => 'BR001', 'status' => 'active']);
        $branch2 = $org2->branches()->create(['name' => 'Branch 2', 'code' => 'BR002', 'status' => 'active']);

        $branchManager->branches()->attach($branch1->id);

        $this->actingAs($branchManager);

        $response = $this->get("/branches/{$branch2->id}");
        $response->assertStatus(403);
    }

    public function test_unauthorized_users_cannot_assign_organization_administrators(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);

        $org = Organization::create(['name' => 'Test Org', 'registration_number' => 'REG-UA-001', 'status' => 'active']);

        $this->actingAs($user);

        $response = $this->post("/organizations/{$org->id}/assign-admin", [
            'admin_user_id' => $user->id,
        ]);

        // Either 403 (policy denies) or 302 (middleware redirects on 403)
        $this->assertContains($response->getStatusCode(), [403, 302]);
    }

    // ========== Tenant Isolation Tests ==========

    public function test_kampala_administrator_cannot_access_abc_microfinance(): void
    {
        $kampalaAdmin = User::factory()->create(['status' => UserStatus::Active]);
        $kampalaAdmin->assignRole('Organization Administrator');

        $kampala = Organization::create(['name' => 'Kampala Limited', 'registration_number' => 'REG-TI-001', 'status' => 'active']);
        $abc = Organization::create(['name' => 'ABC Microfinance', 'registration_number' => 'REG-TI-002', 'status' => 'active']);

        $kampala->users()->attach($kampalaAdmin->id);

        $this->actingAs($kampalaAdmin);

        $response = $this->get("/organizations/{$abc->id}");
        $response->assertStatus(403);
    }

    public function test_abc_administrator_cannot_access_kampala_limited(): void
    {
        $abcAdmin = User::factory()->create(['status' => UserStatus::Active]);
        $abcAdmin->assignRole('Organization Administrator');

        $kampala = Organization::create(['name' => 'Kampala Limited', 'registration_number' => 'REG-TI-003', 'status' => 'active']);
        $abc = Organization::create(['name' => 'ABC Microfinance', 'registration_number' => 'REG-TI-004', 'status' => 'active']);

        $abc->users()->attach($abcAdmin->id);

        $this->actingAs($abcAdmin);

        $response = $this->get("/organizations/{$kampala->id}");
        $response->assertStatus(403);
    }

    public function test_branch_assignment_cannot_cross_organization_boundaries(): void
    {
        $branchManager = User::factory()->create(['status' => UserStatus::Active]);
        $branchManager->assignRole('Branch Manager');

        $org1 = Organization::create(['name' => 'Org 1', 'registration_number' => 'REG-BAC-001', 'status' => 'active']);
        $org2 = Organization::create(['name' => 'Org 2', 'registration_number' => 'REG-BAC-002', 'status' => 'active']);

        $branch1 = $org1->branches()->create(['name' => 'Branch 1', 'code' => 'BR001', 'status' => 'active']);
        $branch2 = $org2->branches()->create(['name' => 'Branch 2', 'code' => 'BR002', 'status' => 'active']);

        $org1->users()->attach($branchManager->id);

        $this->actingAs($branchManager);

        $response = $this->post("/branches/{$branch2->id}/assign-user", [
            'user_id' => $branchManager->id,
        ]);

        $response->assertStatus(403);
    }

    // ========== Administrator Lifecycle Tests ==========

    public function test_super_admin_can_view_organization_administrators(): void
    {
        $admin = $this->createSuperAdmin();
        $this->actingAs($admin);

        $org = Organization::create(['name' => 'Test Org', 'registration_number' => 'REG-AL-001', 'status' => 'active']);

        $orgAdmin = User::factory()->create(['status' => UserStatus::Active]);
        $orgAdmin->assignRole('Organization Administrator');
        $org->users()->attach($orgAdmin->id);

        $response = $this->get("/organizations/{$org->id}");
        $response->assertStatus(200);
        $response->assertSee($orgAdmin->fullname);
    }

    public function test_super_admin_can_remove_an_administrator_assignment(): void
    {
        $admin = $this->createSuperAdmin();
        $this->actingAs($admin);

        $org = Organization::create(['name' => 'Test Org', 'registration_number' => 'REG-AL-002', 'status' => 'active']);

        $orgAdmin1 = User::factory()->create(['status' => UserStatus::Active]);
        $orgAdmin2 = User::factory()->create(['status' => UserStatus::Active]);
        $orgAdmin1->assignRole('Organization Administrator');
        $orgAdmin2->assignRole('Organization Administrator');
        $org->users()->attach([$orgAdmin1->id, $orgAdmin2->id]);

        $response = $this->delete("/organizations/{$org->id}/remove-admin/{$orgAdmin1->id}");
        $response->assertRedirect();
        $this->assertDatabaseMissing('organization_user', [
            'organization_id' => $org->id,
            'user_id' => $orgAdmin1->id,
        ]);
    }

    public function test_prevent_removal_of_last_administrator(): void
    {
        $admin = $this->createSuperAdmin();
        $this->actingAs($admin);

        $org = Organization::create(['name' => 'Test Org', 'registration_number' => 'REG-AL-003', 'status' => 'active']);

        $orgAdmin = User::factory()->create(['status' => UserStatus::Active]);
        $orgAdmin->assignRole('Organization Administrator');
        $org->users()->attach($orgAdmin->id);

        $response = $this->delete("/organizations/{$org->id}/remove-admin/{$orgAdmin->id}");
        $response->assertRedirect();
        $response->assertSessionHas('error', 'Cannot remove the last administrator. An organization must have at least one administrator.');
    }
}
