<?php

namespace App\Services;

use App\Enums\RepaymentFrequency;

class FlatInterestCalculator
{
    public function calculate(float $principal, float $annualRate, int $termMonths, RepaymentFrequency $frequency): array
    {
        $rateDecimal = $annualRate / 100;
        $totalInterest = $principal * $rateDecimal * ($termMonths / 12);

        $periodsPerYear = $frequency->periodsPerYear();
        $totalInstallments = (int) ceil($termMonths * $periodsPerYear / 12);
        $principalPerInstallment = round($principal / max($totalInstallments, 1), 2);
        $interestPerInstallment = round($totalInterest / max($totalInstallments, 1), 2);
        $totalPerInstallment = round($principalPerInstallment + $interestPerInstallment, 2);

        $schedule = [];
        $remainingPrincipal = $principal;

        for ($i = 1; $i <= $totalInstallments; $i++) {
            if ($i === $totalInstallments) {
                $principalPerInstallment = round($remainingPrincipal, 2);
            }

            $outstanding = round($remainingPrincipal - $principalPerInstallment, 2);

            $schedule[] = [
                'installment_number' => $i,
                'principal_amount' => $principalPerInstallment,
                'interest_amount' => $interestPerInstallment,
                'total_amount' => round($principalPerInstallment + $interestPerInstallment, 2),
                'outstanding_amount' => max($outstanding, 0),
                'running_balance' => max($outstanding, 0),
            ];

            $remainingPrincipal -= $principalPerInstallment;
        }

        return [
            'total_interest' => round($totalInterest, 2),
            'total_amount' => round($principal + $totalInterest, 2),
            'total_installments' => $totalInstallments,
            'amount_per_installment' => $totalPerInstallment,
            'schedule' => $schedule,
        ];
    }
}
