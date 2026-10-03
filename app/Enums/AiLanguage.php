<?php

namespace App\Enums;

/**
 * Response language of the FinancePro Assistance AI.
 *
 * The assistant is English-first with Swahili support. Detection is a
 * deterministic, server-side marker scan (never a model guess and never a
 * browser-supplied value), so the same question always resolves to the same
 * language for every tenant. Adding a language is a matter of adding a case
 * here plus its marker list in AiLanguageService — no channel, tool or
 * conversation code has to change, which is what keeps the assistant
 * channel-independent (web chat, public assistant and voice alike).
 */
enum AiLanguage: string
{
    case English = 'en';

    case Swahili = 'sw';

    public function label(): string
    {
        return match ($this) {
            self::English => 'English',
            self::Swahili => 'Kiswahili',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function default(): self
    {
        return self::English;
    }

    /**
     * Resolve from an explicit, validated request value. Anything unknown or
     * missing falls back to the platform default rather than erroring, so a
     * bad client value can never widen or narrow assistant behaviour.
     */
    public static function fromRequest(?string $value): self
    {
        if ($value === null || trim($value) === '') {
            return self::default();
        }

        return self::tryFrom(strtolower(trim($value))) ?? self::default();
    }
}
