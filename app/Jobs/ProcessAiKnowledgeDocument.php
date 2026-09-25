<?php

namespace App\Jobs;

use App\AI\Services\AiKnowledgeIngestionService;
use App\Models\AiKnowledgeDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queues ingestion of a knowledge document so the chat request never waits on
 * chunking or embedding work. Retry-safe: reprocessing is idempotent via the
 * document checksum.
 */
class ProcessAiKnowledgeDocument implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $documentId,
    ) {}

    public function handle(AiKnowledgeIngestionService $ingestion): void
    {
        $document = AiKnowledgeDocument::find($this->documentId);

        if (! $document) {
            return;
        }

        if ($document->status->value === 'archived') {
            return;
        }

        $ingestion->ingest($document);
    }
}