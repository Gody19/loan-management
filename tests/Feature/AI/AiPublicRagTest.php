<?php

namespace Tests\Feature\AI;

use App\AI\Services\AiContextBuilderService;
use App\AI\Services\AiEmbeddingProviderService;
use App\AI\Services\AiKnowledgeIngestionService;
use App\AI\Services\AiKnowledgeRetrievalService;
use App\Enums\AiKnowledgeDocumentStatus;
use App\Enums\AiKnowledgeDocumentType;
use App\Enums\AiKnowledgeScope;
use App\Models\AiKnowledgeChunk;
use App\Models\AiKnowledgeDocument;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Public knowledge retrieval for the landing-page assistant (Phase 11.7.1).
 *
 * AiKnowledgeRetrievalService::searchPublic must return ONLY active documents
 * explicitly published with visibility=public (no tenant columns). Global,
 * organization, branch and group documents are never eligible, nor are draft
 * or archived public documents. Public documents ARE also visible to
 * authenticated users through the ordinary tenant-aware search, mirroring
 * global scope.
 */
class AiPublicRagTest extends AiTestCase
{
    private AiKnowledgeIngestionService $ingestion;

    private AiKnowledgeRetrievalService $retrieval;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ingestion = app(AiKnowledgeIngestionService::class);
        $this->retrieval = app(AiKnowledgeRetrievalService::class);
    }

    private function author(): User
    {
        $user = $this->superAdmin();
        $this->actingAs($user);

        return $user;
    }

    private function createPublicDocument(string $content): AiKnowledgeDocument
    {
        $document = $this->ingestion->store(
            user: $this->author(),
            title: 'Public '.Str::random(6),
            type: AiKnowledgeDocumentType::General,
            scope: AiKnowledgeScope::Public,
            content: $content,
            source: 'public-knowledge',
        );

        return $document->fresh() ?? $document;
    }

    private function createScopedDocument(
        AiKnowledgeScope $scope,
        string $content,
        ?Organization $org = null,
        ?int $branchId = null,
        ?int $groupId = null,
    ): AiKnowledgeDocument {
        $document = $this->ingestion->store(
            user: $this->author(),
            title: ($scope->value.' '.Str::random(6)),
            type: AiKnowledgeDocumentType::General,
            scope: $scope,
            content: $content,
            organizationId: $org?->id,
            branchId: $branchId,
            vicobaGroupId: $groupId,
            source: 'internal',
        );

        return $document->fresh() ?? $document;
    }

    private function embedding(string $text): array
    {
        return app(AiEmbeddingProviderService::class)->resolve()->embed($text);
    }

    private function searchPublicIds(string $query): array
    {
        return array_values(array_unique(array_map(
            fn (object $result) => $result->documentId,
            $this->retrieval->searchPublic($query),
        )));
    }

    public function test_search_public_returns_public_documents(): void
    {
        $document = $this->createPublicDocument(
            'FinancePro new members open a savings account in their VICOBA group.'
        );

        $this->assertSame(AiKnowledgeDocumentStatus::Active, $document->status);

        $ids = $this->searchPublicIds('savings account VICOBA FinancePro');

        $this->assertContains((int) $document->id, $ids);
    }

    public function test_search_public_never_returns_tenant_scoped_or_global_documents(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $group = $this->group($org);

        $content = 'FinancePro membership grants access to savings, shares, welfare and loans.';

        $this->createPublicDocument($content);
        $global = $this->createScopedDocument(AiKnowledgeScope::Global, $content);
        $orgDoc = $this->createScopedDocument(AiKnowledgeScope::Organization, $content, $org);
        $branchDoc = $this->createScopedDocument(AiKnowledgeScope::Branch, $content, $org, $branch->id);
        $groupDoc = $this->createScopedDocument(AiKnowledgeScope::Group, $content, $org, $branch->id, $group->id);

        $ids = $this->searchPublicIds('FinancePro membership savings shares welfare loans');

        $this->assertNotContains((int) $global->id, $ids);
        $this->assertNotContains((int) $orgDoc->id, $ids);
        $this->assertNotContains((int) $branchDoc->id, $ids);
        $this->assertNotContains((int) $groupDoc->id, $ids);

        // At least one public document is returned when the query matches.
        $this->assertNotEmpty($ids);
    }

    public function test_search_public_excludes_draft_and_archived_public_documents(): void
    {
        $content = 'FinancePro delinquency reports the overdue loan portfolio.';

        foreach ([AiKnowledgeDocumentStatus::Draft, AiKnowledgeDocumentStatus::Archived] as $status) {
            $document = AiKnowledgeDocument::factory()->forPublic()->create([
                'title' => 'Inactive '.Str::random(6),
                'document_type' => AiKnowledgeDocumentType::General,
                'content' => $content,
                'status' => $status,
                'source' => 'public-knowledge',
            ]);

            AiKnowledgeChunk::create([
                'ai_knowledge_document_id' => $document->id,
                'chunk_index' => 0,
                'content' => $content,
                'content_hash' => hash('sha256', $content),
                'token_count' => 10,
                'estimated_size' => 60,
                'embedding' => $this->embedding($content),
            ]);
        }

        $ids = $this->searchPublicIds($content);

        $this->assertNotContains(
            AiKnowledgeDocument::where('status', AiKnowledgeDocumentStatus::Draft->value)->value('id'),
            $ids,
        );
        $this->assertNotContains(
            AiKnowledgeDocument::where('status', AiKnowledgeDocumentStatus::Archived->value)->value('id'),
            $ids,
        );
    }

    public function test_public_documents_are_visible_to_authorized_authenticated_users(): void
    {
        $org = $this->makeOrganization();
        $user = $this->staff($org); // Loan Officer carries ai.knowledge.search

        $public = $this->createPublicDocument('FinancePro share accounts record purchases and redemptions.');

        $this->actingAs($user);

        $context = app(AiContextBuilderService::class)->build($user);

        $results = $this->retrieval->search($context, 'share purchases redemptions FinancePro');

        $ids = array_values(array_unique(array_map(fn (object $r) => $r->documentId, $results)));

        $this->assertContains((int) $public->id, $ids);
    }

    public function test_search_public_is_empty_with_no_public_documents(): void
    {
        $this->createScopedDocument(
            AiKnowledgeScope::Global,
            'FinancePro global-only policy content for signed-in users.'
        );

        $this->assertSame([], $this->searchPublicIds('FinancePro policy'));
    }

    public function test_search_public_returns_empty_for_blank_queries(): void
    {
        $this->assertSame([], $this->retrieval->searchPublic('   '));
    }
}
