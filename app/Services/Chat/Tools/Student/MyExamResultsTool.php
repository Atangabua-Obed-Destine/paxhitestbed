<?php

namespace App\Services\Chat\Tools\Student;

use App\Models\SubjectMarking;
use App\Services\Chat\ChatContext;
use App\Services\Chat\Tools\StudentTool;
use App\Services\Chat\ToolResult;

/**
 * Published exam results for the signed-in student.
 *
 * Visibility is decided by SubjectMarking::$is_visible_to_student — the same
 * accessor the portal uses — rather than by re-implementing the workflow-state
 * and publish-date rules here. That matters: a subtly different copy of that
 * logic could expose marks the examinations office has not released.
 */
class MyExamResultsTool extends StudentTool
{
    public function name(): string
    {
        return 'my_exam_results';
    }

    public function description(): string
    {
        return 'Get the signed-in student\'s published exam results: subject, total mark and '
            . 'the session/semester it belongs to. Only results the examinations office has '
            . 'released are available. Use for "what are my results", "did I pass", "my marks".';
    }

    public function handle(ChatContext $context, array $args): ToolResult
    {
        $enrollmentIds = $this->enrollmentIds($context);

        if (empty($enrollmentIds)) {
            return ToolResult::empty('This student has no enrolment records, so there are no results.');
        }

        $markings = SubjectMarking::whereIn('student_enroll_id', $enrollmentIds)
            ->with([
                'subject:id,title,code',
                'studentEnroll.session:id,title',
                'studentEnroll.semester:id,title',
            ])
            ->orderByDesc('id')
            ->limit(120)
            ->get();

        // Publication gate, applied through the model's own accessor.
        $visible = $markings->filter(fn ($m) => (bool) $m->is_visible_to_student);

        if ($visible->isEmpty()) {
            $note = $markings->isEmpty()
                ? 'No exam records exist for this student yet.'
                : 'This student has exam records, but none have been published yet. '
                  . 'Results become visible once the examinations office releases them.';

            return ToolResult::empty($note);
        }

        $rows = $visible->map(fn ($m) => [
            'subject' => optional($m->subject)->title,
            'code' => optional($m->subject)->code,
            'total_marks' => $m->total_marks,
            'session' => optional(optional($m->studentEnroll)->session)->title,
            'semester' => optional(optional($m->studentEnroll)->semester)->title,
        ])->values()->all();

        return ToolResult::make(
            [
                'published_count' => count($rows),
                'withheld_count' => $markings->count() - $visible->count(),
                'results' => $rows,
            ],
            ['url' => route('student.exam-results.index'), 'label' => __('View your full results')],
            'Only published results are listed. Any withheld subjects have not been released yet.'
        );
    }
}
