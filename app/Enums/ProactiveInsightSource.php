<?php

namespace App\Enums;

/**
 * The financial object category a proactive insight is anchored to. Used with
 * source_id to trace an insight back to its domain record (or organization
 * when no specific record exists).
 */
enum ProactiveInsightSource: string
{
    case Loan = 'loan';

    case Member = 'member';

    case Savings = 'savings';

    case Organization = 'organization';

    case Accounting = 'accounting';

    case Prediction = 'prediction';

    case System = 'system';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
