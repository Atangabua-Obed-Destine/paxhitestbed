<?php

namespace App\Services\Chat\Tools\Applicant;

use App\Models\Applicant;
use App\Models\ChatConversation;
use App\Services\Chat\ChatContext;
use App\Services\Chat\Contracts\ChatTool;
use App\Services\Chat\ToolResult;

/**
 * The signed-in applicant's own applications: stage, progress, outstanding
 * documents and admission-fee position.
 *
 * Scoping mirrors Web\ApplicationController::authorizeApplication() — records
 * are reached only through the applicant relation on the context subject.
 */
class MyApplicationsTool implements ChatTool
{
    public function name(): string
    {
        return 'my_applications';
    }

    public function description(): string
    {
        return 'Get the signed-in applicant\'s own applications: application number, programme, '
            . 'intake, current stage, completion progress, admission-fee status and any documents '
            . 'still required. Use for "what is my application status", "what do I still need to upload", '
            . '"have you received my payment".';
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

        $applications = $applicant->applications()
            ->with(['program', 'degreeType', 'session', 'admissionFee', 'documents'])
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        if ($applications->isEmpty()) {
            return ToolResult::empty('This applicant has not started an application yet.');
        }

        $rows = $applications->map(function ($application) {
            $fee = $application->admissionFee;
            $outstanding = $fee
                ? max(0, ($fee->fee_amount + $fee->fine_amount - $fee->discount_amount) - $fee->paid_amount)
                : null;

            $missing = $application->documents
                ->filter(fn ($doc) => empty($doc->file_path) || $doc->needs_resubmission)
                ->map(fn ($doc) => $doc->document_type)
                ->values()
                ->all();

            return [
                'application_number' => $application->registration_no,
                'programme' => optional($application->program)->title,
                'degree_type' => optional($application->degreeType)->title,
                'intake' => optional($application->session)->title,
                'stage' => $application->stage,
                'progress_percent' => $application->stage === 'draft'
                    ? ($application->draft_progress ?? 0)
                    : ($application->progress ?? 0),
                'admission_fee_outstanding' => $outstanding !== null ? number_format($outstanding, 2) : null,
                'admission_fee_settled' => $fee ? ((int) $fee->status === 1) : null,
                'documents_outstanding' => $missing,
            ];
        })->all();

        return ToolResult::make(
            ['applications' => $rows],
            ['url' => route('application.dashboard'), 'label' => __('Open your application portal')]
        );
    }
}
