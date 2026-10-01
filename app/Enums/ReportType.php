<?php

namespace App\Enums;

/**
 * The reporting type of an intelligence report (Phase 12.0). Each type has a
 * fixed, authorized section composition built from existing FinancePro
 * reporting services; a report never assembles its own financial figures.
 */
enum ReportType: string
{
    case ExecutivePortfolio = 'executive_portfolio';

    case LoanPerformance = 'loan_performance';

    case Collections = 'collections';

    case CashflowIntelligence = 'cashflow_intelligence';

    case AccountingIntelligence = 'accounting_intelligence';

    case OperationalIntelligence = 'operational_intelligence';

    public function label(): string
    {
        return match ($this) {
            self::ExecutivePortfolio => 'Executive portfolio report',
            self::LoanPerformance => 'Loan performance report',
            self::Collections => 'Collections report',
            self::CashflowIntelligence => 'Cash-flow intelligence report',
            self::AccountingIntelligence => 'Accounting intelligence report',
            self::OperationalIntelligence => 'Operational intelligence report',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ExecutivePortfolio => 'Members, active loans, outstanding principal, repayments, portfolio at risk, delinquency, concentration, collections and — where authorized — savings and cash position, with the related predictive signals and proactive insights.',
            self::LoanPerformance => 'Applications, approvals, rejections, disbursements, active and completed loans, overdue exposure, repayment performance, portfolio at risk and loan-plan distribution.',
            self::Collections => 'Expected versus actual collections, collection rate, overdue amounts, the collection trend and the Phase 11.8 collection outlook.',
            self::CashflowIntelligence => 'Opening position, inflows, outflows and closing position from the authoritative cash/bank ledger, with the historical trend, the Phase 11.8 cash-flow forecast and any shortfall signal.',
            self::AccountingIntelligence => 'Debits, credits, trial-balance integrity, income-statement and balance-sheet summaries, draft journals and unbalanced conditions, with the related accounting advisories.',
            self::OperationalIntelligence => 'Pending applications, guarantor and collateral workflows, data-quality issues and workflow aging, with the related operational advisories.',
        };
    }

    /**
     * Whether the report requires the accounting capability. The reporting
     * layer never widens a role's domain access: an accounting report is
     * authorized only for a holder of ai.accounting.view.
     */
    public function requiresAccountingCapability(): bool
    {
        return $this === self::AccountingIntelligence;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
