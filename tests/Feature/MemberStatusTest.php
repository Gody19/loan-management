<?php

namespace Tests\Feature;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Models\MemberStatusHistory;
use App\Models\Organization;
use App\Models\Branch;
use App\Models\VicobaGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MemberStatusTest extends TestCase
{
    use RefreshDatabase;

    protected Member $member;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::create(['name' => 'member.view', 'guard_name' => 'web']);
        Permission::create(['name' => 'member.create', 'guard_name' => 'web']);
        Permission::create(['name' => 'member.update', 'guard_name' => 'web']);

        $adminRole = Role::create(['name' => 'Super Administrator', 'guard_name' => 'web']);
        $adminRole->syncPermissions(Permission::all());

        $this->admin = User::factory()->create(['status' => 'active']);
        $this->admin->assignRole('Super Administrator');

        $org = Organization::create(['name' => 'Test Org', 'registration_number' => 'ORG-STAT01', 'status' => 'active']);
        $branch = Branch::create(['organization_id' => $org->id, 'code' => 'BR-STAT01', 'name' => 'Test Branch', 'status' => 'active']);
        $group = VicobaGroup::create(['branch_id' => $branch->id, 'code' => 'GRP-STAT01', 'name' => 'Test Group', 'status' => 'active']);

        $this->member = Member::create([
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'vicoba_group_id' => $group->id,
            'first_name' => 'Test',
            'last_name' => 'Member',
            'gender' => 'male',
            'phone' => '+255712345678',
            'joining_date' => '2024-01-15',
            'membership_status' => 'pending',
            'member_number' => 'VCB-000200',
        ]);
    }

    public function test_pending_can_transition_to_active(): void
    {
        $this->actingAs($this->admin);

        $this->post("/members/{$this->member->id}/change-status", [
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('members', [
            'id' => $this->member->id,
            'membership_status' => 'active',
        ]);
    }

    public function test_pending_can_transition_to_inactive(): void
    {
        $this->actingAs($this->admin);

        $this->post("/members/{$this->member->id}/change-status", [
            'status' => 'inactive',
        ]);

        $this->assertDatabaseHas('members', [
            'id' => $this->member->id,
            'membership_status' => 'inactive',
        ]);
    }

    public function test_active_can_transition_to_suspended(): void
    {
        $this->member->update(['membership_status' => 'active']);

        $this->actingAs($this->admin);

        $this->post("/members/{$this->member->id}/change-status", [
            'status' => 'suspended',
        ]);

        $this->assertDatabaseHas('members', [
            'id' => $this->member->id,
            'membership_status' => 'suspended',
        ]);
    }

    public function test_exited_cannot_transition(): void
    {
        $this->member->update(['membership_status' => 'exited']);

        $this->actingAs($this->admin);

        $response = $this->post("/members/{$this->member->id}/change-status", [
            'status' => 'active',
        ]);

        $response->assertSessionHas('error');
    }

    public function test_blacklisted_cannot_transition(): void
    {
        $this->member->update(['membership_status' => 'blacklisted']);

        $this->actingAs($this->admin);

        $response = $this->post("/members/{$this->member->id}/change-status", [
            'status' => 'active',
        ]);

        $response->assertSessionHas('error');
    }

    public function test_status_history_is_recorded(): void
    {
        $this->actingAs($this->admin);

        $this->post("/members/{$this->member->id}/change-status", [
            'status' => 'active',
            'reason' => 'Membership approved',
        ]);

        $this->assertDatabaseHas('member_status_histories', [
            'member_id' => $this->member->id,
            'old_status' => 'pending',
            'new_status' => 'active',
            'reason' => 'Membership approved',
            'changed_by' => $this->admin->id,
        ]);
    }

    public function test_status_history_tracks_multiple_changes(): void
    {
        $this->actingAs($this->admin);

        $this->post("/members/{$this->member->id}/change-status", ['status' => 'active']);
        $this->post("/members/{$this->member->id}/change-status", ['status' => 'suspended', 'reason' => 'Violation']);
        $this->post("/members/{$this->member->id}/change-status", ['status' => 'active', 'reason' => 'Reinstated']);

        $this->assertDatabaseCount('member_status_histories', 3);

        $histories = MemberStatusHistory::where('member_id', $this->member->id)->get();
        $this->assertEquals('pending', $histories[0]->old_status->value);
        $this->assertEquals('active', $histories[0]->new_status->value);
        $this->assertEquals('active', $histories[1]->old_status->value);
        $this->assertEquals('suspended', $histories[1]->new_status->value);
        $this->assertEquals('suspended', $histories[2]->old_status->value);
        $this->assertEquals('active', $histories[2]->new_status->value);
    }
}
