<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentEnroll;
use App\Models\Program;
use App\Models\ProgramSessionMaxCredit;
use App\Models\Fee;
use Illuminate\Support\Facades\DB;

class ProgramSwapService
{
    /**
     * Validate if a student can swap programs
     * 
     * @param int $studentId
     * @param int $newProgramId
     * @return array ['can_swap' => bool, 'warnings' => array, 'blockers' => array]
     */
    public function validateProgramSwap($studentId, $newProgramId)
    {
        $student = Student::with(['program', 'currentEnroll'])->findOrFail($studentId);
        $newProgram = Program::with('faculty')->findOrFail($newProgramId);
        
        $result = [
            'can_swap' => true,
            'is_program_change' => $student->program_id != $newProgramId,
            'warnings' => [],
            'blockers' => [],
            'info' => [],
            'old_program' => $student->program,
            'new_program' => $newProgram,
            'new_matricule' => null,
            'generates_new_matricule' => false,
        ];

        // If not changing program, no validation needed
        if (!$result['is_program_change']) {
            $result['info'][] = 'Continuing in current program - no program change detected.';
            return $result;
        }

        // Check for unpaid fees
        $unpaidFees = $this->getUnpaidFees($student);
        if ($unpaidFees['count'] > 0) {
            $result['warnings'][] = [
                'type' => 'unpaid_fees',
                'message' => "Student has {$unpaidFees['count']} unpaid fee(s) totaling {$unpaidFees['total_amount']} from current program.",
                'severity' => 'high',
                'details' => $unpaidFees['fees']
            ];
        }

        // Check for pending payments
        $pendingPayments = $this->getPendingPayments($student);
        if ($pendingPayments['count'] > 0) {
            $result['warnings'][] = [
                'type' => 'pending_payments',
                'message' => "Student has {$pendingPayments['count']} pending payment verification(s) totaling {$pendingPayments['total_amount']}.",
                'severity' => 'medium',
                'details' => $pendingPayments['receipts']
            ];
        }

        // Check for active payment plans
        $paymentPlans = $this->getActivePaymentPlans($student);
        if ($paymentPlans['count'] > 0) {
            $result['warnings'][] = [
                'type' => 'payment_plans',
                'message' => "Student has {$paymentPlans['count']} active payment plan(s) in current program.",
                'severity' => 'high',
                'details' => $paymentPlans['plans']
            ];
        }

        // Check for enrolled subjects in current semester
        $enrolledSubjects = $this->getCurrentEnrolledSubjects($student);
        if ($enrolledSubjects['count'] > 0) {
            // Check subject compatibility with new program
            $subjectCompatibility = $this->checkSubjectCompatibility($enrolledSubjects['subject_ids'], $newProgram->id);
            
            if ($subjectCompatibility['incompatible_count'] > 0) {
                // WARNING (not blocker): Student has subjects that don't exist in new program
                // The system will create a NEW enrollment, so old subjects remain in old enrollment
                $result['warnings'][] = [
                    'type' => 'incompatible_subjects',
                    'message' => "Student has {$subjectCompatibility['incompatible_count']} enrolled subject(s) in current program that do not exist in the new program.",
                    'severity' => 'high',
                    'details' => $subjectCompatibility['incompatible_subjects'],
                    'note' => 'These subjects will remain in the old enrollment. A NEW enrollment will be created for the new program. You can drop these subjects later if needed.'
                ];
            } elseif ($subjectCompatibility['compatible_count'] > 0) {
                // INFO: All subjects are compatible
                $result['info'][] = [
                    'type' => 'compatible_subjects',
                    'message' => "Good news: All {$enrolledSubjects['count']} enrolled subject(s) exist in the new program and can be transferred if needed.",
                    'compatible_subjects' => $subjectCompatibility['compatible_subjects']
                ];
            }
        }

        // Check for recorded attendance
        $attendance = $this->getRecordedAttendance($student);
        if ($attendance['count'] > 0) {
            $result['warnings'][] = [
                'type' => 'attendance_records',
                'message' => "Student has {$attendance['count']} attendance record(s) in current enrollment.",
                'severity' => 'medium',
                'details' => $attendance['records']
            ];
        }

        // Check for exam marks
        $examMarks = $this->getExamMarks($student);
        if ($examMarks['count'] > 0) {
            $result['warnings'][] = [
                'type' => 'exam_marks',
                'message' => "Student has {$examMarks['count']} exam/marking record(s) in current enrollment.",
                'severity' => 'high',
                'details' => $examMarks['marks']
            ];
        }

        // Check for assignments
        $assignments = $this->getAssignments($student);
        if ($assignments['count'] > 0) {
            $result['warnings'][] = [
                'type' => 'assignments',
                'message' => "Student has {$assignments['count']} assignment submission(s) in current enrollment.",
                'severity' => 'medium',
                'details' => $assignments['assignments']
            ];
        }

        // Compare credit limits
        $creditComparison = $this->compareCreditLimits($student, $newProgram);
        if ($creditComparison['different']) {
            $result['info'][] = [
                'type' => 'credit_limit_change',
                'message' => "Credit limit changes from {$creditComparison['old_limit']} to {$creditComparison['new_limit']} credits per semester.",
                'old_limit' => $creditComparison['old_limit'],
                'new_limit' => $creditComparison['new_limit']
            ];
        }

        // Matricule handling - ALWAYS generate new matricule for program change
        $latestEnroll = StudentEnroll::where('student_id', $student->id)
            ->whereNotNull('matricule')
            ->orderBy('id', 'desc')
            ->first();
            
        // For any program change, generate a new matricule
        $result['generates_new_matricule'] = true;
        try {
            $result['new_matricule'] = Student::generateEnrollmentMatricule(
                $student->id,
                $newProgram->id,
                $student->batch_id
            );
            
            if ($latestEnroll && $latestEnroll->matricule) {
                $result['info'][] = [
                    'type' => 'matricule_change',
                    'message' => "Program change detected - NEW matricule will be generated: {$result['new_matricule']} (Old: {$latestEnroll->matricule})",
                    'highlight' => true,
                    'old_matricule' => $latestEnroll->matricule
                ];
            } else {
                $result['info'][] = [
                    'type' => 'new_matricule',
                    'message' => "A NEW matricule will be generated: {$result['new_matricule']}",
                    'highlight' => true
                ];
            }
        } catch (\Exception $e) {
            \Log::error('Matricule preview generation error: ' . $e->getMessage());
            $result['new_matricule'] = 'Will be auto-generated';
        }
        
        // Compare faculties
        if ($student->program && $student->program->faculty_id != $newProgram->faculty_id) {
            $oldFaculty = $student->program->faculty->title ?? 'Unknown';
            $newFaculty = $newProgram->faculty->title ?? 'Unknown';
            $result['warnings'][] = [
                'type' => 'faculty_change',
                'message' => "Faculty Change: '{$oldFaculty}' → '{$newFaculty}'. Ensure student meets admission requirements for the new faculty.",
                'severity' => 'high',
                'old_faculty' => $oldFaculty,
                'new_faculty' => $newFaculty
            ];
        }

        // Add program names for display
        $result['info'][] = [
            'type' => 'program_change',
            'message' => "Program Change: '{$student->program->title}' → '{$newProgram->title}'",
            'old_program_name' => $student->program->title ?? 'Unknown',
            'new_program_name' => $newProgram->title
        ];

        return $result;
    }

    /**
     * Get unpaid fees for student
     */
    protected function getUnpaidFees($student)
    {
        $fees = Fee::whereHas('studentEnroll', function($query) use ($student) {
            $query->where('student_id', $student->id);
        })
        ->where(function($query) {
            $query->where('status', '!=', 1) // Not paid
                  ->orWhereRaw('(fee_amount + IFNULL(fine_amount, 0) - IFNULL(discount_amount, 0)) > IFNULL(paid_amount, 0)');
        })
        ->with(['category', 'studentEnroll.semester'])
        ->get();

        $totalAmount = 0;
        $feeDetails = [];

        foreach ($fees as $fee) {
            $dueAmount = ($fee->fee_amount + ($fee->fine_amount ?? 0) - ($fee->discount_amount ?? 0)) - ($fee->paid_amount ?? 0);
            if ($dueAmount > 0) {
                $totalAmount += $dueAmount;
                $feeDetails[] = [
                    'category' => $fee->category->title ?? 'Unknown',
                    'semester' => $fee->studentEnroll->semester->title ?? 'Unknown',
                    'amount' => number_format($dueAmount, 2),
                    'due_date' => $fee->due_date ?? 'N/A'
                ];
            }
        }

        return [
            'count' => count($feeDetails),
            'total_amount' => number_format($totalAmount, 2),
            'fees' => $feeDetails
        ];
    }

    /**
     * Get pending payment receipts
     */
    protected function getPendingPayments($student)
    {
        $receipts = DB::table('payment_receipts')
            ->join('fees', 'payment_receipts.fee_id', '=', 'fees.id')
            ->join('student_enrolls', 'fees.student_enroll_id', '=', 'student_enrolls.id')
            ->where('student_enrolls.student_id', $student->id)
            ->where('payment_receipts.verification_status', 'pending')
            ->select('payment_receipts.*', 'fees.fee_amount')
            ->get();

        $totalAmount = $receipts->sum('amount_paid');
        $receiptDetails = $receipts->map(function($receipt) {
            return [
                'amount' => number_format($receipt->amount_paid, 2),
                'date' => $receipt->payment_date ?? 'N/A',
                'method' => $receipt->payment_method ?? 'Unknown'
            ];
        })->toArray();

        return [
            'count' => $receipts->count(),
            'total_amount' => number_format($totalAmount, 2),
            'receipts' => $receiptDetails
        ];
    }

    /**
     * Get active payment plans
     */
    protected function getActivePaymentPlans($student)
    {
        $plans = DB::table('payment_plans')
            ->join('fees', 'payment_plans.fee_id', '=', 'fees.id')
            ->join('student_enrolls', 'fees.student_enroll_id', '=', 'student_enrolls.id')
            ->where('student_enrolls.student_id', $student->id)
            ->where('payment_plans.status', 'active')
            ->select('payment_plans.*')
            ->get();

        $planDetails = $plans->map(function($plan) {
            return [
                'installments' => $plan->total_installments ?? 0,
                'paid_installments' => $plan->paid_installments ?? 0,
                'next_due_date' => $plan->next_due_date ?? 'N/A'
            ];
        })->toArray();

        return [
            'count' => $plans->count(),
            'plans' => $planDetails
        ];
    }

    /**
     * Get currently enrolled subjects
     */
    protected function getCurrentEnrolledSubjects($student)
    {
        if (!$student->currentEnroll) {
            return ['count' => 0, 'total_credits' => 0, 'subjects' => []];
        }

        $subjects = $student->currentEnroll->subjects()
            ->select('subjects.id', 'subjects.code', 'subjects.title', 'subjects.credit_hour')
            ->get();

        $totalCredits = $subjects->sum('credit_hour');
        $subjectDetails = $subjects->map(function($subject) {
            return [
                'id' => $subject->id,
                'code' => $subject->code,
                'title' => $subject->title,
                'credits' => $subject->credit_hour
            ];
        })->toArray();

        return [
            'count' => $subjects->count(),
            'total_credits' => number_format($totalCredits, 2),
            'subjects' => $subjectDetails,
            'subject_ids' => $subjects->pluck('id')->toArray()
        ];
    }

    /**
     * Check if enrolled subjects are compatible with new program
     */
    protected function checkSubjectCompatibility($enrolledSubjectIds, $newProgramId)
    {
        if (empty($enrolledSubjectIds)) {
            return [
                'compatible_count' => 0,
                'incompatible_count' => 0,
                'compatible_subjects' => [],
                'incompatible_subjects' => []
            ];
        }

        // Get subjects that exist in the new program
        $newProgramSubjects = DB::table('program_subject')
            ->where('program_id', $newProgramId)
            ->pluck('subject_id')
            ->toArray();

        $compatibleIds = array_intersect($enrolledSubjectIds, $newProgramSubjects);
        $incompatibleIds = array_diff($enrolledSubjectIds, $newProgramSubjects);

        // Get subject details
        $compatibleSubjects = [];
        if (!empty($compatibleIds)) {
            $compatibleSubjects = \App\Models\Subject::whereIn('id', $compatibleIds)
                ->select('id', 'code', 'title', 'credit_hour')
                ->get()
                ->map(function($subject) {
                    return [
                        'code' => $subject->code,
                        'title' => $subject->title,
                        'credits' => $subject->credit_hour
                    ];
                })
                ->toArray();
        }

        $incompatibleSubjects = [];
        if (!empty($incompatibleIds)) {
            $incompatibleSubjects = \App\Models\Subject::whereIn('id', $incompatibleIds)
                ->select('id', 'code', 'title', 'credit_hour')
                ->get()
                ->map(function($subject) {
                    return [
                        'code' => $subject->code,
                        'title' => $subject->title,
                        'credits' => $subject->credit_hour
                    ];
                })
                ->toArray();
        }

        return [
            'compatible_count' => count($compatibleIds),
            'incompatible_count' => count($incompatibleIds),
            'compatible_subjects' => $compatibleSubjects,
            'incompatible_subjects' => $incompatibleSubjects
        ];
    }

    /**
     * Get recorded attendance
     */
    protected function getRecordedAttendance($student)
    {
        if (!$student->currentEnroll) {
            return ['count' => 0, 'records' => []];
        }

        $attendanceCount = $student->currentEnroll->attendances()->count();

        return [
            'count' => $attendanceCount,
            'records' => []
        ];
    }

    /**
     * Get exam marks
     */
    protected function getExamMarks($student)
    {
        if (!$student->currentEnroll) {
            return ['count' => 0, 'marks' => []];
        }

        $marks = $student->currentEnroll->subjectMarks()
            ->with('subject')
            ->get();

        $markDetails = $marks->map(function($mark) {
            return [
                'subject' => $mark->subject->code ?? 'Unknown',
                'total_marks' => $mark->total_marks ?? 0
            ];
        })->toArray();

        return [
            'count' => $marks->count(),
            'marks' => $markDetails
        ];
    }

    /**
     * Get assignments
     */
    protected function getAssignments($student)
    {
        if (!$student->currentEnroll) {
            return ['count' => 0, 'assignments' => []];
        }

        $assignmentCount = $student->currentEnroll->assignments()->count();

        return [
            'count' => $assignmentCount,
            'assignments' => []
        ];
    }

    /**
     * Compare credit limits between programs
     */
    protected function compareCreditLimits($student, $newProgram)
    {
        $oldLimit = null;
        $newLimit = null;

        // Get old program credit limit
        if ($student->currentEnroll && $student->program) {
            $facultyId = $student->program->faculty_id ? (int) $student->program->faculty_id : null;
            $oldLimit = ProgramSessionMaxCredit::resolveLimit(
                (int) $student->program_id,
                (int) $student->currentEnroll->session_id,
                $facultyId
            );
        }

        // Get new program credit limit (use same session)
        if ($student->currentEnroll) {
            $facultyId = $newProgram->faculty_id ? (int) $newProgram->faculty_id : null;
            $newLimit = ProgramSessionMaxCredit::resolveLimit(
                (int) $newProgram->id,
                (int) $student->currentEnroll->session_id,
                $facultyId
            );
        }

        return [
            'different' => $oldLimit !== $newLimit,
            'old_limit' => $oldLimit ?? 'Not set',
            'new_limit' => $newLimit ?? 'Not set'
        ];
    }

    /**
     * Format validation result for display
     */
    public function formatValidationForDisplay($validation)
    {
        $html = '';

        // Show blockers (critical issues that prevent swap)
        if (!empty($validation['blockers'])) {
            $html .= '<div class="alert alert-danger"><strong>Critical Issues:</strong><ul>';
            foreach ($validation['blockers'] as $blocker) {
                $html .= '<li>' . htmlspecialchars($blocker['message']) . '</li>';
            }
            $html .= '</ul></div>';
        }

        // Show warnings (issues that should be reviewed)
        if (!empty($validation['warnings'])) {
            $html .= '<div class="alert alert-warning"><strong>Important Warnings:</strong><ul>';
            foreach ($validation['warnings'] as $warning) {
                $html .= '<li>' . htmlspecialchars($warning['message']) . '</li>';
            }
            $html .= '</ul></div>';
        }

        // Show info (general information)
        if (!empty($validation['info'])) {
            $html .= '<div class="alert alert-info"><strong>Program Change Information:</strong><ul>';
            foreach ($validation['info'] as $info) {
                $message = is_array($info) ? $info['message'] : $info;
                $html .= '<li>' . htmlspecialchars($message) . '</li>';
            }
            $html .= '</ul></div>';
        }

        return $html;
    }
}
