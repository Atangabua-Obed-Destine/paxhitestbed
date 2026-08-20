<?php

namespace App\Services\Chat\Tools\Student;

use App\Models\StudentAttendance;
use App\Services\Chat\ChatContext;
use App\Services\Chat\Tools\StudentTool;
use App\Services\Chat\ToolResult;

/**
 * Attendance summary for the signed-in student.
 *
 * Mirrors Student\AttendanceController: attendance rows are reached only via
 * this student's own enrolment ids.
 */
class MyAttendanceTool extends StudentTool
{
    public function name(): string
    {
        return 'my_attendance';
    }

    public function description(): string
    {
        return 'Get the signed-in student\'s attendance record — days present, absent and late, '
            . 'with an attendance percentage. Optionally narrowed to a month and year.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'year' => ['type' => 'INTEGER', 'description' => 'Four-digit year, e.g. 2026.'],
                'month' => ['type' => 'INTEGER', 'description' => 'Month number, 1-12.'],
            ],
        ];
    }

    public function handle(ChatContext $context, array $args): ToolResult
    {
        $enrollmentIds = $this->enrollmentIds($context);

        if (empty($enrollmentIds)) {
            return ToolResult::empty('This student has no enrolment records, so no attendance has been taken.');
        }

        $query = StudentAttendance::whereIn('student_enroll_id', $enrollmentIds);

        $year = isset($args['year']) ? (int) $args['year'] : null;
        $month = isset($args['month']) ? (int) $args['month'] : null;

        if ($year) {
            $query->whereYear('date', $year);
        }
        if ($month >= 1 && $month <= 12) {
            $query->whereMonth('date', $month);
        }

        $rows = $query->get(['attendance', 'date']);

        if ($rows->isEmpty()) {
            return ToolResult::empty(
                'No attendance has been recorded for that period.',
                ['url' => route('student.attendance.index'), 'label' => __('View your attendance')]
            );
        }

        // attendance: 1 present, 2 late, 3 absent (matches the portal's legend).
        $present = $rows->where('attendance', '1')->count();
        $late = $rows->where('attendance', '2')->count();
        $absent = $rows->where('attendance', '3')->count();
        $total = $rows->count();

        return ToolResult::make(
            [
                'period' => trim(($month ? sprintf('%02d/', $month) : '') . ($year ?: 'all records')),
                'total_days_recorded' => $total,
                'present' => $present,
                'late' => $late,
                'absent' => $absent,
                'attendance_percentage' => $total ? round((($present + $late) / $total) * 100, 1) : null,
            ],
            ['url' => route('student.attendance.index'), 'label' => __('View your attendance')]
        );
    }
}
