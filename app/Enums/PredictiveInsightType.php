<?php

namespace App\Enums;

/**
 * The predictive-insight domains (Phase 11.8). Every value maps to one
 * deterministic statistical service; there is no provider, ML model or
 * arbitrary extrapolation behind them. All of them are advisory indications
 * computed from authoritative historical FinancePro records — never
 * guarantees, and never inputs to loan eligibility, approval, rates or
 * accounting business rules.
 */
enum PredictiveInsightType: string
{
    case PortfolioForecast = 'portfolio_forecast';
    case DelinquencyRisk = 'delinquency_risk';
    case CashflowForecast = 'cashflow_forecast';
    case CollectionForecast = 'collection_forecast';

    public function label(): string
    {
        return match ($this) {
            self::PortfolioForecast => 'Portfolio forecast',
            self::DelinquencyRisk => 'Delinquency risk',
            self::CashflowForecast => 'Cash-flow forecast',
            self::CollectionForecast => 'Collection forecast',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
