<?php

namespace App\Enums;

/**
 * A deliberately bounded result scale. No numeric ML score exists in this
 * phase: a criterion either passed or it did not.
 */
enum AiEvaluationResult: string
{
    case Pass = 'pass';
    case Fail = 'fail';

    public function label(): string
    {
        return match ($this) {
            self::Pass => 'Pass',
            self::Fail => 'Fail',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pass => 'success',
            self::Fail => 'danger',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
