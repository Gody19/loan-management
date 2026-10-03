<?php

namespace App\AI\Services;

use App\Enums\AiLanguage;

/**
 * Deterministic server-side detection of the language a question was asked in.
 *
 * Language detection is a marker scan, not a model call: the assistant must
 * answer in the caller's language consistently for every tenant, and a model
 * guess would make that behaviour non-reproducible (and would spend a provider
 * call to decide something the server already knows). It is also what makes the
 * assistant voice-ready — a transcription arrives as plain text and is
 * classified by exactly this same code path, so no channel-specific logic is
 * required to support speech.
 *
 * Detection only chooses the *response* language. It never widens authorization:
 * a Swahili question is routed, permission-checked and scoped exactly like its
 * English equivalent.
 */
class AiLanguageService
{
    /**
     * Unambiguous Swahili function words and connective markers. These are
     * selected because they do not appear in ordinary English finance
     * questions, which keeps the detector conservative: an English question
     * containing a single overlapping word ("us", "wa", "hi") is not
     * misclassified.
     *
     * @var array<string, string[]>
     */
    private const SWAHILI_MARKERS = [
        'question_words' => [
            'nini', 'gani', 'vipi', 'wapi', 'nani', 'lini', 'kwa nini',
        ],
        'pronouns' => [
            'wangu', 'yangu', 'zangu', 'yetu', 'wetu', 'wao', 'yeye',
            'sisi', 'nyinyi', 'ninun', 'wewe',
        ],
        'verbs' => [
            'niambie', 'nipe', 'tuna', 'tazama', 'hesabu', 'onyesha',
            'saidia', 'unganisha', 'jaza', 'chagua',
        ],
        'courtesy_and_connectives' => [
            'asante', 'karibu', 'habari', 'tafadhali', 'kwa sababu',
            'lakini', 'pia', 'hapana', 'sawa', 'kwamba', 'kuhusu',
            'kwa hiyo', 'wakati', 'baada', 'kabla', 'ndani', 'juu ya',
        ],
        'domain_terms' => [
            'akiba', 'hisa', 'mikopo', 'malipo', 'salio', 'mwezi',
            'mwaka', 'kipa', 'shule', 'wanachama', 'mwanachama', 'kopo',
            'hisa za', 'aka ya', 'zinazo', 'zilizo', 'walilipa',
            'nini kina', 'kinachobaki', 'inayoweza',
        ],
    ];

    public function detect(string $message): AiLanguage
    {
        $text = mb_strtolower(trim($message));

        if ($text === '') {
            return AiLanguage::default();
        }

        $score = 0;

        foreach (self::SWAHILI_MARKERS as $markers) {
            foreach ($markers as $marker) {
                if ($this->contains($text, $marker)) {
                    $score++;
                }
            }
        }

        // A single marker is not enough to flip the response language: it is
        // more likely an English word or a product name than a language switch.
        return $score >= 2 ? AiLanguage::Swahili : AiLanguage::default();
    }

    /**
     * Resolve the response language: an explicit validated preference wins,
     * otherwise the question's own language is used.
     */
    public function resolve(?string $requested, string $message): AiLanguage
    {
        // An explicit, recognised preference wins — including English, which is
        // also the detection default and must therefore not be treated as
        // "unset". An unrecognised value falls back to detection rather than
        // erroring, so a bad client value can never widen assistant behaviour.
        if ($requested !== null && trim($requested) !== '') {
            $explicit = AiLanguage::tryFrom(strtolower(trim($requested)));

            if ($explicit !== null) {
                return $explicit;
            }
        }

        return $this->detect($message);
    }

    /**
     * Word-boundary containment for multi-word markers, substring containment
     * for single-word markers, so "nini" does not match "ninun".
     */
    protected function contains(string $text, string $needle): bool
    {
        if (! str_contains($needle, ' ')) {
            return (bool) preg_match('/\b'.preg_quote($needle, '/').'\b/u', $text);
        }

        return str_contains($text, $needle);
    }
}
