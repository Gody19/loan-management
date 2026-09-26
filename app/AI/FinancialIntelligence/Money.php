<?php

namespace App\AI\FinancialIntelligence;

use InvalidArgumentException;

/**
 * Small, explicit money/currency abstraction for the Financial Intelligence
 * layer. Every reported figure carries its currency and the presentation is
 * produced from the application's configured currency settings. The object is
 * immutable and purely presentational: it never performs currency conversion
 * and never declares an exchange rate.
 */
final class Money
{
    public function __construct(
        private readonly float $amount,
        private readonly string $currency = 'TZS',
    ) {
        if ($this->currency === '') {
            throw new InvalidArgumentException('Money currency must not be empty.');
        }
    }

    /**
     * Build from the application default currency configuration.
     */
    public static function from(float|int|string|null $amount): self
    {
        return new self((float) ($amount ?? 0), (string) config('currency.code', 'TZS'));
    }

    public function amount(): float
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    /**
     * Presentation string using the configured decimal/grouping separators.
     */
    public function formatted(): string
    {
        return number_format(
            $this->amount,
            (int) config('currency.decimals', 2),
            (string) config('currency.decimal_separator', '.'),
            (string) config('currency.thousands_separator', ','),
        );
    }

    /**
     * @return array{amount: float, currency: string, formatted: string}
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'formatted' => $this->formatted(),
        ];
    }
}
