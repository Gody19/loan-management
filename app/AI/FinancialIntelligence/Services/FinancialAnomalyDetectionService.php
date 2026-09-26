<?php

namespace App\AI\FinancialIntelligence\Services;

use App\AI\FinancialIntelligence\DTOs\FinancialFindingData;
use App\Enums\AiAnomalyFindingStatus;
use App\Enums\FinancialAnomalyType;
use App\Enums\FinancialFindingSeverity;
use App\Enums\LoanRepaymentStatus;
use App\Enums\LoanStatus;
use App\Models\AiAnomalyFinding;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Services\AccountingConfigurationService;
use App\Services\AuditService;
use App\Services\GeneralLedgerService;
use App\Services\LoanDelinquencyService;
use App\Services\TrialBalanceService;
use Carbon\Carbon;
use InvalidArgumentException;
use Throwable;

/**
 * Deterministic rule-based anomaly detection (Phase 11.7).
 *
 * Every rule is a pure, read-only computation over authoritative FinancePro
 * records with a configurable threshold. Findings are persisted into the
 * review trail (ai_anomaly_findings) and always reported — the detection
 * never suppresses, resolves, or changes a financial record. Scope is the
 * acting user's organizations; branch narrowing is intentionally not applied
 * here so a detection pass is tenant-safe and uniformly interpretable.
 */
class FinancialAnomalyDetectionService
{
    public function __construct(
        private readonly LoanDelinquencyService $delinquency,
        private readonly TrialBalanceService $trialBalance,
        private readonly GeneralLedgerService $ledger,
        private readonly AccountingConfigurationService $accounting,
        private readonly AuditService $audit,
    ) {}

    /**
     * Run every enabled rule across the organizations in scope and persist a
     * day-keyed review trail.
     *
     * @param  int[]  $organizationIds
     * @return FinancialFindingData[] findings for the current pass (bounded)
     */
    public function detect(array $organizationIds): array
    {
        if (! (bool) config('financial-intelligence.anomaly_detection.enabled', true)) {
            return [];
        }

        $windowDays = (int) config('financial-intelligence.anomaly_detection.detection_window_days', 30);
        $windowStart = Carbon::today()->subDays($windowDays);
        $maxFindings = (int) config('financial-intelligence.anomaly_detection.max_findings', 50);

        $findings = [];

        foreach ($organizationIds as $organizationId) {
            foreach ($this->rulesFor($organizationId, $windowStart) as $raw) {
                $row = $this->persist($organizationId, $raw);
                $raw['organization_id'] = $organizationId;
                $raw['id'] = (int) $row->id;
                $raw['detected_at'] = (string) $row->detected_at;
                $raw['status'] = $row->status->value;
                $raw['status_label'] = $row->status->label();
                $findings[] = $this->toData($raw);
            }

            if (count($findings) >= $maxFindings) {
                break;
            }
        }

        $findings = array_slice($findings, 0, $maxFindings);

        $this->audit->log('ai.financial_anomaly.detected', null, [], [
            'organization_count' => count($organizationIds),
            'findings_count' => count($findings),
            'severities' => array_count_values(array_column($findings, 'severity')),
            'types' => array_values(array_unique(array_column($findings, 'type'))),
        ]);

        return $findings;
    }

    /**
     * Run the rule set for one organization and return raw finding payloads.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function rulesFor(int $organizationId, Carbon $windowStart): array
    {
        $findings = [
            ...$this->unusualLargeOverpayments($organizationId),
            ...$this->repeatedReversals($organizationId, $windowStart),
            ...$this->concentrationFindings($organizationId),
            ...$this->negativeCashPosition($organizationId),
            ...$this->trialBalanceFinding($organizationId),
        ];

        return $findings;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function unusualLargeOverpayments(int $organizationId): array
    {
        $ratio = (float) config('financial-intelligence.anomaly_detection.unusual_large_overpayment_ratio', 1.5);
        $findings = [];

        $repayments = LoanRepayment::where('organization_id', $organizationId)
            ->where('status', LoanRepaymentStatus::Posted)
            ->whereBetween('payment_date', [
                Carbon::today()->subDays((int) config('financial-intelligence.anomaly_detection.detection_window_days', 30)),
                Carbon::today(),
            ])
            ->with('loan.branch')
            ->get();

        foreach ($repayments as $repayment) {
            $loan = $repayment->loan;

            if (! $loan) {
                continue;
            }

            $installments = (int) ($loan->total_installments ?? 0);
            $scheduleTotal = (float) $loan->total_amount;

            if ($installments <= 0 || $scheduleTotal <= 0) {
                continue;
            }

            $expectedInstallment = $scheduleTotal / $installments;
            $amount = (float) $repayment->amount;

            if ($amount > $expectedInstallment * $ratio) {
                $findings[] = $this->payload(
                    type: FinancialAnomalyType::UnusualLargeOverpayment->value,
                    severity: FinancialFindingSeverity::Medium->value,
                    title: 'Unusually large loan repayment',
                    description: sprintf(
                        'Loan %s posted a repayment of %s against an expected installment of %s.',
                        $loan->loan_number,
                        number_format($amount, 2),
                        number_format(round($expectedInstallment, 2), 2),
                    ),
                    amount: $amount,
                    sourceType: 'loan',
                    sourceId: (int) $loan->id,
                    branchId: $loan->branch_id,
                    memberId: $loan->member_id,
                    loanId: (int) $loan->id,
                );
            }
        }

        return $findings;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function repeatedReversals(int $organizationId, Carbon $windowStart): array
    {
        $minCount = (int) config('financial-intelligence.anomaly_detection.repeated_reversal_min_count', 3);
        $findings = [];

        $groups = LoanRepayment::where('organization_id', $organizationId)
            ->where('status', LoanRepaymentStatus::Reversed)
            ->where('created_at', '>=', $windowStart)
            ->selectRaw('loan_id, COUNT(*) as reversal_count')
            ->groupBy('loan_id')
            ->havingRaw('COUNT(*) >= ?', [$minCount])
            ->pluck('reversal_count', 'loan_id');

        if ($groups->isEmpty()) {
            return $findings;
        }

        $loans = Loan::whereIn('id', $groups->keys())->with('branch')->get()->keyBy('id');

        foreach ($groups as $loanId => $count) {
            $loan = $loans[$loanId] ?? null;

            if (! $loan) {
                continue;
            }

            $findings[] = $this->payload(
                type: FinancialAnomalyType::RepeatedReversal->value,
                severity: FinancialFindingSeverity::Medium->value,
                title: 'Repeated repayment reversal',
                description: sprintf(
                    'Loan %s had %d payable(s) reversed within the detection window.',
                    $loan->loan_number,
                    (int) $count,
                ),
                amount: (float) $loan->outstanding_balance,
                sourceType: 'loan',
                sourceId: (int) $loan->id,
                branchId: $loan->branch_id,
                memberId: $loan->member_id,
                loanId: (int) $loan->id,
            );
        }

        return $findings;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function concentrationFindings(int $organizationId): array
    {
        $findings = [];

        $loans = Loan::where('organization_id', $organizationId)
            ->where('status', LoanStatus::Active)
            ->with('branch')
            ->get();

        $withPrincipal = collect();

        foreach ($loans as $loan) {
            $principal = (float) $this->delinquency->getPrincipalOutstanding($loan);
            $withPrincipal->push([
                'loan' => $loan,
                'principal' => $principal,
                'stored_outstanding' => (float) $loan->outstanding_balance,
                'dpd' => $this->delinquency->getDaysPastDue($loan),
            ]);
        }

        $totalOutstanding = (float) $withPrincipal->sum('stored_outstanding');
        $loanConcentrationRatio = (float) config('financial-intelligence.anomaly_detection.loan_concentration_ratio', 0.25);

        if ($totalOutstanding > 0) {
            $largest = $withPrincipal->sortByDesc('stored_outstanding')->first();

            if ($largest && $largest['stored_outstanding'] >= $totalOutstanding * $loanConcentrationRatio) {
                $findings[] = $this->payload(
                    type: FinancialAnomalyType::LoanConcentration->value,
                    severity: FinancialFindingSeverity::High->value,
                    title: 'High loan concentration',
                    description: sprintf(
                        'Loan %s represents %.2f%% of total active outstanding principal.',
                        $largest['loan']->loan_number,
                        ($totalOutstanding > 0 ? ($largest['stored_outstanding'] / $totalOutstanding) * 100 : 0),
                    ),
                    amount: $largest['stored_outstanding'],
                    sourceType: 'loan',
                    sourceId: (int) $largest['loan']->id,
                    branchId: $largest['loan']->branch_id,
                    memberId: $largest['loan']->member_id,
                    loanId: (int) $largest['loan']->id,
                );
            }
        }

        $totalDelinquentPrincipal = (float) $withPrincipal->where('dpd', '>', 0)->sum('principal');
        $concentrationRatio = (float) config('financial-intelligence.anomaly_detection.delinquency_concentration_ratio', 0.5);

        if ($totalDelinquentPrincipal > 0) {
            $largest = $withPrincipal->where('dpd', '>', 0)->sortByDesc('principal')->first();

            if ($largest && $largest['principal'] >= $totalDelinquentPrincipal * $concentrationRatio) {
                $findings[] = $this->payload(
                    type: FinancialAnomalyType::DelinquencyConcentration->value,
                    severity: FinancialFindingSeverity::High->value,
                    title: 'Delinquency concentration',
                    description: sprintf(
                        'Loan %s holds %.2f%% of total delinquent principal outstanding.',
                        $largest['loan']->loan_number,
                        ($totalDelinquentPrincipal > 0 ? ($largest['principal'] / $totalDelinquentPrincipal) * 100 : 0),
                    ),
                    amount: round($largest['principal'], 2),
                    sourceType: 'loan',
                    sourceId: (int) $largest['loan']->id,
                    branchId: $largest['loan']->branch_id,
                    memberId: $largest['loan']->member_id,
                    loanId: (int) $largest['loan']->id,
                );
            }
        }

        $totalPrincipal = (float) $withPrincipal->sum('principal');
        $threshold = (int) config('financial-intelligence.par_threshold_days', 30);
        $parPercent = (float) config('financial-intelligence.anomaly_detection.par_concentration_threshold_percent', 10);

        if ($totalPrincipal > 0) {
            $parPrincipal = (float) $withPrincipal->where('dpd', '>=', $threshold)->sum('principal');
            $actualPercent = ($parPrincipal / $totalPrincipal) * 100;

            if ($actualPercent >= $parPercent) {
                $findings[] = $this->payload(
                    type: FinancialAnomalyType::ParConcentration->value,
                    severity: FinancialFindingSeverity::Medium->value,
                    title: 'Portfolio-at-risk concentration',
                    description: sprintf(
                        'Delinquent principal (>= %d days) is %.2f%% of total principal outstanding, at or above the %.2f%% threshold.',
                        $threshold,
                        $actualPercent,
                        $parPercent,
                    ),
                    amount: round($parPrincipal, 2),
                    sourceType: 'organization',
                    sourceId: $organizationId,
                    branchId: null,
                    memberId: null,
                    loanId: null,
                );
            }
        }

        return $findings;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function negativeCashPosition(int $organizationId): array
    {
        $threshold = (float) config('financial-intelligence.anomaly_detection.negative_cash_threshold', 0);
        $total = 0.0;

        foreach (['cash_on_hand', 'bank_account', 'mobile_money'] as $key) {
            try {
                $total += (float) $this->ledger->getAccountBalance(
                    $organizationId,
                    $this->accounting->getAccountId($organizationId, $key),
                );
            } catch (InvalidArgumentException) {
                continue;
            } catch (Throwable) {
                continue;
            }
        }

        if ($total >= $threshold) {
            return [];
        }

        return [$this->payload(
            type: FinancialAnomalyType::NegativeCashPosition->value,
            severity: FinancialFindingSeverity::High->value,
            title: 'Negative cash position',
            description: 'Combined cash-on-hand, bank and mobile-money balances are below the configured floor.',
            amount: round($total, 2),
            sourceType: 'organization',
            sourceId: $organizationId,
            branchId: null,
            memberId: null,
            loanId: null,
        )];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function trialBalanceFinding(int $organizationId): array
    {
        try {
            $result = $this->trialBalance->generate($organizationId);
        } catch (Throwable) {
            return [];
        }

        if ($result['is_balanced']) {
            return [];
        }

        return [$this->payload(
            type: FinancialAnomalyType::TrialBalanceUnbalanced->value,
            severity: FinancialFindingSeverity::High->value,
            title: 'Unbalanced trial balance',
            description: sprintf(
                'Trial balance totals differ: debit %s vs credit %s.',
                number_format((float) $result['total_debit'], 2),
                number_format((float) $result['total_credit'], 2),
            ),
            amount: round(abs((float) $result['total_debit'] - (float) $result['total_credit']), 2),
            sourceType: 'organization',
            sourceId: $organizationId,
            branchId: null,
            memberId: null,
            loanId: null,
        )];
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(
        string $type,
        string $severity,
        string $title,
        string $description,
        ?float $amount,
        string $sourceType,
        int $sourceId,
        ?int $branchId,
        ?int $memberId,
        ?int $loanId,
    ): array {
        return [
            'type' => $type,
            'type_label' => FinancialAnomalyType::from($type)->label(),
            'severity' => $severity,
            'severity_label' => FinancialFindingSeverity::from($severity)->label(),
            'title' => $title,
            'description' => $description,
            'amount' => $amount,
            'currency' => (string) config('financial-intelligence.currency', 'TZS'),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'branch_id' => $branchId,
            'member_id' => $memberId,
            'loan_id' => $loanId,
            'detected_at' => now()->toISOString(),
            'status' => AiAnomalyFindingStatus::Detected->value,
            'status_label' => AiAnomalyFindingStatus::Detected->label(),
        ];
    }

    /**
     * Persist a finding into the day-keyed review trail. Detection date is the
     * deterministic uniqueness key: the same rule + scope + day never creates
     * a duplicate row.
     *
     * @param  array<string, mixed>  $raw
     */
    protected function persist(int $organizationId, array $raw): AiAnomalyFinding
    {
        return AiAnomalyFinding::updateOrCreate(
            [
                'organization_id' => $organizationId,
                'type' => $raw['type'],
                'source_type' => $raw['source_type'],
                'source_id' => $raw['source_id'],
                'detection_date' => Carbon::today()->toDateString(),
            ],
            [
                'branch_id' => $raw['branch_id'],
                'member_id' => $raw['member_id'],
                'loan_id' => $raw['loan_id'],
                'severity' => $raw['severity'],
                'title' => $raw['title'],
                'description' => $raw['description'],
                'amount' => $raw['amount'],
                'currency' => $raw['currency'],
                'metadata' => [
                    'type_label' => $raw['type_label'],
                    'severity_label' => $raw['severity_label'],
                ],
                'detected_at' => now(),
                'status' => AiAnomalyFindingStatus::Detected->value,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    protected function toData(array $raw): FinancialFindingData
    {
        return new FinancialFindingData(
            id: $raw['id'] ?? null,
            type: $raw['type'],
            typeLabel: $raw['type_label'],
            severity: $raw['severity'],
            severityLabel: $raw['severity_label'],
            title: $raw['title'],
            description: $raw['description'],
            amount: $raw['amount'],
            currency: $raw['currency'],
            sourceType: $raw['source_type'],
            sourceId: $raw['source_id'],
            organizationId: $raw['organization_id'] ?? null,
            branchId: $raw['branch_id'],
            memberId: $raw['member_id'],
            loanId: $raw['loan_id'],
            detectedAt: $raw['detected_at'],
            status: $raw['status'],
            statusLabel: $raw['status_label'],
        );
    }
}
