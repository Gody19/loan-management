<?php

namespace App\Enums;

enum SavingsTransactionType: string
{
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';
    case Adjustment = 'adjustment';
    case Reversal = 'reversal';

    public function label(): string
    {
        return match ($this) {
            self::Deposit => 'Deposit',
            self::Withdrawal => 'Withdrawal',
            self::Adjustment => 'Adjustment',
            self::Reversal => 'Reversal',
        };
    }

    public function isCredit(): bool
    {
        return in_array($this, [self::Deposit, self::Adjustment]);
    }

    public function isDebit(): bool
    {
        return in_array($this, [self::Withdrawal, self::Reversal]);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
