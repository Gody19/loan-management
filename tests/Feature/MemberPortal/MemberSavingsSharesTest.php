<?php

namespace Tests\Feature\MemberPortal;

use App\Enums\MemberStatus;
use App\Enums\SavingsAccountStatus;
use App\Enums\ShareAccountStatus;
use App\Enums\UserStatus;
use App\Models\Branch;
use App\Models\Member;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\SavingsTransaction;
use App\Models\ShareAccount;
use App\Models\ShareProduct;
use App\Models\ShareTransaction;
use App\Models\User;
use App\Models\VicobaGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Traits\HasAccountingSetup;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MemberSavingsSharesTest extends TestCase
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

        $savingsProduct = SavingsProduct::factory()->create([
            'organization_id' => $organization->id,
            'allow_withdrawal' => true,
            'minimum_balance' => 0,
        ]);

        $shareProduct = ShareProduct::factory()->create([
            'organization_id' => $organization->id,
            'share_price' => 10000,
            'minimum_shares' => 0,
        ]);

        $paymentMethod = PaymentMethod::factory()->create([
            'organization_id' => $organization->id,
            'status' => 'active',
            'created_by' => $admin->id,
        ]);

        return compact('organization', 'branch', 'group', 'admin', 'savingsProduct', 'shareProduct', 'paymentMethod');
    }

    // ==================== Savings Deposit Tests ====================

    public function test_member_can_deposit_to_own_savings_account(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        $savingsAccount = SavingsAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'savings_product_id' => $infra['savingsProduct']->id,
            'current_balance' => 0,
            'status' => SavingsAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('member.savings.deposit'), [
            'savings_account_id' => $savingsAccount->id,
            'amount' => 50000,
            'payment_method_id' => $infra['paymentMethod']->id,
            'transaction_date' => now()->format('Y-m-d'),
            'idempotency_key' => 'test-deposit-' . uniqid(),
        ]);

        $response->assertRedirect(route('member.savings'));
        $response->assertSessionHas('success');

        $savingsAccount->refresh();
        $this->assertEquals('50000.00', $savingsAccount->current_balance);

        $this->assertDatabaseHas('savings_transactions', [
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'transaction_type' => 'deposit',
            'amount' => 50000,
            'status' => 'completed',
        ]);
    }

    public function test_savings_deposit_creates_accounting_journal(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        $savingsAccount = SavingsAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'savings_product_id' => $infra['savingsProduct']->id,
            'current_balance' => 0,
            'status' => SavingsAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $this->post(route('member.savings.deposit'), [
            'savings_account_id' => $savingsAccount->id,
            'amount' => 100000,
            'payment_method_id' => $infra['paymentMethod']->id,
            'transaction_date' => now()->format('Y-m-d'),
            'idempotency_key' => 'test-journal-' . uniqid(),
        ]);

        $transaction = SavingsTransaction::where('member_id', $member->id)
            ->where('transaction_type', 'deposit')
            ->first();

        $this->assertNotNull($transaction);

        $this->assertDatabaseHas('journal_entries', [
            'organization_id' => $member->organization_id,
            'source_type' => SavingsTransaction::class,
            'source_id' => $transaction->id,
            'status' => 'posted',
        ]);
    }

    public function test_member_cannot_deposit_to_another_members_account(): void
    {
        ['user' => $userA, 'member' => $memberA] = $this->createMemberWithUser();
        ['user' => $userB, 'member' => $memberB] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($memberB);

        $savingsAccountB = SavingsAccount::factory()->create([
            'member_id' => $memberB->id,
            'organization_id' => $memberB->organization_id,
            'branch_id' => $memberB->branch_id,
            'vicoba_group_id' => $memberB->vicoba_group_id,
            'savings_product_id' => $infra['savingsProduct']->id,
            'current_balance' => 0,
            'status' => SavingsAccountStatus::Active,
        ]);

        $this->actingAs($userA);

        $response = $this->post(route('member.savings.deposit'), [
            'savings_account_id' => $savingsAccountB->id,
            'amount' => 50000,
            'payment_method_id' => $infra['paymentMethod']->id,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(404);

        $savingsAccountB->refresh();
        $this->assertEquals('0.00', $savingsAccountB->current_balance);
    }

    public function test_member_cannot_deposit_with_zero_amount(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        $savingsAccount = SavingsAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'savings_product_id' => $infra['savingsProduct']->id,
            'current_balance' => 0,
            'status' => SavingsAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('member.savings.deposit'), [
            'savings_account_id' => $savingsAccount->id,
            'amount' => 0,
            'payment_method_id' => $infra['paymentMethod']->id,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('amount');
    }

    public function test_member_cannot_deposit_with_negative_amount(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        $savingsAccount = SavingsAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'savings_product_id' => $infra['savingsProduct']->id,
            'current_balance' => 0,
            'status' => SavingsAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('member.savings.deposit'), [
            'savings_account_id' => $savingsAccount->id,
            'amount' => -5000,
            'payment_method_id' => $infra['paymentMethod']->id,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('amount');
    }

    public function test_member_cannot_deposit_with_another_orgs_payment_method(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        $otherOrg = Organization::factory()->create();
        $otherPaymentMethod = PaymentMethod::factory()->create([
            'organization_id' => $otherOrg->id,
            'status' => 'active',
        ]);

        $savingsAccount = SavingsAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'savings_product_id' => $infra['savingsProduct']->id,
            'current_balance' => 0,
            'status' => SavingsAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('member.savings.deposit'), [
            'savings_account_id' => $savingsAccount->id,
            'amount' => 50000,
            'payment_method_id' => $otherPaymentMethod->id,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_duplicate_idempotency_key_does_not_duplicate_savings_deposit(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        $savingsAccount = SavingsAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'savings_product_id' => $infra['savingsProduct']->id,
            'current_balance' => 0,
            'status' => SavingsAccountStatus::Active,
        ]);

        $idempotencyKey = 'idempotency-test-' . uniqid();

        $this->actingAs($user);

        $this->post(route('member.savings.deposit'), [
            'savings_account_id' => $savingsAccount->id,
            'amount' => 50000,
            'payment_method_id' => $infra['paymentMethod']->id,
            'transaction_date' => now()->format('Y-m-d'),
            'idempotency_key' => $idempotencyKey,
        ]);

        $response = $this->post(route('member.savings.deposit'), [
            'savings_account_id' => $savingsAccount->id,
            'amount' => 50000,
            'payment_method_id' => $infra['paymentMethod']->id,
            'transaction_date' => now()->format('Y-m-d'),
            'idempotency_key' => $idempotencyKey,
        ]);

        $response->assertSessionHasErrors('idempotency_key');

        $savingsAccount->refresh();
        $this->assertEquals('50000.00', $savingsAccount->current_balance);
    }

    // ==================== Savings Withdrawal Tests ====================

    public function test_member_can_withdraw_from_own_savings_account(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        $savingsAccount = SavingsAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'savings_product_id' => $infra['savingsProduct']->id,
            'current_balance' => 100000,
            'status' => SavingsAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('member.savings.withdraw'), [
            'savings_account_id' => $savingsAccount->id,
            'amount' => 30000,
            'payment_method_id' => $infra['paymentMethod']->id,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $response->assertRedirect(route('member.savings'));
        $response->assertSessionHas('success');

        $savingsAccount->refresh();
        $this->assertEquals('70000.00', $savingsAccount->current_balance);

        $this->assertDatabaseHas('savings_transactions', [
            'member_id' => $member->id,
            'transaction_type' => 'withdrawal',
            'amount' => 30000,
            'status' => 'completed',
        ]);
    }

    public function test_savings_withdrawal_creates_accounting_journal(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        $savingsAccount = SavingsAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'savings_product_id' => $infra['savingsProduct']->id,
            'current_balance' => 100000,
            'status' => SavingsAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $this->post(route('member.savings.withdraw'), [
            'savings_account_id' => $savingsAccount->id,
            'amount' => 25000,
            'payment_method_id' => $infra['paymentMethod']->id,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $transaction = SavingsTransaction::where('member_id', $member->id)
            ->where('transaction_type', 'withdrawal')
            ->first();

        $this->assertNotNull($transaction);

        $this->assertDatabaseHas('journal_entries', [
            'organization_id' => $member->organization_id,
            'source_type' => SavingsTransaction::class,
            'source_id' => $transaction->id,
            'status' => 'posted',
        ]);
    }

    public function test_member_cannot_withdraw_more_than_balance(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        $savingsAccount = SavingsAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'savings_product_id' => $infra['savingsProduct']->id,
            'current_balance' => 10000,
            'status' => SavingsAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('member.savings.withdraw'), [
            'savings_account_id' => $savingsAccount->id,
            'amount' => 50000,
            'payment_method_id' => $infra['paymentMethod']->id,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $savingsAccount->refresh();
        $this->assertEquals('10000.00', $savingsAccount->current_balance);
    }

    public function test_member_cannot_withdraw_from_another_members_account(): void
    {
        ['user' => $userA, 'member' => $memberA] = $this->createMemberWithUser();
        ['user' => $userB, 'member' => $memberB] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($memberB);

        $savingsAccountB = SavingsAccount::factory()->create([
            'member_id' => $memberB->id,
            'organization_id' => $memberB->organization_id,
            'branch_id' => $memberB->branch_id,
            'vicoba_group_id' => $memberB->vicoba_group_id,
            'savings_product_id' => $infra['savingsProduct']->id,
            'current_balance' => 100000,
            'status' => SavingsAccountStatus::Active,
        ]);

        $this->actingAs($userA);

        $response = $this->post(route('member.savings.withdraw'), [
            'savings_account_id' => $savingsAccountB->id,
            'amount' => 50000,
            'payment_method_id' => $infra['paymentMethod']->id,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(404);

        $savingsAccountB->refresh();
        $this->assertEquals('100000.00', $savingsAccountB->current_balance);
    }

    // ==================== Share Purchase Tests ====================

    public function test_member_can_purchase_own_shares(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        $shareAccount = ShareAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'share_product_id' => $infra['shareProduct']->id,
            'total_shares' => 0,
            'total_value' => 0,
            'status' => ShareAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('member.shares.purchase'), [
            'share_account_id' => $shareAccount->id,
            'quantity' => 10,
            'payment_method_id' => $infra['paymentMethod']->id,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $response->assertRedirect(route('member.shares'));
        $response->assertSessionHas('success');

        $shareAccount->refresh();
        $this->assertEquals(10, $shareAccount->total_shares);
        $this->assertEquals('100000.00', $shareAccount->total_value);

        $this->assertDatabaseHas('share_transactions', [
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'transaction_type' => 'purchase',
            'quantity' => 10,
            'share_price' => 10000,
            'amount' => 100000,
            'status' => 'completed',
        ]);
    }

    public function test_share_purchase_creates_accounting_journal(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        $shareAccount = ShareAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'share_product_id' => $infra['shareProduct']->id,
            'total_shares' => 0,
            'total_value' => 0,
            'status' => ShareAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $this->post(route('member.shares.purchase'), [
            'share_account_id' => $shareAccount->id,
            'quantity' => 5,
            'payment_method_id' => $infra['paymentMethod']->id,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $transaction = ShareTransaction::where('member_id', $member->id)
            ->where('transaction_type', 'purchase')
            ->first();

        $this->assertNotNull($transaction);

        $this->assertDatabaseHas('journal_entries', [
            'organization_id' => $member->organization_id,
            'source_type' => ShareTransaction::class,
            'source_id' => $transaction->id,
            'status' => 'posted',
        ]);
    }

    public function test_member_cannot_purchase_shares_for_another_member(): void
    {
        ['user' => $userA, 'member' => $memberA] = $this->createMemberWithUser();
        ['user' => $userB, 'member' => $memberB] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($memberB);

        $shareAccountB = ShareAccount::factory()->create([
            'member_id' => $memberB->id,
            'organization_id' => $memberB->organization_id,
            'branch_id' => $memberB->branch_id,
            'vicoba_group_id' => $memberB->vicoba_group_id,
            'share_product_id' => $infra['shareProduct']->id,
            'total_shares' => 0,
            'total_value' => 0,
            'status' => ShareAccountStatus::Active,
        ]);

        $this->actingAs($userA);

        $response = $this->post(route('member.shares.purchase'), [
            'share_account_id' => $shareAccountB->id,
            'quantity' => 10,
            'payment_method_id' => $infra['paymentMethod']->id,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(404);

        $shareAccountB->refresh();
        $this->assertEquals(0, $shareAccountB->total_shares);
    }

    public function test_member_cannot_purchase_zero_shares(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        $shareAccount = ShareAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'share_product_id' => $infra['shareProduct']->id,
            'total_shares' => 0,
            'total_value' => 0,
            'status' => ShareAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('member.shares.purchase'), [
            'share_account_id' => $shareAccount->id,
            'quantity' => 0,
            'payment_method_id' => $infra['paymentMethod']->id,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('quantity');
    }

    public function test_member_cannot_manipulate_share_price(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        $shareAccount = ShareAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'share_product_id' => $infra['shareProduct']->id,
            'total_shares' => 0,
            'total_value' => 0,
            'status' => ShareAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('member.shares.purchase'), [
            'share_account_id' => $shareAccount->id,
            'quantity' => 10,
            'payment_method_id' => $infra['paymentMethod']->id,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $response->assertRedirect(route('member.shares'));
        $response->assertSessionHas('success');

        $shareAccount->refresh();
        $this->assertEquals(10, $shareAccount->total_shares);
        $this->assertEquals('100000.00', $shareAccount->total_value);
    }

    // ==================== Share Redemption Tests ====================

    public function test_member_can_redeem_own_shares(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        $shareAccount = ShareAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'share_product_id' => $infra['shareProduct']->id,
            'total_shares' => 50,
            'total_value' => 500000,
            'status' => ShareAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('member.shares.redeem'), [
            'share_account_id' => $shareAccount->id,
            'quantity' => 10,
            'payment_method_id' => $infra['paymentMethod']->id,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $response->assertRedirect(route('member.shares'));
        $response->assertSessionHas('success');

        $shareAccount->refresh();
        $this->assertEquals(40, $shareAccount->total_shares);
        $this->assertEquals('400000.00', $shareAccount->total_value);

        $this->assertDatabaseHas('share_transactions', [
            'member_id' => $member->id,
            'transaction_type' => 'redeem',
            'quantity' => 10,
            'status' => 'completed',
        ]);
    }

    public function test_share_redemption_creates_accounting_journal(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        $shareAccount = ShareAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'share_product_id' => $infra['shareProduct']->id,
            'total_shares' => 50,
            'total_value' => 500000,
            'status' => ShareAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $this->post(route('member.shares.redeem'), [
            'share_account_id' => $shareAccount->id,
            'quantity' => 5,
            'payment_method_id' => $infra['paymentMethod']->id,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $transaction = ShareTransaction::where('member_id', $member->id)
            ->where('transaction_type', 'redeem')
            ->first();

        $this->assertNotNull($transaction);

        $this->assertDatabaseHas('journal_entries', [
            'organization_id' => $member->organization_id,
            'source_type' => ShareTransaction::class,
            'source_id' => $transaction->id,
            'status' => 'posted',
        ]);
    }

    public function test_member_cannot_redeem_more_shares_than_available(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        $shareAccount = ShareAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'share_product_id' => $infra['shareProduct']->id,
            'total_shares' => 5,
            'total_value' => 50000,
            'status' => ShareAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('member.shares.redeem'), [
            'share_account_id' => $shareAccount->id,
            'quantity' => 10,
            'payment_method_id' => $infra['paymentMethod']->id,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $shareAccount->refresh();
        $this->assertEquals(5, $shareAccount->total_shares);
    }

    public function test_member_cannot_redeem_another_members_shares(): void
    {
        ['user' => $userA, 'member' => $memberA] = $this->createMemberWithUser();
        ['user' => $userB, 'member' => $memberB] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($memberB);

        $shareAccountB = ShareAccount::factory()->create([
            'member_id' => $memberB->id,
            'organization_id' => $memberB->organization_id,
            'branch_id' => $memberB->branch_id,
            'vicoba_group_id' => $memberB->vicoba_group_id,
            'share_product_id' => $infra['shareProduct']->id,
            'total_shares' => 50,
            'total_value' => 500000,
            'status' => ShareAccountStatus::Active,
        ]);

        $this->actingAs($userA);

        $response = $this->post(route('member.shares.redeem'), [
            'share_account_id' => $shareAccountB->id,
            'quantity' => 10,
            'payment_method_id' => $infra['paymentMethod']->id,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(404);

        $shareAccountB->refresh();
        $this->assertEquals(50, $shareAccountB->total_shares);
    }

    // ==================== Cross-Tenant Tests ====================

    public function test_member_from_org_a_cannot_use_org_b_payment_method(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        $orgB = Organization::factory()->create();
        $paymentMethodB = PaymentMethod::factory()->create([
            'organization_id' => $orgB->id,
            'status' => 'active',
        ]);

        $savingsAccount = SavingsAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'savings_product_id' => $infra['savingsProduct']->id,
            'current_balance' => 0,
            'status' => SavingsAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('member.savings.deposit'), [
            'savings_account_id' => $savingsAccount->id,
            'amount' => 50000,
            'payment_method_id' => $paymentMethodB->id,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    // ==================== Route Protection Tests ====================

    public function test_savings_deposit_requires_authentication(): void
    {
        $response = $this->post(route('member.savings.deposit'), []);
        $response->assertRedirect(route('login'));
    }

    public function test_savings_withdraw_requires_authentication(): void
    {
        $response = $this->post(route('member.savings.withdraw'), []);
        $response->assertRedirect(route('login'));
    }

    public function test_share_purchase_requires_authentication(): void
    {
        $response = $this->post(route('member.shares.purchase'), []);
        $response->assertRedirect(route('login'));
    }

    public function test_share_redeem_requires_authentication(): void
    {
        $response = $this->post(route('member.shares.redeem'), []);
        $response->assertRedirect(route('login'));
    }

    public function test_inactive_member_cannot_perform_savings_deposit(): void
    {
        ['user' => $user] = $this->createMemberWithUser([
            'membership_status' => MemberStatus::Inactive,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('member.savings.deposit'), [
            'savings_account_id' => 1,
            'amount' => 50000,
            'payment_method_id' => 1,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(403);
    }

    public function test_inactive_member_cannot_perform_share_purchase(): void
    {
        ['user' => $user] = $this->createMemberWithUser([
            'membership_status' => MemberStatus::Inactive,
        ]);

        $this->actingAs($user);

        $response = $this->post(route('member.shares.purchase'), [
            'share_account_id' => 1,
            'quantity' => 10,
            'payment_method_id' => 1,
            'transaction_date' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(403);
    }

    // ==================== Enum Status Display Regression Tests ====================

    public function test_savings_page_shows_active_badge_and_action_buttons_for_active_account(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        SavingsAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'savings_product_id' => $infra['savingsProduct']->id,
            'current_balance' => 100000,
            'status' => SavingsAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('member.savings'));
        $response->assertOk();
        $response->assertSee('Deposit');
        $response->assertSee('Withdraw');
        $response->assertSee('100,000.00');
    }

    public function test_savings_page_hides_action_buttons_when_no_active_accounts(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        SavingsAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'savings_product_id' => $infra['savingsProduct']->id,
            'current_balance' => 50000,
            'status' => SavingsAccountStatus::Inactive,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('member.savings'));
        $response->assertOk();
        $response->assertSee('Inactive');
        $response->assertDontSee('data-bs-target="#depositModal"', false);
        $response->assertDontSee('data-bs-target="#withdrawModal"', false);
    }

    public function test_shares_page_shows_active_badge_and_action_buttons_for_active_account(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        ShareAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'share_product_id' => $infra['shareProduct']->id,
            'total_shares' => 10,
            'total_value' => 100000,
            'status' => ShareAccountStatus::Active,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('member.shares'));
        $response->assertOk();
        $response->assertSee('Purchase');
        $response->assertSee('Redeem');
        $response->assertSee('100,000.00');
    }

    public function test_shares_page_hides_action_buttons_when_no_active_accounts(): void
    {
        ['user' => $user, 'member' => $member] = $this->createMemberWithUser();
        $infra = $this->setupFinancialInfrastructure($member);

        ShareAccount::factory()->create([
            'member_id' => $member->id,
            'organization_id' => $member->organization_id,
            'branch_id' => $member->branch_id,
            'vicoba_group_id' => $member->vicoba_group_id,
            'share_product_id' => $infra['shareProduct']->id,
            'total_shares' => 10,
            'total_value' => 100000,
            'status' => ShareAccountStatus::Inactive,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('member.shares'));
        $response->assertOk();
        $response->assertSee('Inactive');
        $response->assertDontSee('data-bs-target="#purchaseModal"', false);
        $response->assertDontSee('data-bs-target="#redeemModal"', false);
    }
}
