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
        'origin_exams' => DB::table('exams')->where('student_enroll_id', $origin->id)->count(),
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
        // Raising a sitting DOES write marks and exam rows now: it inherits
        // the attendance and the CA already earned, which is the whole point
        // of the sitting. What must still hold is that none of it lands
        // anywhere but on the new row — above all not on the original, which
        // carries the fail and has to keep carrying it.
        $markDelta = DB::table('subject_markings')->count() - $before['marks'];
        $marksOnSitting = DB::table('subject_markings')
            ->where('student_enroll_id', $new->id)->count();
        check('every marking written belongs to the new sitting',
            $markDelta === $marksOnSitting,
            sprintf('%d written, %d of them on the sitting', $markDelta, $marksOnSitting));

        $examDelta = DB::table('exams')->count() - $before['exams'];
        $examsOnSitting = DB::table('exams')->where('student_enroll_id', $new->id)->count();
        check('every exam row written belongs to the new sitting',
            $examDelta === $examsOnSitting,
            sprintf('%d written, %d of them on the sitting', $examDelta, $examsOnSitting));

        check("and the original enrolment's own exam rows are untouched",
            DB::table('exams')->where('student_enroll_id', $origin->id)->count()
                === $before['origin_exams'],
            'the fail must stay where it was recorded');

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

        // updateOrInsert, not insert: raising the sitting inherits a DRAFT
        // marking for each course, and that is the row the marking screen
        // fills in. Inserting a second one beside it would be testing a shape
        // the application never produces.
        DB::table('subject_markings')->updateOrInsert(
            ['student_enroll_id' => $new->id, 'subject_id' => $subjectId],
            [
                'exam_marks' => 55,
                'total_marks' => 55,
                'validated' => 1,
                'status' => 1,
                'workflow_state' => 'published',
                'updated_at' => now(),
            ]
        );

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
section('The sitting inherits what it shadows');

/**
 * A resit sitting does not start blank. It carries over, from the semester the
 * resit semester shadows, the attendance records and the continuous-assessment
 * marks already earned - the student is re-sitting the FINAL, not the course.
 *
 * The student-initiated routes have always done this. The admin route created
 * the enrolment and stopped, which had two consequences, and the second is the
 * one that made it visible: with no CA exam row at all the student was absent
 * from the CA marking screen entirely, and the only thing that brings a row
 * into existence is saving exam attendance - which continuous assessment does
 * not otherwise require.
 *
 * Every expectation below is read from the parent enrolment with its own
 * query, never from the service under test.
 */

$inheritCase = $work->first(fn ($i) => $i['resit_semester'] && !$i['existing_enrolment']);

check('there is a case to raise a fresh sitting for', $inheritCase !== null);

if ($inheritCase) {
    $origin = $inheritCase['origin'];
    $resitSemester = $inheritCase['resit_semester'];

    // The semester the resit shadows, and the student's enrolment in it.
    $parentEnrolment = StudentEnroll::where('student_id', $origin->student_id)
        ->where('program_id', $origin->program_id)
        ->where('semester_id', $resitSemester->parent_semester_id)
        ->first();

    check('the resit semester names the semester it shadows',
        $resitSemester->parent_semester_id !== null);
    check('and the student has an enrolment there to inherit from',
        $parentEnrolment !== null);

    if ($parentEnrolment) {
        $courseIds = collect($inheritCase['courses'])->pluck('subject_id')
            ->map(fn ($id) => (int) $id)->values()->all();

        // What the parent holds, worked out independently.
        $parentCa = DB::table('exams')
            ->join('exam_types', 'exam_types.id', '=', 'exams.exam_type_id')
            ->where('exams.student_enroll_id', $parentEnrolment->id)
            ->where('exam_types.is_final', 0)
            ->whereIn('exams.subject_id', $courseIds)
            ->pluck('exams.achieve_marks', 'exams.subject_id')->all();

        $parentAttendance = DB::table('student_attendances')
            ->where('student_enroll_id', $parentEnrolment->id)
            ->whereIn('subject_id', $courseIds)
            ->count();

        DB::beginTransaction();

        try {
            $result = $backfill->apply($origin, null, $actor);
            $sitting = $result['enrolment'];

            $counts = function () use ($sitting) {
                return [
                    'exams' => DB::table('exams')
                        ->where('student_enroll_id', $sitting->id)->count(),
                    'attendance' => DB::table('student_attendances')
                        ->where('student_enroll_id', $sitting->id)->count(),
                    'marking' => DB::table('subject_markings')
                        ->where('student_enroll_id', $sitting->id)->count(),
                ];
            };

            // -- the continuous assessment came across -------------------
            $sittingCa = DB::table('exams')
                ->join('exam_types', 'exam_types.id', '=', 'exams.exam_type_id')
                ->where('exams.student_enroll_id', $sitting->id)
                ->where('exam_types.is_final', 0)
                ->pluck('exams.achieve_marks', 'exams.subject_id')->all();

            check('every course on the sitting has its CA exam row',
                count($sittingCa) === count($parentCa),
                sprintf('%d rows for %d the parent holds', count($sittingCa), count($parentCa)));

            $wrongMark = [];
            foreach ($parentCa as $subjectId => $mark) {
                if (!array_key_exists($subjectId, $sittingCa)
                    || (string) $sittingCa[$subjectId] !== (string) $mark) {
                    $wrongMark[] = $subjectId;
                }
            }
            check('and carries the mark the student already earned, unchanged',
                $wrongMark === [], 'subjects ' . implode(',', $wrongMark));

            // Without this the row exists but the marking roll still hides it.
            $notPresent = DB::table('exams')
                ->where('student_enroll_id', $sitting->id)
                ->where('attendance', '!=', 1)->count();
            check('each inherited row is marked present, so the roll shows it',
                $notPresent === 0, $notPresent . ' row(s) not present');

            // -- the final is what they are re-sitting -------------------
            $finals = DB::table('exams')
                ->join('exam_types', 'exam_types.id', '=', 'exams.exam_type_id')
                ->where('exams.student_enroll_id', $sitting->id)
                ->where('exam_types.is_final', 1)->count();
            check('no final-exam mark is inherited, that being the paper re-sat',
                $finals === 0, $finals . ' final row(s) copied');

            // -- attendance and the marking row --------------------------
            $sittingAttendance = DB::table('student_attendances')
                ->where('student_enroll_id', $sitting->id)->count();
            check('the attendance record is carried over too',
                $sittingAttendance === $parentAttendance,
                sprintf('%d copied, parent holds %d', $sittingAttendance, $parentAttendance));

            check('a subject marking exists for each course, so the mark has somewhere to go',
                DB::table('subject_markings')->where('student_enroll_id', $sitting->id)->count()
                    === count($parentCa));

            // -- CA must not require exam attendance ---------------------
            // The symptom that exposed all of this: ensureCaExamRows is what
            // puts a student on the CA roll without attendance, and it only
            // ever looked at status 1 - while a sitting is filed as 2.
            // Scoped to the method, not the file: the same [1, 2] appears
            // elsewhere in this controller, so a file-wide search would go on
            // passing with ensureCaExamRows narrowed straight back.
            $controllerSource = file_get_contents(
                $base . '/app/Http/Controllers/Admin/ExamMarkingController.php');
            $netStart = strpos($controllerSource, 'function ensureCaExamRows');
            $net = $netStart === false
                ? ''
                : substr($controllerSource, $netStart, 1200);

            check('the CA safety net admits the status a sitting is filed under',
                $net !== '' && str_contains($net, "whereIn('status', [1, 2])"),
                'ensureCaExamRows filters a single status again');
            check('and does not filter enrolments to active-only',
                $net !== '' && !str_contains($net, "StudentEnroll::where('status', '1')"),
                'the single-status filter is back');

            // -- running it again changes nothing ------------------------
            $snapshot = $counts();

            $backfill->apply($origin, null, $actor);

            check('raising it a second time inherits nothing twice', $snapshot === $counts());

            // -- and it repairs a sitting raised before it inherited -----
            // The sittings already on this installation were raised without
            // inheritance, so running the screen over a complete one has to
            // put the data there rather than return "nothing to do".
            DB::table('exams')->where('student_enroll_id', $sitting->id)->delete();
            DB::table('student_attendances')->where('student_enroll_id', $sitting->id)->delete();
            DB::table('subject_markings')->where('student_enroll_id', $sitting->id)->delete();

            $repair = $backfill->apply($origin, null, $actor);
            $repaired = $counts();

            check('a sitting with nothing to register still has its data repaired',
                $repaired === $snapshot,
                sprintf('%d exam rows back, %d expected', $repaired['exams'], $snapshot['exams']));
            check('and reports that there was nothing left to register',
                $repair['registered'] === []);
        } finally {
            DB::rollBack();
        }
    }
}
// ---------------------------------------------------------------------------
section('The screen finds sittings that never inherited, and repairs them');

/**
 * The detector behind the panel on the worklist screen, and the button on it.
 *
 * WHAT IT MUST AND MUST NOT REPORT. A sitting is affected only when the
 * semester it shadows holds a CA mark the sitting does not. Two near misses
 * have to stay off the list, because a button that cannot help, or one that
 * overwrites a sitting that is already right, is worse than no button:
 *
 *  - a course the parent never had assessed has nothing to carry over;
 *  - a sitting whose attendance has fallen behind the parent's inherited
 *    correctly and the parent gained days afterwards. One sitting on this
 *    installation has had its CA re-marked since it inherited, so copying the
 *    parent's figures back over it would undo somebody's work.
 *
 * The repair must also do exactly what the panel advertises and no more --
 * it touches only the courses reported as missing.
 */

$audit = app(App\Services\Resit\ResitInheritanceAudit::class);

$affected = $audit->affected();

DB::enableQueryLog();
DB::flushQueryLog();
$audit->affected();
$detectorQueries = count(DB::getQueryLog());
DB::disableQueryLog();

check('it answers in a fixed number of queries, whatever the roll size',
    $detectorQueries <= 20, $detectorQueries . ' queries');

// Every sitting it reports must genuinely be missing a mark the parent holds.
$wronglyReported = [];
foreach ($affected as $row) {
    foreach ($row['missing'] as $course) {
        $onParent = DB::table('exams')
            ->join('exam_types', 'exam_types.id', '=', 'exams.exam_type_id')
            ->where('exams.student_enroll_id', $row['parent_enrolment']->id)
            ->where('exams.subject_id', $course['subject_id'])
            ->where('exam_types.is_final', 0)->exists();
        $onSitting = DB::table('exams')
            ->join('exam_types', 'exam_types.id', '=', 'exams.exam_type_id')
            ->where('exams.student_enroll_id', $row['enrolment']->id)
            ->where('exams.subject_id', $course['subject_id'])
            ->where('exam_types.is_final', 0)->exists();

        if (!$onParent || $onSitting) {
            $wronglyReported[] = $row['enrolment']->id . '/' . $course['subject_code'];
        }
    }
}
check('everything it reports is a mark the parent holds and the sitting does not',
    $wronglyReported === [], implode(', ', $wronglyReported));

// And nothing that IS in that state may be left off it.
$resitSemesterIds = Semester::where('is_resit', 1)->pluck('id')->all();
$missed = [];

foreach (StudentEnroll::whereIn('semester_id', $resitSemesterIds)->with('subjects')->get() as $sitting) {
    $resit = Semester::find($sitting->semester_id);
    $parent = StudentEnroll::where('student_id', $sitting->student_id)
        ->where('program_id', $sitting->program_id)
        ->where('semester_id', $resit->parent_semester_id)
        ->orderByRaw('CASE WHEN status = 0 THEN 1 ELSE 0 END')->orderBy('id')->first();

    if (!$parent) {
        continue;
    }

    foreach ($sitting->subjects as $subject) {
        $onParent = DB::table('exams')
            ->join('exam_types', 'exam_types.id', '=', 'exams.exam_type_id')
            ->where('exams.student_enroll_id', $parent->id)
            ->where('exams.subject_id', $subject->id)
            ->where('exam_types.is_final', 0)->exists();
        $onSitting = DB::table('exams')
            ->join('exam_types', 'exam_types.id', '=', 'exams.exam_type_id')
            ->where('exams.student_enroll_id', $sitting->id)
            ->where('exams.subject_id', $subject->id)
            ->where('exam_types.is_final', 0)->exists();

        if ($onParent && !$onSitting) {
            $reported = $affected->first(fn ($r) => (int) $r['enrolment']->id === (int) $sitting->id
                && collect($r['missing'])->contains('subject_id', (int) $subject->id));

            if (!$reported) {
                $missed[] = $sitting->id . '/' . $subject->id;
            }
        }
    }
}
check('and nothing in that state is left off it',
    $missed === [], implode(', ', $missed));

// A sitting that merely drifted on attendance must NOT be offered a repair.
$driftOnly = StudentEnroll::whereIn('semester_id', $resitSemesterIds)
    ->get()
    ->first(function ($sitting) use ($affected) {
        if ($affected->contains(fn ($r) => (int) $r['enrolment']->id === (int) $sitting->id)) {
            return false;
        }
        $resit = Semester::find($sitting->semester_id);
        $parent = StudentEnroll::where('student_id', $sitting->student_id)
            ->where('program_id', $sitting->program_id)
            ->where('semester_id', $resit->parent_semester_id)->first();
        if (!$parent) {
            return false;
        }

        return DB::table('student_attendances')->where('student_enroll_id', $parent->id)->count()
            > DB::table('student_attendances')->where('student_enroll_id', $sitting->id)->count();
    });

check('a sitting that only drifted on attendance is left alone',
    $driftOnly !== null, 'no such sitting on this installation to check');

// -- the repair itself ---------------------------------------------------
$target = $affected->first();

if ($target) {
    DB::beginTransaction();

    try {
        $sittingId = $target['enrolment']->id;
        $before = $audit->countsFor($sittingId);
        $advertisedCa = count($target['missing']);
        $advertisedAttendance = $target['attendance_missing'];
        $advertisedMarking = $target['marking_missing'];

        // Published marks on the sitting's other courses must survive.
        $publishedBefore = DB::table('subject_markings')
            ->where('student_enroll_id', $sittingId)
            ->where('workflow_state', 'published')
            ->pluck('total_marks', 'subject_id')->all();

        $audit->repair($target);
        $after = $audit->countsFor($sittingId);

        check('the repair restores exactly the CA marks the panel offered',
            $after['ca'] - $before['ca'] === $advertisedCa,
            sprintf('%d restored, %d offered', $after['ca'] - $before['ca'], $advertisedCa));
        check('and exactly the attendance it offered, not every course on the sitting',
            $after['attendance'] - $before['attendance'] === $advertisedAttendance,
            sprintf('%d copied, %d offered', $after['attendance'] - $before['attendance'], $advertisedAttendance));
        check('and exactly the subject markings it offered',
            $after['marking'] - $before['marking'] === $advertisedMarking,
            sprintf('%d created, %d offered', $after['marking'] - $before['marking'], $advertisedMarking));

        $restored = [];
        foreach ($target['missing'] as $course) {
            $mark = DB::table('exams')
                ->join('exam_types', 'exam_types.id', '=', 'exams.exam_type_id')
                ->where('exams.student_enroll_id', $sittingId)
                ->where('exams.subject_id', $course['subject_id'])
                ->where('exam_types.is_final', 0)
                ->first(['exams.achieve_marks', 'exams.attendance']);

            if (!$mark || (string) $mark->achieve_marks !== (string) $course['ca']
                || (int) $mark->attendance !== 1) {
                $restored[] = $course['subject_code'];
            }
        }
        check('each restored mark is the parent\'s own, and marked present',
            $restored === [], implode(', ', $restored));

        $publishedAfter = DB::table('subject_markings')
            ->where('student_enroll_id', $sittingId)
            ->where('workflow_state', 'published')
            ->pluck('total_marks', 'subject_id')->all();
        check('no published mark on the sitting was disturbed',
            $publishedBefore == $publishedAfter);

        check('and the sitting drops off the detector',
            !$audit->affected()->contains(fn ($r) => (int) $r['enrolment']->id === (int) $sittingId));

        // Pressing the button twice is something people do.
        $audit->repair($target);
        check('pressing repair again changes nothing', $after === $audit->countsFor($sittingId));
    } finally {
        DB::rollBack();
    }
}

// -- the button is wired, and guarded ------------------------------------
check('the repair has a route of its own',
    \Illuminate\Support\Facades\Route::has('admin.resit-enrolment.repair-inheritance'));

$repairRoute = collect(\Illuminate\Support\Facades\Route::getRoutes())
    ->first(fn ($r) => $r->getName() === 'admin.resit-enrolment.repair-inheritance');

check('it only answers POST, so no link or crawler can trigger it',
    $repairRoute && in_array('POST', $repairRoute->methods(), true)
        && !in_array('GET', $repairRoute->methods(), true));
check('and it is behind the same permission as raising an enrolment',
    $repairRoute && in_array('permission:student-enroll-resit', $repairRoute->gatherMiddleware(), true));

$indexSource = file_get_contents($base . '/resources/views/admin/resit-enrolment/index.blade.php');
check('the panel posts to that route', str_contains($indexSource, "route(\$route.'.repair-inheritance')"));
check('and its forms carry a CSRF token', substr_count($indexSource, '@csrf') >= 2);

// The controller must repair only what the detector reports, never the id as
// posted: a stale page or a hand-made request would otherwise run an
// inheritance over an enrolment nobody judged to need one.
$controllerSource = file_get_contents(
    $base . '/app/Http/Controllers/Admin/ResitEnrolmentController.php');
check('the repair is driven by the detector, not by the posted id',
    str_contains($controllerSource, '$this->audit->affected(')
        && !str_contains($controllerSource, 'StudentEnroll::findOrFail($request->enrolment_id)'));
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
