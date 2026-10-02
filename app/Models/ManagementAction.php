<?php

namespace App\Models;

use App\Enums\ManagementActionPriority;
use App\Enums\ManagementActionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A management action (Phase 12.3): a human workflow record raised around an
 * existing advisory intelligence artifact.
 *
 * A management action is NOT intelligence and NOT a financial record. It holds
 * no money column, never changes a business record and never executes a loan,
 * repayment, accounting or eligibility decision. It is a task that a named
 * human is responsible for; the AI never creates, assigns, completes or
 * cancels one.
 */
class ManagementAction extends Model
{
    use HasFactory;

    /**
     * The intelligence artifacts a management action may be raised from, keyed
     * by the stable alias stored in the request and shown in the UI. The stored
     * source_type is the fully-qualified model class.
     */
    public const SOURCE_TYPES = [
        'insight' => AiInsight::class,
        'prediction' => AiPrediction::class,
        'report' => AiIntelligenceReport::class,
        'anomaly' => AiAnomalyFinding::class,
    ];

    protected $fillable = [
        'organization_id', 'branch_id', 'created_by', 'assigned_to',
        'title', 'description', 'priority', 'status', 'due_date',
        'started_at', 'completed_at', 'cancelled_at',
        'source_type', 'source_id', 'source_label',
        'completion_notes', 'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'priority' => ManagementActionPriority::class,
            'status' => ManagementActionStatus::class,
            'due_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ManagementActionEvent::class)->orderBy('id');
    }

    /**
     * The intelligence artifact this follow-up was raised from. It is optional
     * and may have been deleted; the display always falls back to the stored
     * source_label snapshot.
     */
    public function source(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id');
    }

    public function scopeForOrganization(Builder $query, int $organizationId): Builder
    {
        return $query->where('organization_id', $organizationId);
    }

    public function scopeForOrganizations(Builder $query, array $organizationIds): Builder
    {
        return $query->whereIn('organization_id', $organizationIds);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ManagementActionStatus::Open->value,
            ManagementActionStatus::InProgress->value,
        ]);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->open()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', CarbonImmutable::today()->toDateString());
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function isOverdue(): bool
    {
        return $this->dueState() === 'overdue';
    }

    /**
     * The due-date presentation state. Purely descriptive — the status is never
     * changed automatically when a due date passes.
     */
    public function dueState(): string
    {
        if ($this->status === ManagementActionStatus::Completed) {
            return 'completed';
        }

        if ($this->status === ManagementActionStatus::Cancelled) {
            return 'cancelled';
        }

        if ($this->due_date === null) {
            return 'no_due_date';
        }

        $today = CarbonImmutable::today();
        $due = CarbonImmutable::parse($this->due_date->toDateString());
        $soonDays = max(0, (int) config('intelligence-actions.due_soon_days', 3));

        return match (true) {
            $due->lt($today) => 'overdue',
            $due->eq($today) => 'due_today',
            $due->lte($today->addDays($soonDays)) => 'due_soon',
            default => 'upcoming',
        };
    }

    public function dueStateLabel(): string
    {
        return match ($this->dueState()) {
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            'no_due_date' => 'No due date',
            'overdue' => 'Overdue',
            'due_today' => 'Due today',
            'due_soon' => 'Due soon',
            default => 'Upcoming',
        };
    }

    public function dueStateColor(): string
    {
        return match ($this->dueState()) {
            'completed' => 'success',
            'cancelled' => 'dark',
            'overdue' => 'danger',
            'due_today', 'due_soon' => 'warning',
            default => 'secondary',
        };
    }

    /**
     * The stable alias for a stored source class, or null when unrecognised.
     */
    public static function sourceAliasFor(?string $sourceType): ?string
    {
        if ($sourceType === null) {
            return null;
        }

        $alias = array_search($sourceType, self::SOURCE_TYPES, true);

        return $alias === false ? null : $alias;
    }
}
