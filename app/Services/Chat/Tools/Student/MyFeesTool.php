<?php

namespace App\Services\Chat\Tools\Student;

use App\Models\Fee;
use App\Services\Chat\ChatContext;
use App\Services\Chat\Tools\StudentTool;
use App\Services\Chat\ToolResult;

/**
 * The signed-in student's fee statement.
 *
 * Mirrors the scoping in Student\FeesController@index: fees are reached only
 * through this student's own enrolment ids.
 */
class MyFeesTool extends StudentTool
{
    public function name(): string
    {
        return 'my_fees';
    }

    public function description(): string
    {
        return 'Get the signed-in student\'s fees: what is owed, what has been paid, '
            . 'due dates and the overall outstanding balance. Use for any question about '
            . 'money owed, payments, balances or fee deadlines.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'only_unpaid' => [
                    'type' => 'BOOLEAN',
                    'description' => 'Return only fees that are not fully paid.',
                ],
            ],
        ];
    }

    public function handle(ChatContext $context, array $args): ToolResult
    {
        $enrollmentIds = $this->enrollmentIds($context);

        if (empty($enrollmentIds)) {
            return ToolResult::empty('This student has no enrolment records, so no fees are assigned yet.');
        }

        $query = Fee::with(['category', 'studentEnroll.program', 'studentEnroll.semester', 'studentEnroll.session'])
            ->whereIn('student_enroll_id', $enrollmentIds)
            // Statuses mirror the portal: 0 unpaid, 1 paid, 2 partial; 3 cancelled is excluded.
            ->whereIn('status', ['0', '1', '2']);

        if (!empty($args['only_unpaid'])) {
            $query->whereIn('status', ['0', '2']);
        }

        $fees = $query->orderBy('due_date')->limit(50)->get();

        if ($fees->isEmpty()) {
            return ToolResult::empty(
                'No fees match that description for this student.',
                ['url' => route('student.fees.index'), 'label' => __('View your fee statement')]
            );
        }

        $outstanding = 0.0;
        $rows = [];

        foreach ($fees as $fee) {
            $balance = ($fee->fee_amount + $fee->fine_amount - $fee->discount_amount) - $fee->paid_amount;
            $balance = max(0, $balance);
            $outstanding += $balance;

            $rows[] = [
                'category' => optional($fee->category)->title,
                'programme' => optional(optional($fee->studentEnroll)->program)->title,
                'amount' => $this->money($fee->fee_amount),
                'paid' => $this->money($fee->paid_amount),
                'balance' => $this->money($balance),
                'due_date' => optional($fee->due_date)->format('Y-m-d'),
                'status' => match ((string) $fee->status) {
                    '1' => 'paid',
                    '2' => 'partially paid',
                    default => 'unpaid',
                },
            ];
        }

        return ToolResult::make(
            [
                'total_outstanding' => $this->money($outstanding),
                'fee_count' => count($rows),
                'fees' => $rows,
            ],
            ['url' => route('student.fees.index'), 'label' => __('View your fee statement')]
        );
    }
}
