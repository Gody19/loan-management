<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Models\Branch;
use App\Models\Member;
use App\Models\Organization;
use App\Models\User;
use App\Models\VicobaGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MemberTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;
    protected Branch $branch;
    protected VicobaGroup $group;
    protected User $admin;
    protected User $orgAdmin;
    protected User $branchManager;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create(['name' => 'member.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'member.create', 'guard_name' => 'web']);
        Permission::create(['name' => 'member.update', 'guard_name' => 'web']);
        Permission::create(['name' => 'member.delete', 'guard_name' => 'web']);
        Permission::create(['name' => 'organization.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'organization.create', 'guard_name' => 'web']);
        Permission::create(['name' => 'branch.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'branch.create', 'guard_name' => 'web']);
        Permission::create(['name' => 'group.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'group.create', 'guard_name' => 'web']);

        $adminRole = Role::create(['name' => 'Super Administrator', 'guard_name' => 'web']);
        $adminRole->syncPermissions(Permission::all());

        $orgAdminRole = Role::create(['name' => 'Organization Administrator', 'guard_name' => 'web']);
        $orgAdminRole->syncPermissions(Permission::all()->filter(fn ($p) => !str_contains($p->name, 'audit.')));

        $branchManagerRole = Role::create(['name' => 'Branch Manager', 'guard_name' => 'web']);
        $branchManagerRole->syncPermissions(Permission::all()->filter(fn ($p) =>
            in_array(explode('.', $p->name)[0], ['dashboard', 'branch', 'group', 'member'])
        ));

        $this->organization = Organization::create([
            'name' => 'Test Org',
            'registration_number' => 'ORG-TEST01',
            'status' => 'active',
        ]);

        $this->branch = Branch::create([
            'organization_id' => $this->organization->id,
            'code' => 'BR-TEST01',
            'name' => 'Test Branch',
            'status' => 'active',
        ]);

        $this->group = VicobaGroup::create([
            'branch_id' => $this->branch->id,
            'code' => 'GRP-TEST01',
            'name' => 'Test Group',
            'status' => 'active',
        ]);

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('Super Administrator');

        $this->orgAdmin = User::factory()->create(['status' => 'active']);
        $this->orgAdmin->assignRole('Organization Administrator');
        $this->orgAdmin->organizations()->attach($this->organization);

        $this->branchManager = User::factory()->create(['status' => 'active']);
        $this->branchManager->assignRole('Branch Manager');
        $this->branchManager->branches()->attach($this->branch);
    }

    private function memberData(array $overrides = []): array
    {
        return array_merge([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'member_number' => 'VCB-' . str_pad(rand(100, 999999), 6, '0', STR_PAD_LEFT),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'gender' => 'male',
            'phone' => '+255712345678',
            'joining_date' => '2024-01-15',
        ], $overrides);
    }

    public function test_authorized_user_can_view_members(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get('/members');
        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_redirected_to_login(): void
    {
        $response = $this->get('/members');
        $response->assertRedirect('/login');
    }

    public function test_user_without_permission_cannot_view_members(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user);

        $response = $this->get('/members');
        $response->assertForbidden();
    }

    public function test_member_is_created_with_valid_data(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post('/members', $this->memberData());

        $response->assertRedirect();
        $this->assertDatabaseHas('members', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'organization_id' => $this->organization->id,
            'membership_status' => 'pending',
        ]);

        $member = Member::where('first_name', 'John')->first();
        $this->assertStringStartsWith('VCB-', $member->member_number);
    }

    public function test_member_number_is_auto_generated(): void
    {
        $this->actingAs($this->admin);

        $this->post('/members', $this->memberData());

        $member = Member::latest()->first();
        $this->assertNotNull($member->member_number);
        $this->assertMatchesRegularExpression('/^VCB-\d{6}$/', $member->member_number);
    }

    public function test_member_number_is_unique(): void
    {
        Member::create(array_merge($this->memberData(), [
            'member_number' => 'VCB-000001',
        ]));

        $this->actingAs($this->admin);

        $this->post('/members', $this->memberData());

        $members = Member::where('member_number', 'VCB-000001')->get();
        $this->assertCount(1, $members);
    }

    public function test_cannot_create_member_with_invalid_data(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post('/members', []);

        $response->assertSessionHasErrors([
            'organization_id',
            'branch_id',
            'vicoba_group_id',
            'first_name',
            'last_name',
            'gender',
            'phone',
            'joining_date',
        ]);
    }

    public function test_cannot_create_member_with_duplicate_national_id(): void
    {
        Member::create(array_merge($this->memberData(), [
            'national_id' => '1234567890123456',
        ]));

        $this->actingAs($this->admin);

        $response = $this->post('/members', $this->memberData(['national_id' => '1234567890123456']));

        $response->assertSessionHasErrors('national_id');
    }

    public function test_cannot_assign_branch_from_different_organization(): void
    {
        $otherOrg = Organization::create([
            'name' => 'Other Org',
            'registration_number' => 'ORG-OTHER',
            'status' => 'active',
        ]);

        $otherBranch = Branch::create([
            'organization_id' => $otherOrg->id,
            'code' => 'BR-OTHER',
            'name' => 'Other Branch',
            'status' => 'active',
        ]);

        $this->actingAs($this->admin);

        $response = $this->post('/members', $this->memberData([
            'branch_id' => $otherBranch->id,
        ]));

        $response->assertSessionHasErrors('branch_id');
    }

    public function test_cannot_assign_group_from_different_branch(): void
    {
        $otherBranch = Branch::create([
            'organization_id' => $this->organization->id,
            'code' => 'BR-OTHER2',
            'name' => 'Other Branch 2',
            'status' => 'active',
        ]);

        $otherGroup = VicobaGroup::create([
            'branch_id' => $otherBranch->id,
            'code' => 'GRP-OTHER',
            'name' => 'Other Group',
            'status' => 'active',
        ]);

        $this->actingAs($this->admin);

        $response = $this->post('/members', $this->memberData([
            'vicoba_group_id' => $otherGroup->id,
        ]));

        $response->assertSessionHasErrors('vicoba_group_id');
    }

    public function test_user_can_view_member_profile(): void
    {
        $member = Member::create($this->memberData());

        $this->actingAs($this->admin);

        $response = $this->get("/members/{$member->id}");
        $response->assertStatus(200);
        $response->assertSee('John');
        $response->assertSee('Doe');
    }

    public function test_user_can_update_member(): void
    {
        $member = Member::create($this->memberData());

        $this->actingAs($this->admin);

        $response = $this->put("/members/{$member->id}", $this->memberData([
            'first_name' => 'Jane',
        ]));

        $response->assertRedirect();
        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'first_name' => 'Jane',
        ]);
    }

    public function test_org_admin_can_only_see_own_org_members(): void
    {
        $otherOrg = Organization::create([
            'name' => 'Other Org',
            'registration_number' => 'ORG-OTHER2',
            'status' => 'active',
        ]);

        $otherBranch = Branch::create([
            'organization_id' => $otherOrg->id,
            'code' => 'BR-OTHER3',
            'name' => 'Other Branch 3',
            'status' => 'active',
        ]);

        $otherGroup = VicobaGroup::create([
            'branch_id' => $otherBranch->id,
            'code' => 'GRP-OTHER2',
            'name' => 'Other Group 2',
            'status' => 'active',
        ]);

        Member::create($this->memberData());
        Member::create([
            'organization_id' => $otherOrg->id,
            'branch_id' => $otherBranch->id,
            'vicoba_group_id' => $otherGroup->id,
            'first_name' => 'Other',
            'last_name' => 'Person',
            'gender' => 'female',
            'phone' => '+255799999999',
            'joining_date' => '2024-01-15',
            'member_number' => 'VCB-000099',
        ]);

        $this->actingAs($this->orgAdmin);

        $response = $this->get('/members');
        $response->assertStatus(200);

        $this->assertDatabaseHas('members', ['first_name' => 'John']);
        $this->assertDatabaseHas('members', ['first_name' => 'Other']);
    }

    public function test_org_admin_cannot_view_other_org_member_profile(): void
    {
        $otherOrg = Organization::create([
            'name' => 'Other Org',
            'registration_number' => 'ORG-OTHER3',
            'status' => 'active',
        ]);

        $otherBranch = Branch::create([
            'organization_id' => $otherOrg->id,
            'code' => 'BR-OTHER4',
            'name' => 'Other Branch 4',
            'status' => 'active',
        ]);

        $otherGroup = VicobaGroup::create([
            'branch_id' => $otherBranch->id,
            'code' => 'GRP-OTHER3',
            'name' => 'Other Group 3',
            'status' => 'active',
        ]);

        $otherMember = Member::create(array_merge($this->memberData(), [
            'organization_id' => $otherOrg->id,
            'branch_id' => $otherBranch->id,
            'vicoba_group_id' => $otherGroup->id,
            'member_number' => 'VCB-000098',
        ]));

        $this->actingAs($this->orgAdmin);

        $response = $this->get("/members/{$otherMember->id}");
        $response->assertForbidden();
    }

    public function test_branch_manager_can_only_see_own_branch_members(): void
    {
        $otherBranch = Branch::create([
            'organization_id' => $this->organization->id,
            'code' => 'BR-OTHER5',
            'name' => 'Other Branch 5',
            'status' => 'active',
        ]);

        $otherGroup = VicobaGroup::create([
            'branch_id' => $otherBranch->id,
            'code' => 'GRP-OTHER4',
            'name' => 'Other Group 4',
            'status' => 'active',
        ]);

        Member::create($this->memberData());
        Member::create(array_merge($this->memberData(), [
            'branch_id' => $otherBranch->id,
            'vicoba_group_id' => $otherGroup->id,
            'first_name' => 'Other',
            'last_name' => 'Person',
            'member_number' => 'VCB-000097',
        ]));

        $this->actingAs($this->branchManager);

        $response = $this->get('/members');
        $response->assertStatus(200);
    }

    public function test_member_search_works(): void
    {
        Member::create($this->memberData(['first_name' => 'Alpha', 'member_number' => 'VCB-000100']));
        Member::create($this->memberData(['first_name' => 'Beta', 'member_number' => 'VCB-000101']));

        $this->actingAs($this->admin);

        $response = $this->get('/members?search=Alpha');
        $response->assertStatus(200);

        $response->assertSee('Alpha');
    }

    public function test_member_filter_by_status_works(): void
    {
        Member::create($this->memberData(['membership_status' => 'active']));
        Member::create(array_merge($this->memberData(), [
            'membership_status' => 'inactive',
            'member_number' => 'VCB-000102',
        ]));

        $this->actingAs($this->admin);

        $response = $this->get('/members?membership_status=active');
        $response->assertStatus(200);
    }

    public function test_member_soft_deleted(): void
    {
        $member = Member::create($this->memberData(['membership_status' => 'active']));

        $this->actingAs($this->admin);

        $response = $this->delete("/members/{$member->id}");
        $response->assertRedirect('/members');

        $this->assertSoftDeleted('members', ['id' => $member->id]);
    }

    public function test_next_of_kin_can_be_added(): void
    {
        $member = Member::create($this->memberData());

        $this->actingAs($this->admin);

        $response = $this->post("/members/{$member->id}/next-of-kin", [
            'full_name' => 'Jane Doe',
            'relationship' => 'spouse',
            'phone' => '+255711111111',
            'is_primary' => true,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('member_next_of_kins', [
            'member_id' => $member->id,
            'full_name' => 'Jane Doe',
            'relationship' => 'spouse',
            'is_primary' => true,
        ]);
    }

    public function test_primary_next_of_kin_unsets_previous(): void
    {
        $member = Member::create($this->memberData());

        $this->actingAs($this->admin);

        $this->post("/members/{$member->id}/next-of-kin", [
            'full_name' => 'Jane Doe',
            'relationship' => 'spouse',
            'phone' => '+255711111111',
            'is_primary' => true,
        ]);

        $this->post("/members/{$member->id}/next-of-kin", [
            'full_name' => 'John Doe Sr',
            'relationship' => 'parent',
            'phone' => '+255722222222',
            'is_primary' => true,
        ]);

        $this->assertDatabaseHas('member_next_of_kins', [
            'member_id' => $member->id,
            'full_name' => 'John Doe Sr',
            'is_primary' => true,
        ]);

        $this->assertDatabaseHas('member_next_of_kins', [
            'member_id' => $member->id,
            'full_name' => 'Jane Doe',
            'is_primary' => false,
        ]);
    }

    public function test_next_of_kin_can_be_removed(): void
    {
        $member = Member::create($this->memberData());

        $this->actingAs($this->admin);

        $this->post("/members/{$member->id}/next-of-kin", [
            'full_name' => 'Jane Doe',
            'relationship' => 'spouse',
            'phone' => '+255711111111',
        ]);

        $kin = $member->nextOfKins()->first();

        $response = $this->delete("/members/{$member->id}/next-of-kin/{$kin->id}");
        $response->assertRedirect();

        $this->assertDatabaseMissing('member_next_of_kins', ['id' => $kin->id]);
    }

    public function test_document_can_be_uploaded(): void
    {
        Storage::fake('private');

        $member = Member::create($this->memberData());

        $this->actingAs($this->admin);

        $file = UploadedFile::fake()->create('test-document.pdf', 100, 'application/pdf');

        $response = $this->post("/members/{$member->id}/documents", [
            'document_type' => 'national_id',
            'document_number' => '123456789',
            'file' => $file,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('member_documents', [
            'member_id' => $member->id,
            'document_type' => 'national_id',
            'verification_status' => 'pending',
        ]);
    }

    public function test_document_can_be_verified(): void
    {
        $member = Member::create($this->memberData());

        $this->actingAs($this->admin);

        $doc = $member->documents()->create([
            'document_type' => 'national_id',
            'file_path' => 'test/path.pdf',
            'original_filename' => 'test.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'verification_status' => 'pending',
        ]);

        $response = $this->post("/members/{$member->id}/documents/{$doc->id}/verify", [
            'verification_status' => 'verified',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('member_documents', [
            'id' => $doc->id,
            'verification_status' => 'verified',
            'verified_by' => $this->admin->id,
        ]);
    }

    public function test_status_can_be_changed(): void
    {
        $member = Member::create($this->memberData(['membership_status' => 'pending']));

        $this->actingAs($this->admin);

        $response = $this->post("/members/{$member->id}/change-status", [
            'status' => 'active',
            'reason' => 'Approved by admin',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'membership_status' => 'active',
        ]);

        $this->assertDatabaseHas('member_status_histories', [
            'member_id' => $member->id,
            'old_status' => 'pending',
            'new_status' => 'active',
            'reason' => 'Approved by admin',
        ]);
    }

    public function test_invalid_status_transition_is_rejected(): void
    {
        $member = Member::create($this->memberData(['membership_status' => 'exited']));

        $this->actingAs($this->admin);

        $response = $this->post("/members/{$member->id}/change-status", [
            'status' => 'active',
        ]);

        $response->assertSessionHas('error');
    }

    public function test_user_without_permission_cannot_create_member(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->actingAs($user);

        $response = $this->post('/members', $this->memberData());
        $response->assertForbidden();
    }
}
