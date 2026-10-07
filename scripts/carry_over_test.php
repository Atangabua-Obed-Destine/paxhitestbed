<?php

/**
 * What a student still owes, across every screen that asks.
 *
 * The rule under test: a course is outstanding when no published attempt reaches
 * its pass mark, and it is only kept off the carry-over list while a resit is
 * still awaiting its sitting. A resit that has been sat is spent, whatever its
 * workflow state was left as.
 *
 * Every write happens inside a transaction that is rolled back, so this can be
 * run against real data. Expected figures are worked out here, from the marks,
 * rather than read back from the code under test.
 *
 *   php scripts/carry_over_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Models\ResitRequest;
use App\Models\Student;
use App\Models\StudentEnroll;
use App\Models\SubjectMarking;
use App\Services\Academic\OutstandingCourses;

$passed = 0;
$failed = 0;
$skipped = 0;

function check(string $what, bool $ok, string $detail = ''): void
{
    global $passed, $failed;

    if ($ok) {
        $passed++;
        echo "  PASS  $what\n";
    } else {
        $failed++;
        echo "  FAIL  $what" . ($detail !== '' ? " — $detail" : '') . "\n";
    }
}

function skip(string $why): void
{
    global $skipped;
    $skipped++;
    echo "  SKIP  $why\n";
}

function section(string $title): void
{
    echo "\n" . $title . "\n";
}

$outstanding = app(OutstandingCourses::class);

/**
 * The truth, from the marks alone — no workflow states consulted.
 *
 * @return array<int, float> subject id => best failing mark
 */
function owedFromMarks(Student $student, int $programId): array
{
    $validated = [];
    $best = [];

    foreach ($student->enrolls()->where('program_id', $programId)
        ->with('subjectMarks.subject')->get() as $enrollment) {
        foreach ($enrollment->subjectMarks ?? [] as $mark) {
            if (!$mark->subject || $mark->workflow_state !== SubjectMarking::STATE_PUBLISHED) {
                continue;
            }

            $marks = round((float) $mark->total_marks);
            $pass = (float) ($mark->subject->passing_marks ?: 50);

            if ($marks >= $pass) {
                $validated[$mark->subject_id] = true;
            } else {
                $best[$mark->subject_id] = max($best[$mark->subject_id] ?? 0, $marks);
            }
        }
    }

    return array_diff_key($best, $validated);
}

// ---------------------------------------------------------------------------
section('The case this was built for: a resit sat and failed');
// ---------------------------------------------------------------------------
$subject = Student::where('student_id', 'PAX25CEH022')->first();

if (!$subject) {
    skip('PAX25CEH022 is not in this database');
} else {
    $enrollment = $subject->currentEnroll;
    $owed = owedFromMarks($subject, (int) $enrollment->program_id);
    $rows = $outstanding->forEnrollment($enrollment);

    check('he owes three courses, not two',
        $rows->count() === 3, $rows->count() . ': ' . $rows->pluck('subject_code')->implode(', '));
    check('and they are the three the marks say',
        $rows->keys()->sort()->values()->all() === collect(array_keys($owed))->sort()->values()->all(),
        $rows->pluck('subject_code')->implode(', '));

    $gecc = $rows->first(fn ($row) => $row['subject_code'] === 'GECC101');
    check('the course he failed on resit is one of them', $gecc !== null);
    check('carrying the better of its two attempts',
        $gecc && (int) $gecc['best_marks'] === 35, $gecc ? 'best ' . $gecc['best_marks'] : '');
    check('and counted as two attempts',
        $gecc && $gecc['attempts'] === 2, $gecc ? $gecc['attempts'] . ' attempts' : '');
    check('the course he passed on resit is not',
        !$rows->contains(fn ($row) => $row['subject_code'] === 'SWE112'));
    check('none of them is treated as awaiting a resit',
        $rows->every(fn ($row) => !$row['awaiting_resit']));
    check('so all three are carry-overs',
        $outstanding->carryOvers($enrollment)->count() === 3);
}

// ---------------------------------------------------------------------------
section('A resit still to be sat keeps its course off the list');
// ---------------------------------------------------------------------------
if (!$subject) {
    skip('no student to work with');
} else {
    DB::beginTransaction();

    try {
        $enrollment = $subject->currentEnroll;
        $gecc = $outstanding->forEnrollment($enrollment)
            ->first(fn ($row) => $row['subject_code'] === 'GECC101');

        // Put the sitting back in front of him: unpublish the resit mark, so the
        // scheduled request is genuinely still awaiting its paper.
        $resitMark = SubjectMarking::whereIn('student_enroll_id',
                $subject->enrolls()->whereHas('semester', fn ($q) => $q->where('is_resit', 1))->pluck('id'))
            ->where('subject_id', $gecc['subject_id'])
            ->where('workflow_state', SubjectMarking::STATE_PUBLISHED)
            ->first();

        if (!$resitMark) {
            skip('his resit mark is not where this section expects it');
        } else {
            $resitMark->workflow_state = SubjectMarking::STATE_APPROVED;
            $resitMark->saveQuietly();

            $rows = $outstanding->forEnrollment($subject->fresh()->currentEnroll);
            $row = $rows->first(fn ($r) => $r['subject_code'] === 'GECC101');

            check('the course is still owed', $row !== null);
            check('but is marked as awaiting its resit', $row && $row['awaiting_resit']);
            check('so it is not offered as a carry-over',
                !$outstanding->carryOvers($subject->fresh()->currentEnroll)
                    ->contains(fn ($r) => $r['subject_code'] === 'GECC101'));

            // Publishing the mark again settles it, and it comes back.
            $resitMark->workflow_state = SubjectMarking::STATE_PUBLISHED;
            $resitMark->saveQuietly();

            check('publishing the resit mark puts it back on the carry-over list',
                $outstanding->carryOvers($subject->fresh()->currentEnroll)
                    ->contains(fn ($r) => $r['subject_code'] === 'GECC101'));
        }
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('A declined or cancelled resit never hides a course');
// ---------------------------------------------------------------------------
if (!$subject) {
    skip('no student to work with');
} else {
    $declined = ResitRequest::whereIn('student_enroll_id', $subject->enrolls()->pluck('id'))
        ->whereIn('workflow_state', [ResitRequest::STATE_DECLINED, ResitRequest::STATE_CANCELLED])
        ->pluck('subject_id');

    if ($declined->isEmpty()) {
        skip('he has no declined or cancelled resit requests');
    } else {
        $rows = $outstanding->forEnrollment($subject->currentEnroll);
        $owed = owedFromMarks($subject, (int) $subject->currentEnroll->program_id);

        $hidden = $declined->filter(fn ($id) => isset($owed[$id]) && !$rows->has($id));

        check('every declined course he still owes is listed',
            $hidden->isEmpty(), 'hidden: ' . $hidden->implode(', '));
    }
}

// ---------------------------------------------------------------------------
section('A subject sets its own pass mark');
// ---------------------------------------------------------------------------
// Every subject in this database leaves passing_marks null and so falls back to
// 50. The rule still has to honour one that sets its own, and that can only be
// shown by making one — inside a transaction that is rolled back.
DB::beginTransaction();

try {
    $borderline = null;

    foreach (SubjectMarking::with(['subject', 'studentEnroll.student'])
        ->where('workflow_state', SubjectMarking::STATE_PUBLISHED)
        ->whereBetween('total_marks', [50, 65])->limit(200)->get() as $mark) {
        if ($mark->subject && optional($mark->studentEnroll)->student && $mark->studentEnroll->program_id) {
            $borderline = $mark;
            break;
        }
    }

    if (!$borderline) {
        skip('no published mark between 50 and 65 to raise a pass mark above');
    } else {
        $student = $borderline->studentEnroll->student;
        $programId = (int) $borderline->studentEnroll->program_id;
        $subjectId = $borderline->subject_id;

        $wasListed = app(OutstandingCourses::class)->forStudent($student, $programId)->has($subjectId);

        // Raise the bar above what they scored.
        $borderline->subject->passing_marks = round((float) $borderline->total_marks) + 5;
        $borderline->subject->save();

        $nowListed = app(OutstandingCourses::class)->forStudent($student, $programId)->has($subjectId);

        check('a mark that clears 50 but not the subject\'s own pass mark is owed',
            !$wasListed && $nowListed,
            'listed before: ' . var_export($wasListed, true) . ', after: ' . var_export($nowListed, true));

        // And back down below it.
        $borderline->subject->passing_marks = 10;
        $borderline->subject->save();

        check('and is not owed once the pass mark is below it',
            !app(OutstandingCourses::class)->forStudent($student, $programId)->has($subjectId));
    }
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('The observer closes a request when the resit is marked');
// ---------------------------------------------------------------------------
/** A scheduled request whose resit was sat, where the mark passed or failed. */
$openRequestWhere = function (bool $wantPassed) {
    return ResitRequest::where('workflow_state', ResitRequest::STATE_SCHEDULED)
        ->whereNotNull('resit_semester_id')
        ->with('studentEnroll.student', 'subject')
        ->get()
        ->first(function (ResitRequest $request) use ($wantPassed) {
            $student = optional($request->studentEnroll)->student;
            if (!$student) {
                return false;
            }

            $sitting = StudentEnroll::where('student_id', $student->id)
                ->where('semester_id', $request->resit_semester_id)->first();

            if (!$sitting) {
                return false;
            }

            $mark = SubjectMarking::where('student_enroll_id', $sitting->id)
                ->where('subject_id', $request->subject_id)
                ->where('workflow_state', SubjectMarking::STATE_PUBLISHED)->first();

            if (!$mark) {
                return false;
            }

            $pass = (float) (optional($request->subject)->passing_marks ?: 50);

            return (round((float) $mark->total_marks) >= $pass) === $wantPassed;
        });
};

foreach ([['a resit that was passed', true, ResitRequest::OUTCOME_PASSED],
          ['a resit that was failed', false, ResitRequest::OUTCOME_FAILED]] as [$label, $wantPassed, $expected]) {
    DB::beginTransaction();

    try {
        $open = $openRequestWhere($wantPassed);

        if (!$open) {
            skip("no scheduled request for {$label}");
        } else {
            $student = $open->studentEnroll->student;
            $sitting = StudentEnroll::where('student_id', $student->id)
                ->where('semester_id', $open->resit_semester_id)->first();
            $mark = SubjectMarking::where('student_enroll_id', $sitting->id)
                ->where('subject_id', $open->subject_id)->first();

            // Re-publish the mark the way a publishing screen does.
            $mark->workflow_state = SubjectMarking::STATE_APPROVED;
            $mark->saveQuietly();
            $mark->workflow_state = SubjectMarking::STATE_PUBLISHED;
            $mark->save();

            $open->refresh();

            check("{$label}: the request is closed",
                $open->workflow_state === ResitRequest::STATE_COMPLETED, $open->workflow_state);
            check("{$label}: recorded as {$expected}",
                $open->outcome === $expected, $open->outcome . ' for a mark of ' . $mark->total_marks);
            check("{$label}: the enrolment it was sat under is recorded",
                (int) $open->resit_enroll_id === (int) $sitting->id);
        }
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('Closing the stale requests changes nobody\'s carry-overs');
// ---------------------------------------------------------------------------
$sample = Student::whereHas('enrolls')->orderBy('id')->limit(40)->get();
$before = [];

foreach ($sample as $one) {
    $enrollment = $one->currentEnroll;
    if (!$enrollment || !$enrollment->program_id) {
        continue;
    }
    $before[$one->id] = app(OutstandingCourses::class)->carryOvers($enrollment)->keys()->sort()->values()->all();
}

if (empty($before)) {
    skip('no students with current enrolments to sample');
} else {
    DB::beginTransaction();

    try {
        \Illuminate\Support\Facades\Artisan::call('resits:close-sat');

        $changed = [];
        foreach ($sample as $one) {
            $enrollment = $one->currentEnroll;
            if (!$enrollment || !$enrollment->program_id || !isset($before[$one->id])) {
                continue;
            }
            $after = app(OutstandingCourses::class)->carryOvers($enrollment->fresh())->keys()->sort()->values()->all();
            if ($after !== $before[$one->id]) {
                $changed[] = $one->student_id;
            }
        }

        check('the backfill moves nobody — the rule already had it right',
            empty($changed), 'changed for: ' . implode(', ', $changed));
        check('and no scheduled request is left with a published mark behind it',
            ResitRequest::where('workflow_state', ResitRequest::STATE_SCHEDULED)->get()
                ->every(function (ResitRequest $request) {
                    $student = optional($request->studentEnroll)->student;
                    if (!$student || !$request->resit_semester_id) {
                        return true;
                    }
                    $sitting = StudentEnroll::where('student_id', $student->id)
                        ->where('semester_id', $request->resit_semester_id)->first();

                    return !$sitting || !SubjectMarking::where('student_enroll_id', $sitting->id)
                        ->where('subject_id', $request->subject_id)
                        ->where('workflow_state', SubjectMarking::STATE_PUBLISHED)->exists();
                }));
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('School-wide: nothing is hidden by a resit that has been sat');
// ---------------------------------------------------------------------------
$hiddenTotal = 0;
$studentsAffected = 0;

foreach (Student::whereHas('enrolls')->get() as $one) {
    $enrollment = $one->currentEnroll;
    if (!$enrollment || !$enrollment->program_id) {
        continue;
    }

    $owed = owedFromMarks($one, (int) $enrollment->program_id);
    $listed = app(OutstandingCourses::class)->forEnrollment($enrollment);

    $missing = array_diff_key($owed, $listed->all());

    if ($missing) {
        $hiddenTotal += count($missing);
        $studentsAffected++;
    }
}

check('every course the marks say is owed appears in the outstanding list',
    $hiddenTotal === 0, "{$hiddenTotal} course(s) hidden across {$studentsAffected} student(s)");

// ---------------------------------------------------------------------------
section('The screens agree with each other');
// ---------------------------------------------------------------------------
$eligibility = app(\App\Services\Student\ProgressionEligibilityService::class);
$method = new ReflectionMethod($eligibility, 'getCarryOverCourses');
$method->setAccessible(true);

$disagreements = [];

foreach ($sample as $one) {
    $enrollment = $one->currentEnroll;
    if (!$enrollment || !$enrollment->program_id) {
        continue;
    }

    $shared = app(OutstandingCourses::class)->carryOvers($enrollment)->keys()->sort()->values()->all();
    $modal = collect($method->invoke($eligibility, $enrollment))->pluck('subject_id')->sort()->values()->all();

    if ($shared !== $modal) {
        $disagreements[] = $one->student_id;
    }
}

check('the progression modal matches the shared rule exactly',
    empty($disagreements), 'differs for: ' . implode(', ', $disagreements));

// Course registration filters to the semester type it registers into, so it is
// asserted as a subset rather than an equal — a divergence in the rule itself
// still fails.
$notSubset = [];
$registration = new \App\Http\Controllers\Student\CourseRegistrationController();
$prepare = new ReflectionMethod($registration, 'prepareCarryOverCourses');
$prepare->setAccessible(true);
$grades = \App\Models\Grade::all();

foreach ($sample as $one) {
    $enrollment = $one->currentEnroll;
    if (!$enrollment || !$enrollment->program_id) {
        continue;
    }

    $shared = app(OutstandingCourses::class)->carryOvers($enrollment)->keys()->all();

    try {
        $rows = $prepare->invoke($registration, $one, $enrollment, $grades);
    } catch (\Throwable $e) {
        continue;
    }

    foreach ($rows as $row) {
        if (!in_array($row['subject']->id, $shared, true)) {
            $notSubset[] = $one->student_id . '/' . $row['subject']->code;
        }
    }
}

check('course registration never offers a course the shared rule does not owe',
    empty($notSubset), implode(', ', array_slice($notSubset, 0, 5)));

echo "\n----\npassed $passed, failed $failed" . ($skipped ? ", skipped $skipped" : '') . "\n";

exit($failed > 0 ? 1 : 0);
