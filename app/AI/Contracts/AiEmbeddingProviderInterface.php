<?php

namespace App\AI\Contracts;

/**
 * Isolated embedding provider. Produces deterministic or remote embedding
 * vectors for knowledge-base retrieval. Implementations must never leak
 * credentials, raw storage paths, or model internals to callers.
 */
interface AiEmbeddingProviderInterface
{
    public function name(): string;

    /**
     * The fixed vector dimensionality this provider produces.
     */
    public function dimensions(): int;

    /**
     * Embed a single text into a real-valued vector of `dimensions()` length.
     *
     * @return list<float>
     */
    public function embed(string $text): array;

    /**
     * Embed multiple texts in a single provider round-trip when possible.
     *
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embedBatch(array $texts): array;
}