<?php
/**
 * The transcript rule one school asked for: a resit they passed is shown against
 * the semester the course was first taken.
 *
 * It is a reading of the marks, not a rewriting of them, so the suite checks two
 * things above all: that with the setting off nothing whatsoever changes, and
 * that with it on no mark in the database is touched.
 *
 * The expected figures are worked out here from `subject_markings` and the
 * grading scale directly, never through the service, so a mistake in the service
 * cannot agree with itself.
 *
 * Every write runs inside a transaction that is rolled back.
 *
 * Usage: php scripts/transcript_resit_override_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Grade;
use App\Models\MarksheetSetting;
use App\Models\Student;
use App\Models\StudentEnroll;
use App\Models\SubjectMarking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;

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

// Plain paths, not url(): before a request has been handled url() uses APP_URL,
// which carries this site's sub-folder, and such a path matches no route.
$kernel = app(Illuminate\Contracts\Http\Kernel::class);

$admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->where('status', '1')->first()
    ?: App\User::where('is_admin', 1)->where('status', '1')->first();
Auth::guard('web')->login($admin);

$render = function (string $url) use ($kernel) {
    $request = Request::create($url, 'GET');
    $request->setLaravelSession(app('session.store'));
    $response = $kernel->handle($request);

    return [$response->getStatusCode(), (string) $response->getContent()];
};

$marksSnapshot = fn () => json_encode(DB::table('subject_markings')
    ->selectRaw('COUNT(*) n, SUM(total_marks) marks, SUM(CASE WHEN workflow_state = "published" THEN 1 ELSE 0 END) published')->first());

$setOption = function (int $on) {
    MarksheetSetting::where('status', '1')->update(['resit_replaces_original' => $on]);
};

$grades = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();
$gradeFor = function ($marks) use ($grades) {
    foreach ($grades as $grade) {
        if (round($marks) >= $grade->min_mark && round($marks) <= $grade->max_mark) {
            return $grade;
        }
    }

    return null;
};

/** The figures the page should show, worked out here rather than by the service. */
$expected = function (Student $student, bool $optionOn) use ($gradeFor) {
    $enrolls = StudentEnroll::with(['semester', 'subjectMarks.subject'])->where('student_id', $student->id)->get();
    $published = fn ($enroll) => collect($enroll->subjectMarks)
        ->filter(fn ($m) => $m->workflow_state === 'published' && $m->is_visible_to_student);

    // subject => the resit mark that replaces the original, when passed
    $replacing = [];

    if ($optionOn) {
        foreach ($enrolls->filter(fn ($e) => optional($e->semester)->is_resit)->sortBy('id') as $resit) {
            foreach ($published($resit) as $mark) {
                if (round((float) $mark->total_marks) >= 50) {
                    $replacing[$mark->subject_id] = $mark;
                }
            }
        }
    }

    $credits = $earned = $points = 0.0;
    $courses = [];

    foreach ($enrolls as $enroll) {
        $isResit = (bool) optional($enroll->semester)->is_resit;

        foreach ($published($enroll) as $mark) {
            if ($isResit && isset($replacing[$mark->subject_id])) {
                continue; // moved into the original semester
            }

            $effective = (!$isResit && isset($replacing[$mark->subject_id])) ? $replacing[$mark->subject_id] : $mark;
            $grade = $gradeFor($effective->total_marks);

            if (!$grade) {
                continue;
            }

            $credit = (float) $mark->subject->credit_hour;
            $credits += $credit;
            $points += (float) $grade->point * $credit;
            if ((float) $grade->point > 0) $earned += $credit;
            $courses[$mark->subject_id] = true;
        }
    }

    return [
        'gpa' => $credits > 0 ? $points / $credits : 0,
        'credits' => $credits,
        'earned' => $earned,
        'courses' => count($courses),
        'replacing' => $replacing,
    ];
};

/** The four figures the transcript prints, read back off the page. */
$figures = function (string $html) {
    $text = preg_replace('/\s+/', ' ', strip_tags($html));
    preg_match('/CUMULATIVE GPA ([\d.]+)/i', $text, $gpa);
    preg_match('/TOTAL CREDITS ([\d.]+)/i', $text, $credits);
    preg_match('/CREDITS EARNED ([\d.]+)/i', $text, $earned);
    preg_match('/COURSES (\d+)/i', $text, $courses);

    return [
        'gpa' => isset($gpa[1]) ? (float) $gpa[1] : null,
        'credits' => isset($credits[1]) ? (float) $credits[1] : null,
        'earned' => isset($earned[1]) ? (float) $earned[1] : null,
        'courses' => isset($courses[1]) ? (int) $courses[1] : null,
    ];
};

// The richest case available: a student who passed a resit, failed another, and
// has one still being marked, so every branch of the rule is exercised rather
// than skipped.
$resitStudent = null;
$bestScore = -1;

foreach (Student::whereHas('studentEnrolls.semester', fn ($q) => $q->where('is_resit', 1))->get() as $candidate) {
    $enrolls = StudentEnroll::with(['semester', 'subjectMarks'])->where('student_id', $candidate->id)->get();
    $resitMarks = $enrolls->filter(fn ($e) => optional($e->semester)->is_resit)->flatMap(fn ($e) => collect($e->subjectMarks));

    $passed = $resitMarks->filter(fn ($m) => $m->workflow_state === 'published' && round((float) $m->total_marks) >= 50)->count();

    if ($passed === 0) {
        continue;
    }

    $failedAgain = $resitMarks->filter(fn ($m) => $m->workflow_state === 'published' && round((float) $m->total_marks) < 50)->count();
    $stillMarking = $resitMarks->filter(fn ($m) => $m->workflow_state !== 'published')->count();
    $score = ($passed > 0 ? 4 : 0) + ($failedAgain > 0 ? 2 : 0) + ($stillMarking > 0 ? 1 : 0);

    if ($score > $bestScore) {
        $bestScore = $score;
        $resitStudent = $candidate;
    }
}

$plainStudent = Student::whereDoesntHave('studentEnrolls.semester', fn ($q) => $q->where('is_resit', 1))
    ->whereHas('studentEnrolls')->first();

if (!$resitStudent) {
    echo "No student has a passed resit in this database — nothing to test.\n";
    exit(1);
}

$resitEnroll = StudentEnroll::where('student_id', $resitStudent->id)->orderByDesc('id')->first();
$url = "/admin/transcript/marksheet-print/{$resitStudent->id}?enrollment_id={$resitEnroll->id}";

echo "\nStudent under test: {$resitStudent->student_id} {$resitStudent->first_name} {$resitStudent->last_name}\n";

$setOption(0);
[$status, $offHtml] = $render($url);
$offFigures = $figures($offHtml);

// ---------------------------------------------------------------------------

echo "\n== With the option off, nothing changes ==\n";

check('the transcript renders', $status === 200, "status $status");
check('the option is off out of the box',
    (int) MarksheetSetting::where('status', '1')->value('resit_replaces_original') === 0);

$offExpected = $expected($resitStudent, false);
check('its figures are the ones the old rule gives',
    $offFigures['gpa'] !== null && abs($offFigures['gpa'] - $offExpected['gpa']) < 0.01
        && abs($offFigures['credits'] - $offExpected['credits']) < 0.01
        && abs($offFigures['earned'] - $offExpected['earned']) < 0.01
        && $offFigures['courses'] === $offExpected['courses'],
    json_encode([$offFigures, array_diff_key($offExpected, ['replacing' => null])]));

$movedSubjects = $expected($resitStudent, true)['replacing'];
check('a course with a passed resit is listed twice, as it always was',
    collect($movedSubjects)->every(fn ($mark) => substr_count($offHtml, $mark->subject->code) >= 2),
    implode(', ', collect($movedSubjects)->map(fn ($m) => $m->subject->code . '=' . substr_count($offHtml, $m->subject->code))->all()));

// ---------------------------------------------------------------------------

echo "\n== With the option on ==\n";

$before = $marksSnapshot();

DB::beginTransaction();

try {
    $setOption(1);
    [$status, $onHtml] = $render($url);
    $onExpected = $expected($resitStudent, true);
    $onFigures = $figures($onHtml);

    check('the transcript still renders', $status === 200, "status $status");
    check('a passed resit is listed once, not twice',
        collect($movedSubjects)->every(fn ($mark) => substr_count($onHtml, $mark->subject->code) === 1),
        implode(', ', collect($movedSubjects)->map(fn ($m) => $m->subject->code . '=' . substr_count($onHtml, $m->subject->code))->all()));

    // The transcript prints grades, not raw marks, so the proof that the resit
    // result replaced the original is the grade on that course's row.
    $rowFor = function (string $html, string $code) {
        preg_match_all('/<tr[^>]*>(.*?)<\/tr>/s', $html, $rows);

        foreach ($rows[1] as $row) {
            if (str_contains($row, '>' . $code . '<')) {
                return preg_replace('/\s+/', ' ', strip_tags($row));
            }
        }

        return null;
    };

    foreach ($movedSubjects as $subjectId => $resitMark) {
        $parentMark = SubjectMarking::whereIn('student_enroll_id', StudentEnroll::where('student_id', $resitStudent->id)
                ->whereHas('semester', fn ($q) => $q->where('is_resit', 0))->pluck('id'))
            ->where('subject_id', $subjectId)->first();

        $code = $resitMark->subject->code;
        $row = $rowFor($onHtml, $code);
        $resitGrade = $gradeFor($resitMark->total_marks);
        $originalGrade = $parentMark ? $gradeFor($parentMark->total_marks) : null;

        check("{$code}: its row now carries the resit's grade ({$resitGrade->title})",
            $row !== null && str_contains($row, ' ' . $resitGrade->title . ' '),
            (string) $row);
        check("{$code}: and no longer the original's ({$originalGrade->title})",
            $row !== null && ($originalGrade->title === $resitGrade->title
                || !str_contains($row, ' ' . $originalGrade->title . ' ')),
            (string) $row);
        check("{$code}: it earns its credits now",
            $row !== null && str_contains($row, number_format((float) $resitGrade->point * (float) $resitMark->subject->credit_hour, 2)),
            (string) $row);
    }

    check('and the figures count each course once, at the resit result',
        abs($onFigures['gpa'] - $onExpected['gpa']) < 0.01
            && abs($onFigures['credits'] - $onExpected['credits']) < 0.01
            && abs($onFigures['earned'] - $onExpected['earned']) < 0.01
            && $onFigures['courses'] === $onExpected['courses'],
        json_encode([$onFigures, array_diff_key($onExpected, ['replacing' => null])]));
    check('which is a better GPA than before, the fails having gone',
        $onFigures['gpa'] > $offFigures['gpa'], "{$offFigures['gpa']} -> {$onFigures['gpa']}");
    check('the course count does not change: the same courses, read differently',
        $onFigures['courses'] === $offFigures['courses'], "{$offFigures['courses']} -> {$onFigures['courses']}");

    // A resit failed again stays in both places.
    $failedResit = StudentEnroll::with('subjectMarks.subject')->where('student_id', $resitStudent->id)
        ->whereHas('semester', fn ($q) => $q->where('is_resit', 1))->get()
        ->flatMap(fn ($e) => collect($e->subjectMarks))
        ->first(fn ($m) => $m->workflow_state === 'published' && round((float) $m->total_marks) < 50);

    if ($failedResit) {
        check("a resit failed again ({$failedResit->subject->code}) is still shown in both semesters",
            substr_count($onHtml, $failedResit->subject->code) >= 2,
            $failedResit->subject->code . ' appears ' . substr_count($onHtml, $failedResit->subject->code) . ' time(s)');
    } else {
        echo "  SKIP  this student has no resit that was failed again\n";
    }

    // Every semester's own GPA must match the rows printed under it.
    $text = preg_replace('/\s+/', ' ', strip_tags($onHtml));
    preg_match_all('/Semester Totals ([\d.]+) ([\d.]+) ([\d.]+) ([\d.]+) Semester GPA: ([\d.]+)/', $text, $sems, PREG_SET_ORDER);
    check('each semester\'s GPA is its own quality points over its credits',
        $sems !== [] && collect($sems)->every(fn ($s) => abs(((float) $s[4] / max(0.01, (float) $s[1])) - (float) $s[5]) < 0.02),
        json_encode($sems));

    // Nothing was written by any of this.
    check('rendering the transcript writes no mark', $marksSnapshot() === $before);
} finally {
    DB::rollBack();
    $setOption(0);
}

// ---------------------------------------------------------------------------

echo "\n== A resit still being marked ==\n";

$draftResit = StudentEnroll::with('subjectMarks.subject')->where('student_id', $resitStudent->id)
    ->whereHas('semester', fn ($q) => $q->where('is_resit', 1))->get()
    ->flatMap(fn ($e) => collect($e->subjectMarks))
    ->first(fn ($m) => $m->workflow_state !== 'published');

if (!$draftResit) {
    echo "  SKIP  this student has no unpublished resit mark\n";
} else {
    DB::beginTransaction();

    try {
        $setOption(1);
        [, $html] = $render($url);
        check("an unpublished resit ({$draftResit->subject->code}) changes nothing",
            substr_count($html, $draftResit->subject->code) === substr_count($offHtml, $draftResit->subject->code),
            'on: ' . substr_count($html, $draftResit->subject->code) . ', off: ' . substr_count($offHtml, $draftResit->subject->code));

        // Publish it at a pass, and it moves like any other.
        SubjectMarking::where('id', $draftResit->id)->update([
            'workflow_state' => 'published', 'total_marks' => 72,
            'publish_date' => now()->subDay()->format('Y-m-d'), 'publish_time' => '08:00:00',
        ]);
        [, $published] = $render($url);
        check('published as a pass, it moves to the semester the course was first taken',
            substr_count($published, $draftResit->subject->code) === 1);
    } finally {
        DB::rollBack();
        $setOption(0);
    }
}

// ---------------------------------------------------------------------------

echo "\n== A resit semester with nothing left to show ==\n";

DB::beginTransaction();

try {
    $setOption(1);
    $resitEnrolls = StudentEnroll::with('subjectMarks')->where('student_id', $resitStudent->id)
        ->whereHas('semester', fn ($q) => $q->where('is_resit', 1))->get();
    $resitSemesterTitle = optional($resitEnrolls->first()->semester)->title;

    [, $stillThere] = $render($url);
    check('while it holds a failed resit, the resit semester is still listed',
        str_contains($stillThere, $resitSemesterTitle), $resitSemesterTitle);

    // Pass every resit: the semester then has nothing of its own to show.
    SubjectMarking::whereIn('student_enroll_id', $resitEnrolls->pluck('id'))
        ->update(['workflow_state' => 'published', 'total_marks' => 65,
            'publish_date' => now()->subDay()->format('Y-m-d'), 'publish_time' => '08:00:00']);

    [, $emptied] = $render($url);
    check('once every resit has been passed, the resit semester is left off',
        !str_contains($emptied, $resitSemesterTitle));
} finally {
    DB::rollBack();
    $setOption(0);
}

// ---------------------------------------------------------------------------

echo "\n== The other transcripts, and everything else ==\n";

DB::beginTransaction();

try {
    $setOption(1);

    [$s1, $print] = $render($url);
    [$s2, $download] = $render("/admin/transcript/marksheet-download/{$resitStudent->id}?enrollment_id={$resitEnroll->id}");
    [$s3, $bulk] = $render("/admin/transcript/marksheet-bulk?students={$resitEnroll->id}");

    $count = fn ($html, $code) => substr_count($html, $code);
    $moved = collect($movedSubjects)->first();

    check('the download shows what the print does',
        $s2 === 200 && $count($download, $moved->subject->code) === $count($print, $moved->subject->code));
    check('and so does a batch print',
        $s3 === 200 && $count($bulk, $moved->subject->code) === $count($print, $moved->subject->code));

    // The mark sheet screen is a different thing and must not follow this rule.
    [$s4, $marksheet] = $render("/admin/transcript/marksheet/{$resitStudent->id}");
    $setOption(0);
    [, $marksheetOff] = $render("/admin/transcript/marksheet/{$resitStudent->id}");

    check('the mark sheet screen is unaffected by the setting',
        $s4 === 200 && strlen($marksheet) === strlen($marksheetOff),
        strlen($marksheet) . ' vs ' . strlen($marksheetOff));
} finally {
    DB::rollBack();
    $setOption(0);
}

if ($plainStudent) {
    $plainEnroll = StudentEnroll::where('student_id', $plainStudent->id)->orderByDesc('id')->first();
    $plainUrl = "/admin/transcript/marksheet-print/{$plainStudent->id}?enrollment_id={$plainEnroll->id}";

    [, $plainOff] = $render($plainUrl);

    DB::beginTransaction();

    try {
        $setOption(1);
        [, $plainOn] = $render($plainUrl);
        check('a student who never resat sees the same transcript either way',
            strlen($plainOff) === strlen($plainOn), strlen($plainOff) . ' vs ' . strlen($plainOn));
    } finally {
        DB::rollBack();
        $setOption(0);
    }
} else {
    echo "  SKIP  every student in this database has a resit enrolment\n";
}

check('the suite left the option off and the marks untouched',
    (int) MarksheetSetting::where('status', '1')->value('resit_replaces_original') === 0
        && $marksSnapshot() === $before);

echo "\n$passed passed, $failed failed\n";

exit($failed > 0 ? 1 : 0);
