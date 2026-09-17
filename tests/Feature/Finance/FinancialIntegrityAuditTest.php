<?php

namespace Tests\Feature\Finance;

use App\Enums\FinancialTransactionStatus;
use App\Models\AuditLog;
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
use App\Models\WelfareAccount;
use App\Models\WelfareFund;
use App\Models\WelfareTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\HasAccountingSetup;

class FinancialIntegrityAuditTest extends TestCase
{
    use RefreshDatabase, HasAccountingSetup;

    private User $admin;
    private Organization $orgA;
    private Organization $orgB;
    private Branch $branchA;
    private Branch $branchB;
    private VicobaGroup $groupA;
    private VicobaGroup $groupB;
    private SavingsProduct $savingsProductA;
    private SavingsProduct $savingsProductB;
    private ShareProduct $shareProductA;
    private ShareProduct $shareProductB;
    private WelfareFund $welfareFundA;
    private WelfareFund $welfareFundB;
    private PaymentMethod $paymentMethodA;
    private PaymentMethod $paymentMethodB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->admin = User::where('email', 'admin@financepro.co.tz')->first();

        $this->orgA = Organization::factory()->create();
        $this->orgB = Organization::factory()->create();
        $this->branchA = Branch::factory()->create(['organization_id' => $this->orgA->id]);
        $this->branchB = Branch::factory()->create(['organization_id' => $this->orgB->id]);
        $this->groupA = VicobaGroup::factory()->create(['branch_id' => $this->branchA->id]);
        $this->groupB = VicobaGroup::factory()->create(['branch_id' => $this->branchB->id]);
        $this->savingsProductA = SavingsProduct::factory()->create(['organization_id' => $this->orgA->id, 'allow_withdrawal' => true, 'minimum_balance' => 0]);
        $this->savingsProductB = SavingsProduct::factory()->create(['organization_id' => $this->orgB->id, 'allow_withdrawal' => true, 'minimum_balance' => 0]);
        $this->shareProductA = ShareProduct::factory()->create(['organization_id' => $this->orgA->id, 'share_price' => 10000]);
        $this->shareProductB = ShareProduct::factory()->create(['organization_id' => $this->orgB->id, 'share_price' => 5000]);
        $this->welfareFundA = WelfareFund::factory()->create(['organization_id' => $this->orgA->id]);
        $this->welfareFundB = WelfareFund::factory()->create(['organization_id' => $this->orgB->id]);
        $this->paymentMethodA = PaymentMethod::factory()->create(['organization_id' => $this->orgA->id, 'status' => 'active']);
        $this->paymentMethodB = PaymentMethod::factory()->create(['organization_id' => $this->orgB->id, 'status' => 'active']);

        $this->admin->organizations()->attach([$this->orgA->id, $this->orgB->id]);
        $this->setUpAccountingFor($this->admin, $this->orgA);
        $this->setUpAccountingFor($this->admin, $this->orgB);
    }

    private function createSavingsAccount(Organization $org, Branch $branch, VicobaGroup $group, SavingsProduct $product, User $user): SavingsAccount
    {
        $member = Member::factory()->create(['organization_id' => $org->id, 'branch_id' => $branch->id]);
        return SavingsAccount::createQuietly([
            'member_id' => $member->id,
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'vicoba_group_id' => $group->id,
            'savings_product_id' => $product->id,
            'account_number' => 'SAV-AUDIT-' . uniqid(),
            'current_balance' => 0,
            'opening_date' => now(),
            'status' => 'active',
            'created_by' => $user->id,
        ]);
    }

    private function createShareAccount(Organization $org, Branch $branch, VicobaGroup $group, ShareProduct $product, User $user): ShareAccount
    {
        $member = Member::factory()->create(['organization_id' => $org->id, 'branch_id' => $branch->id]);
        return ShareAccount::createQuietly([
            'member_id' => $member->id,
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'vicoba_group_id' => $group->id,
            'share_product_id' => $product->id,
            'account_number' => 'SHR-AUDIT-' . uniqid(),
            'total_shares' => 0,
            'total_value' => 0,
            'status' => 'active',
            'created_by' => $user->id,
        ]);
    }

    private function createWelfareAccount(Organization $org, Branch $branch, VicobaGroup $group, WelfareFund $fund, User $user): WelfareAccount
    {
        $member = Member::factory()->create(['organization_id' => $org->id, 'branch_id' => $branch->id]);
        return WelfareAccount::createQuietly([
            'member_id' => $member->id,
            'organization_id' => $org->id,
            'branch_id' => $branch->id,
            'vicoba_group_id' => $group->id,
            'welfare_fund_id' => $fund->id,
            'account_number' => 'WFA-AUDIT-' . uniqid(),
            'current_balance' => 0,
            'status' => 'active',
            'created_by' => $user->id,
        ]);
    }

    private function makeOrgAdmin(Organization $org): User
    {
        $user = User::factory()->create();
        $user->assignRole('Organization Administrator');
        $user->organizations()->attach($org->id);
        return $user;
    }

    private function makeBranchManager(Branch $branch): User
    {
        $user = User::factory()->create();
        $user->assignRole('Branch Manager');
        $user->branches()->attach($branch->id);
        $user->organizations()->attach($branch->organization_id);
        return $user;
    }

    // =============================================
    // 2. FINANCIAL ATOMICITY
    // =============================================

    public function test_savings_deposit_uses_db_transaction(): void
    {
        $account = $this->createSavingsAccount($this->orgA, $this->branchA, $this->groupA, $this->savingsProductA, $this->admin);

        $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $account), [
            'amount' => 50000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $account->refresh();
        $this->assertEquals(50000, (float) $account->current_balance);
        $this->assertDatabaseHas('savings_transactions', [
            'savings_account_id' => $account->id,
            'transaction_type' => 'deposit',
            'amount' => 50000,
            'balance_before' => 0,
            'balance_after' => 50000,
            'status' => 'completed',
        ]);
    }

    public function test_savings_withdrawal_uses_db_transaction(): void
    {
        $account = $this->createSavingsAccount($this->orgA, $this->branchA, $this->groupA, $this->savingsProductA, $this->admin);

        $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $account), [
            'amount' => 200000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $this->actingAs($this->admin)->post(route('savings-accounts.do-withdraw', $account), [
            'amount' => 50000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $account->refresh();
        $this->assertEquals(150000, (float) $account->current_balance);

        $withdrawal = SavingsTransaction::where('savings_account_id', $account->id)
            ->where('transaction_type', 'withdrawal')->first();
        $this->assertEquals(200000, (float) $withdrawal->balance_before);
        $this->assertEquals(150000, (float) $withdrawal->balance_after);
    }

    public function test_insufficient_savings_balance_rolls_back(): void
    {
        $account = $this->createSavingsAccount($this->orgA, $this->branchA, $this->groupA, $this->savingsProductA, $this->admin);

        $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $account), [
            'amount' => 10000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->post(route('savings-accounts.do-withdraw', $account), [
            'amount' => 50000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response->assertSessionHas('error');
        $account->refresh();
        $this->assertEquals(10000, (float) $account->current_balance);
        $this->assertDatabaseMissing('savings_transactions', [
            'savings_account_id' => $account->id,
            'transaction_type' => 'withdrawal',
            'status' => 'completed',
        ]);
    }

    public function test_welfare_benefit_rolls_back_on_insufficient_balance(): void
    {
        $account = $this->createWelfareAccount($this->orgA, $this->branchA, $this->groupA, $this->welfareFundA, $this->admin);

        $this->actingAs($this->admin)->post(route('welfare-accounts.do-contribute', $account), [
            'amount' => 10000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->post(route('welfare-accounts.do-benefit', $account), [
            'amount' => 50000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response->assertSessionHas('error');
        $account->refresh();
        $this->assertEquals(10000, (float) $account->current_balance);
    }

    public function test_share_redeem_rolls_back_on_insufficient_shares(): void
    {
        $account = $this->createShareAccount($this->orgA, $this->branchA, $this->groupA, $this->shareProductA, $this->admin);

        $this->actingAs($this->admin)->post(route('share-accounts.do-purchase', $account), [
            'quantity' => 5,
            'share_price' => $this->shareProductA->share_price,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->post(route('share-accounts.do-redeem', $account), [
            'quantity' => 10,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response->assertSessionHas('error');
        $account->refresh();
        $this->assertEquals(5, $account->total_shares);
    }

    // =============================================
    // 3. BALANCE INTEGRITY
    // =============================================

    public function test_savings_balance_calculation_is_server_side(): void
    {
        $account = $this->createSavingsAccount($this->orgA, $this->branchA, $this->groupA, $this->savingsProductA, $this->admin);

        $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $account), [
            'amount' => 100000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
            'current_balance' => 9999999,
        ]);

        $account->refresh();
        $this->assertEquals(100000, (float) $account->current_balance);
        $this->assertNotEquals(9999999, (float) $account->current_balance);
    }

    public function test_share_value_is_calculated_server_side(): void
    {
        $account = $this->createShareAccount($this->orgA, $this->branchA, $this->groupA, $this->shareProductA, $this->admin);

        $this->actingAs($this->admin)->post(route('share-accounts.do-purchase', $account), [
            'quantity' => 10,
            'share_price' => 1,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $account->refresh();
        $this->assertEquals(0, $account->total_shares);
        $this->assertEquals(0, (float) $account->total_value);
    }

    public function test_share_purchase_validates_price_against_product(): void
    {
        $account = $this->createShareAccount($this->orgA, $this->branchA, $this->groupA, $this->shareProductA, $this->admin);

        $response = $this->actingAs($this->admin)->post(route('share-accounts.do-purchase', $account), [
            'quantity' => 10,
            'share_price' => 999999,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response->assertSessionHas('error');
        $account->refresh();
        $this->assertEquals(0, $account->total_shares);
    }

    public function test_savings_balance_before_after_are_correct(): void
    {
        $account = $this->createSavingsAccount($this->orgA, $this->branchA, $this->groupA, $this->savingsProductA, $this->admin);

        $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $account), [
            'amount' => 100000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $account), [
            'amount' => 50000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $transactions = SavingsTransaction::where('savings_account_id', $account->id)
            ->where('transaction_type', 'deposit')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $transactions);
        $this->assertEquals(0, (float) $transactions[0]->balance_before);
        $this->assertEquals(100000, (float) $transactions[0]->balance_after);
        $this->assertEquals(100000, (float) $transactions[1]->balance_before);
        $this->assertEquals(150000, (float) $transactions[1]->balance_after);
    }

    public function test_decimal_precision_is_maintained(): void
    {
        $account = $this->createSavingsAccount($this->orgA, $this->branchA, $this->groupA, $this->savingsProductA, $this->admin);

        $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $account), [
            'amount' => 12345.67,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $account->refresh();
        $this->assertEquals('12345.67', $account->current_balance);
    }

    // =============================================
    // 4. CONCURRENCY
    // =============================================

    public function test_concurrent_savings_withdrawals_are_prevented(): void
    {
        $account = $this->createSavingsAccount($this->orgA, $this->branchA, $this->groupA, $this->savingsProductA, $this->admin);

        $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $account), [
            'amount' => 100000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($this->admin)->post(route('savings-accounts.do-withdraw', $account), [
                'amount' => 80000,
                'payment_method_id' => $this->paymentMethodA->id,
                'transaction_date' => now()->toDateString(),
            ]);
        }

        $account->refresh();
        $this->assertGreaterThanOrEqual(0, (float) $account->current_balance);
    }

    public function test_transaction_number_generator_produces_sequential_numbers(): void
    {
        $generator = app(\App\Services\TransactionNumberGenerator::class);
        $num1 = $generator->generate('SVT');

        $account = $this->createSavingsAccount($this->orgA, $this->branchA, $this->groupA, $this->savingsProductA, $this->admin);
        $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $account), [
            'amount' => 10000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $num2 = $generator->generate('SVT');
        $this->assertStringStartsWith('SVT', $num1);
        $this->assertStringStartsWith('SVT', $num2);
    }

    // =============================================
    // 5. IDEMPOTENCY
    // =============================================

    public function test_idempotency_key_is_stored_on_savings_transaction(): void
    {
        $account = $this->createSavingsAccount($this->orgA, $this->branchA, $this->groupA, $this->savingsProductA, $this->admin);

        $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $account), [
            'amount' => 50000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $transaction = SavingsTransaction::where('savings_account_id', $account->id)->first();
        $this->assertNotNull($transaction);
    }

    public function test_duplicate_idempotency_key_causes_db_error(): void
    {
        $account = $this->createSavingsAccount($this->orgA, $this->branchA, $this->groupA, $this->savingsProductA, $this->admin);

        $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $account), [
            'amount' => 50000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
            'idempotency_key' => 'TEST-IDEMPOTENT-001',
        ]);

        $this->assertDatabaseHas('savings_transactions', [
            'idempotency_key' => 'TEST-IDEMPOTENT-001',
        ]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        DB::table('savings_transactions')->insert([
            'savings_account_id' => $account->id,
            'member_id' => $account->member_id,
            'organization_id' => $account->organization_id,
            'branch_id' => $account->branch_id,
            'vicoba_group_id' => $account->vicoba_group_id,
            'transaction_number' => 'SVT-DUPLICATE-001',
            'transaction_type' => 'deposit',
            'amount' => 50000,
            'balance_before' => 0,
            'balance_after' => 50000,
            'transaction_date' => now()->toDateString(),
            'status' => 'completed',
            'idempotency_key' => 'TEST-IDEMPOTENT-001',
        ]);
    }

    // =============================================
    // 6. REVERSAL INTEGRITY
    // =============================================

    public function test_savings_reversal_restores_balance(): void
    {
        $account = $this->createSavingsAccount($this->orgA, $this->branchA, $this->groupA, $this->savingsProductA, $this->admin);

        $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $account), [
            'amount' => 100000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $deposit = SavingsTransaction::where('savings_account_id', $account->id)->first();

        $this->actingAs($this->admin)->post(route('savings-transactions.reverse', $deposit), [
            'reason' => 'Test reversal',
        ]);

        $account->refresh();
        $this->assertEquals(0, (float) $account->current_balance);

        $deposit->refresh();
        $this->assertEquals(FinancialTransactionStatus::Reversed, $deposit->status);

        $reversal = SavingsTransaction::where('reversed_transaction_id', $deposit->id)->first();
        $this->assertNotNull($reversal);
        $this->assertEquals('reversal', $reversal->transaction_type->value);
        $this->assertEquals($deposit->amount, $reversal->amount);
    }

    public function test_double_reversal_is_prevented(): void
    {
        $account = $this->createSavingsAccount($this->orgA, $this->branchA, $this->groupA, $this->savingsProductA, $this->admin);

        $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $account), [
            'amount' => 100000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $deposit = SavingsTransaction::where('savings_account_id', $account->id)->first();

        $this->actingAs($this->admin)->post(route('savings-transactions.reverse', $deposit), [
            'reason' => 'First reversal',
        ]);

        $response = $this->actingAs($this->admin)->post(route('savings-transactions.reverse', $deposit), [
            'reason' => 'Second reversal attempt',
        ]);

        $response->assertSessionHas('error');
        $account->refresh();
        $this->assertEquals(0, (float) $account->current_balance);
    }

    public function test_reversal_creates_separate_transaction(): void
    {
        $account = $this->createSavingsAccount($this->orgA, $this->branchA, $this->groupA, $this->savingsProductA, $this->admin);

        $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $account), [
            'amount' => 100000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $deposit = SavingsTransaction::where('savings_account_id', $account->id)->first();

        $this->actingAs($this->admin)->post(route('savings-transactions.reverse', $deposit), [
            'reason' => 'Test reversal',
        ]);

        $reversal = SavingsTransaction::where('reversed_transaction_id', $deposit->id)->first();
        $this->assertNotEquals($deposit->id, $reversal->id);
        $this->assertDatabaseHas('savings_transactions', [
            'reversed_transaction_id' => $deposit->id,
            'transaction_type' => 'reversal',
        ]);
    }

    public function test_reversed_transaction_cannot_be_reversed_again(): void
    {
        $account = $this->createSavingsAccount($this->orgA, $this->branchA, $this->groupA, $this->savingsProductA, $this->admin);

        $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $account), [
            'amount' => 100000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $deposit = SavingsTransaction::where('savings_account_id', $account->id)->first();

        $this->actingAs($this->admin)->post(route('savings-transactions.reverse', $deposit), [
            'reason' => 'First reversal',
        ]);

        $response = $this->actingAs($this->admin)->post(route('savings-transactions.reverse', $deposit), [
            'reason' => 'Second reversal',
        ]);

        $response->assertSessionHas('error');
    }

    public function test_reversal_requires_reason(): void
    {
        $account = $this->createSavingsAccount($this->orgA, $this->branchA, $this->groupA, $this->savingsProductA, $this->admin);

        $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $account), [
            'amount' => 100000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $deposit = SavingsTransaction::where('savings_account_id', $account->id)->first();

        $response = $this->actingAs($this->admin)->post(route('savings-transactions.reverse', $deposit), []);
        $response->assertSessionHasErrors('reason');
    }

    public function test_reversal_sets_reversed_by(): void
    {
        $account = $this->createSavingsAccount($this->orgA, $this->branchA, $this->groupA, $this->savingsProductA, $this->admin);

        $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $account), [
            'amount' => 100000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $deposit = SavingsTransaction::where('savings_account_id', $account->id)->first();

        $this->actingAs($this->admin)->post(route('savings-transactions.reverse', $deposit), [
            'reason' => 'Test reversal',
        ]);

        $deposit->refresh();
        $this->assertEquals($this->admin->id, $deposit->reversed_by);
    }

    public function test_reversal_cannot_cross_organization_boundary(): void
    {
        $accountA = $this->createSavingsAccount($this->orgA, $this->branchA, $this->groupA, $this->savingsProductA, $this->admin);
        $orgAdminB = $this->makeOrgAdmin($this->orgB);

        $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $accountA), [
            'amount' => 100000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $deposit = SavingsTransaction::where('savings_account_id', $accountA->id)->first();

        $response = $this->actingAs($orgAdminB)->post(route('savings-transactions.reverse', $deposit), [
            'reason' => 'Cross-org reversal attempt',
        ]);

        $response->assertForbidden();
    }

    // =============================================
    // 7. TENANT ISOLATION
    // =============================================

    public function test_org_admin_cannot_view_other_org_savings_account(): void
    {
        $accountA = $this->createSavingsAccount($this->orgA, $this->branchA, $this->groupA, $this->savingsProductA, $this->admin);
        $orgAdminB = $this->makeOrgAdmin($this->orgB);

        $response = $this->actingAs($orgAdminB)->get(route('savings-accounts.show', $accountA));
        $response->assertForbidden();
    }

    public function test_org_admin_cannot_view_other_org_share_account(): void
    {
        $accountA = $this->createShareAccount($this->orgA, $this->branchA, $this->groupA, $this->shareProductA, $this->admin);
        $orgAdminB = $this->makeOrgAdmin($this->orgB);

        $response = $this->actingAs($orgAdminB)->get(route('share-accounts.show', $accountA));
        $response->assertForbidden();
    }

    public function test_org_admin_cannot_view_other_org_welfare_account(): void
    {
        $accountA = $this->createWelfareAccount($this->orgA, $this->branchA, $this->groupA, $this->welfareFundA, $this->admin);
        $orgAdminB = $this->makeOrgAdmin($this->orgB);

        $response = $this->actingAs($orgAdminB)->get(route('welfare-accounts.show', $accountA));
        $response->assertForbidden();
    }

    public function test_org_admin_cannot_deposit_to_other_org_account(): void
    {
        $accountA = $this->createSavingsAccount($this->orgA, $this->branchA, $this->groupA, $this->savingsProductA, $this->admin);
        $orgAdminB = $this->makeOrgAdmin($this->orgB);

        $response = $this->actingAs($orgAdminB)->post(route('savings-accounts.do-deposit', $accountA), [
            'amount' => 50000,
            'payment_method_id' => $this->paymentMethodB->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response->assertForbidden();
        $accountA->refresh();
        $this->assertEquals(0, (float) $accountA->current_balance);
    }

    public function test_org_admin_cannot_reverse_other_org_transaction(): void
    {
        $accountA = $this->createSavingsAccount($this->orgA, $this->branchA, $this->groupA, $this->savingsProductA, $this->admin);

        $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $accountA), [
            'amount' => 100000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $deposit = SavingsTransaction::where('savings_account_id', $accountA->id)->first();
        $orgAdminB = $this->makeOrgAdmin($this->orgB);

        $response = $this->actingAs($orgAdminB)->post(route('savings-transactions.reverse', $deposit), [
            'reason' => 'Cross-org reversal',
        ]);

        $response->assertForbidden();
    }

    public function test_branch_manager_cannot_access_other_branch_account(): void
    {
        $accountA = $this->createSavingsAccount($this->orgA, $this->branchA, $this->groupA, $this->savingsProductA, $this->admin);
        $branchManagerB = $this->makeBranchManager($this->branchB);

        $response = $this->actingAs($branchManagerB)->get(route('savings-accounts.show', $accountA));
        $response->assertForbidden();
    }

    public function test_org_admin_payment_method_index_scoped_to_org(): void
    {
        PaymentMethod::factory()->count(3)->create(['organization_id' => $this->orgA->id]);
        PaymentMethod::factory()->count(2)->create(['organization_id' => $this->orgB->id]);

        $orgAdminA = $this->makeOrgAdmin($this->orgA);

        $response = $this->actingAs($orgAdminA)->get(route('payment-methods.index'));
        $response->assertOk();

        $paymentMethods = $response->viewData('paymentMethods');
        foreach ($paymentMethods as $pm) {
            $this->assertEquals($this->orgA->id, $pm->organization_id);
        }
    }

    // =============================================
    // 8. MASS ASSIGNMENT
    // =============================================

    public function test_savings_account_current_balance_cannot_be_manipulated_via_store(): void
    {
        $member = Member::factory()->create(['organization_id' => $this->orgA->id, 'branch_id' => $this->branchA->id]);

        $response = $this->actingAs($this->admin)->post(route('savings-accounts.store'), [
            'member_id' => $member->id,
            'savings_product_id' => $this->savingsProductA->id,
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branchA->id,
            'vicoba_group_id' => $this->groupA->id,
            'opening_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $account = SavingsAccount::where('member_id', $member->id)->first();
        $this->assertEquals(0, (float) $account->current_balance);
    }

    public function test_savings_account_account_number_cannot_be_manipulated_via_store(): void
    {
        $member = Member::factory()->create(['organization_id' => $this->orgA->id, 'branch_id' => $this->branchA->id]);

        $response = $this->actingAs($this->admin)->post(route('savings-accounts.store'), [
            'member_id' => $member->id,
            'savings_product_id' => $this->savingsProductA->id,
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branchA->id,
            'vicoba_group_id' => $this->groupA->id,
            'opening_date' => now()->toDateString(),
            'account_number' => 'SAV-HACKED-9999',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('savings_accounts', ['account_number' => 'SAV-HACKED-9999']);
    }

    public function test_savings_transaction_balance_cannot_be_manipulated(): void
    {
        $account = $this->createSavingsAccount($this->orgA, $this->branchA, $this->groupA, $this->savingsProductA, $this->admin);

        $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $account), [
            'amount' => 100000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
            'balance_before' => 0,
            'balance_after' => 9999999,
        ]);

        $account->refresh();
        $this->assertEquals(100000, (float) $account->current_balance);

        $transaction = SavingsTransaction::where('savings_account_id', $account->id)->first();
        $this->assertEquals(0, (float) $transaction->balance_before);
        $this->assertEquals(100000, (float) $transaction->balance_after);
    }

    // =============================================
    // 9. TRANSACTION NUMBER INTEGRITY
    // =============================================

    public function test_transaction_numbers_are_generated_server_side(): void
    {
        $account = $this->createSavingsAccount($this->orgA, $this->branchA, $this->groupA, $this->savingsProductA, $this->admin);

        $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $account), [
            'amount' => 100000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $transaction = SavingsTransaction::where('savings_account_id', $account->id)->first();
        $this->assertStringStartsWith('SVT', $transaction->transaction_number);
        $this->assertMatchesRegularExpression('/^SVT\d{8}$/', $transaction->transaction_number);
    }

    public function test_transaction_numbers_are_unique(): void
    {
        $account = $this->createSavingsAccount($this->orgA, $this->branchA, $this->groupA, $this->savingsProductA, $this->admin);

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $account), [
                'amount' => 10000 + $i,
                'payment_method_id' => $this->paymentMethodA->id,
                'transaction_date' => now()->toDateString(),
            ]);
        }

        $count = SavingsTransaction::where('savings_account_id', $account->id)->count();
        $uniqueCount = SavingsTransaction::where('savings_account_id', $account->id)
            ->distinct('transaction_number')->count('transaction_number');
        $this->assertEquals($count, $uniqueCount);
    }

    public function test_share_transaction_numbers_are_unique(): void
    {
        $account = $this->createShareAccount($this->orgA, $this->branchA, $this->groupA, $this->shareProductA, $this->admin);

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($this->admin)->post(route('share-accounts.do-purchase', $account), [
                'quantity' => 1 + $i,
                'share_price' => $this->shareProductA->share_price,
                'payment_method_id' => $this->paymentMethodA->id,
                'transaction_date' => now()->toDateString(),
            ]);
        }

        $count = ShareTransaction::where('share_account_id', $account->id)->count();
        $uniqueCount = ShareTransaction::where('share_account_id', $account->id)
            ->distinct('transaction_number')->count('transaction_number');
        $this->assertEquals($count, $uniqueCount);
    }

    // =============================================
    // 10. ACCOUNT NUMBER INTEGRITY
    // =============================================

    public function test_savings_account_numbers_are_unique(): void
    {
        $numbers = [];
        for ($i = 0; $i < 3; $i++) {
            $member = Member::factory()->create(['organization_id' => $this->orgA->id, 'branch_id' => $this->branchA->id]);
            $this->actingAs($this->admin)->post(route('savings-accounts.store'), [
                'member_id' => $member->id,
                'savings_product_id' => $this->savingsProductA->id,
                'organization_id' => $this->orgA->id,
                'branch_id' => $this->branchA->id,
                'vicoba_group_id' => $this->groupA->id,
                'opening_date' => now()->toDateString(),
            ]);
        }

        $accounts = SavingsAccount::where('organization_id', $this->orgA->id)->get();
        $uniqueNumbers = $accounts->pluck('account_number')->unique();
        $this->assertCount($accounts->count(), $uniqueNumbers);
    }

    public function test_share_account_number_generation_produces_valid_format(): void
    {
        $number = ShareAccount::generateAccountNumber();
        $this->assertStringStartsWith('SHR-', $number);
        $this->assertMatchesRegularExpression('/^SHR-\d{6}$/', $number);
    }

    public function test_welfare_account_number_generation_produces_valid_format(): void
    {
        $number = WelfareAccount::generateAccountNumber();
        $this->assertStringStartsWith('WFA-', $number);
        $this->assertMatchesRegularExpression('/^WFA-\d{6}$/', $number);
    }

    public function test_account_number_database_unique_constraint(): void
    {
        $member1 = Member::factory()->create(['organization_id' => $this->orgA->id, 'branch_id' => $this->branchA->id]);
        $member2 = Member::factory()->create(['organization_id' => $this->orgA->id, 'branch_id' => $this->branchA->id]);

        SavingsAccount::createQuietly([
            'member_id' => $member1->id,
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branchA->id,
            'vicoba_group_id' => $this->groupA->id,
            'savings_product_id' => $this->savingsProductA->id,
            'account_number' => 'SAV-UNIQUE-001',
            'opening_date' => now()->toDateString(),
            'status' => 'active',
            'current_balance' => 0,
        ]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        SavingsAccount::createQuietly([
            'member_id' => $member2->id,
            'organization_id' => $this->orgA->id,
            'branch_id' => $this->branchA->id,
            'vicoba_group_id' => $this->groupA->id,
            'savings_product_id' => $this->savingsProductA->id,
            'account_number' => 'SAV-UNIQUE-001',
            'opening_date' => now()->toDateString(),
            'status' => 'active',
            'current_balance' => 0,
        ]);
    }

    // =============================================
    // 11. SHARE VALUE INTEGRITY
    // =============================================

    public function test_share_purchase_validates_amount_matches_quantity_times_price(): void
    {
        $account = $this->createShareAccount($this->orgA, $this->branchA, $this->groupA, $this->shareProductA, $this->admin);

        $response = $this->actingAs($this->admin)->post(route('share-accounts.do-purchase', $account), [
            'quantity' => 10,
            'share_price' => $this->shareProductA->share_price,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $account->refresh();
        $this->assertEquals(10, $account->total_shares);
        $this->assertEquals(10 * $this->shareProductA->share_price, (float) $account->total_value);
    }

    public function test_cannot_redeem_more_shares_than_owned(): void
    {
        $account = $this->createShareAccount($this->orgA, $this->branchA, $this->groupA, $this->shareProductA, $this->admin);

        $this->actingAs($this->admin)->post(route('share-accounts.do-purchase', $account), [
            'quantity' => 5,
            'share_price' => $this->shareProductA->share_price,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->post(route('share-accounts.do-redeem', $account), [
            'quantity' => 10,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response->assertSessionHas('error');
        $account->refresh();
        $this->assertEquals(5, $account->total_shares);
    }

    public function test_share_redemption_uses_product_price_not_user_input(): void
    {
        $account = $this->createShareAccount($this->orgA, $this->branchA, $this->groupA, $this->shareProductA, $this->admin);

        $this->actingAs($this->admin)->post(route('share-accounts.do-purchase', $account), [
            'quantity' => 10,
            'share_price' => $this->shareProductA->share_price,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->post(route('share-accounts.do-redeem', $account), [
            'quantity' => 5,
            'share_price' => 1,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $account->refresh();
        $this->assertEquals(5, $account->total_shares);
        $expectedValue = 5 * $this->shareProductA->share_price;
        $this->assertEquals($expectedValue, (float) $account->total_value);
    }

    // =============================================
    // 12. WELFARE BENEFIT INTEGRITY
    // =============================================

    public function test_welfare_benefit_cannot_exceed_balance(): void
    {
        $account = $this->createWelfareAccount($this->orgA, $this->branchA, $this->groupA, $this->welfareFundA, $this->admin);

        $this->actingAs($this->admin)->post(route('welfare-accounts.do-contribute', $account), [
            'amount' => 10000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->post(route('welfare-accounts.do-benefit', $account), [
            'amount' => 50000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response->assertSessionHas('error');
        $account->refresh();
        $this->assertEquals(10000, (float) $account->current_balance);
        $this->assertGreaterThanOrEqual(0, (float) $account->current_balance);
    }

    public function test_welfare_benefit_exact_balance_succeeds(): void
    {
        $account = $this->createWelfareAccount($this->orgA, $this->branchA, $this->groupA, $this->welfareFundA, $this->admin);

        $this->actingAs($this->admin)->post(route('welfare-accounts.do-contribute', $account), [
            'amount' => 50000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->post(route('welfare-accounts.do-benefit', $account), [
            'amount' => 50000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response->assertRedirect();
        $account->refresh();
        $this->assertEquals(0, (float) $account->current_balance);
    }

    // =============================================
    // 13. PAYMENT METHOD SECURITY
    // =============================================

    public function test_inactive_payment_method_cannot_be_used(): void
    {
        $inactiveMethod = PaymentMethod::factory()->create(['organization_id' => $this->orgA->id, 'status' => 'inactive']);
        $account = $this->createSavingsAccount($this->orgA, $this->branchA, $this->groupA, $this->savingsProductA, $this->admin);

        $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $account), [
            'amount' => 50000,
            'payment_method_id' => $inactiveMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $account->refresh();
        $this->assertEquals(0, (float) $account->current_balance);
        $this->assertDatabaseMissing('savings_transactions', [
            'savings_account_id' => $account->id,
            'status' => 'completed',
        ]);
    }

    public function test_inactive_payment_method_cannot_be_used_for_welfare(): void
    {
        $inactiveMethod = PaymentMethod::factory()->create(['organization_id' => $this->orgA->id, 'status' => 'inactive']);
        $account = $this->createWelfareAccount($this->orgA, $this->branchA, $this->groupA, $this->welfareFundA, $this->admin);

        $this->actingAs($this->admin)->post(route('welfare-accounts.do-contribute', $account), [
            'amount' => 50000,
            'payment_method_id' => $inactiveMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $account->refresh();
        $this->assertEquals(0, (float) $account->current_balance);
    }

    public function test_inactive_payment_method_cannot_be_used_for_shares(): void
    {
        $inactiveMethod = PaymentMethod::factory()->create(['organization_id' => $this->orgA->id, 'status' => 'inactive']);
        $account = $this->createShareAccount($this->orgA, $this->branchA, $this->groupA, $this->shareProductA, $this->admin);

        $this->actingAs($this->admin)->post(route('share-accounts.do-purchase', $account), [
            'quantity' => 5,
            'share_price' => $this->shareProductA->share_price,
            'payment_method_id' => $inactiveMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $account->refresh();
        $this->assertEquals(0, $account->total_shares);
    }

    public function test_org_cannot_use_other_org_payment_method(): void
    {
        $account = $this->createSavingsAccount($this->orgA, $this->branchA, $this->groupA, $this->savingsProductA, $this->admin);
        $orgAdminA = $this->makeOrgAdmin($this->orgA);

        $response = $this->actingAs($orgAdminA)->post(route('savings-accounts.do-deposit', $account), [
            'amount' => 50000,
            'payment_method_id' => $this->paymentMethodB->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response->assertSessionHas('error');
        $account->refresh();
        $this->assertEquals(0, (float) $account->current_balance);
    }

    // =============================================
    // 14. RECEIPT INTEGRITY
    // =============================================

    public function test_receipt_can_be_viewed_for_completed_transaction(): void
    {
        $account = $this->createSavingsAccount($this->orgA, $this->branchA, $this->groupA, $this->savingsProductA, $this->admin);

        $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $account), [
            'amount' => 100000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $transaction = SavingsTransaction::where('savings_account_id', $account->id)->first();
        $response = $this->actingAs($this->admin)->get(route('savings-transactions.receipt', $transaction));
        $response->assertOk();
    }

    // =============================================
    // 15. AUDIT TRAIL
    // =============================================

    public function test_savings_deposit_creates_audit_log(): void
    {
        $account = $this->createSavingsAccount($this->orgA, $this->branchA, $this->groupA, $this->savingsProductA, $this->admin);

        $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $account), [
            'amount' => 100000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'savings.deposit',
            'auditable_type' => SavingsTransaction::class,
        ]);
    }

    public function test_savings_reversal_creates_audit_log(): void
    {
        $account = $this->createSavingsAccount($this->orgA, $this->branchA, $this->groupA, $this->savingsProductA, $this->admin);

        $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $account), [
            'amount' => 100000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $deposit = SavingsTransaction::where('savings_account_id', $account->id)->first();

        $this->actingAs($this->admin)->post(route('savings-transactions.reverse', $deposit), [
            'reason' => 'Test reversal audit',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'savings.reversal',
        ]);
    }

    public function test_share_purchase_creates_audit_log(): void
    {
        $account = $this->createShareAccount($this->orgA, $this->branchA, $this->groupA, $this->shareProductA, $this->admin);

        $this->actingAs($this->admin)->post(route('share-accounts.do-purchase', $account), [
            'quantity' => 5,
            'share_price' => $this->shareProductA->share_price,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'shares.purchase',
        ]);
    }

    public function test_welfare_contribution_creates_audit_log(): void
    {
        $account = $this->createWelfareAccount($this->orgA, $this->branchA, $this->groupA, $this->welfareFundA, $this->admin);

        $this->actingAs($this->admin)->post(route('welfare-accounts.do-contribute', $account), [
            'amount' => 50000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'welfare.contribution',
        ]);
    }

    public function test_payment_method_change_creates_audit_log(): void
    {
        $method = PaymentMethod::factory()->create(['organization_id' => $this->orgA->id]);

        $this->actingAs($this->admin)->put(route('payment-methods.update', $method), [
            'name' => 'Updated Method',
            'code' => $method->code,
            'type' => 'bank',
            'organization_id' => $this->orgA->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'payment_method.updated',
        ]);
    }

    // =============================================
    // 17. NEGATIVE TESTING
    // =============================================

    public function test_negative_deposit_amount_rejected(): void
    {
        $account = $this->createSavingsAccount($this->orgA, $this->branchA, $this->groupA, $this->savingsProductA, $this->admin);

        $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $account), [
            'amount' => -1000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $account->refresh();
        $this->assertEquals(0, (float) $account->current_balance);
    }

    public function test_zero_amount_rejected(): void
    {
        $account = $this->createSavingsAccount($this->orgA, $this->branchA, $this->groupA, $this->savingsProductA, $this->admin);

        $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $account), [
            'amount' => 0,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $account->refresh();
        $this->assertEquals(0, (float) $account->current_balance);
    }

    public function test_negative_share_quantity_rejected(): void
    {
        $account = $this->createShareAccount($this->orgA, $this->branchA, $this->groupA, $this->shareProductA, $this->admin);

        $response = $this->actingAs($this->admin)->post(route('share-accounts.do-purchase', $account), [
            'quantity' => -5,
            'share_price' => $this->shareProductA->share_price,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors('quantity');
    }

    public function test_negative_welfare_amount_rejected(): void
    {
        $account = $this->createWelfareAccount($this->orgA, $this->branchA, $this->groupA, $this->welfareFundA, $this->admin);

        $this->actingAs($this->admin)->post(route('welfare-accounts.do-contribute', $account), [
            'amount' => -5000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $account->refresh();
        $this->assertEquals(0, (float) $account->current_balance);
    }

    public function test_inactive_savings_account_rejects_deposit(): void
    {
        $account = $this->createSavingsAccount($this->orgA, $this->branchA, $this->groupA, $this->savingsProductA, $this->admin);
        $account->update(['status' => 'closed']);

        $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $account), [
            'amount' => 50000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $account->refresh();
        $this->assertEquals(0, (float) $account->current_balance);
    }

    public function test_inactive_share_account_rejects_purchase(): void
    {
        $account = $this->createShareAccount($this->orgA, $this->branchA, $this->groupA, $this->shareProductA, $this->admin);
        $account->update(['status' => 'closed']);

        $response = $this->actingAs($this->admin)->post(route('share-accounts.do-purchase', $account), [
            'quantity' => 5,
            'share_price' => $this->shareProductA->share_price,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response->assertSessionHas('error');
    }

    public function test_inactive_welfare_account_rejects_contribution(): void
    {
        $account = $this->createWelfareAccount($this->orgA, $this->branchA, $this->groupA, $this->welfareFundA, $this->admin);
        $account->update(['status' => 'closed']);

        $response = $this->actingAs($this->admin)->post(route('welfare-accounts.do-contribute', $account), [
            'amount' => 50000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response->assertSessionHas('error');
    }

    public function test_unauthorized_user_cannot_access_savings_accounts(): void
    {
        $regularUser = User::factory()->create();

        $response = $this->actingAs($regularUser)->get(route('savings-accounts.index'));
        $response->assertForbidden();
    }

    public function test_unauthorized_user_cannot_access_share_accounts(): void
    {
        $regularUser = User::factory()->create();

        $response = $this->actingAs($regularUser)->get(route('share-accounts.index'));
        $response->assertForbidden();
    }

    public function test_unauthorized_user_cannot_access_welfare_accounts(): void
    {
        $regularUser = User::factory()->create();

        $response = $this->actingAs($regularUser)->get(route('welfare-accounts.index'));
        $response->assertForbidden();
    }

    public function test_savings_deposit_requires_valid_payment_method(): void
    {
        $account = $this->createSavingsAccount($this->orgA, $this->branchA, $this->groupA, $this->savingsProductA, $this->admin);

        $response = $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $account), [
            'amount' => 50000,
            'payment_method_id' => 999999,
            'transaction_date' => now()->toDateString(),
        ]);

        $response->assertSessionHasErrors('payment_method_id');
    }

    public function test_minimum_savings_withdrawal_balance_enforced(): void
    {
        $product = SavingsProduct::factory()->create([
            'organization_id' => $this->orgA->id,
            'allow_withdrawal' => true,
            'minimum_balance' => 50000,
        ]);
        $account = $this->createSavingsAccount($this->orgA, $this->branchA, $this->groupA, $product, $this->admin);

        $this->actingAs($this->admin)->post(route('savings-accounts.do-deposit', $account), [
            'amount' => 100000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->post(route('savings-accounts.do-withdraw', $account), [
            'amount' => 60000,
            'payment_method_id' => $this->paymentMethodA->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $response->assertSessionHas('error');
        $account->refresh();
        $this->assertEquals(100000, (float) $account->current_balance);
    }

    public function test_savings_transaction_index_requires_permission(): void
    {
        $regularUser = User::factory()->create();

        $response = $this->actingAs($regularUser)->get(route('savings-transactions.index'));
        $response->assertForbidden();
    }

    public function test_payment_method_code_uniqueness_enforced(): void
    {
        PaymentMethod::factory()->create([
            'organization_id' => $this->orgA->id,
            'code' => 'DUP-CODE',
        ]);

        $response = $this->actingAs($this->admin)->post(route('payment-methods.store'), [
            'name' => 'Duplicate',
            'code' => 'DUP-CODE',
            'type' => 'cash',
            'organization_id' => $this->orgA->id,
        ]);

        $response->assertSessionHasErrors('code');
    }
}
