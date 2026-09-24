<?php

namespace App\AI\Contracts;

use App\AI\DTOs\AiRequestData;
use App\AI\DTOs\AiResponseData;

/**
 * Contract every AI provider must implement. FinancePro only ever talks to
 * providers through this interface; provider-specific structures are mapped to
 * AiResponseData inside the implementing provider and never leak outward.
 */
interface AiProviderInterface
{
    /**
     * Stable machine identifier for the provider (e.g. 'openai').
     */
    public function name(): string;

    /**
     * Generate a completion for the normalized request and return a normalized
     * response. Implementations throw AiProviderException on any failure.
     */
    public function generate(AiRequestData $request): AiResponseData;
}