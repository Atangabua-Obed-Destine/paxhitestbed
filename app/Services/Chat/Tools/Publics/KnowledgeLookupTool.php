<?php

namespace App\Services\Chat\Tools\Publics;

use App\Models\ChatConversation;
use App\Models\ChatKnowledgeEntry;
use App\Services\Chat\ChatContext;
use App\Services\Chat\Contracts\ChatTool;
use App\Services\Chat\ToolResult;

/**
 * Search the admin-curated knowledge base.
 *
 * Entries are filtered by the surface they were published to, so an entry
 * marked staff-only never reaches a public visitor.
 */
class KnowledgeLookupTool implements ChatTool
{
    public function name(): string
    {
        return 'knowledge_lookup';
    }

    public function description(): string
    {
        return 'Search the university\'s published guidance — policies, deadlines, how to apply, '
            . 'office hours, contact details and other frequently asked questions. '
            . 'Use this whenever the answer is procedural rather than about a specific record.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'query' => [
                    'type' => 'STRING',
                    'description' => 'Keywords describing what the user wants to know.',
                ],
            ],
            'required' => ['query'],
        ];
    }

    public function allowedFor(): array
    {
        return [
            ChatConversation::ACTOR_GUEST,
            ChatConversation::ACTOR_APPLICANT,
            ChatConversation::ACTOR_STUDENT,
            ChatConversation::ACTOR_USER,
        ];
    }

    public function permission(): ?string
    {
        return null;
    }

    public function handle(ChatContext $context, array $args): ToolResult
    {
        $term = trim((string) ($args['query'] ?? ''));

        $entries = ChatKnowledgeEntry::forSurface($context->surface())
            ->when($term !== '', function ($q) use ($term) {
                $q->where(function ($sub) use ($term) {
                    $sub->where('question', 'like', '%' . $term . '%')
                        ->orWhere('answer', 'like', '%' . $term . '%')
                        ->orWhere('tags', 'like', '%' . $term . '%');
                });
            })
            ->orderBy('sort_order')
            ->limit(8)
            ->get(['question', 'answer']);

        if ($entries->isEmpty()) {
            return ToolResult::empty('Nothing has been published on that topic.');
        }

        return ToolResult::make([
            'entries' => $entries->map(fn ($e) => [
                'question' => $e->question,
                'answer' => $e->answer,
            ])->all(),
        ]);
    }
}
