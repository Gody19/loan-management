<?php

namespace App\AI\Services;

use App\AI\Contracts\AiProviderInterface;
use App\AI\DTOs\AiRequestData;
use App\AI\DTOs\AiResponseData;
use App\AI\Exceptions\AiProviderException;
use App\AI\Exceptions\AiUnavailableException;
use App\AI\Providers\FakeAiProvider;
use App\AI\Providers\OpenAiProvider;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Resolves, validates, and orchestrates AI providers. Controllers never touch
 * a provider directly — they go through AiConversationService → AiProviderService
 * → AiProviderInterface. Every provider failure is normalized to the single,
 * safe AiUnavailableException.
 */
class AiProviderService
{
    /**
     * @var array<string, class-string<AiProviderInterface>>
     */
    protected const PROVIDERS = [
        'fake' => FakeAiProvider::class,
        'openai' => OpenAiProvider::class,
    ];

    public function isEnabled(): bool
    {
        return (bool) config('ai.enabled');
    }

    public function defaultProviderName(): string
    {
        return (string) config('ai.default_provider', 'openai');
    }

    public function defaultModel(?string $providerName = null): string
    {
        $name = $providerName ?? $this->defaultProviderName();

        return (string) (config('ai.providers.'.$name.'.model') ?? config('ai.default_model', 'gpt-4o-mini'));
    }

    /**
     * Whether the AI capability could be used right now (feature enabled and a
     * provider resolvable). Does not perform any network request.
     */
    public function isAvailable(?string $providerName = null): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        try {
            $this->resolveProvider($providerName);

            return true;
        } catch (AiUnavailableException) {
            return false;
        }
    }

    /**
     * Resolve the configured provider implementation.
     *
     * @throws AiUnavailableException when disabled, unknown, or missing credentials
     */
    public function resolveProvider(?string $providerName = null): AiProviderInterface
    {
        if (! $this->isEnabled()) {
            throw new AiUnavailableException();
        }

        $name = $providerName ?? $this->defaultProviderName();
        $class = self::PROVIDERS[$name] ?? null;

        if (! $class) {
            throw new AiUnavailableException();
        }

        $providerConfig = (array) config('ai.providers.'.$name, []);

        if ($class === OpenAiProvider::class) {
            $apiKey = (string) ($providerConfig['api_key'] ?? '');

            if ($apiKey === '') {
                throw new AiUnavailableException();
            }

            return new OpenAiProvider(
                apiKey: $apiKey,
                baseUrl: (string) ($providerConfig['base_url'] ?? 'https://api.openai.com/v1'),
                timeout: (int) config('ai.timeout', 30),
                defaultModel: $this->defaultModel($name),
            );
        }

        return new FakeAiProvider($this->defaultModel($name));
    }

    /**
     * Generate a completion through the configured provider.
     *
     * @throws AiUnavailableException on any provider failure; the message is
     *                                always the safe user-facing copy
     */
    public function generate(AiRequestData $request, ?string $providerName = null): AiResponseData
    {
        $provider = $this->resolveProvider($providerName);

        try {
            return $provider->generate($request);
        } catch (AiProviderException $exception) {
            Log::warning('AI provider failure.', [
                'provider' => $provider->name(),
                'reason' => $exception->getMessage(),
            ]);

            throw new AiUnavailableException();
        } catch (AiUnavailableException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('AI provider unexpected failure.', [
                'provider' => $provider->name(),
                'exception' => $exception::class,
            ]);

            throw new AiUnavailableException();
        }
    }
}