<?php

namespace App\AI\Tools;

use App\AI\Contracts\AiToolInterface;
use App\AI\DTOs\AiContextData;
use App\AI\Exceptions\AiToolException;
use App\Models\User;

/**
 * Sentry handler for capabilities that must never be executed as business
 * tools. The conversation/chat surface is handled by AiConversationService,
 * never by the tool runner. If this handler is ever invoked it raises an
 * internal error; it never returns data and cannot perform work.
 */
class NullTool implements AiToolInterface
{
    public function execute(User $user, AiContextData $context, array $arguments): array
    {
        throw new AiToolException('internal_error');
    }
}