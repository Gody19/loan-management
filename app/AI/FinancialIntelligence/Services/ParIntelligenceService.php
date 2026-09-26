<?php

namespace App\AI\FinancialIntelligence\Services;

use App\AI\FinancialIntelligence\DTOs\FinancialParData;
use App\Enums\DelinquencyAgingBucket;
use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Services\LoanDelinquencyService;
use Illuminate\Support\Collection;

/**
 * Portfolio-at-risk intelligence. PAR is computed the FinancePro way — each
 * active loan's principal outstanding versus its days past due — then grouped
 * into the explicit, canonical aging buckets (DelinquencyAgingBucket).
 */
class ParIntelligenceService
{
    public function __construct(
        private readonly LoanDelinquencyService $delinquency,
    ) {}

    /**
     * @param  int[]  $organizationIds
     * @param  int[]  $branchIds
     */
    public function summarize(array $organizationIds, array $branchIds = []): FinancialParData
    {
        $loans = $this->activeLoans($organizationIds, $branchIds);

        $thresholdDays = (int) config('financial-intelligence.par_threshold_days', 30);

        $bucketTotals = [];
        foreach (DelinquencyAgingBucket::ordered() as $bucket) {
            $bucketTotals[$bucket->value] = ['bucket' => $bucket->value, 'label' => $bucket->label(), 'min_days' => $bucket->minDays(), 'max_days' => $bucket->maxDays(), 'principal_outstanding' => 0.0, 'loans_count' => 0];
        }

        $totalPrincipal = 0.0;

        foreach ($loans as $loan) {
            $principal = (float) $this->delinquency->getPrincipalOutstanding($loan);
            $dpd = $this->delinquency->getDaysPastDue($loan);
            $bucket = DelinquencyAgingBucket::fromDays($dpd);

            $totalPrincipal += $principal;
            $bucketTotals[$bucket->value]['principal_outstanding'] += $principal;
            $bucketTotals[$bucket->value]['loans_count']++;
        }

        $totalPrincipal = round($totalPrincipal, 2);

        $buckets = array_map(static function (array $row) use ($totalPrincipal) {
            $row['principal_outstanding'] = round($row['principal_outstanding'], 2);
            $row['par_percentage'] = $totalPrincipal > 0
                ? round(($row['principal_outstanding'] / $totalPrincipal) * 100, 2)
                : 0;

            return $row;
        }, array_values($bucketTotals));

        $parOverThreshold = collect($bucketTotals)
            ->filter(fn (array $row) => $row['min_days'] >= $thresholdDays)
            ->sum('principal_outstanding');

        $parRate = $totalPrincipal > 0
            ? round(($parOverThreshold / $totalPrincipal) * 100, 2)
            : 0;

        return new FinancialParData(
            currency: (string) config('financial-intelligence.currency', 'TZS'),
            organizationCount: count($organizationIds),
            activeLoansCount: $loans->count(),
            totalPrincipalOutstanding: $totalPrincipal,
            parThresholdDays: $thresholdDays,
            parRateOverThreshold: $parRate,
            buckets: $buckets,
            generatedAt: now()->toISOString(),
        );
    }

    /**
     * @param  int[]  $organizationIds
     * @param  int[]  $branchIds
     */
    protected function activeLoans(array $organizationIds, array $branchIds): Collection
    {
        if ($organizationIds === []) {
            return collect();
        }

        return Loan::whereIn('organization_id', $organizationIds)
            ->when(
                $branchIds !== [],
                fn ($query) => $query->whereIn('branch_id', $branchIds),
            )
            ->where('status', LoanStatus::Active)
            ->where('outstanding_balance', '>', 0)
            ->get();
    }
}
