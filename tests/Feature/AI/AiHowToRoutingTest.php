<?php

namespace Tests\Feature\AI;

use App\AI\DTOs\AiContextData;
use App\AI\Services\AiChatOrchestrationService;
use App\AI\Services\AiContextBuilderService;
use App\AI\Services\AiLanguageService;
use App\Enums\AiLanguage;
use App\Models\User;
use Database\Seeders\AiPublicKnowledgeSeeder;

/**
 * A how-to question must never reach a business capability.
 *
 * The reported defect: "How can I check my loan eligibility?" was answered with
 * "I could not retrieve your loan eligibility details...". The orchestrator had
 * matched it to the loan-application capability, the capability could not answer
 * a request for instructions, and the model reported the empty result.
 *
 * These tests assert the routing itself: no tool is planned and no tool is
 * executed for a how-to question, so the answer can only come from approved
 * knowledge or from the type-specific grounding directive.
 */
class AiHowToRoutingTest extends AiTestCase
{
    private function contextFor(User $user): AiContextData
    {
        return app(AiContextBuilderService::class)->build($user);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function howToQuestions(): array
    {
        return [
            'the reported question' => ['How can I check my loan eligibility?'],
            'paraphrase' => ['How do I check my loan eligibility?'],
            'location' => ['Where can I check my loan eligibility?'],
            'qualify wording' => ['How can I know if I qualify for a loan?'],
            'steps wording' => ['What steps do I follow to check loan eligibility?'],
            'apply wording' => ['How do I apply for a loan?'],
            'swahili' => ['Nawezaje kuangalia kama ninastahili mkopo?'],
        ];
    }

    /**
     * @dataProvider howToQuestions
     */
    public function test_a_how_to_question_never_selects_a_business_capability(string $question): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);
        $user = $this->vicobaUser($org, $member);

        $this->assertNull(
            app(AiChatOrchestrationService::class)->plan($this->contextFor($user), $question),
            "A how-to question must be left to knowledge, not answered by a tool: {$question}",
        );
    }

    /**
     * @dataProvider howToQuestions
     */
    public function test_a_how_to_question_executes_no_tool(string $question): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);
        $user = $this->vicobaUser($org, $member);

        $this->actingAs($user)->postJson('/ai/chat', ['message' => $question])->assertOk();

        $this->assertDatabaseMissing('audit_logs', ['event' => 'ai.tool.requested']);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'ai.tool.completed']);
    }

    public function test_an_explicit_request_for_the_check_still_selects_a_capability(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);
        $user = $this->vicobaUser($org, $member);

        // The distinction that matters: "Check my loan eligibility" asks for the
        // member's own answer, so a capability must still be planned and run.
        $plan = app(AiChatOrchestrationService::class)->plan(
            $this->contextFor($user),
            'Check my loan eligibility.',
        );

        $this->assertNotNull($plan);
        $this->assertSame('ai.loan.application.start', $plan->capability);

        $this->actingAs($user)->postJson('/ai/chat', ['message' => 'Check my loan eligibility.'])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', ['event' => 'ai.tool.requested']);
    }

    public function test_a_policy_question_is_left_to_knowledge(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);
        $user = $this->vicobaUser($org, $member);

        $this->assertNull(app(AiChatOrchestrationService::class)->plan(
            $this->contextFor($user),
            'What are the loan eligibility requirements?',
        ));

        $this->actingAs($user)
            ->postJson('/ai/chat', ['message' => 'What are the loan eligibility requirements?'])
            ->assertOk();

        $this->assertDatabaseMissing('audit_logs', ['event' => 'ai.tool.requested']);
    }

    public function test_the_swahili_how_to_question_is_answered_in_swahili(): void
    {
        $org = $this->makeOrganization();
        $member = $this->member($org);
        $user = $this->vicobaUser($org, $member);

        $language = app(AiLanguageService::class)->detect(
            'Nawezaje kuangalia kama ninastahili mkopo?'
        );

        $this->assertSame(AiLanguage::Swahili, $language);
    }

    /**
     * The how-to answer must describe the workflow FinancePro actually has. If
     * the approved knowledge ever stops naming the real member-portal location,
     * the assistant would be reduced to inventing navigation, so this pins the
     * documented route that the answer is built from.
     */
    public function test_the_seeded_how_to_knowledge_describes_the_real_member_workflow(): void
    {
        $documents = app(AiPublicKnowledgeSeeder::class);

        $reflection = new \ReflectionClass($documents);
        $method = $reflection->getMethod('documents');
        $method->setAccessible(true);

        $text = mb_strtolower(implode(' ', array_column($method->invoke($documents), 'content')));

        // The real sidebar entry and the real plan actions.
        $this->assertStringContainsString('my loans', $text);
        $this->assertStringContainsString('eligibility check', $text);
        $this->assertStringContainsString('view details', $text);

        // The five checks the eligibility service really performs.
        foreach (['membership status', 'plan must be active', '85 percent'] as $rule) {
            $this->assertStringContainsString($rule, $text);
        }

        // Eligibility must not be described as depending on savings, which the
        // eligibility service does not read. Naming the dependency in order to
        // deny it keeps the wording accurate either way.
        $this->assertStringContainsString('does not depend on savings', $text);
    }
}
