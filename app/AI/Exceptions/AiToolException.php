<?php

namespace App\AI\Exceptions;

use RuntimeException;

/**
 * Controlled failure raised by an AI business-data tool.
 *
 * Categories are safe, structured, and auditable. Messages are always the
 * generic user-facing copy; SQL, stack traces, file paths, and credentials
 * never surface. 'not_found' and 'unauthorized' intentionally collapse to the
 * same message so a response never discloses whether a record exists.
 */
class AiToolException extends RuntimeException
{
    /** @var array<string, string> */
    protected const SAFE_MESSAGES = [
        'unauthorized' => 'That information is outside your authorized scope.',
        'not_found' => 'That record either does not exist or is outside your authorized scope.',
        'validation_failed' => 'The request arguments are invalid for this capability.',
        'business_rule' => 'That request cannot be completed.',
        'service_unavailable' => 'That information is temporarily unavailable.',
        'internal_error' => 'The request could not be completed.',
    ];

    public function __construct(
        public readonly string $category = 'internal_error',
        string $message = '',
        public readonly array $metadata = [],
    ) {
        parent::__construct(
            $message !== '' ? $message : (self::SAFE_MESSAGES[$category] ?? self::SAFE_MESSAGES['internal_error'])
        );
    }

    public function statusCode(): int
    {
        return match ($this->category) {
            'unauthorized', 'not_found' => 403,
            'validation_failed', 'business_rule' => 422,
            'service_unavailable' => 503,
            default => 500,
        };
    }
}