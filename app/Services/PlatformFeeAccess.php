<?php

namespace App\Services;

use App\Models\PlatformFeeExemption;
use App\Models\PlatformFeePayment;
use App\Models\PlatformFeeSetting;
use App\Models\Student;
use App\Models\StudentEnroll;
use Illuminate\Support\Collection;

/**
 * Whether a student has settled the platform access fee for the year.
 *
 * The fee is charged once per academic session. That is not how it behaved: the
 * middleware, the payment screen and the exemptions all keyed on
 * student_enroll_id, and a student gets a NEW enrolment for every semester —
 * first semester, its resit semester, second semester, its resit semester. So
 * paying in September bought access until the student progressed, days later,
 * at which point the portal asked them to pay again. In this database 64
 * student/session pairs carry more than one enrolment, several carrying five.
 *
 * The question asked here is therefore about the student and the academic
 * session, never about one enrolment: a payment raised from any enrolment in a
 * session covers that whole session, and so does an exemption.
 *
 * The payment row still records which enrolment it was raised from, because
 * that is a true fact about it and worth keeping.
 */
class PlatformFeeAccess
{
    public function setting(): ?PlatformFeeSetting
    {
        return PlatformFeeSetting::first();
    }

    /** Is the fee switched on at all? */
    public function isEnabled(): bool
    {
        $setting = $this->setting();

        return $setting !== null && (bool) $setting->is_enabled;
    }

    /**
     * The enrolment the student is currently on.
     *
     * Student::currentEnroll is what the rest of the portal means by "where this
     * student is now"; the platform fee screens picked the most recently created
     * row instead, which is not the same thing once a resit enrolment exists.
     */
    public function currentEnrollment(Student $student): ?StudentEnroll
    {
        return $student->currentEnroll
            ?: StudentEnroll::where('student_id', $student->id)->orderByDesc('id')->first();
    }

    /**
     * Every enrolment this student holds in the same academic session — the
     * span one payment has to cover.
     *
     * @return Collection<int, int> enrolment ids
     */
    public function enrollmentsInSameSession(Student $student, StudentEnroll $enrollment): Collection
    {
        return StudentEnroll::where('student_id', $student->id)
            ->when(
                $enrollment->session_id,
                fn ($query) => $query->where('session_id', $enrollment->session_id),
                // An enrolment with no session can only speak for itself.
                fn ($query) => $query->whereKey($enrollment->id)
            )
            ->pluck('id');
    }

    /** The platform fee payment covering this student's academic session, if any. */
    public function paymentFor(Student $student, StudentEnroll $enrollment): ?PlatformFeePayment
    {
        $enrollmentIds = $this->enrollmentsInSameSession($student, $enrollment);

        return PlatformFeePayment::whereIn('student_enroll_id', $enrollmentIds)
            // An approved payment settles the year; otherwise show the latest
            // attempt, so a student sees the receipt they are waiting on rather
            // than an empty form.
            ->orderByRaw("CASE WHEN status = 'approved' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->first();
    }

    public function hasPaid(Student $student, StudentEnroll $enrollment): bool
    {
        $payment = $this->paymentFor($student, $enrollment);

        return $payment !== null && $payment->status === 'approved';
    }

    /**
     * Is this student excused for this session?
     *
     * A session exemption excuses everybody in it. A student exemption is
     * recorded against one enrolment but means the student: it would otherwise
     * lapse the moment they progressed, which is not what granting it says.
     */
    public function isExempt(Student $student, StudentEnroll $enrollment): bool
    {
        if ($enrollment->session_id && PlatformFeeExemption::where('status', 1)
            ->where('exemption_type', 'session')
            ->where('session_id', $enrollment->session_id)
            ->exists()) {
            return true;
        }

        return PlatformFeeExemption::where('status', 1)
            ->where('exemption_type', 'student')
            ->whereIn('student_enroll_id', $this->enrollmentsInSameSession($student, $enrollment))
            ->exists();
    }

    /** May this student reach the portal without paying anything further? */
    public function grantsAccess(Student $student): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        $enrollment = $this->currentEnrollment($student);

        if (!$enrollment) {
            return true;
        }

        return $this->isExempt($student, $enrollment) || $this->hasPaid($student, $enrollment);
    }
}
