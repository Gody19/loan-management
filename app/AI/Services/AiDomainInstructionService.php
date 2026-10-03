<?php

namespace App\AI\Services;

use App\Enums\AiLanguage;
use App\Enums\AiQuestionType;

/**
 * Composes the assistant's domain contract for the provider payload.
 *
 * The security prompt alone is not a contract: it says what the assistant must
 * not do, but never what it IS and never what it may do when no authoritative
 * source was found. That gap is the whole reason a question such as "what is my
 * current loan balance" could reach the model as a bare prompt and be answered
 * from pretrained knowledge. This service closes it by assembling, in order:
 *
 *   1. identity — the assistant is the FinancePro platform assistant, not a
 *      general-purpose chatbot;
 *   2. source-of-truth hierarchy — authoritative FinancePro data first, then
 *      approved knowledge, then product docs, then general knowledge;
 *   3. the existing security prompt, unchanged and still env-overridable;
 *   4. language policy.
 *
 * A separate grounding(AiQuestionType) directive is injected by the caller ONLY
 * when the question was not answered from an authoritative FinancePro source.
 *
 * None of this is a security boundary and none of it authorizes anything:
 * Laravel authorization (AiToolPolicy / AiGuardrailService) and tenant scoping
 * remain authoritative. These strings shape an answer; they cannot widen a
 * permission or return a record.
 */
class AiDomainInstructionService
{
    public function __construct(
        private readonly AiLanguageService $languages,
    ) {}

    /**
     * Escape hatch parity with AI_SYSTEM_INSTRUCTIONS. When disabled, the
     * composed domain contract is omitted entirely and the caller falls back to
     * the configured system_instructions alone.
     */
    public function enabled(): bool
    {
        return (bool) config('ai.domain_policy_enabled', true);
    }

    /**
     * The always-present assistant contract.
     */
    public function base(?string $message = null): string
    {
        if (! $this->enabled()) {
            return '';
        }

        $sections = [
            $this->identity(),
            $this->hierarchy(),
            $this->security(),
            $this->language($message),
        ];

        return trim(implode("\n\n", array_filter(
            $sections,
            fn (string $section) => trim($section) !== '',
        )));
    }

    /**
     * The no-fabrication directive for a question that produced no
     * authoritative FinancePro source.
     */
    public function grounding(AiQuestionType $type): string
    {
        if (! $this->enabled()) {
            return '';
        }

        $directive = (string) config(
            "ai.answer_policy.grounding.{$type->value}",
            '',
        );

        if (trim($directive) === '') {
            return '';
        }

        return trim(
            'No authoritative FinancePro source was retrieved for this question '
            ."({$type->label()}).\n{$directive}"
        );
    }

    protected function identity(): string
    {
        $name = (string) config('ai.identity.name', 'FinancePro Assistance');
        $platform = (string) config('ai.identity.platform', 'FinancePro');
        $operator = (string) config('ai.identity.operator', '');
        $role = (string) config('ai.identity.role', '');

        $identity = "You are {$name}, {$role} built into {$platform}."
            .' You are not a general-purpose chatbot: your subject is '
            .'FinancePro — the data, policies and product of this platform.';

        if (trim($operator) !== '') {
            $identity .= " You are operated by {$operator}.";
        }

        return $identity.' When asked who or what you are, answer with this '
            .'identity — never describe yourself as a general AI assistant and '
            .'never claim a role outside FinancePro.';
    }

    protected function hierarchy(): string
    {
        $rules = (array) config('ai.answer_policy.hierarchy', []);

        if ($rules === []) {
            return '';
        }

        $lines = ['Sources of truth, in strict order of precedence:'];

        foreach (array_values($rules) as $index => $rule) {
            $lines[] = '  '.($index + 1).'. '.trim((string) $rule);
        }

        return implode("\n", $lines);
    }

    protected function security(): string
    {
        return trim((string) config('ai.system_instructions', ''));
    }

    protected function language(?string $message): string
    {
        if (! (bool) config('ai.answer_policy.language.detect', true)) {
            return '';
        }

        $directive = trim((string) config('ai.answer_policy.language.directive', ''));

        if ($directive === '') {
            return '';
        }

        $language = $message !== null
            ? $this->languages->resolve(null, $message)
            : AiLanguage::default();

        return 'Response language: '.$language->label().".\n{$directive}";
    }
}
