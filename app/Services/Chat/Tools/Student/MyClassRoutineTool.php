<?php

namespace App\Services\Chat\Tools\Student;

use App\Models\ClassRoutine;
use App\Models\StudentEnroll;
use App\Services\Chat\ChatContext;
use App\Services\Chat\Tools\StudentTool;
use App\Services\Chat\ToolResult;

/**
 * The signed-in student's timetable.
 *
 * Mirrors Student\ClassRoutineController: the routine is selected by the
 * session / programme / semester / section of THIS student's own enrolment, so
 * it cannot return another cohort's timetable.
 */
class MyClassRoutineTool extends StudentTool
{
    public function name(): string
    {
        return 'my_class_routine';
    }

    public function description(): string
    {
        return 'Get the signed-in student\'s class timetable — subject, day, start and end time, '
            . 'room and lecturer. Optionally narrowed to one day. Use for "when is my next class", '
            . '"what is my timetable", "do I have lectures on Monday".';
    }

    public function parameters(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'day' => [
                    'type' => 'STRING',
                    'description' => 'Optional day name, e.g. Monday.',
                ],
            ],
        ];
    }

    public function handle(ChatContext $context, array $args): ToolResult
    {
        $student = $this->student($context);

        if (!$student) {
            return ToolResult::empty('The student record could not be found.');
        }

        // Current enrolment first, most recent otherwise.
        $enroll = $student->currentEnroll ?? StudentEnroll::where('student_id', $student->id)
            ->orderByDesc('id')
            ->first();

        if (!$enroll) {
            return ToolResult::empty('This student has no enrolment, so no timetable is assigned.');
        }

        $query = ClassRoutine::where('status', '1')
            ->where('session_id', $enroll->session_id)
            ->where('program_id', $enroll->program_id)
            ->where('semester_id', $enroll->semester_id)
            ->when($enroll->section_id, fn ($q) => $q->where('section_id', $enroll->section_id))
            ->with(['subject:id,title,code', 'room:id,title', 'teacher:id,first_name,last_name']);

        if (!empty($args['day'])) {
            $query->where('day', 'like', '%' . $args['day'] . '%');
        }

        $rows = $query->orderBy('day')->orderBy('start_time')->limit(60)->get();

        if ($rows->isEmpty()) {
            return ToolResult::empty(
                'No timetable has been published for this enrolment yet.',
                ['url' => route('student.class-routine.index'), 'label' => __('View your timetable')]
            );
        }

        return ToolResult::make(
            [
                'programme' => optional($enroll->program)->title,
                'semester' => optional($enroll->semester)->title,
                'classes' => $rows->map(fn ($r) => [
                    'day' => $r->day,
                    'subject' => optional($r->subject)->title,
                    'start_time' => $r->start_time,
                    'end_time' => $r->end_time,
                    'room' => optional($r->room)->title,
                    'lecturer' => trim(optional($r->teacher)->first_name . ' ' . optional($r->teacher)->last_name) ?: null,
                ])->all(),
            ],
            ['url' => route('student.class-routine.index'), 'label' => __('View your timetable')]
        );
    }
}
