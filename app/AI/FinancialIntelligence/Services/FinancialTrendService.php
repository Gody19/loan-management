<?php

namespace App\AI\FinancialIntelligence\Services;

use App\AI\FinancialIntelligence\DTOs\FinancialTrendData;
use App\Enums\LoanRepaymentStatus;
use App\Models\Loan;
use App\Models\LoanRepayment;
use Carbon\Carbon;

/**
 * Monthly money-movement trend series (disbursements, posted collections,
 * reversals). All values are summed from authoritative loan and repayment
 * records; nothing is predicted or extrapolated.
 */
class FinancialTrendService
{
    /**
     * @param  int[]  $organizationIds
     * @param  int[]  $branchIds
     */
    public function monthly(array $organizationIds, array $branchIds = [], ?int $months = null): FinancialTrendData
    {
        $months = $months ?? (int) config('financial-intelligence.trend_months', 12);
        $months = max(1, min(abs($months), 60));

        $organizationsInScope = $organizationIds === []
            ? [0]
            : $organizationIds;

        $series = [];

        for ($i = 0; $i < $months; $i++) {
            $period = Carbon::today()->startOfMonth()->subMonths($i);
            $start = $period->copy()->startOfMonth();
            $end = $period->copy()->endOfMonth();
            $periodKey = $period->format('Y-m');

            $series[] = [
                'period' => $periodKey,
                'label' => $period->format('M Y'),
                'total_disbursed' => $this->disbursed($organizationsInScope, $branchIds, $start, $end),
                'disbursements_count' => $this->disbursedCount($organizationsInScope, $branchIds, $start, $end),
                'total_collected' => $this->collected($organizationsInScope, $branchIds, $start, $end),
                'repayments_count' => $this->collectedCount($organizationsInScope, $branchIds, $start, $end),
                'reversals_count' => $this->reversalsCount($organizationsInScope, $branchIds, $start, $end),
                'net_cash_flow' => null,
            ];
        }

        // Oldest first, net computed after both streams are known.
        $series = array_reverse($series);
        foreach ($series as &$row) {
            $row['net_cash_flow'] = round($row['total_collected'] - $row['total_disbursed'], 2);
        }
        unset($row);

        $totalDisbursed = round((float) collect($series)->sum('total_disbursed'), 2);
        $totalCollected = round((float) collect($series)->sum('total_collected'), 2);

        return new FinancialTrendData(
            currency: (string) config('financial-intelligence.currency', 'TZS'),
            organizationCount: count($organizationIds),
            months: count($series),
            trend: $series,
            totalDisbursed: $totalDisbursed,
            totalCollected: $totalCollected,
            generatedAt: now()->toISOString(),
        );
    }

    /**
     * @param  int[]  $organizationIds
     * @param  int[]  $branchIds
     */
    protected function disbursed(array $organizationIds, array $branchIds, Carbon $start, Carbon $end): float
    {
        $query = Loan::whereIn('organization_id', $organizationIds)
            ->whereBetween('disbursement_date', [$start, $end]);

        if ($branchIds !== []) {
            $query->whereIn('branch_id', $branchIds);
        }

        return round((float) $query->sum('disbursed_amount'), 2);
    }

    /**
     * @param  int[]  $organizationIds
     * @param  int[]  $branchIds
     */
    protected function disbursedCount(array $organizationIds, array $branchIds, Carbon $start, Carbon $end): int
    {
        $query = Loan::whereIn('organization_id', $organizationIds)
            ->whereBetween('disbursement_date', [$start, $end]);

        if ($branchIds !== []) {
            $query->whereIn('branch_id', $branchIds);
        }

        return (int) $query->count();
    }

    /**
     * @param  int[]  $organizationIds
     * @param  int[]  $branchIds
     */
    protected function collected(array $organizationIds, array $branchIds, Carbon $start, Carbon $end): float
    {
        $query = LoanRepayment::whereIn('organization_id', $organizationIds)
            ->where('status', LoanRepaymentStatus::Posted)
            ->whereBetween('payment_date', [$start, $end]);

        if ($branchIds !== []) {
            $query->whereIn('branch_id', $branchIds);
        }

        return round((float) $query->sum('amount'), 2);
    }

    /**
     * @param  int[]  $organizationIds
     * @param  int[]  $branchIds
     */
    protected function collectedCount(array $organizationIds, array $branchIds, Carbon $start, Carbon $end): int
    {
        $query = LoanRepayment::whereIn('organization_id', $organizationIds)
            ->where('status', LoanRepaymentStatus::Posted)
            ->whereBetween('payment_date', [$start, $end]);

        if ($branchIds !== []) {
            $query->whereIn('branch_id', $branchIds);
        }

        return (int) $query->count();
    }

    /**
     * @param  int[]  $organizationIds
     * @param  int[]  $branchIds
     */
    protected function reversalsCount(array $organizationIds, array $branchIds, Carbon $start, Carbon $end): int
    {
        $query = LoanRepayment::whereIn('organization_id', $organizationIds)
            ->where('status', LoanRepaymentStatus::Reversed)
            ->whereBetween('payment_date', [$start, $end]);

        if ($branchIds !== []) {
            $query->whereIn('branch_id', $branchIds);
        }

        return (int) $query->count();
    }
}
