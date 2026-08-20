<?php

namespace App\Services\Chat\Tools\Staff;

use App\Models\Application;
use App\Models\ChatConversation;
use App\Services\Chat\ChatContext;
use App\Services\Chat\Contracts\ChatTool;
use App\Services\Chat\ToolResult;

/**
 * Aggregate application counts for staff — no individual records, so it answers
 * "how are we doing this intake" without exposing anybody's details.
 */
class CountApplicationsTool implements ChatTool
{
    public function name(): string
    {
        return 'count_applications';
    }

    public function description(): string
    {
        return 'Count applications broken down by stage (draft, submitted, under review, approved, '
            . 'rejected), optionally for one intake. For staff use. Returns totals only, not names.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'intake' => [
                    'type' => 'STRING',
                    'description' => 'Optional academic session title, e.g. "2026-2027".',
                ],
            ],
        ];
    }

    public function allowedFor(): array
    {
        return [ChatConversation::ACTOR_USER];
    }

    public function permission(): ?string
    {
        return 'application-view';
    }

    public function handle(ChatContext $context, array $args): ToolResult
    {
        $query = Application::query();

        if (!empty($args['intake'])) {
            $query->whereHas('session', function ($q) use ($args) {
                $q->where('title', 'like', '%' . $args['intake'] . '%');
            });
        }

        $byStage = (clone $query)
            ->selectRaw('stage, COUNT(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage')
            ->all();

        $total = array_sum($byStage);

        if ($total === 0) {
            return ToolResult::empty('No applications match that description.');
        }

        return ToolResult::make(
            [
                'intake' => $args['intake'] ?? 'all intakes',
                'total' => $total,
                'by_stage' => $byStage,
            ],
            ['url' => url('admin/admission/application'), 'label' => __('Open the applications list')]
        );
    }
}
