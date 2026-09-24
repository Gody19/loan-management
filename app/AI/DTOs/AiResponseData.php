<?php

namespace App\AI\DTOs;

/**
 * Normalized response returned by an AI provider. Provider-specific payloads
 * never escape the provider boundary intact; usage and metadata fields are
 * explicitly mapped and sanitized here.
 */
class AiResponseData
{
    public function __construct(
        public readonly string $provider,
        public readonly string $model,
        public readonly string $content,
        public readonly ?int $inputTokens = null,
        public readonly ?int $outputTokens = null,
        public readonly ?int $totalTokens = null,
        public readonly ?string $finishReason = null,
        public readonly ?string $providerRequestId = null,
        public readonly array $metadata = [],
    ) {}
}