<?php

namespace App\Http\Controllers;

use App\AI\DTOs\AiContextData;
use App\AI\Exceptions\AiUnavailableException;
use App\AI\Services\AiContextBuilderService;
use App\AI\Services\AiKnowledgeIngestionService;
use App\Enums\AiKnowledgeDocumentType;
use App\Enums\AiKnowledgeScope;
use App\Models\AiKnowledgeDocument;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Minimal secured backend for the AI knowledge base.
 *
 * Every route requires the ai.knowledge.manage permission (route middleware)
 * and re-validates the requested tenant scope against the acting user's
 * trusted context — a document can only be created inside an organization,
 * branch, or group the acting user actually belongs to. This is a write
 * surface: retrieval itself stays read-only and gated by ai.knowledge.search.
 */
class AiKnowledgeDocumentController extends Controller
{
    public function __construct(
        private readonly AiKnowledgeIngestionService $ingestion,
        private readonly AiContextBuilderService $contextBuilder,
    ) {}

    /**
     * GET /ai/knowledge/documents — list the documents the acting user manages.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $context = $this->contextBuilder->build($user);

        $query = AiKnowledgeDocument::query();

        if (! $context->isSuperAdmin) {
            $query->where(function ($q) use ($context) {
                $q->whereNull('organization_id')
                    ->orWhereIn('organization_id', $context->organizationIds);
            });
        }

        $documents = $query->orderByDesc('created_at')->limit(100)->get()->map(
            fn (AiKnowledgeDocument $document) => $this->present($document)
        );

        return response()->json(['data' => $documents]);
    }

    /**
     * POST /ai/knowledge/documents
     *
     * Body: { title, document_type, content, visibility, organization_id?,
     *         branch_id?, vicoba_group_id?, description?, source? }
     *
     * Only approved text is accepted (no file uploads in this phase). Tenant
     * identifiers, when supplied, must belong to the acting user; manager
     * scope is never inferred from anything the request could claim.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $context = $this->contextBuilder->build($user);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'document_type' => ['required', 'string', 'in:'.implode(',', AiKnowledgeDocumentType::values())],
            'visibility' => ['required', 'string', 'in:'.implode(',', AiKnowledgeScope::values())],
            'content' => ['required', 'string', 'min:10'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'source' => ['sometimes', 'nullable', 'string', 'max:255'],
            'organization_id' => ['sometimes', 'nullable', 'integer'],
            'branch_id' => ['sometimes', 'nullable', 'integer'],
            'vicoba_group_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        $scope = AiKnowledgeScope::from($validated['visibility']);

        [$organizationId, $branchId, $groupId] = $this->resolveScopeTargets($context, $scope, $validated);

        try {
            $document = $this->ingestion->store(
                user: $user,
                title: (string) $validated['title'],
                type: AiKnowledgeDocumentType::from($validated['document_type']),
                scope: $scope,
                content: (string) $validated['content'],
                organizationId: $organizationId,
                branchId: $branchId,
                vicobaGroupId: $groupId,
                description: $validated['description'] ?? null,
                source: $validated['source'] ?? null,
            );
        } catch (AiUnavailableException) {
            return response()->json(['message' => 'Knowledge processing is unavailable.'], 503);
        }

        return response()->json(['data' => $this->present($document)], 201);
    }

    /**
     * POST /ai/knowledge/documents/{document}/archive
     */
    public function archive(Request $request, AiKnowledgeDocument $document): JsonResponse
    {
        $user = $request->user();
        $context = $this->contextBuilder->build($user);

        if (! $this->canManageDocument($context, $document)) {
            return response()->json(['message' => 'Unauthorized knowledge document.'], 403);
        }

        $this->ingestion->archive($document);

        return response()->json(['data' => $this->present($document->fresh() ?? $document)]);
    }

    /**
     * Tenant targets for the requested scope, validated against the acting
     * user's trusted context. Global and Public documents carry no tenant
     * columns; all other scopes require an organization the user belongs to,
     * plus the narrower branch/group when requested by the scope.
     *
     * @return array{0: ?int, 1: ?int, 2: ?int}
     */
    protected function resolveScopeTargets(AiContextData $context, AiKnowledgeScope $scope, array $validated): array
    {
        if ($scope === AiKnowledgeScope::Global || $scope === AiKnowledgeScope::Public) {
            return [null, null, null];
        }

        $organizationId = isset($validated['organization_id']) ? (int) $validated['organization_id'] : null;

        if ($organizationId === null) {
            abort(422, 'organization_id is required for this visibility.');
        }

        if (! $context->isSuperAdmin && ! $context->belongsToOrganization($organizationId)) {
            abort(403, 'Unauthorized organization scope.');
        }

        if ($scope === AiKnowledgeScope::Organization) {
            return [$organizationId, null, null];
        }

        $branchId = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;

        if ($branchId === null) {
            abort(422, 'branch_id is required for this visibility.');
        }

        if (! $context->isSuperAdmin && ! $context->belongsToBranch($branchId)) {
            abort(403, 'Unauthorized branch scope.');
        }

        if ($scope === AiKnowledgeScope::Branch) {
            return [$organizationId, $branchId, null];
        }

        $groupId = isset($validated['vicoba_group_id']) ? (int) $validated['vicoba_group_id'] : null;

        if ($groupId === null) {
            abort(422, 'vicoba_group_id is required for this visibility.');
        }

        if (! $context->isSuperAdmin && ! in_array($groupId, $context->vicobaGroupIds, true)) {
            abort(403, 'Unauthorized group scope.');
        }

        return [$organizationId, $branchId, $groupId];
    }

    /**
     * Whether the acting context may manage a specific document. Managers may
     * only manage documents inside their own organizations (global documents
     * are only manageable by Super Administrators here).
     */
    protected function canManageDocument(AiContextData $context, AiKnowledgeDocument $document): bool
    {
        if ($context->isSuperAdmin) {
            return true;
        }

        if ($document->organization_id === null) {
            return false;
        }

        return $context->belongsToOrganization((int) $document->organization_id);
    }

    protected function present(AiKnowledgeDocument $document): array
    {
        return [
            'id' => $document->id,
            'title' => $document->title,
            'document_type' => $document->document_type->value,
            'visibility' => $document->visibility->value,
            'status' => $document->status->value,
            'version' => $document->version,
            'source' => $document->source,
            'description' => $document->description,
            'created_at' => $document->created_at?->toISOString(),
            'chunks' => $document->chunks()->count(),
        ];
    }
}
