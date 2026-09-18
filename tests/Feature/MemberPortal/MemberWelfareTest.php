<?php

namespace Tests\Feature\MemberPortal;

use App\Enums\MemberStatus;
use App\Enums\SavingsAccountStatus;
use App\Enums\UserStatus;
use App\Enums\WelfareAccountStatus;
use App\Enums\WelfareBenefitRequestStatus;
use App\Models\Branch;
use App\Models\Member;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Models\VicobaGroup;
use App\Models\WelfareAccount;
use App\Models\WelfareBenefitRequest;
use App\Models\WelfareFund;
use App\Models\WelfareTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Traits\HasAccountingSetup;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MemberWelfareTest extends TestCase
{
    use RefreshDatabase, HasAccountingSetup;

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

    private function setupFinancialInfrastructure(Member $member): array
    {
        $organization = Organization::find($member->organization_id);
        $branch = Branch::find($member->branch_id);
        $group = VicobaGroup::find($member->vicoba_group_id);

        if (! $organization) {
            $organization = Organization::factory()->create();
        }
        if (! $branch) {
            $branch = Branch::factory()->create(['organization_id' => $organization->id]);
        }
        if (! $group) {
            $group = VicobaGroup::factory()->create(['branch_id' => $branch->id]);
        }

        $admin = User::where('email', 'admin@financepro.co.tz')->first();
        if (! $admin) {
            $admin = User::factory()->create([
                'email' => 'admin@financepro.co.tz',
                'status' => UserStatus::Active,
                'is_active' => true,
            ]);
            $admin->assignRole('Super Administrator');
        }

        $admin->organizations()->syncWithoutDetaching([$organization->id]);
        $this->setUpAccountingFor($admin, $organization);

        $paymentMethod = PaymentMethod::factory()->create([
            'organization_id' => $organization->id,
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        $welfareFund = WelfareFund::factory()->create([
            'organization_id' => $organization->id,
            'status' => SavingsAccountStatus::Active,
        ]);

        return compact('organization', 'branch', 'group', 'admin', 'paymentMethod', 'welfareFund');
    }

    // ==================== Welfare Contribution Tests ====================

    public function test_member_can_contribute_to_own_welfare_account(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        $welfareAccount = WelfareAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'welfare_fund_id' => $infra['welfareFund']->id,
            'current_balance' => 0,
            'status' => WelfareAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('member.welfare.contribute'), [
            'welfare_account_id' => $welfareAccount->id,
            'amount' => 50000,
            'payment_method_id' => $infra['paymentMethod']->id,
            'transaction_date' => now()->format('Y-m-d'),
            'idempotency_key' => 'test-contribute-' . uniqid(),
        ]);

        $response->assertRedirect(route('member.welfare'));
        $response->assertSessionHas('success');

        $welfareAccount->refresh();
        $this->assertEquals('50000.00', $welfareAccount->current_balance);

        $this->assertDatabaseHas('welfare_transactions', [
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'transaction_type' => 'contribution',
            'amount' => 50000,
            'status' => 'completed',
        ]);
    }

    public function test_welfare_contribution_creates_accounting_journal(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        $welfareAccount = WelfareAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'welfare_fund_id' => $infra['welfareFund']->id,
            'current_balance' => 0,
            'status' => WelfareAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $this->post(route('member.welfare.contribute'), [
            'welfare_account_id' => $welfareAccount->id,
            'amount' => 100000,
            'payment_method_id' => $infra['paymentMethod']->id,
            'transaction_date' => now()->format('Y-m-d'),
            'idempotency_key' => 'test-journal-' . uniqid(),
        ]);

        $transaction = WelfareTransaction::where('member_id', $member->id)
            ->where('transaction_type', 'contribution')
            ->first();

        $this->assertNotNull($transaction);

        $this->assertDatabaseHas('journal_entries', [
            'organization_id' => $member->organization_id,
            'source_type' => WelfareTransaction::class,
            'source_id' => $transaction->id,
            'status' => 'posted',
        ]);
    }

    public function test_member_cannot_contribute_to_another_members_account(): void
    {
        ['user' => $userA, 'member' => $memberA] = $this->createMemberWithUser();
        ['user' => $userB, 'member' => $memberB] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($memberB);

        $welfareAccountB = WelfareAccount::factory()->create([
            'member_id' => $memberB->id,
            'organization_id' => $memberB->organization_id,
            'branch_id' => $memberB->branch_id,
            'vicoba_group_id' => $memberB->vicoba_group_id,
            'welfare_fund_id' => $infra['welfareFund']->id,
            'current_balance' => 0,
            'status' => WelfareAccountStatus::Active,
        ]);

        $this->actingAs($userA);

        $response = $this->post(route('member.welfare.contribute'), [
            'welfare_account_id' => $welfareAccountB->id,
            'amount' => 50000,
            'payment_method_id' => $infra['paymentMethod']->id,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(404);
    }

    public function test_member_cannot_contribute_with_zero_amount(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        $welfareAccount = WelfareAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'welfare_fund_id' => $infra['welfareFund']->id,
            'status' => WelfareAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('member.welfare.contribute'), [
            'welfare_account_id' => $welfareAccount->id,
            'amount' => 0,
            'payment_method_id' => $infra['paymentMethod']->id,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('amount');
    }

    public function test_member_cannot_contribute_with_negative_amount(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        $welfareAccount = WelfareAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'welfare_fund_id' => $infra['welfareFund']->id,
            'status' => WelfareAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('member.welfare.contribute'), [
            'welfare_account_id' => $welfareAccount->id,
            'amount' => -5000,
            'payment_method_id' => $infra['paymentMethod']->id,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('amount');
    }

    public function test_member_cannot_contribute_with_another_orgs_payment_method(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        $otherOrg = Organization::factory()->create();
        $otherPaymentMethod = PaymentMethod::factory()->create([
            'organization_id' => $otherOrg->id,
            'status' => 'active',
        ]);

        $welfareAccount = WelfareAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'welfare_fund_id' => $infra['welfareFund']->id,
            'status' => WelfareAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('member.welfare.contribute'), [
            'welfare_account_id' => $welfareAccount->id,
            'amount' => 50000,
            'payment_method_id' => $otherPaymentMethod->id,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $response->assertRedirect(route('member.welfare'));
        $response->assertSessionHas('error');
    }

    // ==================== Welfare Benefit Request Tests ====================

    public function test_member_can_submit_benefit_request(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        $welfareAccount = WelfareAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'welfare_fund_id' => $infra['welfareFund']->id,
            'current_balance' => 100000,
            'status' => WelfareAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('member.welfare.benefit-request'), [
            'welfare_account_id' => $welfareAccount->id,
            'requested_amount' => 30000,
            'reason' => 'Medical emergency',
            'idempotency_key' => 'test-benefit-' . uniqid(),
        ]);

        $response->assertRedirect(route('member.welfare'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('welfare_benefit_requests', [
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'requested_amount' => 30000,
            'reason' => 'Medical emergency',
            'status' => WelfareBenefitRequestStatus::Pending->value,
        ]);
    }

    public function test_benefit_request_creates_pending_record(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        $welfareAccount = WelfareAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'welfare_fund_id' => $infra['welfareFund']->id,
            'current_balance' => 100000,
            'status' => WelfareAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $this->post(route('member.welfare.benefit-request'), [
            'welfare_account_id' => $welfareAccount->id,
            'requested_amount' => 25000,
            'reason' => 'School fees',
            'idempotency_key' => 'test-pending-' . uniqid(),
        ]);

        $request = WelfareBenefitRequest::where('member_id', $member->id)->first();

        $this->assertNotNull($request);
        $this->assertEquals(WelfareBenefitRequestStatus::Pending, $request->status);
        $this->assertNull($request->approved_by);
        $this->assertNull($request->approved_at);
        $this->assertNull($request->welfare_transaction_id);
    }

    public function test_member_cannot_submit_benefit_request_for_another_member(): void
    {
        ['user' => $userA, 'member' => $memberA] = $this->createMemberWithUser();
        ['user' => $userB, 'member' => $memberB] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($memberB);

        $welfareAccountB = WelfareAccount::factory()->create([
            'member_id' => $memberB->id,
            'organization_id' => $memberB->organization_id,
            'branch_id' => $memberB->branch_id,
            'vicoba_group_id' => $memberB->vicoba_group_id,
            'welfare_fund_id' => $infra['welfareFund']->id,
            'current_balance' => 100000,
            'status' => WelfareAccountStatus::Active,
        ]);

        $this->actingAs($userA);

        $response = $this->post(route('member.welfare.benefit-request'), [
            'welfare_account_id' => $welfareAccountB->id,
            'requested_amount' => 30000,
            'reason' => 'Medical',
            'idempotency_key' => 'test-idor-' . uniqid(),
        ]);

        $response->assertStatus(404);
    }

    public function test_member_cannot_submit_benefit_request_with_zero_amount(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        $welfareAccount = WelfareAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'welfare_fund_id' => $infra['welfareFund']->id,
            'status' => WelfareAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('member.welfare.benefit-request'), [
            'welfare_account_id' => $welfareAccount->id,
            'requested_amount' => 0,
            'reason' => 'Test',
        ]);

        $response->assertSessionHasErrors('requested_amount');
    }

    public function test_benefit_request_idempotency_prevents_duplicate(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        $welfareAccount = WelfareAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'welfare_fund_id' => $infra['welfareFund']->id,
            'current_balance' => 100000,
            'status' => WelfareAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $idempotencyKey = 'test-idempotency-' . uniqid();

        $this->post(route('member.welfare.benefit-request'), [
            'welfare_account_id' => $welfareAccount->id,
            'requested_amount' => 30000,
            'reason' => 'First request',
            'idempotency_key' => $idempotencyKey,
        ]);

        $response = $this->post(route('member.welfare.benefit-request'), [
            'welfare_account_id' => $welfareAccount->id,
            'requested_amount' => 30000,
            'reason' => 'Duplicate request',
            'idempotency_key' => $idempotencyKey,
        ]);

        $response->assertSessionHasErrors('idempotency_key');

        $this->assertDatabaseCount('welfare_benefit_requests', 1);
    }

    public function test_member_cannot_tamper_with_benefit_request_approval_fields(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        $welfareAccount = WelfareAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'welfare_fund_id' => $infra['welfareFund']->id,
            'current_balance' => 100000,
            'status' => WelfareAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('member.welfare.benefit-request'), [
            'welfare_account_id' => $welfareAccount->id,
            'requested_amount' => 30000,
            'reason' => 'Medical',
            'idempotency_key' => 'test-tamper-' . uniqid(),
        ]);

        $request = WelfareBenefitRequest::where('member_id', $member->id)->first();
        $this->assertNotNull($request);
        $this->assertEquals(WelfareBenefitRequestStatus::Pending, $request->status);
        $this->assertNull($request->approved_by);
        $this->assertNull($request->welfare_transaction_id);
    }

    // ==================== Authentication Tests ====================

    public function test_unauthenticated_user_cannot_access_welfare_page(): void
    {
        $response = $this->get(route('member.welfare'));
        $response->assertRedirect(route('login'));
    }

    public function test_unauthenticated_user_cannot_contribute(): void
    {
        $response = $this->post(route('member.welfare.contribute'), [
            'welfare_account_id' => 1,
            'amount' => 50000,
            'payment_method_id' => 1,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_unauthenticated_user_cannot_submit_benefit_request(): void
    {
        $response = $this->post(route('member.welfare.benefit-request'), [
            'welfare_account_id' => 1,
            'requested_amount' => 30000,
            'reason' => 'Test',
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_inactive_member_cannot_contribute(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser([
            'membership_status' => MemberStatus::Inactive,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('member.welfare.contribute'), [
            'welfare_account_id' => 1,
            'amount' => 50000,
            'payment_method_id' => 1,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(403);
    }

    public function test_inactive_member_cannot_submit_benefit_request(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser([
            'membership_status' => MemberStatus::Inactive,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('member.welfare.benefit-request'), [
            'welfare_account_id' => 1,
            'requested_amount' => 30000,
            'reason' => 'Test',
        ]);

        $response->assertStatus(403);
    }

    // ==================== View Tests ====================

    public function test_welfare_page_displays_accounts_and_transactions(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        $welfareAccount = WelfareAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'welfare_fund_id' => $infra['welfareFund']->id,
            'current_balance' => 75000,
            'status' => WelfareAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('member.welfare'));
        $response->assertOk();
        $response->assertSee($welfareAccount->account_number);
        $response->assertSee('75,000.00');
        $response->assertSee('Contribute');
        $response->assertSee('Request Benefit');
    }

    public function test_welfare_page_shows_benefit_requests(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        $welfareAccount = WelfareAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'welfare_fund_id' => $infra['welfareFund']->id,
            'current_balance' => 100000,
            'status' => WelfareAccountStatus::Active,
        ]);

        WelfareBenefitRequest::factory()->create([
            'welfare_account_id' => $welfareAccount->id,
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'requested_amount' => 25000,
            'reason' => 'Medical emergency',
            'status' => WelfareBenefitRequestStatus::Pending,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('member.welfare'));
        $response->assertOk();
        $response->assertSee('Medical emergency');
        $response->assertSee('25,000.00');
    }

    // ==================== Accounting Integration Tests ====================

    public function test_welfare_contribution_accounting_flow(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        $welfareAccount = WelfareAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'welfare_fund_id' => $infra['welfareFund']->id,
            'current_balance' => 0,
            'status' => WelfareAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $this->post(route('member.welfare.contribute'), [
            'welfare_account_id' => $welfareAccount->id,
            'amount' => 200000,
            'payment_method_id' => $infra['paymentMethod']->id,
            'transaction_date' => now()->format('Y-m-d'),
            'idempotency_key' => 'test-full-flow-' . uniqid(),
        ]);

        $welfareAccount->refresh();
        $this->assertEquals('200000.00', $welfareAccount->current_balance);

        $transaction = WelfareTransaction::where('member_id', $member->id)
            ->where('transaction_type', 'contribution')
            ->first();
        $this->assertNotNull($transaction);
        $this->assertEquals('200000.00', $transaction->amount);
        $this->assertEquals('0.00', $transaction->balance_before);
        $this->assertEquals('200000.00', $transaction->balance_after);

        $this->assertDatabaseHas('journal_entries', [
            'organization_id' => $member->organization_id,
            'source_type' => WelfareTransaction::class,
            'source_id' => $transaction->id,
            'status' => 'posted',
        ]);

        $journalEntry = \App\Models\JournalEntry::where('source_type', WelfareTransaction::class)
            ->where('source_id', $transaction->id)
            ->first();
        $this->assertNotNull($journalEntry);

        $lines = $journalEntry->lines;
        $this->assertCount(2, $lines);
        $totalDebit = $lines->sum('debit');
        $totalCredit = $lines->sum('credit');
        $this->assertEquals($totalDebit, $totalCredit);
    }

    // ==================== Route Protection Tests ====================

    public function test_welfare_routes_require_member_middleware(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Active,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('member.welfare'));
        $response->assertStatus(403);
    }

    public function test_welfare_contribute_requires_post(): void
    {
        $user = User::factory()->create([
            'status' => UserStatus::Active,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('member.welfare.contribute'));
        $response->assertStatus(405);
    }

    // ==================== Enum Status Display Regression Tests ====================

    public function test_welfare_page_shows_active_badge_and_action_buttons_for_active_account(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        WelfareAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'welfare_fund_id' => $infra['welfareFund']->id,
            'current_balance' => 100000,
            'status' => WelfareAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('member.welfare'));
        $response->assertOk();
        $response->assertSee('Contribute');
        $response->assertSee('Request Benefit');
        $response->assertSee('100,000.00');
    }

    public function test_welfare_page_hides_action_buttons_when_no_active_accounts(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        WelfareAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'welfare_fund_id' => $infra['welfareFund']->id,
            'current_balance' => 50000,
            'status' => WelfareAccountStatus::Inactive,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('member.welfare'));
        $response->assertOk();
        $response->assertSee('Inactive');
        $response->assertDontSee('data-bs-target="#contributeModal"', false);
        $response->assertDontSee('data-bs-target="#benefitModal"', false);
    }

    public function test_dashboard_shares_active_account_count(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        $savingsProduct = \App\Models\SavingsProduct::factory()->create([
            'organization_id' => $infra['organization']->id,
        ]);

        SavingsAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'savings_product_id' => $savingsProduct->id,
            'current_balance' => 75000,
            'status' => SavingsAccountStatus::Active,
        ]);

        WelfareAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'welfare_fund_id' => $infra['welfareFund']->id,
            'current_balance' => 50000,
            'status' => WelfareAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('member.dashboard'));
        $response->assertOk();
        $response->assertSee('75,000.00');
        $response->assertSee('50,000.00');
    }
}
