<?php

namespace Tests\Unit;

use App\AI\Services\AiDomainInstructionService;
use App\AI\Services\AiIntentClassifier;
use App\AI\Services\AiLanguageService;
use App\Enums\AiLanguage;
use App\Enums\AiQuestionType;
use Tests\TestCase;

/**
 * Unit coverage for the deterministic grounding layer: question
 * classification, language detection, and the composed domain contract.
 *
 * These are pure functions of the message text by design, so they are asserted
 * exhaustively here rather than through the (deliberately slow, database-backed)
 * chat endpoint. The feature test AiSystemGroundingTest then proves the same
 * directives actually reach the provider payload.
 */
class AiIntentGroundingTest extends TestCase
{
    private AiIntentClassifier $classifier;

    private AiLanguageService $languages;

    private AiDomainInstructionService $domain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->classifier = new AiIntentClassifier;
        $this->languages = new AiLanguageService;
        $this->domain = new AiDomainInstructionService($this->languages);
    }

    public function test_member_and_organization_record_questions_require_system_data(): void
    {
        $systemData = [
            'What is my current loan balance?',
            'How much do I have in savings?',
            'When is my next loan payment due?',
            'Am I eligible for a loan?',
            'Can I qualify for another loan?',
            'How much do I owe?',
            'Show me our income statement',
            'What is our balance sheet?',
            'Give me the trial balance',
            'How many active members do we have?',
            'What is our PAR30?',
            'Show me our portfolio',
        ];

        foreach ($systemData as $message) {
            $this->assertSame(
                AiQuestionType::FinanceProSystemData,
                $this->classifier->classify($message),
                "[{$message}] must require authoritative FinancePro data.",
            );
        }
    }

    public function test_swahili_record_questions_require_system_data(): void
    {
        $systemData = [
            'Salio langu ni kiasi gani?',
            'Nina akiba kiasi gani?',
            'Mikopo yangu inabakiwa kiasi gani?',
            'Je, ninaweza kupata mkopo?',
            'Tuonyeshe suruhi ya mapato yetu',
            'Niwate kwanini?',
        ];

        foreach ($systemData as $message) {
            $this->assertSame(
                AiQuestionType::FinanceProSystemData,
                $this->classifier->classify($message),
                "[{$message}] must require authoritative FinancePro data.",
            );
        }
    }

    public function test_policy_questions_are_not_confused_with_record_questions(): void
    {
        $policy = [
            'Explain the loan eligibility criteria',
            'What are the withdrawal requirements?',
            'What is the interest rate policy?',
            'Sasa au kuna sera kuhusu riba?',
        ];

        foreach ($policy as $message) {
            $this->assertSame(
                AiQuestionType::FinanceProPolicy,
                $this->classifier->classify($message),
                "[{$message}] is a policy question, not a record question.",
            );
        }
    }

    public function test_platform_usage_questions_are_how_to(): void
    {
        foreach (['How do I reset my password in the system?', 'Jinsi ya kutumia dashibodi?'] as $message) {
            $this->assertSame(
                AiQuestionType::FinanceProHowTo,
                $this->classifier->classify($message),
                "[{$message}] is a platform how-to question.",
            );
        }
    }

    public function test_general_concepts_are_educational_but_out_of_scope_is_not(): void
    {
        $this->assertSame(
            AiQuestionType::GeneralEducational,
            $this->classifier->classify('What is collateral in general?'),
        );

        $outside = [
            'What is the weather in Dar es Salaam?',
            'Who won the football match?',
            'Tell me a joke',
            'Hali ya hewa leo ni vipi?',
            'Yapi timu ya Simba ilicheza?',
        ];

        foreach ($outside as $message) {
            $this->assertSame(
                AiQuestionType::OutsideScope,
                $this->classifier->classify($message),
                "[{$message}] is outside FinancePro's domain.",
            );
        }
    }

    public function test_classification_defaults_to_the_conservative_type(): void
    {
        $this->assertSame(
            AiQuestionType::OutsideScope,
            $this->classifier->classify('   '),
            'An empty message must not be answered from general knowledge.',
        );

        $this->assertSame(
            AiQuestionType::OutsideScope,
            $this->classifier->classify('asdkjh qwe zxc'),
            'An unrecognised question must decline rather than speculate.',
        );
    }

    public function test_language_detection_prefers_swahili_only_with_multiple_markers(): void
    {
        $this->assertSame(AiLanguage::English, $this->languages->detect('What is my loan balance?'));
        $this->assertSame(AiLanguage::Swahili, $this->languages->detect('Salio langu ni kiasi gani?'));
        $this->assertSame(AiLanguage::English, $this->languages->detect(''));
        $this->assertSame(AiLanguage::English, $this->languages->resolve('en', 'Salio langu ni kiasi gani?'));
        $this->assertSame(AiLanguage::Swahili, $this->languages->resolve('sw', 'What is my loan balance?'));
        $this->assertSame(AiLanguage::English, $this->languages->resolve('klingon', 'What is my loan balance?'));
    }

    public function test_base_contract_carries_identity_hierarchy_and_security_prompt(): void
    {
        $contract = $this->domain->base('What is my loan balance?');

        $this->assertStringContainsString('FinancePro Assistance', $contract);
        $this->assertStringContainsString('not a general-purpose chatbot', $contract);
        $this->assertStringContainsString('Sources of truth, in strict order', $contract);
        $this->assertStringContainsString('Response language: English', $contract);
    }

    public function test_base_contract_follows_the_question_language(): void
    {
        $contract = $this->domain->base('Salio langu ni kiasi gani?');

        $this->assertStringContainsString('Response language: Kiswahili', $contract);
        $this->assertStringContainsString('never convert currency', $contract);
    }

    public function test_grounding_directive_forbids_inventing_figures(): void
    {
        $systemData = $this->domain->grounding(AiQuestionType::FinanceProSystemData);

        $this->assertStringContainsString('No authoritative FinancePro source was retrieved', $systemData);
        $this->assertStringContainsString('must NOT answer it from general knowledge', $systemData);
        $this->assertStringContainsString('typical values', $systemData);
        $this->assertStringContainsString('sector averages', $systemData);

        // General education may still be explained, but never as live data.
        $educational = $this->domain->grounding(AiQuestionType::GeneralEducational);

        $this->assertStringContainsString('may explain this general concept', $educational);
        $this->assertStringContainsString('actual FinancePro data', $educational);

        // Outside the domain the assistant declines instead of answering.
        $outside = $this->domain->grounding(AiQuestionType::OutsideScope);

        $this->assertStringContainsString('Do not answer it', $outside);
        $this->assertStringContainsString('outside the domain', $outside);
    }

    public function test_grounding_directive_is_produced_for_every_question_type(): void
    {
        foreach (AiQuestionType::cases() as $type) {
            $this->assertNotSame(
                '',
                $this->domain->grounding($type),
                "A grounding directive must exist for [{$type->value}].",
            );
        }
    }

    public function test_domain_contract_can_be_disabled_without_leaking_a_partial_one(): void
    {
        $enabled = $this->domain->base('What is my loan balance?');
        $this->assertNotSame('', $enabled);

        config(['ai.domain_policy_enabled' => false]);

        $this->assertSame('', $this->domain->base('What is my loan balance?'));
        $this->assertSame('', $this->domain->grounding(AiQuestionType::FinanceProSystemData));

        config(['ai.domain_policy_enabled' => true]);
    }
}
