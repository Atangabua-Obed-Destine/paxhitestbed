<?php
/**
 * The order a transcript prints its semester sections in.
 *
 * Every screen that shows a transcript used to walk the student's enrolments in
 * whatever order the relation handed them back, which is the order they were
 * CREATED. That was right by accident for as long as enrolments were raised as
 * the student moved forward through the programme. Raising one BACKWARDS -- a
 * first-semester resit opened after the second semester and its own resit are
 * already on file -- gave it the highest id of the four, and the transcript
 * printed FIRST, SECOND, SECOND RESIT, FIRST RESIT.
 *
 * The rule the suite pins: session, then academic year, then first semester
 * before second, with a resit directly behind the semester it shadows.
 *
 * THE EXPECTED ORDER IS RECOMPUTED HERE IN SQL, never by asking the service
 * twice, so a mistake in the service cannot agree with itself. The one
 * refinement SQL does not express -- a resit borrowing its parent's SESSION --
 * is given a fixture of its own.
 *
 * Every write runs inside a transaction that is rolled back.
 *
 * Usage: php scripts/transcript_semester_order_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentEnroll;
use App\Services\Academic\TranscriptSemesterOrder;
use Illuminate\Http\Request;
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
        echo "  FAIL  $label" . ($detail !== '' ? "\n          $detail" : '') . "\n";
    }
}

$order = app(TranscriptSemesterOrder::class);

// Absolute counts, to close with.
$countsBefore = [
    'enrolls' => DB::table('student_enrolls')->count(),
    'semesters' => DB::table('semesters')->count(),
    'markings' => DB::table('subject_markings')->count(),
    'published' => DB::table('subject_markings')->where('workflow_state', 'published')->count(),
];

/**
 * One transcript's enrolments: a student, in one programme, under one matricule.
 */
$transcripts = DB::table('student_enrolls')
    ->whereNotNull('semester_id')->whereNotNull('session_id')
    ->orderBy('id')->get()
    ->groupBy(fn ($r) => $r->student_id . '|' . $r->program_id . '|' . $r->matricule);

// ---------------------------------------------------------------------------
section('The order is academic, not the order the enrolments were raised');

/**
 * The expectation, worked out from the tables directly.
 *
 * A resit takes the year and semester type of the semester it shadows, then
 * sorts behind it; everything else sorts on its own. Sessions lead, so a
 * repeated year stays whole. `year` is a varchar, so it is cast rather than
 * compared as text -- "10" must not sort before "2".
 */
$expectedOrder = DB::table('student_enrolls as se')
    ->join('semesters as s', 's.id', '=', 'se.semester_id')
    ->join('sessions as ss', 'ss.id', '=', 'se.session_id')
    ->leftJoin('semesters as p', function ($join) {
        $join->on('p.id', '=', 's.parent_semester_id')->where('s.is_resit', '=', 1);
    })
    ->orderBy('ss.start_date')
    ->orderByRaw('CAST(COALESCE(p.year, s.year) AS UNSIGNED)')
    ->orderByRaw('COALESCE(p.semester_type, s.semester_type)')
    ->orderBy('s.is_resit')
    ->orderBy('se.id')
    ->get(['se.id', 'se.student_id', 'se.program_id', 'se.matricule', 'se.semester_id'])
    ->groupBy(fn ($r) => $r->student_id . '|' . $r->program_id . '|' . $r->matricule);

check('there are transcripts on this installation to check',
    $transcripts->count() > 0, $transcripts->count() . ' found');

$multi = $transcripts->filter(fn ($g) => $g->pluck('semester_id')->unique()->count() > 1);

check('and more than one of them spans several semesters',
    $multi->count() > 1, $multi->count() . ' of ' . $transcripts->count());

$disagree = [];

foreach ($transcripts as $key => $rows) {
    $enrolments = StudentEnroll::with(['semester', 'session'])
        ->whereIn('id', $rows->pluck('id'))->get();

    $mine = $order->sort($enrolments)->pluck('semester_id')->map(fn ($i) => (int) $i)->unique()->values()->all();
    $theirs = ($expectedOrder[$key] ?? collect())->pluck('semester_id')->map(fn ($i) => (int) $i)->unique()->values()->all();

    if ($mine !== $theirs) {
        $disagree[$key] = [$mine, $theirs];
    }
}

check('the service agrees with the order worked out in SQL, on every transcript',
    $disagree === [],
    count($disagree) . ' disagree, e.g. ' . json_encode(array_slice($disagree, 0, 1)));

// ---------------------------------------------------------------------------
section('A resit prints directly behind the semester it shadows');

$semesters = Semester::all()->keyBy('id');
$adjacencyBroken = [];
$resitsChecked = 0;

foreach ($transcripts as $key => $rows) {
    $enrolments = StudentEnroll::with(['semester', 'session'])
        ->whereIn('id', $rows->pluck('id'))->get();

    $sequence = $order->sort($enrolments)->pluck('semester_id')->map(fn ($i) => (int) $i)->unique()->values()->all();

    foreach ($sequence as $position => $semesterId) {
        $semester = $semesters[$semesterId] ?? null;

        if (!$semester || !$semester->is_resit || !$semester->parent_semester_id) {
            continue;
        }

        $parentId = (int) $semester->parent_semester_id;

        // Only where the semester it shadows is on this transcript at all --
        // a resit whose original enrolment has gone has nothing to sit behind.
        if (!in_array($parentId, $sequence, true)) {
            continue;
        }

        $resitsChecked++;

        if (($sequence[$position - 1] ?? null) !== $parentId) {
            $adjacencyBroken[$key] = $sequence;
        }
    }
}

check('there are resit sittings on this installation to check',
    $resitsChecked > 0, $resitsChecked . ' found');

check('every one of them prints immediately after its parent semester',
    $adjacencyBroken === [],
    count($adjacencyBroken) . ' broken, e.g. ' . json_encode(array_slice($adjacencyBroken, 0, 1)));

// ---------------------------------------------------------------------------
section('A sitting raised backwards still lands in its place');

/**
 * The bug as it was reported: the first resit carries the newest enrolment id
 * of the four and used to print last.
 */
$backward = null;

foreach ($transcripts as $key => $rows) {
    $ids = $rows->pluck('semester_id')->map(fn ($i) => (int) $i)->unique()->values()->all();
    $last = $semesters[(int) $rows->sortByDesc('id')->first()->semester_id] ?? null;

    if (!$last || !$last->is_resit || !$last->parent_semester_id) {
        continue;
    }

    // Raised last, yet its parent is not the last semester of the programme:
    // something comes after it academically.
    $parentPosition = array_search((int) $last->parent_semester_id, $ids, true);

    if ($parentPosition !== false && $parentPosition < count($ids) - 2) {
        $backward = [$key, $rows, $last];
        break;
    }
}

check('a transcript with a backward-raised resit exists to test against',
    $backward !== null);

if ($backward) {
    [$key, $rows, $resitSemester] = $backward;

    $enrolments = StudentEnroll::with(['semester', 'session'])
        ->whereIn('id', $rows->pluck('id'))->get();

    $raised = $enrolments->sortBy('id')->pluck('semester_id')->map(fn ($i) => (int) $i)->unique()->values()->all();
    $printed = $order->sort($enrolments)->pluck('semester_id')->map(fn ($i) => (int) $i)->unique()->values()->all();

    $titles = fn (array $ids) => implode(' > ', array_map(fn ($i) => $semesters[$i]->title ?? "#$i", $ids));

    check('raised last, it used to print last',
        end($raised) === (int) $resitSemester->id,
        $titles($raised));

    check('and now prints behind the semester it shadows instead',
        array_search((int) $resitSemester->id, $printed, true)
            === array_search((int) $resitSemester->parent_semester_id, $printed, true) + 1,
        $titles($printed));

    check('nothing was dropped or duplicated in the reordering',
        count($printed) === count($raised) && array_diff($printed, $raised) === [] && array_diff($raised, $printed) === []);
}

// ---------------------------------------------------------------------------
section('A resit raised in a later session still sits with its parent');

/**
 * The refinement the plain SQL rule above does not express. Every resit on this
 * installation happens to sit in the same session as the enrolment it shadows,
 * so ordering by the resit's own session would look correct here and come apart
 * the first time a school raises a Year 1 resit during the following academic
 * year. The fixture moves one into the next session to prove the rule holds.
 */
$laterSession = DB::table('sessions')->orderByDesc('start_date')->first();

if ($backward && $laterSession) {
    [$key, $rows, $resitSemester] = $backward;

    $sitting = $rows->first(fn ($r) => (int) $r->semester_id === (int) $resitSemester->id);

    check('the fixture has a session later than the one the sitting is in to move it to',
        (int) $laterSession->id !== (int) $sitting->session_id,
        'session ' . $laterSession->id . ' vs ' . $sitting->session_id);

    DB::beginTransaction();

    try {
        DB::table('student_enrolls')->where('id', $sitting->id)
            ->update(['session_id' => $laterSession->id]);

        $enrolments = StudentEnroll::with(['semester', 'session'])
            ->whereIn('id', $rows->pluck('id'))->get();

        $printed = $order->sort($enrolments)->pluck('semester_id')->map(fn ($i) => (int) $i)->unique()->values()->all();

        check('a resit sat in the next academic year still prints behind its parent',
            array_search((int) $resitSemester->id, $printed, true)
                === array_search((int) $resitSemester->parent_semester_id, $printed, true) + 1,
            implode(' > ', array_map(fn ($i) => $semesters[$i]->title ?? "#$i", $printed)));
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('The place a resit takes is its parent semester, not its own');

/**
 * Two things this installation's own data cannot prove, because every resit
 * semester was set up carrying its parent's year and type, and every resit
 * enrolment happens to have been raised after the one it shadows. Both would
 * go on passing if the rule read the resit's own columns, or left resits
 * tying with their parent and relied on the order they arrived in.
 */
if ($backward) {
    [$key, $rows, $resitSemester] = $backward;

    $enrolments = StudentEnroll::with(['semester', 'session'])
        ->whereIn('id', $rows->pluck('id'))->get();

    $sequence = fn ($collection) => $order->sort($collection)
        ->pluck('semester_id')->map(fn ($i) => (int) $i)->unique()->values()->all();

    $printed = $sequence($enrolments);

    check('the sequence does not depend on the order the enrolments arrive in',
        $sequence($enrolments->reverse()) === $printed && $sequence($enrolments->shuffle()) === $printed,
        implode(' > ', $printed) . ' vs ' . implode(' > ', $sequence($enrolments->reverse())));

    DB::beginTransaction();

    try {
        // Year and type that would sort it last if they were ever read.
        DB::table('semesters')->where('id', $resitSemester->id)
            ->update(['year' => '9', 'semester_type' => 9]);

        $reloaded = StudentEnroll::with(['semester', 'session'])
            ->whereIn('id', $rows->pluck('id'))->get();

        check("a resit carrying its own wrong year and type still sits behind its parent",
            array_search((int) $resitSemester->id, $sequence($reloaded), true)
                === array_search((int) $resitSemester->parent_semester_id, $sequence($reloaded), true) + 1,
            implode(' > ', array_map(fn ($i) => $semesters[$i]->title ?? "#$i", $sequence($reloaded))));
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
section('Every screen that shows a transcript uses the same order');

/**
 * Seven places build this list, each its own copy of the same loop. A fix
 * applied to one would leave the printed transcript, the preview and the
 * student's own copy disagreeing about the sequence, so each is checked for
 * the shared service by name.
 */
$callers = [
    'resources/views/admin/marksheet/partials/page.blade.php' => '->sort($row->studentEnrolls) as $enroll',
    'resources/views/admin/marksheet/show.blade.php' => '@foreach($orderedEnrolls as $enroll)',
    'resources/views/admin/student/show.blade.php' => '@foreach( $orderedEnrolls as $key => $enroll )',
    'resources/views/student/transcript/index.blade.php' => '@foreach( $orderedEnrolls as $key => $enroll )',
    'app/Http/Controllers/Admin/MarksheetController.php' => '->sort(',
    'app/Http/Controllers/Admin/StudentController.php' => '->sort($enrollments)',
    'app/Http/Controllers/Student/TranscriptController.php' => 'foreach ($orderedEnrolls as $enroll)',
];

foreach ($callers as $file => $needle) {
    $source = @file_get_contents(__DIR__ . '/../' . $file);

    check('the ordering reaches ' . $file,
        is_string($source)
            && str_contains($source, 'TranscriptSemesterOrder')
            && str_contains($source, $needle));
}

// ---------------------------------------------------------------------------
section('The screens still render, in the order the service gives');

$admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->where('status', '1')->first()
    ?: App\User::where('is_admin', 1)->where('status', '1')->first();

check('there is an administrator to render the screens as', $admin !== null);

$kernel = app(Illuminate\Contracts\Http\Kernel::class);

Auth::guard('web')->logout();
app('session.store')->start();
Auth::guard('web')->login($admin);

$render = function (string $url) use ($kernel) {
    $request = Request::create($url, 'GET');
    $request->setLaravelSession(app('session.store'));
    $response = $kernel->handle($request);

    return [$response->getStatusCode(), (string) $response->getContent()];
};

if ($backward) {
    [$key, $rows] = $backward;
    [$studentId] = explode('|', $key);

    $enrolments = StudentEnroll::with(['semester', 'session'])
        ->whereIn('id', $rows->pluck('id'))->get();

    $expectedTitles = $order->sort($enrolments)
        ->map(fn ($e) => optional($e->semester)->title)->filter()->unique()->values()->all();

    $latest = $rows->sortByDesc('id')->first();

    $screens = [
        'the printed transcript' => '/admin/transcript/marksheet-print/' . $studentId . '?enrollment_id=' . $latest->id,
        'the downloaded transcript' => '/admin/transcript/marksheet-download/' . $studentId . '?enrollment_id=' . $latest->id,
        'the on-screen preview' => '/admin/transcript/marksheet/' . $studentId,
        'the student profile' => '/admin/admission/student/' . $studentId,
    ];

    foreach ($screens as $label => $url) {
        [$status, $html] = $render($url);

        check($label . ' still renders', $status === 200, 'HTTP ' . $status);

        if ($status !== 200) {
            continue;
        }

        // Where each semester's heading first appears in the document. A
        // heading that appears nowhere is not evidence either way, so only
        // the ones actually printed are compared.
        $positions = [];

        foreach ($expectedTitles as $title) {
            $at = strpos($html, htmlspecialchars($title, ENT_QUOTES));

            if ($at === false) {
                $at = strpos($html, $title);
            }

            if ($at !== false) {
                $positions[$title] = $at;
            }
        }

        $printedOrder = array_keys($positions);
        asort($positions);

        check($label . ' prints them in that order',
            $printedOrder === array_keys($positions) && count($positions) > 1,
            implode(' > ', array_keys($positions)));
    }
}

// ---------------------------------------------------------------------------
section("The student's own copy agrees with the one the registrar prints");

/**
 * The portal renders the same transcript behind a different guard and its own
 * copy of the loop. A student reading a sequence the registrar's copy does not
 * print is the whole reason this is one shared service rather than seven sorts.
 */
if ($backward) {
    [$key, $rows] = $backward;
    [$studentId] = explode('|', $key);

    $student = Student::find((int) $studentId);

    check('the student whose transcript is out of order can be signed in as',
        $student !== null);

    if ($student) {
        Auth::guard('student')->logout();
        Auth::guard('student')->login($student);
        app('session.store')->start();

        $request = Request::create('/student/transcript', 'GET');
        $request->setLaravelSession(app('session.store'));
        $response = $kernel->handle($request);

        check('the portal transcript renders', $response->getStatusCode() === 200,
            'HTTP ' . $response->getStatusCode());

        preg_match_all(
            '~<h3 class="transcript-semester-title">([^<]*)</h3>~',
            (string) $response->getContent(),
            $headings
        );

        $portal = array_values(array_unique(array_map('trim', $headings[1])));

        $enrolments = StudentEnroll::with(['semester', 'session'])
            ->whereIn('id', $rows->pluck('id'))->get();

        $expected = $order->sort($enrolments)
            ->map(fn ($e) => optional($e->semester)->title)->filter()->unique()->values()->all();

        check('and prints its sections in the same order the registrar sees',
            $portal === array_values(array_filter($expected, fn ($t) => in_array($t, $portal, true)))
                && count($portal) > 1,
            implode(' > ', $portal));

        Auth::guard('student')->logout();
    }
}

// ---------------------------------------------------------------------------
section('The order changes sequence, not figures');

/**
 * The transcript's cumulative GPA is summed over every mark in a pass of its
 * own, so reordering the sections must not move it. The GPA TREND is the
 * opposite case -- it accumulates as it walks the list -- which is why it has
 * to be ordered by the same rule, and is checked against the page beside it.
 */
if ($backward) {
    [$key, $rows] = $backward;
    [$studentId] = explode('|', $key);

    [$status, $html] = $render('/admin/transcript/marksheet/' . $studentId);

    if ($status === 200 && preg_match('~var gpaData = (\[.*?\]);~s', $html, $m)) {
        $trend = json_decode($m[1], true) ?: [];

        $enrolments = StudentEnroll::with(['semester', 'session'])
            ->whereIn('id', $rows->pluck('id'))->get();

        $expected = $order->sort($enrolments)
            ->map(fn ($e) => optional($e->session)->title . ' - ' . optional($e->semester)->title)
            ->unique()->values()->all();

        $labels = array_column($trend, 'label');

        check('the GPA trend walks the semesters in the printed order',
            $labels === array_values(array_filter($expected, fn ($l) => in_array($l, $labels, true))),
            implode(' > ', $labels));

    } else {
        echo "  SKIP  this transcript carries no GPA trend to check\n";
    }
}

/**
 * The reordered sitting entering the trend. Its marks are drafts on this
 * installation, so the fixture publishes them inside a transaction: the point
 * is the position it takes, and the running figure at that position.
 */
if ($backward) {
    [$key, $rows, $resitSemester] = $backward;
    [$studentId] = explode('|', $key);

    $sitting = $rows->first(fn ($r) => (int) $r->semester_id === (int) $resitSemester->id);
    $drafts = DB::table('subject_markings')->where('student_enroll_id', $sitting->id)
        ->where('workflow_state', '!=', 'published')->count();

    check('the backward sitting has marks to publish for the fixture',
        $drafts > 0, $drafts . ' draft row(s)');

    if ($drafts > 0) {
        DB::beginTransaction();

        try {
            DB::table('subject_markings')->where('student_enroll_id', $sitting->id)
                ->update(['workflow_state' => 'published', 'publish_date' => now()->toDateString()]);

            [$status, $html] = $render('/admin/transcript/marksheet/' . $studentId);

            if ($status === 200 && preg_match('~var gpaData = (\[.*?\]);~s', $html, $m)) {
                $labels = array_column(json_decode($m[1], true) ?: [], 'label');

                $resitLabel = collect($labels)->first(fn ($l) => str_contains($l, $resitSemester->title));
                $parentLabel = collect($labels)->first(
                    fn ($l) => str_contains($l, $semesters[(int) $resitSemester->parent_semester_id]->title ?? '~none~')
                );

                check('once it carries marks the sitting joins the trend',
                    $resitLabel !== null, implode(' > ', $labels));

                check('directly behind the semester it shadows, not at the end',
                    $resitLabel !== null && $parentLabel !== null
                        && array_search($resitLabel, $labels, true) === array_search($parentLabel, $labels, true) + 1,
                    implode(' > ', $labels));
            } else {
                check('the trend could be read back with the sitting published', false, 'HTTP ' . $status);
            }
        } finally {
            DB::rollBack();
        }
    }
}

// ---------------------------------------------------------------------------
section('It costs no extra query where the relations are already loaded');

/**
 * Every transcript screen eager-loads `semester` and `session`, and the
 * semesters a resit shadows are themselves on the transcript. Walking students
 * one at a time is how this screen becomes unusable on a bulk print of eighty.
 */
if ($backward) {
    [, $rows] = $backward;

    $enrolments = StudentEnroll::with(['semester', 'session'])
        ->whereIn('id', $rows->pluck('id'))->get();

    DB::enableQueryLog();
    DB::flushQueryLog();
    $order->sort($enrolments);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    check('sorting a fully loaded transcript runs no query at all',
        $queries === 0, $queries . ' run');

    $bare = StudentEnroll::whereIn('id', $rows->pluck('id'))->get();

    DB::enableQueryLog();
    DB::flushQueryLog();
    $order->sort($bare);
    $bareQueries = count(DB::getQueryLog());
    DB::disableQueryLog();

    check('and no more than two when nothing is loaded',
        $bareQueries <= 2, $bareQueries . ' run');
}

// ---------------------------------------------------------------------------
section('Nothing was left behind');

$countsAfter = [
    'enrolls' => DB::table('student_enrolls')->count(),
    'semesters' => DB::table('semesters')->count(),
    'markings' => DB::table('subject_markings')->count(),
    'published' => DB::table('subject_markings')->where('workflow_state', 'published')->count(),
];

foreach ($countsBefore as $what => $before) {
    check("the $what table is exactly as it was ($before)",
        $countsAfter[$what] === $before,
        $before . ' -> ' . $countsAfter[$what]);
}

echo "\n$passed passed, $failed failed\n";

exit($failed > 0 ? 1 : 0);
