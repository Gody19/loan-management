<?php

namespace App\Services;

use App\Enums\LoanScheduleInstallmentStatus;
use App\Models\Loan;
use App\Models\LoanRepaymentAllocation;
use App\Models\LoanRepaymentSchedule;

class LoanRepaymentAllocationService
{
    public function allocatePayment(Loan $loan, float $amount, int $repaymentId): array
    {
        $allocations = [];
        $remainingAmount = $amount;

        $installments = $loan->repaymentSchedule()
            ->whereIn('status', [
                LoanScheduleInstallmentStatus::Pending,
                LoanScheduleInstallmentStatus::Partial,
                LoanScheduleInstallmentStatus::Overdue,
            ])
            ->orderBy('due_date')
            ->orderBy('installment_number')
            ->get();

        foreach ($installments as $installment) {
            if ($remainingAmount <= 0) break;

            $outstanding = (float) $installment->outstanding_amount;
            if ($outstanding <= 0) continue;

            $allocAmount = min($remainingAmount, $outstanding);

            $feePortion = 0;
            $interestPortion = 0;
            $principalPortion = 0;

            $feeDue = $installment->late_fee;
            if ($feeDue > 0) {
                $feePortion = min($allocAmount, $feeDue);
                $allocAmount -= $feePortion;
            }

            $interestDue = (float) $installment->interest_amount;
            $interestPaid = $this->getInterestPaid($installment);
            $interestRemaining = $interestDue - $interestPaid;
            if ($interestRemaining > 0) {
                $interestPortion = min($allocAmount, $interestRemaining);
                $allocAmount -= $interestPortion;
            }

            $principalDue = (float) $installment->principal_amount;
            $principalPaid = $this->getPrincipalPaid($installment);
            $principalRemaining = $principalDue - $principalPaid;
            if ($principalRemaining > 0) {
                $principalPortion = min($allocAmount, $principalRemaining);
                $allocAmount -= $principalPortion;
            }

            $totalAllocated = $feePortion + $interestPortion + $principalPortion;
            if ($totalAllocated <= 0) continue;

            $allocation = LoanRepaymentAllocation::create([
                'loan_repayment_id' => $repaymentId,
                'loan_id' => $loan->id,
                'loan_repayment_schedule_id' => $installment->id,
                'organization_id' => $loan->organization_id,
                'amount' => round($totalAllocated, 2),
                'principal_allocation' => round($principalPortion, 2),
                'interest_allocation' => round($interestPortion, 2),
                'fee_allocation' => round($feePortion, 2),
                'status' => 'active',
            ]);

            $this->updateInstallmentStatus($installment, $totalAllocated);

            $allocations[] = $allocation;
            $remainingAmount -= $totalAllocated;
        }

        return $allocations;
    }

    public function reverseAllocations(int $repaymentId): void
    {
        $allocations = LoanRepaymentAllocation::where('loan_repayment_id', $repaymentId)->get();

        foreach ($allocations as $allocation) {
            $installment = $allocation->installment;

            $newAmountPaid = max(0, (float) $installment->amount_paid - (float) $allocation->amount);
            $totalDue = (float) $installment->total_amount;

            if ($newAmountPaid >= $totalDue) {
                $status = LoanScheduleInstallmentStatus::Paid;
            } elseif ($newAmountPaid > 0) {
                $status = LoanScheduleInstallmentStatus::Partial;
            } else {
                $status = LoanScheduleInstallmentStatus::Pending;
            }

            $installment->update([
                'amount_paid' => round($newAmountPaid, 2),
                'outstanding_amount' => round(max(0, $totalDue - $newAmountPaid), 2),
                'status' => $status,
            ]);

            $allocation->update(['status' => 'reversed']);
        }
    }

    private function getInterestPaid(LoanRepaymentSchedule $installment): float
    {
        return LoanRepaymentAllocation::where('loan_repayment_schedule_id', $installment->id)
            ->whereHas('repayment', fn($q) => $q->where('status', 'posted'))
            ->sum('interest_allocation');
    }

    private function getPrincipalPaid(LoanRepaymentSchedule $installment): float
    {
        return LoanRepaymentAllocation::where('loan_repayment_schedule_id', $installment->id)
            ->whereHas('repayment', fn($q) => $q->where('status', 'posted'))
            ->sum('principal_allocation');
    }

    private function updateInstallmentStatus(LoanRepaymentSchedule $installment, float $totalAllocated): void
    {
        $newAmountPaid = (float) $installment->amount_paid + $totalAllocated;
        $totalDue = (float) $installment->total_amount;

        if ($newAmountPaid >= $totalDue) {
            $status = LoanScheduleInstallmentStatus::Paid;
        } elseif ($newAmountPaid > 0) {
            $status = LoanScheduleInstallmentStatus::Partial;
        } else {
            $status = LoanScheduleInstallmentStatus::Pending;
        }

        $installment->update([
            'amount_paid' => round($newAmountPaid, 2),
            'outstanding_amount' => round(max(0, $totalDue - $newAmountPaid), 2),
            'status' => $status,
            'paid_date' => now()->toDateString(),
        ]);
    }
}
