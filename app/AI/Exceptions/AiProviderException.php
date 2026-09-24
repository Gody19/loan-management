<?php

namespace App\AI\Exceptions;

use RuntimeException;

/**
 * Raised by an AI provider when a provider interaction fails.
 *
 * Messages are authored by FinancePro and sanitized: no raw provider bodies,
 * credentials, or stack traces are ever attached to this exception.
 */
class AiProviderException extends RuntimeException
{
    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}