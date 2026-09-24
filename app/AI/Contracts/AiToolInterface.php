<?php

namespace App\AI\Contracts;

use App\AI\DTOs\AiContextData;
use App\Models\User;

/**
 * A single read-only business-data capability executable by the AI pipeline.
 *
 * Implementations are referenced ONLY from the explicit AiToolRegistry (a
 * server-side constant map). Model output can never name a tool class.
 * Execution is strictly read-only: tools reuse authoritative FinancePro
 * services and models and return sanitized DTO arrays. Tools never persist,
 * mutate, or reverse business data.
 */
interface AiToolInterface
{
    /**
     * Execute a read-only capability against the trusted context.
     *
     * @param  array<string, mixed>  $arguments  Already validated against the
     *                                           registered schema by AiToolPolicy.
     * @return array<string, mixed> sanitized, authoritative result
     */
    public function execute(User $user, AiContextData $context, array $arguments): array;
}