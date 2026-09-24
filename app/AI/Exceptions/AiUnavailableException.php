<?php

namespace App\AI\Exceptions;

use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Raised whenever the AI capability cannot be used: feature disabled, provider
 * not configured, provider unreachable, or any provider failure. This is the
 * single safe exception propagated to the rest of FinancePro. It renders as a
 * 503 response and never exposes internal provider details.
 */
class AiUnavailableException extends RuntimeException implements HttpExceptionInterface
{
    public const SAFE_MESSAGE = 'AI service is currently unavailable. Please use the existing FinancePro features.';

    public function __construct(string $message = '', int $code = 503, ?\Throwable $previous = null)
    {
        parent::__construct($message !== '' ? $message : self::SAFE_MESSAGE, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return 503;
    }

    public function getHeaders(): array
    {
        return [];
    }
}