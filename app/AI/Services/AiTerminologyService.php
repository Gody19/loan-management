<?php

namespace App\AI\Services;

/**
 * FinancePro terminology in Kiswahili, mapped onto the English concept names the
 * rest of the system is written in.
 *
 * This exists so that a question asked naturally in Kiswahili resolves to the
 * same authoritative FinancePro concept as its English equivalent. It is a
 * MATCHING aid only:
 *
 *  - normalize() output is used exclusively to decide which capability or which
 *    loan plan a question refers to. It is never displayed, never persisted and
 *    never returned to a member.
 *  - It performs no translation of values. Amounts, balances, percentages,
 *    account labels and currency symbols are left byte-for-byte untouched, so
 *    an authoritative figure can never be altered by passing through here.
 *
 * It is deliberately not a second language-detection system. Detecting the
 * response language remains the sole responsibility of AiLanguageService.
 */
class AiTerminologyService
{
    /**
     * Kiswahili phrase => FinancePro concept.
     *
     * Loan-purpose words are included deliberately: a member asking for a
     * "mkopo wa biashara" names a real plan in English as "Business Loan", and
     * without these the plan could never be resolved.
     *
     * @var array<string, string>
     */
    private const SWAHILI_TO_FINANCEPRO = [
        'ombi la mkopo' => 'loan application',
        'mfuko wa ustawi' => 'welfare fund',
        'mkopo' => 'loan',
        'mikopo' => 'loans',
        'malipo' => 'repayment',
        'deni' => 'outstanding debt',
        'akiba' => 'savings',
        'hisa' => 'shares',
        'mdhamini' => 'guarantor',
        'dhamana' => 'collateral',
        'salio' => 'balance',
        'riba' => 'interest',
        'ada' => 'fee',
        'mwanachama' => 'member',
        'wanachama' => 'members',
        'kikundi' => 'group',
        'tawi' => 'branch',
        'shirika' => 'organization',
        'biashara' => 'business',
        'kilimo' => 'agriculture',
        'elimu' => 'education',
        'dharura' => 'emergency',
        'binfi' => 'personal',
        'maendeleo' => 'development',
    ];

    /**
     * The single monetary amount stated in a question, or null when absent or
     * ambiguous.
     *
     * Numbers are read from the ORIGINAL text, never from the normalized text,
     * so a terminology substitution can never alter a figure. Only
     * digit-grouped numbers count, which keeps a year or a term in months from
     * being mistaken for a requested amount.
     */
    public function requestedAmount(string $text): ?float
    {
        preg_match_all('/\d[\d,._]*/u', $text, $matches);

        $amounts = [];

        foreach ($matches[0] as $candidate) {
            $value = (float) str_replace([',', '_'], '', $candidate);

            if ($value > 0) {
                $amounts[(string) $value] = $value;
            }
        }

        return count($amounts) === 1 ? (float) array_values($amounts)[0] : null;
    }

    /**
     * Rewrite Kiswahili FinancePro terms into their English equivalents so that
     * keyword matching against plans and capabilities sees the same concepts an
     * English question would produce. Longest phrases are replaced first so that
     * "ombi la mkopo" is not clipped by "mkopo".
     */
    public function normalize(string $text): string
    {
        $normalized = mb_strtolower($text);

        $terms = self::SWAHILI_TO_FINANCEPRO;

        uksort($terms, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));

        foreach ($terms as $swahili => $english) {
            $normalized = str_replace($swahili, $english, $normalized);
        }

        return $normalized;
    }

    /**
     * Every Kiswahili FinancePro term, for language-detection markers and
     * intent keyword tables that want to share one vocabulary.
     *
     * @return array<int, string>
     */
    public function swahiliTerms(): array
    {
        return array_keys(self::SWAHILI_TO_FINANCEPRO);
    }
}
