<?php

namespace App\Http\Controllers;

use App\AI\DTOs\AiContextData;
use App\AI\ManagementActions\Services\ManagementActionAuthorizationService;
use App\AI\ManagementActions\Services\ManagementActionEffectivenessService;
use App\AI\ManagementActions\Services\ManagementActionQueryService;
use App\AI\Reporting\Services\ReportPeriodService;
use App\AI\Services\AiContextBuilderService;
use App\Enums\ManagementActionStatus;
use App\Enums\ReportPeriodType;
use App\Models\Branch;
use App\Models\ManagementAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Management Action Effectiveness & Executive Accountability (Phase 12.4).
 *
 * A thin, strictly read-only HTTP layer over
 * ManagementActionEffectivenessService. The controller validates the filter
 * shape, resolves the trusted AI context, re-validates every scope the request
 * asks for and renders. It performs no aggregation of its own, makes no tenant
 * decision, invokes no AI provider and changes no state — loading this page
 * cannot create, assign, escalate, complete or cancel anything.
 *
 * A requested branch must belong to the acting user's trusted scope, and a
 * requested assignee must be a user who could legitimately hold an action in
 * that scope. Both are therefore rejected with 403 rather than being used to
 * infer whether a foreign branch or user exists.
 */
class ManagementActionEffectivenessController extends Controller
{
    public function __construct(
        private readonly AiContextBuilderService $contextBuilder,
        private readonly ManagementActionEffectivenessService $effectiveness,
        private readonly ManagementActionQueryService $query,
        private readonly ManagementActionAuthorizationService $authorization,
        private readonly ReportPeriodService $periods,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $context = $this->contextBuilder->build($request->user());

        $validated = $request->validate([
            'period' => ['nullable', Rule::in(ReportPeriodType::values())],
            'from' => ['nullable', 'date', 'required_if:period,custom'],
            'to' => ['nullable', 'date', 'after_or_equal:from', 'required_if:period,custom'],
            'branch_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in(ManagementActionStatus::values())],
            'assigned_to' => ['nullable', 'string', 'max:20'],
            'source_type' => ['nullable', Rule::in($this->sourceOptions())],
        ]);

        $branchId = $this->resolveBranchFilter($context, $validated['branch_id'] ?? null);

        $assignedTo = $this->resolveAssignee($context, $validated['assigned_to'] ?? null, $branchId);

        try {
            $period = $this->periods->resolve(
                (string) ($validated['period'] ?? ReportPeriodType::ThisMonth->value),
                $validated['from'] ?? null,
                $validated['to'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['period' => $exception->getMessage()]);
        }

        $filters = [
            'branch_id' => $branchId,
            'assigned_to' => $assignedTo,
            'source_type' => $validated['source_type'] ?? null,
        ];

        try {
            $report = $this->effectiveness->report($context, $period, $filters);

            $actions = $this->query->paginate($context, [
                'branch_id' => $branchId,
                'assigned_to' => $assignedTo,
                'source_type' => $validated['source_type'] ?? null,
                'status' => $validated['status'] ?? null,
            ]);
        } catch (InvalidArgumentException $exception) {
            abort(403, $exception->getMessage());
        }

        return view('ai.intelligence-center.actions.effectiveness', [
            'report' => $report,
            'actions' => $actions,
            'period' => $period,
            'filters' => $filters,
            'displayFilters' => [
                'status' => $validated['status'] ?? null,
                'from' => $validated['from'] ?? null,
                'to' => $validated['to'] ?? null,
            ],
            'branches' => $this->branches($context),
            'assignees' => $this->assignees($context, $branchId),
            'statuses' => ManagementActionStatus::cases(),
            'periodTypes' => ReportPeriodType::cases(),
            'sourceOptions' => $this->sourceOptions(),
        ]);
    }

    /**
     * The source filter accepts the four stored aliases plus the explicit
     * "standalone" value, so actions raised without an intelligence source can
     * be measured on their own.
     *
     * @return array<int, string>
     */
    protected function sourceOptions(): array
    {
        return [...array_keys(ManagementAction::SOURCE_TYPES), 'standalone'];
    }

    /**
     * Resolve the optional branch that narrows the measurement.
     *
     * An empty selection means "my whole authorized scope" and is never rejected:
     * unlike the Phase 12.3 create flow — where no branch means an
     * organization-wide action a branch-limited user may not create — a read
     * filter must always be able to express "everything I can see".
     *
     * A named branch must belong to one of the acting user's own organizations
     * and, for a branch-limited user, must be one of their assigned branches. The
     * check is therefore a genuine scope decision on a trusted id, and an
     * out-of-scope branch is a 403 rather than a silent empty result, so the
     * filter can never be used to probe for foreign branches.
     */
    protected function resolveBranchFilter(AiContextData $context, mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            abort(403, 'Unauthorized branch scope.');
        }

        $branchId = (int) $value;
        $branch = Branch::find($branchId);

        if ($branch === null || ! $context->belongsToOrganization((int) $branch->organization_id)) {
            abort(403, 'Unauthorized branch scope.');
        }

        if ($context->branchIds !== [] && ! $context->belongsToBranch($branchId)) {
            abort(403, 'Unauthorized branch scope.');
        }

        return $branchId;
    }

    /**
     * Re-validate the requested assignee against the users who could actually
     * hold an action in the requested scope.
     *
     * Every one of the acting user's own organizations is checked, not just the
     * first, and the branch restriction is only applied to a branch-limited user:
     * an organization-wide user may legitimately filter by any reader of their
     * organization. Anything else is a 403, so the filter can never be used to
     * probe for foreign users.
     */
    protected function resolveAssignee(AiContextData $context, mixed $value, ?int $branchId): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value === 'me' || $value === 'unassigned') {
            return $value;
        }

        if (! is_numeric($value)) {
            abort(403, 'Unauthorized assignee scope.');
        }

        $userId = (int) $value;
        $scopedBranch = $context->branchIds === [] ? null : $branchId;

        foreach ($context->organizationIds as $organizationId) {
            if ($this->authorization->candidateAssignees((int) $organizationId, $scopedBranch)->contains('id', $userId)) {
                return (string) $userId;
            }
        }

        abort(403, 'Unauthorized assignee scope.');
    }

    /**
     * The branches offered by the branch filter.
     *
     * A branch-limited user sees only their assigned branches. An
     * organization-wide user sees every branch of their own organizations, so the
     * branch filter is usable for both kinds of scope.
     *
     * @return EloquentCollection<int, Branch>
     */
    protected function branches(AiContextData $context): EloquentCollection
    {
        return Branch::query()
            ->when(
                $context->branchIds !== [],
                fn ($query) => $query->whereIn('id', $context->branchIds),
            )
            ->when(
                $context->organizationIds !== [],
                fn ($query) => $query->whereIn('organization_id', $context->organizationIds),
            )
            ->orderBy('name')
            ->get();
    }

    /**
     * The assignee options for the requested scope: the readers of the acting
     * user's own organizations, narrowed to the selected branch for a
     * branch-limited user.
     *
     * @return EloquentCollection<int, User>
     */
    protected function assignees(AiContextData $context, ?int $branchId): EloquentCollection
    {
        if ($context->organizationIds === []) {
            return new EloquentCollection;
        }

        $scopedBranch = $context->branchIds === [] ? null : $branchId;
        $assignees = new EloquentCollection;

        foreach ($context->organizationIds as $organizationId) {
            $assignees = $assignees->concat(
                $this->authorization->candidateAssignees((int) $organizationId, $scopedBranch),
            );
        }

        return $assignees->unique('id')->sortBy('fullname')->values();
    }
}
