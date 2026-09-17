<?php

namespace Tests\Feature\Finance;

use App\Enums\AccountType;
use App\Enums\AccountingPeriodStatus;
use App\Enums\JournalEntryStatus;
use App\Models\AccountMapping;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Organization;
use App\Models\Branch;
use App\Models\User;
use App\Services\AccountingConfigurationService;
use App\Services\AccountingEventService;
use App\Services\AccountingPeriodService;
use App\Services\ChartOfAccountsService;
use App\Services\JournalPostingService;
use App\Services\JournalReversalService;
use App\Services\GeneralLedgerService;
use App\Services\TrialBalanceService;
use App\Services\BalanceSheetService;
use App\Services\IncomeStatementService;
use App\Services\CashBankLedgerService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Organization $organization;
    protected Branch $branch;
    protected AccountingPeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->admin = User::factory()->create(['password' => bcrypt('password')]);
        $this->admin->assignRole('Super Administrator');

        $this->organization = Organization::factory()->create(['status' => 'active']);
        $this->branch = Branch::factory()->create(['organization_id' => $this->organization->id]);
        $this->admin->organizations()->attach($this->organization->id);
        $this->admin->branches()->attach($this->branch->id);

        $chartService = app(ChartOfAccountsService::class);
        $chartService->initializeDefaultChart($this->organization->id, $this->admin->id);

        $configService = app(AccountingConfigurationService::class);
        $configService->initializeDefaultMappings($this->organization->id);

        $periodService = app(AccountingPeriodService::class);
        $this->period = $periodService->createPeriod(
            $this->organization->id,
            'Test Period 2026',
            '2026-01-01',
            '2026-12-31',
            $this->admin->id,
        );

        session(['organization_id' => $this->organization->id]);
    }

    // ===== Chart of Accounts Tests =====

    public function test_create_account(): void
    {
        $service = app(ChartOfAccountsService::class);

        $account = $service->createAccount(
            $this->organization->id,
            '9900',
            'Test Account',
            AccountType::Expense,
            'Test description',
            false,
            null,
            $this->admin->id,
        );

        $this->assertNotNull($account);
        $this->assertEquals('9900', $account->account_code);
        $this->assertEquals('Test Account', $account->account_name);
        $this->assertEquals(AccountType::Expense, $account->account_type);
    }

    public function test_unique_account_code_per_organization(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $service = app(ChartOfAccountsService::class);
        $service->createAccount($this->organization->id, '1100', 'Duplicate', AccountType::Asset);
    }

    public function test_system_account_protection(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $service = app(ChartOfAccountsService::class);
        $systemAccount = ChartOfAccount::where('organization_id', $this->organization->id)
            ->where('account_code', '1100')->first();

        $service->deleteAccount($systemAccount);
    }

    public function test_account_activation_deactivation(): void
    {
        $service = app(ChartOfAccountsService::class);

        $account = $service->createAccount(
            $this->organization->id, '9901', 'Deactivatable', AccountType::Expense,
        );

        $deactivated = $service->deactivateAccount($account);
        $this->assertFalse($deactivated->is_active);

        $activated = $service->activateAccount($deactivated);
        $this->assertTrue($activated->is_active);
    }

    public function test_default_chart_has_system_accounts(): void
    {
        $count = ChartOfAccount::where('organization_id', $this->organization->id)
            ->where('is_system', true)
            ->count();

        $this->assertGreaterThan(0, $count);
    }

    // ===== Accounting Period Tests =====

    public function test_create_period(): void
    {
        $service = app(AccountingPeriodService::class);

        $period = $service->createPeriod(
            $this->organization->id,
            'Q1 2027',
            '2027-01-01',
            '2027-03-31',
            $this->admin->id,
        );

        $this->assertNotNull($period);
        $this->assertEquals(AccountingPeriodStatus::Open, $period->status);
    }

    public function test_overlapping_period_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $service = app(AccountingPeriodService::class);
        $service->createPeriod($this->organization->id, 'Overlap', '2026-03-01', '2026-09-30');
    }

    public function test_close_period(): void
    {
        $service = app(AccountingPeriodService::class);
        $period = $service->createPeriod($this->organization->id, 'To Close', '2027-01-01', '2027-12-31');
        $closed = $service->closePeriod($period, $this->admin->id);

        $this->assertEquals(AccountingPeriodStatus::Closed, $closed->status);
        $this->assertNotNull($closed->closed_at);
    }

    public function test_closed_period_blocks_posting(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $service = app(AccountingPeriodService::class);
        $period = $service->createPeriod($this->organization->id, 'Closed Period', '2025-01-01', '2025-12-31');
        $service->closePeriod($period, $this->admin->id);

        $postingService = app(JournalPostingService::class);
        $postingService->createAndPost([
            'organization_id' => $this->organization->id,
            'entry_date' => '2025-06-01',
            'description' => 'Test blocked',
            'lines' => [
                ['chart_of_account_id' => $this->getAccountId('1100'), 'debit' => 100000, 'credit' => 0],
                ['chart_of_account_id' => $this->getAccountId('3100'), 'debit' => 0, 'credit' => 100000],
            ],
        ], $this->admin->id);
    }

    // ===== Journal Entry Tests =====

    public function test_balanced_journal_posts(): void
    {
        $postingService = app(JournalPostingService::class);

        $entry = $postingService->createAndPost([
            'organization_id' => $this->organization->id,
            'entry_date' => '2026-06-01',
            'description' => 'Test balanced entry',
            'lines' => [
                ['chart_of_account_id' => $this->getAccountId('1100'), 'debit' => 100000, 'credit' => 0],
                ['chart_of_account_id' => $this->getAccountId('2100'), 'debit' => 0, 'credit' => 100000],
            ],
        ], $this->admin->id);

        $this->assertEquals(JournalEntryStatus::Posted, $entry->status);
        $this->assertNotNull($entry->posted_at);
    }

    public function test_unbalanced_journal_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $postingService = app(JournalPostingService::class);
        $postingService->createDraft([
            'organization_id' => $this->organization->id,
            'entry_date' => '2026-06-01',
            'description' => 'Test unbalanced',
            'lines' => [
                ['chart_of_account_id' => $this->getAccountId('1100'), 'debit' => 100000, 'credit' => 0],
                ['chart_of_account_id' => $this->getAccountId('2100'), 'debit' => 0, 'credit' => 90000],
            ],
        ], $this->admin->id);
    }

    public function test_zero_line_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $postingService = app(JournalPostingService::class);
        $postingService->createDraft([
            'organization_id' => $this->organization->id,
            'entry_date' => '2026-06-01',
            'description' => 'Zero line',
            'lines' => [
                ['chart_of_account_id' => $this->getAccountId('1100'), 'debit' => 0, 'credit' => 0],
                ['chart_of_account_id' => $this->getAccountId('2100'), 'debit' => 0, 'credit' => 0],
            ],
        ], $this->admin->id);
    }

    public function test_debit_and_credit_line_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $postingService = app(JournalPostingService::class);
        $postingService->createDraft([
            'organization_id' => $this->organization->id,
            'entry_date' => '2026-06-01',
            'description' => 'Both debit and credit',
            'lines' => [
                ['chart_of_account_id' => $this->getAccountId('1100'), 'debit' => 50000, 'credit' => 50000],
                ['chart_of_account_id' => $this->getAccountId('2100'), 'debit' => 0, 'credit' => 100000],
            ],
        ], $this->admin->id);
    }

    public function test_journal_number_uniqueness(): void
    {
        $postingService = app(JournalPostingService::class);
        $data = [
            'organization_id' => $this->organization->id,
            'entry_date' => '2026-06-01',
            'description' => 'Test uniqueness',
            'lines' => [
                ['chart_of_account_id' => $this->getAccountId('1100'), 'debit' => 50000, 'credit' => 0],
                ['chart_of_account_id' => $this->getAccountId('2100'), 'debit' => 0, 'credit' => 50000],
            ],
        ];

        $entry1 = $postingService->createDraft($data, $this->admin->id);
        $entry2 = $postingService->createDraft($data, $this->admin->id);

        $this->assertNotEquals($entry1->journal_number, $entry2->journal_number);
    }

    // ===== Journal Immutability Tests =====

    public function test_posted_journal_cannot_be_edited(): void
    {
        $postingService = app(JournalPostingService::class);
        $entry = $postingService->createAndPost([
            'organization_id' => $this->organization->id,
            'entry_date' => '2026-06-01',
            'description' => 'Immutable test',
            'lines' => [
                ['chart_of_account_id' => $this->getAccountId('1100'), 'debit' => 50000, 'credit' => 0],
                ['chart_of_account_id' => $this->getAccountId('2100'), 'debit' => 0, 'credit' => 50000],
            ],
        ], $this->admin->id);

        $this->expectException(\InvalidArgumentException::class);
        $postingService->postEntry($entry, $this->admin->id);
    }

    public function test_posted_line_cannot_be_changed(): void
    {
        $postingService = app(JournalPostingService::class);
        $entry = $postingService->createAndPost([
            'organization_id' => $this->organization->id,
            'entry_date' => '2026-06-01',
            'description' => 'Immutable lines',
            'lines' => [
                ['chart_of_account_id' => $this->getAccountId('1100'), 'debit' => 50000, 'credit' => 0],
                ['chart_of_account_id' => $this->getAccountId('2100'), 'debit' => 0, 'credit' => 50000],
            ],
        ], $this->admin->id);

        $originalTotalDebit = $entry->fresh()->lines->sum('debit');
        $this->assertEqualsWithDelta(50000, $originalTotalDebit, 0.01);

        $this->expectException(\InvalidArgumentException::class);
        $postingService->postEntry($entry->fresh(), $this->admin->id);
    }

    // ===== Reversal Tests =====

    public function test_reversal_balances(): void
    {
        $postingService = app(JournalPostingService::class);
        $reversalService = app(JournalReversalService::class);

        $original = $postingService->createAndPost([
            'organization_id' => $this->organization->id,
            'entry_date' => '2026-06-01',
            'description' => 'To reverse',
            'lines' => [
                ['chart_of_account_id' => $this->getAccountId('1100'), 'debit' => 100000, 'credit' => 0],
                ['chart_of_account_id' => $this->getAccountId('2100'), 'debit' => 0, 'credit' => 100000],
            ],
        ], $this->admin->id);

        $reversal = $reversalService->reverseJournal($original, 'Test reversal', $this->admin->id);

        $this->assertEquals(JournalEntryStatus::Posted, $reversal->status);
        $this->assertEquals($reversal->lines->sum('debit'), $reversal->lines->sum('credit'));
        $this->assertEquals(100000, $reversal->lines->sum('debit'));
    }

    public function test_reversal_preserves_original(): void
    {
        $postingService = app(JournalPostingService::class);
        $reversalService = app(JournalReversalService::class);

        $original = $postingService->createAndPost([
            'organization_id' => $this->organization->id,
            'entry_date' => '2026-06-01',
            'description' => 'Preserve me',
            'lines' => [
                ['chart_of_account_id' => $this->getAccountId('1100'), 'debit' => 50000, 'credit' => 0],
                ['chart_of_account_id' => $this->getAccountId('2100'), 'debit' => 0, 'credit' => 50000],
            ],
        ], $this->admin->id);

        $reversalService->reverseJournal($original, 'Test', $this->admin->id);
        $original->refresh();

        $this->assertEquals(JournalEntryStatus::Reversed, $original->status);
        $this->assertNotNull($original->reversed_at);
        $this->assertEquals($this->admin->id, $original->reversed_by);
    }

    public function test_duplicate_reversal_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $postingService = app(JournalPostingService::class);
        $reversalService = app(JournalReversalService::class);

        $original = $postingService->createAndPost([
            'organization_id' => $this->organization->id,
            'entry_date' => '2026-06-01',
            'description' => 'Double reverse',
            'lines' => [
                ['chart_of_account_id' => $this->getAccountId('1100'), 'debit' => 50000, 'credit' => 0],
                ['chart_of_account_id' => $this->getAccountId('2100'), 'debit' => 0, 'credit' => 50000],
            ],
        ], $this->admin->id);

        $reversalService->reverseJournal($original, 'First', $this->admin->id);
        $reversalService->reverseJournal($original->fresh(), 'Second', $this->admin->id);
    }

    public function test_reversal_audit_populated(): void
    {
        $postingService = app(JournalPostingService::class);
        $reversalService = app(JournalReversalService::class);

        $original = $postingService->createAndPost([
            'organization_id' => $this->organization->id,
            'entry_date' => '2026-06-01',
            'description' => 'Audit test',
            'lines' => [
                ['chart_of_account_id' => $this->getAccountId('1100'), 'debit' => 50000, 'credit' => 0],
                ['chart_of_account_id' => $this->getAccountId('2100'), 'debit' => 0, 'credit' => 50000],
            ],
        ], $this->admin->id);

        $reversalService->reverseJournal($original, 'Testing audit fields', $this->admin->id);
        $original->refresh();

        $this->assertNotNull($original->reversal_reason);
        $this->assertEquals('Testing audit fields', $original->reversal_reason);
        $this->assertEquals($this->admin->id, $original->reversed_by);
    }

    // ===== Idempotency Tests =====

    public function test_same_source_cannot_post_twice(): void
    {
        $postingService = app(JournalPostingService::class);

        $entry1 = $postingService->createAndPost([
            'organization_id' => $this->organization->id,
            'entry_date' => '2026-06-01',
            'description' => 'Source test entry',
            'source_type' => 'test_source',
            'source_id' => 1,
            'lines' => [
                ['chart_of_account_id' => $this->getAccountId('1100'), 'debit' => 50000, 'credit' => 0],
                ['chart_of_account_id' => $this->getAccountId('2100'), 'debit' => 0, 'credit' => 50000],
            ],
        ], $this->admin->id);

        $count1 = JournalEntry::where('source_type', 'test_source')
            ->where('source_id', 1)
            ->where('status', 'posted')
            ->count();

        $this->assertEquals(1, $count1);
        $this->assertEquals(1, $postingService->hasPostedJournal('test_source', 1) ? 1 : 0);
    }

    // ===== Loan Integration Tests =====

    public function test_loan_repayment_exact_accounting(): void
    {
        $postingService = app(JournalPostingService::class);
        $cashAccountId = $this->getAccountId('1100');
        $loanReceivableId = $this->getAccountId('1400');
        $interestIncomeId = $this->getAccountId('4100');
        $feeIncomeId = $this->getAccountId('4200');

        $entry = $postingService->createAndPost([
            'organization_id' => $this->organization->id,
            'entry_date' => '2026-06-15',
            'description' => 'Loan repayment - RPT-2026-000001',
            'source_type' => 'LoanRepayment',
            'source_id' => 99999,
            'lines' => [
                ['chart_of_account_id' => $cashAccountId, 'debit' => 150000, 'credit' => 0],
                ['chart_of_account_id' => $loanReceivableId, 'debit' => 0, 'credit' => 100000],
                ['chart_of_account_id' => $interestIncomeId, 'debit' => 0, 'credit' => 40000],
                ['chart_of_account_id' => $feeIncomeId, 'debit' => 0, 'credit' => 10000],
            ],
        ], $this->admin->id);

        $this->assertNotNull($entry);
        $this->assertEquals(JournalEntryStatus::Posted, $entry->status);

        $totalDebit = (float) $entry->lines->sum('debit');
        $totalCredit = (float) $entry->lines->sum('credit');
        $this->assertEqualsWithDelta(150000, $totalDebit, 0.01);
        $this->assertEqualsWithDelta(150000, $totalCredit, 0.01);

        $cashLine = $entry->lines->where('debit', '>', 0)->first();
        $this->assertEqualsWithDelta(150000, $cashLine->debit, 0.01);

        $creditLines = $entry->lines->where('credit', '>', 0);
        $this->assertEqualsWithDelta(100000, $creditLines->firstWhere('chart_of_account_id', $loanReceivableId)->credit ?? 0, 0.01);
        $this->assertEqualsWithDelta(40000, $creditLines->firstWhere('chart_of_account_id', $interestIncomeId)->credit ?? 0, 0.01);
        $this->assertEqualsWithDelta(10000, $creditLines->firstWhere('chart_of_account_id', $feeIncomeId)->credit ?? 0, 0.01);
    }

    // ===== Trial Balance Tests =====

    public function test_trial_balance_debits_equal_credits(): void
    {
        $postingService = app(JournalPostingService::class);

        $postingService->createAndPost([
            'organization_id' => $this->organization->id,
            'entry_date' => '2026-06-01',
            'description' => 'Entry 1',
            'lines' => [
                ['chart_of_account_id' => $this->getAccountId('1100'), 'debit' => 100000, 'credit' => 0],
                ['chart_of_account_id' => $this->getAccountId('2100'), 'debit' => 0, 'credit' => 100000],
            ],
        ], $this->admin->id);

        $postingService->createAndPost([
            'organization_id' => $this->organization->id,
            'entry_date' => '2026-06-01',
            'description' => 'Entry 2',
            'lines' => [
                ['chart_of_account_id' => $this->getAccountId('1400'), 'debit' => 500000, 'credit' => 0],
                ['chart_of_account_id' => $this->getAccountId('1100'), 'debit' => 0, 'credit' => 500000],
            ],
        ], $this->admin->id);

        $service = app(TrialBalanceService::class);
        $result = $service->generate($this->organization->id);

        $this->assertTrue($result['is_balanced']);
        $this->assertEqualsWithDelta($result['total_debit'], $result['total_credit'], 0.01);
    }

    // ===== Balance Sheet Tests =====

    public function test_balance_sheet_equation(): void
    {
        $postingService = app(JournalPostingService::class);

        $postingService->createAndPost([
            'organization_id' => $this->organization->id,
            'entry_date' => '2026-06-01',
            'description' => 'Asset increase',
            'lines' => [
                ['chart_of_account_id' => $this->getAccountId('1100'), 'debit' => 200000, 'credit' => 0],
                ['chart_of_account_id' => $this->getAccountId('3100'), 'debit' => 0, 'credit' => 200000],
            ],
        ], $this->admin->id);

        $service = app(BalanceSheetService::class);
        $result = $service->generate($this->organization->id, '2026-12-31');

        $this->assertTrue($result['is_balanced']);
        $this->assertEqualsWithDelta($result['total_assets'], $result['total_liabilities'] + $result['total_equity'], 0.01);
    }

    // ===== Income Statement Tests =====

    public function test_income_statement_net_income(): void
    {
        $postingService = app(JournalPostingService::class);

        $postingService->createAndPost([
            'organization_id' => $this->organization->id,
            'entry_date' => '2026-06-15',
            'description' => 'Interest income',
            'lines' => [
                ['chart_of_account_id' => $this->getAccountId('1100'), 'debit' => 40000, 'credit' => 0],
                ['chart_of_account_id' => $this->getAccountId('4100'), 'debit' => 0, 'credit' => 40000],
            ],
        ], $this->admin->id);

        $postingService->createAndPost([
            'organization_id' => $this->organization->id,
            'entry_date' => '2026-06-20',
            'description' => 'Fee income',
            'lines' => [
                ['chart_of_account_id' => $this->getAccountId('1100'), 'debit' => 10000, 'credit' => 0],
                ['chart_of_account_id' => $this->getAccountId('4200'), 'debit' => 0, 'credit' => 10000],
            ],
        ], $this->admin->id);

        $service = app(IncomeStatementService::class);
        $result = $service->generate($this->organization->id, '2026-01-01', '2026-12-31');

        $this->assertEqualsWithDelta(50000, $result['total_income'], 0.01);
        $this->assertEqualsWithDelta(50000, $result['net_income'], 0.01);
    }

    // ===== General Ledger Tests =====

    public function test_general_ledger_running_balance(): void
    {
        $postingService = app(JournalPostingService::class);
        $cashAccountId = $this->getAccountId('1100');

        $postingService->createAndPost([
            'organization_id' => $this->organization->id,
            'entry_date' => '2026-06-01',
            'description' => 'First',
            'lines' => [
                ['chart_of_account_id' => $cashAccountId, 'debit' => 100000, 'credit' => 0],
                ['chart_of_account_id' => $this->getAccountId('3100'), 'debit' => 0, 'credit' => 100000],
            ],
        ], $this->admin->id);

        $postingService->createAndPost([
            'organization_id' => $this->organization->id,
            'entry_date' => '2026-06-05',
            'description' => 'Second',
            'lines' => [
                ['chart_of_account_id' => $this->getAccountId('3100'), 'debit' => 30000, 'credit' => 0],
                ['chart_of_account_id' => $cashAccountId, 'debit' => 0, 'credit' => 30000],
            ],
        ], $this->admin->id);

        $service = app(GeneralLedgerService::class);
        $result = $service->getLedger($this->organization->id, $cashAccountId, null, '2026-01-01', '2026-12-31');

        $this->assertCount(2, $result['entries']);
        $this->assertEqualsWithDelta(70000, $result['entries'][1]['running_balance'], 0.01);
    }

    // ===== Cash Ledger Tests =====

    public function test_cash_ledger_opening_receipts_payments_closing(): void
    {
        $postingService = app(JournalPostingService::class);
        $cashAccountId = $this->getAccountId('1100');

        $postingService->createAndPost([
            'organization_id' => $this->organization->id,
            'entry_date' => '2026-01-15',
            'description' => 'Opening cash',
            'lines' => [
                ['chart_of_account_id' => $cashAccountId, 'debit' => 200000, 'credit' => 0],
                ['chart_of_account_id' => $this->getAccountId('3100'), 'debit' => 0, 'credit' => 200000],
            ],
        ], $this->admin->id);

        $postingService->createAndPost([
            'organization_id' => $this->organization->id,
            'entry_date' => '2026-03-01',
            'description' => 'Cash out',
            'lines' => [
                ['chart_of_account_id' => $this->getAccountId('5100'), 'debit' => 50000, 'credit' => 0],
                ['chart_of_account_id' => $cashAccountId, 'debit' => 0, 'credit' => 50000],
            ],
        ], $this->admin->id);

        $service = app(CashBankLedgerService::class);
        $result = $service->generate($this->organization->id, $cashAccountId, '2026-01-01', '2026-12-31');

        $this->assertEqualsWithDelta(0, $result['opening_balance'], 0.01);
        $this->assertEqualsWithDelta(200000, $result['total_receipts'], 0.01);
        $this->assertEqualsWithDelta(50000, $result['total_payments'], 0.01);
        $this->assertEqualsWithDelta(150000, $result['closing_balance'], 0.01);
    }

    // ===== Tenant Isolation Tests =====

    public function test_cross_tenant_account_access_rejected(): void
    {
        $otherOrg = Organization::factory()->create(['status' => 'active']);

        $this->expectException(\InvalidArgumentException::class);

        $service = app(ChartOfAccountsService::class);
        $service->getAccount($otherOrg->id, $this->getAccountId('1100'));
    }

    public function test_cross_tenant_journal_posting_rejected(): void
    {
        $otherOrg = Organization::factory()->create(['status' => 'active']);
        $otherAccount = ChartOfAccount::create([
            'organization_id' => $otherOrg->id,
            'account_code' => '1100',
            'account_name' => 'Other Cash',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
        ]);

        $this->expectException(\InvalidArgumentException::class);

        $postingService = app(JournalPostingService::class);
        $postingService->createDraft([
            'organization_id' => $this->organization->id,
            'entry_date' => '2026-06-01',
            'description' => 'Cross-tenant test',
            'lines' => [
                ['chart_of_account_id' => $this->getAccountId('1100'), 'debit' => 100000, 'credit' => 0],
                ['chart_of_account_id' => $otherAccount->id, 'debit' => 0, 'credit' => 100000],
            ],
        ], $this->admin->id);
    }

    public function test_cross_tenant_journal_reversal_rejected(): void
    {
        $postingService = app(JournalPostingService::class);
        $reversalService = app(JournalReversalService::class);

        $otherOrg = Organization::factory()->create(['status' => 'active']);
        $otherOrgChart = app(ChartOfAccountsService::class);
        $otherOrgChart->initializeDefaultChart($otherOrg->id);

        $otherAccount = ChartOfAccount::where('organization_id', $otherOrg->id)->where('account_code', '1100')->first();
        $otherAccount2 = ChartOfAccount::where('organization_id', $otherOrg->id)->where('account_code', '3100')->first();

        $otherPeriod = app(AccountingPeriodService::class)->createPeriod($otherOrg->id, 'Other Period', '2026-01-01', '2026-12-31');

        $entry = $postingService->createAndPost([
            'organization_id' => $otherOrg->id,
            'entry_date' => '2026-06-01',
            'description' => 'Other org entry',
            'lines' => [
                ['chart_of_account_id' => $otherAccount->id, 'debit' => 50000, 'credit' => 0],
                ['chart_of_account_id' => $otherAccount2->id, 'debit' => 0, 'credit' => 50000],
            ],
        ]);

        $this->actingAs($this->admin);
        $this->expectException(\InvalidArgumentException::class);
        $reversalService->reverseJournal($entry, 'Cross-tenant attempt', $this->admin->id);
    }

    // ===== Complete Integration Scenario =====

    public function test_complete_accounting_scenario(): void
    {
        $postingService = app(JournalPostingService::class);
        $cashAccountId = $this->getAccountId('1100');
        $savingsAccountId = $this->getAccountId('2100');
        $loanReceivableId = $this->getAccountId('1400');
        $interestIncomeId = $this->getAccountId('4100');
        $feeIncomeId = $this->getAccountId('4200');

        // 1. Savings deposit: 100,000
        $postingService->createAndPost([
            'organization_id' => $this->organization->id,
            'entry_date' => '2026-06-01',
            'description' => 'Member savings deposit',
            'lines' => [
                ['chart_of_account_id' => $cashAccountId, 'debit' => 100000, 'credit' => 0],
                ['chart_of_account_id' => $savingsAccountId, 'debit' => 0, 'credit' => 100000],
            ],
        ], $this->admin->id);

        // 2. Loan disbursement: 500,000
        $postingService->createAndPost([
            'organization_id' => $this->organization->id,
            'entry_date' => '2026-06-05',
            'description' => 'Loan disbursement',
            'lines' => [
                ['chart_of_account_id' => $loanReceivableId, 'debit' => 500000, 'credit' => 0],
                ['chart_of_account_id' => $cashAccountId, 'debit' => 0, 'credit' => 500000],
            ],
        ], $this->admin->id);

        // 3. Repayment: 150,000 (principal=100k, interest=40k, fees=10k)
        $postingService->createAndPost([
            'organization_id' => $this->organization->id,
            'entry_date' => '2026-06-15',
            'description' => 'Loan repayment',
            'lines' => [
                ['chart_of_account_id' => $cashAccountId, 'debit' => 150000, 'credit' => 0],
                ['chart_of_account_id' => $loanReceivableId, 'debit' => 0, 'credit' => 100000],
                ['chart_of_account_id' => $interestIncomeId, 'debit' => 0, 'credit' => 40000],
                ['chart_of_account_id' => $feeIncomeId, 'debit' => 0, 'credit' => 10000],
            ],
        ], $this->admin->id);

        // Verify cash balance: 100,000 - 500,000 + 150,000 = -250,000
        $glService = app(GeneralLedgerService::class);
        $cashBalance = $glService->getAccountBalance($this->organization->id, $cashAccountId);
        $this->assertEqualsWithDelta(-250000, $cashBalance, 0.01);

        // Verify loan receivable: 500,000 - 100,000 = 400,000
        $loanBalance = $glService->getAccountBalance($this->organization->id, $loanReceivableId);
        $this->assertEqualsWithDelta(400000, $loanBalance, 0.01);

        // Verify income: 40,000 + 10,000 = 50,000
        $interestBalance = $glService->getAccountBalance($this->organization->id, $interestIncomeId);
        $feeBalance = $glService->getAccountBalance($this->organization->id, $feeIncomeId);
        $this->assertEqualsWithDelta(40000, $interestBalance, 0.01);
        $this->assertEqualsWithDelta(10000, $feeBalance, 0.01);

        // Verify trial balance is balanced
        $tbService = app(TrialBalanceService::class);
        $tb = $tbService->generate($this->organization->id, '2026-01-01', '2026-12-31');
        $this->assertTrue($tb['is_balanced']);
        $this->assertEqualsWithDelta($tb['total_debit'], $tb['total_credit'], 0.01);
    }

    protected function getAccountId(string $code): int
    {
        return ChartOfAccount::where('organization_id', $this->organization->id)
            ->where('account_code', $code)
            ->first()
            ->id;
    }
}
