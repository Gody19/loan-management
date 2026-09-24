<?php

namespace App\AI\DTOs;

/**
 * Normalized request sent from FinancePro to an AI provider.
 *
 * @param  AiMessageData[]  $messages
 */
class AiRequestData
{
    public function __construct(
        public readonly array $messages,
        public readonly string $model,
        public readonly ?float $temperature = null,
        public readonly ?int $maxOutputTokens = null,
        public readonly array $metadata = [],
        public readonly ?int $conversationId = null,
    ) {}
}