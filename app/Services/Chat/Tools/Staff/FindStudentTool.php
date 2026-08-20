<?php

namespace App\Services\Chat\Tools\Staff;

use App\Models\ChatConversation;
use App\Models\Student;
use App\Services\Chat\ChatContext;
use App\Services\Chat\Contracts\ChatTool;
use App\Services\Chat\ToolResult;

/**
 * Staff lookup of a student record.
 *
 * This is the one family of tools where the subject IS supplied as an argument,
 * because staff legitimately ask about other people. The protection is
 * different in kind: the tool is only offered to, and only runnable by, an
 * actor holding the student-view permission — checked by ToolExecutor before
 * this class is ever reached. Non-staff actors can never satisfy that check,
 * because ChatContext::can() returns false for them unconditionally.
 */
class FindStudentTool implements ChatTool
{
    public function name(): string
    {
        return 'find_student';
    }

    public function description(): string
    {
        return 'Look up a student by name or registration number and return their programme, '
            . 'current session and semester. For staff use.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'query' => [
                    'type' => 'STRING',
                    'description' => 'Part of the student name or their registration number.',
                ],
            ],
            'required' => ['query'],
        ];
    }

    public function allowedFor(): array
    {
        return [ChatConversation::ACTOR_USER];
    }

    public function permission(): ?string
    {
        return 'student-view';
    }

    public function handle(ChatContext $context, array $args): ToolResult
    {
        $term = trim((string) ($args['query'] ?? ''));

        if (mb_strlen($term) < 2) {
            return ToolResult::empty('Please give at least two characters to search for.');
        }

        $students = Student::query()
            ->where(function ($q) use ($term) {
                $q->where('first_name', 'like', '%' . $term . '%')
                    ->orWhere('last_name', 'like', '%' . $term . '%')
                    ->orWhere('registration_no', 'like', '%' . $term . '%');
            })
            ->with(['currentEnroll.program:id,title', 'currentEnroll.session:id,title', 'currentEnroll.semester:id,title'])
            ->limit(10)
            ->get();

        if ($students->isEmpty()) {
            return ToolResult::empty('No student matches that search.');
        }

        return ToolResult::make([
            'match_count' => $students->count(),
            'students' => $students->map(fn ($s) => [
                'name' => trim($s->first_name . ' ' . $s->last_name),
                'registration_no' => $s->registration_no,
                'programme' => optional(optional($s->currentEnroll)->program)->title,
                'session' => optional(optional($s->currentEnroll)->session)->title,
                'semester' => optional(optional($s->currentEnroll)->semester)->title,
            ])->all(),
        ]);
    }
}
