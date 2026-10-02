<?php

namespace App\Http\Controllers;

use App\AI\DTOs\AiContextData;
use App\AI\Reporting\Scheduling\AiReportScheduleService;
use App\AI\Reporting\Services\AiIntelligenceReportService;
use App\AI\Services\AiContextBuilderService;
use App\Enums\ReportPeriodType;
use App\Enums\ReportScheduleFrequency;
use App\Enums\ReportScheduleRecipientMode;
use App\Enums\ReportType;
use App\Models\AiIntelligenceReport;
use App\Models\Branch;
use App\Models\User;
use App\Services\AuditService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Management intelligence reporting (Phase 12.0).
 *
 * A thin HTTP layer over AiIntelligenceReportService: the controller validates
 * the request shape, resolves the trusted AI context and renders. It performs no
 * financial computation, no tenant decision and no AI call of its own.
 *
 * Two invariants are enforced here in addition to the service:
 *  - tenant and branch scope are never read from the request; the organization
 *    comes from the trusted context and a requested branch is re-validated
 *    against it, so an IDOR attempt is a 403 and never a disclosure;
 *  - a report may only be viewed, printed or exported while it is `completed`,
 *    so a failed or still-generating row is never presented as a report.
 */
class AiIntelligenceReportController extends Controller
{
    public function __construct(
        private readonly AiContextBuilderService $contextBuilder,
        private readonly AiIntelligenceReportService $reports,
        private readonly AiReportScheduleService $schedules,
        private readonly AuditService $audit,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $context = $this->contextBuilder->build($user);

        return view('ai.reports.index', [
            'reportTypes' => $this->reports->availableTypes($context),
            'periods' => ReportPeriodType::cases(),
            'branches' => $this->branches($context),
            'recent' => $this->reports->recent($context, 10),
            'context' => $context,
            'schedules' => $this->schedules->listFor($context),
            'scheduleFrequencies' => ReportScheduleFrequency::cases(),
            'scheduleRecipientModes' => ReportScheduleRecipientMode::cases(),
            'scheduleRecipients' => $this->scheduleRecipients($context),
            'canSchedule' => $context->hasPermission('ai.reports.schedule')
                && $context->hasPermission('ai.reports.view'),
        ]);
    }

    /**
     * Generate a report. The response is a redirect for the HTML flow so a
     * refresh never re-generates; the report itself is then read from the
     * persisted (and audited) result.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $context = $this->contextBuilder->build($user);

        $validated = $request->validate([
            'report_type' => ['required', Rule::in(array_column(ReportType::cases(), 'value'))],
            'period' => ['required', Rule::in(array_column(ReportPeriodType::cases(), 'value'))],
            'from' => ['nullable', 'date', 'required_if:period,custom'],
            'to' => ['nullable', 'date', 'required_if:period,custom', 'after_or_equal:from'],
            'branch_id' => ['nullable', 'integer'],
            'with_narrative' => ['nullable', 'boolean'],
        ]);

        if (($validated['period'] ?? null) === ReportPeriodType::Custom->value) {
            $this->assertCustomRangeWithinBounds($validated);
        }

        try {
            $report = $this->reports->generate(
                context: $context,
                user: $user,
                reportType: $validated['report_type'],
                periodType: $validated['period'],
                from: $validated['from'] ?? null,
                to: $validated['to'] ?? null,
                branchId: isset($validated['branch_id']) ? (string) $validated['branch_id'] : null,
                withNarrative: (bool) ($validated['with_narrative'] ?? false),
            );
        } catch (\InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        // Generation is audited by the reporting service against the report row; the
        // redirect straight into `show`, which records the view itself.

        return redirect()->route('ai.reports.show', $report);
    }

    public function show(Request $request, int $report): View
    {
        $context = $this->contextBuilder->build($request->user());

        $model = $this->authorizedCompleted($context, $report);

        $this->audit->log('ai.report.viewed', $model, [], [
            'report_type' => $model->report_type->value,
            'organization_id' => $model->organization_id,
        ]);

        return view('ai.reports.show', [
            'report' => $model,
            'sections' => (array) data_get($model->report_data, 'sections', []),
            'dataQuality' => (array) data_get($model->report_data, 'data_quality', []),
        ]);
    }

    /**
     * The print view. Same authorized, completed-only read as `show`, rendered
     * with the printable layout and no interactive chrome.
     */
    public function print(Request $request, int $report): View
    {
        $context = $this->contextBuilder->build($request->user());

        $model = $this->authorizedCompleted($context, $report);

        $this->audit->log('ai.report.printed', $model, [], [
            'report_type' => $model->report_type->value,
            'organization_id' => $model->organization_id,
        ]);

        return view('ai.reports.print', [
            'report' => $model,
            'sections' => (array) data_get($model->report_data, 'sections', []),
            'dataQuality' => (array) data_get($model->report_data, 'data_quality', []),
        ]);
    }

    /**
     * CSV export of the structured dataset. The narrative is exported as a
     * clearly labelled block and never merged into a financial column, so an
     * exported report can never let prose be read as a figure.
     */
    public function export(Request $request, int $report): StreamedResponse
    {
        $context = $this->contextBuilder->build($request->user());

        $model = $this->authorizedCompleted($context, $report);

        $csv = $this->reports->toCsv($model);

        $this->audit->log('ai.report.exported', $model, [], [
            'report_type' => $model->report_type->value,
            'organization_id' => $model->organization_id,
            'format' => 'csv',
            'row_count' => count($csv['rows']),
        ]);

        $filename = sprintf(
            '%s-report-%s-%s.csv',
            $model->report_type->value,
            $model->period_start->toDateString(),
            $model->period_end->toDateString(),
        );

        return response()->streamDownload(function () use ($csv, $model) {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, ['FINANCEPRO MANAGEMENT INTELLIGENCE REPORT'], ['escape' => '\\']);
            fputcsv($handle, [], ['escape' => '\\']);

            foreach ($csv['metadata'] as $label => $value) {
                fputcsv($handle, [ucfirst(str_replace('_', ' ', $label)), $value], ['escape' => '\\']);
            }

            fputcsv($handle, [], ['escape' => '\\']);
            fputcsv($handle, ['Note', 'Every fact and trend below was computed deterministically by the FinancePro reporting service from authoritative records. Predictions are statistical indications, never guarantees. Advisories are rule-based suggestions for a human decision; the AI never approves or rejects anything.']);
            fputcsv($handle, [], ['escape' => '\\']);
            fputcsv($handle, $csv['header'], ['escape' => '\\']);

            foreach ($csv['rows'] as $row) {
                fputcsv($handle, $row, ['escape' => '\\']);
            }

            $narrative = $model->narrative;

            if (is_array($narrative) && ($narrative['available'] ?? false) === true) {
                fputcsv($handle, [], ['escape' => '\\']);
                fputcsv($handle, ['AI NARRATIVE (explanatory only; not a financial figure)'], ['escape' => '\\']);
                fputcsv($handle, ['Provider', ($narrative['provider'] ?? 'unknown').' / '.($narrative['model'] ?? 'unknown')], ['escape' => '\\']);
                fputcsv($handle, ['Narrative', (string) ($narrative['summary'] ?? '')], ['escape' => '\\']);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Authorized, completed report only. A foreign report id and an
     * unauthorized branch are both denied before any content is read.
     */
    protected function authorizedCompleted(AiContextData $context, int $reportId): AiIntelligenceReport
    {
        try {
            $model = $this->reports->findAuthorized($context, $reportId);
        } catch (\InvalidArgumentException) {
            abort(404);
        }

        if (! $model->status->isReportable()) {
            abort(404);
        }

        return $model;
    }

    /**
     * Custom ranges are bounded by configuration so a request cannot ask for an
     * unbounded scan of the ledger.
     *
     * @param  array<string, mixed>  $validated
     */
    protected function assertCustomRangeWithinBounds(array $validated): void
    {
        $from = CarbonImmutable::parse($validated['from']);
        $to = CarbonImmutable::parse($validated['to']);
        $max = (int) config('intelligence-reporting.max_custom_range_days', 366);

        if ($from->diffInDays($to) > $max) {
            abort(redirect()->back()->withErrors([
                'from' => "The reporting range may not exceed {$max} days.",
            ]));
        }
    }

    /**
     * Branches the acting user is actually assigned to. The list is derived from
     * the trusted context, so a branch outside the user's scope is not even
     * offered in the form.
     *
     * @return Collection<int, Branch>
     */
    protected function branches(AiContextData $context)
    {
        if ($context->branchIds === []) {
            return new Collection;
        }

        return Branch::whereIn('id', $context->branchIds)
            ->orderBy('name')
            ->get();
    }

    /**
     * The authorized recipient pool offered in the schedule form: only users who
     * already hold the reporting capability inside the acting user's own
     * organization. The scheduling service re-verifies every selection anyway.
     *
     * @return Collection<int, User>
     */
    protected function scheduleRecipients(AiContextData $context)
    {
        if ($context->organizationIds === []) {
            return new Collection;
        }

        return $this->schedules->candidateRecipients((int) $context->organizationIds[0]);
    }
}
