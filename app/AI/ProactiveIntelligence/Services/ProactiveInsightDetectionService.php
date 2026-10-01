<?php

namespace App\AI\ProactiveIntelligence\Services;

use App\AI\PredictiveIntelligence\DataQualityService;
use App\AI\PredictiveIntelligence\Services\CashflowForecastService;
use App\AI\PredictiveIntelligence\Services\PredictiveIntelligenceService;
use App\Enums\FinancialTransactionStatus;
use App\Enums\JournalEntryStatus;
use App\Enums\LoanApplicationStatus;
use App\Enums\LoanScheduleInstallmentStatus;
use App\Enums\LoanStatus;
use App\Enums\PredictiveInsightStatus;
use App\Enums\PredictiveInsightType;
use App\Enums\ProactiveInsightSeverity;
use App\Enums\ProactiveInsightSource;
use App\Enums\ProactiveInsightType as ProactiveType;
use App\Enums\SavingsTransactionType;
use App\Models\AiPrediction;
use App\Models\JournalEntry;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanRepayment;
use App\Models\LoanRepaymentSchedule;
use App\Models\SavingsTransaction;
use App\Services\LoanDelinquencyService;
use App\Services\TrialBalanceService;
use Carbon\Carbon;
use Throwable;

/**
 * Deterministic rule-based proactive detection (Phase 11.9).
 *
 * Every rule is a pure, read-only computation over authoritative FinancePro
 * records with configurable thresholds, exactly like the Phase 11.7 anomaly
 * rules — but scoped to advisory forward signals (overdue exposure, maturity
 * pressure, portfolio risk, cash-flow, collections, savings, accounting,
 * operational gaps and predictive outlooks). Detection never creates,
 * resolves, or changes a financial record; it only proposes insights that a
 * human resolves. Scope is always derived from the caller's trusted
 * organization/branch list.
 */
class ProactiveInsightDetectionService
{
    public function __construct(
        private readonly LoanDelinquencyService $delinquency,
        private readonly TrialBalanceService $trialBalance,
        private readonly DataQualityService $quality,
        private readonly CashflowForecastService $cashflow,
        private readonly PredictiveIntelligenceService $predictive,
    ) {}

    /**
     * Run every enabled rule across one organization (optionally narrow by
     * branch) and return the raw insight payloads, ordered by severity.
     * Persistence and deduplication happen in ProactiveInsightService.
     *
     * @param  int[]  $branchIds
     * @return array<int, array<string, mixed>>
     */
    public function detect(int $organizationId, array $branchIds = []): array
    {
        if (! (bool) config('proactive-intelligence.enabled', true)) {
            return [];
        }

        $raws = [
            ...$this->overdueLoans($organizationId, $branchIds),
            ...$this->maturityPressure($organizationId, $branchIds),
            ...$this->portfolioPar($organizationId, $branchIds),
            ...$this->portfolioConcentration($organizationId, $branchIds),
            ...$this->cashflowShortfall($organizationId, $branchIds),
            ...$this->collectionDecline($organizationId, $branchIds),
            ...$this->savingsDecline($organizationId, $branchIds),
            ...$this->accountingImbalance($organizationId, $branchIds),
            ...$this->operationalGap($organizationId, $branchIds),
            ...$this->predictiveOutlookRisk($organizationId),
        ];

        usort(
            $raws,
            fn (array $a, array $b) => ProactiveInsightSeverity::from($b['severity'])->priority()
                <=> ProactiveInsightSeverity::from($a['severity'])->priority(),
        );

        return array_slice($raws, 0, (int) config('proactive-intelligence.max_per_organization', 50));
    }

    /**
     * Rule: loans past due. Advisory escalations tied to how far past due the
     * oldest outstanding installment is.
     *
     * @param  int[]  $branchIds
     * @return array<int, array<string, mixed>>
     */
    protected function overdueLoans(int $organizationId, array $branchIds): array
    {
        $minimum = max(1, (int) config('proactive-intelligence.overdue_min_days', 7));
        $warning = (int) config('proactive-intelligence.overdue_warning_days', 30);
        $critical = (int) config('proactive-intelligence.overdue_critical_days', 90);

        $loans = Loan::where('organization_id', $organizationId)
            ->where('status', LoanStatus::Active->value)
            ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->whereHas('repaymentSchedule', function ($query) {
                $query->where('outstanding_amount', '>', 0)
                    ->where('due_date', '<', Carbon::today())
                    ->whereIn('status', [
                        LoanScheduleInstallmentStatus::Pending->value,
                        LoanScheduleInstallmentStatus::Partial->value,
                        LoanScheduleInstallmentStatus::Overdue->value,
                    ]);
            })
            ->with(['repaymentSchedule' => function ($query) {
                $query->where('outstanding_amount', '>', 0)
                    ->where('due_date', '<', Carbon::today())
                    ->orderBy('due_date');
            }, 'branch'])
            ->get();

        $insights = [];

        foreach ($loans as $loan) {
            $dpd = $this->delinquency->getDaysPastDue($loan);

            if ($dpd < $minimum) {
                continue;
            }

            $due = $loan->repaymentSchedule->first();

            if (! $due || ! $due->due_date) {
                continue;
            }

            $severity = match (true) {
                $dpd >= $critical => ProactiveInsightSeverity::Critical,
                $dpd >= $warning => ProactiveInsightSeverity::Warning,
                default => ProactiveInsightSeverity::Notice,
            };

            $insights[] = $this->payload(
                rule: 'overdue_loan',
                type: ProactiveType::OverdueLoan->value,
                severity: $severity->value,
                title: 'Loan overdue',
                summary: sprintf(
                    'Loan %s of TZS %s has been overdue for %d day(s) (oldest due %s).',
                    $loan->loan_number,
                    number_format((float) $loan->outstanding_balance, 2),
                    $dpd,
                    $due->due_date->toDateString(),
                ),
                recommendation: 'Contact the member and agree a catch-up plan; review the next collection cycle.',
                sourceType: ProactiveInsightSource::Loan->value,
                sourceId: (int) $loan->id,
                branchId: $loan->branch_id,
                objectType: Loan::class,
                objectId: (int) $loan->id,
                periodStart: $due->due_date->toDateString(),
                periodEnd: $due->due_date->toDateString(),
                metadata: [
                    'days_past_due' => $dpd,
                    'outstanding_amount' => (float) $loan->outstanding_balance,
                    'loan_number' => $loan->loan_number,
                    'currency' => (string) config('proactive-intelligence.currency', 'TZS'),
                ],
            );
        }

        return $insights;
    }

    /**
     * Rule: a cluster of loans maturing inside the same short window.
     *
     * @param  int[]  $branchIds
     * @return array<int, array<string, mixed>>
     */
    protected function maturityPressure(int $organizationId, array $branchIds): array
    {
        $window = max(1, (int) config('proactive-intelligence.maturity_window_days', 30));
        $concentration = max(1, (int) config('proactive-intelligence.maturity_concentration', 3));

        $loans = Loan::where('organization_id', $organizationId)
            ->where('status', LoanStatus::Active->value)
            ->where('outstanding_balance', '>', 0)
            ->whereNotNull('maturity_date')
            ->whereBetween('maturity_date', [Carbon::today(), Carbon::today()->copy()->addDays($window)])
            ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->get();

        if ($loans->count() < $concentration) {
            return [];
        }

        $principal = (float) $loans->sum('outstanding_balance');

        return [$this->payload(
            rule: 'maturity_pressure',
            type: ProactiveType::MaturityPressure->value,
            severity: ProactiveInsightSeverity::Notice->value,
            title: 'Maturity concentration',
            summary: sprintf(
                '%d active loan(s) (TZS %s) mature within the next %d day(s); plan refinancing or collection capacity.',
                $loans->count(),
                number_format($principal, 2),
                $window,
            ),
            recommendation: 'Review the maturing portfolio and line up refinancing or settlement conversations before maturity.',
            sourceType: ProactiveInsightSource::Organization->value,
            sourceId: $organizationId,
            branchId: null,
            objectType: null,
            objectId: null,
            periodStart: Carbon::today()->toDateString(),
            periodEnd: Carbon::today()->copy()->addDays($window)->toDateString(),
            metadata: [
                'maturing_loans' => $loans->count(),
                'maturing_principal' => round($principal, 2),
                'currency' => (string) config('proactive-intelligence.currency', 'TZS'),
            ],
        )];
    }

    /**
     * Rule: portfolio at risk reaching a threshold share.
     *
     * @param  int[]  $branchIds
     * @return array<int, array<string, mixed>>
     */
    protected function portfolioPar(int $organizationId, array $branchIds): array
    {
        $threshold = (int) config('proactive-intelligence.par_threshold_days', 30);
        $warning = (float) config('proactive-intelligence.par_warning_percent', 5);
        $critical = (float) config('proactive-intelligence.par_critical_percent', 10);

        $loans = Loan::where('organization_id', $organizationId)
            ->where('status', LoanStatus::Active->value)
            ->where('outstanding_balance', '>', 0)
            ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->get();

        $totalPrincipal = 0.0;
        $parPrincipal = 0.0;

        foreach ($loans as $loan) {
            $principal = $this->delinquency->getPrincipalOutstanding($loan);
            $totalPrincipal += $principal;

            if ($this->delinquency->getDaysPastDue($loan) >= $threshold && $principal > 0) {
                $parPrincipal += $principal;
            }
        }

        if ($totalPrincipal <= 0) {
            return [];
        }

        $percent = ($parPrincipal / $totalPrincipal) * 100;

        if ($percent < $warning) {
            return [];
        }

        return [$this->payload(
            rule: 'portfolio_par',
            type: ProactiveType::PortfolioPar->value,
            severity: $percent >= $critical
                ? ProactiveInsightSeverity::Critical->value
                : ProactiveInsightSeverity::Warning->value,
            title: 'Portfolio at risk',
            summary: sprintf(
                '%.2f%% of principal outstanding (TZS %s of TZS %s) is delinquent past the %d-day PAR threshold.',
                $percent,
                number_format($parPrincipal, 2),
                number_format($totalPrincipal, 2),
                $threshold,
            ),
            recommendation: 'Review the delinquent book, reschedule at-risk loans and tighten collection follow-up.',
            sourceType: ProactiveInsightSource::Organization->value,
            sourceId: $organizationId,
            branchId: null,
            objectType: null,
            objectId: null,
            periodStart: Carbon::today()->toDateString(),
            periodEnd: Carbon::today()->toDateString(),
            metadata: [
                'par_percent' => round($percent, 2),
                'par_principal' => round($parPrincipal, 2),
                'total_principal' => round($totalPrincipal, 2),
                'threshold_days' => $threshold,
                'currency' => (string) config('proactive-intelligence.currency', 'TZS'),
            ],
        )];
    }

    /**
     * Rule: a single loan dominates the active outstanding principal.
     *
     * @param  int[]  $branchIds
     * @return array<int, array<string, mixed>>
     */
    protected function portfolioConcentration(int $organizationId, array $branchIds): array
    {
        $ratio = (float) config('proactive-intelligence.concentration_ratio', 0.30);

        $loans = Loan::where('organization_id', $organizationId)
            ->where('status', LoanStatus::Active->value)
            ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->with('branch')
            ->get();

        $total = (float) $loans->sum('outstanding_balance');

        if ($total <= 0) {
            return [];
        }

        $largest = $loans->sortByDesc('outstanding_balance')->first();

        if (! $largest) {
            return [];
        }

        $share = ((float) $largest->outstanding_balance / $total) * 100;

        if ($share < $ratio * 100) {
            return [];
        }

        return [$this->payload(
            rule: 'portfolio_concentration',
            type: ProactiveType::PortfolioConcentration->value,
            severity: ProactiveInsightSeverity::Warning->value,
            title: 'Loan concentration',
            summary: sprintf(
                'Loan %s represents %.2f%% (TZS %s) of total active outstanding principal.',
                $largest->loan_number,
                $share,
                number_format((float) $largest->outstanding_balance, 2),
            ),
            recommendation: 'Diversify the lending book so a single loan cannot move the portfolio.',
            sourceType: ProactiveInsightSource::Loan->value,
            sourceId: (int) $largest->id,
            branchId: $largest->branch_id,
            objectType: Loan::class,
            objectId: (int) $largest->id,
            periodStart: Carbon::today()->toDateString(),
            periodEnd: Carbon::today()->toDateString(),
            metadata: [
                'share_percent' => round($share, 2),
                'outstanding_amount' => (float) $largest->outstanding_balance,
                'loan_number' => $largest->loan_number,
                'currency' => (string) config('proactive-intelligence.currency', 'TZS'),
            ],
        )];
    }

    /**
     * Rule: the projected net loan cash-flow is below the liquidity floor.
     *
     * @param  int[]  $branchIds
     * @return array<int, array<string, mixed>>
     */
    protected function cashflowShortfall(int $organizationId, array $branchIds): array
    {
        $floor = (float) config('proactive-intelligence.cashflow_shortfall_threshold', 0);
        $horizon = max(1, (int) config('predictive-intelligence.default_horizon', 3));

        try {
            $outcome = $this->cashflow->predict($organizationId, $branchIds, $horizon);
        } catch (Throwable) {
            return [];
        }

        if (($outcome['status'] ?? '') !== 'generated' || $outcome['value_total'] === null) {
            return [];
        }

        $value = (float) $outcome['value_total'];

        if ($value >= $floor) {
            return [];
        }

        return [$this->payload(
            rule: 'cashflow_shortfall',
            type: ProactiveType::CashflowShortfall->value,
            severity: ProactiveInsightSeverity::Warning->value,
            title: 'Projected cash-flow shortfall',
            summary: sprintf(
                'Projected cumulative net loan cash-flow over the next %d month(s) is %s, below the %s floor.',
                $horizon,
                number_format($value, 2),
                number_format($floor, 2),
            ),
            recommendation: 'Plan liquidity ahead of the projected outflow months; align disbursements with collections.',
            sourceType: ProactiveInsightSource::Organization->value,
            sourceId: $organizationId,
            branchId: null,
            objectType: null,
            objectId: null,
            periodStart: Carbon::today()->toDateString(),
            periodEnd: Carbon::today()->addMonths($horizon)->toDateString(),
            metadata: [
                'projected_net' => round($value, 2),
                'floor' => $floor,
                'horizon_months' => $horizon,
                'currency' => (string) config('proactive-intelligence.currency', 'TZS'),
            ],
        )];
    }

    /**
     * Rule: collection rate dropped materially between the two trailing
     * complete months.
     *
     * @param  int[]  $branchIds
     * @return array<int, array<string, mixed>>
     */
    protected function collectionDecline(int $organizationId, array $branchIds): array
    {
        $points = (float) config('proactive-intelligence.collection_decline_points', 20);

        $currentMonth = Carbon::today()->startOfMonth()->subMonth();
        $previousMonth = Carbon::today()->startOfMonth()->subMonths(2);

        $current = $this->collectionRate($organizationId, $branchIds, $currentMonth);
        $previous = $this->collectionRate($organizationId, $branchIds, $previousMonth);

        if ($current['total_due'] <= 0 || $previous['total_due'] <= 0) {
            return [];
        }

        $drop = $previous['collection_rate'] - $current['collection_rate'];

        if ($drop < $points) {
            return [];
        }

        return [$this->payload(
            rule: 'collection_decline',
            type: ProactiveType::CollectionDecline->value,
            severity: ProactiveInsightSeverity::Warning->value,
            title: 'Collection decline',
            summary: sprintf(
                'Collection rate fell from %.2f%% (%s) to %.2f%% (%s), a drop of %.2f points.',
                $previous['collection_rate'],
                $previousMonth->format('Y-m'),
                $current['collection_rate'],
                $currentMonth->format('Y-m'),
                $drop,
            ),
            recommendation: 'Investigate why collections slowed and intensify follow-up on the affected month.',
            sourceType: ProactiveInsightSource::Organization->value,
            sourceId: $organizationId,
            branchId: null,
            objectType: null,
            objectId: null,
            periodStart: $currentMonth->copy()->startOfMonth()->toDateString(),
            periodEnd: $currentMonth->copy()->endOfMonth()->toDateString(),
            metadata: [
                'current_rate' => round($current['collection_rate'], 2),
                'previous_rate' => round($previous['collection_rate'], 2),
                'drop_points' => round($drop, 2),
                'current_due' => round($current['total_due'], 2),
                'current_collected' => round($current['total_collected'], 2),
                'currency' => (string) config('proactive-intelligence.currency', 'TZS'),
            ],
        )];
    }

    /**
     * @param  int[]  $branchIds
     * @return array{total_due: float, total_collected: float, collection_rate: float}
     */
    protected function collectionRate(int $organizationId, array $branchIds, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $totalDue = (float) LoanRepaymentSchedule::where('organization_id', $organizationId)
            ->whereBetween('due_date', [$start, $end])
            ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->sum('total_amount');

        $totalCollected = (float) LoanRepayment::where('organization_id', $organizationId)
            ->where('status', 'posted')
            ->whereBetween('payment_date', [$start, $end])
            ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->sum('amount');

        return [
            'total_due' => $totalDue,
            'total_collected' => $totalCollected,
            'collection_rate' => $totalDue > 0 ? ($totalCollected / $totalDue) * 100 : 0.0,
        ];
    }

    /**
     * Rule: deposit inflows dropped materially between two equal complete
     * windows.
     *
     * @param  int[]  $branchIds
     * @return array<int, array<string, mixed>>
     */
    protected function savingsDecline(int $organizationId, array $branchIds): array
    {
        $window = max(1, (int) config('proactive-intelligence.savings_window_months', 3));
        $percent = (float) config('proactive-intelligence.savings_decline_percent', 20);

        $recent = $this->deposits(
            $organizationId,
            $branchIds,
            Carbon::today()->startOfMonth()->subMonths($window),
            Carbon::today()->startOfMonth()->subMonth()->copy()->endOfMonth(),
        );

        $previous = $this->deposits(
            $organizationId,
            $branchIds,
            Carbon::today()->startOfMonth()->subMonths($window * 2),
            Carbon::today()->startOfMonth()->subMonths($window)->copy()->endOfMonth(),
        );

        if ($previous <= 0) {
            return [];
        }

        $dropPercent = (($previous - $recent) / $previous) * 100;

        if ($dropPercent < $percent) {
            return [];
        }

        return [$this->payload(
            rule: 'savings_decline',
            type: ProactiveType::SavingsDecline->value,
            severity: ProactiveInsightSeverity::Notice->value,
            title: 'Savings decline',
            summary: sprintf(
                'Deposits over the last %d complete month(s) fell %.2f%% (TZS %s) versus the previous %d month(s) (TZS %s).',
                $window,
                $dropPercent,
                number_format($recent, 2),
                $window,
                number_format($previous, 2),
            ),
            recommendation: 'Re-engage members on the savings obligation; check whether a product or fee change is at play.',
            sourceType: ProactiveInsightSource::Organization->value,
            sourceId: $organizationId,
            branchId: null,
            objectType: null,
            objectId: null,
            periodStart: Carbon::today()->startOfMonth()->subMonths($window)->toDateString(),
            periodEnd: Carbon::today()->startOfMonth()->subMonth()->copy()->endOfMonth()->toDateString(),
            metadata: [
                'recent_deposits' => round($recent, 2),
                'previous_deposits' => round($previous, 2),
                'drop_percent' => round($dropPercent, 2),
                'window_months' => $window,
                'currency' => (string) config('proactive-intelligence.currency', 'TZS'),
            ],
        )];
    }

    /**
     * @param  int[]  $branchIds
     */
    protected function deposits(int $organizationId, array $branchIds, Carbon $start, Carbon $end): float
    {
        return round((float) SavingsTransaction::where('organization_id', $organizationId)
            ->where('transaction_type', SavingsTransactionType::Deposit->value)
            ->where('status', FinancialTransactionStatus::Completed->value)
            ->whereBetween('transaction_date', [$start, $end])
            ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->sum('amount'), 2);
    }

    /**
     * Rule: accounting hygiene — drafts aging and / or an unbalanced trial
     * balance.
     *
     * @param  int[]  $branchIds
     * @return array<int, array<string, mixed>>
     */
    protected function accountingImbalance(int $organizationId, array $branchIds): array
    {
        $draftDays = max(1, (int) config('proactive-intelligence.journal_draft_days', 7));
        $insights = [];

        $drafts = JournalEntry::where('organization_id', $organizationId)
            ->where('status', JournalEntryStatus::Draft->value)
            ->where('created_at', '<', Carbon::today()->subDays($draftDays))
            ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->count();

        if ($drafts > 0) {
            $insights[] = $this->payload(
                rule: 'accounting_draft_entries',
                type: ProactiveType::AccountingImbalance->value,
                severity: ProactiveInsightSeverity::Notice->value,
                title: 'Unposted journal entries',
                summary: "{$drafts} journal entr(ies) have been in draft for more than {$draftDays} day(s).",
                recommendation: 'Review and post or void stale draft entries so the general ledger stays current.',
                sourceType: ProactiveInsightSource::Accounting->value,
                sourceId: $organizationId,
                branchId: null,
                objectType: null,
                objectId: null,
                periodStart: Carbon::today()->subDays($draftDays)->toDateString(),
                periodEnd: Carbon::today()->toDateString(),
                metadata: ['draft_count' => $drafts, 'draft_days' => $draftDays],
            );
        }

        try {
            $result = $this->trialBalance->generate($organizationId);
        } catch (Throwable) {
            $result = null;
        }

        if ($result !== null && ! (bool) ($result['is_balanced'] ?? true)) {
            $insights[] = $this->payload(
                rule: 'accounting_trial_balance',
                type: ProactiveType::AccountingImbalance->value,
                severity: ProactiveInsightSeverity::Warning->value,
                title: 'Unbalanced trial balance',
                summary: sprintf(
                    'Trial balance totals differ: debit %s versus credit %s.',
                    number_format((float) ($result['total_debit'] ?? 0), 2),
                    number_format((float) ($result['total_credit'] ?? 0), 2),
                ),
                recommendation: 'Investigate the posting difference before closing the accounting period.',
                sourceType: ProactiveInsightSource::Accounting->value,
                sourceId: $organizationId,
                branchId: null,
                objectType: null,
                objectId: null,
                periodStart: Carbon::today()->toDateString(),
                periodEnd: Carbon::today()->toDateString(),
                metadata: [
                    'total_debit' => (float) ($result['total_debit'] ?? 0),
                    'total_credit' => (float) ($result['total_credit'] ?? 0),
                ],
            );
        }

        return $insights;
    }

    /**
     * Rule: operational throughput — stale pending loan applications and
     * predictive data-quality gaps.
     *
     * @param  int[]  $branchIds
     * @return array<int, array<string, mixed>>
     */
    protected function operationalGap(int $organizationId, array $branchIds): array
    {
        $pendingDays = max(1, (int) config('proactive-intelligence.operational_pending_days', 5));
        $insights = [];

        $pending = LoanApplication::where('organization_id', $organizationId)
            ->whereIn('status', [
                LoanApplicationStatus::Submitted->value,
                LoanApplicationStatus::UnderReview->value,
            ])
            ->where('application_date', '<', Carbon::today()->subDays($pendingDays))
            ->when($branchIds !== [], fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->count();

        if ($pending > 0) {
            $insights[] = $this->payload(
                rule: 'operational_pending_applications',
                type: ProactiveType::OperationalGap->value,
                severity: ProactiveInsightSeverity::Notice->value,
                title: 'Pending loan applications',
                summary: "{$pending} loan application(s) have been in review for more than {$pendingDays} day(s).",
                recommendation: 'Clear the application queue so members are not left waiting and decisions stay timely.',
                sourceType: ProactiveInsightSource::System->value,
                sourceId: $organizationId,
                branchId: null,
                objectType: null,
                objectId: null,
                periodStart: Carbon::today()->subDays($pendingDays)->toDateString(),
                periodEnd: Carbon::today()->toDateString(),
                metadata: ['pending_count' => $pending, 'pending_days' => $pendingDays],
            );
        }

        $issues = $this->quality->issues($organizationId, $branchIds);

        if ($issues !== []) {
            $insights[] = $this->payload(
                rule: 'operational_quality_gate',
                type: ProactiveType::OperationalGap->value,
                severity: ProactiveInsightSeverity::Notice->value,
                title: 'Underlying data-quality gaps',
                summary: 'The predictive-layer quality gate reported issues that undermine foresight: '.implode(' ', $issues),
                recommendation: 'Fix the reported data issues (duplicate repayments, missing relationships, non-positive amounts).',
                sourceType: ProactiveInsightSource::System->value,
                sourceId: $organizationId,
                branchId: null,
                objectType: null,
                objectId: null,
                periodStart: Carbon::today()->toDateString(),
                periodEnd: Carbon::today()->toDateString(),
                metadata: ['issues' => $issues],
            );
        }

        return $insights;
    }

    /**
     * Rule: predictive outlook — a poor-quality or high-risk snapshot worth
     * surfacing proactively.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function predictiveOutlookRisk(int $organizationId): array
    {
        $insights = [];

        foreach (PredictiveInsightType::cases() as $domain) {
            $prediction = $this->predictive->latest($domain, $organizationId);

            if ($prediction === null) {
                continue;
            }

            if ($prediction->status === PredictiveInsightStatus::PoorQualityData) {
                $insights[] = $this->payload(
                    rule: 'predictive_quality_gate',
                    type: ProactiveType::PredictiveOutlookRisk->value,
                    severity: ProactiveInsightSeverity::Notice->value,
                    title: 'Predictive data-quality gate',
                    summary: sprintf(
                        'The %s outlook could not be produced reliably: the quality gate reported inconsistencies in the source records.',
                        $domain->label(),
                    ),
                    recommendation: 'Resolve the reported data inconsistencies so outlooks are dependable.',
                    sourceType: ProactiveInsightSource::Prediction->value,
                    sourceId: (int) $prediction->id,
                    branchId: null,
                    objectType: AiPrediction::class,
                    objectId: (int) $prediction->id,
                    periodStart: $prediction->data_from?->toDateString(),
                    periodEnd: $prediction->data_through?->toDateString(),
                    metadata: [
                        'prediction_type' => $domain->value,
                        'quality' => $prediction->data_quality->value,
                    ],
                );

                continue;
            }

            if ($domain !== PredictiveInsightType::DelinquencyRisk || $prediction->value_total === null) {
                continue;
            }

            $score = (float) $prediction->value_total;
            $medium = (float) config('predictive-intelligence.delinquency_risk_levels.medium_threshold', 34);
            $high = (float) config('predictive-intelligence.delinquency_risk_levels.high_threshold', 67);

            if ($score < $medium) {
                continue;
            }

            $insights[] = $this->payload(
                rule: 'predictive_delinquency_risk',
                type: ProactiveType::PredictiveOutlookRisk->value,
                severity: $score >= $high
                    ? ProactiveInsightSeverity::Warning->value
                    : ProactiveInsightSeverity::Notice->value,
                title: 'Rising delinquency risk',
                summary: sprintf(
                    'The delinquency-risk outlook stands at %.2f / 100 for the coming horizon.',
                    $score,
                ),
                recommendation: 'Tighten collection follow-up ahead of the projected delinquency level.',
                sourceType: ProactiveInsightSource::Prediction->value,
                sourceId: (int) $prediction->id,
                branchId: null,
                objectType: AiPrediction::class,
                objectId: (int) $prediction->id,
                periodStart: $prediction->data_from?->toDateString(),
                periodEnd: $prediction->data_through?->toDateString(),
                metadata: [
                    'prediction_type' => $domain->value,
                    'risk_score' => round($score, 2),
                ],
            );
        }

        return $insights;
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(
        string $rule,
        string $type,
        string $severity,
        string $title,
        string $summary,
        ?string $recommendation,
        string $sourceType,
        ?int $sourceId,
        ?int $branchId,
        ?string $objectType,
        ?int $objectId,
        ?string $periodStart,
        ?string $periodEnd,
        array $metadata,
    ): array {
        return [
            'rule' => $rule,
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'summary' => $summary,
            'recommendation' => $recommendation,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'branch_id' => $branchId,
            'object_type' => $objectType,
            'object_id' => $objectId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'metadata' => $metadata,
            'data_through' => Carbon::today()->toDateString(),
        ];
    }
}
