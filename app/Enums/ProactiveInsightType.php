<?php

namespace App\Enums;

/**
 * Detection category of a proactive insight (Phase 11.9). Each case maps to a
 * deterministic rule set over authoritative FinancePro records. Categories
 * mirror the platform domains the advisory layer watches; they never change a
 * financial record.
 */
enum ProactiveInsightType: string
{
    case OverdueLoan = 'overdue_loan';

    case MaturityPressure = 'maturity_pressure';

    case PortfolioPar = 'portfolio_par';

    case PortfolioConcentration = 'portfolio_concentration';

    case CashflowShortfall = 'cashflow_shortfall';

    case CollectionDecline = 'collection_decline';

    case SavingsDecline = 'savings_decline';

    case AccountingImbalance = 'accounting_imbalance';

    case OperationalGap = 'operational_gap';

    case PredictiveOutlookRisk = 'predictive_outlook';

    public function label(): string
    {
        return match ($this) {
            self::OverdueLoan => 'Overdue loan',
            self::MaturityPressure => 'Maturity concentration',
            self::PortfolioPar => 'Portfolio at risk',
            self::PortfolioConcentration => 'Loan concentration',
            self::CashflowShortfall => 'Cash-flow shortfall',
            self::CollectionDecline => 'Collection decline',
            self::SavingsDecline => 'Savings decline',
            self::AccountingImbalance => 'Accounting imbalance',
            self::OperationalGap => 'Operational gap',
            self::PredictiveOutlookRisk => 'Predictive outlook risk',
        };
    }
}
