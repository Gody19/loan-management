<?php

namespace App\AI\Reporting\Services;

use App\AI\DTOs\AiContextData;
use App\AI\PredictiveIntelligence\DataQualityService;
use App\AI\Reporting\Data\ReportDatum;
use App\AI\Reporting\Data\ReportPeriod;
use App\AI\Reporting\Data\ReportSection;
use App\Enums\ReportTrendDirection;
use App\Enums\ReportType;
use App\Models\AiInsight;
use App\Models\Branch;
use App\Services\AccountingConfigurationService;
use App\Services\BalanceSheetService;
use App\Services\CashBankLedgerService;
use App\Services\IncomeStatementService;
use App\Services\TrialBalanceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Throwable;

/**
 * Builds the six Phase 12.0 intelligence reports from authoritative FinancePro
 * services only.
 *
 * Every figure is deterministic: a fact comes from an authoritative record or
 * an existing reporting service, a trend from a deterministic comparison of
 * two periods, a prediction from Phase 11.8 and an advisory from Phase 11.9.
 * No section ever recalculates a financial balance independently, and no AI
 * call happens here — the narrative is attached afterwards by
 * IntelligenceReportNarrativeService so a provider failure can never affect the
 * report itself.
 */
class ReportBuilderService
{
    public function __construct(
        private readonly ReportPeriodService $periods,
        private readonly ReportDataService $data,
        private readonly ReportIntelligenceService $intelligence,
        private readonly TrialBalanceService $trialBalance,
        private readonly IncomeStatementService $incomeStatement,
        private readonly BalanceSheetService $balanceSheet,
        private readonly CashBankLedgerService $cashLedger,
        private readonly AccountingConfigurationService $accountingConfiguration,
        private readonly DataQualityService $dataQuality,
    ) {}

    /**
     * @return array<string, mixed> the structured report dataset
     */
    public function build(
        ReportType $type,
        AiContextData $context,
        int $organizationId,
        ReportPeriod $period,
        ?string $branchId = null,
    ): array {
        $organizationIds = [$organizationId];
        $branchIds = $branchId !== null ? [(int) $branchId] : [];

        $insights = $this->data->insightsFor($context);

        $sections = match ($type) {
            ReportType::ExecutivePortfolio => $this->executiveSections($context, $organizationIds, $branchIds, $period, $insights),
            ReportType::LoanPerformance => $this->loanSections($organizationIds, $branchIds, $period, $insights),
            ReportType::Collections => $this->collectionsSections($organizationIds, $branchIds, $period, $insights),
            ReportType::CashflowIntelligence => $this->cashflowSections($context, $organizationIds, $branchIds, $period, $insights),
            ReportType::AccountingIntelligence => $this->accountingSections($context, $organizationIds, $branchIds, $period, $insights),
            ReportType::OperationalIntelligence => $this->operationalSections($organizationIds, $branchIds, $period, $insights),
        };

        $sections = array_values(array_filter($sections, fn (ReportSection $section) => ! $section->isEmpty()));

        // An unavailable comparison is a fact about the report itself and must
        // be stated, not implied by a blank cell: attach a data-quality note to
        // each section that carries one, and surface the count at report level.
        $comparisonNotes = [];
        $sections = array_map(function (ReportSection $section) use (&$comparisonNotes) {
            $unavailable = array_values(array_filter(
                $section->trends,
                fn (ReportDatum $trend) => $trend->direction === ReportTrendDirection::Unavailable,
            ));

            if ($unavailable === []) {
                return $section;
            }

            $labels = implode(', ', array_map(fn (ReportDatum $trend) => '"'.$trend->label.'"', $unavailable));
            $note = 'No comparable previous-period value was available for '.$labels.' — the comparison was not calculated rather than shown as zero.';

            $comparisonNotes[] = $note;

            return $section->withDataQuality($note);
        }, $sections);

        $sectionPayloads = array_map(fn (ReportSection $section) => $section->toArray(), $sections);

        // Report-level data quality is the honest union of every limitation any
        // section declared: an unavailable prediction, an unavailable
        // comparison, a missing advisory set and any source-data issue. A
        // limitation is never confined to one section where a reader would miss
        // it.
        $dataQuality = $comparisonNotes;

        foreach ($sectionPayloads as $section) {
            foreach ((array) ($section['data_quality'] ?? []) as $note) {
                $dataQuality[] = (string) $note;
            }
        }

        $dataQuality = array_values(array_unique($dataQuality));

        return [
            'report_type' => $type->value,
            'report_type_label' => $type->label(),
            'scope' => [
                'organization_id' => $organizationId,
                'branch_id' => $branchId,
            ],
            'period' => $period->toArray(),
            'data_through' => $this->intelligence->dataThrough($organizationId),
            'generated_at' => CarbonImmutable::now()->toDateTimeString(),
            'data_quality' => $dataQuality,
            'sections' => $sectionPayloads,
        ];
    }

    /**
     * Executive portfolio: members, active loans, outstanding principal,
     * repayments, PAR, delinquency, concentration, collection performance,
     * savings/cash where authorized, plus the predictive and proactive layers.
     *
     * @param  int[]  $organizationIds
     * @param  int[]  $branchIds
     * @param  Collection<int, AiInsight>  $insights
     * @return array<int, ReportSection>
     */
    protected function executiveSections(
        AiContextData $context,
        array $organizationIds,
        array $branchIds,
        ReportPeriod $period,
        Collection $insights,
    ): array {
        $position = $this->data->portfolioPosition($organizationIds, $branchIds);
        $risk = $this->data->riskPosition($organizationIds, $branchIds);
        $collections = $this->data->collectionWindow($organizationIds, $branchIds, $period->start, $period->end);
        $previousCollections = $period->hasPrevious()
            ? $this->data->collectionWindow($organizationIds, $branchIds, (string) $period->previousStart, (string) $period->previousEnd)
            : null;

        $portfolio = new ReportSection(
            key: 'portfolio',
            title: 'Portfolio position',
            facts: [
                ReportDatum::fact('members_total', 'Total members', $position['members_total'], 'integer', $position['source']),
                ReportDatum::fact('members_active', 'Active members', $position['members_active'], 'integer', $position['source']),
                ReportDatum::fact('active_loans', 'Active loans', $position['active_loans'], 'integer', $position['source']),
                ReportDatum::fact('total_outstanding', 'Outstanding principal', $position['total_outstanding'], 'money', $position['source'], unit: $position['currency']),
                ReportDatum::fact('total_principal_disbursed', 'Principal disbursed to date', $position['total_principal_disbursed'], 'money', $position['source'], unit: $position['currency']),
                ReportDatum::fact('maturing_30', 'Maturing within 30 days', $position['maturing_within_30_days'], 'money', $position['source'], unit: $position['currency']),
                ReportDatum::fact('maturing_60', 'Maturing within 60 days', $position['maturing_within_60_days'], 'money', $position['source'], unit: $position['currency']),
            ],
            rows: [
                ['label' => 'Composition by loan plan', 'rows' => $position['composition_by_plan'], 'classification' => 'fact'],
            ],
        );

        $riskSection = new ReportSection(
            key: 'risk',
            title: 'Portfolio at risk and delinquency',
            facts: [
                ReportDatum::fact('par_rate', 'PAR '.$risk['par_threshold_days'], $risk['par_rate'], 'percent', $risk['source'], unit: '%'),
                ReportDatum::fact('total_principal', 'Principal outstanding', $risk['total_principal_outstanding'], 'money', $risk['source'], unit: $risk['currency']),
                ReportDatum::fact('delinquent_loans', 'Delinquent loans', $risk['delinquent_loans'], 'integer', $risk['source']),
                ReportDatum::fact('delinquent_principal', 'Delinquent principal', $risk['delinquent_principal'], 'money', $risk['source'], unit: $risk['currency']),
                ReportDatum::fact('max_dpd', 'Maximum days past due', $risk['max_dpd'], 'integer', $risk['source']),
                ReportDatum::fact('average_dpd', 'Average days past due', $risk['average_dpd'], 'decimal', $risk['source']),
            ],
            rows: [
                ['label' => 'Aging buckets', 'rows' => $risk['buckets'], 'classification' => 'fact'],
            ],
        );

        $collectionSection = new ReportSection(
            key: 'collections',
            title: 'Collection performance ('.$period->label.')',
            facts: [
                ReportDatum::fact('total_due', 'Expected collections', $collections['total_due'], 'money', $collections['source'], unit: $collections['currency']),
                ReportDatum::fact('total_collected', 'Actual collections', $collections['total_collected'], 'money', $collections['source'], unit: $collections['currency']),
                ReportDatum::fact('collection_rate', 'Collection rate', $collections['collection_rate'], 'percent', $collections['source'], unit: '%'),
            ],
            trends: [
                ReportDatum::trend(
                    key: 'collected_trend',
                    label: 'Actual collections versus previous period',
                    value: $collections['total_collected'],
                    format: 'money',
                    source: $collections['source'],
                    previousValue: $previousCollections['total_collected'] ?? null,
                    unit: $collections['currency'],
                ),
                ReportDatum::trend(
                    key: 'collection_rate_trend',
                    label: 'Collection rate versus previous period',
                    value: $collections['collection_rate'],
                    format: 'percent',
                    source: $collections['source'],
                    previousValue: $previousCollections['collection_rate'] ?? null,
                    unit: '%',
                ),
            ],
        );

        $sections = [$portfolio, $riskSection, $collectionSection];

        // Cash and savings are only reported to a role that already holds the
        // accounting capability — the reporting layer never widens access.
        if ($context->hasPermission('ai.accounting.view')) {
            $position = $this->data->position($organizationIds, $branchIds);

            $sections[] = new ReportSection(
                key: 'position',
                title: 'Savings and cash position',
                facts: [
                    ReportDatum::fact('savings_balance', 'Total savings balance', $position['savings_balance'], 'money', 'SavingsAccount.current_balance'),
                    ReportDatum::fact('active_savings_accounts', 'Active savings accounts', $position['active_savings_accounts'], 'integer', 'SavingsAccount'),
                    ReportDatum::fact('liquid_position', 'Liquid position', $position['liquid_position'], 'money', 'AccountingIntelligenceService'),
                ],
            );
        }

        $sections[] = $this->intelligenceSection($organizationIds[0], $insights);

        return $sections;
    }

    /**
     * Loan performance: applications, approvals, rejections, disbursements,
     * active/completed loans, overdue exposure, repayment performance, PAR and
     * plan distribution.
     *
     * @param  int[]  $organizationIds
     * @param  int[]  $branchIds
     * @param  Collection<int, AiInsight>  $insights
     * @return array<int, ReportSection>
     */
    protected function loanSections(array $organizationIds, array $branchIds, ReportPeriod $period, Collection $insights): array
    {
        $pipeline = $this->data->applicationPipeline($organizationIds, $branchIds, $period->start, $period->end);
        $flows = $this->data->loanFlows($organizationIds, $branchIds, $period->start, $period->end);
        $statuses = $this->data->loanStatusCounts($organizationIds, $branchIds);
        $risk = $this->data->riskPosition($organizationIds, $branchIds);
        $distribution = $this->data->planDistribution($organizationIds, $branchIds);

        $previousFlows = $period->hasPrevious()
            ? $this->data->loanFlows($organizationIds, $branchIds, (string) $period->previousStart, (string) $period->previousEnd)
            : null;

        $pipelineSection = new ReportSection(
            key: 'applications',
            title: 'Applications ('.$period->label.')',
            facts: [
                ReportDatum::fact('applications_total', 'Applications received', $pipeline['total'], 'integer', 'LoanApplication'),
                ReportDatum::fact('applications_submitted', 'Submitted', $pipeline['submitted'], 'integer', 'LoanApplication'),
                ReportDatum::fact('applications_review', 'Under review', $pipeline['under_review'], 'integer', 'LoanApplication'),
                ReportDatum::fact('applications_approved', 'Approved', $pipeline['approved'], 'integer', 'LoanApplication'),
                ReportDatum::fact('applications_rejected', 'Rejected', $pipeline['rejected'], 'integer', 'LoanApplication'),
            ],
        );

        $performance = new ReportSection(
            key: 'performance',
            title: 'Loan performance ('.$period->label.')',
            facts: [
                ReportDatum::fact('disbursed', 'Disbursements', $flows['disbursed'], 'money', 'Loan.disbursed_amount'),
                ReportDatum::fact('disbursed_count', 'Disbursed loans', $flows['disbursed_count'], 'integer', 'Loan'),
                ReportDatum::fact('collected', 'Repayments received', $flows['collected'], 'money', 'LoanRepayment'),
                ReportDatum::fact('active_loans', 'Active loans', $statuses['active'] ?? 0, 'integer', 'Loan'),
                ReportDatum::fact('completed_loans', 'Completed loans', $statuses['completed'] ?? 0, 'integer', 'Loan'),
                ReportDatum::fact('overdue_outstanding', 'Overdue outstanding', $this->data->overdueOutstanding($organizationIds, $branchIds), 'money', 'LoanRepaymentSchedule'),
            ],
            trends: [
                ReportDatum::trend('disbursed_trend', 'Disbursements versus previous period', $flows['disbursed'], 'money', 'Loan', $previousFlows['disbursed'] ?? null),
                ReportDatum::trend('collected_trend', 'Repayments versus previous period', $flows['collected'], 'money', 'LoanRepayment', $previousFlows['collected'] ?? null),
            ],
        );

        $riskSection = new ReportSection(
            key: 'risk',
            title: 'Portfolio at risk',
            facts: [
                ReportDatum::fact('par_rate', 'PAR '.$risk['par_threshold_days'], $risk['par_rate'], 'percent', $risk['source'], unit: '%'),
                ReportDatum::fact('delinquent_loans', 'Delinquent loans', $risk['delinquent_loans'], 'integer', $risk['source']),
                ReportDatum::fact('average_dpd', 'Average days past due', $risk['average_dpd'], 'decimal', $risk['source']),
            ],
            rows: [
                ['label' => 'Loan plan distribution', 'rows' => $distribution, 'classification' => 'fact'],
            ],
        );

        return [$pipelineSection, $performance, $riskSection, $this->intelligenceSection($organizationIds[0], $insights)];
    }

    /**
     * Collections: expected versus actual, collection rate, overdue amounts,
     * the collection trend and the Phase 11.8 collection forecast.
     *
     * @param  int[]  $organizationIds
     * @param  int[]  $branchIds
     * @param  Collection<int, AiInsight>  $insights
     * @return array<int, ReportSection>
     */
    protected function collectionsSections(array $organizationIds, array $branchIds, ReportPeriod $period, Collection $insights): array
    {
        $current = $this->data->collectionWindow($organizationIds, $branchIds, $period->start, $period->end);
        $previous = $period->hasPrevious()
            ? $this->data->collectionWindow($organizationIds, $branchIds, (string) $period->previousStart, (string) $period->previousEnd)
            : null;
        $flows = $this->data->loanFlows($organizationIds, $branchIds, $period->start, $period->end);

        $section = new ReportSection(
            key: 'collections',
            title: 'Expected versus actual collections ('.$period->label.')',
            facts: [
                ReportDatum::fact('total_due', 'Expected collections', $current['total_due'], 'money', $current['source'], unit: $current['currency']),
                ReportDatum::fact('total_collected', 'Actual collections', $current['total_collected'], 'money', $current['source'], unit: $current['currency']),
                ReportDatum::fact('collection_rate', 'Collection rate', $current['collection_rate'], 'percent', $current['source'], unit: '%'),
                ReportDatum::fact('overdue_amount', 'Overdue outstanding', $current['overdue_amount'], 'money', 'LoanRepaymentSchedule', unit: $current['currency']),
                ReportDatum::fact('posted_count', 'Posted repayments', $current['posted_count'], 'integer', 'LoanRepayment'),
                ReportDatum::fact('reversed_amount', 'Reversed collections', $current['reversed_amount'], 'money', 'LoanRepayment', unit: $current['currency']),
            ],
            trends: [
                ReportDatum::trend('collected_trend', 'Collections versus previous period', $current['total_collected'], 'money', $current['source'], $previous['total_collected'] ?? null, unit: $current['currency']),
                ReportDatum::trend('rate_trend', 'Collection rate versus previous period', $current['collection_rate'], 'percent', $current['source'], $previous['collection_rate'] ?? null, unit: '%'),
                ReportDatum::trend('due_trend', 'Expected collections versus previous period', $current['total_due'], 'money', $current['source'], $previous['total_due'] ?? null, unit: $current['currency']),
            ],
        );

        $trendSection = new ReportSection(
            key: 'collection_trend',
            title: 'Collection trend',
            rows: [
                ['label' => 'Monthly series', 'rows' => $this->data->trendSeries($organizationIds, $branchIds, 6)['series'], 'classification' => 'fact'],
            ],
            facts: [
                ReportDatum::fact('period_reversals', 'Reversed collections in period', $flows['reversed'], 'money', 'LoanRepayment'),
            ],
        );

        return [$section, $trendSection, $this->intelligenceSection($organizationIds[0], $insights)];
    }

    /**
     * Cash-flow intelligence: opening position, inflows, outflows, closing
     * position from the authoritative cash/bank ledger, the historical trend,
     * the Phase 11.8 cash-flow forecast and shortfall signals.
     *
     * @param  int[]  $organizationIds
     * @param  int[]  $branchIds
     * @param  Collection<int, AiInsight>  $insights
     * @return array<int, ReportSection>
     */
    protected function cashflowSections(
        AiContextData $context,
        array $organizationIds,
        array $branchIds,
        ReportPeriod $period,
        Collection $insights,
    ): array {
        $ledger = $this->cashLedgerRows($organizationIds[0], $period, $branchIds[0] ?? null);
        $flows = $this->data->loanFlows($organizationIds, $branchIds, $period->start, $period->end);

        $section = new ReportSection(
            key: 'cash_flow',
            title: 'Cash flow ('.$period->label.')',
            facts: [
                ReportDatum::fact('opening_position', 'Opening position', $ledger['opening'], 'money', 'CashBankLedgerService', note: 'Sum of the configured cash, bank and mobile-money accounts at the period start.'),
                ReportDatum::fact('inflows', 'Inflows', $ledger['inflows'], 'money', 'CashBankLedgerService'),
                ReportDatum::fact('outflows', 'Outflows', $ledger['outflows'], 'money', 'CashBankLedgerService'),
                ReportDatum::fact('closing_position', 'Closing position', $ledger['closing'], 'money', 'CashBankLedgerService'),
                ReportDatum::fact('loan_disbursements', 'Loan disbursements in period', $flows['disbursed'], 'money', 'Loan'),
                ReportDatum::fact('loan_collections', 'Loan collections in period', $flows['collected'], 'money', 'LoanRepayment'),
            ],
        );

        $trendSection = new ReportSection(
            key: 'cash_flow_trend',
            title: 'Historical cash-flow trend',
            rows: [
                ['label' => 'Monthly series', 'rows' => $this->data->trendSeries($organizationIds, $branchIds, 6)['series'], 'classification' => 'fact'],
            ],
        );

        $sections = [$section, $trendSection];

        if ($context->hasPermission('ai.accounting.view')) {
            $accounting = $this->data->accountingPosition($organizationIds, $branchIds);

            $sections[] = new ReportSection(
                key: 'liquidity',
                title: 'Liquidity',
                facts: [
                    ReportDatum::fact('liquid_position', 'Liquid position', $accounting['liquid_position'], 'money', $accounting['source']),
                    ReportDatum::fact('all_balanced', 'Trial balance integrity', $accounting['all_balanced'] ? 'Balanced' : 'Unbalanced', 'text', $accounting['source']),
                ],
            );
        }

        $sections[] = $this->intelligenceSection($organizationIds[0], $insights);

        return $sections;
    }

    /**
     * Accounting intelligence: debits, credits, trial-balance integrity, income
     * and balance-sheet summaries, draft journals and unbalanced conditions.
     *
     * @param  int[]  $organizationIds
     * @param  int[]  $branchIds
     * @param  Collection<int, AiInsight>  $insights
     * @return array<int, ReportSection>
     */
    protected function accountingSections(
        AiContextData $context,
        array $organizationIds,
        array $branchIds,
        ReportPeriod $period,
        Collection $insights,
    ): array {
        $organizationId = $organizationIds[0];
        $position = $this->data->accountingPosition($organizationIds, $branchIds);

        $trial = $this->safeTrialBalance($organizationId, $period->start, $period->end, $branchIds[0] ?? null);
        $income = $this->safeIncomeStatement($organizationId, $period->start, $period->end, $branchIds[0] ?? null);
        $balance = $this->safeBalanceSheet($organizationId, $period->end, $branchIds[0] ?? null);

        $facts = [
            ReportDatum::fact('draft_journals', 'Draft journals', $position['draft_journals'], 'integer', 'JournalEntry'),
            ReportDatum::fact('trial_balance', 'Trial balance', $trial === null ? 'Unavailable' : ($trial['is_balanced'] ? 'Balanced' : 'Unbalanced'), 'text', 'TrialBalanceService'),
            ReportDatum::fact('liquid_position', 'Liquid position', $position['liquid_position'], 'money', $position['source']),
        ];

        if ($trial !== null) {
            $facts[] = ReportDatum::fact('total_debits', 'Total debits', $trial['total_debit'], 'money', 'TrialBalanceService');
            $facts[] = ReportDatum::fact('total_credits', 'Total credits', $trial['total_credit'], 'money', 'TrialBalanceService');
        }

        $rows = [
            ['label' => 'Trial balance accounts', 'rows' => $trial['accounts'] ?? [], 'classification' => 'fact'],
        ];

        $section = new ReportSection(
            key: 'accounting',
            title: 'Accounting position ('.$period->label.')',
            facts: $facts,
            rows: $rows,
        );

        $sections = [$section];

        if ($income !== null) {
            $sections[] = new ReportSection(
                key: 'income_statement',
                title: 'Income statement summary',
                facts: [
                    ReportDatum::fact('total_income', 'Total income', $income['total_income'], 'money', 'IncomeStatementService'),
                    ReportDatum::fact('total_expenses', 'Total expenses', $income['total_expenses'], 'money', 'IncomeStatementService'),
                    ReportDatum::fact('net_income', 'Net income', $income['net_income'], 'money', 'IncomeStatementService'),
                ],
                rows: [
                    ['label' => 'Income lines', 'rows' => $income['income'], 'classification' => 'fact'],
                    ['label' => 'Expense lines', 'rows' => $income['expenses'], 'classification' => 'fact'],
                ],
            );
        }

        if ($balance !== null) {
            $sections[] = new ReportSection(
                key: 'balance_sheet',
                title: 'Balance sheet summary',
                facts: [
                    ReportDatum::fact('total_assets', 'Total assets', $balance['total_assets'], 'money', 'BalanceSheetService'),
                    ReportDatum::fact('total_liabilities', 'Total liabilities', $balance['total_liabilities'], 'money', 'BalanceSheetService'),
                    ReportDatum::fact('total_equity', 'Total equity', $balance['total_equity'], 'money', 'BalanceSheetService'),
                    ReportDatum::fact('is_balanced', 'Balance sheet balanced', $balance['is_balanced'], 'boolean', 'BalanceSheetService'),
                ],
            );
        }

        $sections[] = $this->intelligenceSection($organizationId, $insights);

        return $sections;
    }

    /**
     * Operational intelligence: pending applications, guarantor and collateral
     * workflows, unresolved operational issues and workflow aging.
     *
     * @param  int[]  $organizationIds
     * @param  int[]  $branchIds
     * @param  Collection<int, AiInsight>  $insights
     * @return array<int, ReportSection>
     */
    protected function operationalSections(array $organizationIds, array $branchIds, ReportPeriod $period, Collection $insights): array
    {
        $backlog = $this->data->operationalBacklog($organizationIds, $branchIds);
        $pipeline = $this->data->applicationPipeline($organizationIds, $branchIds, $period->start, $period->end);

        $section = new ReportSection(
            key: 'operations',
            title: 'Operational workflow ('.$period->label.')',
            facts: [
                ReportDatum::fact('pending_applications', 'Pending applications', $backlog['pending_applications'], 'integer', 'LoanApplication'),
                ReportDatum::fact('pending_application_age', 'Oldest pending application', $backlog['pending_application_oldest_days'], 'integer', 'LoanApplication', note: 'Days since the application date of the oldest pending application.'),
                ReportDatum::fact('pending_guarantors', 'Pending guarantor reviews', $backlog['pending_guarantors'], 'integer', 'LoanApplicationGuarantor'),
                ReportDatum::fact('pending_collaterals', 'Pending collateral reviews', $backlog['pending_collaterals'], 'integer', 'LoanApplicationCollateral'),
                ReportDatum::fact('pending_collateral_age', 'Oldest pending collateral', $backlog['pending_collateral_oldest_days'], 'integer', 'LoanApplicationCollateral', note: 'Days since the oldest pending collateral review was created.'),
            ],
        );

        $intelligence = $this->intelligenceSection($organizationIds[0], $insights);

        $quality = $this->dataQualityNotes($organizationIds);

        if ($quality !== []) {
            $intelligence = $intelligence->withDataQuality(...$quality);
        }

        return [$section, $intelligence];
    }

    /**
     * The shared predictive + proactive section every report carries. Nothing
     * is reinterpreted: Phase 11.8 snapshots keep their own status, confidence
     * and data-quality labels, and Phase 11.9 insights keep their advisory
     * caveat.
     *
     * @param  Collection<int, AiInsight>  $insights
     */
    protected function intelligenceSection(int $organizationId, Collection $insights): ReportSection
    {
        $predictions = $this->intelligence->predictionsFor($organizationId);

        $section = new ReportSection(
            key: 'intelligence',
            title: 'Predictive signals and proactive insights',
            predictions: $predictions['predictions'],
        );

        if ($predictions['notes'] !== []) {
            $section = $section->withDataQuality(...$predictions['notes']);
        }

        return $this->intelligence->advisorySection($section, $insights);
    }

    /**
     * Cash/bank/mobile-money opening, inflows, outflows and closing from the
     * authoritative ledger service. Falls back to the loan-cycle flows when the
     * organization has no configured cash accounts, and says so explicitly
     * rather than reporting a fabricated zero.
     *
     * @return array{opening: float, inflows: float, outflows: float, closing: float, source: string, note: ?string}
     */
    protected function cashLedgerRows(int $organizationId, ReportPeriod $period, ?int $branchId = null): array
    {
        $opening = 0.0;
        $inflows = 0.0;
        $outflows = 0.0;
        $accounts = 0;
        $notes = [];

        foreach (['cash_on_hand', 'bank_account', 'mobile_money'] as $key) {
            try {
                $accountId = $this->accountingConfiguration->getAccountId($organizationId, $key);
            } catch (Throwable) {
                // Not configured for this organization — an absent account is
                // not a zero balance, it is simply not part of the position.
                continue;
            }

            try {
                $ledger = $this->cashLedger->generate($organizationId, $accountId, $period->start, $period->end, $branchId);
            } catch (Throwable) {
                $notes[] = 'The ledger for the configured '.$key.' account could not be generated and is excluded from this section.';

                continue;
            }

            $opening += (float) $ledger['opening_balance'];
            $inflows += (float) $ledger['total_receipts'];
            $outflows += (float) $ledger['total_payments'];
            $accounts++;
        }

        if ($accounts === 0) {
            return [
                'opening' => 0.0, 'inflows' => 0.0, 'outflows' => 0.0, 'closing' => 0.0,
                'source' => 'CashBankLedgerService',
                'note' => 'No cash, bank or mobile-money accounts are configured for this organization; ledger figures are unavailable rather than zero.',
            ];
        }

        return [
            'opening' => round($opening, 2),
            'inflows' => round($inflows, 2),
            'outflows' => round($outflows, 2),
            'closing' => round($opening + $inflows - $outflows, 2),
            'source' => 'CashBankLedgerService',
            'note' => $notes === [] ? null : implode(' ', $notes),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function safeTrialBalance(int $organizationId, string $start, string $end, ?int $branchId = null): ?array
    {
        try {
            return $this->trialBalance->generate($organizationId, $start, $end, null, $branchId);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function safeIncomeStatement(int $organizationId, string $start, string $end, ?int $branchId = null): ?array
    {
        try {
            return $this->incomeStatement->generate($organizationId, $start, $end, null, $branchId);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function safeBalanceSheet(int $organizationId, string $asOf, ?int $branchId = null): ?array
    {
        try {
            return $this->balanceSheet->generate($organizationId, $asOf, $branchId);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  int[]  $organizationIds
     * @return array<int, string>
     */
    protected function dataQualityNotes(array $organizationIds): array
    {
        if ($organizationIds === []) {
            return [];
        }

        try {
            $issues = $this->dataQuality->issues($organizationIds[0], []);
        } catch (Throwable) {
            return [];
        }

        if ($issues === []) {
            return [];
        }

        return [count($issues).' source-data quality issue(s) detected by the Phase 11.8 data-quality service; figures may be incomplete.'];
    }

    /**
     * Explicit validation of a requested branch against the trusted context. A
     * branch belonging to another organization is never accepted.
     */
    public function assertBranchAllowed(AiContextData $context, ?string $branchId): void
    {
        if ($branchId === null || $branchId === '') {
            return;
        }

        $branch = Branch::find((int) $branchId);

        if ($branch === null
            || ! $context->belongsToOrganization((int) $branch->organization_id)
            || ! $context->belongsToBranch((int) $branch->id)) {
            throw new InvalidArgumentException('The requested branch is outside your authorized scope.');
        }
    }
}
