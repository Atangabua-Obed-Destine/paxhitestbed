<?php

namespace App\Services\Chat\Tools\Publics;

use App\Models\ChatConversation;
use App\Models\Session;
use App\Services\Chat\ChatContext;
use App\Services\Chat\Contracts\ChatTool;
use App\Services\Chat\ToolResult;

/**
 * Intakes currently open for applications. Public information — no personal
 * data — so available to every actor.
 */
class OpenIntakesTool implements ChatTool
{
    public function name(): string
    {
        return 'open_intakes';
    }

    public function description(): string
    {
        return 'List the academic sessions currently open for applications, so a visitor knows '
            . 'whether they can apply now. Use for "can I still apply", "when is the next intake", '
            . '"are admissions open".';
    }

    public function parameters(): array
    {
        return ['type' => 'OBJECT', 'properties' => (object) []];
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
        $open = Session::where('applications_open', 1)
            ->orderByDesc('title')
            ->limit(10)
            ->get(['id', 'title']);

        if ($open->isEmpty()) {
            return ToolResult::empty(
                'No intake is currently open for applications. '
                . 'Applications open periodically — the admissions office can confirm the next date.'
            );
        }

        return ToolResult::make(
            [
                'open_intakes' => $open->pluck('title')->all(),
            ],
            ['url' => route('application.register'), 'label' => __('Start an application')]
        );
    }
}
