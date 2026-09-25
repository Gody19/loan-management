<?php

namespace Tests\Unit\AI;

use App\AI\Providers\FakeEmbeddingProvider;
use App\AI\Services\AiKnowledgeChunkingService;
use PHPUnit\Framework\TestCase;

class AiKnowledgeEmbeddingTest extends TestCase
{
    public function test_fake_embedding_is_deterministic_and_unit_normed(): void
    {
        $provider = new FakeEmbeddingProvider(256);

        $first = $provider->embed('The FinancePro loan policy applies to all active members.');
        $second = $provider->embed('The FinancePro loan policy applies to all active members.');

        $this->assertSame($first, $second);
        $this->assertCount(256, $first);

        $norm = sqrt(array_sum(array_map(fn ($v) => $v * $v, $first)));
        $this->assertEqualsWithDelta(1.0, $norm, 1e-9);
    }

    public function test_fake_embedding_respects_configured_dimensions(): void
    {
        $provider = new FakeEmbeddingProvider(64);

        $this->assertCount(64, $provider->embed('anything'));
        $this->assertSame(64, $provider->dimensions());

        $empty = new FakeEmbeddingProvider(16);
        $this->assertCount(16, $empty->embed(''));
    }

    public function test_related_content_scores_higher_than_unrelated(): void
    {
        $provider = new FakeEmbeddingProvider(256);

        $loanChunk = 'promissory note liquidity cap aggregate savings leverage guarantee';
        $irrigationChunk = 'drought response irrigation scheduling north farms harvest';

        $queryLoan = $provider->embed('promissory note liquidity');
        $queryIrrigation = $provider->embed('drought irrigation harvest');

        $loanVector = $provider->embed($loanChunk);
        $irrigationVector = $provider->embed($irrigationChunk);

        $this->assertGreaterThan(
            $this->cosine($queryLoan, $irrigationVector),
            $this->cosine($queryLoan, $loanVector),
        );

        $this->assertGreaterThan(
            $this->cosine($queryIrrigation, $loanVector),
            $this->cosine($queryIrrigation, $irrigationVector),
        );
    }

    public function test_batch_matches_individual_embeddings(): void
    {
        $provider = new FakeEmbeddingProvider(128);

        $texts = ['first document text', 'second document text', 'third'];

        $batch = $provider->embedBatch($texts);

        $this->assertCount(3, $batch);

        foreach ($texts as $index => $text) {
            $this->assertSame($provider->embed($text), $batch[$index]);
        }
    }

    public function test_chunking_is_deterministic_and_preserves_order(): void
    {
        $service = new AiKnowledgeChunkingService();

        $content = implode("\n\n", array_map(
            fn (int $i) => 'Section '.$i.' '.str_repeat('lorem ipsum knowledge text ', 40),
            range(1, 6)
        ));

        $first = $service->chunk($content, 500, 50);
        $second = $service->chunk($content, 500, 50);

        $this->assertSame($first, $second);
        $this->assertNotEmpty($first);

        $combined = implode(' ', $first);

        foreach ($first as $chunk) {
            $this->assertLessThanOrEqual(650, mb_strlen($chunk));
        }

        $this->assertStringContainsString('Section 1', $combined);
        $this->assertStringContainsString('Section 6', $combined);
    }

    public function test_chunking_normalizes_line_endings_for_stable_checksum(): void
    {
        $service = new AiKnowledgeChunkingService();

        $this->assertSame(
            $service->checksum("one\ntwo"),
            $service->checksum("one\r\ntwo"),
        );
    }

    private function cosine(array $vectorA, array $vectorB): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        foreach ($vectorA as $i => $valueA) {
            $valueB = $vectorB[$i];
            $dot += $valueA * $valueB;
            $normA += $valueA * $valueA;
            $normB += $valueB * $valueB;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}