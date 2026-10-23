<?php

/**
 * Raising a resit enrolment for a carried-over course.
 *
 * A student who did not validate a course sits it again, and the mark needs an
 * enrolment to live on. It cannot go on the original one — that carries the
 * fail, and the transcript has to go on showing the course was registered in
 * the year it was first taken — so the sitting gets its own enrolment in the
 * resit semester, holding only the carried-over courses.
 *
 * The school had been doing this through the student-request workflow only, so
 * a student who declined, or a cohort settled after a remedial assessment the
 * school ran itself, had no route. This covers the admin-initiated one.
 *
 * WHAT HAS TO HOLD, and why each check is here:
 *
 *  - Nobody is charged. A resit semester is excluded from fee assignment, and
 *    nothing here calls a fee assigner; the check is that the fee table does
 *    not move at all.
 *  - The student is untouched. Their canonical id, their programme, and the
 *    original enrolment's status, registrations and marks all stay as they
 *    were. The new row REUSES the matricule rather than consuming one.
 *  - THE PORTAL DOES NOT MOVE. This is the check that matters most. A resit
 *    sitting is back-dated, so it is the newest row by id and the earliest by
 *    academic position. Every "current enrolment" lookup used MAX(id), so
 *    creating one silently moved the student's whole portal to the resit
 *    semester. Measured before the fix: all 64 Second Semester enrolments.
 *  - Marks can be entered. The marking screens admit status 1 and 2, so the
 *    row is created with 2 — a resit sitting is a completed phase. status 0,
 *    which the older ResitEnrollmentService writes, is excluded by every one
 *    of those screens.
 *  - It is idempotent. These screens get refreshed and double-submitted, and
 *    raising the enrolment is itself what makes the courses look provided for.
 *  - Carry-over stays honest. The course drops off the student's carry-over
 *    list once there is a scheduled request, and comes back if it is cancelled.
 *
 * Writes happen inside transactions that are rolled back.
 */

$base = 'C:/xampp/htdocs/paxhitestbed';
require $base . '/vendor/autoload.php';
$app = require_once $base . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ResitRequest;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentEnroll;
use App\Services\Academic\OutstandingCourses;
use App\Services\Resit\ResitEnrolmentBackfill;
use App\Support\EnrolmentOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;

function section(string $title): void
{
    echo "\n", $title, "\n", str_repeat('-', strlen($title)), "\n";
}

function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;

    if ($ok) {
        $passed++;
        echo "  PASS  $label\n";
    } else {
        $failed++;
        echo "  FAIL  $label", $detail !== '' ? "  ($detail)" : '', "\n";
    }
}

$backfill = app(ResitEnrolmentBackfill::class);
$outstanding = app(OutstandingCourses::class);
$actor = (int) App\User::where('is_admin', 1)->orderBy('id')->firstOrFail()->id;

/**
 * Absolute row counts, taken before anything runs and compared at the end.
 *
 * An earlier version of this suite checked only for resit enrolments created
 * in the last ten minutes, and a row that escaped a rolled-back transaction
 * went unnoticed for an hour — along with two course registrations, a marking
 * row, an exam row, and two resit requests flipped out of "declined". Counting
 * every table outright is the only check that would have caught it.
 */
$tables = [
    'student_enrolls', 'student_enroll_subject', 'subject_markings',
    'exams', 'resit_requests', 'fees', 'students',
];

$opening = [];

foreach ($tables as $table) {
    $opening[$table] = DB::table($table)->count();
}

$openingRequestStates = DB::table('resit_requests')
    ->select('workflow_state', DB::raw('count(*) n'))
    ->groupBy('workflow_state')->pluck('n', 'workflow_state')->all();

// ---------------------------------------------------------------------------
section('The resit semesters are configured to be enrolled into');

$resitSemesters = Semester::where('is_resit', 1)->where('status', 1)->get();

check('there is at least one active resit semester', $resitSemesters->isNotEmpty(),
    $resitSemesters->count() . ' found');
check('each one declares the semester it shadows',
    $resitSemesters->every(fn ($s) => (bool) $s->parent_semester_id),
    $resitSemesters->filter(fn ($s) => !$s->parent_semester_id)->pluck('title')->implode(', '));
check('and none of them has a fee structure',
    DB::table('program_semester_fees')->whereIn('semester_id', $resitSemesters->pluck('id'))->count() === 0,
    'a resit semester with fees configured would charge for a resit');

// ---------------------------------------------------------------------------
section('The worklist offers only what is genuinely missing');

$work = collect();

foreach (Student::where('status', '1')->get() as $candidate) {
    foreach ($backfill->worklistFor($candidate) as $item) {
        $work->push($item + ['student' => $candidate]);
    }
}

check('somebody is owed a resit enrolment', $work->isNotEmpty(), $work->count() . ' rows');

$overReported = [];
$closedOrigin = [];
$resitOrigin = [];

foreach ($work as $item) {
    $existing = $item['existing_enrolment'];
    $have = $existing
        ? DB::table('student_enroll_subject')->where('student_enroll_id', $existing->id)->pluck('subject_id')->all()
        : [];

    foreach ($item['courses'] as $course) {
        if (in_array((int) $course['subject_id'], array_map('intval', $have), true)) {
            $overReported[] = $item['student']->student_id . ' / ' . $course['subject_code'];
        }
    }

    if ((string) $item['origin']->status === '0') {
        $closedOrigin[] = $item['student']->student_id;
    }

    if (optional($item['origin']->semester)->is_resit) {
        $resitOrigin[] = $item['student']->student_id;
    }
}

check('it never offers a course already on the resit enrolment', $overReported === [],
    implode(', ', array_slice($overReported, 0, 4)));
check('it never offers a resit on a closed enrolment', $closedOrigin === [],
    'a closed row is a programme the student has left: ' . implode(', ', array_slice($closedOrigin, 0, 4)));
check('it never offers a resit of a resit', $resitOrigin === [],
    implode(', ', array_slice($resitOrigin, 0, 4)));

// Every course offered must actually be unvalidated, per the one service that
// decides that question for the whole system.
$notOwed = [];

foreach ($work->take(25) as $item) {
    $owed = $outstanding->forEnrollment($item['origin'])->keys()->map(fn ($k) => (int) $k)->all();

    foreach ($item['courses'] as $course) {
        if (!in_array((int) $course['subject_id'], $owed, true)) {
            $notOwed[] = $item['student']->student_id . ' / ' . $course['subject_code'];
        }
    }
}

check('every course it offers is one OutstandingCourses says is owed', $notOwed === [],
    implode(', ', array_slice($notOwed, 0, 4)));

// ---------------------------------------------------------------------------
section('Raising one creates the right thing, and only that');

$subject = $work->first(fn ($i) => $i['resit_semester'] && !$i['existing_enrolment']);

check('there is a case with no existing resit enrolment to use', $subject !== null);

if ($subject) {
    $origin = $subject['origin'];
    $student = $subject['student'];

    $before = [
        'enrolments' => StudentEnroll::count(),
        'fees' => DB::table('fees')->count(),
        'fee_total' => round((float) DB::table('fees')->sum('fee_amount'), 2),
        'marks' => DB::table('subject_markings')->count(),
        'exams' => DB::table('exams')->count(),
        'student_id' => $student->student_id,
        'program_id' => $student->program_id,
        'origin_status' => (string) $origin->status,
        'origin_subjects' => $origin->subjects()->count(),
        'origin_marks' => DB::table('subject_markings')->where('student_enroll_id', $origin->id)->count(),
        'portal' => optional(EnrolmentOrder::furthest(
            StudentEnroll::with(['semester', 'session'])->where('student_id', $student->id)->get()
        ))->id,
    ];

    DB::beginTransaction();

    try {
        $result = $backfill->apply($origin, null, $actor);
        $new = $result['enrolment'];

        check('an enrolment was created', $new !== null && $new->exists);
        check('exactly one', StudentEnroll::count() === $before['enrolments'] + 1,
            sprintf('%d -> %d', $before['enrolments'], StudentEnroll::count()));
        check('in the resit semester',
            (int) $new->semester_id === (int) $subject['resit_semester']->id);
        check('marked completed, so the marking screens admit it',
            (int) $new->status === ResitEnrolmentBackfill::ENROLMENT_STATUS);
        check('and those screens really do admit it',
            StudentEnroll::whereIn('status', [1, 2])->where('id', $new->id)->exists(),
            'status ' . $new->status . ' is invisible to mark entry');
        check('carrying only the carried-over courses',
            $new->subjects()->count() === count($subject['courses']),
            sprintf('%d registered, %d owed', $new->subjects()->count(), count($subject['courses'])));

        // -- nobody is charged -------------------------------------------
        check('no fee row was created',
            DB::table('fees')->count() === $before['fees'],
            sprintf('%d -> %d', $before['fees'], DB::table('fees')->count()));
        check('and not a franc was charged',
            round((float) DB::table('fees')->sum('fee_amount'), 2) === $before['fee_total'],
            number_format((float) DB::table('fees')->sum('fee_amount'), 2));
        check('the resit enrolment itself carries no fee',
            DB::table('fees')->where('student_enroll_id', $new->id)->count() === 0);

        // -- the student is untouched -------------------------------------
        $student->refresh();
        $origin->refresh();

        check('the matricule is reused, not reissued',
            $new->matricule === $origin->matricule, $new->matricule . ' vs ' . $origin->matricule);
        check('students.student_id is unchanged', $student->student_id === $before['student_id']);
        check('students.program_id is unchanged',
            (int) $student->program_id === (int) $before['program_id']);
        check('the original enrolment keeps its status',
            (string) $origin->status === $before['origin_status']);
        check('and all of its registrations',
            $origin->subjects()->count() === $before['origin_subjects']);
        check('and all of its marks',
            DB::table('subject_markings')->where('student_enroll_id', $origin->id)->count() === $before['origin_marks']);
        check('no mark anywhere was written',
            DB::table('subject_markings')->count() === $before['marks']);
        check('no exam row anywhere was written',
            DB::table('exams')->count() === $before['exams']);

        // -- the portal does not move ------------------------------------
        $portalAfter = optional(EnrolmentOrder::furthest(
            StudentEnroll::with(['semester', 'session'])->where('student_id', $student->id)->get()
        ))->id;

        check('the student\'s portal enrolment does not move',
            $portalAfter === $before['portal'],
            sprintf('#%s -> #%s', $before['portal'], $portalAfter));

        $byMaxId = StudentEnroll::where('student_id', $student->id)->orderByDesc('id')->first();

        check('and MAX(id) would have moved it, which is why this matters',
            (int) $byMaxId->id === (int) $new->id,
            'the new row is not the newest, so this case does not prove anything');

        // -- the requests ------------------------------------------------
        $requests = ResitRequest::where('resit_enroll_id', $new->id)->get();

        check('a resit request records each course',
            $requests->count() === count($subject['courses']),
            sprintf('%d requests, %d courses', $requests->count(), count($subject['courses'])));
        check('each is scheduled', $requests->every(fn ($r) => $r->workflow_state === ResitRequest::STATE_SCHEDULED));
        check('each is waived, so finance is not left with a null',
            $requests->every(fn ($r) => $r->payment_status === ResitRequest::PAYMENT_WAIVED));
        check('and none carries a charge',
            $requests->every(fn ($r) => (float) $r->fee_amount === 0.0));
        check('each points back at the ORIGINAL enrolment, where the debt is owed',
            $requests->every(fn ($r) => (int) $r->student_enroll_id === (int) $origin->id));
        check('and names the resit semester',
            $requests->every(fn ($r) => (int) $r->resit_semester_id === (int) $subject['resit_semester']->id));

        // -- carry-over ---------------------------------------------------
        check('the courses drop off the carry-over list once scheduled',
            $outstanding->carryOvers($origin)
                ->keys()->intersect(collect($subject['courses'])->pluck('subject_id'))->isEmpty());

        // -- idempotent ---------------------------------------------------
        $enrolmentsAfterFirst = StudentEnroll::count();
        $registrationsAfterFirst = DB::table('student_enroll_subject')->count();
        $requestsAfterFirst = ResitRequest::count();

        $again = $backfill->apply($origin, null, $actor);

        check('running it again creates no second enrolment',
            StudentEnroll::count() === $enrolmentsAfterFirst);
        check('and returns the same one', (int) $again['enrolment']->id === (int) $new->id);
        check('with no duplicate registrations',
            DB::table('student_enroll_subject')->count() === $registrationsAfterFirst);
        check('and no duplicate requests', ResitRequest::count() === $requestsAfterFirst);
        check('and it reports that there was nothing to do',
            $again['registered'] === [] && $again['requests'] === 0);
    } finally {
        DB::rollBack();
    }

    check('nothing was left behind by the rollback',
        StudentEnroll::count() === $before['enrolments']
        && DB::table('fees')->count() === $before['fees']
        && DB::table('subject_markings')->count() === $before['marks']);
}

// ---------------------------------------------------------------------------
section('A mark recorded on the sitting stays on the sitting');

if ($subject) {
    $origin = $subject['origin'];

    DB::beginTransaction();

    try {
        $new = $backfill->apply($origin, null, $actor)['enrolment'];
        $course = collect($subject['courses'])->first();
        $subjectId = (int) $course['subject_id'];

        $originMarkBefore = DB::table('subject_markings')
            ->where('student_enroll_id', $origin->id)->where('subject_id', $subjectId)->first();

        DB::table('subject_markings')->insert([
            'student_enroll_id' => $new->id,
            'subject_id' => $subjectId,
            'exam_marks' => 55,
            'total_marks' => 55,
            'validated' => 1,
            'status' => 1,
            'workflow_state' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $onResit = DB::table('subject_markings')
            ->where('student_enroll_id', $new->id)->where('subject_id', $subjectId)->first();
        $onOriginAfter = DB::table('subject_markings')
            ->where('student_enroll_id', $origin->id)->where('subject_id', $subjectId)->first();

        check('the resit mark lands on the resit enrolment',
            $onResit && (float) $onResit->total_marks === 55.0);
        check('and the original mark is untouched',
            $onOriginAfter && $originMarkBefore
            && (float) $onOriginAfter->total_marks === (float) $originMarkBefore->total_marks,
            sprintf('%s -> %s', $originMarkBefore->total_marks ?? '?', $onOriginAfter->total_marks ?? '?'));
        check('so the course is no longer owed at all',
            !$outstanding->forEnrollment($origin)->has($subjectId),
            'a passed resit should validate the course');
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('What it refuses');

$closed = StudentEnroll::where('status', '0')
    ->whereHas('semester', fn ($q) => $q->where('is_resit', 0))->first();

if ($closed) {
    $preview = $backfill->preview($closed);

    check('it refuses a closed enrolment', !$preview['ok']);
    check('and says why', collect($preview['blockers'])->contains(fn ($b) => str_contains($b, 'closed')));
}

$resitRow = StudentEnroll::whereHas('semester', fn ($q) => $q->where('is_resit', 1))->first();

if ($resitRow) {
    $preview = $backfill->preview($resitRow);

    check('it refuses a resit of a resit', !$preview['ok']);
}

// Teeth: break the resit semester's parent link and prove the named check bites.
$firstResit = Semester::where('is_resit', 1)->where('status', 1)->whereNotNull('parent_semester_id')->first();

if ($firstResit && $subject) {
    DB::beginTransaction();

    try {
        $wasParent = $firstResit->parent_semester_id;
        $firstResit->parent_semester_id = null;
        $firstResit->save();

        $preview = $backfill->preview($subject['origin']);

        check('without a parent semester it refuses, rather than silently losing inheritance',
            !$preview['ok']
            && collect($preview['blockers'])->contains(fn ($b) => str_contains($b, 'parent semester')),
            implode(' | ', $preview['blockers']));
    } finally {
        DB::rollBack();
    }

    $firstResit->refresh();

    check('the parent link is back after the rollback',
        (int) $firstResit->parent_semester_id === (int) $wasParent);
}

// ---------------------------------------------------------------------------
section('The enrolment selector is behaviour-preserving');

$groups = 0;
$moved = [];

foreach (StudentEnroll::with(['semester', 'session'])->get()->groupBy('student_id') as $studentId => $rows) {
    foreach ($rows->groupBy('matricule') as $matricule => $group) {
        $groups++;

        if ((int) $group->sortByDesc('id')->first()->id !== (int) EnrolmentOrder::furthest($group)->id) {
            $moved[] = $studentId . '/' . $matricule;
        }
    }
}

check('it picks what MAX(id) picks for every student today', $moved === [],
    sprintf('%d of %d groups move: %s', count($moved), $groups, implode(', ', array_slice($moved, 0, 4))));
check('and there were groups to check', $groups > 0, $groups . ' groups');

// A row with no semester must never be mistaken for the furthest point.
$stub = StudentEnroll::whereNull('semester_id')->first();

if ($stub) {
    $withStub = collect([$stub]);
    $real = StudentEnroll::with(['semester', 'session'])->whereNotNull('semester_id')->first();
    $withStub->push($real);

    check('a placeholder enrolment never outranks a real one',
        (int) EnrolmentOrder::furthest($withStub)->id === (int) $real->id);
}

// The graduation case: nothing active at all must still resolve sensibly.
$multi = StudentEnroll::with(['semester', 'session'])->get()
    ->groupBy('student_id')->first(fn ($g) => $g->whereNotNull('semester_id')->count() > 1);

if ($multi) {
    $allCompleted = $multi->whereNotNull('semester_id')->map(function ($e) {
        $copy = clone $e;
        $copy->status = 2;

        return $copy;
    });

    $pick = EnrolmentOrder::furthest($allCompleted);

    check('a student with nothing active still resolves to their furthest semester',
        $pick !== null && !optional($pick->semester)->is_resit,
        'picked ' . optional($pick->semester)->title);
}

// ---------------------------------------------------------------------------
section('The screens are reachable, and only by the right people');

$admin = App\User::where('is_admin', 1)->orderBy('id')->first();
Auth::guard('web')->logout();
app('session.store')->start();
Auth::guard('web')->login($admin);

$request = Illuminate\Http\Request::create('/admin/student/resit-enrolment', 'GET');
$request->setLaravelSession(app('session.store'));
$response = $kernel->handle($request);

check('an admin can open the worklist', $response->getStatusCode() === 200,
    'status ' . $response->getStatusCode());

$body = $response->getContent();

check('it says plainly that no fee is charged', str_contains($body, 'does not charge a fee'));
check('and that the matricule is not reissued', str_contains($body, 'does not reissue the matricule'));

if ($subject) {
    $request = Illuminate\Http\Request::create('/admin/student/resit-enrolment/' . $subject['origin']->id, 'GET');
    $request->setLaravelSession(app('session.store'));
    $preview = $kernel->handle($request);

    check('and the preview for one student', $preview->getStatusCode() === 200);

    $previewBody = preg_replace('/\s+/', ' ', strip_tags($preview->getContent()));

    check('the preview names the resit semester', str_contains($previewBody, 'Will be sat in'));
    check('and warns when it would override a declined request',
        !str_contains($previewBody, 'declined') || str_contains($previewBody, 'already have a resit request'));
}

$source = file_get_contents($base . '/app/Http/Controllers/Admin/ResitEnrolmentController.php');

check('every route carries its own permission',
    substr_count(file_get_contents($base . '/routes/web.php'), "permission:student-enroll-resit") >= 3);
check('the controller guards itself too',
    str_contains($source, "permission:' . \$this->access"));
check('the permission is seeded',
    DB::table('permissions')->where('name', 'student-enroll-resit')->exists());

// ---------------------------------------------------------------------------
section('Nothing was left behind');

Auth::guard('web')->logout();

foreach ($tables as $table) {
    $now = DB::table($table)->count();

    check(sprintf('%s is exactly as it was found', $table),
        $now === $opening[$table],
        sprintf('%d -> %d (%+d)', $opening[$table], $now, $now - $opening[$table]));
}

// A request's state can move without the row count changing, so the states are
// counted too: raising a resit flips "declined" to "scheduled", and a run that
// leaked would leave a student's recorded decision overwritten.
$closingRequestStates = DB::table('resit_requests')
    ->select('workflow_state', DB::raw('count(*) n'))
    ->groupBy('workflow_state')->pluck('n', 'workflow_state')->all();

check('no resit request changed state',
    $closingRequestStates == $openingRequestStates,
    json_encode(['was' => $openingRequestStates, 'now' => $closingRequestStates]));

echo "\n", str_repeat('=', 64), "\n";
printf("passed %d, failed %d\n", $passed, $failed);

exit($failed > 0 ? 1 : 0);
