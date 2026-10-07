<?php

namespace Database\Seeders;

use App\Enums\AiAnomalyFindingStatus;
use App\Enums\FinancialAnomalyType;
use App\Enums\FinancialFindingSeverity;
use App\Enums\PredictionConfidence;
use App\Enums\PredictiveInsightType;
use App\Enums\ProactiveInsightSeverity;
use App\Enums\ProactiveInsightSource;
use App\Enums\ProactiveInsightStatus;
use App\Enums\ProactiveInsightType;
use App\Enums\ReportPeriodType;
use App\Enums\ReportScheduleFrequency;
use App\Enums\ReportScheduleRecipientMode;
use App\Enums\ReportScheduleRunStatus;
use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Models\AiAnomalyFinding;
use App\Models\AiInsight;
use App\Models\AiIntelligenceReport;
use App\Models\AiPrediction;
use App\Models\AiReportSchedule;
use App\Models\Branch;
use App\Models\Loan;
use App\Models\Member;
use Database\Seeders\Concerns\FillsTables;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AiIntelligenceSeeder extends Seeder
{
    use FillsTables;

    public function run(): void
    {
        $this->modelVersions();
        $this->insights();
        $this->predictions();
        $this->anomalies();
        $this->reports();
        $this->schedules();
        $this->scheduleRuns();

        $this->report('AI intelligence ready.');
    }

    private function modelVersions(): void
    {
        $versions = [
            ['openai', 'gpt-4o-mini', 'Advisory model (mini)', 'gpt-4o-mini-2024-07-18', 'active'],
            ['anthropic', 'claude-3-5-haiku', 'Advisory model (fast)', 'claude-3-5-haiku-latest', 'active'],
            ['google', 'gemini-1.5-pro', 'Narrative model', 'gemini-1.5-pro-002', 'deprecated'],
        ];

        foreach ($versions as $version) {
            if (DB::table('ai_model_versions')->where('version', $version[3])->exists()) {
                continue;
            }

            DB::table('ai_model_versions')->insert([
                'provider' => $version[0],
                'model' => $version[1],
                'display_name' => $version[2],
                'version' => $version[3],
                'status' => $version[4],
                'configuration' => json_encode(['temperature' => 0.2, 'max_tokens' => 1200, 'retries' => 2]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->bump('ai_model_versions');
        }
    }

    private function insights(): void
    {
        if ($this->gap('ai_insights') <= 0) {
            return;
        }

        $branches = Branch::where('organization_id', self::ORG_ID)->orderBy('id')->pluck('id')->all();
        $loans = Loan::where('organization_id', self::ORG_ID)->orderBy('id')->pluck('id')->all();
        $members = Member::where('organization_id', self::ORG_ID)->orderBy('id')->pluck('id')->all();

        $definitions = [
            [ProactiveInsightType::OverdueLoan, ProactiveInsightSeverity::Critical, 'Loan instalments overdue beyond 30 days', 'member', 'Schedule instalments have passed the due date without full settlement.', 'Escalate to the collections team and agree a repayment plan with the member.'],
            [ProactiveInsightType::OverdueLoan, ProactiveInsightSeverity::Warning, 'Instalment slippage on a business loan', 'member', 'Payments are consistently a few days late on this loan.', 'Schedule an early reminder call before the next due date.'],
            [ProactiveInsightType::MaturityPressure, ProactiveInsightSeverity::Warning, 'Concentrated loan maturities next month', 'loan', 'Several loans reach maturity in the same month.', 'Prepare renewal offers early and stagger the review workload.'],
            [ProactiveInsightType::PortfolioPar, ProactiveInsightSeverity::Warning, 'Portfolio at risk is trending upwards', 'loan', 'The share of the portfolio in arrears has increased this period.', 'Review the largest exposed balances and tighten approval criteria.'],
            [ProactiveInsightType::PortfolioConcentration, ProactiveInsightSeverity::Notice, 'Loan concentration in one product', 'loan', 'A single loan product holds a large share of disbursed value.', 'Encourage uptake of other approved products to spread risk.'],
            [ProactiveInsightType::CashflowShortfall, ProactiveInsightSeverity::Warning, 'Expected cash inflow below obligations', 'accounting', 'Expected collections do not cover scheduled repayments this week.', 'Defer non-critical disbursements until inflows settle.'],
            [ProactiveInsightType::CollectionDecline, ProactiveInsightSeverity::Notice, 'Collection rate declining for two periods', 'accounting', 'Recovered amounts are lower than the previous two periods.', 'Review the arrears list and re-engage early-stage defaulters.'],
            [ProactiveInsightType::SavingsDecline, ProactiveInsightSeverity::Notice, 'Group savings deposits falling short of target', 'savings', 'Group savings contributions are below the monthly target.', 'Reinforce the savings discipline at the next group meeting.'],
            [ProactiveInsightType::AccountingImbalance, ProactiveInsightSeverity::Critical, 'Trial balance out of balance', 'accounting', 'Debit and credit totals do not agree for the current period.', 'Reconcile the affected journal entries before closing the period.'],
            [ProactiveInsightType::OperationalGap, ProactiveInsightSeverity::Info, 'Members missing verified documents', 'member', 'Several active members have incomplete documentation.', 'Send a documentation reminder during the next membership meeting.'],
            [ProactiveInsightType::PredictiveOutlookRisk, ProactiveInsightSeverity::Critical, 'Forecast delinquency risk above tolerance', 'prediction', 'The delinquency forecast exceeds the internal risk tolerance.', 'Hold new disbursements for the at-risk segment until reviewed.'],
            [ProactiveInsightType::PredictiveOutlookRisk, ProactiveInsightSeverity::Notice, 'Forecast collections softening', 'prediction', 'Projected collections are lower than the trailing average.', 'Monitor weekly and prepare the collections contingency plan.'],
        ];

        $statuses = [
            ProactiveInsightStatus::New,
            ProactiveInsightStatus::Read,
            ProactiveInsightStatus::Acknowledged,
            ProactiveInsightStatus::Resolved,
            ProactiveInsightStatus::Dismissed,
            ProactiveInsightStatus::New,
            ProactiveInsightStatus::Acknowledged,
            ProactiveInsightStatus::Expired,
        ];

        $sequence = DB::table('ai_insights')->count();

        foreach ($definitions as $index => [$type, $severity, $title, $anchor, $summary, $recommendation]) {
            if ($this->gap('ai_insights') <= 0) {
                break;
            }

            $source = ProactiveInsightSource::from($anchor);
            $sourceId = match ($source) {
                ProactiveInsightSource::Loan => $loans[$index % max(1, count($loans))] ?? null,
                ProactiveInsightSource::Member => $members[$index % max(1, count($members))] ?? null,
                ProactiveInsightSource::Savings, ProactiveInsightSource::Accounting => $members[$index % max(1, count($members))] ?? null,
                default => self::ORG_ID,
            };

            $status = $statuses[$index % count($statuses)];
            $generatedAt = $this->daysAgo(45, 1);
            $acknowledged = in_array($status, [ProactiveInsightStatus::Acknowledged, ProactiveInsightStatus::Resolved, ProactiveInsightStatus::Dismissed], true);
            $resolved = $status === ProactiveInsightStatus::Resolved;
            $dismissed = $status === ProactiveInsightStatus::Dismissed;
            $expired = $status === ProactiveInsightStatus::Expired;
            $sequence++;

            $dedupKey = sprintf('insight-%d-%s', self::ORG_ID, substr(md5($type->value.$title.$sourceId.'#'.$index), 0, 16));

            if (DB::table('ai_insights')->where('dedup_key', $dedupKey)->exists()) {
                continue;
            }

            AiInsight::create([
                'organization_id' => self::ORG_ID,
                'branch_id' => $branches[$index % max(1, count($branches))] ?? null,
                'type' => $type->value,
                'severity' => $severity->value,
                'title' => $title,
                'summary' => $summary,
                'recommendation' => $recommendation,
                'source_type' => $source->value,
                'source_id' => $sourceId,
                'object_type' => $source->label(),
                'object_id' => $sourceId,
                'dedup_key' => $dedupKey,
                'status' => $status->value,
                'period_start' => $generatedAt->copy()->subDays(30)->toDateString(),
                'period_end' => $generatedAt->copy()->toDateString(),
                'metadata' => [
                    'anchor' => $source->value,
                    'exposure' => $this->money(500000, 25000000, 250000),
                    'members_affected' => fake()->numberBetween(1, 24),
                    'rule_version' => 'phase11.9',
                    'confidence' => fake()->numberBetween(55, 96),
                ],
                'generated_at' => $generatedAt,
                'data_through' => $generatedAt->copy()->toDateString(),
                'acknowledged_by' => $acknowledged ? self::ACTOR_ID : null,
                'acknowledged_at' => $acknowledged ? $generatedAt->copy()->addHours(20) : null,
                'resolved_by' => $resolved ? self::ACTOR_ID : null,
                'resolved_at' => $resolved ? $generatedAt->copy()->addDays(3) : null,
                'dismissed_by' => $dismissed ? self::ACTOR_ID : null,
                'dismissed_at' => $dismissed ? $generatedAt->copy()->addDays(1) : null,
                'expired_at' => $expired ? $generatedAt->copy()->addDays(14) : null,
            ]);

            $this->bump('ai_insights');
        }
    }

    private function predictions(): void
    {
        $through = now()->subDay()->toDateString();

        $definitions = [
            [PredictiveInsightType::PortfolioForecast, 'trailing_average', 'expected_value', 'generated', 'good', 'Projected portfolio balance based on the trailing twelve months.'],
            [PredictiveInsightType::DelinquencyRisk, 'weighted_average', 'probability', 'generated', 'good', 'Probability that an instalment is missed over the next three months.'],
            [PredictiveInsightType::CashflowForecast, 'moving_average', 'expected_cash', 'generated', 'limited', 'Expected cash inflow over the next two months.'],
            [PredictiveInsightType::CollectionForecast, 'trend', 'expected_collection', 'generated', 'good', 'Projected collections compared with amounts due in the same period.'],
            [PredictiveInsightType::DelinquencyRisk, 'segment_average', 'probability', 'generated', 'limited', 'Delinquency probability for the agriculture segment.'],
            [PredictiveInsightType::CashflowForecast, 'expanding_window', 'expected_cash', 'generated', 'insufficient', 'Cash forecast with limited history for the newest branch.'],
            [PredictiveInsightType::PortfolioForecast, 'linear_trend', 'expected_value', 'superseded', 'good', 'Earlier portfolio projection retained for comparison.'],
            [PredictiveInsightType::CollectionForecast, 'seasonal_trend', 'expected_collection', 'generated', 'good', 'Collection projection for education loans.'],
        ];

        foreach ($definitions as $index => [$type, $method, $scope, $status, $quality, $explanation]) {
            if ($this->gap('ai_predictions') <= 0) {
                break;
            }

            $dataThrough = $through;

            if (DB::table('ai_predictions')->where('organization_id', self::ORG_ID)
                ->where('type', $type->value)->where('method', $method)->where('data_through', $dataThrough)->exists()) {
                continue;
            }

            $series = [];
            $point = now()->startOfMonth()->subMonths(6);

            for ($month = 0; $month < 9; $month++) {
                $series[] = [
                    'period' => $point->format('Y-m'),
                    'value' => round(fake()->numberBetween(800000, 9000000) + $month * 150000, 2),
                ];
                $point = $point->copy()->addMonth();
            }

            AiPrediction::create([
                'organization_id' => self::ORG_ID,
                'type' => $type->value,
                'status' => $status,
                'scope' => $scope,
                'method' => $method,
                'model_version' => 'gpt-4o-mini-2024-07-18',
                'target_period' => now()->addMonth()->format('Y-m'),
                'data_through' => $dataThrough,
                'data_from' => now()->subMonths(6)->toDateString(),
                'horizon' => fake()->numberBetween(1, 3),
                'confidence' => fake()->randomElement(PredictionConfidence::cases())->value,
                'data_quality' => $quality,
                'value_total' => round(array_sum(array_column($series, 'value')) / count($series), 2),
                'currency' => 'TZS',
                'series' => $series,
                'factors' => [
                    'seasonality' => fake()->numberBetween(10, 40),
                    'member_growth' => fake()->numberBetween(1, 12),
                    'repayment_history' => fake()->numberBetween(60, 99),
                ],
                'assumptions' => [
                    'no new disbursement restrictions',
                    'constant interest rates',
                    'collection effort unchanged',
                ],
                'explanation' => $explanation,
                'generated_by' => self::ACTOR_ID,
                'generated_at' => now()->subHours(fake()->numberBetween(1, 72)),
            ]);

            $this->bump('ai_predictions');
        }
    }

    private function anomalies(): void
    {
        $branches = Branch::where('organization_id', self::ORG_ID)->orderBy('id')->pluck('id')->all();
        $members = Member::where('organization_id', self::ORG_ID)->orderBy('id')->pluck('id')->all();
        $loans = Loan::where('organization_id', self::ORG_ID)->orderBy('id')->pluck('id')->all();

        $definitions = [
            [FinancialAnomalyType::UnusualLargeOverpayment, FinancialFindingSeverity::Low, 'Unusual overpayment on a loan receipt', 'The overpayment exceeds the amount expected for the settled schedule.'],
            [FinancialAnomalyType::RepeatedReversal, FinancialFindingSeverity::Medium, 'Repeated repayment reversals for one loan', 'Several repayments on the same loan were reversed within a short window.'],
            [FinancialAnomalyType::DelinquencyConcentration, FinancialFindingSeverity::High, 'Delinquency concentrated in one branch', 'Arrears are concentrated in a single branch this period.'],
            [FinancialAnomalyType::ParConcentration, FinancialFindingSeverity::High, 'Portfolio at risk concentrated in one product', 'One loan product accounts for most of the at-risk balance.'],
            [FinancialAnomalyType::LoanConcentration, FinancialFindingSeverity::Medium, 'Single member exposure above policy limit', 'A member holds an exposure close to the concentration limit.'],
            [FinancialAnomalyType::NegativeCashPosition, FinancialFindingSeverity::High, 'Cash position negative for the day', 'Closing cash fell below the outstanding obligations for the day.'],
            [FinancialAnomalyType::TrialBalanceUnbalanced, FinancialFindingSeverity::Critical, 'Trial balance out of balance for the period', 'Total debits do not equal total credits in the current period.'],
        ];

        $statuses = [AiAnomalyFindingStatus::Detected, AiAnomalyFindingStatus::Detected, AiAnomalyFindingStatus::Reviewed, AiAnomalyFindingStatus::Reviewed];

        foreach ($definitions as $index => [$type, $severity, $title, $description]) {
            if ($this->gap('ai_anomaly_findings') <= 0) {
                break;
            }

            $detectionDate = $this->daysAgo(30, 1)->toDateString();
            $sourceType = $type === FinancialAnomalyType::TrialBalanceUnbalanced || $type === FinancialAnomalyType::NegativeCashPosition
                ? 'organization'
                : 'loan';
            $sourceId = $sourceType === 'loan' ? ($loans[$index % max(1, count($loans))] ?? null) : self::ORG_ID;
            $status = $statuses[$index % count($statuses)];
            $reviewed = $status !== AiAnomalyFindingStatus::Detected;

            if (DB::table('ai_anomaly_findings')->where('organization_id', self::ORG_ID)
                ->where('type', $type->value)->where('source_type', $sourceType)
                ->where('source_id', $sourceId)->where('detection_date', $detectionDate)->exists()) {
                continue;
            }

            AiAnomalyFinding::create([
                'organization_id' => self::ORG_ID,
                'branch_id' => $branches[$index % max(1, count($branches))] ?? null,
                'member_id' => $sourceType === 'loan' ? ($members[$index % max(1, count($members))] ?? null) : null,
                'loan_id' => $sourceType === 'loan' ? $sourceId : null,
                'type' => $type->value,
                'severity' => $severity->value,
                'title' => $title,
                'description' => $description,
                'amount' => $this->money(250000, 15000000, 100000),
                'currency' => 'TZS',
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'metadata' => [
                    'rule' => $type->value,
                    'threshold' => $this->money(100000, 5000000, 50000),
                    'observed' => $this->money(100000, 6000000, 50000),
                    'branch_reviewed' => $reviewed,
                ],
                'detection_date' => $detectionDate,
                'detected_at' => $this->daysAgo(30, 1),
                'status' => $status->value,
                'reviewed_by' => $reviewed ? self::ACTOR_ID : null,
                'reviewed_at' => $reviewed ? now()->subDays(fake()->numberBetween(1, 10)) : null,
            ]);

            $this->bump('ai_anomaly_findings');
        }
    }

    private function reports(): void
    {
        $branches = Branch::where('organization_id', self::ORG_ID)->orderBy('id')->pluck('id')->all();
        $definitions = [
            [ReportType::ExecutivePortfolio, ReportPeriodType::ThisMonth],
            [ReportType::LoanPerformance, ReportPeriodType::ThisMonth],
            [ReportType::Collections, ReportPeriodType::ThisWeek],
            [ReportType::CashflowIntelligence, ReportPeriodType::ThisMonth],
            [ReportType::AccountingIntelligence, ReportPeriodType::ThisQuarter],
            [ReportType::OperationalIntelligence, ReportPeriodType::ThisMonth],
        ];

        foreach ($definitions as $index => [$type, $periodType]) {
            if ($this->gap('ai_intelligence_reports') <= 0) {
                break;
            }

            [$periodStart, $periodEnd] = $this->periodBounds($periodType);
            $completed = $index % 6 !== 5;
            $generatedAt = now()->subDays(fake()->numberBetween(1, 20));

            AiIntelligenceReport::create([
                'organization_id' => self::ORG_ID,
                'branch_id' => $index % 2 === 0 ? ($branches[$index % max(1, count($branches))] ?? null) : null,
                'report_type' => $type->value,
                'status' => $completed ? ReportStatus::Completed->value : ReportStatus::Failed->value,
                'period_type' => $periodType->value,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'previous_period_start' => $periodStart->copy()->sub($periodStart->diffInDays($periodEnd) + 1, 'days')->toDateString(),
                'previous_period_end' => $periodStart->copy()->subDay()->toDateString(),
                'data_through' => $periodEnd->copy()->toDateString(),
                'generated_at' => $generatedAt,
                'requested_by' => self::ACTOR_ID,
                'report_data' => [
                    'metrics' => [
                        'disbursed' => $this->money(5000000, 120000000, 100000),
                        'collected' => $this->money(4000000, 110000000, 100000),
                        'outstanding' => $this->money(20000000, 400000000, 100000),
                        'par_ratio' => round(fake()->numberBetween(2, 28) / 10, 2),
                        'members_active' => fake()->numberBetween(10, 60),
                    ],
                    'trend' => [
                        'disbursed' => fake()->randomElement(['up', 'down', 'flat']),
                        'collected' => fake()->randomElement(['up', 'down', 'flat']),
                        'par_ratio' => fake()->randomElement(['up', 'down', 'flat']),
                    ],
                ],
                'narrative' => $completed ? 'Portfolio performance for the period was broadly stable with a small decline in collections. The largest exposures remain within policy limits, and delinquency is concentrated in two branches.' : null,
                'ai_generated' => $completed,
                'failure_reason' => $completed ? null : 'Report generation exceeded the allowed execution time.',
            ]);

            $this->bump('ai_intelligence_reports');
        }
    }

    private function schedules(): void
    {
        if ($this->gap('ai_report_schedules') <= 0) {
            return;
        }

        $definitions = [
            ['Monday executive portfolio digest', ReportType::ExecutivePortfolio, ReportScheduleFrequency::Weekly, '08:00:00', 1],
            ['Daily collections summary', ReportType::Collections, ReportScheduleFrequency::Daily, '17:30:00', null],
            ['Monthly loan performance review', ReportType::LoanPerformance, ReportScheduleFrequency::Monthly, '09:00:00', null],
            ['Quarterly accounting intelligence', ReportType::AccountingIntelligence, ReportScheduleFrequency::Quarterly, '10:00:00', null],
            ['Weekly cashflow watch', ReportType::CashflowIntelligence, ReportScheduleFrequency::Weekly, '07:45:00', 3],
        ];

        foreach ($definitions as $index => [$name, $type, $frequency, $runTime, $weekday]) {
            if ($this->gap('ai_report_schedules') <= 0) {
                break;
            }

            if (DB::table('ai_report_schedules')->where('organization_id', self::ORG_ID)->where('name', $name)->exists()) {
                continue;
            }

            DB::table('ai_report_schedules')->insert([
                'organization_id' => self::ORG_ID,
                'branch_id' => null,
                'name' => $name,
                'report_type' => $type->value,
                'frequency' => $frequency->value,
                'run_time' => $runTime,
                'weekday' => $weekday,
                'day_of_month' => $frequency === ReportScheduleFrequency::Monthly || $frequency === ReportScheduleFrequency::Quarterly ? 1 : null,
                'timezone' => self::TZ,
                'recipient_mode' => ReportScheduleRecipientMode::SpecificUsers->value,
                'recipients' => json_encode([['type' => 'role', 'value' => 'Organization Administrator'], ['type' => 'user', 'value' => self::ACTOR_ID]]),
                'include_narrative' => $index % 2 === 0,
                'is_active' => $index % 4 !== 3,
                'next_run_at' => now()->addDays($index + 1)->setTime(8, 0),
                'last_run_at' => now()->subDays(fake()->numberBetween(2, 14)),
                'created_by' => self::ACTOR_ID,
                'updated_by' => self::ACTOR_ID,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->bump('ai_report_schedules');
        }
    }

    private function scheduleRuns(): void
    {
        $schedules = AiReportSchedule::where('organization_id', self::ORG_ID)->orderBy('id')->get();
        $reports = AiIntelligenceReport::where('organization_id', self::ORG_ID)->orderBy('id')->get();

        foreach ($schedules as $index => $schedule) {
            if ($this->gap('ai_report_schedule_runs') <= 0) {
                break;
            }

            $report = $reports->first();
            $failed = $index % 4 === 2;
            $startedAt = now()->subDays(fake()->numberBetween(2, 25));
            $executionKey = $schedule->id.'-'.$startedAt->format('Ymd-His');

            if (DB::table('ai_report_schedule_runs')->where('execution_key', $executionKey)->exists()) {
                continue;
            }

            DB::table('ai_report_schedule_runs')->insert([
                'ai_report_schedule_id' => $schedule->id,
                'ai_intelligence_report_id' => $failed ? null : $report?->id,
                'execution_key' => $executionKey,
                'trigger' => 'schedule',
                'status' => $failed ? ReportScheduleRunStatus::Failed->value : ReportScheduleRunStatus::Completed->value,
                'period_type' => ReportPeriodType::PreviousMonth->value,
                'period_start' => now()->subMonth()->startOfMonth()->toDateString(),
                'period_end' => now()->subMonth()->endOfMonth()->toDateString(),
                'timezone' => self::TZ,
                'notifications_sent' => $failed ? 0 : fake()->numberBetween(1, 3),
                'digest_available' => ! $failed,
                'failure_reason' => $failed ? 'Model provider returned an error for the narrative step.' : null,
                'requested_by' => self::ACTOR_ID,
                'started_at' => $startedAt,
                'completed_at' => $failed ? null : $startedAt->copy()->addMinutes(fake()->numberBetween(2, 20)),
                'created_at' => $startedAt,
                'updated_at' => $failed ? $startedAt : $startedAt->copy()->addMinutes(20),
            ]);

            $this->bump('ai_report_schedule_runs');
        }
    }

    private function periodBounds(ReportPeriodType $periodType): array
    {
        return match ($periodType) {
            ReportPeriodType::Today => [now()->startOfDay(), now()->endOfDay()],
            ReportPeriodType::Yesterday => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            ReportPeriodType::ThisWeek => [now()->startOfWeek(), now()->endOfWeek()],
            ReportPeriodType::PreviousWeek => [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()],
            ReportPeriodType::ThisMonth => [now()->startOfMonth(), now()->endOfMonth()],
            ReportPeriodType::PreviousMonth => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            ReportPeriodType::ThisQuarter => [now()->startOfQuarter(), now()->endOfQuarter()],
            ReportPeriodType::PreviousQuarter => [now()->subQuarter()->startOfQuarter(), now()->subQuarter()->endOfQuarter()],
            ReportPeriodType::ThisYear => [now()->startOfYear(), now()->endOfYear()],
            default => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
        };
    }
}
