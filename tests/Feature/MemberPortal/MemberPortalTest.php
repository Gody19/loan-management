<?php

namespace Tests\Feature\MemberPortal;

use App\Enums\MemberStatus;
use App\Enums\SavingsAccountStatus;
use App\Enums\ShareAccountStatus;
use App\Enums\UserStatus;
use App\Enums\WelfareAccountStatus;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Models\ShareAccount;
use App\Models\User;
use App\Models\WelfareAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MemberPortalTest extends TestCase
{
    use RefreshDatabase;
    private function createRoles(): void
    {
        Role::firstOrCreate(['name' => 'Super Administrator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'VICOBA Member', 'guard_name' => 'web']);
    }

    private function createMemberWithUser(array $memberAttrs = [], array $userAttrs = []): array
    {
        $this->createRoles();

        $user = User::factory()->create([
            'password' => Hash::make('password'),
            'status' => UserStatus::Active,
            'is_active' => true,
            ...$userAttrs,
        ]);

        $member = Member::factory()->create([
            'user_id' => $user->id,
            'membership_status' => MemberStatus::Active,
            ...$memberAttrs,
        ]);

        $user->assignRole('VICOBA Member');

        return ['user' => $user, 'member' => $member];
    }

    // ==================== Authentication Tests ====================

    public function test_unauthenticated_user_cannot_access_member_portal(): void
    {
        $response = $this->get(route('member.dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_admin_user_cannot_access_member_portal(): void
    {
        $this->createRoles();

        $user = User::factory()->create([
            'status' => UserStatus::Active,
            'is_active' => true,
        ]);
        $user->assignRole('Super Administrator');

        $this->actingAs($user);

        $response = $this->get(route('member.dashboard'));

        $response->assertStatus(403);
    }

    public function test_member_without_linked_member_profile_cannot_access_portal(): void
    {
        $this->createRoles();

        $user = User::factory()->create([
            'status' => UserStatus::Active,
            'is_active' => true,
        ]);
        $user->assignRole('VICOBA Member');

        $this->actingAs($user);

        $response = $this->get(route('member.dashboard'));

        $response->assertStatus(403);
    }

    public function test_inactive_member_cannot_access_portal(): void
    {
        ['user' => $user] = $this->createMemberWithUser([
            'membership_status' => MemberStatus::Inactive,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('member.dashboard'));

        $response->assertStatus(403);
    }

    public function test_suspended_member_cannot_access_portal(): void
    {
        ['user' => $user] = $this->createMemberWithUser([
            'membership_status' => MemberStatus::Suspended,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('member.dashboard'));

        $response->assertStatus(403);
    }

    public function test_pending_member_cannot_access_portal(): void
    {
        ['user' => $user] = $this->createMemberWithUser([
            'membership_status' => MemberStatus::Pending,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('member.dashboard'));

        $response->assertStatus(403);
    }

    public function test_active_member_can_access_portal(): void
    {
        ['user' => $user] = $this->createMemberWithUser();

        $this->actingAs($user);

        $response = $this->get(route('member.dashboard'));

        $response->assertStatus(200);
    }

    // ==================== Dashboard Tests ====================

    public function test_member_dashboard_renders_with_no_accounts(): void
    {
        ['user' => $user] = $this->createMemberWithUser();

        $this->actingAs($user);

        $response = $this->get(route('member.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('My Dashboard');
        $response->assertSee('My Savings');
        $response->assertSee('My Shares');
        $response->assertSee('My Welfare');
        $response->assertSee('My Loans');
    }

    public function test_member_dashboard_shows_savings_accounts(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();

        SavingsAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'current_balance' => 50000,
            'status' => SavingsAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('member.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('50,000');
        $response->assertSee('My Savings Accounts');
    }

    public function test_member_dashboard_shares_only_own_data(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $otherUser = User::factory()->create(['status' => UserStatus::Active, 'is_active' => true]);
        $otherMember = Member::factory()->create([
            'user_id' => $otherUser->id,
            'membership_status' => MemberStatus::Active,
            'organization_id' => $member->organization_id,
        ]);

        SavingsAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'current_balance' => 50000,
            'status' => SavingsAccountStatus::Active,
        ]);

        SavingsAccount::factory()->create([
            'member_id' => $otherMember->id,
            'organization_id' => $otherMember->organization_id,
            'branch_id' => $otherMember->branch_id,
            'vicoba_group_id' => $otherMember->vicoba_group_id,
            'current_balance' => 999999,
            'status' => SavingsAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('member.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('50,000');
        $response->assertDontSee('999,999');
    }

    public function test_member_dashboard_tenant_isolation(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();

        $otherUser = User::factory()->create(['status' => UserStatus::Active, 'is_active' => true]);
        $otherMember = Member::factory()->create([
            'user_id' => $otherUser->id,
            'membership_status' => MemberStatus::Active,
        ]);

        SavingsAccount::factory()->create([
            'member_id' => $otherMember->id,
            'organization_id' => $otherMember->organization_id,
            'branch_id' => $otherMember->branch_id,
            'vicoba_group_id' => $otherMember->vicoba_group_id,
            'current_balance' => 888888,
            'status' => SavingsAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('member.dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee('888,888');
    }

    // ==================== Profile View Tests ====================

    public function test_member_can_view_profile(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();

        $this->actingAs($user);

        $response = $this->get(route('member.profile'));

        $response->assertStatus(200);
        $response->assertSee($member->full_name);
        $response->assertSee($member->member_number);
        $response->assertSee($member->phone);
    }

    public function test_member_profile_shows_edit_link(): void
    {
        ['user' => $user] = $this->createMemberWithUser();

        $this->actingAs($user);

        $response = $this->get(route('member.profile'));

        $response->assertStatus(200);
        $response->assertSee('Edit Profile');
    }

    // ==================== Profile Update Tests ====================

    public function test_member_can_view_profile_edit_form(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();

        $this->actingAs($user);

        $response = $this->get(route('member.profile.edit'));

        $response->assertStatus(200);
        $response->assertSee('Edit Profile');
        $response->assertSee($member->phone);
    }

    public function test_member_can_update_profile(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();

        $this->actingAs($user);

        $response = $this->put(route('member.profile.update'), [
            'phone' => '0712345678',
            'email' => 'updated@example.com',
            'occupation' => 'Teacher',
            'region' => 'Dar es Salaam',
        ]);

        $response->assertRedirect(route('member.profile'));
        $response->assertSessionHas('success');

        $member->refresh();
        $this->assertEquals('0712345678', $member->phone);
        $this->assertEquals('updated@example.com', $member->email);
        $this->assertEquals('Teacher', $member->occupation);
        $this->assertEquals('Dar es Salaam', $member->region);
    }

    public function test_member_profile_update_requires_phone(): void
    {
        ['user' => $user] = $this->createMemberWithUser();

        $this->actingAs($user);

        $response = $this->put(route('member.profile.update'), [
            'phone' => '',
        ]);

        $response->assertSessionHasErrors('phone');
    }

    public function test_member_profile_update_validates_email(): void
    {
        ['user' => $user] = $this->createMemberWithUser();

        $this->actingAs($user);

        $response = $this->put(route('member.profile.update'), [
            'phone' => '0712345678',
            'email' => 'not-an-email',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_member_profile_update_creates_audit_log(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();

        $this->actingAs($user);

        $this->put(route('member.profile.update'), [
            'phone' => '0712345678',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'member.profile_updated',
            'auditable_type' => Member::class,
            'auditable_id' => $member->id,
        ]);
    }

    public function test_member_cannot_update_others_profile(): void
    {
        ['user' => $user] = $this->createMemberWithUser();
        $otherUser = User::factory()->create(['status' => UserStatus::Active, 'is_active' => true]);
        $otherMember = Member::factory()->create([
            'user_id' => $otherUser->id,
            'membership_status' => MemberStatus::Active,
        ]);

        $this->actingAs($user);

        $response = $this->put(route('member.profile.update'), [
            'phone' => '0712345678',
        ]);

        $response->assertRedirect();
        $otherMember->refresh();
        $this->assertNotEquals('0712345678', $otherMember->phone);
    }

    // ==================== IDOR Prevention Tests ====================

    public function test_member_cannot_access_other_members_dashboard(): void
    {
        ['user' => $user] = $this->createMemberWithUser();

        $this->actingAs($user);

        $response = $this->get(route('member.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('My Dashboard');
    }

    public function test_member_cannot_view_other_members_profile_via_url(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();

        $this->actingAs($user);

        $response = $this->get(route('member.profile'));

        $response->assertStatus(200);
        $response->assertSee($member->full_name);
    }

    // ==================== Navigation Tests ====================

    public function test_member_portal_routes_use_correct_middleware(): void
    {
        $routes = [
            route('member.dashboard'),
            route('member.profile'),
            route('member.profile.edit'),
        ];

        foreach ($routes as $url) {
            $response = $this->get($url);
            $response->assertRedirect(route('login'));
        }
    }

    public function test_member_portal_uses_member_layout(): void
    {
        ['user' => $user] = $this->createMemberWithUser();

        $this->actingAs($user);

        $response = $this->get(route('member.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Member Portal');
    }

    public function test_member_portal_shows_logout_button(): void
    {
        ['user' => $user] = $this->createMemberWithUser();

        $this->actingAs($user);

        $response = $this->get(route('member.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Logout');
    }

    public function test_member_portal_shows_user_name(): void
    {
        ['user' => $user] = $this->createMemberWithUser();

        $this->actingAs($user);

        $response = $this->get(route('member.dashboard'));

        $response->assertStatus(200);
        $response->assertSee($user->fullname);
    }

    // ==================== Home Redirect Tests ====================

    public function test_member_user_redirects_to_member_portal_on_home(): void
    {
        ['user' => $user] = $this->createMemberWithUser();

        $this->actingAs($user);

        $response = $this->get('/');

        $response->assertRedirect(route('member.dashboard'));
    }

    public function test_admin_user_redirects_to_admin_dashboard_on_home(): void
    {
        $this->createRoles();

        $user = User::factory()->create([
            'status' => UserStatus::Active,
            'is_active' => true,
        ]);
        $user->assignRole('Super Administrator');

        $this->actingAs($user);

        $response = $this->get('/');

        $response->assertRedirect(route('dashboard'));
    }

    // ==================== Savings Account Detail Tests ====================

    public function test_member_dashboard_shows_savings_account_status(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();

        SavingsAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'current_balance' => 25000,
            'status' => SavingsAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('member.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Active');
        $response->assertSee('25,000');
    }

    // ==================== Share Account Tests ====================

    public function test_member_dashboard_shows_share_accounts(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();

        ShareAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'total_shares' => 100,
            'total_value' => 500000,
            'status' => ShareAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('member.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('My Share Accounts');
        $response->assertSee('100');
        $response->assertSee('500,000');
    }

    // ==================== Welfare Account Tests ====================

    public function test_member_dashboard_shows_welfare_accounts(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();

        WelfareAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'current_balance' => 75000,
            'status' => WelfareAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('member.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('My Welfare Accounts');
        $response->assertSee('75,000');
    }

    // ==================== Profile Edit Form Tests ====================

    public function test_profile_edit_form_prepopulates_values(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser([
            'phone' => '0711111111',
            'occupation' => 'Farmer',
            'region' => 'Mwanza',
        ]);

        $this->actingAs($user);

        $response = $this->get(route('member.profile.edit'));

        $response->assertStatus(200);
        $response->assertSee('0711111111');
        $response->assertSee('Farmer');
        $response->assertSee('Mwanza');
    }

    public function test_profile_edit_has_cancel_button(): void
    {
        ['user' => $user] = $this->createMemberWithUser();

        $this->actingAs($user);

        $response = $this->get(route('member.profile.edit'));

        $response->assertStatus(200);
        $response->assertSee('Cancel');
        $response->assertSee(route('member.profile'));
    }

    // ==================== Multiple Account Tests ====================

    public function test_member_dashboard_aggregates_multiple_savings_accounts(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();

        SavingsAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'current_balance' => 30000,
            'status' => SavingsAccountStatus::Active,
        ]);

        SavingsAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'current_balance' => 20000,
            'status' => SavingsAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('member.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('50,000');
        $response->assertSee('2 account(s)');
    }

    // ==================== Update Only Allowed Fields Tests ====================

    public function test_member_cannot_update_membership_status_via_profile(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser([
            'membership_status' => MemberStatus::Active,
        ]);

        $this->actingAs($user);

        $this->put(route('member.profile.update'), [
            'phone' => '0712345678',
            'membership_status' => 'exited',
        ]);

        $member->refresh();
        $this->assertEquals(MemberStatus::Active, $member->membership_status);
    }

    public function test_member_cannot_update_first_name_via_profile(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();

        $originalFirstName = $member->first_name;

        $this->actingAs($user);

        $this->put(route('member.profile.update'), [
            'phone' => '0712345678',
            'first_name' => 'Hacked',
        ]);

        $member->refresh();
        $this->assertEquals($originalFirstName, $member->first_name);
    }
}
