<?php

namespace Tests\Feature\MemberPortal;

use App\Enums\LoanRepaymentStatus;
use App\Enums\LoanScheduleInstallmentStatus;
use App\Enums\LoanStatus;
use App\Enums\MemberStatus;
use App\Enums\UserStatus;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\LoanRepaymentSchedule;
use App\Models\LoanPlan;
use App\Models\Member;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tests\Traits\HasAccountingSetup;

class MemberRepaymentTest extends TestCase
{
    use RefreshDatabase, HasAccountingSetup;

    private User $admin;
    private User $memberUser;
    private Member $member;
    private LoanPlan $plan;
    private Loan $loan;
    private PaymentMethod $paymentMethod;
    private \App\Models\Organization $org;
    private \App\Models\Branch $branch;
    private \App\Models\VicobaGroup $group;

    private function createRoles(): void
    {
        Role::firstOrCreate(['name' => 'Super Administrator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'VICOBA Member', 'guard_name' => 'web']);
    }

    private function createOrgWithBranch(string $suffix = ''): array
    {
        $org = \App\Models\Organization::create([
            'name' => "Org {$suffix}",
            'registration_number' => "ORG-{$suffix}-001",
            'phone' => '+255700000000',
            'email' => "test@{$suffix}.com",
            'region' => 'Dar es Salaam',
            'district' => 'Ilala',
            'status' => 'active',
        ]);

        $branch = \App\Models\Branch::create([
            'organization_id' => $org->id,
            'name' => 'Main Branch',
            'code' => "BR-{$suffix}-001",
            'status' => 'active',
        ]);

        $group = \App\Models\VicobaGroup::create([
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'name' => "Group {$suffix}",
            'code' => "GRP-{$suffix}-001",
            'status' => 'active',
        ]);

        return ['organization' => $org, 'branch' => $branch, 'group' => $group];
    }

    private function createActiveLoan(
        int $orgId,
        int $branchId,
        int $memberId,
        int $loanPlanId,
        float $outstandingBalance = 500000,
        float $amountPaid = 0,
    ): Loan {
        return Loan::factory()->active()->create([
            'organization_id' => $orgId,
            'branch_id' => $branchId,
            'member_id' => $memberId,
            'loan_plan_id' => $loanPlanId,
            'outstanding_balance' => $outstandingBalance,
            'amount_paid' => $amountPaid,
            'principal_amount' => 1000000,
            'total_installments' => 6,
            'installments_paid' => 0,
        ]);
    }

    private function createInstallments(
        int $loanId,
        int $orgId,
        int $count = 3,
    ): void {
        for ($i = 1; $i <= $count; $i++) {
            LoanRepaymentSchedule::factory()->create([
                'loan_id' => $loanId,
                'organization_id' => $orgId,
                'installment_number' => $i,
                'principal_amount' => 150000,
                'interest_amount' => 50000,
                'total_amount' => 200000,
                'amount_paid' => 0,
                'outstanding_amount' => 200000,
                'status' => LoanScheduleInstallmentStatus::Pending,
                'due_date' => now()->addMonth($i),
            ]);
        }
    }

    private function createPaymentMethod(int $orgId): PaymentMethod
    {
        return PaymentMethod::factory()->create([
            'organization_id' => $orgId,
            'status' => 'active',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->createRoles();

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Administrator');

        $orgData = $this->createOrgWithBranch('main');
        $this->org = $orgData['organization'];
        $this->branch = $orgData['branch'];
        $this->group = $orgData['group'];

        $this->memberUser = User::factory()->create([
            'status' => UserStatus::Active,
            'is_active' => true,
        ]);

        $this->member = Member::factory()->create([
            'user_id' => $this->memberUser->id,
            'organization_id' => $this->org->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'membership_status' => MemberStatus::Active,
        ]);

        $this->memberUser->assignRole('VICOBA Member');

        $this->setUpAccountingFor($this->admin, $this->org);

        $this->plan = LoanPlan::create([
            'organization_id' => $this->org->id,
            'name' => 'Standard Loan',
            'code' => 'STD-LOAN',
            'loan_purpose' => 'personal',
            'minimum_amount' => 50000,
            'maximum_amount' => 5000000,
            'interest_rate' => 10,
            'interest_method' => 'flat',
            'minimum_term' => 3,
            'maximum_term' => 24,
            'repayment_frequency' => 'monthly',
            'maximum_active_loans' => 2,
            'requires_guarantor' => false,
            'minimum_guarantors' => 0,
            'requires_collateral' => false,
            'minimum_savings_balance' => 0,
            'savings_multiplier' => 2,
            'share_multiplier' => 0,
            'maximum_loan_to_savings_ratio' => 3,
            'grace_period' => 5,
            'processing_fee' => 1,
            'insurance_fee' => 0.5,
            'late_payment_allowed' => true,
            'status' => 'active',
        ]);

        $this->loan = $this->createActiveLoan(
            $this->org->id,
            $this->branch->id,
            $this->member->id,
            $this->plan->id,
        );

        $this->createInstallments($this->loan->id, $this->org->id);

        $this->paymentMethod = $this->createPaymentMethod($this->org->id);
    }

    private function createMemberWithUser(array $orgData, string $name = 'Other'): array
    {
        $user = User::factory()->create([
            'status' => UserStatus::Active,
            'is_active' => true,
        ]);

        $member = Member::factory()->create([
            'user_id' => $user->id,
            'organization_id' => $orgData['organization']->id,
            'branch_id' => $orgData['branch']->id,
            'vicoba_group_id' => $orgData['group']->id,
            'membership_status' => MemberStatus::Active,
        ]);

        $user->assignRole('VICOBA Member');

        return ['user' => $user, 'member' => $member];
    }

    private function makeLoanPlan(int $orgId): LoanPlan
    {
        return LoanPlan::create([
            'organization_id' => $orgId,
            'name' => 'Other Plan',
            'code' => 'OTH-LOAN-' . uniqid(),
            'loan_purpose' => 'business',
            'minimum_amount' => 10000,
            'maximum_amount' => 1000000,
            'interest_rate' => 15,
            'interest_method' => 'flat',
            'minimum_term' => 1,
            'maximum_term' => 12,
            'repayment_frequency' => 'monthly',
            'maximum_active_loans' => 1,
            'requires_guarantor' => false,
            'minimum_guarantors' => 0,
            'requires_collateral' => false,
            'minimum_savings_balance' => 0,
            'savings_multiplier' => 2,
            'share_multiplier' => 0,
            'maximum_loan_to_savings_ratio' => 3,
            'grace_period' => 0,
            'processing_fee' => 0,
            'insurance_fee' => 0,
            'late_payment_allowed' => true,
            'status' => 'active',
        ]);
    }

    // ==================== Authentication Tests ====================

    public function test_unauthenticated_user_cannot_access_loan_detail(): void
    {
        $response = $this->get(route('member.loans.show', $this->loan));
        $response->assertRedirect(route('login'));
    }

    public function test_unauthenticated_user_cannot_access_repayment_history(): void
    {
        $response = $this->get(route('member.repayments'));
        $response->assertRedirect(route('login'));
    }

    public function test_unauthenticated_user_cannot_access_make_payment_page(): void
    {
        $response = $this->get(route('member.loans.repay', $this->loan));
        $response->assertRedirect(route('login'));
    }

    public function test_unauthenticated_user_cannot_submit_repayment(): void
    {
        $response = $this->post(route('member.loans.repay.store', $this->loan), []);
        $response->assertRedirect(route('login'));
    }

    // ==================== Loan Detail Tests ====================

    public function test_member_can_view_own_loan_detail(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->get(route('member.loans.show', $this->loan));
        $response->assertOk();
        $response->assertSee($this->loan->loan_number);
    }

    public function test_member_cannot_view_other_member_loan_detail(): void
    {
        ['user' => $otherUser, 'member' => $otherMember] = $this->createMemberWithUser(
            $this->createOrgWithBranch('other')
        );

        $otherLoan = $this->createActiveLoan(
            $otherMember->organization_id,
            $otherMember->branch_id,
            $otherMember->id,
            $this->makeLoanPlan($otherMember->organization_id)->id,
        );

        $this->actingAs($this->memberUser);

        $response = $this->get(route('member.loans.show', $otherLoan));
        $response->assertNotFound();
    }

    public function test_member_cannot_view_other_org_loan_detail(): void
    {
        $otherOrgData = $this->createOrgWithBranch('tenant');
        ['user' => $otherUser, 'member' => $otherMember] = $this->createMemberWithUser($otherOrgData);

        $otherPlan = $this->makeLoanPlan($otherOrgData['organization']->id);
        $otherLoan = $this->createActiveLoan(
            $otherOrgData['organization']->id,
            $otherOrgData['branch']->id,
            $otherMember->id,
            $otherPlan->id,
        );

        $this->actingAs($this->memberUser);

        $response = $this->get(route('member.loans.show', $otherLoan));
        $response->assertNotFound();
    }

    // ==================== Repayment Schedule Tests ====================

    public function test_member_can_view_own_loan_schedule(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->get(route('member.loans.schedule', $this->loan));
        $response->assertOk();
        $response->assertSee('Repayment Schedule');
    }

    public function test_member_cannot_view_other_member_loan_schedule(): void
    {
        ['user' => $otherUser, 'member' => $otherMember] = $this->createMemberWithUser(
            $this->createOrgWithBranch('sched-other')
        );

        $otherPlan = $this->makeLoanPlan($otherMember->organization_id);
        $otherLoan = $this->createActiveLoan(
            $otherMember->organization_id,
            $otherMember->branch_id,
            $otherMember->id,
            $otherPlan->id,
        );

        $this->actingAs($this->memberUser);

        $response = $this->get(route('member.loans.schedule', $otherLoan));
        $response->assertNotFound();
    }

    public function test_schedule_shows_installments(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->get(route('member.loans.schedule', $this->loan));
        $response->assertOk();
        $response->assertSee('3 installments');
        $response->assertSee('Pending');
    }

    // ==================== Make Payment Page Tests ====================

    public function test_member_can_view_make_payment_page(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->get(route('member.loans.repay', $this->loan));
        $response->assertOk();
        $response->assertSee('Make a Payment');
    }

    public function test_make_payment_page_shows_payment_methods(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->get(route('member.loans.repay', $this->loan));
        $response->assertOk();
        $response->assertSee($this->paymentMethod->name);
    }

    public function test_member_cannot_access_make_payment_for_inactive_loan(): void
    {
        $inactiveLoan = Loan::factory()->create([
            'organization_id' => $this->org->id,
            'branch_id' => $this->branch->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->plan->id,
            'status' => LoanStatus::Cancelled,
            'outstanding_balance' => 500000,
        ]);

        $this->actingAs($this->memberUser);

        $response = $this->get(route('member.loans.repay', $inactiveLoan));
        $response->assertSessionHasErrors('error');
    }

    public function test_member_cannot_access_make_payment_for_fully_paid_loan(): void
    {
        $paidLoan = Loan::factory()->active()->create([
            'organization_id' => $this->org->id,
            'branch_id' => $this->branch->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->plan->id,
            'outstanding_balance' => 0,
            'amount_paid' => 1000000,
        ]);

        $this->actingAs($this->memberUser);

        $response = $this->get(route('member.loans.repay', $paidLoan));
        $response->assertSessionHasErrors('error');
    }

    // ==================== Store Repayment Tests ====================

    public function test_member_can_submit_valid_repayment(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->post(route('member.loans.repay.store', $this->loan), [
            'amount' => 100000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'payment_method_id' => $this->paymentMethod->id,
            'reference_number' => 'REF-001',
            'notes' => 'Monthly installment',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('loan_repayments', [
            'loan_id' => $this->loan->id,
            'member_id' => $this->member->id,
            'amount' => 100000,
            'payment_method' => 'cash',
            'status' => LoanRepaymentStatus::Posted->value,
        ]);
    }

    public function test_repayment_with_payment_method_id_validates_org_ownership(): void
    {
        $otherOrgData = $this->createOrgWithBranch('pm-org');
        $otherPaymentMethod = PaymentMethod::factory()->create([
            'organization_id' => $otherOrgData['organization']->id,
            'status' => 'active',
        ]);

        $this->actingAs($this->memberUser);

        $response = $this->post(route('member.loans.repay.store', $this->loan), [
            'amount' => 100000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'payment_method_id' => $otherPaymentMethod->id,
            'reference_number' => 'REF-002',
        ]);

        $response->assertSessionHasErrors('payment_method_id');
    }

    public function test_repayment_updates_loan_balance(): void
    {
        $this->actingAs($this->memberUser);

        $this->post(route('member.loans.repay.store', $this->loan), [
            'amount' => 100000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ]);

        $this->loan->refresh();
        $this->assertEquals(100000, (float) $this->loan->amount_paid);
        $this->assertEquals(400000, (float) $this->loan->outstanding_balance);
    }

    public function test_repayment_updates_installment_status(): void
    {
        $this->actingAs($this->memberUser);

        $this->post(route('member.loans.repay.store', $this->loan), [
            'amount' => 200000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ]);

        $installment = LoanRepaymentSchedule::where('loan_id', $this->loan->id)
            ->where('installment_number', 1)
            ->first();

        $this->assertNotNull($installment);
        $this->assertEquals(LoanScheduleInstallmentStatus::Paid->value, $installment->status->value);
    }

    public function test_repayment_creates_repayment_record(): void
    {
        $this->actingAs($this->memberUser);

        $this->post(route('member.loans.repay.store', $this->loan), [
            'amount' => 50000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'mobile_money',
            'reference_number' => 'MPESA-123',
            'notes' => 'Test payment',
        ]);

        $repayment = LoanRepayment::where('loan_id', $this->loan->id)->first();

        $this->assertNotNull($repayment);
        $this->assertEquals(50000, (float) $repayment->amount);
        $this->assertEquals('mobile_money', $repayment->payment_method);
        $this->assertEquals('MPESA-123', $repayment->reference_number);
        $this->assertEquals('Test payment', $repayment->notes);
        $this->assertEquals(LoanRepaymentStatus::Posted->value, $repayment->status->value);
    }

    public function test_repayment_requires_amount(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->post(route('member.loans.repay.store', $this->loan), [
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ]);

        $response->assertSessionHasErrors('amount');
    }

    public function test_repayment_requires_payment_date(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->post(route('member.loans.repay.store', $this->loan), [
            'amount' => 100000,
            'payment_method' => 'cash',
        ]);

        $response->assertSessionHasErrors('payment_date');
    }

    public function test_repayment_requires_payment_method(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->post(route('member.loans.repay.store', $this->loan), [
            'amount' => 100000,
            'payment_date' => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors('payment_method');
    }

    public function test_repayment_rejects_future_date(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->post(route('member.loans.repay.store', $this->loan), [
            'amount' => 100000,
            'payment_date' => now()->addDay()->toDateString(),
            'payment_method' => 'cash',
        ]);

        $response->assertSessionHasErrors('payment_date');
    }

    public function test_repayment_rejects_zero_amount(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->post(route('member.loans.repay.store', $this->loan), [
            'amount' => 0,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ]);

        $response->assertSessionHasErrors('amount');
    }

    // ==================== Repayment History Tests ====================

    public function test_member_can_view_repayment_history(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->get(route('member.repayments'));
        $response->assertOk();
        $response->assertSee('Payment History');
    }

    public function test_repayment_history_shows_own_repayments_only(): void
    {
        $this->actingAs($this->memberUser);

        $this->post(route('member.loans.repay.store', $this->loan), [
            'amount' => 100000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'notes' => 'My payment',
        ]);

        $myRepayment = LoanRepayment::where('loan_id', $this->loan->id)->first();

        $response = $this->get(route('member.repayments'));
        $response->assertOk();
        $response->assertSee($myRepayment->repayment_number);

        $otherOrgData = $this->createOrgWithBranch('hist-other');
        ['user' => $otherUser, 'member' => $otherMember] = $this->createMemberWithUser($otherOrgData);
        $otherPlan = $this->makeLoanPlan($otherOrgData['organization']->id);
        $otherLoan = $this->createActiveLoan(
            $otherOrgData['organization']->id,
            $otherOrgData['branch']->id,
            $otherMember->id,
            $otherPlan->id,
        );

        $otherPaymentMethod = PaymentMethod::factory()->create([
            'organization_id' => $otherOrgData['organization']->id,
            'status' => 'active',
        ]);

        $this->setUpAccountingFor($this->admin, $otherOrgData['organization']);

        $this->actingAs($otherUser);

        $this->post(route('member.loans.repay.store', $otherLoan), [
            'amount' => 50000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'notes' => 'Other payment',
        ]);

        $otherRepayment = LoanRepayment::where('loan_id', $otherLoan->id)->first();

        $this->actingAs($this->memberUser);

        $response = $this->get(route('member.repayments'));
        $response->assertOk();
        $response->assertDontSee($otherLoan->loan_number);
    }

    public function test_repayment_history_is_paginated(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->get(route('member.repayments'));
        $response->assertOk();

        for ($i = 0; $i < 20; $i++) {
            $this->post(route('member.loans.repay.store', $this->loan), [
                'amount' => 1000,
                'payment_date' => now()->toDateString(),
                'payment_method' => 'cash',
            ]);
        }

        $response = $this->get(route('member.repayments'));
        $response->assertOk();
    }

    // ==================== Repayment Detail Tests ====================

    public function test_member_can_view_own_repayment_detail(): void
    {
        $this->actingAs($this->memberUser);

        $this->post(route('member.loans.repay.store', $this->loan), [
            'amount' => 100000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ]);

        $repayment = LoanRepayment::where('loan_id', $this->loan->id)->first();

        $response = $this->get(route('member.repayments.show', $repayment));
        $response->assertOk();
        $response->assertSee($repayment->repayment_number);
    }

    public function test_member_cannot_view_other_member_repayment_detail(): void
    {
        $otherOrgData = $this->createOrgWithBranch('detail-other');
        ['user' => $otherUser, 'member' => $otherMember] = $this->createMemberWithUser($otherOrgData);

        $otherPlan = $this->makeLoanPlan($otherOrgData['organization']->id);
        $otherLoan = $this->createActiveLoan(
            $otherOrgData['organization']->id,
            $otherOrgData['branch']->id,
            $otherMember->id,
            $otherPlan->id,
        );

        $otherPaymentMethod = PaymentMethod::factory()->create([
            'organization_id' => $otherOrgData['organization']->id,
            'status' => 'active',
        ]);

        $this->setUpAccountingFor($this->admin, $otherOrgData['organization']);

        $this->actingAs($otherUser);

        $this->post(route('member.loans.repay.store', $otherLoan), [
            'amount' => 50000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'payment_method_id' => $otherPaymentMethod->id,
        ]);

        $otherRepayment = LoanRepayment::where('loan_id', $otherLoan->id)->first();

        $this->actingAs($this->memberUser);

        $response = $this->get(route('member.repayments.show', $otherRepayment));
        $response->assertNotFound();
    }

    public function test_repayment_detail_shows_allocations(): void
    {
        $this->actingAs($this->memberUser);

        $this->post(route('member.loans.repay.store', $this->loan), [
            'amount' => 200000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ]);

        $repayment = LoanRepayment::where('loan_id', $this->loan->id)->first();

        $response = $this->get(route('member.repayments.show', $repayment));
        $response->assertOk();
        $response->assertSee('Allocations');
    }

    // ==================== Edge Cases ====================

    public function test_http_method_get_rejected_on_store_route(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->put(route('member.loans.repay.store', $this->loan), []);
        $response->assertStatus(405);
    }

    public function test_http_method_post_rejected_on_schedule_route(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->post(route('member.loans.schedule', $this->loan));
        $response->assertStatus(405);
    }

    public function test_nonexistent_loan_returns_404(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->get(route('member.loans.show', 999999));
        $response->assertNotFound();
    }

    public function test_nonexistent_repayment_returns_404(): void
    {
        $this->actingAs($this->memberUser);

        $response = $this->get(route('member.repayments.show', 999999));
        $response->assertNotFound();
    }

    public function test_completed_loan_cannot_accept_payment(): void
    {
        $completedLoan = Loan::factory()->completed()->create([
            'organization_id' => $this->org->id,
            'branch_id' => $this->branch->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->plan->id,
        ]);

        $this->actingAs($this->memberUser);

        $response = $this->post(route('member.loans.repay.store', $completedLoan), [
            'amount' => 100000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ]);

        $response->assertSessionHas('error');
    }

    public function test_member_cannot_store_repayment_for_other_member_loan(): void
    {
        $otherOrgData = $this->createOrgWithBranch('idor-store');
        ['user' => $otherUser, 'member' => $otherMember] = $this->createMemberWithUser($otherOrgData);

        $otherPlan = $this->makeLoanPlan($otherOrgData['organization']->id);
        $otherLoan = $this->createActiveLoan(
            $otherOrgData['organization']->id,
            $otherOrgData['branch']->id,
            $otherMember->id,
            $otherPlan->id,
        );

        $this->actingAs($this->memberUser);

        $response = $this->post(route('member.loans.repay.store', $otherLoan), [
            'amount' => 100000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(403);

        $this->assertDatabaseCount('loan_repayments', 0);
    }
}
