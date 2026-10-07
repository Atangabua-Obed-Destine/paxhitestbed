<?php

namespace App\Console\Commands;

use App\Models\ResitRequest;
use App\Models\StudentEnroll;
use App\Models\SubjectMarking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Close resit requests whose paper was sat and marked long ago.
 *
 * The workflow had no terminal state, so a request stayed 'scheduled' for ever
 * once scheduled — including for resits sat and published months earlier. Every
 * carry-over list read that as "being handled" and hid the course.
 *
 * SubjectMarkingObserver now closes a request as its mark is published, so this
 * is only needed for the backlog. OutstandingCourses works the outcome out from
 * the marks either way, so running this changes no student's carry-overs — it
 * makes the workflow data say what actually happened.
 */
class CloseSatResitsCommand extends Command
{
    protected $signature = 'resits:close-sat
                            {--dry-run : List what would be closed and change nothing}';

    protected $description = 'Close resit requests whose resit has already been sat and marked';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $requests = ResitRequest::whereIn('workflow_state', [
                ResitRequest::STATE_APPROVED,
                ResitRequest::STATE_SCHEDULED,
            ])
            ->with(['studentEnroll.student', 'subject'])
            ->orderBy('id')
            ->get();

        if ($requests->isEmpty()) {
            $this->info('No open resit requests. Nothing to do.');

            return self::SUCCESS;
        }

        $rows = [];
        $toClose = [];
        $stillWaiting = 0;

        foreach ($requests as $request) {
            $sitting = $this->sittingFor($request);

            $mark = $sitting
                ? SubjectMarking::where('student_enroll_id', $sitting->id)
                    ->where('subject_id', $request->subject_id)
                    ->where('workflow_state', SubjectMarking::STATE_PUBLISHED)
                    ->first()
                : null;

            if (!$mark) {
                $stillWaiting++;
                continue;
            }

            $passMark = (float) (optional($request->subject)->passing_marks ?: 50);
            $passed = round((float) $mark->total_marks) >= $passMark;

            $student = optional($request->studentEnroll)->student;

            $rows[] = [
                $request->id,
                $student->student_id ?? '?',
                optional($request->subject)->code ?? '?',
                number_format((float) $mark->total_marks, 2),
                $passed ? 'passed' : 'FAILED',
            ];

            $toClose[] = [
                'request' => $request,
                'passed' => $passed,
                'sitting' => $sitting,
            ];
        }

        if (empty($toClose)) {
            $this->info("Nothing to close. {$stillWaiting} request(s) are genuinely still awaiting their sitting.");

            return self::SUCCESS;
        }

        $this->info(count($toClose) . ' resit request(s) were sat and marked but never closed'
            . ($dryRun ? ' — dry run, nothing will be written:' : ':'));

        $this->table(['request', 'student', 'subject', 'resit mark', 'outcome'], $rows);

        $failed = count(array_filter($toClose, fn ($row) => !$row['passed']));
        $this->line("  of these, {$failed} failed the resit and are carry-overs again.");
        $this->line("  {$stillWaiting} request(s) are genuinely still awaiting their sitting and are left alone.");

        if ($dryRun) {
            $this->comment('Dry run. Run again without --dry-run to close these.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($toClose) {
            foreach ($toClose as $row) {
                $request = $row['request'];
                $request->workflow_state = ResitRequest::STATE_COMPLETED;
                $request->completed_at = $request->completed_at ?? now();
                $request->outcome = $row['passed']
                    ? ResitRequest::OUTCOME_PASSED
                    : ResitRequest::OUTCOME_FAILED;

                if (!$request->resit_enroll_id && $row['sitting']) {
                    $request->resit_enroll_id = $row['sitting']->id;
                }

                $request->save();
            }
        });

        $this->info('Closed ' . count($toClose) . '.');

        return self::SUCCESS;
    }

    /**
     * The enrolment the resit was to be sat under.
     *
     * resit_enroll_id was never populated by the scheduling step, so the
     * semester it was scheduled into is what can actually be relied on.
     */
    protected function sittingFor(ResitRequest $request): ?StudentEnroll
    {
        if ($request->resit_enroll_id) {
            return StudentEnroll::find($request->resit_enroll_id);
        }

        if (!$request->resit_semester_id) {
            return null;
        }

        $student = optional($request->studentEnroll)->student;

        if (!$student) {
            return null;
        }

        return StudentEnroll::where('student_id', $student->id)
            ->where('semester_id', $request->resit_semester_id)
            ->when(optional($request->studentEnroll)->program_id,
                fn ($q, $programId) => $q->where('program_id', $programId))
            ->orderByDesc('id')
            ->first();
    }
}
