<?php

namespace App\AI\Services;

use App\AI\Contracts\AiEmbeddingProviderInterface;
use App\AI\Exceptions\AiUnavailableException;
use App\AI\Providers\FakeEmbeddingProvider;
use App\AI\Providers\OpenAiEmbeddingProvider;

/**
 * Resolves and validates the configured embedding provider. Controllers and
 * services never instantiate a provider directly — they always go through
 * this service, which normalizes missing credentials or unknown providers to
 * the single safe AiUnavailableException.
 */
class AiEmbeddingProviderService
{
    /**
     * @var array<string, class-string<AiEmbeddingProviderInterface>>
     */
    protected const PROVIDERS = [
        'fake' => FakeEmbeddingProvider::class,
        'openai' => OpenAiEmbeddingProvider::class,
    ];

    public function enabled(): bool
    {
        return (bool) config('ai.knowledge.enabled', true);
    }

    public function providerName(): string
    {
        return (string) config('ai.embeddings.provider', 'fake');
    }

    /**
     * Whether an embedding provider is resolvable right now.
     */
    public function isAvailable(): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        try {
            $this->resolve();

            return true;
        } catch (AiUnavailableException) {
            return false;
        }
    }

    /**
     * Resolve the configured embedding provider implementation.
     *
     * @throws AiUnavailableException when knowledge is disabled, the provider
     *                                is unknown, or credentials are missing
     */
    public function resolve(): AiEmbeddingProviderInterface
    {
        if (! $this->enabled()) {
            throw new AiUnavailableException();
        }

        $name = $this->providerName();
        $class = self::PROVIDERS[$name] ?? null;

        if (! $class) {
            throw new AiUnavailableException();
        }

        $config = (array) config('ai.embeddings', []);
        $dimensions = (int) ($config['dimensions'] ?? 256);

        if ($class === OpenAiEmbeddingProvider::class) {
            $apiKey = (string) ($config['api_key'] ?? '');

            if ($apiKey === '') {
                throw new AiUnavailableException();
            }

            return new OpenAiEmbeddingProvider(
                apiKey: $apiKey,
                baseUrl: (string) ($config['base_url'] ?? 'https://api.openai.com/v1'),
                model: (string) ($config['model'] ?? 'text-embedding-3-small'),
                dimensions: $dimensions,
                timeout: (int) ($config['timeout'] ?? 30),
            );
        }

        if ($class === FakeEmbeddingProvider::class) {
            return new FakeEmbeddingProvider($dimensions);
        }

        throw new AiUnavailableException();
    }
}