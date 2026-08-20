<?php

namespace App\Services\Chat\Tools\Applicant;

use App\Models\Applicant;
use App\Models\ApplicationStatusUpdate;
use App\Models\ChatConversation;
use App\Services\Chat\ChatContext;
use App\Services\Chat\Contracts\ChatTool;
use App\Services\Chat\ToolResult;

/**
 * Progress updates on the signed-in applicant's own applications.
 *
 * Two filters matter here: the application must belong to this applicant, AND
 * the update must be flagged is_visible_to_applicant — internal admissions
 * notes are not the applicant's to read.
 */
class MyApplicationTimelineTool implements ChatTool
{
    public function name(): string
    {
        return 'my_application_timeline';
    }

    public function description(): string
    {
        return 'Get the progress updates published on the signed-in applicant\'s own applications: '
            . 'what stage it reached, when, and any message from the admissions office. '
            . 'Use for "what is happening with my application", "any update", "have I been accepted".';
    }

    public function parameters(): array
    {
        return ['type' => 'OBJECT', 'properties' => (object) []];
    }

    public function allowedFor(): array
    {
        return [ChatConversation::ACTOR_APPLICANT];
    }

    public function permission(): ?string
    {
        return null;
    }

    public function handle(ChatContext $context, array $args): ToolResult
    {
        $applicant = $context->subject();

        if (!$applicant instanceof Applicant) {
            return ToolResult::empty('The applicant record could not be found.');
        }

        $applicationIds = $applicant->applications()->pluck('id');

        if ($applicationIds->isEmpty()) {
            return ToolResult::empty('This applicant has not started an application yet.');
        }

        $updates = ApplicationStatusUpdate::whereIn('application_id', $applicationIds)
            ->where('is_visible_to_applicant', 1)
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        if ($updates->isEmpty()) {
            return ToolResult::empty('There are no published updates on this application yet.');
        }

        return ToolResult::make(
            [
                'updates' => $updates->map(fn ($u) => [
                    'stage' => $u->stage,
                    'title' => $u->title,
                    'note' => $u->note,
                    'date' => optional($u->created_at)->format('Y-m-d'),
                ])->all(),
            ],
            ['url' => route('application.dashboard'), 'label' => __('Open your application portal')]
        );
    }
}
