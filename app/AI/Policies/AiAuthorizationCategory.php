<?php

namespace App\AI\Policies;

/**
 * Categorization for AI authorization denials. Values are safe, structured,
 * and auditable; they never include prompt content, secrets, or raw provider
 * data.
 */
enum AiAuthorizationCategory: string
{
    case MalformedCapability = 'malformed_capability';
    case UnknownCapability = 'unknown_capability';
    case EscalationAttempt = 'scope_escalation_attempt';
    case ArgumentNotAllowed = 'argument_not_allowed';
    case MalformedArgument = 'malformed_argument';
    case MissingPermission = 'missing_permission';
    case Unauthenticated = 'unauthenticated';
    case ConversationNotOwned = 'conversation_not_owned';
    case ConversationScopeViolation = 'conversation_scope_violation';
}