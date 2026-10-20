<?php

namespace App\Services\Academic;

use App\Models\Fee;
use App\Models\Program;
use App\Models\ProgramSemesterFee;
use App\Models\Student;
use App\Models\StudentEnroll;
use App\Services\FeeAssignmentAmount;
use App\Services\FeePaymentState;
use Illuminate\Support\Facades\DB;

/**
 * Moving a student to another programme, and putting their fees right.
 *
 * What happened before this existed: there was no programme change. An admin
 * enrolled the student again on the new programme, which added a second
 * enrolment and left the first one active, so the student was on both at once.
 * No fee was raised for the new programme and none of the old programme's fees
 * were touched - they stayed on the enrolment the student had left. One real
 * student ended up with 300,000 paid against a programme they were no longer
 * on, and no fee at all on the one they had joined.
 *
 * So this repoints the enrolment the student is actually sitting in. The row
 * keeps its id, which is the whole point: the fees, marks and attendance hang
 * off `student_enroll_id`, so they stay attached and can be reconciled rather
 * than stranded.
 *
 * THE MONEY RULES, which are the school's and not this class's invention:
 *
 *   - the new programme charges MORE  -> raise the fee to match it
 *   - the new programme charges LESS  -> leave the fee alone. A student is not
 *     refunded for moving to a cheaper programme, and a charge already paid is
 *     never reduced.
 *   - nothing has been paid at all    -> replace the figure outright, in either
 *     direction. Before any money moves a fee is just a number, and the student
 *     should simply be charged what their actual programme costs.
 *   - the new programme charges something the old one did not -> raise it.
 *   - the old programme charged something the new one does not -> report it and
 *     leave it. Deleting a charge is somebody's decision, not a side effect.
 *
 * Every amount change goes through FeeAssignmentAmount, which already owns the
 * rule that a paid fee cannot be lowered and already recomputes status, applies
 * credit and resyncs the ledger. Nothing here does arithmetic on money.
 *
 * Fees are collected BY STUDENT, not by enrolment: 62 of 73 students have their
 * first and second instalment on different enrolment rows in different
 * semesters, so a per-enrolment scan finds one and misses the other.
 */
class ProgrammeChange
{
    /** What this change would do, in full, before anything is written. */
    public function preview(StudentEnroll $enrolment, int $newProgramId): array
    {
        $from = Program::find($enrolment->program_id);
        $to = Program::find($newProgramId);

        return [
            'from' => $from,
            'to' => $to,
            'blockers' => $this->blockers($enrolment, $newProgramId),
            'warnings' => $this->warnings($enrolment, $newProgramId),
            'fees' => $this->feePlan($enrolment, $newProgramId),
            'courses' => $this->coursePlan($enrolment, $newProgramId),
            'matricule' => $this->proposedMatricule($enrolment, $newProgramId),
        ];
    }

    /**
     * Why this move cannot happen.
     *
     * An empty array means it can. Unlike ProgramSwapService - whose `blockers`
     * key is initialised empty and never written to, so it can never refuse
     * anything - these are real.
     *
     * @return array<int, string>
     */
    public function blockers(StudentEnroll $enrolment, int $newProgramId): array
    {
        $reasons = [];

        $target = Program::find($newProgramId);

        if (!$target) {
            return [__('That programme does not exist.')];
        }

        if ((int) $enrolment->program_id === $newProgramId) {
            $reasons[] = __('The student is already on this programme.');
        }

        // Within the faculty only. A move across faculties is a different
        // thing entirely - a different curriculum, a different matricule
        // series, usually a fresh admission rather than a transfer - and this
        // screen reconciles fees, not admissions.
        $current = Program::find($enrolment->program_id);

        if ($current && $target->faculty_id && $current->faculty_id
            && (int) $target->faculty_id !== (int) $current->faculty_id) {
            $reasons[] = __(':target is in :newFaculty, but the student is in :oldFaculty. A programme change is only possible within the same faculty.', [
                'target' => $target->title,
                'newFaculty' => optional($target->faculty)->title ?? __('another faculty'),
                'oldFaculty' => optional($current->faculty)->title ?? __('their faculty'),
            ]);
        }

        // Only at the very beginning. A programme change rewrites the
        // matricule, and every document already issued under the old one -
        // transcripts above all - keeps naming it, because those are
        // deliberate snapshots of what was signed. So the move is allowed only
        // while there is nothing to have been issued yet.
        $semester = $enrolment->semester;

        if ($semester && ((int) $semester->semester_type !== 1 || (int) $semester->year !== 1)) {
            $reasons[] = __('This enrolment is on :semester. A programme may only be changed while the student is in the first semester of year one.', [
                'semester' => $semester->title,
            ]);
        }

        // Anywhere, not just on this enrolment: a year-two student has
        // year-one results, and a transcript carrying them would go stale.
        $published = \App\Models\SubjectMarking::where('workflow_state', 'published')
            ->whereHas('studentEnroll', fn ($query) => $query->where('student_id', $enrolment->student_id))
            ->count();

        if ($published > 0) {
            $reasons[] = __(':count published result(s) already exist for this student. A programme change would leave them, and any transcript carrying them, naming a programme and matricule the student no longer has.', [
                'count' => $published,
            ]);
        }

        // A course the new programme does not teach would be left registered
        // against a programme that has no such course.
        $courses = $this->coursePlan($enrolment, $newProgramId);

        if ($courses['orphans']->isNotEmpty()) {
            $withMarks = $courses['orphans']->where('has_marks', true);

            $reasons[] = __(':count registered course(s) are not taught by :programme: :list. Drop them from the student\'s registration first.', [
                'count' => $courses['orphans']->count(),
                'programme' => $target->title,
                'list' => $courses['orphans']->pluck('code')->implode(', '),
            ]);

            if ($withMarks->isNotEmpty()) {
                $reasons[] = __(':count of those already carry marks (:list). Those marks would be left against a course the student is not registered for, so decide what happens to them before moving.', [
                    'count' => $withMarks->count(),
                    'list' => $withMarks->pluck('code')->implode(', '),
                ]);
            }
        }

        // Money in flight. FeeAssignmentAmount refuses these one by one; the
        // move must not half-apply around them.
        foreach ($this->feesOf($enrolment) as $fee) {
            if ($fee->pendingReceipt()->exists()) {
                $reasons[] = __('Fee #:id has a payment awaiting verification. Approve or reject it first.', [
                    'id' => $fee->id,
                ]);
            }

            if ($fee->payment_plan_id && $fee->paymentPlan && $fee->paymentPlan->status === 'active') {
                $reasons[] = __('Fee #:id is on an active payment plan. Cancel or re-issue the plan first.', [
                    'id' => $fee->id,
                ]);
            }
        }

        return $reasons;
    }

    /**
     * Things the admin should know but which do not stop the move.
     *
     * Only 12 of 39 programmes have a subject list or a fee structure, and the
     * programme dropdown offers all of them. Moving a student onto an
     * unconfigured programme is allowed - a school must be able to move someone
     * into something it is still setting up - but never silently.
     *
     * @return array<int, string>
     */
    public function warnings(StudentEnroll $enrolment, int $newProgramId): array
    {
        $warnings = [];
        $target = Program::find($newProgramId);

        if (!$target) {
            return $warnings;
        }

        if (!DB::table('program_subject')->where('program_id', $newProgramId)->exists()) {
            $warnings[] = __(':programme has no subject list configured, so no course can be checked against it.', [
                'programme' => $target->title,
            ]);
        }

        if (!ProgramSemesterFee::where('program_id', $newProgramId)->where('status', 1)->exists()) {
            $warnings[] = __(':programme has no fee structure configured, so no fee can be compared or raised.', [
                'programme' => $target->title,
            ]);
        }

        return $warnings;
    }

    /**
     * Every fee of this student that belongs to the programme being left.
     *
     * By student, deliberately: the two instalments usually sit on different
     * enrolments in different semesters.
     */
    public function feesOf(StudentEnroll $enrolment)
    {
        $studentId = $enrolment->student_id;
        $programId = $enrolment->program_id;

        return Fee::with(['category', 'studentEnroll.semester', 'paymentPlan'])
            ->whereHas('studentEnroll', function ($query) use ($studentId, $programId) {
                $query->where('student_id', $studentId)->where('program_id', $programId);
            })
            ->where('status', '!=', '3')
            ->orderBy('id')
            ->get();
    }

    /**
     * What each fee would become, and why.
     *
     * @return \Illuminate\Support\Collection
     */
    public function feePlan(StudentEnroll $enrolment, int $newProgramId)
    {
        $plan = collect();

        foreach ($this->feesOf($enrolment) as $fee) {
            $semesterId = optional($fee->studentEnroll)->semester_id;

            $target = $semesterId
                ? ProgramSemesterFee::where('program_id', $newProgramId)
                    ->where('semester_id', $semesterId)
                    ->where('fees_category_id', $fee->category_id)
                    ->where('status', 1)
                    ->first()
                : null;

            $current = round((float) ($fee->fee_amount ?? 0), 2);
            $untouched = FeePaymentState::isUntouched($fee);

            if (!$target) {
                $plan->push($this->line($fee, $current, $current, 'not_charged',
                    __(':programme does not charge :category. The existing charge is left as it is.', [
                        'programme' => optional(Program::find($newProgramId))->title,
                        'category' => optional($fee->category)->title,
                    ]), $untouched));

                continue;
            }

            $new = round((float) $target->amount, 2);

            if (abs($new - $current) < 0.005) {
                $plan->push($this->line($fee, $current, $current, 'same',
                    __('Both programmes charge the same for this.'), $untouched));

                continue;
            }

            // Nothing paid: the figure is just a number, so it simply becomes
            // what the student's actual programme costs - in either direction.
            if ($untouched) {
                $plan->push($this->line($fee, $current, $new, 'replace',
                    __('Nothing has been paid against this yet, so it is replaced with the new programme\'s figure.'),
                    true));

                continue;
            }

            if ($new > $current) {
                $plan->push($this->line($fee, $current, $new, 'raise',
                    __('The new programme charges more, so the assignment is raised to match it.'), false));

                continue;
            }

            // Cheaper, and already paid against: the charge stands.
            $plan->push($this->line($fee, $current, $current, 'keep',
                __('The new programme charges less, but money has already been paid against this, so the existing charge stands.'),
                false));
        }

        // Anything the new programme charges that the student has no fee for.
        $plan = $plan->concat($this->missingFees($enrolment, $newProgramId));

        return $plan;
    }

    /** Fees the new programme charges which the student does not have at all. */
    protected function missingFees(StudentEnroll $enrolment, int $newProgramId)
    {
        $existing = $this->feesOf($enrolment);
        $semesterIds = $existing->pluck('studentEnroll.semester_id')
            ->filter()
            ->push($enrolment->semester_id)
            ->unique();

        $missing = collect();

        $configured = ProgramSemesterFee::with('feesCategory')
            ->where('program_id', $newProgramId)
            ->whereIn('semester_id', $semesterIds)
            ->where('status', 1)
            ->get();

        foreach ($configured as $config) {
            $already = $existing->first(function ($fee) use ($config) {
                return (int) $fee->category_id === (int) $config->fees_category_id
                    && (int) optional($fee->studentEnroll)->semester_id === (int) $config->semester_id;
            });

            if ($already) {
                continue;
            }

            $missing->push([
                'fee' => null,
                'config' => $config,
                'category' => optional($config->feesCategory)->title,
                'semester_id' => $config->semester_id,
                'current' => 0.0,
                'new' => round((float) $config->amount, 2),
                'action' => 'add',
                'why' => __('The new programme charges this and the student has no such fee, so it is raised.'),
                'untouched' => true,
            ]);
        }

        return $missing;
    }

    protected function line(Fee $fee, float $current, float $new, string $action, string $why, bool $untouched): array
    {
        return [
            'fee' => $fee,
            'config' => null,
            'category' => optional($fee->category)->title,
            'semester_id' => optional($fee->studentEnroll)->semester_id,
            'current' => $current,
            'new' => $new,
            'action' => $action,
            'why' => $why,
            'untouched' => $untouched,
        ];
    }

    /**
     * Which registered courses survive the move, and which do not.
     *
     * Every one of the 2,037 registered course rows currently matches its own
     * programme's subject list. A move that broke that would be the first.
     */
    public function coursePlan(StudentEnroll $enrolment, int $newProgramId): array
    {
        $registered = $enrolment->subjects()->get();

        $taught = DB::table('program_subject')
            ->where('program_id', $newProgramId)
            ->pluck('subject_id')
            ->all();

        $kept = collect();
        $orphans = collect();

        foreach ($registered as $subject) {
            $entry = [
                'id' => $subject->id,
                'code' => $subject->code,
                'title' => $subject->title,
                'has_marks' => DB::table('subject_markings')
                        ->where('student_enroll_id', $enrolment->id)
                        ->where('subject_id', $subject->id)
                        ->exists()
                    || DB::table('exams')
                        ->where('student_enroll_id', $enrolment->id)
                        ->where('subject_id', $subject->id)
                        ->whereNotNull('achieve_marks')
                        ->exists(),
            ];

            if (in_array($subject->id, $taught)) {
                $kept->push($entry);
            } else {
                $orphans->push($entry);
            }
        }

        return ['kept' => $kept, 'orphans' => $orphans];
    }

    /**
     * The matricule the student would be given.
     *
     * Generated once and carried through to the save, because the preview and
     * the save used to call the generator independently - and it is a
     * read-then-write over a column with no unique index - so the number shown
     * in the confirmation was not necessarily the number stored.
     */
    public function proposedMatricule(StudentEnroll $enrolment, int $newProgramId): ?string
    {
        $student = Student::find($enrolment->student_id);

        if (!$student) {
            return null;
        }

        try {
            return Student::generateEnrollmentMatricule(
                $student->id,
                $newProgramId,
                $student->batch_id
            );
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Move the student.
     *
     * Refuses unless blockers() is empty. Everything is one transaction: a
     * half-moved student - repointed but with the old programme's fees, or fees
     * corrected but the programme unchanged - is worse than not moving at all.
     */
    public function apply(StudentEnroll $enrolment, int $newProgramId, string $reason, ?int $userId = null): array
    {
        $blockers = $this->blockers($enrolment, $newProgramId);

        if ($blockers !== []) {
            return ['moved' => false, 'blockers' => $blockers, 'fees' => collect(), 'matricule' => null];
        }

        $plan = $this->feePlan($enrolment, $newProgramId);
        $matricule = $this->proposedMatricule($enrolment, $newProgramId);
        $amounts = app(FeeAssignmentAmount::class);
        $applied = collect();

        DB::transaction(function () use ($enrolment, $newProgramId, $reason, $userId, $plan, $matricule, $amounts, &$applied) {
            $student = Student::find($enrolment->student_id);
            $oldProgramId = $enrolment->program_id;

            // The enrolment is repointed, not replaced.
            $enrolment->previous_program_id = $oldProgramId;
            $enrolment->is_program_change = true;
            $enrolment->program_change_reason = $reason;
            $enrolment->program_id = $newProgramId;

            if ($matricule) {
                $enrolment->matricule = $matricule;
            }

            $enrolment->save();

            if ($student) {
                $student->program_id = $newProgramId;

                // The canonical id and the enrolment's matricule used to
                // diverge for ever after a change, because only the enrolment
                // was reissued.
                if ($matricule) {
                    $student->student_id = $matricule;
                }

                $student->save();

                // The application keeps naming the old programme otherwise,
                // which ProgrammeDoctor then has to repair.
                if ($student->registration_no) {
                    DB::table('applications')
                        ->where('registration_no', $student->registration_no)
                        ->update(['program_id' => $newProgramId]);
                }
            }

            foreach ($plan as $line) {
                $note = $reason . ' ' . __('(programme change)');

                if ($line['action'] === 'add' && $line['config']) {
                    $applied->push($this->raiseMissingFee($enrolment, $line, $note, $userId));

                    continue;
                }

                if (!in_array($line['action'], ['raise', 'replace'], true)) {
                    continue; // keep / same / not_charged write nothing
                }

                $result = $amounts->apply($line['fee'], $line['new'], $note, $userId);

                $applied->push([
                    'fee_id' => $line['fee']->id,
                    'action' => $line['action'],
                    'from' => $line['current'],
                    'to' => $line['new'],
                    'changed' => $result['changed'] ?? false,
                    'reasons' => $result['reasons'] ?? [],
                ]);
            }
        });

        return [
            'moved' => true,
            'blockers' => [],
            'fees' => $applied,
            'matricule' => $matricule,
        ];
    }

    /**
     * Raise a fee the new programme charges and the student did not have.
     *
     * Deliberately not autoAssignProgramSemesterFees(): its existing-fee check
     * is scoped by programme, so straight after a move it cannot see the old
     * programme's charge for the same semester and would raise a second one.
     */
    protected function raiseMissingFee(StudentEnroll $enrolment, array $line, string $note, ?int $userId): array
    {
        $config = $line['config'];

        $fine = 0;

        if ($config->fine_amount && $config->fine_type) {
            $fine = $config->fine_type === 'fixed'
                ? (float) $config->fine_amount
                : round(((float) $config->amount * (float) $config->fine_amount) / 100, 2);
        }

        $fee = Fee::create([
            'student_enroll_id' => $enrolment->id,
            'category_id' => $config->fees_category_id,
            'fee_amount' => $config->amount,
            'fine_amount' => $fine,
            'assign_date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'note' => __('Raised on programme change. ') . $note,
            'status' => 0,
            'created_by' => $userId,
        ]);

        return [
            'fee_id' => $fee->id,
            'action' => 'add',
            'from' => 0.0,
            'to' => round((float) $config->amount, 2),
            'changed' => true,
            'reasons' => [],
        ];
    }
}
