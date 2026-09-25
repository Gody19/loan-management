<?php

namespace App\AI\Services;

/**
 * Deterministic redaction applied before any text may enter the learning
 * dataset.
 *
 * This is deliberately NOT a universal PII detector. It is a fixed, ordered
 * rule list tuned to the material FinancePro actually stores, so the same
 * input always produces the same output and a reviewer can predict exactly
 * what will leave the system. When a redaction cannot be applied confidently
 * the text is still reported so the reviewer can reject the example instead of
 * approving something unsafe.
 *
 * Nothing here interprets, executes, or repairs the text. Redaction only ever
 * removes or masks; it never invents replacement financial values, because a
 * fabricated amount would be worse than a redaction for model training.
 */
class AiDatasetSanitizerService
{
    public const REDACTED = '[REDACTED]';

    /**
     * Ordered rules. Earlier rules win, and each rule records the categories it
     * removed so the sanitization report can be reviewed and exported safely.
     *
     * Identifier rules (member/loan) are deliberately case-sensitive: FinancePro
     * writes those prefixes in upper case, and a case-insensitive character
     * class would redact ordinary prose such as "the Loans list".
     *
     * @var array<string, string>
     */
    protected const RULES = [
        'secret' => '/\b(?:sk-[A-Za-z0-9_-]{8,}|Bearer\s+[A-Za-z0-9._-]{8,}|(?:api[_-]?key|access[_-]?token|refresh[_-]?token|secret|password|passwd|pwd)\s*[:=]\s*\S+)\b/i',
        'email' => '/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i',
        'nida' => '/\b(?:\d{4}[\s-]?){2,3}\d{4}\b/',
        'phone' => '/(?:\+?255|0)\s?\d{3}[\s-]?\d{3}[\s-]?\d{3,4}\b/',
        'account' => '/\b(?:acc(?:ount)?|a\/c|bank)[\s:#-]*[A-Z0-9-]*\d[A-Z0-9-]*\b/i',
        'member_number' => '/\b(?:MEM|MBR|VIC)[\s\/-]?[A-Z0-9-]*\d[A-Z0-9-]*\b/',
        'loan_number' => '/\b(?:LN|LOAN)[\s\/-]?[A-Z0-9-]*\d[A-Z0-9-]*\b/',
        'amount' => '/\bTSh[\s]?[0-9][0-9,._ ]*\b/i',
        'long_digit_run' => '/\b\d{9,}\b/',
    ];

    /**
     * Sanitize a single string.
     *
     * @return array{text: string, redactions: array<string, int>, safe: bool}
     */
    public function sanitize(?string $text): array
    {
        $original = (string) $text;
        $redactions = [];

        if (trim($original) === '') {
            return ['text' => '', 'redactions' => [], 'safe' => false];
        }

        $sanitized = $original;

        foreach (self::RULES as $category => $pattern) {
            $count = 0;
            $sanitized = preg_replace_callback(
                $pattern,
                function () use (&$count) {
                    $count++;

                    return self::REDACTED;
                },
                $sanitized,
            ) ?? $sanitized;

            if ($count > 0) {
                $redactions[$category] = $count;
            }
        }

        $sanitized = trim((string) preg_replace('/[ \t]+/u', ' ', $sanitized));

        return [
            'text' => $sanitized,
            'redactions' => $redactions,
            'safe' => $this->isSafe($sanitized),
        ];
    }

    /**
     * Sanitize a full example payload in one pass.
     *
     * @return array{
     *     input_text: string,
     *     original_response: string,
     *     corrected_response: string|null,
     *     report: array<string, int>,
     *     safe: bool
     * }
     */
    public function sanitizeExample(string $inputText, string $originalResponse, ?string $correctedResponse = null): array
    {
        $input = $this->sanitize($inputText);
        $response = $this->sanitize($originalResponse);
        $corrected = $correctedResponse === null ? null : $this->sanitize($correctedResponse);

        $report = $this->mergeReports([
            'input' => $input['redactions'],
            'response' => $response['redactions'],
            'correction' => $corrected['redactions'] ?? [],
        ]);

        $safe = $input['safe'] && $response['safe'] && ($corrected === null || $corrected['safe']);

        // A correction that was entirely redactions carries no learning signal,
        // so it is dropped rather than exported as a bare placeholder.
        $correctedText = $corrected === null
            ? null
            : (($corrected['text'] !== '' && ! $this->isOnlyRedactions($corrected['text']))
                ? $corrected['text']
                : null);

        return [
            'input_text' => $input['text'],
            'original_response' => $response['text'],
            'corrected_response' => $correctedText,
            'report' => $report,
            'safe' => $safe,
        ];
    }

    /**
     * A sanitized string is only usable when it still carries content and no
     * residual instruction-like payload survived redaction.
     */
    protected function isSafe(string $sanitized): bool
    {
        if (mb_strlen(trim($sanitized)) < 3) {
            return false;
        }

        return ! $this->containsInstructionPayload($sanitized);
    }

    /**
     * True when the sanitized string is nothing but redaction placeholders and
     * the punctuation that joined them.
     */
    protected function isOnlyRedactions(string $text): bool
    {
        $stripped = str_replace(self::REDACTED, '', $text);

        return trim(preg_replace('/[\s,.;:()\[\]{}\-]+/u', '', $stripped) ?? '') === '';
    }

    /**
     * Detect residual prompt-injection style control text that must never be
     * promoted into a training example. A reviewer can still reject an example
     * for any other reason; this only blocks the obvious class.
     */
    protected function containsInstructionPayload(string $text): bool
    {
        $needles = [
            'ignore all previous',
            'ignore previous instructions',
            'disregard all prior',
            'system prompt',
            'you are now',
            'execute this command',
            'select * from',
            'drop table',
            '<?php',
        ];

        $lower = mb_strtolower($text);

        foreach ($needles as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, array<string, int>>  $groups
     * @return array<string, int>
     */
    protected function mergeReports(array $groups): array
    {
        $merged = [];

        foreach ($groups as $counts) {
            foreach ($counts as $category => $count) {
                $merged[$category] = ($merged[$category] ?? 0) + $count;
            }
        }

        ksort($merged);

        return $merged;
    }
}
