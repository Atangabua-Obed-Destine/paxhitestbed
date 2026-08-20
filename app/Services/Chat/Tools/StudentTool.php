<?php

namespace App\Services\Chat\Tools;

use App\Models\ChatConversation;
use App\Models\Student;
use App\Models\StudentEnroll;
use App\Services\Chat\ChatContext;
use App\Services\Chat\Contracts\ChatTool;

/**
 * Base for tools that read a student's own records.
 *
 * Subclasses obtain the student and their enrolment ids exclusively through
 * these helpers, which read from the ChatContext. There is deliberately no way
 * to pass a student id in, so a subclass cannot accidentally widen its scope.
 */
abstract class StudentTool implements ChatTool
{
    public function allowedFor(): array
    {
        return [ChatConversation::ACTOR_STUDENT];
    }

    public function permission(): ?string
    {
        return null;
    }

    /** Tools with no arguments still need a well-formed schema. */
    public function parameters(): array
    {
        return ['type' => 'OBJECT', 'properties' => (object) []];
    }

    /** The signed-in student, or null if the record has gone. */
    protected function student(ChatContext $context): ?Student
    {
        $subject = $context->subject();

        return $subject instanceof Student ? $subject : null;
    }

    /**
     * Enrolment ids belonging to this student — the join key for fees, results,
     * attendance and course registration. Always derived from the context.
     *
     * @return array<int>
     */
    protected function enrollmentIds(ChatContext $context): array
    {
        $student = $this->student($context);

        if (!$student) {
            return [];
        }

        return StudentEnroll::where('student_id', $student->id)->pluck('id')->all();
    }

    /** Money formatted consistently for the model to quote back. */
    protected function money($amount): string
    {
        return number_format((float) $amount, 2);
    }
}
