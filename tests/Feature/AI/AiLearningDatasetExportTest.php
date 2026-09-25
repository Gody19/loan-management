<?php

namespace Tests\Feature\AI;

use App\AI\Services\AiContextBuilderService;
use App\AI\Services\AiConversationService;
use App\AI\Services\AiEvaluationService;
use App\AI\Services\AiFeedbackService;
use App\Enums\AiEvaluationCriterion;
use App\Enums\AiEvaluationStatus;
use App\Enums\AiFeedbackType;
use App\Enums\AiLearningExampleStatus;
use App\Enums\AiMessageRole;
use App\Models\AiEvaluation;
use App\Models\AiFeedback;
use App\Models\AiLearningExample;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;

class AiLearningDatasetExportTest extends AiTestCase
{
    protected AiConversationService $conversations;

    protected AiContextBuilderService $contexts;

    protected AiFeedbackService $feedback;

    protected AiEvaluationService $evaluations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conversations = app(AiConversationService::class);
        $this->contexts = app(AiContextBuilderService::class);
        $this->feedback = app(AiFeedbackService::class);
        $this->evaluations = app(AiEvaluationService::class);
    }

    protected function createFeedback(
        User $submitter,
        Organization $organization,
        string $type = 'positive',
        ?string $correction = null,
        string $question = 'How do I check my loan balance?',
        string $response = 'Open the Loans module to review the balance.',
    ): AiFeedback {
        $conversation = $this->conversations->create(
            user: $submitter,
            organizationId: $organization->id,
        );

        $this->conversations->appendMessage($conversation, AiMessageRole::User, $question);
        $message = $this->conversations->appendMessage(
            $conversation,
            AiMessageRole::Assistant,
            $response,
            ['provider_message_id' => 'dataset-test', 'model' => 'gpt-dataset-test'],
        );
        $conversation->update(['provider' => 'fake', 'model' => 'gpt-dataset-test']);

        $result = $this->feedback->submit(
            $this->contexts->build($submitter),
            $submitter,
            $message->id,
            AiFeedbackType::from($type),
            $correction,
        );

        return $result['feedback']->fresh(['message', 'conversation', 'user']);
    }

    protected function scores(): array
    {
        return array_fill_keys(AiEvaluationCriterion::values(), 'pass');
    }

    protected function approve(User $reviewer, AiFeedback $feedback): AiEvaluation
    {
        $evaluation = $this->evaluations->start(
            $this->contexts->build($reviewer),
            $reviewer,
            $feedback,
        );

        return $this->evaluations->decide(
            $this->contexts->build($reviewer),
            $reviewer,
            $evaluation,
            AiEvaluationStatus::Approved->value,
            $this->scores(),
        );
    }

    protected function reject(User $reviewer, AiFeedback $feedback): AiEvaluation
    {
        $evaluation = $this->evaluations->start(
            $this->contexts->build($reviewer),
            $reviewer,
            $feedback,
        );

        return $this->evaluations->decide(
            $this->contexts->build($reviewer),
            $reviewer,
            $evaluation,
            AiEvaluationStatus::Rejected->value,
            ['safety' => 'fail'],
            null,
            'The response is not safe to use.',
        );
    }

    protected function approvedExample(
        User $submitter,
        Organization $organization,
        string $question = 'How do I check my loan balance?',
        string $response = 'Open the Loans module to review the balance.',
    ): AiLearningExample {
        $feedback = $this->createFeedback($submitter, $organization, question: $question, response: $response);

        $this->approve($this->staff($organization, 'Organization Administrator'), $feedback);

        return AiLearningExample::latest('id')->firstOrFail();
    }

    public function test_export_is_jsonl_and_tenant_scoped(): void
    {
        $organization = $this->makeOrganization();
        $otherOrganization = $this->makeOrganization();
        $reviewer = $this->staff($organization, 'Organization Administrator');

        $this->approvedExample(
            $this->staff($organization),
            $organization,
            question: 'Organization A question?',
            response: 'Organization A response.',
        );
        $this->approvedExample(
            $this->staff($otherOrganization),
            $otherOrganization,
            question: 'Organization B question?',
            response: 'Organization B response.',
        );

        $response = $this->actingAs($reviewer)->get(route('ai.dataset.export'));

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/x-ndjson; charset=utf-8')
            ->assertHeader('X-FinancePro-Example-Count', '1');

        $body = $response->getContent();
        $this->assertStringContainsString('Organization A question?', $body);
        $this->assertStringNotContainsString('Organization B question?', $body);
        $this->assertSame(1, substr_count(trim($body), "\n") + 1);
        $this->assertDatabaseHas('audit_logs', ['event' => 'ai.dataset.exported']);
    }

    public function test_export_requires_the_export_permission(): void
    {
        $organization = $this->makeOrganization();
        $reviewer = $this->staff($organization, 'Credit Officer');

        $this->actingAs($reviewer)
            ->get(route('ai.dataset.export'))
            ->assertForbidden();
    }

    public function test_dataset_versions_are_isolated_per_tenant(): void
    {
        $organization = $this->makeOrganization();
        $otherOrganization = $this->makeOrganization();

        $this->approvedExample($this->staff($organization), $organization, 'A question 1?', 'A response 1.');
        $this->approvedExample($this->staff($organization), $organization, 'A question 2?', 'A response 2.');
        $this->approvedExample($this->staff($otherOrganization), $otherOrganization, 'B question?', 'B response.');

        $aExamples = AiLearningExample::where('organization_id', $organization->id)->orderBy('id')->get();
        $bExamples = AiLearningExample::where('organization_id', $otherOrganization->id)->get();

        $this->assertSame([1, 2], $aExamples->pluck('dataset_version')->all());
        $this->assertSame([1], $bExamples->pluck('dataset_version')->all());
    }

    public function test_export_contains_sanitized_text_not_private_source_values(): void
    {
        $organization = $this->makeOrganization();
        $reviewer = $this->staff($organization, 'Organization Administrator');

        $this->approvedExample(
            $this->staff($organization),
            $organization,
            question: 'How do I contact support?',
            response: 'Email private.person@example.com and use account 1234567890.',
        );

        $body = $this->actingAs($reviewer)->get(route('ai.dataset.export'))->getContent();

        $this->assertStringNotContainsString('private.person@example.com', $body);
        $this->assertStringNotContainsString('1234567890', $body);
        $this->assertStringContainsString('[REDACTED]', $body);
    }

    public function test_revoked_example_is_retained_but_not_exportable(): void
    {
        $organization = $this->makeOrganization();
        $reviewer = $this->staff($organization, 'Organization Administrator');
        $example = $this->approvedExample($this->staff($organization), $organization);

        $this->actingAs($reviewer)
            ->postJson(route('ai.dataset.examples.revoke', $example))
            ->assertOk()
            ->assertJsonPath('data.status', AiLearningExampleStatus::Revoked->value);

        $this->assertDatabaseHas('ai_learning_examples', [
            'id' => $example->id,
            'status' => AiLearningExampleStatus::Revoked->value,
        ]);

        $this->actingAs($reviewer)
            ->get(route('ai.dataset.export'))
            ->assertHeader('X-FinancePro-Example-Count', '0');
    }

    public function test_dataset_list_is_tenant_scoped_and_includes_history(): void
    {
        $organization = $this->makeOrganization();
        $otherOrganization = $this->makeOrganization();
        $reviewer = $this->staff($organization, 'Organization Administrator');
        $example = $this->approvedExample($this->staff($organization), $organization);
        $this->approvedExample($this->staff($otherOrganization), $otherOrganization);

        $this->actingAs($reviewer)
            ->postJson(route('ai.dataset.examples.revoke', $example))
            ->assertOk();

        $this->actingAs($reviewer)
            ->getJson(route('ai.dataset.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $example->id)
            ->assertJsonPath('data.0.status', AiLearningExampleStatus::Revoked->value);
    }

    public function test_analytics_counts_only_the_reviewers_tenant(): void
    {
        $organization = $this->makeOrganization();
        $otherOrganization = $this->makeOrganization();
        $reviewer = $this->staff($organization, 'Organization Administrator');

        $ownFeedback = $this->createFeedback($this->staff($organization), $organization);
        $this->approve($reviewer, $ownFeedback);
        $rejected = $this->createFeedback($this->staff($organization), $organization, 'negative');
        $this->reject($reviewer, $rejected);
        $this->createFeedback($this->staff($organization), $organization, 'correction', 'Use the dashboard instead.');
        $this->createFeedback($this->staff($otherOrganization), $otherOrganization);

        $response = $this->actingAs($reviewer)
            ->getJson(route('ai.dataset.analytics'))
            ->assertOk()
            ->assertJsonPath('data.total_feedback', 3)
            ->assertJsonPath('data.approved', 1)
            ->assertJsonPath('data.rejected', 1)
            ->assertJsonPath('data.pending_evaluations', 0)
            ->assertJsonPath('data.by_type.positive', 1)
            ->assertJsonPath('data.by_type.negative', 1)
            ->assertJsonPath('data.by_type.correction', 1)
            ->assertJsonPath('data.approval_rate', 0.5);

        $this->assertCount(1, $response->json('data.by_model'));
        $this->assertDatabaseHas('audit_logs', ['event' => 'ai.feedback.analytics.viewed']);
    }

    public function test_analytics_requires_review_permission(): void
    {
        $organization = $this->makeOrganization();
        $submitter = $this->staff($organization);

        $this->actingAs($submitter)
            ->getJson(route('ai.dataset.analytics'))
            ->assertForbidden();
    }

    public function test_dataset_export_version_filter_returns_only_requested_version(): void
    {
        $organization = $this->makeOrganization();
        $reviewer = $this->staff($organization, 'Organization Administrator');

        $this->approvedExample($this->staff($organization), $organization, 'A question 1?', 'A response 1.');
        $this->approvedExample($this->staff($organization), $organization, 'A question 2?', 'A response 2.');

        $this->actingAs($reviewer)
            ->getJson(route('ai.dataset.index', ['version' => 2]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.dataset_version', 2);

        $this->actingAs($reviewer)
            ->get(route('ai.dataset.export', ['version' => 1]))
            ->assertHeader('X-FinancePro-Example-Count', '1')
            ->assertHeader('Content-Disposition', 'attachment; filename="ai-learning-dataset-v1.jsonl"');
    }

    public function test_dataset_examples_keep_immutable_sanitized_snapshots(): void
    {
        $organization = $this->makeOrganization();
        $reviewer = $this->staff($organization, 'Organization Administrator');
        $feedback = $this->createFeedback(
            $this->staff($organization),
            $organization,
            question: 'What is the support email?',
            response: 'The support email is private.person@example.com.',
        );
        $evaluation = $this->approve($reviewer, $feedback);
        $example = $evaluation->learningExample;

        $feedback->message->update(['content' => 'Changed after approval.']);

        $this->assertSame('The support email is [REDACTED].', $example->original_response);
        $this->assertDatabaseMissing('ai_learning_examples', [
            'id' => $example->id,
            'input_text' => 'Changed after approval.',
        ]);
    }
}
