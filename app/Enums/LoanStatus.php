<?php

namespace App\Enums;

enum LoanStatus: string
{
    case Approved = 'approved';
    case PendingDisbursement = 'pending_disbursement';
    case Disbursed = 'disbursed';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Approved',
            self::PendingDisbursement => 'Pending Disbursement',
            self::Disbursed => 'Disbursed',
            self::Active => 'Active',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Approved => 'success',
            self::PendingDisbursement => 'warning',
            self::Disbursed => 'info',
            self::Active => 'primary',
            self::Completed => 'secondary',
            self::Cancelled => 'dark',
        };
    }

    public function canTransitionTo(LoanStatus $newStatus): bool
    {
        return match ($this) {
            self::Approved => in_array($newStatus, [self::PendingDisbursement, self::Cancelled]),
            self::PendingDisbursement => in_array($newStatus, [self::Disbursed, self::Cancelled]),
            self::Disbursed => $newStatus === self::Active,
            self::Active => $newStatus === self::Completed,
            self::Completed => false,
            self::Cancelled => false,
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled]);
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
