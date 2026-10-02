<?php

namespace App\Http\Controllers;

use App\AI\DTOs\AiContextData;
use App\AI\ManagementCenter\Services\ManagementIntelligenceCenterService;
use App\AI\Services\AiContextBuilderService;
use App\Enums\PredictiveInsightType;
use App\Enums\ProactiveInsightSeverity;
use App\Enums\ProactiveInsightStatus;
use App\Enums\ProactiveInsightType;
use App\Enums\ReportPeriodType;
use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Management Intelligence Center (Phase 12.2).
 *
 * A thin, read-only HTTP layer over ManagementIntelligenceCenterService. The
 * controller validates the filter shape, resolves the trusted AI context and
 * renders; it performs no financial computation, no tenant decision, no AI call
 * of its own and no state change.
 *
 * Tenant scope is never read from the request: the organization set comes from
 * the trusted context, and a requested branch is re-validated against it, so a
 * foreign branch filter is a 403 rather than a disclosure.
 */
class ManagementIntelligenceCenterController extends Controller
{
    public function __construct(
        private readonly AiContextBuilderService $contextBuilder,
        private readonly ManagementIntelligenceCenterService $center,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $context = $this->contextBuilder->build($user);

        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'period' => ['nullable', Rule::in(ReportPeriodType::values())],
            'from' => ['nullable', 'date', 'required_if:period,custom'],
            'to' => ['nullable', 'date', 'after_or_equal:from', 'required_if:period,custom'],
            'insight_severity' => ['nullable', Rule::in(array_column(ProactiveInsightSeverity::cases(), 'value'))],
            'insight_type' => ['nullable', Rule::in(array_column(ProactiveInsightType::cases(), 'value'))],
            'insight_status' => ['nullable', Rule::in(array_column(ProactiveInsightStatus::cases(), 'value'))],
            'report_type' => ['nullable', Rule::in(ReportType::values())],
            'report_status' => ['nullable', Rule::in(ReportStatus::values())],
            'generated_from' => ['nullable', 'date'],
            'generated_to' => ['nullable', 'date', 'after_or_equal:generated_from'],
            'prediction_type' => ['nullable', Rule::in(PredictiveInsightType::values())],
            'narrative' => ['nullable', 'boolean'],
        ]);

        $branchId = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;

        if ($branchId !== null && ! $context->belongsToBranch($branchId)) {
            abort(403, 'Unauthorized branch scope.');
        }

        $filters = [
            'branch_id' => $branchId,
            'period' => $validated['period'] ?? ReportPeriodType::ThisMonth->value,
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
            'insight_severity' => $validated['insight_severity'] ?? null,
            'insight_type' => $validated['insight_type'] ?? null,
            'insight_status' => $validated['insight_status'] ?? null,
            'report_type' => $validated['report_type'] ?? null,
            'report_status' => $validated['report_status'] ?? null,
            'generated_from' => $validated['generated_from'] ?? null,
            'generated_to' => $validated['generated_to'] ?? null,
            'prediction_type' => $validated['prediction_type'] ?? null,
        ];

        try {
            $overview = $this->center->overview($context, $filters, (bool) ($validated['narrative'] ?? false));
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['period' => $exception->getMessage()]);
        }

        return view('ai.intelligence-center.index', [
            'overview' => $overview,
            'context' => $context,
            'filters' => $filters,
            'branches' => $this->branches($context),
        ]);
    }

    /**
     * Branches the acting user is assigned to. A branch outside the trusted
     * scope is never even offered, matching the report filter behaviour.
     *
     * @return EloquentCollection<int, Branch>
     */
    protected function branches(AiContextData $context): EloquentCollection
    {
        if ($context->branchIds === []) {
            return new EloquentCollection;
        }

        return Branch::whereIn('id', $context->branchIds)->orderBy('name')->get();
    }
}
