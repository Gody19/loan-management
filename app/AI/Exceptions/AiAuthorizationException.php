<?php

namespace App\AI\Exceptions;

use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Raised when the AI authorization/guardrail boundary denies a capability.
 * The message is generic and safe; the structured reason/category used for
 * auditing lives on the exception and never surfaces raw provider or prompt
 * content. Renders as a 403 response.
 */
class AiAuthorizationException extends RuntimeException implements HttpExceptionInterface
{
    public const SAFE_MESSAGE = 'You are not authorized to perform that AI action.';

    public function __construct(
        string $message = '',
        public readonly ?string $category = null,
        public readonly string $capability = '',
        public readonly array $metadata = [],
        int $code = 403,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message !== '' ? $message : self::SAFE_MESSAGE, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return 403;
    }

    public function getHeaders(): array
    {
        return [];
    }
}