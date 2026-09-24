<?php

namespace App\AI\DTOs;

use App\Enums\AiMessageRole;

/**
 * A single normalized message exchanged with an AI provider.
 *
 * Role values are controlled by the AiMessageRole enum: system, user, assistant.
 */
class AiMessageData
{
    public function __construct(
        public readonly AiMessageRole $role,
        public readonly string $content,
    ) {}
}