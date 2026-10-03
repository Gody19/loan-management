<?php

namespace Tests\Feature\AI;

use App\AI\DTOs\AiRequestData;
use App\AI\DTOs\AiResponseData;
use App\AI\Services\AiChatOrchestrationService;
use App\AI\Services\AiContextBuilderService;
use App\AI\Services\AiProviderService;
use App\AI\Services\AiToolRunnerService;

/**
 * Acceptance coverage for the system-data-first correction.
 *
 * The defect under test: a FinancePro question that produced no authoritative
 * result used to reach the provider as a bare prompt, and the model answered
 * it from general knowledge. These tests capture the REAL provider payload and
 * assert that the platform identity, the source-of-truth hierarchy and the
 * question-type grounding directive are all present — i.e. the correction
 * works end to end, not just in isolation.
 */
class AiSystemGroundingTest extends AiTestCase
{
    private ?AiRequestData $captured = null;

    /**
     * Replace the provider with a recording double so the exact payload sent
     * upstream can be asserted. Everything else in the pipeline (guardrail,
     * context builder, orchestration, policy, runner) stays real.
     */
    private function recordProvider(): void
    {
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
    }

    private function payload(): string
    {
        $this->assertNotNull($this->captured, 'The provider must have been called.');

        return implode("\n", array_map(fn ($message) => $message->content, $this->captured->messages));
    }

    public function test_platform_identity_and_source_of_truth_hierarchy_always_reach_the_provider(): void
    {
        $org = $this->makeOrganization();
        $this->actingAs($this->staff($org));

        $this->recordProvider();
        $this->postJson('/ai/chat', ['message' => 'Hello'])->assertOk();

        $payload = $this->payload();

        $this->assertStringContainsString((string) config('ai.identity.name'), $payload);
        $this->assertStringContainsString('not a general-purpose chatbot', $payload);
        $this->assertStringContainsString('Sources of truth, in strict order', $payload);
        $this->assertStringContainsString('Response language:', $payload);
    }

    public function test_financepro_question_with_no_authoritative_data_carries_a_no_fabrication_directive(): void
    {
        $org = $this->makeOrganization();
        $this->actingAs($this->staff($org));

        $this->recordProvider();

        // No registered capability answers a member count, and no knowledge
        // document exists on a fresh install: this is exactly the case that
        // previously reached the model as a bare prompt.
        $this->postJson('/ai/chat', [
            'message' => 'How many active members do we have?',
        ])->assertOk();

        $payload = $this->payload();

        $this->assertStringContainsString(
            'No authoritative FinancePro source was retrieved',
            $payload,
            'An unanswered FinancePro question must carry the no-fabrication directive.',
        );
        $this->assertStringContainsString(
            'must NOT answer it from general knowledge',
            $payload,
        );
        $this->assertStringContainsString('sector averages', $payload);
    }

    public function test_out_of_domain_question_is_declined_rather_than_answered(): void
    {
        $org = $this->makeOrganization();
        $this->actingAs($this->staff($org));

        $this->recordProvider();
        $this->postJson('/ai/chat', ['message' => 'Who won the football match?'])->assertOk();

        $payload = $this->payload();

        $this->assertStringContainsString('outside the domain of FinancePro Assistance', $payload);
        $this->assertStringContainsString('Do not answer it', $payload);
    }

    public function test_identity_question_is_answered_from_the_registry_not_improvised(): void
    {
        $org = $this->makeOrganization();
        $user = $this->staff($org);
        $this->actingAs($user);

        $context = app(AiContextBuilderService::class)->build($user);

        $plan = app(AiChatOrchestrationService::class)->plan($context, 'Who are you?');
        $this->assertNotNull($plan, 'An identity question must be routed to a capability.');
        $this->assertSame('ai.identity.view', $plan->capability);

        $this->recordProvider();
        $this->postJson('/ai/chat', ['message' => 'Who are you?'])->assertOk();

        $payload = $this->payload();

        $this->assertStringContainsString((string) config('ai.identity.name'), $payload);
        $this->assertStringContainsString('FINANCEPRO_DATUM', $payload);
    }

    public function test_swahili_financepro_question_is_grounded_and_answered_in_swahili(): void
    {
        $org = $this->makeOrganization();
        $this->actingAs($this->staff($org));

        $this->recordProvider();
        $this->postJson('/ai/chat', [
            'message' => 'Salio langu ni kiasi gani?',
        ])->assertOk();

        $payload = $this->payload();

        $this->assertStringContainsString('Response language: Kiswahili', $payload);
        $this->assertStringContainsString(
            'No authoritative FinancePro source was retrieved',
            $payload,
            'A Swahili question must be grounded exactly like its English equivalent.',
        );
    }

    public function test_accounting_statement_questions_route_to_the_statement_capabilities(): void
    {
        $org = $this->makeOrganization();
        $user = $this->staff($org, 'Organization Administrator');
        $this->actingAs($user);

        $context = app(AiContextBuilderService::class)->build($user);
        $orchestrator = app(AiChatOrchestrationService::class);

        $expectations = [
            'Show me our income statement' => 'ai.accounting.income_statement',
            'What is our balance sheet?' => 'ai.accounting.balance_sheet',
            'Give me the trial balance' => 'ai.accounting.trial_balance',
        ];

        foreach ($expectations as $message => $capability) {
            $plan = $orchestrator->plan($context, $message);

            $this->assertNotNull($plan, "[{$message}] must resolve to a capability.");
            $this->assertSame($capability, $plan->capability, "[{$message}] routed incorrectly.");
        }
    }

    public function test_grounding_directive_discloses_nothing_about_authorization_or_records(): void
    {
        $org = $this->makeOrganization();
        $other = $this->makeOrganization();
        $user = $this->staff($org);
        $user->organizations()->attach($other->id);
        $this->actingAs($user);

        $this->recordProvider();
        $this->postJson('/ai/chat', [
            'message' => 'How many active members do we have?',
        ])->assertOk();

        $payload = $this->payload();

        foreach ([
            'unauthorized', 'forbidden', 'permission denied', 'not authorized',
            'organization_id', 'branch_id',
            // The other organization the caller belongs to must not be named.
            $other->name,
        ] as $leak) {
            $this->assertStringNotContainsString(
                $leak,
                $payload,
                "The grounding directive must not disclose [{$leak}].",
            );
        }
    }

    public function test_no_system_message_is_ever_persisted(): void
    {
        $org = $this->makeOrganization();
        $this->actingAs($this->staff($org));

        $this->recordProvider();
        $this->postJson('/ai/chat', ['message' => 'Who are you?'])->assertOk();

        $this->assertDatabaseMissing('ai_messages', ['role' => 'system']);
    }

    public function test_statement_capabilities_return_a_well_formed_envelope_without_guessing(): void
    {
        $org = $this->makeOrganization();
        $user = $this->staff($org, 'Organization Administrator');
        $this->actingAs($user);

        $context = app(AiContextBuilderService::class)->build($user);
        $runner = app(AiToolRunnerService::class);

        $expectations = [
            'ai.accounting.income_statement' => 'income_statement',
            'ai.accounting.balance_sheet' => 'balance_sheet',
            'ai.accounting.trial_balance' => 'trial_balance',
        ];

        foreach ($expectations as $capability => $statement) {
            $result = $runner->run($context, $capability, [], $user);

            $this->assertSame($statement, $result['statement'], "[{$capability}] must label its statement.");
            $this->assertArrayHasKey('currency', $result);
            $this->assertArrayHasKey('totals', $result);
            $this->assertArrayHasKey('statements', $result);
            $this->assertSame($context->organizationIds !== [], $result['organization_count'] > 0);
        }

        // Date arguments are validated, never guessed: a malformed value is
        // discarded so the authoritative service applies its own default.
        $this->recordProvider();
        $this->postJson('/ai/chat', ['message' => 'Show me our income statement'])->assertOk();

        $this->assertStringContainsString('FINANCEPRO_DATUM', $this->payload());
    }

    public function test_capability_enumeration_never_exposes_an_unpermitted_capability(): void
    {
        $org = $this->makeOrganization();

        // A VICOBA Member holds chat permissions but not the organization-level
        // intelligence permissions.
        $member = $this->member($org);
        $user = $this->vicobaUser($org, $member);
        $this->actingAs($user);

        $context = app(AiContextBuilderService::class)->build($user);

        $result = app(AiToolRunnerService::class)->run(
            $context,
            'ai.identity.view',
            [],
            $user,
        );

        $this->assertArrayHasKey('capabilities', $result);
        $this->assertNotEmpty($result['capabilities']);

        $joined = strtolower(implode(' ', $result['capabilities']));

        $this->assertStringNotContainsString('portfolio at risk', $joined);
        $this->assertStringNotContainsString('predictive intelligence', $joined);
        $this->assertSame(
            $context->memberId !== null,
            $result['authenticated_scope']['has_linked_member_record'],
        );
    }
}
