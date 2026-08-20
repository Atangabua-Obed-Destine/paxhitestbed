<?php

namespace App\Services\Chat\Tools\Student;

use App\Services\Chat\ChatContext;
use App\Services\Chat\Tools\StudentTool;
use App\Services\Chat\ToolResult;

/**
 * Identity and current enrolment for the signed-in student.
 *
 * Answers "what is my matricule", "which programme am I in", "which semester am
 * I in" without exposing anything the student cannot already see on their
 * dashboard.
 */
class MyProfileTool extends StudentTool
{
    public function name(): string
    {
        return 'my_profile';
    }

    public function description(): string
    {
        return 'Get the signed-in student\'s own profile and current enrolment: name, '
            . 'matricule/registration number, programme, department, current session and semester. '
            . 'Use for "who am I", "what is my matricule", "what programme am I in".';
    }

    public function handle(ChatContext $context, array $args): ToolResult
    {
        $student = $this->student($context);

        if (!$student) {
            return ToolResult::empty('The student record could not be found.');
        }

        $enroll = $student->currentEnroll ?? $student->lastEnroll;

        return ToolResult::make(
            [
                'name' => trim($student->first_name . ' ' . $student->last_name),
                'registration_no' => $student->registration_no ?? null,
                'email' => $student->email ?? null,
                'programme' => optional(optional($enroll)->program)->title,
                'session' => optional(optional($enroll)->session)->title,
                'semester' => optional(optional($enroll)->semester)->title,
                'section' => optional(optional($enroll)->section)->title,
                'matricule' => optional($enroll)->matricule ?? null,
            ],
            ['url' => route('student.profile.index'), 'label' => __('View your profile')]
        );
    }
}
