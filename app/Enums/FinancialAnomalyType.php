<?php

namespace App\Enums;

/**
 * Deterministic rule-based anomaly categories produced by the Financial
 * Intelligence layer. Each type maps to exactly one read-only rule; findings
 * are always reported, never silently resolved or auto-waived.
 */
enum FinancialAnomalyType: string
{
    case UnusualLargeOverpayment = 'unusual_large_overpayment';
    case RepeatedReversal = 'repeated_reversal';
    case DelinquencyConcentration = 'delinquency_concentration';
    case ParConcentration = 'par_concentration';
    case LoanConcentration = 'loan_concentration';
    case NegativeCashPosition = 'negative_cash_position';
    case TrialBalanceUnbalanced = 'trial_balance_unbalanced';

    public function label(): string
    {
        return match ($this) {
            self::UnusualLargeOverpayment => 'Unusual Large Overpayment',
            self::RepeatedReversal => 'Repeated Repayment Reversal',
            self::DelinquencyConcentration => 'Delinquency Concentration',
            self::ParConcentration => 'Portfolio-at-Risk Concentration',
            self::LoanConcentration => 'Loan Concentration',
            self::NegativeCashPosition => 'Negative Cash Position',
            self::TrialBalanceUnbalanced => 'Unbalanced Trial Balance',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
