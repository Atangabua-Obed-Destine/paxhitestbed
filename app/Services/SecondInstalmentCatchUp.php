<?php

namespace App\Services;

use App\Models\Fee;
use App\Models\FeesCategory;
use App\Models\ProgramSemesterFee;
use App\Models\Semester;
use App\Models\StudentCredit;
use App\Models\StudentEnroll;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Raises the Second Instalments that were never assigned.
 *
 * In 2025/2026 the instalments were configured one semester at a time: First
 * Instalments against the first semester, Second Instalments against the
 * second. The school's model is both against the first semester of the year, so
 * a student who paid more than their First Instalment and then left during that
 * semester was never given a Second Instalment to spend the excess on. Their
 * overpayment sits as unusable credit and the fees report calls them overpaid.
 *
 * This raises the missing fee at the programme's configured amount and lets the
 * student's credit settle what it can. It writes nothing on its own: the screen
 * previews who would be billed and by how much, and only the students ticked
 * there are passed to apply().
 */
class SecondInstalmentCatchUp
{
    /** Written on every fee this raises: what undo() recognises, and what the bursar sees. */
    public const MARKER = 'Second Instalment catch-up';

    protected StudentCreditService $credits;

    public function __construct(StudentCreditService $credits)
    {
        $this->credits = $credits;
    }

    public function category(): ?FeesCategory
    {
        return FeesCategory::where('is_second_installment', 1)->first();
    }

    /**
     * Students who should have a Second Instalment for this year and have none.
     *
     * A row with `blocked` set cannot be applied — its programme has no Second
     * Instalment configured on Program Semester Fee, so there is no amount to
     * raise and guessing one is worse than saying so.
     */
    public function preview(int $sessionId, ?int $programId = null): Collection
    {
        $category = $this->category();

        if (!$category) {
            return collect();
        }

        // Every regular (non-resit) enrolment for the year, newest first, so the
        // one a fee attaches to is the student's latest.
        $enrollments = StudentEnroll::with(['student', 'program', 'semester'])
            ->where('session_id', $sessionId)
            ->whereHas('semester', fn ($query) => $query->where('is_resit', 0))
            ->whereHas('student', fn ($query) => $query->where('status', '!=', 0))
            ->when($programId, fn ($query) => $query->where('program_id', $programId))
            ->orderByDesc('id')
            ->get();

        if ($enrollments->isEmpty()) {
            return collect();
        }

        // Who already has one, counting every enrolment of theirs for the year.
        $allEnrollments = StudentEnroll::where('session_id', $sessionId)
            ->whereIn('student_id', $enrollments->pluck('student_id')->unique())
            ->get(['id', 'student_id']);

        $alreadyHas = Fee::whereIn('student_enroll_id', $allEnrollments->pluck('id'))
            ->where('category_id', $category->id)
            ->pluck('student_enroll_id')
            ->map(fn ($enrollId) => (int) optional($allEnrollments->firstWhere('id', $enrollId))->student_id)
            ->filter()
            ->unique();

        $configured = $this->configuredAmounts();

        return $enrollments
            ->unique('student_id')
            ->reject(fn ($enroll) => $alreadyHas->contains((int) $enroll->student_id))
            ->map(function ($enroll) use ($configured, $sessionId) {
                $config = $configured->get($enroll->program_id);
                $amount = (float) optional($config)->amount;
                $fine = $this->fineFor($config);
                $credit = (float) StudentCredit::where('student_id', $enroll->student_id)
                    ->whereIn('status', [StudentCredit::STATUS_AVAILABLE, StudentCredit::STATUS_PARTIALLY_APPLIED])
                    ->sum('remaining_amount');

                return [
                    'student_id' => (int) $enroll->student_id,
                    'enroll_id' => (int) $enroll->id,
                    'matricule' => $enroll->matricule ?? optional($enroll->student)->student_id,
                    'name' => trim(optional($enroll->student)->first_name . ' ' . optional($enroll->student)->last_name),
                    'program' => optional($enroll->program)->title,
                    'program_id' => (int) $enroll->program_id,
                    'reached_second_semester' => $this->reachedSecondSemester((int) $enroll->student_id, $sessionId),
                    'amount' => $amount,
                    'fine' => $fine,
                    'due_date' => $config ? $this->dueDateFor($config) : null,
                    'credit' => $credit,
                    // What the student would still owe once their credit is applied.
                    'owing' => max(0, round($amount + $fine - $credit, 2)),
                    'blocked' => $config ? null : __('No Second Instalment is configured for this programme on Program Semester Fee.'),
                ];
            })
            ->sortBy([['program', 'asc'], ['name', 'asc']])
            ->values();
    }

    /**
     * Raise the fee for the students given, and let their credit settle it.
     *
     * One transaction per student: a student whose fee cannot be raised is
     * reported and the rest still go through.
     *
     * @return array{billed: int, raised: float, credit_applied: float, owing: float, failed: array}
     */
    public function apply(array $studentIds, int $sessionId, ?int $userId = null): array
    {
        $wanted = array_map('intval', $studentIds);
        $rows = $this->preview($sessionId)->whereIn('student_id', $wanted);

        $billed = 0;
        $raised = $creditApplied = $owing = 0.0;
        $failed = [];

        foreach ($rows as $row) {
            if ($row['blocked']) {
                $failed[] = ['student' => $row['matricule'], 'reason' => $row['blocked']];

                continue;
            }

            try {
                $fee = DB::transaction(function () use ($row, $userId) {
                    $fee = new Fee();
                    $fee->student_enroll_id = $row['enroll_id'];
                    $fee->category_id = $this->category()->id;
                    $fee->fee_amount = $row['amount'];
                    $fee->fine_amount = $row['fine'];
                    $fee->assign_date = now()->format('Y-m-d');
                    $fee->due_date = $row['due_date'];
                    $fee->status = 0;
                    $fee->note = self::MARKER . ' on ' . now()->format('d M Y')
                        . ' - never assigned when the second semester fees were configured.';
                    $fee->created_by = $userId;
                    $fee->save();

                    // The student's own credit settles what it can. Applying
                    // credit is not cash, so this adds nothing to the ledger.
                    $this->credits->autoApplyCreditsToNewFee($fee, $userId);

                    return $fee->fresh();
                });

                $billed++;
                $raised += (float) $fee->fee_amount + (float) $fee->fine_amount;
                $creditApplied += (float) $fee->paid_amount;
                $owing += max(0, (float) $fee->fee_amount + (float) $fee->fine_amount - (float) $fee->paid_amount);
            } catch (\Throwable $e) {
                $failed[] = ['student' => $row['matricule'], 'reason' => $e->getMessage()];
            }
        }

        return [
            'billed' => $billed,
            'raised' => round($raised, 2),
            'credit_applied' => round($creditApplied, 2),
            'owing' => round($owing, 2),
            'failed' => $failed,
        ];
    }

    /** Fees this tool raised for the year, newest first. */
    public function raised(int $sessionId, ?int $programId = null): Collection
    {
        $enrollIds = StudentEnroll::where('session_id', $sessionId)
            ->when($programId, fn ($query) => $query->where('program_id', $programId))
            ->pluck('id');

        return Fee::with(['studentEnroll.student', 'studentEnroll.program', 'creditApplications'])
            ->whereIn('student_enroll_id', $enrollIds)
            ->where('note', 'like', self::MARKER . '%')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($fee) => [
                'fee_id' => $fee->id,
                'matricule' => $fee->studentEnroll->matricule ?? optional(optional($fee->studentEnroll)->student)->student_id,
                'name' => trim(optional(optional($fee->studentEnroll)->student)->first_name . ' ' . optional(optional($fee->studentEnroll)->student)->last_name),
                'program' => optional(optional($fee->studentEnroll)->program)->title,
                'amount' => (float) $fee->fee_amount + (float) $fee->fine_amount,
                'paid' => (float) $fee->paid_amount,
                'credit_applied' => (float) $fee->creditApplications->sum('amount_applied'),
                // Only a fee nobody has touched can be taken back.
                'removable' => (float) $fee->paid_amount < 0.01 && $fee->creditApplications->isEmpty(),
            ]);
    }

    /**
     * Take back fees this tool raised, while nothing has been done with them.
     *
     * A fee that has been paid, or that credit has been applied to, is left
     * alone and named: unpicking a credit application is not a deletion, and a
     * receipt is not ours to throw away.
     *
     * @return array{removed: int, amount: float, refused: array}
     */
    public function undo(array $feeIds, ?int $userId = null): array
    {
        $removed = 0;
        $amount = 0.0;
        $refused = [];

        foreach (Fee::with('creditApplications')->whereIn('id', array_map('intval', $feeIds))->get() as $fee) {
            if (!str_starts_with((string) $fee->note, self::MARKER)) {
                $refused[] = ['fee_id' => $fee->id, 'reason' => __('This fee was not raised by the catch-up.')];

                continue;
            }

            if ((float) $fee->paid_amount >= 0.01 || $fee->creditApplications->isNotEmpty()) {
                $refused[] = ['fee_id' => $fee->id, 'reason' => __('Money has already been put against this fee.')];

                continue;
            }

            $amount += (float) $fee->fee_amount + (float) $fee->fine_amount;
            $fee->delete();
            $removed++;
        }

        return ['removed' => $removed, 'amount' => round($amount, 2), 'refused' => $refused];
    }

    /**
     * The Second Instalment configured for each programme.
     *
     * A programme can have one configured against more than one semester; the
     * second semester's is the one this is catching up on, so it wins.
     */
    protected function configuredAmounts(): Collection
    {
        $category = $this->category();

        return ProgramSemesterFee::with('semester')
            ->where('fees_category_id', $category->id)
            ->where('status', 1)
            ->get()
            ->sortBy(fn ($config) => optional($config->semester)->semester_type === Semester::TYPE_SECOND ? 0 : 1)
            ->keyBy('program_id');
    }

    protected function fineFor($config): float
    {
        if (!$config || !$config->fine_amount || !$config->fine_type) {
            return 0.0;
        }

        return $config->fine_type === 'percentage'
            ? round(((float) $config->amount * (float) $config->fine_amount) / 100, 2)
            : (float) $config->fine_amount;
    }

    /**
     * The same due date the ordinary assignment would have given it: the
     * configured month and day, or 60 days out for a second semester fee.
     */
    protected function dueDateFor($config): string
    {
        if ($config->due_month && $config->due_day) {
            return \Carbon\Carbon::createFromDate(
                optional($config->semester)->year ?? now()->year,
                $config->due_month,
                $config->due_day
            )->format('Y-m-d');
        }

        return now()->addDays(60)->format('Y-m-d');
    }

    protected function reachedSecondSemester(int $studentId, int $sessionId): bool
    {
        return StudentEnroll::where('student_id', $studentId)
            ->where('session_id', $sessionId)
            ->whereHas('semester', fn ($query) => $query->where('semester_type', Semester::TYPE_SECOND)->where('is_resit', 0))
            ->exists();
    }
}
