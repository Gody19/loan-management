<?php

namespace App\Services;

use App\Enums\RepaymentFrequency;

class ReducingBalanceInterestCalculator
{
    public function calculate(float $principal, float $annualRate, int $termMonths, RepaymentFrequency $frequency): array
    {
        $rateDecimal = $annualRate / 100;
        $periodsPerYear = $frequency->periodsPerYear();
        $totalInstallments = (int) ceil($termMonths * $periodsPerYear / 12);
        $periodicRate = $rateDecimal / $periodsPerYear;

        $totalInterest = 0;

        if ($periodicRate > 0) {
            $factor = pow(1 + $periodicRate, $totalInstallments);
            $paymentPerPeriod = $principal * ($periodicRate * $factor) / ($factor - 1);
        } else {
            $paymentPerPeriod = $principal / max($totalInstallments, 1);
        }

        $schedule = [];
        $remainingBalance = $principal;

        for ($i = 1; $i <= $totalInstallments; $i++) {
            $interestPortion = round($remainingBalance * $periodicRate, 2);

            if ($i === $totalInstallments) {
                $principalPortion = round($remainingBalance, 2);
                $totalPayment = round($principalPortion + $interestPortion, 2);
            } else {
                $totalPayment = round($paymentPerPeriod, 2);
                $principalPortion = round($totalPayment - $interestPortion, 2);
            }

            $outstanding = round($remainingBalance - $principalPortion, 2);

            $schedule[] = [
                'installment_number' => $i,
                'principal_amount' => $principalPortion,
                'interest_amount' => $interestPortion,
                'total_amount' => $totalPayment,
                'outstanding_amount' => max($outstanding, 0),
                'running_balance' => max($outstanding, 0),
            ];

            $totalInterest += $interestPortion;
            $remainingBalance -= $principalPortion;
        }

        return [
            'total_interest' => round($totalInterest, 2),
            'total_amount' => round($principal + $totalInterest, 2),
            'total_installments' => $totalInstallments,
            'amount_per_installment' => round($paymentPerPeriod, 2),
            'schedule' => $schedule,
        ];
    }
}
