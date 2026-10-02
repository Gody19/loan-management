<?php

namespace App\Http\Controllers;

use App\AI\DTOs\AiContextData;
use App\AI\ManagementActions\Data\ManagementActionData;
use App\AI\ManagementActions\Services\ManagementActionAuthorizationService;
use App\AI\ManagementActions\Services\ManagementActionQueryService;
use App\AI\ManagementActions\Services\ManagementActionService;
use App\AI\Services\AiContextBuilderService;
use App\Enums\ManagementActionPriority;
use App\Enums\ManagementActionStatus;
use App\Models\AiIntelligenceReport;
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
 * Executive Action & Review Management (Phase 12.3).
 *
 * A thin HTTP layer over the management action services: the controller
 * validates the request shape, resolves the trusted AI context and delegates.
 * It performs no tenant decision, no lifecycle rule and no AI call of its own.
 *
 * The Action Center is a human workflow surface. Nothing here executes a
 * financial or business operation, and every mutating act is re-authorized and
 * re-scoped inside the service, so a foreign action id is a not-found/forbidden
 * rather than a disclosure.
 */
class ManagementActionController extends Controller
{
    public function __construct(
        private readonly AiContextBuilderService $contextBuilder,
        private readonly ManagementActionService $actions,
        private readonly ManagementActionQueryService $query,
        private readonly ManagementActionAuthorizationService $authorization,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $context = $this->contextBuilder->build($user);

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(ManagementActionStatus::values())],
            'priority' => ['nullable', Rule::in(ManagementActionPriority::values())],
            'assigned_to' => ['nullable', 'string', 'max:20'],
            'branch_id' => ['nullable', 'integer'],
            'source_type' => ['nullable', Rule::in(array_keys(ManagementAction::SOURCE_TYPES))],
            'due' => ['nullable', Rule::in(['overdue', 'due_soon', 'open'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $branchId = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;

        if ($branchId !== null && ! $context->belongsToBranch($branchId)) {
            abort(403, 'Unauthorized branch scope.');
        }

        $filters = [
            'status' => $validated['status'] ?? null,
            'priority' => $validated['priority'] ?? null,
            'assigned_to' => $validated['assigned_to'] ?? null,
            'branch_id' => $branchId,
            'source_type' => $validated['source_type'] ?? null,
            'due' => $validated['due'] ?? null,
        ];

        try {
            $actions = $this->query->paginate($context, $filters);
        } catch (InvalidArgumentException $exception) {
            abort(403, $exception->getMessage());
        }

        return view('ai.intelligence-center.actions.index', [
            'actions' => $actions,
            'counts' => $this->query->dashboard($context),
            'filters' => $filters,
            'branches' => $this->branches($context),
            'assignees' => $this->assignees($context),
            'statuses' => ManagementActionStatus::cases(),
            'priorities' => ManagementActionPriority::cases(),
            'sourceTypes' => array_keys(ManagementAction::SOURCE_TYPES),
            'can' => $this->capabilities($context),
        ]);
    }

    public function create(Request $request): View
    {
        $context = $this->contextBuilder->build($request->user());

        return view('ai.intelligence-center.actions.create', [
            'branches' => $this->branches($context),
            'assignees' => $this->assignees($context),
            'priorities' => ManagementActionPriority::cases(),
            'sourceTypes' => array_keys(ManagementAction::SOURCE_TYPES),
            'can' => $this->capabilities($context),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $context = $this->contextBuilder->build($user);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:4000'],
            'priority' => ['nullable', Rule::in(ManagementActionPriority::values())],
            'due_date' => ['nullable', 'date'],
            'branch_id' => ['nullable', 'integer'],
            'assigned_to' => ['nullable', 'integer'],
            'source_type' => ['nullable', Rule::in(array_keys(ManagementAction::SOURCE_TYPES))],
            'source_id' => ['nullable', 'integer'],
        ]);

        try {
            $action = $this->actions->create($context, $user, $validated);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()
            ->route('ai.actions.show', $action)
            ->with('success', 'Management action created.');
    }

    public function show(Request $request, int $action): View
    {
        $context = $this->contextBuilder->build($request->user());

        try {
            $model = $this->authorization->findViewable($context, $action);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        $model->load(['events.actor']);

        return view('ai.intelligence-center.actions.show', [
            'action' => $model,
            'data' => ManagementActionData::fromModel($model),
            'timeline' => $model->events,
            'assignees' => $this->assignees($context),
            'priorities' => ManagementActionPriority::cases(),
            'sourceUrl' => $this->sourceUrl($model),
            'can' => $this->capabilities($context),
        ]);
    }

    public function update(Request $request, int $action): RedirectResponse
    {
        $user = $request->user();
        $context = $this->contextBuilder->build($user);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:4000'],
            'priority' => ['nullable', Rule::in(ManagementActionPriority::values())],
            'due_date' => ['nullable', 'date'],
        ]);

        try {
            $model = $this->authorization->findManageable($context, $action);
            $this->actions->update($context, $user, $model, $validated);
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Management action updated.');
    }

    public function assign(Request $request, int $action): RedirectResponse
    {
        $user = $request->user();
        $context = $this->contextBuilder->build($user);

        $validated = $request->validate([
            'assigned_to' => ['nullable', 'integer'],
        ]);

        try {
            $model = $this->authorization->findViewable($context, $action);
            $this->actions->assign($context, $user, $model, $validated['assigned_to'] ?? null);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Management action assignment updated.');
    }

    public function start(Request $request, int $action): RedirectResponse
    {
        return $this->transition($request, $action, fn ($context, $user, $model) => $this->actions->start($context, $user, $model), 'Management action started.');
    }

    public function complete(Request $request, int $action): RedirectResponse
    {
        $validated = $request->validate([
            'completion_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->transition(
            $request,
            $action,
            fn ($context, $user, $model) => $this->actions->complete($context, $user, $model, $validated['completion_notes'] ?? null),
            'Management action completed.',
        );
    }

    public function cancel(Request $request, int $action): RedirectResponse
    {
        $validated = $request->validate([
            'cancellation_reason' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->transition(
            $request,
            $action,
            fn ($context, $user, $model) => $this->actions->cancel($context, $user, $model, $validated['cancellation_reason'] ?? null),
            'Management action cancelled.',
        );
    }

    /**
     * @param  callable(AiContextData, User, ManagementAction): ManagementAction  $callback
     */
    protected function transition(Request $request, int $action, callable $callback, string $message): RedirectResponse
    {
        $user = $request->user();
        $context = $this->contextBuilder->build($user);

        try {
            $model = $this->authorization->findManageable($context, $action);
            $callback($context, $user, $model);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', $message);
    }

    /**
     * @return array<string, bool>
     */
    protected function capabilities(AiContextData $context): array
    {
        return [
            'view' => $this->authorization->canView($context),
            'manage' => $this->authorization->canManage($context),
            'assign' => $this->authorization->canAssign($context),
        ];
    }

    /**
     * @return EloquentCollection<int, Branch>
     */
    protected function branches(AiContextData $context): EloquentCollection
    {
        if ($context->branchIds === []) {
            return new EloquentCollection;
        }

        return Branch::whereIn('id', $context->branchIds)->orderBy('name')->get();
    }

    /**
     * @return EloquentCollection<int, User>
     */
    protected function assignees(AiContextData $context): EloquentCollection
    {
        if ($context->organizationIds === []) {
            return new EloquentCollection;
        }

        return $this->authorization->candidateAssignees((int) $context->organizationIds[0]);
    }

    protected function sourceUrl(ManagementAction $action): ?string
    {
        if ($action->source_id === null) {
            return null;
        }

        return match ($action->source_type) {
            AiIntelligenceReport::class => route('ai.reports.show', $action->source_id),
            default => route('ai.intelligence.index'),
        };
    }
}
