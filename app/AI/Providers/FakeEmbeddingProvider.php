<?php

namespace App\AI\Providers;

use App\AI\Contracts\AiEmbeddingProviderInterface;

/**
 * Deterministic, offline embedding provider used by the automated test suite
 * and as the safe default when no embedding credentials are configured.
 *
 * Vectors are computed from token and character n-gram feature hashing with a
 * stable hash family, so the same text always produces the same vector across
 * runs, databases, and restarts without any network access. Zero vectors never
 * occur for non-empty text, which keeps cosine similarity numerically safe.
 */
class FakeEmbeddingProvider implements AiEmbeddingProviderInterface
{
    public function __construct(
        private readonly int $dimensions = 256,
    ) {}

    public function name(): string
    {
        return 'fake';
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }

    public function embed(string $text): array
    {
        if ($text === '') {
            return array_fill(0, max(1, $this->dimensions), 0.0);
        }

        $vector = array_fill(0, $this->dimensions, 0.0);

        $normalized = mb_strtolower($text);

        // Word features: tokens and consecutive token pairs.
        $tokens = preg_split('/[\s,.;:!?()\[\]"\'\/\\|«»<>«»]+/u', $normalized);
        $tokens = array_values(array_filter(array_map('trim', (array) $tokens), fn ($token) => $token !== ''));

        $features = [];

        foreach ($tokens as $token) {
            $features[] = 'w:'.$token;
        }

        for ($i = 1, $count = count($tokens); $i < $count; $i++) {
            $features[] = 'b:'.$tokens[$i - 1].' '.$tokens[$i];
        }

        // Character trigrams add substring robustness without matching whole
        // sentences, so rephrased questions still resemble the source chunk.
        $clean = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
        $length = mb_strlen($clean);

        for ($i = 0; $i < max(0, $length - 2); $i++) {
            $features[] = 't:'.mb_substr($clean, $i, 3);
        }

        foreach ($features as $feature) {
            $bucket = $this->hashToBucket($feature);
            $vector[$bucket] += 1.0;
        }

        return $this->normalize($vector);
    }

    public function embedBatch(array $texts): array
    {
        return array_map(
            fn (string $text) => $this->embed($text),
            array_values($texts)
        );
    }

    /**
     * Stable 2^32-feature hash → bucket. Uses a fixed Feistel-style bijection
     * so flipping one character changes the bucket deterministically.
     */
    protected function hashToBucket(string $feature): int
    {
        $hash = crc32($feature);
        $mixed = ($hash ^ ($hash >> 13)) * 1103515245;
        $mixed = ($mixed & 0xFFFFFFFF);

        return (int) (($mixed % $this->dimensions) + $this->dimensions) % $this->dimensions;
    }

    /**
     * Unit-normalize the vector. A zero norm (only possible for the empty
     * text, handled above) returns the zero vector unchanged.
     *
     * @param  list<float>  $vector
     * @return list<float>
     */
    protected function normalize(array $vector): array
    {
        $norm = sqrt(array_sum(array_map(fn ($value) => $value * $value, $vector)));

        if ($norm <= 0.0) {
            return $vector;
        }

        return array_map(fn ($value) => (float) $value / $norm, $vector);
    }
}