<?php

namespace App\AI\Reporting\Services;

use App\AI\DTOs\AiContextData;
use App\AI\FinancialIntelligence\Services\AccountingIntelligenceService;
use App\AI\FinancialIntelligence\Services\CollectionIntelligenceService;
use App\AI\FinancialIntelligence\Services\DelinquencyIntelligenceService;
use App\AI\FinancialIntelligence\Services\FinancialTrendService;
use App\AI\FinancialIntelligence\Services\ParIntelligenceService;
use App\AI\FinancialIntelligence\Services\PortfolioIntelligenceService;
use App\AI\ProactiveIntelligence\Services\ProactiveInsightService;
use App\Enums\FinancialTransactionStatus;
use App\Enums\GuarantorStatus;
use App\Enums\JournalEntryStatus;
use App\Enums\LoanApplicationStatus;
use App\Enums\LoanCollateralStatus;
use App\Enums\LoanStatus;
use App\Enums\ReportDatumClassification;
use App\Enums\SavingsAccountStatus;
use App\Enums\SavingsTransactionType;
use App\Models\AiInsight;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanApplicationCollateral;
use App\Models\LoanApplicationGuarantor;
use App\Models\LoanPlan;
use App\Models\LoanRepayment;
use App\Models\LoanRepaymentSchedule;
use App\Models\Member;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Authoritative, deterministic report data for the Phase 12.0 layer.
 *
 * Every figure produced here is either read directly from an authoritative
 * FinancePro record or delegated to an existing reporting service (Phase 11.7
 * financial intelligence, the core accounting report services). Nothing is
 * recomputed from scratch and nothing is inferred by the AI. Portfolio-level
 * position is always current as of "now" (a stock, not a flow); flow figures
 * are always confined to the resolved reporting period.
 */
class ReportDataService
{
    public function __construct(
        private readonly PortfolioIntelligenceService $portfolio,
        private readonly ParIntelligenceService $par,
        private readonly DelinquencyIntelligenceService $delinquency,
        private readonly CollectionIntelligenceService $collections,
        private readonly FinancialTrendService $trends,
        private readonly AccountingIntelligenceService $accounting,
        private readonly ProactiveInsightService $insights,
    ) {}

    /**
     * Current portfolio position: members, active loans, outstanding principal,
     * maturities and composition. Reused verbatim by the executive and loan
     * reports so both always agree.
     *
     * @param  int[]  $organizationIds
     * @param  int[]  $branchIds
     * @return array<string, mixed>
     */
    public function portfolioPosition(array $organizationIds, array $branchIds = []): array
    {
        $portfolio = $this->portfolio->summarize($organizationIds, $branchIds);
        $members = $this->memberCounts($organizationIds, $branchIds);

        return [
            'currency' => $portfolio->currency,
            'members_total' => $members['total'],
            'members_active' => $members['active'],
            'active_loans' => $portfolio->activeLoansCount,
            'total_outstanding' => $portfolio->totalOutstanding,
            'total_principal_disbursed' => $portfolio->totalPrincipalDisbursed,
            'maturing_within_30_days' => $portfolio->maturingWithin30Days,
            'maturing_within_60_days' => $portfolio->maturingWithin60Days,
            'composition_by_plan' => $portfolio->compositionByPlan,
            'source' => 'PortfolioIntelligenceService',
        ];
    }

    /**
     * Portfolio at risk and the delinquency profile for the current position.
     *
     * @param  int[]  $organizationIds
     * @param  int[]  $branchIds
     * @return array<string, mixed>
     */
    public function riskPosition(array $organizationIds, array $branchIds = []): array
    {
        $par = $this->par->summarize($organizationIds, $branchIds);
        $delinquency = $this->delinquency->profile($organizationIds, $branchIds);

        return [
            'currency' => $par->currency,
            'par_threshold_days' => $par->parThresholdDays,
            'par_rate' => $par->parRateOverThreshold,
            'total_principal_outstanding' => $par->totalPrincipalOutstanding,
            'buckets' => $par->buckets,
            'delinquent_loans' => $delinquency->delinquentLoansCount,
            'delinquent_principal' => $delinquency->delinquentPrincipalOutstanding,
            'min_dpd' => $delinquency->minDpd,
            'max_dpd' => $delinquency->maxDpd,
            'average_dpd' => $delinquency->averageDpd,
            'source' => 'ParIntelligenceService + DelinquencyIntelligenceService',
        ];
    }

    /**
     * Expected versus actual collections for one window, using the existing
     * Phase 11.7 collection service (schedule amounts due versus posted
     * repayments).
     *
     * @param  int[]  $organizationIds
     * @param  int[]  $branchIds
     * @return array<string, mixed>
     */
    public function collectionWindow(array $organizationIds, array $branchIds, string $start, string $end): array
    {
        $collection = $this->collections->summarize($organizationIds, $branchIds, $start, $end);

        return [
            'currency' => $collection->currency,
            'start_date' => $collection->startDate,
            'end_date' => $collection->endDate,
            'total_due' => $collection->totalDue,
            'total_collected' => $collection->totalCollected,
            'collection_rate' => $collection->collectionRate,
            'posted_count' => $collection->postedCount,
            'reversed_count' => $collection->reversedCount,
            'reversed_amount' => $collection->reversedAmount,
            'overdue_amount' => $this->overdueOutstanding($organizationIds, $branchIds),
            'source' => 'CollectionIntelligenceService',
        ];
    }

    /**
     * Outstanding installment amounts already past their due date — the
     * authoritative overdue exposure at the report's data-through date.
     *
     * @param  int[]  $organizationIds
     * @param  int[]  $branchIds
     */
    public function overdueOutstanding(array $organizationIds, array $branchIds = []): float
    {
        if ($organizationIds === []) {
            return 0.0;
        }

        return round((float) LoanRepaymentSchedule::whereIn('organization_id', $organizationIds)
            ->where('outstanding_amount', '>', 0)
            ->where('due_date', '<', CarbonImmutable::now()->startOfDay())
            ->when($branchIds !== [], fn ($query) => $query->whereHas('loan', fn ($loan) => $loan->whereIn('branch_id', $branchIds)))
            ->sum('outstanding_amount'), 2);
    }

    /**
     * Period flows: disbursed, collected and reversed inside the window.
     *
     * @param  int[]  $organizationIds
     * @param  int[]  $branchIds
     * @return array<string, mixed>
     */
    public function loanFlows(array $organizationIds, array $branchIds, string $start, string $end): array
    {
        if ($organizationIds === []) {
            return [
                'disbursed' => 0.0, 'disbursed_count' => 0, 'collected' => 0.0,
                'collected_count' => 0, 'reversed' => 0.0, 'reversed_count' => 0,
            ];
        }

        $loans = Loan::whereIn('organization_id', $organizationIds)
            ->whereBetween('disbursement_date', [$start, $end])
            ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds));

        $repayments = LoanRepayment::whereIn('organization_id', $organizationIds)
            ->whereBetween('payment_date', [$start, $end])
            ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds));

        return [
            'disbursed' => round((float) (clone $loans)->sum('disbursed_amount'), 2),
            'disbursed_count' => (int) (clone $loans)->count(),
            'collected' => round((float) (clone $repayments)->where('status', 'posted')->sum('amount'), 2),
            'collected_count' => (int) (clone $repayments)->where('status', 'posted')->count(),
            'reversed' => round((float) (clone $repayments)->where('status', 'reversed')->sum('amount'), 2),
            'reversed_count' => (int) (clone $repayments)->where('status', 'reversed')->count(),
        ];
    }

    /**
     * Loan counts by lifecycle status at the report's as-of date.
     *
     * @param  int[]  $organizationIds
     * @param  int[]  $branchIds
     * @return array<string, int>
     */
    public function loanStatusCounts(array $organizationIds, array $branchIds = [], ?string $asOf = null): array
    {
        if ($organizationIds === []) {
            return [];
        }

        $counts = [];

        $query = Loan::whereIn('organization_id', $organizationIds)
            ->when($branchIds !== [], fn ($q) => $q->whereIn('branch_id', $branchIds))
            ->when($asOf !== null, fn ($q) => $q->where('created_at', '<=', CarbonImmutable::parse($asOf)->endOfDay()));

        foreach ($query->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status') as $status => $aggregate) {
            $counts[(string) $status] = (int) $aggregate;
        }

        return $counts;
    }

    /**
     * Application pipeline counts for a window.
     *
     * @param  int[]  $organizationIds
     * @param  int[]  $branchIds
     * @return array<string, int>
     */
    public function applicationPipeline(array $organizationIds, array $branchIds, string $start, string $end): array
    {
        if ($organizationIds === []) {
            return ['submitted' => 0, 'approved' => 0, 'rejected' => 0, 'total' => 0];
        }

        $base = LoanApplication::whereIn('organization_id', $organizationIds)
            ->whereBetween('application_date', [$start, $end])
            ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds));

        return [
            'total' => (int) (clone $base)->count(),
            'submitted' => (int) (clone $base)->where('status', LoanApplicationStatus::Submitted->value)->count(),
            'under_review' => (int) (clone $base)->where('status', LoanApplicationStatus::UnderReview->value)->count(),
            'approved' => (int) (clone $base)->where('status', LoanApplicationStatus::Approved->value)->count(),
            'rejected' => (int) (clone $base)->where('status', LoanApplicationStatus::Rejected->value)->count(),
        ];
    }

    /**
     * Operational workflow backlog: pending applications, guarantor reviews and
     * collateral reviews with their age in days.
     *
     * @param  int[]  $organizationIds
     * @param  int[]  $branchIds
     * @return array<string, mixed>
     */
    public function operationalBacklog(array $organizationIds, array $branchIds = []): array
    {
        if ($organizationIds === []) {
            return [
                'pending_applications' => 0, 'pending_application_oldest_days' => null,
                'pending_guarantors' => 0, 'pending_collaterals' => 0,
                'pending_collateral_oldest_days' => null,
            ];
        }

        $today = CarbonImmutable::now()->startOfDay();

        $applications = LoanApplication::whereIn('organization_id', $organizationIds)
            ->whereIn('status', [
                LoanApplicationStatus::Submitted->value,
                LoanApplicationStatus::UnderReview->value,
            ])
            ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->get(['application_date', 'created_at']);

        $oldestApplication = $applications
            ->map(fn (LoanApplication $application) => (int) $application->application_date->diffInDays($today))
            ->max();

        $guarantors = LoanApplicationGuarantor::query()
            ->whereHas('application', fn ($query) => $query->whereIn('organization_id', $organizationIds))
            ->where('status', GuarantorStatus::Pending->value)
            ->when($branchIds !== [], fn ($query) => $query->whereHas('application', fn ($app) => $app->whereIn('branch_id', $branchIds)))
            ->count();

        $collaterals = LoanApplicationCollateral::query()
            ->whereHas('application', fn ($query) => $query->whereIn('organization_id', $organizationIds))
            ->whereIn('status', [LoanCollateralStatus::Pending->value, LoanCollateralStatus::UnderReview->value])
            ->when($branchIds !== [], fn ($query) => $query->whereHas('application', fn ($app) => $app->whereIn('branch_id', $branchIds)))
            ->get(['created_at']);

        return [
            'pending_applications' => $applications->count(),
            'pending_application_oldest_days' => $oldestApplication === null ? null : (int) $oldestApplication,
            'pending_guarantors' => (int) $guarantors,
            'pending_collaterals' => $collaterals->count(),
            'pending_collateral_oldest_days' => $collaterals->isEmpty()
                ? null
                : (int) $collaterals->max(fn ($collateral) => (int) $collateral->created_at->diffInDays($today)),
        ];
    }

    /**
     * Cash and savings position. Savings aggregates come from the stored
     * authoritative account balances; the liquid position is delegated to the
     * existing accounting service so the report never recomputes it.
     *
     * @param  int[]  $organizationIds
     * @param  int[]  $branchIds
     * @return array<string, mixed>
     */
    public function position(array $organizationIds, array $branchIds = []): array
    {
        if ($organizationIds === []) {
            return [
                'savings_balance' => 0.0, 'active_savings_accounts' => 0,
                'savings_deposits' => 0.0, 'liquid_position' => 0.0,
            ];
        }

        $accounts = SavingsAccount::whereIn('organization_id', $organizationIds)
            ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds));

        return [
            'savings_balance' => round((float) (clone $accounts)->sum('current_balance'), 2),
            'active_savings_accounts' => (int) (clone $accounts)
                ->where('status', SavingsAccountStatus::Active->value)
                ->count(),
            'savings_deposits' => round((float) SavingsTransaction::whereIn('organization_id', $organizationIds)
                ->where('transaction_type', SavingsTransactionType::Deposit->value)
                ->where('status', FinancialTransactionStatus::Completed->value)
                ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds))
                ->sum('amount'), 2),
            'liquid_position' => $this->accounting->summarize($organizationIds)->liquidPosition,
        ];
    }

    /**
     * Draft journal entries and the unbalanced condition, read-only.
     *
     * @param  int[]  $organizationIds
     * @param  int[]  $branchIds
     * @return array<string, mixed>
     */
    public function accountingPosition(array $organizationIds, array $branchIds = []): array
    {
        $summary = $this->accounting->summarize($organizationIds);

        $drafts = JournalEntry::whereIn('organization_id', $organizationIds)
            ->where('status', JournalEntryStatus::Draft->value)
            ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->count();

        return [
            'total_income' => $summary->totalIncome,
            'total_expenses' => $summary->totalExpenses,
            'net_income' => $summary->netIncome,
            'liquid_position' => $summary->liquidPosition,
            'all_balanced' => $summary->allBalanced,
            'draft_journals' => $drafts,
            'organization_breakdown' => $summary->organizationBreakdown,
            'source' => 'AccountingIntelligenceService',
        ];
    }

    /**
     * Monthly trend series over the trailing window, for the historical view.
     *
     * @param  int[]  $organizationIds
     * @param  int[]  $branchIds
     * @return array<string, mixed>
     */
    public function trendSeries(array $organizationIds, array $branchIds, int $months = 12): array
    {
        $trend = $this->trends->monthly($organizationIds, $branchIds, $months);

        return [
            'currency' => $trend->currency,
            'months' => $trend->months,
            'series' => $trend->trend,
            'total_disbursed' => $trend->totalDisbursed,
            'total_collected' => $trend->totalCollected,
            'source' => 'FinancialTrendService',
        ];
    }

    /**
     * Loan-plan distribution of the current active book.
     *
     * @param  int[]  $organizationIds
     * @param  int[]  $branchIds
     * @return array<int, array<string, mixed>>
     */
    public function planDistribution(array $organizationIds, array $branchIds = []): array
    {
        if ($organizationIds === []) {
            return [];
        }

        return Loan::query()
            ->select('loans.id', 'loans.loan_plan_id', 'loans.outstanding_balance')
            ->whereIn('loans.organization_id', $organizationIds)
            ->where('loans.status', LoanStatus::Active->value)
            ->where('loans.outstanding_balance', '>', 0)
            ->when($branchIds !== [], fn ($query) => $query->whereIn('loans.branch_id', $branchIds))
            ->join('loan_plans', 'loan_plans.id', '=', 'loans.loan_plan_id')
            ->groupBy('loan_plans.id', 'loan_plans.name')
            ->select('loan_plans.name as plan')
            ->selectRaw('COUNT(*) as loans_count, SUM(loans.outstanding_balance) as outstanding')
            ->orderByDesc('outstanding')
            ->get()
            ->map(fn ($row) => [
                'plan' => (string) $row->plan,
                'loans_count' => (int) $row->loans_count,
                'outstanding' => round((float) $row->outstanding, 2),
            ])
            ->all();
    }

    /**
     * @param  int[]  $organizationIds
     * @param  int[]  $branchIds
     * @return array{total: int, active: int}
     */
    public function memberCounts(array $organizationIds, array $branchIds = []): array
    {
        if ($organizationIds === []) {
            return ['total' => 0, 'active' => 0];
        }

        $base = Member::whereIn('organization_id', $organizationIds)
            ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds));

        return [
            'total' => (int) (clone $base)->count(),
            'active' => (int) (clone $base)->active()->count(),
        ];
    }

    /**
     * The open Phase 11.9 insights for the trusted context, read straight from
     * ProactiveInsightService::forDashboard(). The reporting layer never queries
     * the insight table itself, never regenerates insights and never changes one
     * — a report only presents what Phase 11.9 already decided is open.
     *
     * @return Collection<int, AiInsight>
     */
    public function insightsFor(AiContextData $context): Collection
    {
        return $this->insights->forDashboard($context);
    }

    /**
     * Helpers shared with the report builder so the classification vocabulary
     * is never restated per report type.
     */
    public function classification(): ReportDatumClassification
    {
        return ReportDatumClassification::Fact;
    }

    /**
     * Active loan plans for the loan-plan distribution table.
     *
     * @return Collection<int, LoanPlan>
     */
    public function loanPlans(): Collection
    {
        return LoanPlan::orderBy('name')->get();
    }

    /**
     * The explicit, honest reason a comparison could not be made.
     */
    public function noComparisonNote(): string
    {
        return 'No previous comparable period data — the comparison could not be calculated.';
    }
}
