<?php

namespace App\AI\Providers;

use App\AI\Contracts\AiProviderInterface;
use App\AI\DTOs\AiMessageData;
use App\AI\DTOs\AiRequestData;
use App\AI\DTOs\AiResponseData;

/**
 * Deterministic, offline provider used by the automated test suite and as a
 * safe fallback when no credentials are configured. Never requires a network
 * connection or an API key.
 */
class FakeAiProvider implements AiProviderInterface
{
    public function __construct(
        private readonly string $defaultModel = 'fake-model-1',
    ) {}

    public function name(): string
    {
        return 'fake';
    }

    public function generate(AiRequestData $request): AiResponseData
    {
        $inputWords = collect($request->messages)
            ->sum(fn (AiMessageData $message) => max(1, str_word_count($message->content)));

        $content = 'This is a deterministic offline reply from the fake AI provider.';
        $outputTokens = max(1, str_word_count($content));

        return new AiResponseData(
            provider: $this->name(),
            model: $this->defaultModel,
            content: $content,
            inputTokens: (int) $inputWords,
            outputTokens: (int) $outputTokens,
            totalTokens: (int) $inputWords + $outputTokens,
            finishReason: 'stop',
            providerRequestId: 'fake-req-1',
            metadata: ['provider' => $this->name(), 'offline' => true],
        );
    }
}