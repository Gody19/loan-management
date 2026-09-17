<?php

namespace App\Services;

use App\Enums\LoanScheduleInstallmentStatus;
use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\LoanRepaymentSchedule;
use Carbon\Carbon;

class LoanDelinquencyService
{
    public function updateDelinquencyStatuses(int $organizationId): array
    {
        $activeLoans = Loan::forOrganization($organizationId)
            ->where('status', LoanStatus::Active)
            ->get();

        $updatedCount = 0;

        foreach ($activeLoans as $loan) {
            $updated = $this->updateLoanDelinquency($loan);
            if ($updated) {
                $updatedCount++;
            }
        }

        return [
            'loans_checked' => $activeLoans->count(),
            'loans_updated' => $updatedCount,
        ];
    }

    public function updateLoanDelinquency(Loan $loan): bool
    {
        $today = Carbon::today();
        $updated = false;

        $overdueInstallments = $loan->repaymentSchedule()
            ->whereIn('status', [
                LoanScheduleInstallmentStatus::Pending->value,
                LoanScheduleInstallmentStatus::Partial->value,
            ])
            ->where('due_date', '<', $today)
            ->get();

        foreach ($overdueInstallments as $installment) {
            $daysOverdue = (int) abs($today->diffInDays($installment->due_date));

            if ($installment->status !== LoanScheduleInstallmentStatus::Overdue) {
                $installment->update([
                    'status' => LoanScheduleInstallmentStatus::Overdue,
                    'days_overdue' => $daysOverdue,
                ]);
                $updated = true;
            } elseif ($installment->days_overdue !== $daysOverdue) {
                $installment->update(['days_overdue' => $daysOverdue]);
                $updated = true;
            }
        }

        return $updated;
    }

    public function getDelinquentLoans(int $organizationId): \Illuminate\Support\Collection
    {
        return Loan::forOrganization($organizationId)
            ->where('status', LoanStatus::Active)
            ->whereHas('repaymentSchedule', function ($q) {
                $q->where('outstanding_amount', '>', 0)
                ->where('due_date', '<', Carbon::today());
            })
            ->with(['member', 'branch', 'loanPlan', 'repaymentSchedule' => function ($q) {
                $q->where('outstanding_amount', '>', 0)
                  ->where('due_date', '<', Carbon::today());
            }])
            ->get();
    }

    public function getDaysPastDue(Loan $loan): int
    {
        $oldestOverdue = $loan->repaymentSchedule()
            ->where('outstanding_amount', '>', 0)
            ->where('due_date', '<', Carbon::today())
            ->orderBy('due_date')
            ->first();

        if (!$oldestOverdue) {
            return 0;
        }

        return (int) abs(Carbon::today()->startOfDay()->diffInDays($oldestOverdue->due_date->startOfDay()));
    }

    public function getPAR(int $organizationId, int $daysThreshold): array
    {
        $activeLoans = Loan::forOrganization($organizationId)
            ->where('status', LoanStatus::Active)
            ->where('outstanding_balance', '>', 0)
            ->get();

        if ($activeLoans->isEmpty()) {
            return [
                'total_outstanding' => 0,
                'delinquent_outstanding' => 0,
                'par_percentage' => 0,
                'active_loans_count' => 0,
                'delinquent_loans_count' => 0,
            ];
        }

        $totalPrincipalOutstanding = 0;
        $delinquentPrincipalOutstanding = 0;
        $delinquentCount = 0;

        foreach ($activeLoans as $loan) {
            $principalOutstanding = $this->getPrincipalOutstanding($loan);
            $totalPrincipalOutstanding += $principalOutstanding;

            $dpd = $this->getDaysPastDue($loan);
            if ($dpd >= $daysThreshold && $principalOutstanding > 0) {
                $delinquentPrincipalOutstanding += $principalOutstanding;
                $delinquentCount++;
            }
        }

        return [
            'total_outstanding' => round($totalPrincipalOutstanding, 2),
            'delinquent_outstanding' => round($delinquentPrincipalOutstanding, 2),
            'par_percentage' => $totalPrincipalOutstanding > 0
                ? round(($delinquentPrincipalOutstanding / $totalPrincipalOutstanding) * 100, 2)
                : 0,
            'active_loans_count' => $activeLoans->count(),
            'delinquent_loans_count' => $delinquentCount,
        ];
    }

    public function getPrincipalOutstanding(Loan $loan): float
    {
        $totalPrincipalScheduled = (float) $loan->repaymentSchedule()
            ->sum('principal_amount');

        $totalPrincipalPaid = (float) \App\Models\LoanRepaymentAllocation::where('loan_id', $loan->id)
            ->whereHas('repayment', fn($q) => $q->where('status', 'posted'))
            ->sum('principal_allocation');

        return max(0, $totalPrincipalScheduled - $totalPrincipalPaid);
    }

    public function getCollectionRate(int $organizationId, string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        $totalDue = LoanRepaymentSchedule::forOrganization($organizationId)
            ->whereBetween('due_date', [$start, $end])
            ->sum('total_amount');

        $totalCollected = \App\Models\LoanRepayment::forOrganization($organizationId)
            ->where('status', 'posted')
            ->whereBetween('payment_date', [$start, $end])
            ->sum('amount');

        return [
            'total_due' => round((float) $totalDue, 2),
            'total_collected' => round($totalCollected, 2),
            'collection_rate' => (float) $totalDue > 0
                ? round(($totalCollected / (float) $totalDue) * 100, 2)
                : 0,
        ];
    }
}
