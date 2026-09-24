<?php

namespace Tests\Feature\Finance;

use App\Enums\AccountingPeriodStatus;
use App\Enums\InterestMethod;
use App\Enums\JournalEntryStatus;
use App\Enums\LoanDisbursementStatus;
use App\Enums\LoanScheduleInstallmentStatus;
use App\Enums\LoanStatus;
use App\Enums\RepaymentFrequency;
use App\Enums\SavingsAccountStatus;
use App\Enums\ShareAccountStatus;
use App\Enums\WelfareAccountStatus;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanDisbursement;
use App\Models\LoanPlan;
use App\Models\LoanRepayment;
use App\Models\LoanRepaymentSchedule;
use App\Models\Member;
use App\Models\Organization;
use App\Models\PaymentMethod;
use App\Models\SavingsAccount;
use App\Models\SavingsProduct;
use App\Models\SavingsTransaction;
use App\Models\ShareAccount;
use App\Models\ShareProduct;
use App\Models\User;
use App\Models\VicobaGroup;
use App\Models\WelfareAccount;
use App\Models\WelfareFund;
use App\Services\AccountingConfigurationService;
use App\Services\AccountingEventService;
use App\Services\AccountingPeriodService;
use App\Services\ChartOfAccountsService;
use App\Services\GeneralLedgerService;
use App\Services\JournalPostingService;
use App\Services\JournalReversalService;
use App\Services\LoanDisbursementService;
use App\Services\LoanRepaymentService;
use App\Services\SavingsTransactionService;
use App\Services\ShareTransactionService;
use App\Services\WelfareTransactionService;
use App\Services\TrialBalanceService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\HasAccountingSetup;

class AccountingIntegrationTest extends TestCase
{
    use RefreshDatabase, HasAccountingSetup;

    private User $admin;
    private Organization $organization;
    private Branch $branch;
    private Member $member;
    private VicobaGroup $group;
    private SavingsProduct $savingsProduct;
    private SavingsAccount $savingsAccount;
    private ShareProduct $shareProduct;
    private ShareAccount $shareAccount;
    private WelfareFund $welfareFund;
    private WelfareAccount $welfareAccount;
    private PaymentMethod $paymentMethod;
    private LoanPlan $loanPlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::where('email', 'admin@financepro.co.tz')->first();
        $this->organization = Organization::factory()->create(['status' => 'active']);
        $this->branch = Branch::factory()->create(['organization_id' => $this->organization->id]);
        $this->admin->organizations()->attach($this->organization->id);
        $this->admin->branches()->attach($this->branch->id);

        $this->setUpAccountingFor($this->admin, $this->organization);

        $this->group = VicobaGroup::factory()->create(['branch_id' => $this->branch->id]);
        $this->member = Member::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
        ]);

        $this->paymentMethod = PaymentMethod::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => 'active',
            'type' => 'cash',
        ]);

        $this->savingsProduct = SavingsProduct::factory()->create([
            'organization_id' => $this->organization->id,
            'allow_withdrawal' => true,
            'minimum_balance' => 0,
        ]);

        $this->savingsAccount = SavingsAccount::createQuietly([
            'member_id' => $this->member->id,
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'savings_product_id' => $this->savingsProduct->id,
            'account_number' => 'SAV-INT-0001',
            'current_balance' => 0,
            'opening_date' => now(),
            'status' => SavingsAccountStatus::Active,
            'created_by' => $this->admin->id,
        ]);

        $this->shareProduct = ShareProduct::factory()->create([
            'organization_id' => $this->organization->id,
            'share_price' => 10000,
        ]);

        $this->shareAccount = ShareAccount::createQuietly([
            'member_id' => $this->member->id,
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'share_product_id' => $this->shareProduct->id,
            'account_number' => 'SHR-INT-0001',
            'total_shares' => 0,
            'total_value' => 0,
            'status' => ShareAccountStatus::Active,
            'created_by' => $this->admin->id,
        ]);

        $this->welfareFund = WelfareFund::factory()->create([
            'organization_id' => $this->organization->id,
        ]);

        $this->welfareAccount = WelfareAccount::createQuietly([
            'member_id' => $this->member->id,
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'vicoba_group_id' => $this->group->id,
            'welfare_fund_id' => $this->welfareFund->id,
            'account_number' => 'WFA-INT-0001',
            'current_balance' => 0,
            'status' => WelfareAccountStatus::Active,
            'created_by' => $this->admin->id,
        ]);

        $this->loanPlan = LoanPlan::factory()->create([
            'organization_id' => $this->organization->id,
            'interest_rate' => 24.0,
            'interest_method' => 'flat',
            'repayment_frequency' => 'monthly',
            'grace_period' => 0,
            'processing_fee' => 0,
            'insurance_fee' => 0,
        ]);

        auth()->login($this->admin);
    }

    private function findJournalBySource(int $sourceId): ?JournalEntry
    {
        $entries = JournalEntry::where('source_id', $sourceId)->get();

        return $entries->filter(fn ($e) => $e->status === JournalEntryStatus::Posted)->last();
    }

    private function findAllJournalsBySource(int $sourceId): \Illuminate\Database\Eloquent\Collection
    {
        return JournalEntry::where('source_id', $sourceId)->get();
    }

    private function getAccountId(string $code): int
    {
        return ChartOfAccount::where('organization_id', $this->organization->id)
            ->where('account_code', $code)
            ->first()
            ->id;
    }

    // ===== 1. Savings Deposit Creates Exactly One Journal =====

    public function test_savings_deposit_creates_one_journal(): void
    {
        $service = app(SavingsTransactionService::class);

        $transaction = $service->deposit($this->savingsAccount, [
            'amount' => 100000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $journal = $this->findJournalBySource($transaction->id);
        $this->assertNotNull($journal);
        $this->assertEquals(JournalEntryStatus::Posted, $journal->status);

        $count = $this->findAllJournalsBySource($transaction->id)
            ->where('status', JournalEntryStatus::Posted)
            ->count();
        $this->assertEquals(1, $count);
    }

    // ===== 2. Savings Deposit Exact Amounts =====

    public function test_savings_deposit_exact_amounts(): void
    {
        $service = app(SavingsTransactionService::class);
        $cashAccountId = $this->getAccountId('1100');
        $savingsAccountId = $this->getAccountId('2100');

        $transaction = $service->deposit($this->savingsAccount, [
            'amount' => 100000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $journal = $this->findJournalBySource($transaction->id);
        $this->assertNotNull($journal);

        $debitLine = $journal->lines->where('debit', '>', 0)->first();
        $creditLine = $journal->lines->where('credit', '>', 0)->first();

        $this->assertEquals($cashAccountId, $debitLine->chart_of_account_id);
        $this->assertEqualsWithDelta(100000, $debitLine->debit, 0.01);

        $this->assertEquals($savingsAccountId, $creditLine->chart_of_account_id);
        $this->assertEqualsWithDelta(100000, $creditLine->credit, 0.01);

        $this->assertEqualsWithDelta($journal->lines->sum('debit'), $journal->lines->sum('credit'), 0.01);
    }

    // ===== 3. Savings Withdrawal Creates Journal =====

    public function test_savings_withdrawal_creates_journal(): void
    {
        $service = app(SavingsTransactionService::class);

        $service->deposit($this->savingsAccount, [
            'amount' => 200000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $withdrawal = $service->withdraw($this->savingsAccount, [
            'amount' => 50000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $journal = $this->findJournalBySource($withdrawal->id);

        $this->assertNotNull($journal);
        $this->assertEqualsWithDelta(50000, $journal->lines->sum('debit'), 0.01);
        $this->assertEqualsWithDelta(50000, $journal->lines->sum('credit'), 0.01);

        $debitLine = $journal->lines->where('debit', '>', 0)->first();
        $this->assertEquals($this->getAccountId('2100'), $debitLine->chart_of_account_id);
    }

    // ===== 4. Savings Deposit Idempotency =====

    public function test_savings_deposit_idempotent(): void
    {
        $service = app(SavingsTransactionService::class);

        $t1 = $service->deposit($this->savingsAccount, [
            'amount' => 100000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $t2 = $service->deposit($this->savingsAccount, [
            'amount' => 50000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $journalCount1 = $this->findAllJournalsBySource($t1->id)
            ->where('status', JournalEntryStatus::Posted)
            ->count();
        $this->assertEquals(1, $journalCount1);

        $journalCount2 = $this->findAllJournalsBySource($t2->id)
            ->where('status', JournalEntryStatus::Posted)
            ->count();
        $this->assertEquals(1, $journalCount2);

        $this->assertNotEquals($t1->id, $t2->id);
    }

    // ===== 5. Savings Deposit Accounting Failure Rolls Back =====

    public function test_savings_deposit_accounting_failure_rolls_back(): void
    {
        $periodService = app(AccountingPeriodService::class);
        $period = $periodService->createPeriod($this->organization->id, 'Closed', '2025-01-01', '2025-12-31', $this->admin->id);
        $periodService->closePeriod($period, $this->admin->id);

        $balanceBefore = (float) $this->savingsAccount->fresh()->current_balance;

        $service = app(SavingsTransactionService::class);

        try {
            $service->deposit($this->savingsAccount, [
                'amount' => 100000,
                'payment_method_id' => $this->paymentMethod->id,
                'transaction_date' => '2025-06-15',
            ]);
            $this->fail('Expected exception was not thrown.');
        } catch (\Throwable $e) {
            $this->assertDatabaseCount('savings_transactions', 0);
            $this->assertDatabaseCount('journal_entries', 0);
        }

        $this->assertEqualsWithDelta($balanceBefore, (float) $this->savingsAccount->fresh()->current_balance, 0.01);
    }

    // ===== 6. Share Purchase Creates Journal =====

    public function test_share_purchase_creates_journal(): void
    {
        $service = app(ShareTransactionService::class);

        $transaction = $service->purchase($this->shareAccount, [
            'quantity' => 5,
            'share_price' => 10000,
            'amount' => 50000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $journal = $this->findJournalBySource($transaction->id);
        $this->assertNotNull($journal);

        $debitLine = $journal->lines->where('debit', '>', 0)->first();
        $creditLine = $journal->lines->where('credit', '>', 0)->first();

        $this->assertEquals($this->getAccountId('1100'), $debitLine->chart_of_account_id);
        $this->assertEqualsWithDelta(50000, $debitLine->debit, 0.01);

        $this->assertEquals($this->getAccountId('2300'), $creditLine->chart_of_account_id);
        $this->assertEqualsWithDelta(50000, $creditLine->credit, 0.01);
    }

    // ===== 7. Share Redemption Creates Journal =====

    public function test_share_redemption_creates_journal(): void
    {
        $service = app(ShareTransactionService::class);

        $service->purchase($this->shareAccount, [
            'quantity' => 10,
            'share_price' => 10000,
            'amount' => 100000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $redemption = $service->redeem($this->shareAccount, [
            'quantity' => 3,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $journal = $this->findJournalBySource($redemption->id);
        $this->assertNotNull($journal);

        $debitLine = $journal->lines->where('debit', '>', 0)->first();
        $this->assertEquals($this->getAccountId('2300'), $debitLine->chart_of_account_id);
        $this->assertEqualsWithDelta(30000, $debitLine->debit, 0.01);
    }

    // ===== 8. Welfare Contribution Creates Journal =====

    public function test_welfare_contribution_creates_journal(): void
    {
        $service = app(WelfareTransactionService::class);

        $transaction = $service->contribute($this->welfareAccount, [
            'amount' => 50000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $journal = $this->findJournalBySource($transaction->id);
        $this->assertNotNull($journal);

        $debitLine = $journal->lines->where('debit', '>', 0)->first();
        $creditLine = $journal->lines->where('credit', '>', 0)->first();

        $this->assertEquals($this->getAccountId('1100'), $debitLine->chart_of_account_id);
        $this->assertEqualsWithDelta(50000, $debitLine->debit, 0.01);

        $this->assertEquals($this->getAccountId('2200'), $creditLine->chart_of_account_id);
        $this->assertEqualsWithDelta(50000, $creditLine->credit, 0.01);
    }

    // ===== 9. Welfare Benefit Creates Journal =====

    public function test_welfare_benefit_creates_journal(): void
    {
        $service = app(WelfareTransactionService::class);

        $service->contribute($this->welfareAccount, [
            'amount' => 100000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $benefit = $service->benefit($this->welfareAccount, [
            'amount' => 30000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $journal = $this->findJournalBySource($benefit->id);
        $this->assertNotNull($journal);

        $debitLine = $journal->lines->where('debit', '>', 0)->first();
        $this->assertEquals($this->getAccountId('2200'), $debitLine->chart_of_account_id);
        $this->assertEqualsWithDelta(30000, $debitLine->debit, 0.01);
    }

    // ===== 10. Savings Reversal Reverses Journal =====

    public function test_savings_reversal_reverses_journal(): void
    {
        $service = app(SavingsTransactionService::class);

        $deposit = $service->deposit($this->savingsAccount, [
            'amount' => 100000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $originalJournal = $this->findJournalBySource($deposit->id);
        $this->assertNotNull($originalJournal);

        $service->reverse($deposit, 'Test reversal');

        $originalJournal->refresh();
        $this->assertEquals(JournalEntryStatus::Reversed, $originalJournal->status);
        $this->assertNotNull($originalJournal->reversed_at);

        $reversalJournal = $this->findJournalBySource($deposit->id);
        $this->assertNotNull($reversalJournal);
        $this->assertStringContainsString('Reversal of', $reversalJournal->description);

        $this->assertEqualsWithDelta(
            $originalJournal->lines->sum('debit'),
            $reversalJournal->lines->sum('credit'),
            0.01
        );
        $this->assertEqualsWithDelta(
            $originalJournal->lines->sum('credit'),
            $reversalJournal->lines->sum('debit'),
            0.01
        );
    }

    // ===== 11. Loan Disbursement Creates Journal =====

    public function test_loan_disbursement_creates_journal(): void
    {
        $application = LoanApplication::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->loanPlan->id,
            'requested_amount' => 500000,
            'requested_term' => 12,
            'repayment_frequency' => RepaymentFrequency::Monthly,
            'status' => 'approved',
        ]);

        $disbursementService = app(LoanDisbursementService::class);
        $loan = $disbursementService->createLoanFromApplication($application);

        $disbursement = $disbursementService->createDisbursementRecord(
            $loan,
            500000,
            'cash',
            $this->paymentMethod->id,
            null,
            null,
        );

        $disbursementService->confirmDisbursement($disbursement, $this->admin);

        $journal = $this->findJournalBySource($disbursement->id);
        $this->assertNotNull($journal);
        $this->assertGreaterThanOrEqual(2, $journal->lines->count());

        $totalDebit = (float) $journal->lines->sum('debit');
        $totalCredit = (float) $journal->lines->sum('credit');
        $this->assertEqualsWithDelta($totalDebit, $totalCredit, 0.01);
        $this->assertEqualsWithDelta(500000, $totalDebit, 0.01);
    }

    // ===== 12. Rejected Disbursement Creates No Journal =====

    public function test_rejected_disbursement_creates_no_journal(): void
    {
        $application = LoanApplication::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->loanPlan->id,
            'requested_amount' => 500000,
            'requested_term' => 12,
            'repayment_frequency' => RepaymentFrequency::Monthly,
            'status' => 'approved',
        ]);

        $disbursementService = app(LoanDisbursementService::class);
        $loan = $disbursementService->createLoanFromApplication($application);

        $disbursement = $disbursementService->createDisbursementRecord(
            $loan, 500000, 'cash', $this->paymentMethod->id, null, null,
        );

        $disbursementService->rejectDisbursement($disbursement, 'Rejected for testing', $this->admin);

        $journal = $this->findJournalBySource($disbursement->id);
        $this->assertNull($journal);
    }

    // ===== 13. Loan Repayment Creates Journal =====

    public function test_loan_repayment_creates_journal(): void
    {
        $loan = $this->createActiveLoan();
        $service = app(LoanRepaymentService::class);

        $repayment = $service->postRepayment(
            $loan,
            10333.33,
            now()->toDateString(),
            'cash',
            null,
            null,
            null,
            null,
        );

        $journal = $this->findJournalBySource($repayment->id);
        $this->assertNotNull($journal);

        $totalDebit = (float) $journal->lines->sum('debit');
        $totalCredit = (float) $journal->lines->sum('credit');
        $this->assertEqualsWithDelta($totalDebit, $totalCredit, 0.01);
        $this->assertEqualsWithDelta(10333.33, $totalDebit, 0.01);
    }

    // ===== 14. Loan Repayment Exact Allocation =====

    public function test_loan_repayment_exact_allocation(): void
    {
        $loan = $this->createActiveLoan();
        $service = app(LoanRepaymentService::class);

        $repayment = $service->postRepayment(
            $loan,
            10333.33,
            now()->toDateString(),
            'cash',
            null,
            null,
            null,
            null,
        );

        $journal = $this->findJournalBySource($repayment->id);
        $this->assertNotNull($journal);

        $cashLine = $journal->lines->where('debit', '>', 0)->first();
        $this->assertEquals($this->getAccountId('1100'), $cashLine->chart_of_account_id);
        $this->assertEqualsWithDelta(10333.33, $cashLine->debit, 0.01);

        $creditLines = $journal->lines->where('credit', '>', 0);
        $totalCredit = (float) $creditLines->sum('credit');
        $this->assertEqualsWithDelta(10333.33, $totalCredit, 0.01);
    }

    // ===== 15. Overpayment Preserved in Journal =====

    public function test_overpayment_preserved_in_journal(): void
    {
        $loan = $this->createActiveLoan();
        $service = app(LoanRepaymentService::class);

        $totalDue = $loan->repaymentSchedule()->sum('total_amount');

        $repayment = $service->postRepayment(
            $loan,
            $totalDue + 5000,
            now()->toDateString(),
            'cash',
            null,
            null,
            null,
            null,
        );

        $journal = $this->findJournalBySource($repayment->id);
        $this->assertNotNull($journal);

        $cashLine = $journal->lines->where('debit', '>', 0)->first();
        $this->assertEqualsWithDelta($totalDue + 5000, $cashLine->debit, 0.01);

        $totalCredit = (float) $journal->lines->where('credit', '>', 0)->sum('credit');
        $this->assertEqualsWithDelta($totalDue + 5000, $totalCredit, 0.01);
    }

    // ===== 16. Repayment Reversal Reverses Journal =====

    public function test_repayment_reversal_reverses_journal(): void
    {
        $loan = $this->createActiveLoan();
        $service = app(LoanRepaymentService::class);

        $repayment = $service->postRepayment(
            $loan,
            10333.33,
            now()->toDateString(),
            'cash',
            null,
            null,
            null,
            null,
        );

        $originalJournal = $this->findJournalBySource($repayment->id);
        $this->assertNotNull($originalJournal);

        $service->reverseRepayment($repayment, 'Test reversal');

        $originalJournal->refresh();
        $this->assertEquals(JournalEntryStatus::Reversed, $originalJournal->status);

        $reversalJournal = $this->findJournalBySource($repayment->id);
        $this->assertNotNull($reversalJournal);

        $this->assertEqualsWithDelta(
            $originalJournal->lines->sum('debit'),
            $reversalJournal->lines->sum('credit'),
            0.01
        );
        $this->assertEqualsWithDelta(
            $originalJournal->lines->sum('credit'),
            $reversalJournal->lines->sum('debit'),
            0.01
        );
    }

    // ===== 17. Double Reversal Blocked =====

    public function test_double_reversal_blocked(): void
    {
        $loan = $this->createActiveLoan();
        $service = app(LoanRepaymentService::class);

        $repayment = $service->postRepayment(
            $loan,
            10333.33,
            now()->toDateString(),
            'cash',
            null,
            null,
            null,
            null,
        );

        $service->reverseRepayment($repayment, 'First reversal');

        $this->expectException(\InvalidArgumentException::class);
        $service->reverseRepayment($repayment->fresh(), 'Second reversal');
    }

    // ===== 18. Tenant Isolation =====

    public function test_tenant_isolation(): void
    {
        $otherOrg = Organization::factory()->create(['status' => 'active']);
        $otherBranch = Branch::factory()->create(['organization_id' => $otherOrg->id]);
        $chartService = app(ChartOfAccountsService::class);
        $chartService->initializeDefaultChart($otherOrg->id, $this->admin->id);
        $configService = app(AccountingConfigurationService::class);
        $configService->initializeDefaultMappings($otherOrg->id);
        $periodService = app(AccountingPeriodService::class);
        $periodService->createPeriod($otherOrg->id, 'Other 2026', '2026-01-01', '2026-12-31', $this->admin->id);

        $otherMember = Member::factory()->create(['organization_id' => $otherOrg->id, 'branch_id' => $otherBranch->id]);
        $otherSavingsProduct = SavingsProduct::factory()->create(['organization_id' => $otherOrg->id]);
        $otherGroup = VicobaGroup::factory()->create(['branch_id' => $otherBranch->id]);
        $otherAccount = SavingsAccount::createQuietly([
            'member_id' => $otherMember->id,
            'organization_id' => $otherOrg->id,
            'branch_id' => $otherBranch->id,
            'vicoba_group_id' => $otherGroup->id,
            'savings_product_id' => $otherSavingsProduct->id,
            'account_number' => 'SAV-OTHER-0002',
            'current_balance' => 0,
            'opening_date' => now(),
            'status' => SavingsAccountStatus::Active,
        ]);
        $otherPm = PaymentMethod::factory()->create(['organization_id' => $otherOrg->id, 'status' => 'active']);

        $service = app(SavingsTransactionService::class);

        $deposit = $service->deposit($otherAccount, [
            'amount' => 100000,
            'payment_method_id' => $otherPm->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $journal = $this->findJournalBySource($deposit->id);

        $this->assertNotNull($journal);
        $this->assertEquals($otherOrg->id, $journal->organization_id);
        $this->assertNotEquals($this->organization->id, $journal->organization_id);
    }

    // ===== 19. Closed Period Rejection =====

    public function test_closed_period_rejection(): void
    {
        $periodService = app(AccountingPeriodService::class);
        $period = $periodService->createPeriod($this->organization->id, 'Closed', '2025-01-01', '2025-12-31', $this->admin->id);
        $periodService->closePeriod($period, $this->admin->id);

        $service = app(SavingsTransactionService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->deposit($this->savingsAccount, [
            'amount' => 100000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => '2025-06-15',
        ]);
    }

    // ===== 20. Exact Reconciliation =====

    public function test_exact_reconciliation(): void
    {
        $service = app(SavingsTransactionService::class);

        $service->deposit($this->savingsAccount, [
            'amount' => 100000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $this->assertEqualsWithDelta(100000, (float) $this->savingsAccount->fresh()->current_balance, 0.01);

        $glService = app(GeneralLedgerService::class);
        $savingsBalance = $glService->getAccountBalance($this->organization->id, $this->getAccountId('2100'));
        $this->assertEqualsWithDelta(100000, $savingsBalance, 0.01);
    }

    // ===== 21. Trial Balance Stays Balanced =====

    public function test_trial_balance_stays_balanced(): void
    {
        $savingsService = app(SavingsTransactionService::class);

        $savingsService->deposit($this->savingsAccount, [
            'amount' => 100000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $shareService = app(ShareTransactionService::class);
        $shareService->purchase($this->shareAccount, [
            'quantity' => 5,
            'share_price' => 10000,
            'amount' => 50000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $tbService = app(TrialBalanceService::class);
        $tb = $tbService->generate($this->organization->id);

        $this->assertTrue($tb['is_balanced']);
        $this->assertEqualsWithDelta($tb['total_debit'], $tb['total_credit'], 0.01);
    }

    // ===== 22. Full Acceptance Scenario =====

    public function test_full_acceptance_scenario(): void
    {
        $savingsService = app(SavingsTransactionService::class);
        $glService = app(GeneralLedgerService::class);
        $tbService = app(TrialBalanceService::class);

        // Step 1: Savings deposit 100,000
        $savingsService->deposit($this->savingsAccount, [
            'amount' => 100000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $savingsBalance = $glService->getAccountBalance($this->organization->id, $this->getAccountId('2100'));
        $this->assertEqualsWithDelta(100000, $savingsBalance, 0.01);

        // Step 2: Loan disbursement 500,000
        $application = LoanApplication::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->loanPlan->id,
            'requested_amount' => 500000,
            'requested_term' => 12,
            'repayment_frequency' => RepaymentFrequency::Monthly,
            'status' => 'approved',
        ]);

        $disbursementService = app(LoanDisbursementService::class);
        $loan = $disbursementService->createLoanFromApplication($application);
        $disbursement = $disbursementService->createDisbursementRecord(
            $loan, 500000, 'cash', $this->paymentMethod->id, null, null,
        );
        $disbursementService->confirmDisbursement($disbursement, $this->admin);

        $loan = $loan->fresh();

        $loanReceivable = $glService->getAccountBalance($this->organization->id, $this->getAccountId('1400'));
        $this->assertEqualsWithDelta(500000, $loanReceivable, 0.01);

        // Step 3: Loan repayment 150,000 (simplified — schedule may differ)
        $repaymentService = app(LoanRepaymentService::class);
        $totalDue = $loan->repaymentSchedule()->sum('total_amount');
        $payAmount = min(150000, $totalDue);

        $repayment = $repaymentService->postRepayment(
            $loan,
            $payAmount,
            now()->toDateString(),
            'cash',
            null,
            null,
            null,
            null,
        );

        $this->assertNotNull($repayment);

        $repaymentJournal = $this->findJournalBySource($repayment->id);
        $this->assertNotNull($repaymentJournal);
        $this->assertEqualsWithDelta(
            $repaymentJournal->lines->sum('debit'),
            $repaymentJournal->lines->sum('credit'),
            0.01
        );

        // Step 4: Verify trial balance
        $tb = $tbService->generate($this->organization->id);
        $this->assertTrue($tb['is_balanced']);

        // Step 5: Reverse repayment
        $repaymentService->reverseRepayment($repayment, 'Test reversal');

        $repaymentJournal->refresh();
        $this->assertEquals(JournalEntryStatus::Reversed, $repaymentJournal->status);

        $reversalJournal = $this->findJournalBySource($repayment->id);
        $this->assertNotNull($reversalJournal);

        // Step 6: Verify trial balance still balanced
        $tb = $tbService->generate($this->organization->id);
        $this->assertTrue($tb['is_balanced']);
    }

    // ===== 23. Branch Isolation =====

    public function test_branch_preserved_in_journal(): void
    {
        $service = app(SavingsTransactionService::class);

        $transaction = $service->deposit($this->savingsAccount, [
            'amount' => 100000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $journal = $this->findJournalBySource($transaction->id);

        $this->assertNotNull($journal);
        $this->assertEquals($this->branch->id, $journal->branch_id);
    }

    // ===== 24. Source Traceability =====

    public function test_source_traceability(): void
    {
        $service = app(SavingsTransactionService::class);

        $transaction = $service->deposit($this->savingsAccount, [
            'amount' => 100000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $journal = $this->findJournalBySource($transaction->id);

        $this->assertNotNull($journal);
        $this->assertEquals(SavingsTransaction::class, $journal->source_type);
        $this->assertEquals($transaction->id, $journal->source_id);
        $this->assertStringContainsString($transaction->transaction_number, $journal->description);
    }

    // ===== 25. Accounting Period from Transaction Date =====

    public function test_accounting_period_from_transaction_date(): void
    {
        $service = app(SavingsTransactionService::class);

        $transaction = $service->deposit($this->savingsAccount, [
            'amount' => 100000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $journal = $this->findJournalBySource($transaction->id);

        $this->assertNotNull($journal);
        $this->assertNotNull($journal->accounting_period_id);

        $period = AccountingPeriod::find($journal->accounting_period_id);
        $this->assertNotNull($period);
        $this->assertTrue($period->isOpen());
        $this->assertEquals($this->organization->id, $period->organization_id);
    }

    // ===== 26. Share Reversal Reverses Journal =====

    public function test_share_reversal_reverses_journal(): void
    {
        $service = app(ShareTransactionService::class);

        $purchase = $service->purchase($this->shareAccount, [
            'quantity' => 5,
            'share_price' => 10000,
            'amount' => 50000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $originalJournal = $this->findJournalBySource($purchase->id);
        $this->assertNotNull($originalJournal);

        $service->reverse($purchase, 'Test reversal');

        $originalJournal->refresh();
        $this->assertEquals(JournalEntryStatus::Reversed, $originalJournal->status);

        $reversalJournal = $this->findJournalBySource($purchase->id);
        $this->assertNotNull($reversalJournal);
    }

    // ===== 27. Welfare Reversal Reverses Journal =====

    public function test_welfare_reversal_reverses_journal(): void
    {
        $service = app(WelfareTransactionService::class);

        $contribution = $service->contribute($this->welfareAccount, [
            'amount' => 50000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $originalJournal = $this->findJournalBySource($contribution->id);
        $this->assertNotNull($originalJournal);

        $service->reverse($contribution, 'Test reversal');

        $originalJournal->refresh();
        $this->assertEquals(JournalEntryStatus::Reversed, $originalJournal->status);

        $reversalJournal = $this->findJournalBySource($contribution->id);
        $this->assertNotNull($reversalJournal);
    }

    // ===== 28. Accounting Failure Rolls Back Savings Deposit =====

    public function test_accounting_failure_rolls_back_savings_deposit(): void
    {
        $otherOrg = Organization::factory()->create(['status' => 'active']);
        $otherBranch = Branch::factory()->create(['organization_id' => $otherOrg->id]);
        $chartService = app(ChartOfAccountsService::class);
        $chartService->initializeDefaultChart($otherOrg->id, $this->admin->id);

        $otherMember = Member::factory()->create(['organization_id' => $otherOrg->id, 'branch_id' => $otherBranch->id]);
        $otherSavingsProduct = SavingsProduct::factory()->create(['organization_id' => $otherOrg->id]);
        $otherGroup = VicobaGroup::factory()->create(['branch_id' => $otherBranch->id]);
        $otherAccount = SavingsAccount::createQuietly([
            'member_id' => $otherMember->id,
            'organization_id' => $otherOrg->id,
            'branch_id' => $otherBranch->id,
            'vicoba_group_id' => $otherGroup->id,
            'savings_product_id' => $otherSavingsProduct->id,
            'account_number' => 'SAV-FAIL-0001',
            'current_balance' => 0,
            'opening_date' => now(),
            'status' => SavingsAccountStatus::Active,
        ]);

        $service = app(SavingsTransactionService::class);

        try {
            $service->deposit($otherAccount, [
                'amount' => 100000,
                'payment_method_id' => $this->paymentMethod->id,
                'transaction_date' => now()->toDateString(),
            ]);
            $this->fail('Expected exception was not thrown.');
        } catch (\Throwable $e) {
            $this->assertDatabaseCount('savings_transactions', 0);
        }
    }

    // ===== 29. Multiple Transactions Create Multiple Journals =====

    public function test_multiple_transactions_create_multiple_journals(): void
    {
        $service = app(SavingsTransactionService::class);

        $t1 = $service->deposit($this->savingsAccount, [
            'amount' => 50000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $t2 = $service->deposit($this->savingsAccount, [
            'amount' => 30000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $journal1 = $this->findJournalBySource($t1->id);
        $journal2 = $this->findJournalBySource($t2->id);

        $this->assertNotNull($journal1);
        $this->assertNotNull($journal2);
        $this->assertNotEquals($journal1->id, $journal2->id);

        $this->assertEqualsWithDelta(50000, $journal1->lines->sum('debit'), 0.01);
        $this->assertEqualsWithDelta(30000, $journal2->lines->sum('debit'), 0.01);
    }

    // ===== 30. Savings Withdrawal Accounting Failure Rolls Back =====

    public function test_savings_withdrawal_accounting_failure_rolls_back(): void
    {
        $service = app(SavingsTransactionService::class);

        $service->deposit($this->savingsAccount, [
            'amount' => 200000,
            'payment_method_id' => $this->paymentMethod->id,
            'transaction_date' => now()->toDateString(),
        ]);

        $this->assertEqualsWithDelta(200000, (float) $this->savingsAccount->fresh()->current_balance, 0.01);

        $otherOrg = Organization::factory()->create(['status' => 'active']);
        $chartService = app(ChartOfAccountsService::class);
        $chartService->initializeDefaultChart($otherOrg->id, $this->admin->id);

        $otherMember = Member::factory()->create(['organization_id' => $otherOrg->id]);
        $otherSavingsProduct = SavingsProduct::factory()->create(['organization_id' => $otherOrg->id]);
        $otherBranch = Branch::factory()->create(['organization_id' => $otherOrg->id]);
        $otherGroup = VicobaGroup::factory()->create(['branch_id' => $otherBranch->id]);
        $otherAccount = SavingsAccount::createQuietly([
            'member_id' => $otherMember->id,
            'organization_id' => $otherOrg->id,
            'branch_id' => $otherBranch->id,
            'vicoba_group_id' => $otherGroup->id,
            'savings_product_id' => $otherSavingsProduct->id,
            'account_number' => 'SAV-WITHDRAW-FAIL',
            'current_balance' => 200000,
            'opening_date' => now(),
            'status' => SavingsAccountStatus::Active,
        ]);

        try {
            $service->withdraw($otherAccount, [
                'amount' => 50000,
                'payment_method_id' => $this->paymentMethod->id,
                'transaction_date' => now()->toDateString(),
            ]);
            $this->fail('Expected exception was not thrown.');
        } catch (\Throwable $e) {
            $this->assertDatabaseCount('savings_transactions', 1);
        }
    }

    // ===== Helpers =====

    private function createActiveLoan(): Loan
    {
        $application = LoanApplication::factory()->create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->loanPlan->id,
            'requested_amount' => 100000,
            'requested_term' => 12,
            'repayment_frequency' => RepaymentFrequency::Monthly,
            'status' => 'approved',
        ]);

        $loan = Loan::create([
            'organization_id' => $this->organization->id,
            'branch_id' => $this->branch->id,
            'member_id' => $this->member->id,
            'loan_plan_id' => $this->loanPlan->id,
            'loan_application_id' => $application->id,
            'loan_number' => 'LN-2026-000001',
            'principal_amount' => 100000,
            'disbursed_amount' => 100000,
            'interest_rate' => 24.0,
            'interest_method' => 'flat',
            'term_months' => 12,
            'repayment_frequency' => 'monthly',
            'total_interest' => 24000,
            'total_amount' => 124000,
            'processing_fee' => 0,
            'insurance_fee' => 0,
            'amount_paid' => 0,
            'outstanding_balance' => 100000,
            'grace_period' => 0,
            'status' => LoanStatus::Active,
            'disbursement_date' => now()->subMonths(1),
            'total_installments' => 12,
        ]);

        for ($i = 1; $i <= 12; $i++) {
            LoanRepaymentSchedule::create([
                'loan_id' => $loan->id,
                'organization_id' => $this->organization->id,
                'installment_number' => $i,
                'due_date' => now()->subMonth()->addMonths($i),
                'principal_amount' => 8333.33,
                'interest_amount' => 2000,
                'total_amount' => 10333.33,
                'amount_paid' => 0,
                'outstanding_amount' => 10333.33,
                'running_balance' => 100000 - (8333.33 * $i),
                'status' => LoanScheduleInstallmentStatus::Pending,
                'days_overdue' => 0,
                'late_fee' => 0,
            ]);
        }

        return $loan;
    }
}
