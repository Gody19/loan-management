<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An append-only history entry for a management action (Phase 12.3).
 *
 * Every lifecycle act (creation, edit, assignment, start, completion,
 * cancellation) writes one immutable row here. Rows are never updated or
 * deleted by the application; the timeline is the authoritative record of what
 * humans did to the action.
 */
class ManagementActionEvent extends Model
{
    use HasFactory;

    /** Append-only: no updated_at column exists. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'management_action_id', 'organization_id', 'actor_id', 'event',
        'old_status', 'new_status', 'old_assignee_id', 'new_assignee_id',
        'notes', 'metadata', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(ManagementAction::class, 'management_action_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
