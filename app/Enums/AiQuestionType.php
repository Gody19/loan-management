<?php

namespace App\Enums;

/**
 * What kind of question the caller actually asked, decided server-side before
 * any provider call.
 *
 * This is the switch that keeps the assistant system-data-first. Because the
 * type is known BEFORE the model is invoked, the assistant can tell the model
 * exactly what it is allowed to do when no authoritative FinancePro data was
 * retrieved — which is the defect this classification was introduced to fix:
 * an unclassified question used to fall straight through to a bare model call
 * that answered FinancePro questions from pretrained general knowledge.
 */
enum AiQuestionType: string
{
    /**
     * A question whose answer lives in FinancePro records: balances, loans,
     * statements, portfolio, counts. Never answerable from general knowledge.
     */
    case FinanceProSystemData = 'financepro_system_data';

    /**
     * A question about FinancePro's own policies, procedures, handbooks,
     * rates or FAQs. Answerable only from approved knowledge documents.
     */
    case FinanceProPolicy = 'financepro_policy';

    /**
     * A question about how to use the FinancePro platform.
     */
    case FinanceProHowTo = 'financepro_howto';

    /**
     * A genuinely general finance/microfinance concept question, unrelated to
     * any FinancePro record. Safe to answer from general knowledge, provided
     * the answer never asserts live FinancePro figures.
     */
    case GeneralEducational = 'general_educational';

    /**
     * Anything outside the assistant's domain (sport, weather, politics...).
     * Declined rather than answered, so the assistant never becomes a generic
     * chatbot.
     */
    case OutsideScope = 'outside_scope';

    /**
     * Whether the answer must be grounded in an authoritative FinancePro
     * source. True for every type except genuinely general education.
     */
    public function requiresFinanceProSource(): bool
    {
        return $this !== self::GeneralEducational;
    }

    /**
     * Whether this type is a question the assistant is expected to serve from
     * a registered tool capability.
     */
    public function expectsToolData(): bool
    {
        return $this === self::FinanceProSystemData;
    }

    public function label(): string
    {
        return match ($this) {
            self::FinanceProSystemData => 'FinancePro system data',
            self::FinanceProPolicy => 'FinancePro policy',
            self::FinanceProHowTo => 'FinancePro how-to',
            self::GeneralEducational => 'General educational',
            self::OutsideScope => 'Outside scope',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
