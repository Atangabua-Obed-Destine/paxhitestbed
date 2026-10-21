<?php

namespace App\Console\Commands;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Delete chat history older than the configured retention period.
 *
 * Chat Settings has carried a "history retention (days)" field since the
 * module shipped, validated between 1 and 3650 and defaulting to 90 — and
 * nothing ever read it. Threads accumulated for ever, including students
 * asking about their own fees, results and attendance, which is exactly the
 * material a retention period exists to limit. The setting promised a
 * deletion that never happened.
 *
 * Deletes messages first, then the conversations left with none, so a part-way
 * failure cannot leave a conversation whose messages are gone but which still
 * appears on the audit screen as though it held them.
 */
class ChatHistoryPrune extends Command
{
    protected $signature = 'chat:prune-history
                            {--days= : Override the configured retention period}
                            {--dry-run : Report what would be deleted and delete nothing}';

    protected $description = 'Delete chat conversations and messages older than the retention period';

    public function handle(): int
    {
        $settings = ChatSetting::current();
        $days = (int) ($this->option('days') ?: $settings->history_retention_days);

        if ($days < 1) {
            $this->error('Retention must be at least one day; nothing was deleted.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $dryRun = (bool) $this->option('dry-run');

        $this->line(sprintf('Retention %d days — deleting anything last used before %s.',
            $days, $cutoff->format('d M Y H:i')));

        // A thread is stale by its last message, not by when it was opened: a
        // conversation started a year ago and used yesterday is live.
        $stale = ChatConversation::query()
            ->where(function ($query) use ($cutoff) {
                $query->where('last_message_at', '<', $cutoff)
                    ->orWhere(function ($inner) use ($cutoff) {
                        $inner->whereNull('last_message_at')->where('created_at', '<', $cutoff);
                    });
            });

        $conversationCount = $stale->count();

        if ($conversationCount === 0) {
            $this->info('Nothing to delete.');

            return self::SUCCESS;
        }

        $ids = $stale->pluck('id');
        $messageCount = ChatMessage::whereIn('conversation_id', $ids)->count();

        if ($dryRun) {
            $this->warn(sprintf('Dry run: %d conversation(s) and %d message(s) would be deleted.',
                $conversationCount, $messageCount));

            return self::SUCCESS;
        }

        DB::transaction(function () use ($ids) {
            // Chunked, because a long-running installation can have far more
            // rows than a single IN clause should carry.
            foreach ($ids->chunk(500) as $chunk) {
                ChatMessage::whereIn('conversation_id', $chunk)->delete();
                ChatConversation::whereIn('id', $chunk)->delete();
            }
        });

        $this->info(sprintf('Deleted %d conversation(s) and %d message(s).',
            $conversationCount, $messageCount));

        return self::SUCCESS;
    }
}
