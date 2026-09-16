<?php

namespace App\Enums;

enum LoanScheduleInstallmentStatus: string
{
    case Pending = 'pending';
    case Partial = 'partial';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Waived = 'waived';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Partial => 'Partially Paid',
            self::Paid => 'Paid',
            self::Overdue => 'Overdue',
            self::Waived => 'Waived',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'secondary',
            self::Partial => 'info',
            self::Paid => 'success',
            self::Overdue => 'danger',
            self::Waived => 'warning',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
