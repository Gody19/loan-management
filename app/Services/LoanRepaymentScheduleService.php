<?php

namespace App\Services;

use App\Enums\InterestMethod;
use App\Enums\LoanScheduleInstallmentStatus;
use App\Enums\RepaymentFrequency;
use App\Models\Loan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LoanRepaymentScheduleService
{
    public function __construct(
        private readonly FlatInterestCalculator $flatCalculator,
        private readonly ReducingBalanceInterestCalculator $rbCalculator,
    ) {}

    public function generateSchedule(Loan $loan): array
    {
        $calculator = match ($loan->interest_method) {
            InterestMethod::Flat => $this->flatCalculator,
            InterestMethod::ReducingBalance => $this->rbCalculator,
        };

        $result = $calculator->calculate(
            (float) $loan->principal_amount,
            (float) $loan->interest_rate,
            $loan->term_months,
            $loan->repayment_frequency,
        );

        $scheduleRows = $this->buildScheduleRows($loan, $result['schedule']);

        DB::transaction(function () use ($loan, $scheduleRows, $result) {
            $loan->repaymentSchedule()->delete();

            foreach ($scheduleRows as $row) {
                $loan->repaymentSchedule()->create($row);
            }

            $loan->update([
                'total_interest' => $result['total_interest'],
                'total_amount' => $result['total_amount'],
                'total_installments' => $result['total_installments'],
                'outstanding_balance' => $loan->principal_amount,
            ]);
        });

        return $result;
    }

    public function getScheduleSummary(Loan $loan): array
    {
        $schedule = $loan->repaymentSchedule()->orderBy('installment_number')->get();

        $totalPrincipal = $schedule->sum('principal_amount');
        $totalInterest = $schedule->sum('interest_amount');
        $totalDue = $schedule->sum('total_amount');
        $totalPaid = $schedule->sum('amount_paid');
        $overdueCount = $schedule->where('status', LoanScheduleInstallmentStatus::Overdue)->count();

        return [
            'total_principal' => $totalPrincipal,
            'total_interest' => $totalInterest,
            'total_due' => $totalDue,
            'total_paid' => $totalPaid,
            'outstanding' => round($totalDue - $totalPaid, 2),
            'installments_total' => $schedule->count(),
            'installments_paid' => $schedule->where('status', LoanScheduleInstallmentStatus::Paid)->count(),
            'installments_overdue' => $overdueCount,
        ];
    }

    private function buildScheduleRows(Loan $loan, array $schedule): array
    {
        $disbursementDate = $loan->disbursement_date ?? Carbon::now();
        $rows = [];
        $runningBalance = (float) $loan->principal_amount;

        foreach ($schedule as $installment) {
            $dueDate = $this->calculateDueDate(
                $disbursementDate,
                $installment['installment_number'],
                $loan->repayment_frequency,
                $loan->grace_period,
            );

            $rows[] = [
                'organization_id' => $loan->organization_id,
                'installment_number' => $installment['installment_number'],
                'due_date' => $dueDate,
                'principal_amount' => $installment['principal_amount'],
                'interest_amount' => $installment['interest_amount'],
                'total_amount' => $installment['total_amount'],
                'amount_paid' => 0,
                'outstanding_amount' => $installment['total_amount'],
                'running_balance' => $installment['running_balance'],
                'status' => LoanScheduleInstallmentStatus::Pending,
                'days_overdue' => 0,
                'late_fee' => 0,
            ];

            $runningBalance = $installment['running_balance'];
        }

        return $rows;
    }

    private function calculateDueDate(Carbon $disbursementDate, int $installmentNumber, RepaymentFrequency $frequency, int $gracePeriod): Carbon
    {
        $date = $disbursementDate->copy()->addDays($gracePeriod);

        return match ($frequency) {
            RepaymentFrequency::Weekly => $date->addWeeks($installmentNumber),
            RepaymentFrequency::Biweekly => $date->addWeeks($installmentNumber * 2),
            RepaymentFrequency::Monthly => $date->addMonths($installmentNumber),
            RepaymentFrequency::Quarterly => $date->addMonths($installmentNumber * 3),
        };
    }
}
