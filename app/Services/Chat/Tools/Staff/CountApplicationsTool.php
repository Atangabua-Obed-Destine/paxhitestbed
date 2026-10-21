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
        // The everyday phrasings matter as much as the summary. Without them
        // the model would not connect "how many applications this session?"
        // to this tool and answered that it had none — while holding it. The
        // public tools carry the same kind of examples; these staff ones did
        // not, which is why only staff questions failed.
        return 'Count applications, in total and broken down by stage (draft, submitted, under review, '
            . 'approved, rejected), optionally for one intake or session. For staff use. Returns totals '
            . 'only, never names. Use for "how many applications this session", "how many applicants do '
            . 'we have", "application numbers", "how many have been approved", "admission statistics", '
            . '"how many people applied".';
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
