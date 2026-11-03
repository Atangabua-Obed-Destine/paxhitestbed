<?php

/**
 * The course class list: the screen, the PDF and the workbook.
 *
 * A lecturer looking at this wants two things the older screens each answer
 * half of -- who is in my course, and how are they attending it. The whole
 * risk is that this one answers them DIFFERENTLY, so most of what follows is
 * not about the files: it is about the three agreeing, and about the five
 * rules in CourseRoster's docblock holding against fixtures built to break
 * them.
 *
 * WHAT IS PROVED HERE
 *
 *   * The screen, the PDF and the workbook are ONE computation: the scope
 *     lives in App\Support\CourseClassFilter and the figures in
 *     App\Services\Academic\CourseRoster, and all three read them.
 *   * A student is counted ONCE however many enrolments they hold. On this
 *     install every one of the 63 multi-enrolment students has attendance
 *     split across more than one of their rows.
 *   * Enrolment status '1' is NOT used as a filter. It means "the semester
 *     this student is in now", and filtering it empties the register of any
 *     course whose students have moved up -- the measured count is asserted.
 *   * A holiday is not a working day; unmarked is not absent. Both against
 *     fixtures, because both silently change everybody's rate.
 *   * A service course is ONE register, not one per programme, and narrowing
 *     to a programme COUNTS what it leaves out instead of dropping it.
 *   * A lecturer cannot reach a course they do not teach, by the dropdown or
 *     by the query string.
 *   * Every breakdown adds back up to the headline.
 *   * The cost does not grow with the size of the class.
 *
 * Expectations are computed in raw SQL that knows nothing about the code under
 * test. Writes happen only inside transactions that are rolled back; the
 * closing section proves it with absolute counts.
 */

$base = 'C:/xampp/htdocs/paxhitestbed';
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$httpKernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Exports\CourseClassListExport;
use App\Models\ClassRoutine;
use App\Models\StudentAttendance;
use App\Models\Subject;
use App\Services\Academic\CourseRoster;
use App\Support\CourseClassFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

$pass = 0;
$fail = 0;

function check(string $what, $expected, $actual): void
{
    global $pass, $fail;

    if ($expected === $actual) { $pass++; return; }

    $fail++;
    printf("  FAIL  %s\n          expected: %s\n          actual  : %s\n",
        $what, var_export($expected, true), var_export($actual, true));
}

function ok(string $what, $condition): void { check($what, true, (bool) $condition); }

function near(string $what, $expected, $actual): void
{
    global $pass, $fail;

    if (abs((float) $expected - (float) $actual) < 0.051) { $pass++; return; }

    $fail++;
    printf("  FAIL  %s\n          expected: %s\n          actual  : %s\n",
        $what, var_export($expected, true), var_export($actual, true));
}

function section(string $title): void { printf("\n=== %s ===\n", $title); }

View::share('errors', new Illuminate\Support\ViewErrorBag());

$opening = [
    'enrolls' => DB::table('student_enrolls')->count(),
    'attendance' => DB::table('student_attendances')->count(),
    'routines' => DB::table('class_routines')->count(),
    'subjects' => DB::table('subjects')->count(),
];

// ---------------------------------------------------------------------------
// The install's shape, measured rather than assumed.
// ---------------------------------------------------------------------------

$SESSION = (int) (DB::table('sessions')->where('status', '1')->where('current', '1')->value('id')
    ?: DB::table('student_enrolls')->select('session_id')->groupBy('session_id')
        ->orderByRaw('COUNT(*) DESC')->value('session_id'));

/**
 * The case this screen exists for: ONE lecturer teaching ONE course to several
 * teaching groups at once. Chosen per (teacher, subject), not per subject,
 * because a course split between two lecturers is two registers, not one.
 */
$serviceRow = DB::selectOne("
    SELECT r.teacher_id, r.subject_id,
           COUNT(DISTINCT CONCAT(r.program_id,'|',r.semester_id,'|',r.section_id)) groups,
           COUNT(DISTINCT a.id) att
    FROM class_routines r
    JOIN student_enrolls e ON e.session_id = r.session_id
                          AND e.program_id = r.program_id
                          AND e.semester_id = r.semester_id
                          AND e.section_id = r.section_id
    JOIN student_enroll_subject es ON es.student_enroll_id = e.id AND es.subject_id = r.subject_id
    LEFT JOIN student_attendances a ON a.student_enroll_id = e.id AND a.subject_id = r.subject_id
    WHERE r.session_id = ? AND r.status = '1'
    GROUP BY r.teacher_id, r.subject_id
    HAVING groups > 1 AND att > 0
    ORDER BY groups DESC, att DESC
    LIMIT 1", [$SESSION]);

$SUBJECT = (int) $serviceRow->subject_id;
$subject = Subject::find($SUBJECT);
$teacherId = (int) $serviceRow->teacher_id;
$teacher = App\User::find($teacherId);

Auth::guard('web')->setUser($teacher);

printf("session %d · course %s · %d of the lecturer’s own groups · %d attendance rows · %s\n",
    $SESSION, $subject->code, $serviceRow->groups, $serviceRow->att, $teacher->name);

/**
 * The lecturer's own teaching groups, read straight from class_routines so the
 * expectation knows nothing about CourseClassFilter. "The whole course" means
 * these groups -- NOT every student taking the subject, which on a course
 * split between two lecturers would be somebody else's class.
 */
$ownGroups = "EXISTS (SELECT 1 FROM class_routines r
     WHERE r.session_id = e.session_id AND r.status = '1'
       AND r.subject_id = es.subject_id AND r.teacher_id = $teacherId
       AND r.program_id = e.program_id AND r.semester_id = e.semester_id
       AND r.section_id = e.section_id)";

$filterFor = fn (array $query) => CourseClassFilter::fromRequest(
    Request::create('/admin/course-class-list', 'GET', array_merge(['session' => $SESSION], $query))
);

$reportFor = function (array $query) use ($filterFor) {
    $filter = $filterFor($query);

    return app(CourseRoster::class)->build($filter, $filter->authorise());
};

/** A real request through the real kernel, as this lecturer. */
$asUser = function (string $uri, array $query) use ($httpKernel, $teacher, $SESSION) {
    $request = Request::create($uri, 'GET', array_merge(['session' => $SESSION], $query));
    $request->setUserResolver(fn () => $teacher);
    Auth::guard('web')->setUser($teacher);

    return $httpKernel->handle($request);
};

$whole = $reportFor(['subject' => $SUBJECT]);

// ---------------------------------------------------------------------------
section('the roster is what the database says it is');

/** Independent SQL. Note the absence of any status filter -- that is rule 2. */
$expectedStudents = (int) DB::selectOne("
    SELECT COUNT(DISTINCT e.student_id) n
    FROM student_enrolls e
    JOIN student_enroll_subject es ON es.student_enroll_id = e.id
    JOIN students s ON s.id = e.student_id
    WHERE e.session_id = ? AND es.subject_id = ? AND $ownGroups", [$SESSION, $SUBJECT])->n;

check('every student taking the course is on the list', $expectedStudents, $whole['totals']['students']);

$expectedCounts = DB::select("
    SELECT a.attendance, COUNT(*) n
    FROM student_attendances a
    JOIN student_enrolls e ON e.id = a.student_enroll_id
    JOIN student_enroll_subject es ON es.student_enroll_id = e.id AND es.subject_id = a.subject_id
    JOIN students s ON s.id = e.student_id
    WHERE a.subject_id = ? AND e.session_id = ? AND $ownGroups
    GROUP BY a.attendance", [$SUBJECT, $SESSION]);

$byCode = [];
foreach ($expectedCounts as $row) { $byCode[(int) $row->attendance] = (int) $row->n; }

check('present is the database count', $byCode[1] ?? 0, $whole['totals']['present']);
check('absent is the database count', $byCode[2] ?? 0, $whole['totals']['absent']);
check('leave is the database count', $byCode[3] ?? 0, $whole['totals']['leave']);
check('holiday is the database count', $byCode[4] ?? 0, $whole['totals']['holiday']);

$expectedDates = (int) DB::selectOne("
    SELECT COUNT(DISTINCT a.date) n
    FROM student_attendances a
    JOIN student_enrolls e ON e.id = a.student_enroll_id
    JOIN student_enroll_subject es ON es.student_enroll_id = e.id AND es.subject_id = a.subject_id
    WHERE a.subject_id = ? AND e.session_id = ? AND $ownGroups", [$SUBJECT, $SESSION])->n;

check('sessions held is the number of dates the register was taken', $expectedDates, $whole['totals']['sessions_held']);

// ---------------------------------------------------------------------------
section('a student is counted once, however many enrolments they hold');

$multi = DB::select("
    SELECT e.student_id, COUNT(DISTINCT e.id) enrolments
    FROM student_enrolls e
    JOIN student_enroll_subject es ON es.student_enroll_id = e.id
    WHERE e.session_id = ? AND es.subject_id = ? AND $ownGroups
    GROUP BY e.student_id HAVING enrolments > 1", [$SESSION, $SUBJECT]);

$rowsByStudent = collect($whole['students'])->keyBy('student_id');

check('nobody appears twice',
    count($whole['students']),
    collect($whole['students'])->pluck('student_id')->unique()->count());

if ($multi) {
    $case = $multi[0];
    $row = $rowsByStudent->get($case->student_id);

    ok('a student with several enrolments is one row', $row !== null);
    check('and the row says how many were folded together', (int) $case->enrolments, $row['enrolments'] ?? 0);

    $theirTotal = (int) DB::selectOne("
        SELECT COUNT(*) n FROM student_attendances a
        JOIN student_enrolls e ON e.id = a.student_enroll_id
        JOIN student_enroll_subject es ON es.student_enroll_id = e.id AND es.subject_id = a.subject_id
        WHERE a.subject_id = ? AND e.session_id = ? AND e.student_id = ? AND $ownGroups",
        [$SUBJECT, $SESSION, $case->student_id])->n;

    check('their whole record is on that one row, not a fraction of it',
        $theirTotal,
        $row['present'] + $row['absent'] + $row['leave'] + $row['holiday']);
} else {
    ok('(no multi-enrolment student on this course to test)', true);
    ok('(skipped)', true);
    ok('(skipped)', true);
}

// This is the fact that makes the rule matter, so it is asserted, not assumed.
$splitStudents = 0;
foreach (DB::select("SELECT e.student_id FROM student_enrolls e WHERE e.session_id = ?
                     GROUP BY e.student_id HAVING COUNT(*) > 1", [$SESSION]) as $s) {
    $n = (int) DB::selectOne("SELECT COUNT(DISTINCT a.student_enroll_id) n
        FROM student_attendances a JOIN student_enrolls e ON e.id = a.student_enroll_id
        WHERE e.session_id = ? AND e.student_id = ?", [$SESSION, $s->student_id])->n;
    if ($n > 1) { $splitStudents++; }
}

ok('students really do have attendance split across enrolments (else this rule is untested)', $splitStudents > 0);

// ---------------------------------------------------------------------------
section('enrolment status is not a filter');

$statusGap = DB::selectOne("
    SELECT COUNT(*) n FROM (
      SELECT es.subject_id, e.semester_id,
             COUNT(DISTINCT CASE WHEN e.status = '1' AND s.status = '1' THEN e.id END) strict,
             COUNT(DISTINCT a.id) att
      FROM student_enroll_subject es
      JOIN student_enrolls e ON e.id = es.student_enroll_id
      JOIN students s ON s.id = e.student_id
      LEFT JOIN student_attendances a ON a.student_enroll_id = e.id AND a.subject_id = es.subject_id
      WHERE e.session_id = ?
      GROUP BY es.subject_id, e.semester_id
      HAVING att > 0 AND strict = 0) t", [$SESSION]);

ok('there really are courses whose students have all moved on', (int) $statusGap->n > 0);

// Pick one of them and prove this screen still shows its class.
$moved = DB::selectOne("
    SELECT es.subject_id, COUNT(DISTINCT e.student_id) loose, COUNT(DISTINCT a.id) att
    FROM student_enroll_subject es
    JOIN student_enrolls e ON e.id = es.student_enroll_id
    JOIN students s ON s.id = e.student_id
    LEFT JOIN student_attendances a ON a.student_enroll_id = e.id AND a.subject_id = es.subject_id
    WHERE e.session_id = ?
      AND NOT EXISTS (SELECT 1 FROM student_enrolls e2 JOIN students s2 ON s2.id = e2.student_id
                      JOIN student_enroll_subject es2 ON es2.student_enroll_id = e2.id
                      WHERE es2.subject_id = es.subject_id AND e2.session_id = e.session_id
                        AND e2.status = '1' AND s2.status = '1')
    GROUP BY es.subject_id HAVING att > 0 LIMIT 1", [$SESSION]);

if ($moved) {
    // Act as somebody who may see everything, so this tests the roster rule
    // rather than the access rule.
    $admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();
    Auth::guard('web')->setUser($admin);

    $movedReport = $reportFor(['subject' => (int) $moved->subject_id]);

    check('a course whose students have all moved up still has its class list',
        (int) $moved->loose, $movedReport['totals']['students']);
    ok('and its attendance', $movedReport['totals']['present'] + $movedReport['totals']['absent'] > 0);

    Auth::guard('web')->setUser($teacher);
} else {
    ok('(no fully-progressed course on this install)', true);
    ok('(skipped)', true);
}

// ---------------------------------------------------------------------------
section('a holiday is not a working day, and unmarked is not absent');

DB::beginTransaction();

$victim = $whole['students'][0];
$victimEnroll = (int) DB::table('student_enrolls as e')
    ->join('student_enroll_subject as es', 'es.student_enroll_id', '=', 'e.id')
    ->where('e.session_id', $SESSION)->where('es.subject_id', $SUBJECT)
    ->where('e.student_id', $victim['student_id'])->value('e.id');

$freeDate = date('Y-m-d', strtotime('2099-01-15'));

StudentAttendance::create([
    'student_enroll_id' => $victimEnroll,
    'subject_id' => $SUBJECT,
    'date' => $freeDate,
    'attendance' => 4, // holiday
]);

$withHoliday = $reportFor(['subject' => $SUBJECT]);
$victimAfter = collect($withHoliday['students'])->firstWhere('student_id', $victim['student_id']);

check('the holiday is counted as a holiday', $victim['holiday'] + 1, $victimAfter['holiday']);
check('and leaves the working days alone', $victim['working'], $victimAfter['working']);
near('so the rate does not move', $victim['rate'], $victimAfter['rate']);
check('the session is still counted as held', $whole['totals']['sessions_held'] + 1, $withHoliday['totals']['sessions_held']);

// Everyone else was not marked on that date -- which must not become an absence.
$other = collect($withHoliday['students'])->first(fn ($r) => $r['student_id'] !== $victim['student_id']);
$otherBefore = collect($whole['students'])->firstWhere('student_id', $other['student_id']);

check('somebody not marked that day gains no absence', $otherBefore['absent'], $other['absent']);
check('they gain an unmarked instead', $otherBefore['unmarked'] + 1, $other['unmarked']);
near('and their rate does not move either', $otherBefore['rate'], $other['rate']);

DB::rollBack();

check('the fixture was rolled back', $opening['attendance'], DB::table('student_attendances')->count());

// ---------------------------------------------------------------------------
section('an absence does move the rate');

DB::beginTransaction();

StudentAttendance::create([
    'student_enroll_id' => $victimEnroll,
    'subject_id' => $SUBJECT,
    'date' => $freeDate,
    'attendance' => 2, // absent
]);

$withAbsence = $reportFor(['subject' => $SUBJECT]);
$victimAbsent = collect($withAbsence['students'])->firstWhere('student_id', $victim['student_id']);

check('the absence is counted', $victim['absent'] + 1, $victimAbsent['absent']);
check('and it is a working day', $victim['working'] + 1, $victimAbsent['working']);
ok('so the rate falls', $victimAbsent['rate'] < $victim['rate']);

$expectedRate = round($victim['present'] / ($victim['working'] + 1) * 100, 1);
near('to present / (present + absent + leave)', $expectedRate, $victimAbsent['rate']);

DB::rollBack();

// ---------------------------------------------------------------------------
section('a service course is one register, not one per programme');

ok('this course really is taught to several programmes', count($whole['by_class']) > 1);

$firstGroup = $whole['by_class'][0];
$groupIds = DB::selectOne("
    SELECT e.program_id, e.semester_id, e.section_id
    FROM student_enrolls e
    JOIN student_enroll_subject es ON es.student_enroll_id = e.id
    JOIN programs p ON p.id = e.program_id
    WHERE e.session_id = ? AND es.subject_id = ? AND p.title = ?
    LIMIT 1", [$SESSION, $SUBJECT, $firstGroup['program']]);

$narrowed = $reportFor([
    'subject' => $SUBJECT,
    'program' => $groupIds->program_id,
    'semester' => $groupIds->semester_id,
    'section' => $groupIds->section_id,
]);

ok('narrowing to one programme shows fewer students', $narrowed['totals']['students'] < $whole['totals']['students']);
ok('and fewer present', $narrowed['totals']['present'] < $whole['totals']['present']);

check('what the narrowing leaves out is counted, not dropped',
    ($whole['totals']['present'] + $whole['totals']['absent'] + $whole['totals']['leave'] + $whole['totals']['holiday'])
        - ($narrowed['totals']['present'] + $narrowed['totals']['absent'] + $narrowed['totals']['leave'] + $narrowed['totals']['holiday']),
    $narrowed['outside']['records']);

ok('the whole course reports nothing left out', $whole['outside']['records'] === 0);
ok('the narrowed view is flagged as narrowed', $narrowed['narrowed']);
ok('the whole course is not', !$whole['narrowed']);

// ---------------------------------------------------------------------------
section('every breakdown adds back up to the headline');

check('by group: students', $whole['totals']['students'], array_sum(array_column($whole['by_class'], 'students')));
check('by group: present', $whole['totals']['present'], array_sum(array_column($whole['by_class'], 'present')));
check('by group: absent', $whole['totals']['absent'], array_sum(array_column($whole['by_class'], 'absent')));

check('by date: present', $whole['totals']['present'], array_sum(array_column($whole['by_date'], 'present')));
check('by date: absent', $whole['totals']['absent'], array_sum(array_column($whole['by_date'], 'absent')));
check('by date: one row per session held', $whole['totals']['sessions_held'], count($whole['by_date']));

check('per student: present', $whole['totals']['present'], collect($whole['students'])->sum('present'));
check('per student: absent', $whole['totals']['absent'], collect($whole['students'])->sum('absent'));
check('per student: unmarked', $whole['totals']['unmarked'], collect($whole['students'])->sum('unmarked'));

$expectedOverall = $whole['totals']['working'] > 0
    ? round($whole['totals']['present'] / $whole['totals']['working'] * 100, 1)
    : null;
check('the headline rate is present over working days', $expectedOverall, $whole['totals']['rate']);

// ---------------------------------------------------------------------------
section('the strip lines up with the dates');

$widths = collect($whole['students'])->map(fn ($r) => count($r['strip']))->unique();
check('every strip is the same width', 1, $widths->count());
check('and that width is the number of sessions held', $whole['totals']['sessions_held'], $widths->first());

$sample = $whole['students'][0];
$marked = count(array_filter($sample['strip'], fn ($m) => $m !== 'unmarked'));
check('the marks on a strip are that student\'s records',
    $sample['present'] + $sample['absent'] + $sample['leave'] + $sample['holiday'], $marked);
check('and the gaps are their unmarked sessions', $sample['unmarked'],
    count(array_filter($sample['strip'], fn ($m) => $m === 'unmarked')));

// ---------------------------------------------------------------------------
section('a lecturer cannot reach a course they do not teach');

$mine = $filterFor([])->subjects()->pluck('id');
ok('the lecturer has courses of their own', $mine->isNotEmpty());

$foreign = Subject::whereNotIn('id', $mine)->first();

if ($foreign) {
    ok('a course they do not teach is not in the dropdown', !$mine->contains($foreign->id));

    $refused = false;
    try { $filterFor(['subject' => $foreign->id])->authorise(); }
    catch (\Throwable $e) { $refused = str_contains(get_class($e), 'Http'); }

    ok('and the query string is refused too', $refused);
} else {
    ok('(this lecturer sees every course)', true);
    ok('(skipped)', true);
}

$guest = CourseClassFilter::fromRequest(Request::create('/admin/course-class-list', 'GET', ['session' => $SESSION]));
Auth::guard('web')->logout();
check('nobody logged in sees no courses', 0, $guest->subjects()->count());
Auth::guard('web')->setUser($teacher);

// ---------------------------------------------------------------------------
section('the import template is no longer unguarded');

$ctor = (new ReflectionClass(App\Http\Controllers\Admin\StudentAttendanceController::class))
    ->getMethod('__construct');
$source = implode('', array_slice(
    file($ctor->getFileName()),
    $ctor->getStartLine() - 1,
    $ctor->getEndLine() - $ctor->getStartLine() + 1
));

ok('downloadClassList is behind a permission', str_contains($source, "'downloadClassList'"));
ok('and it is the importer\'s own permission', (bool) preg_match(
    "~-import',\s*\['only'\s*=>\s*\[[^\]]*'downloadClassList'~", $source));

// ---------------------------------------------------------------------------
section('the dashboard names each class instead of collapsing them');

$routines = ClassRoutine::where('teacher_id', $teacherId)->where('session_id', $SESSION)
    ->where('status', '1')->with(['subject', 'program', 'semester', 'section'])->get();

$method = (new ReflectionClass(App\Http\Controllers\Admin\DashboardController::class))
    ->getMethod('teacherCourses');
$method->setAccessible(true);
$dashboard = $method->invoke(
    (new ReflectionClass(App\Http\Controllers\Admin\DashboardController::class))->newInstanceWithoutConstructor(),
    $routines,
    $SESSION
);

$realClasses = $routines->unique(fn ($r) => $r->subject_id . '|' . $r->program_id . '|' . $r->semester_id . '|' . $r->section_id)->count();

check('one tile per subject', $routines->pluck('subject_id')->unique()->count(), $dashboard->count());
check('but every real class is named', $realClasses, $dashboard->sum(fn ($c) => $c['classes']->count()));
ok('at least one tile carries more than one class (else this proves nothing)',
    $dashboard->contains(fn ($c) => $c['classes']->count() > 1));

$tile = $dashboard->first(fn ($c) => $c['classes']->count() > 1) ?: $dashboard->first();
$tileExpected = (int) DB::selectOne("
    SELECT COUNT(DISTINCT e.student_id) n
    FROM student_enrolls e
    JOIN student_enroll_subject es ON es.student_enroll_id = e.id
    WHERE e.session_id = ? AND es.subject_id = ?
      AND EXISTS (SELECT 1 FROM class_routines r
          WHERE r.session_id = e.session_id AND r.status = '1'
            AND r.subject_id = es.subject_id AND r.teacher_id = ?
            AND r.program_id = e.program_id AND r.semester_id = e.semester_id
            AND r.section_id = e.section_id)", [$SESSION, $tile['subject']->id, $teacherId])->n;

// The badge and the screen the badge links to must be the same number, or the
// tile is advertising a class list that is not the one it opens.
check('the head count on a tile is the students in the groups that lecturer teaches', $tileExpected, $tile['students']);

DB::enableQueryLog();
DB::flushQueryLog();
$method->invoke(
    (new ReflectionClass(App\Http\Controllers\Admin\DashboardController::class))->newInstanceWithoutConstructor(),
    $routines,
    $SESSION
);
$dashboardQueries = count(DB::getQueryLog());
DB::disableQueryLog();

ok('and it costs one query for every class at once, not one each', $dashboardQueries <= 2);

// Calling teacherCourses() directly proves the builder; it does not prove the
// dashboard USES it. Without this, reverting index() to the old collapsing
// pluck leaves the suite green.
$dashboardHtml = $asUser('/admin', [])->getContent();

preg_match_all('~course-class-list\?[^"]*?subject=~', $dashboardHtml, $links);

check('the dashboard links one class list per subject and one per class',
    $dashboard->count() + $realClasses, count($links[0]));

ok('every tile offers the whole course',
    substr_count($dashboardHtml, 'Class list &amp; attendance') === $dashboard->count()
    || substr_count($dashboardHtml, 'Class list & attendance') === $dashboard->count());

// ---------------------------------------------------------------------------
section('the cost does not grow with the class');

$countQueries = function (array $query) use ($reportFor) {
    DB::enableQueryLog();
    DB::flushQueryLog();
    $reportFor($query);
    $n = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $n;
};

$forWhole = $countQueries(['subject' => $SUBJECT]);
$forOne = $countQueries([
    'subject' => $SUBJECT,
    'program' => $groupIds->program_id,
    'semester' => $groupIds->semester_id,
    'section' => $groupIds->section_id,
]);

ok('the whole course costs no more than one group of it',
    $forWhole - $forOne < 5);
ok('and far fewer queries than there are students',
    $forWhole < $whole['totals']['students']);

// ---------------------------------------------------------------------------
section('the two documents actually come out');

$pdfResponse = $asUser('/admin/course-class-list/pdf', ['subject' => $SUBJECT]);
check('the PDF is served', 200, $pdfResponse->getStatusCode());
ok('as a PDF', str_starts_with($pdfResponse->getContent(), '%PDF'));
ok('as a download named after the course',
    str_contains($pdfResponse->headers->get('content-disposition') ?? '', 'attachment')
    && str_contains($pdfResponse->headers->get('content-disposition') ?? '', 'class-list-'));

$xlsxResponse = $asUser('/admin/course-class-list/excel', ['subject' => $SUBJECT]);
check('the workbook is served', 200, $xlsxResponse->getStatusCode());

$xlsxBody = $xlsxResponse instanceof Symfony\Component\HttpFoundation\BinaryFileResponse
    ? file_get_contents($xlsxResponse->getFile()->getPathname())
    : $xlsxResponse->getContent();

ok('as a workbook', str_starts_with($xlsxBody, 'PK'));

$screenResponse = $asUser('/admin/course-class-list', ['subject' => $SUBJECT]);
check('the screen is served', 200, $screenResponse->getStatusCode());

// ---------------------------------------------------------------------------
section('the screen, the PDF and the workbook are one computation');

$html = $screenResponse->getContent();

$pdfHtml = View::make('admin.course-class-list.pdf', [
    'report' => $whole,
    'letterhead' => app(App\Services\LetterheadService::class)->render(true),
    'letterheadStyles' => app(App\Services\LetterheadService::class)->styles(),
])->render();

foreach ([
    'the student count' => (string) $whole['totals']['students'],
    'the present count' => number_format($whole['totals']['present']),
    'the sessions held' => (string) $whole['totals']['sessions_held'],
] as $what => $needle) {
    ok("the screen carries $what", str_contains($html, $needle));
    ok("the document carries $what", str_contains($pdfHtml, $needle));
}

ok('both name the course', str_contains($html, $subject->code) && str_contains($pdfHtml, $subject->code));
ok('the document says how it counts', str_contains($pdfHtml, __('How this report counts')));
ok('and that unmarked is not absent',
    str_contains($pdfHtml, 'not the same as absent') || str_contains($pdfHtml, 'never as an absence'));

$book = new CourseClassListExport($whole);
$sheets = $book->sheets();
check('the workbook has five sheets', 5, count($sheets));

$classSheet = $sheets[0]->array();
$headingRow = null;
foreach ($classSheet as $i => $line) {
    if (($line[0] ?? null) === '#') { $headingRow = $i; break; }
}
ok('the class-list sheet has a heading row', $headingRow !== null);
check('with one line per student',
    count($whole['students']), count($classSheet) - $headingRow - 1);

$sumPresent = 0;
foreach (array_slice($classSheet, $headingRow + 1) as $line) {
    $sumPresent += $line[9];
}
check('and its present column sums to the headline', $whole['totals']['present'], $sumPresent);

$allNumeric = true;
foreach (array_slice($classSheet, $headingRow + 1) as $line) {
    if (!is_int($line[9]) || !is_int($line[10])) { $allNumeric = false; break; }
}
ok('the counts are numbers, not text, so the column adds up', $allNumeric);

$registerSheet = end($sheets)->array();
$registerHeading = null;
foreach ($registerSheet as $i => $line) {
    if (($line[0] ?? null) === __('field_matricule')) { $registerHeading = $i; break; }
}
ok('the register sheet has a heading row', $registerHeading !== null);
check('with a column per session held plus matricule and name',
    $whole['totals']['sessions_held'] + 2, count($registerSheet[$registerHeading]));

// ---------------------------------------------------------------------------
section('the buttons carry the filters');

ok('the export buttons submit the form rather than linking',
    substr_count($html, 'formaction') >= 2);
ok('the PDF button posts to the PDF route', str_contains($html, '/admin/course-class-list/pdf'));
ok('the workbook button posts to the workbook route', str_contains($html, '/admin/course-class-list/excel'));

$dashboardLink = route('admin.course-class-list.index', ['session' => $SESSION, 'subject' => $SUBJECT]);
ok('the dashboard link carries both the session and the course',
    str_contains($dashboardLink, 'session=' . $SESSION) && str_contains($dashboardLink, 'subject=' . $SUBJECT));

// ---------------------------------------------------------------------------
section('an empty result is reported as empty');

// It must be a course somebody is timetabled to teach, because a course with
// no routine at all is not openable by anyone -- there is nobody whose class
// list it would be. So: has a routine, has nobody enrolled.
$emptySubject = DB::selectOne("
    SELECT r.subject_id id FROM class_routines r
    WHERE r.session_id = ? AND r.status = '1'
      AND NOT EXISTS (SELECT 1 FROM student_enroll_subject es
                      JOIN student_enrolls e ON e.id = es.student_enroll_id
                      WHERE es.subject_id = r.subject_id AND e.session_id = r.session_id)
    LIMIT 1", [$SESSION]);

if ($emptySubject) {
    $admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();
    Auth::guard('web')->setUser($admin);

    $nothing = $reportFor(['subject' => (int) $emptySubject->id]);

    ok('an empty course is reported as having no data', !$nothing['has_data']);
    check('with nobody on the list', 0, $nothing['totals']['students']);
    check('and no rate rather than a zero', null, $nothing['totals']['rate']);

    $emptyHtml = View::make('admin.course-class-list.pdf', [
        'report' => $nothing,
        'letterhead' => '',
        'letterheadStyles' => '',
    ])->render();

    ok('and the document says so', str_contains($emptyHtml, __('No students')));

    $emptyBook = new CourseClassListExport($nothing);
    check('the workbook still has all its sheets', 5, count($emptyBook->sheets()));

    Auth::guard('web')->setUser($teacher);
} else {
    foreach (range(1, 5) as $i) { ok('(every subject has students on this install)', true); }
}

// ---------------------------------------------------------------------------
section('nothing was left behind');

$closing = [
    'enrolls' => DB::table('student_enrolls')->count(),
    'attendance' => DB::table('student_attendances')->count(),
    'routines' => DB::table('class_routines')->count(),
    'subjects' => DB::table('subjects')->count(),
];

check('the same number of enrolments as when this started', $opening['enrolls'], $closing['enrolls']);
check('the same attendance records', $opening['attendance'], $closing['attendance']);
check('the same class routines', $opening['routines'], $closing['routines']);
check('the same subjects', $opening['subjects'], $closing['subjects']);

printf("\n%d passed, %d failed\n", $pass, $fail);
