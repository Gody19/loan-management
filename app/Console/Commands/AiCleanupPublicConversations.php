<?php

namespace App\Console\Commands;

use App\Enums\AiConversationType;
use App\Models\AiConversation;
use Illuminate\Console\Command;

/**
 * Deletes public (anonymous) conversations and their messages older than the
 * configured retention window. Private conversations are never touched — the
 * command is scoped to type=public so it can never remove a signed-in user's
 * chat, and deletion also satisfies member-data retention expectations for
 * anonymous visitors.
 */
class AiCleanupPublicConversations extends Command
{
    protected $signature = 'ai:cleanup-public-conversations';

    protected $description = 'Delete public landing-page AI conversations older than the retention window';

    public function handle(): int
    {
        $retentionDays = max(0, (int) config('ai.public_chat.retention_days', 30));

        $query = AiConversation::query()
            ->where('type', AiConversationType::Public->value);

        if ($retentionDays > 0) {
            $query->where('updated_at', '<', now()->subDays($retentionDays));
        }

        $count = $query->count();

        if ($count === 0) {
            $this->info('No public conversations to clean up.');

            return self::SUCCESS;
        }

        // Delete in chunks so very large tables do not hold one long transaction.
        $deleted = 0;

        $query->chunkById(200, function ($conversations) use (&$deleted) {
            foreach ($conversations as $conversation) {
                $conversation->delete();
                $deleted++;
            }
        });

        $this->info("Deleted {$deleted} public conversation(s) older than {$retentionDays} day(s).");

        return self::SUCCESS;
    }
}
