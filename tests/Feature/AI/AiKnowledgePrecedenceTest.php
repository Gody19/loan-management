<?php

namespace Tests\Feature\AI;

use App\AI\DTOs\AiRequestData;
use App\AI\DTOs\AiResponseData;
use App\AI\Services\AiContextBuilderService;
use App\AI\Services\AiKnowledgeIngestionService;
use App\AI\Services\AiKnowledgeRetrievalService;
use App\AI\Services\AiProviderService;
use App\Enums\AiKnowledgeDocumentType;
use App\Enums\AiKnowledgeScope;
use App\Models\AiKnowledgeDocument;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Acceptance coverage for knowledge scope precedence.
 *
 * The defect under test: ranking was driven by cosine similarity alone, so a
 * generic global chunk could outrank an organization, branch or group document
 * that actually described the caller's own policy. Every precedence test below
 * is written so the LOSING document is the closer semantic match — if these
 * pass, specificity is genuinely overriding relevance rather than merely
 * coinciding with it.
 *
 * Tenant isolation is asserted alongside precedence, because precedence must be
 * applied only inside the already-authorized candidate set: a narrower scope is
 * never allowed to widen access.
 */
class AiKnowledgePrecedenceTest extends AiTestCase
{
    private ?AiRequestData $captured = null;

    /**
     * The query used throughout. It is byte-identical to the global document, so
     * the global chunk scores a perfect 1.0 similarity and is the strongest
     * possible baseline to outrank.
     */
    private const QUERY = 'Borrowing limits for members are capped at two times aggregate savings before a new application is considered.';

    private const GLOBAL = self::QUERY;

    private const ORGANIZATION = 'This organization reviews every member loan request before it reaches the approval committee.';

    private const BRANCH = 'This branch office requires a countersignature on every member loan request before approval.';

    private const GROUP = 'This group settles every member loan request at its monthly meeting after discussion.';

    protected function setUp(): void
    {
        parent::setUp();

        // Keep the whole file focused on precedence rather than truncation.
        config(['ai.knowledge.top_k' => 10, 'ai.knowledge.min_similarity' => 0.0]);
    }

    private function publish(
        User $creator,
        string $content,
        AiKnowledgeScope $scope,
        ?int $organizationId = null,
        ?int $branchId = null,
        ?int $groupId = null,
    ): AiKnowledgeDocument {
        $this->actingAs($creator);

        return app(AiKnowledgeIngestionService::class)->store(
            user: $creator,
            title: 'Precedence '.Str::random(6),
            type: AiKnowledgeDocumentType::LoanPolicy,
            scope: $scope,
            content: $content,
            organizationId: $organizationId,
            branchId: $branchId,
            vicobaGroupId: $groupId,
            source: 'internal',
        )->fresh() ?? AiKnowledgeDocument::query()->latest('id')->firstOrFail();
    }

    private function search(string $term): array
    {
        return app(AiKnowledgeRetrievalService::class)->search(
            app(AiContextBuilderService::class)->build(auth()->user()),
            $term,
        );
    }

    /**
     * @return array<int, float>
     */
    private function similarities(array $results): array
    {
        $map = [];

        foreach ($results as $result) {
            $map[(int) $result->documentId] = (float) $result->similarity;
        }

        return $map;
    }

    public function test_global_document_is_returned_when_no_more_specific_document_exists(): void
    {
        $org = $this->makeOrganization();
        $staff = $this->staff($org);

        $global = $this->publish($staff, self::GLOBAL, AiKnowledgeScope::Global);

        $this->actingAs($staff);
        $results = $this->search(self::QUERY);

        $this->assertNotEmpty($results);
        $this->assertSame((int) $global->id, (int) $results[0]->documentId);
        $this->assertSame('global', $results[0]->scope);
    }

    public function test_organization_document_outranks_a_closer_global_document(): void
    {
        $org = $this->makeOrganization();
        $staff = $this->staff($org);

        $global = $this->publish($staff, self::GLOBAL, AiKnowledgeScope::Global);
        $organization = $this->publish($staff, self::ORGANIZATION, AiKnowledgeScope::Organization, $org->id);

        $this->actingAs($staff);
        $results = $this->search(self::QUERY);

        $scores = $this->similarities($results);

        $this->assertArrayHasKey((int) $global->id, $scores, 'The global document must still be retrievable.');
        $this->assertArrayHasKey((int) $organization->id, $scores);
        $this->assertGreaterThan(
            $scores[(int) $organization->id],
            $scores[(int) $global->id],
            'This test is only meaningful when the losing global chunk is the closer match.',
        );

        $this->assertSame(
            (int) $organization->id,
            (int) $results[0]->documentId,
            'An organization policy must outrank global documentation even at a lower similarity.',
        );
        $this->assertSame('organization', $results[0]->scope);
    }

    public function test_branch_document_outranks_an_organization_document(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $staff = $this->staff($org);
        $staff->branches()->attach($branch->id);

        // Both scopes exist; only the narrower one may win.
        $this->publish($staff, self::ORGANIZATION, AiKnowledgeScope::Organization, $org->id);
        $branchDocument = $this->publish($staff, self::BRANCH, AiKnowledgeScope::Branch, $org->id, $branch->id);

        $this->actingAs($staff);
        $results = $this->search(self::QUERY);

        $this->assertCount(2, $results, 'Both the branch and organization documents must be retrievable.');
        $this->assertSame((int) $branchDocument->id, (int) $results[0]->documentId);
        $this->assertSame('branch', $results[0]->scope);
    }

    public function test_group_document_outranks_a_branch_document(): void
    {
        $org = $this->makeOrganization();
        $branch = $this->branch($org);
        $group = $this->group($org);

        // Only a Super Administrator carries vicoba group context, because no
        // user-to-group assignment exists in the schema yet. Asserting group
        // precedence here therefore means asserting it for the one audience
        // that can currently reach group-scoped knowledge at all.
        $super = $this->superAdmin();

        $this->publish($super, self::BRANCH, AiKnowledgeScope::Branch, $org->id, $branch->id);
        $groupDocument = $this->publish(
            $super,
            self::GROUP,
            AiKnowledgeScope::Group,
            $org->id,
            $branch->id,
            $group->id,
        );

        $this->actingAs($super);
        $results = $this->search(self::QUERY);

        $this->assertSame((int) $groupDocument->id, (int) $results[0]->documentId);
        $this->assertSame('group', $results[0]->scope);
    }

    public function test_conflicting_organization_policy_wins_over_contradicting_global_policy(): void
    {
        $org = $this->makeOrganization();
        $staff = $this->staff($org);

        $global = $this->publish(
            $staff,
            'Aggregate borrowing is capped at two times savings for every member without exception.',
            AiKnowledgeScope::Global,
        );
        $organization = $this->publish(
            $staff,
            'Aggregate borrowing is capped at five times savings for every member of this organization.',
            AiKnowledgeScope::Organization,
            $org->id,
        );

        $this->actingAs($staff);
        $results = $this->search(self::QUERY);

        $this->assertSame((int) $organization->id, (int) $results[0]->documentId);

        $top = $results[0];
        $second = $results[1];

        $this->assertSame((int) $global->id, (int) $second->documentId);
        $this->assertStringContainsString('five times', $top->content);
        $this->assertStringContainsString('two times', $second->content);
    }

    public function test_unauthorized_organization_document_is_never_retrieved_even_when_it_matches_best(): void
    {
        $mine = $this->makeOrganization();
        $theirs = $this->makeOrganization();
        $staff = $this->staff($mine);

        $global = $this->publish($staff, self::GLOBAL, AiKnowledgeScope::Global);
        $foreign = $this->publish(
            $staff,
            self::QUERY,
            AiKnowledgeScope::Organization,
            $theirs->id,
        );

        $this->actingAs($staff);

        // The foreign document is a byte-perfect match for the query, so it would
        // trivially rank first if the tenant boundary were ever applied after
        // ranking instead of before it.
        $results = $this->search(self::QUERY);
        $ids = array_map(fn ($result) => (int) $result->documentId, $results);

        $this->assertNotContains((int) $foreign->id, $ids, 'Another organization\'s document must never be retrieved.');
        $this->assertSame([(int) $global->id], $ids);
    }

    public function test_same_scope_still_ranks_by_semantic_relevance(): void
    {
        $org = $this->makeOrganization();
        $staff = $this->staff($org);

        $relevant = $this->publish(
            $staff,
            'Borrowing limits for members are capped at two times aggregate savings.',
            AiKnowledgeScope::Global,
        );
        $unrelated = $this->publish(
            $staff,
            'Cold chain storage regulations for fresh produce are reviewed quarterly.',
            AiKnowledgeScope::Global,
        );

        $this->actingAs($staff);
        $results = $this->search(self::QUERY);

        $scores = $this->similarities($results);

        $this->assertSame((int) $relevant->id, (int) $results[0]->documentId);
        $this->assertGreaterThan($scores[(int) $unrelated->id], $scores[(int) $relevant->id]);
    }

    public function test_empty_knowledge_base_stays_grounded_instead_of_inventing_an_answer(): void
    {
        $staff = $this->staff($this->makeOrganization());

        $this->actingAs($staff);
        $this->assertSame([], $this->search(self::QUERY), 'No applicable knowledge must yield no results.');

        $this->captured = null;

        $this->mock(AiProviderService::class)
            ->shouldReceive('isEnabled')->andReturnTrue()
            ->shouldReceive('isAvailable')->andReturnTrue()
            ->shouldReceive('defaultModel')->andReturn('recording-model')
            ->shouldReceive('defaultProviderName')->andReturn('fake')
            ->shouldReceive('generate')
            ->andReturnUsing(function (AiRequestData $request): AiResponseData {
                $this->captured = $request;

                return new AiResponseData(
                    provider: 'fake',
                    model: 'recording-model',
                    content: 'recorded',
                    inputTokens: 1,
                    outputTokens: 1,
                    totalTokens: 2,
                    finishReason: 'stop',
                    providerRequestId: 'rec-1',
                    metadata: [],
                );
            });

        $this->postJson('/ai/chat', [
            'message' => 'Explain the FinancePro constitution requirements for joining as a member',
        ])->assertOk();

        $payload = implode("\n", array_map(fn ($message) => $message->content, $this->captured->messages));

        $this->assertStringContainsString(
            'No authoritative FinancePro source was retrieved',
            $payload,
            'An empty knowledge base must produce the existing ungrounded directive, not a fabricated answer.',
        );
    }
}
