<?php

namespace App\AI\Services;

/**
 * Deterministic text chunker for the knowledge base.
 *
 * Chunk boundaries respect paragraph structure where possible and fall back to
 * fixed-size windows with overlap for content that cannot be split on
 * paragraph boundaries. The algorithm is a pure function of its inputs: the
 * same content, chunk size and overlap always produce the same chunks, which
 * is what makes ingestion idempotent.
 */
class AiKnowledgeChunkingService
{
    /**
     * Split normalized source text into deterministic chunks.
     *
     * @return list<string>
     */
    public function chunk(string $content, int $chunkSize = 1200, int $overlap = 150): array
    {
        $content = $this->normalize($content);

        if ($content === '') {
            return [];
        }

        $size = max(64, $chunkSize);
        $overlap = max(0, min($overlap, (int) floor($size / 2)));

        $chunks = [];
        $paragraphs = preg_split("/\n\s*\n/u", $content) ?: [];

        if (count($paragraphs) <= 1) {
            return $this->windows($content, $size, $overlap);
        }

        $current = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim((string) $paragraph);

            if ($paragraph === '') {
                continue;
            }

            if (mb_strlen($paragraph) > $size) {
                if ($current !== '') {
                    $chunks[] = $current;
                    $current = '';
                }

                foreach ($this->windows($paragraph, $size, $overlap) as $window) {
                    $chunks[] = $window;
                }

                continue;
            }

            $candidate = $current === '' ? $paragraph : $current."\n\n".$paragraph;

            if ($current !== '' && mb_strlen($candidate) > $size) {
                $chunks[] = $current;
                $current = $paragraph;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * A deterministic approximate token count (used for context budgeting).
     */
    public function estimateTokens(string $content): int
    {
        return max(1, str_word_count($content));
    }

    public function estimatedSize(string $content): int
    {
        return strlen($content);
    }

    public function checksum(string $content): string
    {
        return hash('sha256', $this->normalize($content));
    }

    /**
     * Fixed-size windows with a configurable overlap.
     *
     * @return list<string>
     */
    protected function windows(string $text, int $size, int $overlap): array
    {
        $stride = max(1, $size - $overlap);
        $length = mb_strlen($text);
        $chunks = [];

        for ($start = 0; $start < $length; $start += $stride) {
            $chunk = mb_substr($text, $start, $size);

            if ($chunk !== '' && trim($chunk) !== '') {
                $chunks[] = trim($chunk);
            }
        }

        return $chunks;
    }

    /**
     * Stable normalization used both for chunking and checksumming so that
     * line-ending differences between environments never break idempotency.
     */
    protected function normalize(string $content): string
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);

        return trim($content);
    }
}