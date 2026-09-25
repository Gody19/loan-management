<?php

namespace App\AI\Policies;

use App\AI\DTOs\AiContextData;
use App\Enums\AiEvaluationStatus;
use App\Enums\AiFeedbackStatus;
use App\Models\AiEvaluation;
use App\Models\AiFeedback;
use App\Models\AiLearningExample;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Authorization for the feedback, evaluation and learning-dataset pipeline.
 *
 * Two independent gates apply to every record, and both are checked here:
 *
 *   1. Capability — a named permission (ai.feedback.review / .approve /
 *      .export) must already be present in the trusted context.
 *   2. Tenant record scope — the record's own organization (and branch, when
 *      the record is branch scoped) must fall inside the trusted context.
 *
 * Organization Administrator is never given a global bypass. Only the
 * Super Administrator's platform-wide context reaches beyond the records that
 * belong to organizations the caller actually belongs to.
 *
 * Approval is a governance act over untrusted text. It never confers business
 * authority, so this policy can authorize reviewing a correction but has no
 * method — and no path — that could apply it to FinancePro business data.
 */
class AiFeedbackPolicy
{
    /**
     * A user may submit feedback only for an assistant message inside a
     * conversation they are already authorized to read. Conversation
     * ownership/tenant access itself is enforced by
     * AiGuardrailService::checkConversationAccess; this guards the message.
     */
    public function canSubmitFeedback(AiContextData $context, User $user, AiMessage $message): bool
    {
        if ($message->conversation === null) {
            return false;
        }

        return $this->canAccessConversation($context, $user, $message);
    }

    /**
     * Conversation read access, mirroring the guardrail's rule: super admin
     * platform scope, then strict owner-only for VICOBA Members, then
     * organization membership for staff.
     */
    public function canAccessConversation(AiContextData $context, User $user, AiMessage $message): bool
    {
        $conversation = $message->conversation;

        if ($conversation === null) {
            return false;
        }

        if ($context->isSuperAdmin) {
            return true;
        }

        if ($conversation->user_id !== null && (int) $conversation->user_id === (int) $user->id) {
            return true;
        }

        if ($context->hasRole('VICOBA Member')) {
            return false;
        }

        if ($conversation->organization_id === null) {
            return false;
        }

        return $context->belongsToOrganization((int) $conversation->organization_id);
    }

    /**
     * Submitting feedback is available to any authenticated AI user; the
     * ai.use capability already governs the chat surface itself.
     */
    public function canSubmit(AiContextData $context): bool
    {
        return $context->hasPermission('ai.use');
    }

    /**
     * A submitter may read and withdraw their own feedback. A reviewer may
     * read it once it is inside their tenant scope. Nobody else may.
     */
    public function canViewFeedback(AiContextData $context, User $user, AiFeedback $feedback): bool
    {
        if ((int) $feedback->user_id === (int) $user->id) {
            return true;
        }

        if (! $this->canReview($context)) {
            return false;
        }

        return $this->withinTenantScope($context, $feedback);
    }

    public function canWithdraw(AiContextData $context, User $user, AiFeedback $feedback): bool
    {
        return (int) $feedback->user_id === (int) $user->id
            && $feedback->status !== AiFeedbackStatus::Reviewed;
    }

    public function canReview(AiContextData $context): bool
    {
        return $context->hasPermission('ai.feedback.review');
    }

    public function canApprove(AiContextData $context): bool
    {
        return $context->hasPermission('ai.feedback.approve');
    }

    public function canExport(AiContextData $context): bool
    {
        return $context->hasPermission('ai.feedback.export');
    }

    public function canViewEvaluation(AiContextData $context, AiEvaluation $evaluation): bool
    {
        return $this->canReview($context) && $this->withinTenantScope($context, $evaluation);
    }

    public function canViewExample(AiContextData $context, AiLearningExample $example): bool
    {
        return $this->canReview($context) && $this->withinTenantScope($context, $example);
    }

    /**
     * A transition out of a terminal evaluation state is never allowed. This is
     * the second half of concurrency protection: the row lock serializes the
     * writers, and this predicate makes the loser a no-op.
     */
    public function canTransition(AiEvaluation $evaluation): bool
    {
        return ! $evaluation->status->isTerminal();
    }

    /**
     * Tenant record scope. A record with no organization is ambiguous and is
     * never exposed outside the platform context; a branch-scoped record
     * additionally requires branch membership.
     */
    public function withinTenantScope(AiContextData $context, Model $record): bool
    {
        if ($context->isSuperAdmin) {
            return true;
        }

        $organizationId = $record->organization_id;

        if ($organizationId === null) {
            return false;
        }

        if (! $context->belongsToOrganization((int) $organizationId)) {
            return false;
        }

        $branchId = $record->branch_id;

        if ($branchId !== null && ! $context->belongsToBranch((int) $branchId)) {
            return false;
        }

        return true;
    }

    /**
     * Constrain a query to the records the trusted context may see. The
     * browser never supplies a tenant id; the effective scope always comes
     * from here.
     */
    public function scopeForContext($query, AiContextData $context)
    {
        if ($context->isSuperAdmin) {
            return $query;
        }

        $organizationIds = $context->organizationIds ?: [0];
        $branchIds = $context->branchIds;

        // Organization is always required. A branch narrowing may only further
        // restrict an already-authorized organization, never widen it to a
        // record belonging to another tenant.
        return $query->whereIn('organization_id', $organizationIds)
            ->where(function ($inner) use ($branchIds) {
                $inner->whereNull('branch_id');

                if ($branchIds !== []) {
                    $inner->orWhereIn('branch_id', $branchIds);
                }
            });
    }
}
