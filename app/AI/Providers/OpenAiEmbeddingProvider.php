<?php

namespace App\AI\Providers;

use App\AI\Contracts\AiEmbeddingProviderInterface;
use App\AI\Exceptions\AiProviderException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * OpenAI embeddings provider, isolated behind AiEmbeddingProviderInterface.
 *
 * Credentials and authentication headers never leave this class. Responses are
 * validated against the expected dimensionality and numeric types before being
 * returned — malformed or wrong-dimension vectors are rejected instead of being
 * trusted as-is.
 */
class OpenAiEmbeddingProvider implements AiEmbeddingProviderInterface
{
    private const ENDPOINT = '/embeddings';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly int $dimensions,
        private readonly int $timeout = 30,
    ) {}

    public function name(): string
    {
        return 'openai';
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }

    public function embed(string $text): array
    {
        $vectors = $this->embedBatch([$text]);

        return $vectors[0] ?? [];
    }

    public function embedBatch(array $texts): array
    {
        if ($this->apiKey === '') {
            throw new AiProviderException('Provider configuration is incomplete.');
        }

        if ($texts === []) {
            return [];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withToken($this->apiKey)
                ->acceptJson()
                ->post(rtrim($this->baseUrl, '/').self::ENDPOINT, [
                    'model' => $this->model,
                    'input' => array_values($texts),
                ]);

            if ($response->failed()) {
                throw $this->mapFailure($response);
            }

            return $this->normalizeResponse((array) $response->json(), count($texts));
        } catch (AiProviderException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new AiProviderException('Embedding provider request failed.', 0, $exception);
        }
    }

    /**
     * Map the provider response into embeddings, rejecting malformed payloads.
     *
     * @return list<list<float>>
     */
    protected function normalizeResponse(array $data, int $expectedCount): array
    {
        $rows = $data['data'] ?? [];

        if (! is_array($rows) || count($rows) !== $expectedCount) {
            throw new AiProviderException('Embedding provider returned an invalid response.');
        }

        $vectors = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['embedding']) || ! is_array($row['embedding'])) {
                throw new AiProviderException('Embedding provider returned an invalid response.');
            }

            $embedding = array_values($row['embedding']);

            if (count($embedding) !== $this->dimensions) {
                throw new AiProviderException('Embedding provider returned an unexpected dimensionality.');
            }

            foreach ($embedding as $value) {
                if (! is_int($value) && ! is_float($value)) {
                    throw new AiProviderException('Embedding provider returned non-numeric values.');
                }
            }

            $vectors[] = array_map('floatval', $embedding);
        }

        return $vectors;
    }

    protected function mapFailure(Response $response): AiProviderException
    {
        return match ($response->status()) {
            401, 403 => new AiProviderException('Embedding provider authentication failed.'),
            408, 429 => new AiProviderException('Embedding provider rate limit or timeout exceeded.'),
            502, 503, 504 => new AiProviderException('Embedding provider is temporarily unavailable.'),
            default => new AiProviderException('Embedding provider returned an unexpected error.'),
        };
    }
}