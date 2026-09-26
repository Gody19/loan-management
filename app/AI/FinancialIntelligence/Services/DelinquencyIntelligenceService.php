<?php

namespace App\AI\FinancialIntelligence\Services;

use App\AI\FinancialIntelligence\DTOs\FinancialDelinquencyData;
use App\Enums\DelinquencyAgingBucket;
use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Services\LoanDelinquencyService;
use Illuminate\Support\Collection;

/**
 * Delinquency profile: how many active loans are past due, how deep the DPD
 * goes, and which exposures are largest. Days past due comes exclusively from
 * the authoritative LoanDelinquencyService.
 */
class DelinquencyIntelligenceService
{
    public function __construct(
        private readonly LoanDelinquencyService $delinquency,
    ) {}

    /**
     * @param  int[]  $organizationIds
     * @param  int[]  $branchIds
     */
    public function profile(array $organizationIds, array $branchIds = []): FinancialDelinquencyData
    {
        $loans = $this->activeLoans($organizationIds, $branchIds);

        $delinquent = [];

        foreach ($loans as $loan) {
            $dpd = $this->delinquency->getDaysPastDue($loan);

            if ($dpd <= 0) {
                continue;
            }

            $principal = (float) $this->delinquency->getPrincipalOutstanding($loan);

            $delinquent[] = [
                'loan' => $loan,
                'days_past_due' => $dpd,
                'principal' => $principal,
            ];
        }

        usort($delinquent, fn (array $a, array $b) => $b['principal'] <=> $a['principal']);

        $totalPrincipal = round((float) collect($delinquent)->sum('principal'), 2);
        $dpds = collect($delinquent)->pluck('days_past_due');

        $top = collect($delinquent)->take(10)->map(function (array $row) {
            $loan = $row['loan'];
            $bucket = DelinquencyAgingBucket::fromDays($row['days_past_due']);

            return [
                'loan_number' => $loan->loan_number,
                'member_number' => $loan->member?->member_number,
                'member_name' => $loan->member?->full_name,
                'days_past_due' => $row['days_past_due'],
                'outstanding' => round($row['principal'], 2),
                'bucket' => $bucket->value,
                'bucket_label' => $bucket->label(),
            ];
        })->values()->all();

        return new FinancialDelinquencyData(
            currency: (string) config('financial-intelligence.currency', 'TZS'),
            organizationCount: count($organizationIds),
            delinquentLoansCount: count($delinquent),
            delinquentPrincipalOutstanding: $totalPrincipal,
            minDpd: $dpds->isEmpty() ? 0 : (int) $dpds->min(),
            maxDpd: $dpds->isEmpty() ? 0 : (int) $dpds->max(),
            averageDpd: $dpds->isEmpty() ? 0 : round((float) $dpds->avg(), 2),
            topDelinquentLoans: $top,
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
            ->with('member')
            ->get();
    }
}
