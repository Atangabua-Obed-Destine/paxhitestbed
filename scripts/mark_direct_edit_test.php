<?php
/**
 * Correcting a mark in place, with a permission, without unpublishing it first.
 *
 * Writes happen inside transactions that are rolled back; the last section
 * proves the database was left as it was found.
 *
 * The check that matters most is "the figure survives". subject_markings
 * .total_marks is a stored sum that ExamObserver and ExamMarksSyncService
 * recompute from the exam rows, so an edit that wrote the total instead of the
 * components would appear to work and then quietly undo itself the next time
 * anything touched this student's exams. Every other check here is about a
 * correction being deliberate and recorded; that one is about it being real.
 *
 * Run: php scripts/mark_direct_edit_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Exam;
use App\Models\ResitRequest;
use App\Models\SubjectMarking;
use App\Models\SubjectMarkingPublishLog;
use App\Services\Assessment\MarkChangeGuard;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;
$skipped = 0;

function section(string $title): void
{
    echo "\n" . $title . "\n" . str_repeat('-', strlen($title)) . "\n";
}

function check(string $what, bool $ok, string $why = ''): void
{
    global $passed, $failed;

    if ($ok) {
        $passed++;
        echo "  PASS  " . $what . "\n";
    } else {
        $failed++;
        echo "  FAIL  " . $what . ($why ? "\n        " . $why : '') . "\n";
    }
}

function skip(string $what): void
{
    global $skipped;
    $skipped++;
    echo "  SKIP  " . $what . "\n";
}

app('session.store')->start();

$admin = User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();

if (!$admin) {
    echo "no Super Admin to act as\n";
    exit(1);
}

Auth::guard('web')->login($admin);

$post = function (string $path, array $data) use ($kernel) {
    $request = Request::create($path, 'POST', array_merge($data, ['_token' => csrf_token()]));
    $request->setLaravelSession(app('session.store'));

    return $kernel->handle($request);
};

$get = function (string $path) use ($kernel) {
    $request = Request::create($path, 'GET');
    $request->setLaravelSession(app('session.store'));

    return $kernel->handle($request);
};

$guard = app(MarkChangeGuard::class);

// Compared at the end. A rolled-back suite must not add one of these.
$directEditRowsAtStart = SubjectMarkingPublishLog::where('action', 'direct_edit')->count();

// A published marking on an ordinary (non-resit) semester, with exam components.
$marking = SubjectMarking::with(['studentEnroll.semester', 'studentEnroll.student', 'subject'])
    ->where('workflow_state', 'published')
    ->whereHas('studentEnroll.semester', fn ($q) => $q->where('is_resit', 0))
    ->whereHas('subject')
    ->get()
    ->first(function ($candidate) {
        return Exam::where('student_enroll_id', $candidate->student_enroll_id)
            ->where('subject_id', $candidate->subject_id)
            ->where('marks', '>', 0)
            ->exists();
    });

if (!$marking) {
    echo "no published marking with exam components to correct\n";
    exit(1);
}

$components = Exam::where('student_enroll_id', $marking->student_enroll_id)
    ->where('subject_id', $marking->subject_id)
    ->where('marks', '>', 0)
    ->get();

$component = $components->first();
$url = '/admin/exam/subject-marking/' . $marking->id . '/direct-edit';

echo "subject: marking #{$marking->id}, total {$marking->total_marks}, "
    . "component #{$component->id} at {$component->achieve_marks} out of {$component->marks}, "
    . ($component->marks_locked ? 'locked' : 'unlocked') . "\n";

// ---------------------------------------------------------------------------
section('The permission is real, not merely a hidden button');
// ---------------------------------------------------------------------------
check('Super Admin holds subject-marking-direct-edit', $admin->can('subject-marking-direct-edit'));

$plain = User::whereDoesntHave('roles', fn ($q) => $q->where('name', 'Super Admin'))
    ->get()
    ->first(fn ($user) => !$user->can('subject-marking-direct-edit'));

if (!$plain) {
    skip('no user without the permission to try the route as');
} else {
    Auth::guard('web')->login($plain);

    // Rolled back like everything else: this is a real post, and if the
    // middleware is ever missing it must not leave anything behind.
    DB::beginTransaction();

    try {
        $refused = $post($url, ['reason' => 'a user without the permission trying the route directly']);

        check('without the permission the route itself refuses',
            in_array($refused->getStatusCode(), [401, 403], true),
            'status ' . $refused->getStatusCode() . ' - middleware must refuse, not the view hiding the button');

        $details = $get($url);

        check('and so does the modal read',
            in_array($details->getStatusCode(), [401, 403], true),
            'status ' . $details->getStatusCode());
    } finally {
        DB::rollBack();
    }

    Auth::guard('web')->login($admin);
}

// ---------------------------------------------------------------------------
section('A correction lands, past the lock and the publish');
// ---------------------------------------------------------------------------
$before = [
    'total' => (float) $marking->total_marks,
    'state' => $marking->workflow_state,
    'override' => $marking->is_published_override,
    'publish_date' => $marking->publish_date,
    'component' => (float) $component->achieve_marks,
];

// Down by three, so the figure is unambiguous and cannot exceed the maximum.
$target = max(0, $before['component'] - 3);
$expectedTotal = round($before['total'] - ($before['component'] - $target), 2);

DB::beginTransaction();

try {
    $response = $post($url, [
        'reason' => 'the script was re-marked after a remark request',
        'exam_marks' => [$component->id => $target],
    ]);

    $after = SubjectMarking::find($marking->id);
    $afterComponent = Exam::find($component->id);

    check('the correction was accepted', $response->getStatusCode() === 302,
        'status ' . $response->getStatusCode());

    check('the component now holds the corrected figure',
        abs((float) $afterComponent->achieve_marks - $target) < 0.005,
        'component is ' . $afterComponent->achieve_marks . ', expected ' . $target);

    check('the total was recomputed to match',
        abs((float) $after->total_marks - $expectedTotal) < 0.005,
        'total is ' . $after->total_marks . ', independently expected ' . $expectedTotal);

    check('and the total still equals the sum of its parts',
        abs((float) $after->total_marks - round(
            (float) $after->exam_marks + (float) $after->attendances
            + (float) $after->assignments + (float) $after->activities, 2)) < 0.005,
        $after->total_marks . ' against ' . ((float) $after->exam_marks + (float) $after->attendances
            + (float) $after->assignments + (float) $after->activities));

    if (!$component->marks_locked) {
        skip('this component was not locked, so the lock was not stepped over');
    } else {
        check('a locked component was corrected anyway - the point of the permission',
            abs((float) $afterComponent->achieve_marks - $target) < 0.005);
    }

    // -----------------------------------------------------------------------
    section('The figure survives the recompute');
    // -----------------------------------------------------------------------
    // The whole reason the components are edited rather than the total. Touch an
    // exam row so ExamObserver fires and recomputes the stored sum.
    $touched = Exam::find($component->id);
    $touched->updated_by = $admin->id;
    $touched->save();

    $later = SubjectMarking::find($marking->id);

    check('after the observer recomputes, the corrected total is unchanged',
        abs((float) $later->total_marks - (float) $after->total_marks) < 0.005,
        'was ' . $after->total_marks . ', became ' . $later->total_marks
            . ' - a total-only edit would have been reverted here');

    check('and it still equals the sum of its parts',
        abs((float) $later->total_marks - round(
            (float) $later->exam_marks + (float) $later->attendances
            + (float) $later->assignments + (float) $later->activities, 2)) < 0.005);

    // -----------------------------------------------------------------------
    section('Nothing else moved');
    // -----------------------------------------------------------------------
    check('the result is still published', $later->workflow_state === $before['state'],
        $before['state'] . ' became ' . $later->workflow_state);

    check('the publish override was not touched',
        $later->is_published_override === $before['override'],
        var_export($before['override'], true) . ' became ' . var_export($later->is_published_override, true));

    check('the publish date was not touched',
        (string) $later->publish_date === (string) $before['publish_date']);

    $otherTotals = SubjectMarking::where('subject_id', $marking->subject_id)
        ->where('id', '!=', $marking->id)
        ->orderBy('id')
        ->pluck('total_marks', 'id')
        ->toArray();

    check('no other student on this subject changed',
        $otherTotals === SubjectMarking::where('subject_id', $marking->subject_id)
            ->where('id', '!=', $marking->id)
            ->orderBy('id')
            ->pluck('total_marks', 'id')
            ->toArray());

    // -----------------------------------------------------------------------
    section('It is recorded');
    // -----------------------------------------------------------------------
    $log = SubjectMarkingPublishLog::where('subject_marking_id', $marking->id)
        ->where('action', 'direct_edit')
        ->latest('id')
        ->first();

    check('a direct_edit row was written', $log !== null,
        'the history the school reads must show this correction');

    if ($log) {
        check('it keeps the reason given',
            $log->reason === 'the script was re-marked after a remark request',
            'reason stored as: ' . $log->reason);

        check('it names who did it', (int) $log->performed_by === (int) $admin->id);

        check('it records the figure before and after',
            str_contains((string) $log->previous_state, number_format($before['total'], 2))
            && str_contains((string) $log->new_state, number_format($expectedTotal, 2)),
            $log->previous_state . ' -> ' . $log->new_state);
    }

    // 'direct_edit' must be a value the column actually accepts. It was not,
    // once: the enum held only unpublish and republish, so the row stored a
    // blank action on a lax connection and would have thrown after the marks
    // were already committed on a strict one.
    $enum = DB::select('SHOW COLUMNS FROM subject_marking_publish_logs WHERE Field = ?', ['action']);

    check('the action column accepts direct_edit',
        isset($enum[0]) && str_contains($enum[0]->Type, 'direct_edit'),
        isset($enum[0]) ? $enum[0]->Type : 'no action column');

    if ($log) {
        check('and the stored action is not blank', $log->action === 'direct_edit',
            'stored as "' . $log->action . '" - a blank action is invisible to the history');
    }
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('A figure above the maximum is refused');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    $response = $post($url, [
        'reason' => 'trying to award more than the component is out of',
        'exam_marks' => [$component->id => (float) $component->marks + 10],
    ]);

    $after = SubjectMarking::find($marking->id);

    check('the total was not changed',
        abs((float) $after->total_marks - $before['total']) < 0.005,
        'total moved to ' . $after->total_marks);

    check('and the component was not changed',
        abs((float) Exam::find($component->id)->achieve_marks - $before['component']) < 0.005);

    check('and nothing was recorded as a correction',
        !SubjectMarkingPublishLog::where('subject_marking_id', $marking->id)
            ->where('reason', 'trying to award more than the component is out of')
            ->exists());
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('The guard refuses a mark that contradicts a decided resit');
// ---------------------------------------------------------------------------
// There are no completed resits in this database, so the situation is built
// here and rolled back. Without this the guard's blocking path is never run.
DB::beginTransaction();

try {
    $passMark = app(App\Services\Academic\OutstandingCourses::class)->passMark($marking->subject);

    $resit = ResitRequest::create([
        'student_enroll_id' => $marking->student_enroll_id,
        'subject_id' => $marking->subject_id,
        'session_id' => $marking->studentEnroll->session_id ?? 1,
        'fee_amount' => 0,
        'workflow_state' => ResitRequest::STATE_COMPLETED,
        'outcome' => ResitRequest::OUTCOME_FAILED,
        'completed_at' => now(),
    ]);

    $fresh = SubjectMarking::with(['studentEnroll.semester', 'subject'])->find($marking->id);

    $contradicting = $guard->blockers($fresh->studentEnroll, $fresh->subject, $passMark + 10);
    $agreeing = $guard->blockers($fresh->studentEnroll, $fresh->subject, max(0, $passMark - 10));

    check('a pass against a resit recorded as failed is refused', $contradicting !== []);

    check('and the reason names the resit',
        $contradicting !== [] && str_contains($contradicting[0], '#' . $resit->id),
        $contradicting === [] ? 'nothing was refused' : $contradicting[0]);

    check('and names the subject',
        $contradicting !== [] && str_contains($contradicting[0], (string) $marking->subject->title));

    check('a fail against a resit recorded as failed is allowed', $agreeing === [],
        'a resit that agrees with the mark is no reason to refuse a correction');

    // Through the admin screen, where it matters.
    $totalNow = (float) SubjectMarking::find($marking->id)->total_marks;
    $raise = $post($url, [
        'reason' => 'raising the mark above the pass mark after the resit was closed',
        'exam_marks' => [$component->id => (float) $component->marks],
    ]);

    $afterRaise = SubjectMarking::find($marking->id);

    // That post only contradicts the resit if it actually produces a pass.
    $wouldPass = $guard->passes($marking->subject, round(
        $totalNow - (float) $component->achieve_marks + (float) $component->marks, 2));

    if (!$wouldPass) {
        skip('awarding this component in full still does not reach the pass mark');
    } else {
        check('the admin screen refuses it and writes nothing',
            abs((float) $afterRaise->total_marks - $totalNow) < 0.005,
            'total moved to ' . $afterRaise->total_marks . ' despite the decided resit');

        check('and records no correction',
            !SubjectMarkingPublishLog::where('subject_marking_id', $marking->id)
                ->where('reason', 'raising the mark above the pass mark after the resit was closed')
                ->exists());
    }

} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('The lecturer screen refuses it too');
// ---------------------------------------------------------------------------
// The user asked for the same protection on both screens. This needs its own
// subject: the exam-marking screen refuses a LOCKED component on the lock
// alone, so pointed at a locked one these checks would pass with the guard
// removed entirely - which is exactly how they first went wrong.
$lecturerExam = Exam::with(['studentEnroll.semester', 'subject', 'type', 'scriptCode'])
    ->where('marks_locked', 0)
    ->where('marks', '>', 0)
    ->whereNotNull('achieve_marks')
    ->get()
    ->first(function ($exam) {
        return $exam->subject
            && $exam->studentEnroll
            && !optional($exam->studentEnroll->semester)->is_resit
            && App\Services\ResultContributionService::isConfigured($exam->subject_id)
            // The screen also refuses a final-type exam with no script code,
            // which would make the checks below vacuous in the same way.
            && (!($exam->type->is_final ?? false) || $exam->scriptCode)
            && SubjectMarking::where('student_enroll_id', $exam->student_enroll_id)
                ->where('subject_id', $exam->subject_id)->exists()
            // The full award must actually change the mark, or there is no
            // write to refuse.
            && (float) $exam->achieve_marks < (float) $exam->marks;
    });

if (!$lecturerExam) {
    skip('no unlocked exam on a configured subject to drive the lecturer screen with');
} else {
    $lecturerMarking = SubjectMarking::with(['studentEnroll.semester', 'subject'])
        ->where('student_enroll_id', $lecturerExam->student_enroll_id)
        ->where('subject_id', $lecturerExam->subject_id)
        ->first();

    $lecturerPost = [
        'marks' => [$lecturerExam->id => (float) $lecturerExam->marks],
        'subject' => $lecturerExam->subject_id,
        'type' => $lecturerExam->exam_type_id,
    ];

    echo "  lecturer subject: exam #{$lecturerExam->id}, unlocked, at {$lecturerExam->achieve_marks}"
        . " out of {$lecturerExam->marks}, marking #{$lecturerMarking->id} total {$lecturerMarking->total_marks}\n";

    // The positive control. Without this, "nothing was written" below could
    // mean the post never writes anything under any circumstances.
    DB::beginTransaction();

    try {
        $post('/admin/exam/exam-marking', $lecturerPost);

        $wrote = Exam::find($lecturerExam->id);

        check('the lecturer screen does write this mark when nothing refuses it',
            abs((float) $wrote->achieve_marks - (float) $lecturerExam->marks) < 0.005,
            'component stayed at ' . $wrote->achieve_marks . ' - if this post writes nothing, the '
                . 'refusal check below proves nothing either');

        $controlTotal = (float) SubjectMarking::find($lecturerMarking->id)->total_marks;

        check('and the total moved with it',
            abs($controlTotal - (float) $lecturerMarking->total_marks) > 0.005,
            'total stayed at ' . $controlTotal);
    } finally {
        DB::rollBack();
    }

    // Now the same post, with a decided resit that the resulting mark
    // contradicts - whichever way round that happens to be.
    DB::beginTransaction();

    try {
        $wouldTotal = round((float) $lecturerMarking->total_marks
            - (float) $lecturerExam->achieve_marks + (float) $lecturerExam->marks, 2);
        $wouldPass = $guard->passes($lecturerMarking->subject, $wouldTotal);

        $resit = ResitRequest::create([
            'student_enroll_id' => $lecturerExam->student_enroll_id,
            'subject_id' => $lecturerExam->subject_id,
            'session_id' => $lecturerExam->studentEnroll->session_id ?? 1,
            'fee_amount' => 0,
            'workflow_state' => ResitRequest::STATE_COMPLETED,
            // Deliberately the opposite of what the new mark would say.
            'outcome' => $wouldPass ? ResitRequest::OUTCOME_FAILED : ResitRequest::OUTCOME_PASSED,
            'completed_at' => now(),
        ]);

        $response = $post('/admin/exam/exam-marking', $lecturerPost);

        $afterExam = Exam::find($lecturerExam->id);
        $afterMarking = SubjectMarking::find($lecturerMarking->id);

        check('the lecturer screen refuses a mark that contradicts a decided resit',
            abs((float) $afterExam->achieve_marks - (float) $lecturerExam->achieve_marks) < 0.005,
            'component moved from ' . $lecturerExam->achieve_marks . ' to ' . $afterExam->achieve_marks
                . ' despite resit #' . $resit->id . ' recorded as ' . $resit->outcome);

        check('and the total is untouched',
            abs((float) $afterMarking->total_marks - (float) $lecturerMarking->total_marks) < 0.005,
            'total moved to ' . $afterMarking->total_marks);

        check('and it is refused before anything is written, not rolled back after',
            abs((float) $afterMarking->total_marks - (float) $lecturerMarking->total_marks) < 0.005
            && abs((float) $afterExam->achieve_marks - (float) $lecturerExam->achieve_marks) < 0.005,
            'a refusal after the commit could undo nothing');
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('What the guard must not refuse');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    // An open request is the observer's business, not the guard's.
    ResitRequest::create([
        'student_enroll_id' => $marking->student_enroll_id,
        'subject_id' => $marking->subject_id,
        'session_id' => $marking->studentEnroll->session_id ?? 1,
        'fee_amount' => 0,
        'workflow_state' => ResitRequest::STATE_APPROVED,
        'outcome' => ResitRequest::OUTCOME_FAILED,
    ]);

    $fresh = SubjectMarking::with(['studentEnroll.semester', 'subject'])->find($marking->id);
    $passMark = app(App\Services\Academic\OutstandingCourses::class)->passMark($marking->subject);

    check('an approved, undecided resit does not block a correction',
        $guard->blockers($fresh->studentEnroll, $fresh->subject, $passMark + 10) === [],
        'the observer still settles open requests in the ordinary way');
} finally {
    DB::rollBack();
}

$resitMarking = SubjectMarking::with(['studentEnroll.semester', 'subject'])
    ->whereHas('studentEnroll.semester', fn ($q) => $q->where('is_resit', 1))
    ->whereHas('subject')
    ->first();

if (!$resitMarking) {
    skip('no marking on a resit semester to check that marking a resit is allowed');
} else {
    DB::beginTransaction();

    try {
        ResitRequest::create([
            'student_enroll_id' => $resitMarking->student_enroll_id,
            'subject_id' => $resitMarking->subject_id,
            'session_id' => $resitMarking->studentEnroll->session_id ?? 1,
            'fee_amount' => 0,
            'workflow_state' => ResitRequest::STATE_COMPLETED,
            'outcome' => ResitRequest::OUTCOME_FAILED,
            'completed_at' => now(),
        ]);

        $fresh = SubjectMarking::with(['studentEnroll.semester', 'subject'])->find($resitMarking->id);

        check('marking the resit attempt itself is allowed',
            $guard->blockers($fresh->studentEnroll, $fresh->subject, 100) === [],
            'this is how a resit gets decided - refusing it would break resits entirely');
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('The two paths beside this one are closed');
// ---------------------------------------------------------------------------
if (!$plain) {
    skip('no user without the marking permission to try autosave as');
} else {
    Auth::guard('web')->login($plain);

    $autosave = $post('/admin/exam/subject-marking/autosave', [
        'subject_marking_id' => $marking->id,
        'assignments' => 99,
    ]);

    check('autosave refuses a user without the marking permission',
        in_array($autosave->getStatusCode(), [401, 403], true),
        'status ' . $autosave->getStatusCode() . ' - it carried no permission at all before');

    Auth::guard('web')->login($admin);
}

$source = file_get_contents(__DIR__ . '/../app/Http/Controllers/Admin/SubjectMarkingController.php');

check('store() sums the components instead of trusting the posted total',
    !str_contains($source, '$totalMarks = (float) ($request->total_marks[$key] ?? 0);'),
    'a readonly field still posts whatever the browser says it holds');

check('and it reads the component inputs to do so',
    str_contains($source, "\$totalMarks = round(")
    && str_contains($source, "(float) (\$request->exam_marks[\$key] ?? 0)"));

$examSource = file_get_contents(__DIR__ . '/../app/Http/Controllers/Admin/ExamMarkingController.php');
$guardAt = strpos($examSource, 'MarkChangeGuard');
$commitAt = strpos($examSource, 'DB::commit()');

check('the lecturer screen consults the guard before it commits',
    $guardAt !== false && $commitAt !== false && $guardAt < $commitAt,
    'a refusal after the commit could undo nothing and the lecturer was already told it saved');

// ---------------------------------------------------------------------------
section('Nothing was left behind');
// ---------------------------------------------------------------------------
$final = SubjectMarking::find($marking->id);

check('the subject marking is as it was found',
    abs((float) $final->total_marks - $before['total']) < 0.005
    && $final->workflow_state === $before['state'],
    'total ' . $final->total_marks . ' against ' . $before['total']);

check('the component is as it was found',
    abs((float) Exam::find($component->id)->achieve_marks - $before['component']) < 0.005);

// Counted rather than matched on one reason string: a stray row written by a
// post this suite makes for some other purpose would otherwise slip past.
check('no direct_edit row survived the rollbacks',
    SubjectMarkingPublishLog::where('action', 'direct_edit')->count() === $directEditRowsAtStart,
    'started with ' . $directEditRowsAtStart . ', now '
        . SubjectMarkingPublishLog::where('action', 'direct_edit')->count());

check('no resit request survived the rollbacks',
    !ResitRequest::where('subject_id', $marking->subject_id)
        ->where('student_enroll_id', $marking->student_enroll_id)
        ->where('created_at', '>=', now()->subMinutes(10))
        ->exists());

echo "\n----\npassed $passed, failed $failed" . ($skipped ? ", skipped $skipped" : '') . "\n";
