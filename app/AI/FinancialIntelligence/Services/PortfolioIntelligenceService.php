<?php

namespace App\AI\FinancialIntelligence\Services;

use App\AI\FinancialIntelligence\DTOs\FinancialPortfolioData;
use App\Enums\LoanScheduleInstallmentStatus;
use App\Enums\LoanStatus;
use App\Models\Loan;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Portfolio intelligence. Every figure is read from the stored authoritative
 * loan columns (and the repayment schedule for upcoming maturities) on
 * demand — the AI never recomputes business balances.
 */
class PortfolioIntelligenceService
{
    /**
     * @param  int[]  $organizationIds
     * @param  int[]  $branchIds
     */
    public function summarize(array $organizationIds, array $branchIds = []): FinancialPortfolioData
    {
        $organizationCount = count($organizationIds);

        $activeLoans = $this->activeLoans($organizationIds, $branchIds)->load(['loanPlan', 'repaymentSchedule']);

        $totalOutstanding = $this->sum(fn (Loan $loan) => (float) $loan->outstanding_balance, $activeLoans);
        $totalPrincipalDisbursed = $this->sum(fn (Loan $loan) => (float) $loan->disbursed_amount, $activeLoans);

        $compositionByStatus = $activeLoans
            ->groupBy(fn (Loan $loan) => $loan->status?->value ?? 'unknown')
            ->map(fn (Collection $group) => ['status' => $group->first()->status?->label() ?? 'Unknown', 'count' => $group->count()])
            ->values()
            ->all();

        $compositionByPlan = $activeLoans
            ->groupBy(fn (Loan $loan) => $loan->loan_plan_id ?? 0)
            ->filter(fn (Collection $group) => $group->first()->loanPlan !== null)
            ->map(function (Collection $group) {
                return [
                    'plan' => $group->first()->loanPlan->name,
                    'count' => $group->count(),
                    'outstanding' => round($this->sum(fn (Loan $loan) => (float) $loan->outstanding_balance, $group), 2),
                ];
            })
            ->values()
            ->sortByDesc('outstanding')
            ->take(10)
            ->values()
            ->all();

        $today = Carbon::today()->startOfDay();

        $maturities = $activeLoans
            ->flatMap(fn (Loan $loan) => $loan->repaymentSchedule
                ->filter(fn ($installment) => in_array($installment->status->value ?? '', [
                    LoanScheduleInstallmentStatus::Pending->value,
                    LoanScheduleInstallmentStatus::Partial->value,
                ], true))
                ->values())
            ->filter(fn ($installment) => $installment->due_date !== null);

        $within30 = $maturities->filter(fn ($installment) => $installment->due_date->between($today, $today->copy()->addDays(30)))->sum(fn ($installment) => (float) $installment->principal_amount);
        $within60 = $maturities->filter(fn ($installment) => $installment->due_date->between($today, $today->copy()->addDays(60)))->sum(fn ($installment) => (float) $installment->principal_amount);

        return new FinancialPortfolioData(
            currency: (string) config('financial-intelligence.currency', 'TZS'),
            organizationCount: $organizationCount,
            activeLoansCount: $activeLoans->count(),
            totalOutstanding: round($totalOutstanding, 2),
            totalPrincipalDisbursed: round($totalPrincipalDisbursed, 2),
            maturingWithin30Days: round((float) $within30, 2),
            maturingWithin60Days: round((float) $within60, 2),
            compositionByStatus: $compositionByStatus,
            compositionByPlan: $compositionByPlan,
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

    /**
     * @param  Collection<int, Loan>  $loans
     */
    protected function sum(callable $extract, Collection $loans): float
    {
        return (float) $loans->sum($extract);
    }
}
