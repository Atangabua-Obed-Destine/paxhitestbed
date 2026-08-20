<?php

namespace App\Services\Chat\Tools\Publics;

use App\Models\ChatConversation;
use App\Models\Program;
use App\Services\Chat\ChatContext;
use App\Services\Chat\Contracts\ChatTool;
use App\Services\Chat\ToolResult;

/**
 * Publicly advertised programmes. Contains no personal data, so it is available
 * to every actor including anonymous visitors.
 */
class ListProgrammesTool implements ChatTool
{
    public function name(): string
    {
        return 'list_programmes';
    }

    public function description(): string
    {
        return 'List the programmes the university offers, optionally filtered by a search term '
            . 'or degree type (for example HND, Bachelor, Masters). Use for "what courses do you offer", '
            . '"do you have accounting", "what can I study".';
    }

    public function parameters(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'search' => [
                    'type' => 'STRING',
                    'description' => 'Optional keyword to match against the programme title.',
                ],
                'degree_type' => [
                    'type' => 'STRING',
                    'description' => 'Optional degree type name, e.g. "Higher National Diploma".',
                ],
            ],
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
        $query = Program::where('status', '1')->with(['faculty', 'degreeType']);

        if (!empty($args['search'])) {
            $query->where('title', 'like', '%' . $args['search'] . '%');
        }

        if (!empty($args['degree_type'])) {
            $query->whereHas('degreeType', function ($q) use ($args) {
                $q->where('title', 'like', '%' . $args['degree_type'] . '%');
            });
        }

        $programmes = $query->orderBy('title')->limit(40)->get();

        if ($programmes->isEmpty()) {
            return ToolResult::empty('No matching programmes are currently published.');
        }

        return ToolResult::make(
            [
                'count' => $programmes->count(),
                'programmes' => $programmes->map(fn ($p) => [
                    'title' => $p->title,
                    'faculty' => optional($p->faculty)->title,
                    'degree_type' => optional($p->degreeType)->title,
                ])->all(),
            ],
            ['url' => url('/programs'), 'label' => __('Browse programmes')]
        );
    }
}
