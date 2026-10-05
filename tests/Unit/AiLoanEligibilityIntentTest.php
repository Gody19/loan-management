<?php

namespace Tests\Unit;

use App\AI\Services\AiIntentClassifier;
use App\Enums\AiQuestionType;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Guards the distinction between three questions that look almost identical:
 *
 *   "How can I check my loan eligibility?"  -> how-to   (explain the workflow)
 *   "Check my loan eligibility."            -> my data  (do the check for me)
 *   "What are the loan eligibility requirements?" -> policy (the rules)
 *
 * The defect this locks down: "How can I check my loan eligibility?" carries
 * both a personal marker ("my") and a record noun ("loan"), so the
 * personal-scope rule classified it as system data and the assistant tried to
 * return an eligibility verdict the member never asked for.
 */
class AiLoanEligibilityIntentTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: AiQuestionType}>
     */
    public static function intentMatrix(): array
    {
        $howTo = AiQuestionType::FinanceProHowTo;
        $data = AiQuestionType::FinanceProSystemData;
        $policy = AiQuestionType::FinanceProPolicy;

        return [
            // --- How-to: the member wants instructions, not a verdict. ---
            'how can I check eligibility' => ['How can I check my loan eligibility?', $howTo],
            'how do I check eligibility' => ['How do I check my loan eligibility?', $howTo],
            'how do I check whether I qualify' => ['How do I check whether I qualify for a loan?', $howTo],
            'how can I know if I qualify' => ['How can I know if I qualify for a loan?', $howTo],
            'where can I see eligibility' => ['Where can I see my loan eligibility?', $howTo],
            'where can I check eligibility' => ['Where can I check my loan eligibility?', $howTo],
            'what steps to check' => ['What steps do I follow to check loan eligibility?', $howTo],
            'what do I need to do' => ['What do I need to do to check my loan eligibility?', $howTo],
            'how do I know whether I qualify' => ['How do I know whether I qualify?', $howTo],
            'can I check my eligibility' => ['Can I check my eligibility?', $howTo],
            'how do I apply' => ['How do I apply for a loan?', $howTo],

            // Swahili. The subject concord is written as one word with the verb
            // stem ("ninastahili"), so a \b-delimited match cannot see it.
            'swahili nawezaje' => ['Nawezaje kuangalia kama ninastahili mkopo?', $howTo],
            'swahili ninawezaje' => ['Ninawezaje kujua kama nastahili mkopo?', $howTo],
            'swahili naangaliaje' => ['Naangaliaje ustahiki wa mkopo?', $howTo],
            'swahili nitajuaje' => ['Nitajuaje kama ninaweza kupata mkopo?', $howTo],
            'swahili ninaangalia wapi' => ['Ninaangalia wapi kama ninastahili mkopo?', $howTo],

            // --- System data: the member wants their own answer. ---
            'check my eligibility' => ['Check my loan eligibility.', $data],
            'am I eligible' => ['Am I eligible for a loan?', $data],
            'am I eligible for a plan and amount' => ['Am I eligible for a Business Loan of 500,000?', $data],
            'swahili je ninastahili' => ['Je, ninastahili mkopo?', $data],
            'my loan balance' => ['What is my loan balance?', $data],

            // --- Policy: the rules, not the member and not the process. ---
            'eligibility requirements' => ['What are the loan eligibility requirements?', $policy],
            'eligibility criteria' => ['What are the FinancePro loan eligibility criteria?', $policy],
            'what determines eligibility' => ['What determines loan eligibility?', $policy],
            'what is loan eligibility' => ['What is loan eligibility?', $policy],
        ];
    }

    #[DataProvider('intentMatrix')]
    public function test_it_classifies_the_question_into_the_right_intent(string $question, AiQuestionType $expected): void
    {
        $this->assertSame(
            $expected,
            (new AiIntentClassifier)->classify($question),
            "Wrong intent for: {$question}",
        );
    }

    /**
     * A "where do I ..." question about a plain record is still a request for
     * that record. The how-to rule must not swallow it, otherwise members could
     * no longer get their balance.
     */
    #[DataProvider('recordLocationMatrix')]
    public function test_a_location_question_about_a_record_stays_system_data(string $question): void
    {
        $this->assertSame(
            AiQuestionType::FinanceProSystemData,
            (new AiIntentClassifier)->classify($question),
            "A record lookup must not be downgraded to a how-to answer: {$question}",
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function recordLocationMatrix(): array
    {
        return [
            'where is my balance' => ['Where can I see my loan balance?'],
            'my savings balance' => ['What is my savings balance?'],
            'my shares' => ['What is my share balance?'],
            'my loan statement' => ['Where can I see my loan statement?'],
        ];
    }

    /**
     * Some record requests are deliberately left unclassified so the member gets
     * plain chat rather than a capability. What must never happen is the how-to
     * rule capturing them and answering "here is how to do that" instead.
     */
    #[DataProvider('neverHowToMatrix')]
    public function test_a_record_request_is_never_answered_as_a_how_to_question(string $question): void
    {
        $this->assertNotSame(
            AiQuestionType::FinanceProHowTo,
            (new AiIntentClassifier)->classify($question),
            "A record request must not be downgraded to a how-to answer: {$question}",
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function neverHowToMatrix(): array
    {
        return [
            'recent repayments' => ['Show me my recent repayments'],
            'my dues' => ['How much do I owe?'],
            'my statement' => ['Show me my statement'],
            'my application' => ['What is my current application status?'],
        ];
    }
}
