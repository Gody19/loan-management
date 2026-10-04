<?php

namespace App\AI\Services;

use App\Enums\AiQuestionType;

/**
 * Deterministic server-side classification of a question into an AiQuestionType.
 *
 * This runs BEFORE the provider is called, which is the whole point: the
 * assistant can only refuse to fabricate FinancePro facts if it already knows
 * that the question demands FinancePro facts. Previously the question type was
 * never established, so a miss in the keyword router produced a bare model call
 * with no grounding directive, and the model answered member-balance and
 * eligibility questions from pretrained general finance knowledge.
 *
 * Deliberate constraints (this mirrors the existing orchestration discipline):
 *  - Explicit marker tables and word-boundary regexes, evaluated in order,
 *    first match wins. No model output, no extra provider call, no arbitrary
 *    routing.
 *  - English and Swahili are classified by the same rules, so a Swahili
 *    question is grounded exactly like its English equivalent.
 *  - Classification is ADVISORY for answer shaping only. It is never an
 *    authorization surface: it grants no permission, no scope and no data. A
 *    misclassification can therefore never expose data — it can only change
 *    which grounding directive the model is given, and the worst case is a
 *    refusal to answer a question that was in scope.
 *  - The default is the conservative type (OutsideScope), not the permissive
 *    one, so an unrecognised question declines instead of being answered from
 *    general knowledge.
 */
class AiIntentClassifier
{
    /**
     * Unambiguous phrases that always demand the caller's own FinancePro
     * records or the organization's live financial position.
     *
     * @var array<string, string[]>
     */
    private const SYSTEM_DATA_PHRASES = [
        'member_own_records' => [
            // English
            'my summary', 'my statement', 'my profile', 'my number',
            'my repayments', 'my repayment', 'my payments', 'my payment',
            'my due', 'my application', 'my eligibility', 'my record',
            'my records', 'my financial', 'i owe', 'i owe you', 'i have saved',
            'what do i owe', 'how much do i owe',
            'am i eligible', 'can i qualify', 'can i borrow', 'do i qualify',
            'what can i afford', 'my outstanding',
            // Swahili
            'aka yangu', 'taarifa za aka', 'aka ya mwanachama',
            'nini kina', 'kinachobaki', 'nini nilicho', 'nini nime',
            'nakulipa', 'imebaki', 'wodi yangu', 'hamisha yangu',
            'uwajiri wangu', 'aka ya kifedha',
        ],
        'bare_personal_holdings' => [
            // Unqualified first-person holdings questions. In a FinancePro-only
            // assistant "how much do I have?" can only sensibly mean the
            // caller's FinancePro position. Classifying it as system data is
            // safe in the strong sense: the tool still has to supply the value,
            // and when it cannot the grounding directive makes the assistant say
            // so rather than estimate.
            'how much do i have', 'what do i have', 'how much have i got',
            'how much is mine', 'what do i own',
            'niwate kwanini', 'nina kiasi gani', 'nina nini', 'ninataka kiasi gani',
        ],
        'organization_position' => [
            // English
            'income statement', 'balance sheet', 'trial balance',
            'profit and loss', 'p&l', 'financial statement', 'statement of income',
            'portfolio at risk', 'portfolio', 'loan book', 'par30', 'par 30',
            'par15', 'par 15', 'loan portfolio',
            // Swahili
            'suruhi ya mapato', 'suruhi ya hali', 'hafidhau ya hali',
            'faili ya majaribio', 'mikopo kamili',
        ],
    ];

    /**
     * First-person / our-organization markers. Matched with word boundaries so
     * "I" never matches inside "income", and with Swahili subject concord
     * (ni-, nina-, niwate-) and possessive stems (-angu, -ako, -etu) so the
     * rule works in both languages.
     *
     * @var string[]
     */
    private const PERSONAL_SCOPE = [
        'my', 'mine', 'me', 'i', 'we', 'us', 'our', 'ours', 'myself',
        'yangu', 'wangu', 'zangu', 'yako', 'wako', 'lake', 'letu', 'zetu',
        'yetu', 'je', 'mimi', 'sisi', 'wewe',
    ];

    /**
     * FinancePro record nouns. A personal-scope marker plus one of these is a
     * system-data question ("what is MY LOAN BALANCE", "salio LANGU ni kiasi
     * gani"), which is what lets the classifier handle word order it does not
     * enumerate verbatim.
     *
     * @var string[]
     */
    private const RECORD_NOUNS = [
        'balance', 'balances', 'loan', 'loans', 'savings', 'share', 'shares',
        'welfare', 'account', 'accounts', 'statement', 'portfolio', 'member',
        'members', 'disbursement', 'disbursements', 'collection', 'collections',
        'delinquency', 'income', 'expense', 'expenses', 'cash', 'arrears',
        'financial summary', 'position', 'figures', 'statement',
        // Swahili
        'mkopo', 'mikopo', 'akiba', 'hisa', 'salio', 'malipo', 'mapato',
        'matumizi', 'wanachama', 'mwanachama', 'deni', 'kopo', 'lipa',
        // FinancePro concepts that had no marker at all, so a first-person
        // question about them fell through to the outside-scope default.
        'mdhamini', 'dhamana', 'kikundi', 'tawi', 'shirika', 'mfuko',
        'ombi', 'ombi la mkopo', 'ada', 'biashara', 'kilimo', 'elimu',
        'maendeleo', 'binfi', 'dharura',
    ];

    /**
     * Frames that turn a finance noun into a conceptual question rather than a
     * request for this caller's own data. Checked before the personal-scope
     * rule so "explain the loan eligibility criteria" stays a policy question.
     *
     * @var string[]
     */
    private const EXPLANATION_FRAMES = [
        'explain', 'difference between', 'meaning of', 'define', 'in general',
        'generically', 'concept of', 'what does', 'maana ya', 'kwa ujumui',
        'eleza', 'nini ni', 'tofauti kati ya', 'meaning',
    ];

    /**
     * Frames that ask for INSTRUCTIONS rather than for a value.
     *
     * These are checked before the FinancePro-data rules, because a process
     * question ("how can I check my loan eligibility?") must never be answered
     * by a business tool: the tool returns a record or a verdict, and neither
     * answers "how do I do this?". Routing those to system data is what made the
     * assistant claim it could not retrieve eligibility details for a member who
     * had simply asked where the check lives.
     *
     * @var string[]
     */
    private const HOW_TO_FRAMES = [
        // English
        'how can i', 'how do i', 'how to', 'how can we', 'how do we',
        'where can i', 'where do i', 'where can we', 'where do we',
        'where should i', 'where would i', 'where in the system',
        'what steps', 'which steps', 'what is the process', 'what is the procedure',
        'what is the workflow', 'how can i know', 'how do i know',
        'how would i know', 'can i check', 'is there a way to check',
        'any way to check', 'where do i start', 'how do i begin', 'how do i proceed',
        'what do i need to do', 'what should i do', 'what do i do to',
        // Kiswahili
        'nawezaje', 'ninawezaje', 'nitajuaje', 'naangaliaje', 'ninaangaliaje',
        'nini hatua', 'hatua gani', 'mchakato gani', 'mchakato upi',
        'nini kwa ku', 'wapi naweza', 'wapi naangalia', 'vipi naweza',
        'ninaweza kuangalia wapi', 'ninaangalia wapi', 'naangalia wapi',
        'ninataka kuanza', 'nianze',
    ];

    /**
     * Subjects that make a how-to frame a question about the loan APPLICATION
     * PROCESS, as opposed to any "where do I ..." question.
     *
     * Deliberately excludes generic FinancePro nouns such as "loan", "balance"
     * or "mkopo". "Where can I see my loan balance?" is a request for that
     * member's balance and must stay system data; only an eligibility or
     * application-process subject turns the frame into a how-to question.
     *
     * @var string[]
     */
    private const PROCESS_NOUNS = [
        // English
        'eligibility', 'eligible', 'qualify', 'qualifying', 'qualification',
        'qualified', 'apply', 'applying', 'application', 'applications',
        // Kiswahili
        'ustahiki', 'nastahili', 'kustahili', 'sifa', 'kupata', 'kuomba',
        'ombe', 'kuwasilisha',
    ];

    /**
     * Questions about FinancePro's own rules, rates, policies and documented
     * processes. Answerable only from approved knowledge documents.
     *
     * @var array<string, string[]>
     */
    private const POLICY = [
        'documented_rules' => [
            'policy', 'policies', 'rule', 'rules', 'regulation', 'guideline',
            'guidelines', 'criteria', 'criterion', 'requirement', 'requirements',
            'procedure', 'process', 'handbook', 'manual', 'faq', 'faqs',
            'terms', 'charge', 'charges', 'fee', 'fees', 'interest rate',
            'rates', 'target audience', 'purpose of the sacco', 'what is a sacco',
            'what is a microfinance', 'difference between',
            // "What determines eligibility?" is a policy question even though it
            // names no policy noun, so the deciding-verb forms are listed.
            'determines', 'what determines', 'determining', 'what factors',
            'factors in', 'based on what', 'criteria for', 'conditions for',
            // Swahili
            'sera', 'sheria', 'kanuni', 'taratibu', 'mahusizo', 'wajibu',
            'mwongozo', 'makato', 'amana', 'riba', 'funguo', 'lengo la',
            'tofauti kati ya', 'nini huamua', 'vigezo', 'hali zinazotumika',
        ],
    ];

    /**
     * Questions about operating the FinancePro platform itself.
     *
     * @var array<string, string[]>
     */
    private const HOW_TO = [
        'platform_operation' => [
            'how do i use', 'how do i log in', 'how do i login', 'how to log in',
            'where do i find', 'how do i navigate', 'how do i change',
            'how do i reset', 'how do i add', 'how do i register', 'how do i set up',
            'how do i use the system', 'in the system', 'in the platform',
            'in the dashboard', 'on the dashboard', 'in the portal', 'n-account',
            'the menu', 'the sidebar', 'the settings page', 'the report page',
            'can i export', 'how do i export', 'how do i print', 'how do i upload',
            // Swahili
            'jinsi ya kutumia', 'nini kutumia', 'vipi kuingia', 'ninapoweka',
            'ninapohifadhi', 'kupakua', 'kuandika', 'katika mfumo',
            'menyu', 'dashibodi', 'tofauti ya matumizi',
            // Platform identity. These are routed to the ai.identity.view
            // capability, but they are classified as platform questions so that
            // if that capability is ever unavailable the fallback is "say what is
            // not available" rather than "outside the domain" — declining to
            // describe the assistant is never the correct answer.
            'who are you', 'who is this', 'what are you', 'what is this',
            'your name', 'what is your name', 'introduce yourself',
            'tell me about yourself', 'what can you do', 'what can you help',
            'how can you help', 'what are you able to do', 'what do you do',
            'your capabilities', 'what can you tell me', 'what data can you see',
            'wewe ni nani', 'wewe ni nini', 'ni nani', 'jinsi ya kujitambulisha',
            'jina lako', 'unapaswa kusaidia', 'unaweza kusaidia',
            'unaweza kunisaidia', 'unaweza kufanya nini',
        ],
    ];

    /**
     * Explicitly outside the assistant's domain. Checked after the FinancePro
     * tables so a question that genuinely references FinancePro records is not
     * discarded, but ahead of the general-education default so the assistant
     * declines instead of becoming a generic chatbot.
     *
     * @var array<string, string[]>
     */
    private const OUTSIDE_SCOPE = [
        'non_domain' => [
            'weather', 'forecast', 'temperature', 'rain', 'raining',
            'football', 'match result', 'match results', 'who won',
            'election', 'president', 'politics', 'party', 'celebrity',
            'song', 'lyrics', 'movie', 'film', 'joke', 'tell me a joke',
            'recipe', 'cooking', 'score', 'who scored',
            'premier league', 'champions league', 'transfer', 'goal',
            // Swahili
            'hali ya hewa', 'ubora wa hewa', 'mchezo', 'mchezi', 'timu',
            'kura', 'cheshi', 'mwinyozi', 'filamu', 'nyimbo', 'shairi',
            'mapishi', 'chefu', 'habari za dunia',
        ],
    ];

    /**
     * Genuinely general finance concepts, unrelated to any FinancePro record.
     *
     * @var array<string, string[]>
     */
    private const GENERAL_EDUCATION = [
        'general_concepts' => [
            'what is a loan', 'what is savings', 'what is interest',
            'what is a sacco', 'what is microfinance', 'what is collateral',
            'what is a guarantor', 'what is credit', 'what is debt',
            'what is an amortization', 'what is par', 'what is dpd',
            'explain how', 'explain what', 'in general', 'generally speaking',
            'what does', 'define', 'meaning of',
            // Swahili
            'nini ni kikopo', 'nini ni akiba', 'nini ni riba', 'nini ni dhamana',
            'nini ni mikopo', 'kwa ujumui', 'maana ya', 'eleza',
        ],
    ];

    public function classify(string $message): AiQuestionType
    {
        $text = mb_strtolower(trim($message));

        if ($text === '') {
            return AiQuestionType::OutsideScope;
        }

        // A request for INSTRUCTIONS is decided before any data rule. "How can I
        // check my loan eligibility?" contains a personal marker ("my") and a
        // record noun ("loan"), so without this check the personal-scope rule
        // below classified it as system data and the assistant tried to return
        // an eligibility verdict the member never asked for.
        if ($this->asksHowTo($text)) {
            return AiQuestionType::FinanceProHowTo;
        }

        // FinancePro data first: a first-person or organization-position
        // question must be answered from records even when it also contains
        // policy vocabulary ("explain my eligibility" is about MY eligibility).
        foreach (self::SYSTEM_DATA_PHRASES as $group) {
            if ($this->hasAny($text, $group)) {
                return AiQuestionType::FinanceProSystemData;
            }
        }

        // Personal scope + a record noun, so word order the tables do not
        // enumerate verbatim is still recognised ("what is my current loan
        // balance", "nina akiba kiasi gani").
        if ($this->hasExplanationFrame($text) === false
            && $this->mentionsPersonalScope($text)
            && $this->mentionsRecordNoun($text)
        ) {
            return AiQuestionType::FinanceProSystemData;
        }

        foreach (self::POLICY as $group) {
            if ($this->hasAny($text, $group)) {
                return AiQuestionType::FinanceProPolicy;
            }
        }

        // A definitional question about a FinancePro concept ("what is loan
        // eligibility?") is answerable from approved knowledge about that
        // concept, so it must not fall through to the outside-scope default.
        // It is deliberately checked AFTER the system-data rules, so "what is
        // MY eligibility" still resolves to this member's own record.
        if ($this->containsAny($text, self::DEFINITION_FRAMES)
            && $this->containsAny($text, self::PROCESS_NOUNS)
        ) {
            return AiQuestionType::FinanceProPolicy;
        }

        foreach (self::HOW_TO as $group) {
            if ($this->hasAny($text, $group)) {
                return AiQuestionType::FinanceProHowTo;
            }
        }

        foreach (self::OUTSIDE_SCOPE as $group) {
            if ($this->hasAny($text, $group)) {
                return AiQuestionType::OutsideScope;
            }
        }

        foreach (self::GENERAL_EDUCATION as $group) {
            if ($this->hasAny($text, $group)) {
                return AiQuestionType::GeneralEducational;
            }
        }

        // Conservative default: an unrecognised question is declined rather
        // than answered from general knowledge.
        return AiQuestionType::OutsideScope;
    }

    /**
     * True when the caller is asking HOW to do something in FinancePro rather
     * than asking for a value.
     *
     * Requires BOTH an instructional frame and a process subject, so that
     * "where can I see my loan balance?" stays a system-data question while
     * "where can I check my loan eligibility?" becomes a how-to question.
     */
/**
     * Frames that ask what something MEANS rather than for a value or a process.
     *
     * Kept separate from EXPLANATION_FRAMES on purpose: that list suppresses the
     * personal-scope rule, so adding "what is" to it would stop "what is my loan
     * balance?" from being recognised as a request for that member's balance.
     *
     * @var string[]
     */
    private const DEFINITION_FRAMES = [
        'what is', 'what are', 'what does', 'what do', 'meaning of',
        'define', 'explain', 'nini ni', 'nini za', 'maana ya', 'eleza',
    ];

    protected function asksHowTo(string $text): bool
    {
        return $this->containsAny($text, self::HOW_TO_FRAMES)
            && $this->containsAny($text, self::PROCESS_NOUNS);
    }

    /**
     * Substring matching, used where word boundaries are the wrong tool.
     *
     * Kiswahili writes the subject concord and the verb stem as ONE word, so
     * "nastahili" (am I eligible) also appears as "ninastahili" and "nakustahili".
     * A \b-delimited match cannot see a boundary inside "ninastahili" and would
     * silently miss the most common form of the question. The needles here are
     * distinctive enough that substring matching cannot over-match.
     *
     * @param  string[]  $needles
     */
    protected function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    protected function mentionsPersonalScope(string $text): bool
    {
        if ($this->hasAny($text, self::PERSONAL_SCOPE)) {
            return true;
        }

        // Swahili subject concord (nina, niwate, nime, ninapata) and possessive
        // stems (-angu, -ako, -etu) carry first-person scope without a space.
        return preg_match('/\bni[a-z]*/u', $text) === 1
            || preg_match('/\b[lwyz](angu|ako|ake)\b/u', $text) === 1
            || preg_match('/\b(langu|yangu|wangu|zangu|lako|letu|zetu)\b/u', $text) === 1;
    }

    protected function mentionsRecordNoun(string $text): bool
    {
        return $this->hasAny($text, self::RECORD_NOUNS);
    }

    protected function hasExplanationFrame(string $text): bool
    {
        foreach (self::EXPLANATION_FRAMES as $frame) {
            if (str_contains($text, $frame)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  string[]  $needles
     */
    protected function hasAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($needle, ' ')) {
                if (str_contains($text, $needle)) {
                    return true;
                }

                continue;
            }

            if (preg_match('/\b'.preg_quote($needle, '/').'\b/u', $text) === 1) {
                return true;
            }
        }

        return false;
    }
}
