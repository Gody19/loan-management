<?php

namespace App\AI\Providers;

use App\AI\Contracts\AiProviderInterface;
use App\AI\DTOs\AiMessageData;
use App\AI\DTOs\AiRequestData;
use App\AI\DTOs\AiResponseData;
use App\AI\Exceptions\AiProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * OpenAI Chat Completions provider, isolated behind AiProviderInterface.
 *
 * No OpenAI-specific structures are exposed to the rest of FinancePro — the
 * raw API payload is built here and the response is mapped to AiResponseData
 * here. Credentials and authentication headers never leave this class.
 */
class OpenAiProvider implements AiProviderInterface
{
    private const ENDPOINT = '/chat/completions';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly int $timeout,
        private readonly string $defaultModel,
    ) {}

    public function name(): string
    {
        return 'openai';
    }

    public function generate(AiRequestData $request): AiResponseData
    {
        if ($this->apiKey === '') {
            throw new AiProviderException('Provider configuration is incomplete.');
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withToken($this->apiKey)
                ->acceptJson()
                ->post(rtrim($this->baseUrl, '/').self::ENDPOINT, $this->payload($request));

            if ($response->failed()) {
                throw $this->mapFailure($response);
            }

            return $this->normalizeResponse($request, (array) $response->json());
        } catch (AiProviderException $exception) {
            throw $exception;
        } catch (ConnectionException $exception) {
            throw new AiProviderException('Provider request timed out.', 0, $exception);
        } catch (Throwable $exception) {
            throw new AiProviderException('Provider request failed.', 0, $exception);
        }
    }

    /**
     * Build the provider-specific request payload.
     */
    protected function payload(AiRequestData $request): array
    {
        $payload = [
            'model' => $request->model,
            'messages' => array_map(
                fn (AiMessageData $message) => [
                    'role' => $message->role->value,
                    'content' => $message->content,
                ],
                $request->messages
            ),
        ];

        if ($request->temperature !== null) {
            $payload['temperature'] = $request->temperature;
        }

        if ($request->maxOutputTokens !== null) {
            $payload['max_tokens'] = $request->maxOutputTokens;
        }

        return $payload;
    }

    /**
     * Map a provider response to the normalized AiResponseData. Malformed or
     * unexpected payloads are rejected instead of being trusted as-is.
     */
    protected function normalizeResponse(AiRequestData $request, array $data): AiResponseData
    {
        $choice = $data['choices'][0] ?? null;

        if (! is_array($choice) || ! isset($choice['message']['content']) || ! is_string($choice['message']['content'])) {
            throw new AiProviderException('Provider returned an invalid response.');
        }

        $usage = is_array($data['usage'] ?? null) ? $data['usage'] : [];
        $finishReason = isset($choice['finish_reason']) && is_string($choice['finish_reason'])
            ? $choice['finish_reason']
            : null;

        return new AiResponseData(
            provider: $this->name(),
            model: isset($data['model']) && is_string($data['model']) ? $data['model'] : $request->model,
            content: $choice['message']['content'],
            inputTokens: $this->positiveInt($usage['prompt_tokens'] ?? null),
            outputTokens: $this->positiveInt($usage['completion_tokens'] ?? null),
            totalTokens: $this->positiveInt($usage['total_tokens'] ?? null),
            finishReason: $finishReason,
            providerRequestId: isset($data['id']) ? (string) $data['id'] : null,
            metadata: [
                'provider' => $this->name(),
                'model' => $data['model'] ?? $request->model,
                'finish_reason' => $finishReason,
            ],
        );
    }

    /**
     * Translate an HTTP failure into a sanitized, category-safe AiProviderException.
     */
    protected function mapFailure(Response $response): AiProviderException
    {
        return match ($response->status()) {
            401, 403 => new AiProviderException('Provider authentication failed.'),
            408, 429 => new AiProviderException('Provider rate limit or timeout exceeded.'),
            502, 503, 504 => new AiProviderException('Provider is temporarily unavailable.'),
            default => new AiProviderException('Provider returned an unexpected error.'),
        };
    }

    private function positiveInt(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}