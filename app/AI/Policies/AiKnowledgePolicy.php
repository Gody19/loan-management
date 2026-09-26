<?php

namespace App\AI\Policies;

use App\AI\DTOs\AiContextData;
use App\Enums\AiKnowledgeScope;
use App\Models\AiKnowledgeDocument;

/**
 * Authorizes access to an approved knowledge document for an AI retrieval.
 *
 * This policy answers one question: "can this trusted AI context retrieve a
 * given knowledge document?" It is a READ authorization over document content
 * — it never grants execution, write, approval, or financial authority. The
 * Laravel permission (ai.knowledge.search) gates the capability itself; this
 * policy narrows which documents, by tenant scope, the authorized user may
 * actually retrieve.
 *
 * Scope precedence is explicit: GLOBAL > ORGANIZATION > BRANCH > GROUP. A user
 * may retrieve a document at a scope only when their trusted context covers
 * the exact narrow scope (branch users also belong to the branch's
 * organization through branch_user assignments).
 */
class AiKnowledgePolicy
{
    public function canAccessDocument(AiContextData $context, AiKnowledgeDocument $document): bool
    {
        if ($context->isSuperAdmin) {
            return true;
        }

        $scope = $document->visibility;

        if ($scope === AiKnowledgeScope::Global || $scope === AiKnowledgeScope::Public) {
            return true;
        }

        if ($scope === AiKnowledgeScope::Organization) {
            return $document->organization_id !== null
                && $context->belongsToOrganization((int) $document->organization_id);
        }

        if ($scope === AiKnowledgeScope::Branch) {
            return $document->branch_id !== null
                && $context->belongsToBranch((int) $document->branch_id);
        }

        if ($scope === AiKnowledgeScope::Group) {
            return $document->vicoba_group_id !== null
                && $context->belongsToOrganization((int) $document->organization_id)
                && in_array((int) $document->vicoba_group_id, $context->vicobaGroupIds, true);
        }

        return false;
    }

    /**
     * Whether the trusted context may manage the knowledge base (create,
     * process, archive). Independent of document-level read scoping.
     */
    public function canManage(AiContextData $context): bool
    {
        return $context->hasPermission('ai.knowledge.manage');
    }
}
