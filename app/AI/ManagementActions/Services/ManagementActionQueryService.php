<?php

namespace App\AI\ManagementActions\Services;

use App\AI\DTOs\AiContextData;
use App\Enums\ManagementActionPriority;
use App\Enums\ManagementActionStatus;
use App\Models\ManagementAction;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Read-only queries over the management action workflow (Phase 12.3).
 *
 * Every method is scoped through the authorization service, so an action
 * outside the acting user's trusted organization/branch scope is never
 * returned, counted or disclosed. Nothing here writes state or runs a business
 * operation.
 */
class ManagementActionQueryService
{
    public function __construct(
        private readonly ManagementActionAuthorizationService $authorization,
    ) {}

    /**
     * The filtered, paginated Action Center list.
     *
     * @param  array<string, mixed>  $filters
     */
    public function paginate(AiContextData $context, array $filters = [], ?int $perPage = null): LengthAwarePaginator
    {
        $this->authorization->assertCanView($context);

        $perPage ??= (int) config('intelligence-actions.index_per_page', 20);

        $query = $this->authorization->applyScope(
            ManagementAction::with(['organization', 'branch', 'creator', 'assignee']),
            $context,
        );

        $this->applyFilters($query, $filters, $context);

        return $query
            ->orderByRaw("CASE WHEN status IN ('open', 'in_progress') THEN 0 ELSE 1 END")
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date')
            ->orderByDesc('id')
            ->paginate(max(1, $perPage))
            ->withQueryString();
    }

    /**
     * The bounded executive follow-up counters plus the recently completed
     * actions shown on the Intelligence Center.
     *
     * @return array<string, mixed>
     */
    public function dashboard(AiContextData $context): array
    {
        $empty = [
            'open' => 0,
            'in_progress' => 0,
            'overdue' => 0,
            'due_soon' => 0,
            'assigned_to_me' => 0,
            'unassigned' => 0,
            'completed' => 0,
            'cancelled' => 0,
            'recently_completed' => new EloquentCollection,
        ];

        if (! $this->authorization->canView($context)) {
            return $empty;
        }

        $base = $this->authorization->applyScope(ManagementAction::query(), $context);
        $open = (clone $base)->open();

        $today = CarbonImmutable::today();
        $soonDays = max(0, (int) config('intelligence-actions.due_soon_days', 3));

        $counts = [
            'open' => (clone $base)->where('status', ManagementActionStatus::Open->value)->count(),
            'in_progress' => (clone $base)->where('status', ManagementActionStatus::InProgress->value)->count(),
            'overdue' => (clone $open)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', $today->toDateString())
                ->count(),
            'due_soon' => (clone $open)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '>=', $today->toDateString())
                ->whereDate('due_date', '<=', $today->addDays($soonDays)->toDateString())
                ->count(),
            'assigned_to_me' => (clone $open)->where('assigned_to', $context->userId)->count(),
            'unassigned' => (clone $open)->whereNull('assigned_to')->count(),
            'completed' => (clone $base)->where('status', ManagementActionStatus::Completed->value)->count(),
            'cancelled' => (clone $base)->where('status', ManagementActionStatus::Cancelled->value)->count(),
        ];

        $counts['recently_completed'] = (clone $base)
            ->with(['creator', 'assignee'])
            ->where('status', ManagementActionStatus::Completed->value)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->limit((int) config('intelligence-actions.recent_completed_limit', 5))
            ->get();

        return $counts;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, array $filters, AiContextData $context): void
    {
        $status = $filters['status'] ?? null;

        if (is_string($status) && in_array($status, ManagementActionStatus::values(), true)) {
            $query->where('status', $status);
        }

        $priority = $filters['priority'] ?? null;

        if (is_string($priority) && in_array($priority, ManagementActionPriority::values(), true)) {
            $query->where('priority', $priority);
        }

        if (! empty($filters['branch_id']) && is_numeric($filters['branch_id'])) {
            $query->where('branch_id', (int) $filters['branch_id']);
        }

        if (! empty($filters['source_type']) && is_string($filters['source_type'])) {
            $class = ManagementAction::SOURCE_TYPES[$filters['source_type']] ?? null;

            if ($class !== null) {
                $query->where('source_type', $class);
            }
        }

        $assigned = $filters['assigned_to'] ?? null;

        if ($assigned === 'me') {
            $query->where('assigned_to', $context->userId);
        } elseif ($assigned === 'unassigned') {
            $query->whereNull('assigned_to');
        } elseif (is_numeric($assigned)) {
            $query->where('assigned_to', (int) $assigned);
        }

        $due = $filters['due'] ?? null;
        $today = CarbonImmutable::today();

        if ($due === 'overdue') {
            $query->open()->whereNotNull('due_date')->whereDate('due_date', '<', $today->toDateString());
        } elseif ($due === 'due_soon') {
            $soonDays = max(0, (int) config('intelligence-actions.due_soon_days', 3));

            $query->open()
                ->whereNotNull('due_date')
                ->whereDate('due_date', '>=', $today->toDateString())
                ->whereDate('due_date', '<=', $today->addDays($soonDays)->toDateString());
        } elseif ($due === 'open') {
            $query->open();
        }
    }
}
