<?php

namespace App\AI\FinancialIntelligence\Services;

use App\AI\FinancialIntelligence\DTOs\FinancialCollectionData;
use App\Enums\LoanRepaymentStatus;
use App\Models\LoanRepayment;
use App\Models\LoanRepaymentSchedule;
use Carbon\Carbon;

/**
 * Collection intelligence for a reporting period. Amounts due come from the
 * repayment schedules, amounts collected from posted repayments — both are
 * authoritative FinancePro records; the intelligence only groups them.
 */
class CollectionIntelligenceService
{
    /**
     * @param  int[]  $organizationIds
     * @param  int[]  $branchIds
     */
    public function summarize(
        array $organizationIds,
        array $branchIds = [],
        ?string $startDate = null,
        ?string $endDate = null,
    ): FinancialCollectionData {
        $end = $startDate === null || $endDate === null
            ? Carbon::today()->endOfDay()
            : Carbon::parse($endDate)->endOfDay();

        $start = $startDate === null
            ? Carbon::today()->startOfMonth()
            : Carbon::parse($startDate)->startOfDay();

        $organizationsInScope = $organizationIds === []
            ? [0]
            : $organizationIds;

        $scheduleQuery = LoanRepaymentSchedule::whereIn('organization_id', $organizationsInScope)
            ->whereBetween('due_date', [$start, $end]);

        $repaymentQuery = LoanRepayment::whereIn('organization_id', $organizationsInScope)
            ->whereBetween('payment_date', [$start, $end]);

        if ($branchIds !== []) {
            $scheduleQuery->whereHas('loan', fn ($query) => $query->whereIn('branch_id', $branchIds));
            $repaymentQuery->whereIn('branch_id', $branchIds);
        }

        $totalDue = (float) $scheduleQuery->sum('total_amount');
        $posted = $repaymentQuery->where('status', LoanRepaymentStatus::Posted)->get();
        $totalCollected = (float) $posted->sum('amount');

        $reversed = $repaymentQuery->where('status', LoanRepaymentStatus::Reversed)->get();

        return new FinancialCollectionData(
            currency: (string) config('financial-intelligence.currency', 'TZS'),
            organizationCount: count($organizationIds),
            startDate: $start->toDateString(),
            endDate: $end->toDateString(),
            totalDue: round($totalDue, 2),
            totalCollected: round($totalCollected, 2),
            collectionRate: $totalDue > 0
                ? round(($totalCollected / $totalDue) * 100, 2)
                : 0,
            postedCount: $posted->count(),
            reversedCount: $reversed->count(),
            reversedAmount: round((float) $reversed->sum('amount'), 2),
            generatedAt: now()->toISOString(),
        );
    }
}
