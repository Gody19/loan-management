<?php

namespace App\Enums;

/**
 * Canonical delinquency aging buckets used by the Financial Intelligence
 * layer. Buckets are the explicit value object for aging classification —
 * the day ranges live here and nowhere else, so days-past-due is never
 * mapped into loose magic numbers scattered across services.
 */
enum DelinquencyAgingBucket: string
{
    case Current = 'current';
    case PastDue1to30 = '1-30';
    case PastDue31to60 = '31-60';
    case PastDue61to90 = '61-90';
    case PastDueOver90 = '90-plus';

    public function label(): string
    {
        return match ($this) {
            self::Current => 'Current',
            self::PastDue1to30 => '1-30 days',
            self::PastDue31to60 => '31-60 days',
            self::PastDue61to90 => '61-90 days',
            self::PastDueOver90 => '90+ days',
        };
    }

    /**
     * Inclusive lower bound of the bucket, in days past due.
     */
    public function minDays(): int
    {
        return match ($this) {
            self::Current => 0,
            self::PastDue1to30 => 1,
            self::PastDue31to60 => 31,
            self::PastDue61to90 => 61,
            self::PastDueOver90 => 91,
        };
    }

    /**
     * Inclusive upper bound of the bucket. The final bucket is open-ended
     * (null means "and above").
     */
    public function maxDays(): ?int
    {
        return match ($this) {
            self::Current => 0,
            self::PastDue1to30 => 30,
            self::PastDue31to60 => 60,
            self::PastDue61to90 => 90,
            self::PastDueOver90 => null,
        };
    }

    /**
     * Classify a days-past-due figure into its bucket.
     */
    public static function fromDays(int $daysPastDue): self
    {
        if ($daysPastDue <= 0) {
            return self::Current;
        }

        if ($daysPastDue <= 30) {
            return self::PastDue1to30;
        }

        if ($daysPastDue <= 60) {
            return self::PastDue31to60;
        }

        if ($daysPastDue <= 90) {
            return self::PastDue61to90;
        }

        return self::PastDueOver90;
    }

    /**
     * Ordered buckets from current to oldest, as used for aging displays.
     *
     * @return self[]
     */
    public static function ordered(): array
    {
        return [
            self::Current,
            self::PastDue1to30,
            self::PastDue31to60,
            self::PastDue61to90,
            self::PastDueOver90,
        ];
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
