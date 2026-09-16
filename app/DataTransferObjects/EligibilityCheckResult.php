<?php

namespace App\DataTransferObjects;

class EligibilityCheckResult
{
    /**
     * @param  string  $memberName
     * @param  string  $planName
     * @param  float  $requestedAmount
     * @param  float  $approvedAmount
     * @param  bool  $eligible
     * @param  array<string, string>  $checks  ['check_name' => 'pass'|'fail']
     * @param  array<string>  $failureReasons
     * @param  float  $totalSavings
     * @param  float  $totalShares
     * @param  int  $activeLoanCount
     * @param  float  $maxAllowedBySavings
     * @param  float  $maxAllowedByShares
     */
    public function __construct(
        public readonly string $memberName,
        public readonly string $planName,
        public readonly float $requestedAmount,
        public readonly float $approvedAmount,
        public readonly bool $eligible,
        public readonly array $checks,
        public readonly array $failureReasons,
        public readonly float $totalSavings,
        public readonly float $totalShares,
        public readonly int $activeLoanCount,
        public readonly float $maxAllowedBySavings,
        public readonly float $maxAllowedByShares,
    ) {}

    public function passCount(): int
    {
        return count(array_filter($this->checks, fn ($c) => $c === 'pass'));
    }

    public function failCount(): int
    {
        return count(array_filter($this->checks, fn ($c) => $c === 'fail'));
    }

    public function totalChecks(): int
    {
        return count($this->checks);
    }
}
