<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Fee;
use App\Models\PaymentReceipt;
use App\Models\Student;
use App\Models\StudentCredit;
use App\Models\StudentEnroll;
use App\Models\Transaction;

/**
 * Undoing a student record that was created from an application by mistake.
 *
 * A student converted onto the wrong programme cannot simply be edited: the
 * programme is written onto the student and the enrolment at conversion, and the
 * application keeps its own record of what was approved. The practical fix the
 * school asked for is to take the half-made record away and put the application
 * back in front of the admissions officer, so they can correct the programme and
 * convert it properly.
 *
 * This is deliberately narrow. It undoes a conversion that never really
 * happened - a student with no enrolment, no money and no history. The moment
 * any of those exist, the record is part of the school's accounts and must be
 * corrected rather than deleted, so this refuses and says why.
 */
class UnconvertStudent
{
    /**
     * Why this student record may not be taken back to its application.
     *
     * An empty array means it may. Each reason names the obstacle, because
     * "cannot delete" on its own tells an admissions officer nothing.
     *
     * @return array<int, string>
     */
    public function blockers(Student $student): array
    {
        $reasons = [];

        $enrolments = StudentEnroll::where('student_id', $student->id)->count();

        if ($enrolments > 0) {
            // The whole point: this is for records that were never enrolled.
            $reasons[] = __('This student has :count enrolment(s). Only a student record created without an enrolment can be sent back to its application.', [
                'count' => $enrolments,
            ]);
        }

        $receipts = PaymentReceipt::where('student_id', $student->id)->count();

        if ($receipts > 0) {
            $reasons[] = __('This student has :count payment receipt(s) against them. Money that has been received cannot be deleted away.', [
                'count' => $receipts,
            ]);
        }

        $credits = StudentCredit::where('student_id', $student->id)->count();

        if ($credits > 0) {
            $reasons[] = __('This student holds :count credit record(s).', ['count' => $credits]);
        }

        $transactions = Transaction::where('transactionable_type', Student::class)
            ->where('transactionable_id', $student->id)
            ->count();

        if ($transactions > 0) {
            $reasons[] = __('This student has :count entry(ies) on their financial statement.', [
                'count' => $transactions,
            ]);
        }

        $fees = Fee::whereHas('studentEnroll', fn ($query) => $query->where('student_id', $student->id))->count();

        if ($fees > 0) {
            $reasons[] = __('This student has :count fee(s) assigned.', ['count' => $fees]);
        }

        if (!$this->applicationFor($student)) {
            $reasons[] = __('No application matches this student, so there is nothing to send them back to. Delete the record from the student screen instead.');
        }

        return $reasons;
    }

    public function canUnconvert(Student $student): bool
    {
        return $this->blockers($student) === [];
    }

    /**
     * The application this student was made from.
     *
     * Matched on the registration number, which is the only link between the
     * two tables - and a reliable one: no registration number is shared by two
     * students.
     */
    public function applicationFor(Student $student): ?Application
    {
        if (!$student->registration_no) {
            return null;
        }

        return Application::where('registration_no', $student->registration_no)->first();
    }

    /**
     * Put the application back in front of the admissions officer.
     *
     * The student record itself is removed by the caller, which owns the file
     * cleanup. This is the part that is a rule: what the application goes back
     * to, and the trail it leaves behind.
     *
     * @return array{stage: string, status: int}
     */
    public function revertApplication(Application $application, ?string $reason = null, ?int $userId = null): array
    {
        // Back to the decision, not back to the beginning. Everything the
        // applicant and the office already did - documents, interview, the
        // board's approval - stands; only the conversion is undone.
        $note = trim(__('The student record created from this application was removed, so the admission can be corrected and made again.')
            . ($reason ? ' ' . $reason : ''));

        $application->recordStatus(
            'decision_pending',
            $note,
            1,
            __('Student record withdrawn'),
            $userId,
            'App\\User'
        );

        // recordStatus stamps these only when a decision is reached. Reaching
        // back past one has to clear them, or the application still reads as
        // decided and completed.
        $application->decision_at = null;
        $application->completed_at = null;
        $application->save();

        return [
            'stage' => $application->stage,
            'status' => (int) $application->status,
        ];
    }
}
