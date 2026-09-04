<?php
/**
 * The health report tells the truth about the school.
 *
 * Its worst previous failure was not a crash: it raised a permanent error that
 * grading was misconfigured, on a school where all 101 subjects were correctly
 * configured. It was reading ExamType.contribution, a global column nothing
 * grades against — marks resolve through AssessmentWeightService and per-subject
 * rows in exam_type_contributions. A red flag that is always red teaches
 * everyone to ignore it, so the day grading really breaks it looks the same.
 *
 * So the tests here are mostly about teeth: each check must fire when something
 * is genuinely wrong and clear when it is not. A health report that cannot fail
 * is decoration.
 *
 * Every mutation runs inside a transaction that is rolled back.
 *
 * Usage: php scripts/academic_health_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Admin\AcademicHealthController;
use App\Models\Subject;
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

Auth::guard('web')->login(App\User::where('is_admin', 1)->firstOrFail());

/** The report, freshly computed. */
$report = fn () => app(AcademicHealthController::class)->index()->getData();

/** Does the report carry a finding with this title? */
$hasFinding = function (array $data, string $needle): bool {
    foreach (array_merge($data['diagnostic_errors'], $data['warnings']) as $f) {
        if (stripos($f['title'], $needle) !== false) {
            return true;
        }
    }

    return false;
};

echo "\n== It reports on what actually grades ==\n";

$data = $report();

// ExamType.contribution sums to 70 here and always has. If the report still
// read it, this would be a permanent error on a correctly configured school.
$examContribution = App\Models\ExamType::where('status', '1')->sum('contribution');

check('the unused global column is still not 100', abs($examContribution - 100) > 0.01,
    'sum is ' . $examContribution);
check('yet no error is raised about it',
    !$hasFinding($data, 'Exam Contribution'),
    'the false alarm is back');
check('mark distribution is reported as configured',
    collect($data['healthy'])->contains(fn ($h) => str_contains($h['title'], 'Mark Distribution')));

DB::beginTransaction();
try {
    // The check has to fire on a real gap, or it proves nothing.
    $id = Subject::where('status', '1')->value('id');
    DB::table('result_contributions')->where('subject_id', $id)->update(['status' => 0]);

    check('a subject without a distribution is caught',
        $hasFinding($report(), 'Without Mark Distribution'));
} finally {
    DB::rollBack();
}

check('and the finding clears once it is configured again',
    !$hasFinding($report(), 'Without Mark Distribution'));

echo "\n== The score reflects consequence, not a count of checks ==\n";

$baseline = $report()['kpis']['configuration_score'];
check('a fully configured school scores 100', (int) $baseline === 100, (string) $baseline);

$scoreWith = function (callable $break) use ($report) {
    DB::beginTransaction();
    try {
        $break();

        return $report()['kpis']['configuration_score'];
    } finally {
        DB::rollBack();
    }
};

$cosmetic = $scoreWith(fn () => DB::table('class_rooms')->update(['status' => 0]));
$critical = $scoreWith(fn () => DB::table('sessions')->update(['current' => 0]));
$grading = $scoreWith(fn () => DB::table('result_contributions')->update(['status' => 0]));

check('losing the classrooms costs a little', $cosmetic < 100 && $cosmetic >= 95, $cosmetic . '%');
check('losing the current session costs much more', $critical < $cosmetic - 5,
    $critical . '% vs ' . $cosmetic . '%');
check('losing all mark distribution costs as much', $grading < $cosmetic - 5,
    $grading . '% vs ' . $cosmetic . '%');

// Half configured should land between "fine" and "broken", not at either end.
$partial = $scoreWith(fn () => DB::table('result_contributions')->limit(50)->update(['status' => 0]));
check('a half-configured school scores between the two',
    $partial < 100 && $partial > $grading, $partial . '%');

echo "\n== The headline number accounts for the findings beneath it ==\n";

$data = $report();
$breakdown = $data['score_breakdown'];

check('the breakdown is published so the number can be explained',
    isset($breakdown['configuration'], $breakdown['error_penalty'], $breakdown['warning_penalty']));
check('errors and warnings pull the headline down',
    $data['kpis']['overall_score']
        === max(0, $breakdown['configuration'] - $breakdown['error_penalty'] - $breakdown['warning_penalty']));
check('so a clean configuration with open findings does not read as perfect',
    ($breakdown['errors'] + $breakdown['warnings']) === 0
        || $data['kpis']['overall_score'] < $breakdown['configuration'],
    'overall ' . $data['kpis']['overall_score'] . ' vs config ' . $breakdown['configuration']);

echo "\n== It reports what stops a semester closing ==\n";

$readiness = $data['results_readiness'];

check('unpublished marks are counted', isset($readiness['draft_marks']));
check('enrolments with no marks are counted', isset($readiness['enrolments_without_marks']));
check('the counts match the database',
    $readiness['enrolments_without_marks']
        === App\Models\StudentEnroll::whereDoesntHave('subjectMarks')->count());
check('and outstanding marks raise a warning',
    $readiness['draft_marks'] === 0 || $hasFinding($data, 'Unpublished'));

echo "\n== It reports the money ==\n";

$financial = $data['financial'];

check('fees billed and received are reported',
    isset($financial['fees_raised'], $financial['fees_paid']));
check('students owing are counted',
    $financial['students_owing'] === DB::table('fees')
        ->whereRaw('paid_amount < (fee_amount + fine_amount - discount_amount)')
        ->distinct()->count('student_enroll_id'));

// Receiving more than was billed is a missing-instalment fault, not a windfall,
// and it makes every arrears figure unreliable.
check('collecting more than was billed is raised as an error',
    $financial['fees_paid'] <= $financial['fees_raised']
        || $hasFinding($data, 'More Collected Than Billed'));

check('a missing active budget is raised',
    $financial['active_budget'] > 0 || $hasFinding($data, 'No Active Budget'));

echo "\n== Optional steps are not shown as failures ==\n";

$sectors = collect($data['pipeline'])->firstWhere('label', 'Sectors');

check('sectors is marked optional', ($sectors['optional'] ?? false) === true);
check('and is not shown as failed while it raises no warning',
    $sectors['ok'] === true);

echo "\n== Only the right people can read it ==\n";

$kernel = app(Illuminate\Contracts\Http\Kernel::class);

$statusFor = function (string $role) use ($kernel) {
    $user = App\User::first()->replicate();
    $user->email = 'zzhealth' . random_int(10000, 99999) . '@example.test';
    // Gate::before grants an admin everything, which would hide a missing check.
    $user->is_admin = 0;

    if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'staff_id')) {
        $user->staff_id = 'ZZ' . random_int(100000, 999999);
    }
    if (\Illuminate\Support\Facades\Schema::hasColumn('users', 'username')) {
        $user->username = 'zzhealth' . random_int(10000, 99999);
    }

    $user->save();
    $user->syncRoles([$role]);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    Auth::guard('web')->login(App\User::find($user->id));

    $request = Illuminate\Http\Request::create('/admin/academic/health-status', 'GET');
    $request->setLaravelSession(app('session.store'));

    try {
        return $kernel->handle($request)->getStatusCode();
    } catch (\Throwable $e) {
        return 403;
    }
};

DB::beginTransaction();
try {
    check('a lecturer cannot read the school\'s health report', $statusFor('Lecturer') === 403);
    check('someone who administers academic structure can', $statusFor('Records Officer') === 200);
} finally {
    DB::rollBack();
    Auth::guard('web')->login(App\User::where('is_admin', 1)->firstOrFail());
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
}

check('the permission exists to be granted',
    Spatie\Permission\Models\Permission::where('name', 'academic-health-view')->exists());

echo "\n== It renders, and without a query storm ==\n";

DB::flushQueryLog();
DB::enableQueryLog();
$start = microtime(true);
app(AcademicHealthController::class)->index();
$queries = count(DB::getQueryLog());
$elapsed = microtime(true) - $start;
DB::disableQueryLog();

// It ran 442 queries per load before the lookups were batched. The ceiling is
// deliberately well above today's count and well below where it started.
check('the page does not run a query per row', $queries < 300, $queries . ' queries');
check('and returns promptly', $elapsed < 3.0, round($elapsed, 2) . 's');

$request = Illuminate\Http\Request::create('/admin/academic/health-status', 'GET');
$request->setLaravelSession(app('session.store'));
$response = $kernel->handle($request);

check('the page renders', $response->getStatusCode() === 200, 'status ' . $response->getStatusCode());
check('the results section is on it', str_contains($response->getContent(), 'Enrolments With No Marks'));
check('so is the collections section', str_contains($response->getContent(), 'Fees Billed'));

echo "\n== It is called Courses, as the rest of the system is ==\n";

// module_subject already translates to "Course|Courses" everywhere else; this
// page was the only one writing "Subjects" in English.
$data = $report();
$pipelineLabels = collect($data['pipeline'])->pluck('label')->implode(' ');

check('the pipeline says Courses', str_contains($pipelineLabels, 'Courses'));
check('and no longer says Subjects', !preg_match('/\bSubjects\b/', $pipelineLabels), $pipelineLabels);

$request = Illuminate\Http\Request::create('/admin/academic/health-status', 'GET');
$request->setLaravelSession(app('session.store'));
$pageHtml = $kernel->handle($request)->getContent();

check('the page column heading says Courses', str_contains($pageHtml, '>Courses<'));
check('the page has no Subjects heading left', !str_contains($pageHtml, '>Subjects<'));

echo "\n== The workbook ==\n";

$request = Illuminate\Http\Request::create('/admin/academic/health-status/excel', 'GET');
$request->setLaravelSession(app('session.store'));
$response = $kernel->handle($request);

check('the export downloads', $response->getStatusCode() === 200,
    'status ' . $response->getStatusCode());
check('as a spreadsheet', str_contains(
    (string) $response->headers->get('content-type'),
    'spreadsheetml'
));
check('named for the session and the day it was produced',
    (bool) preg_match('/academic-health-.*\d{4}-\d{2}-\d{2}\.xlsx/',
        (string) $response->headers->get('content-disposition')),
    (string) $response->headers->get('content-disposition'));

$file = sys_get_temp_dir() . '/academic-health-test.xlsx';

if (method_exists($response, 'sendContent')) {
    ob_start();
    $response->sendContent();
    file_put_contents($file, ob_get_clean());
} else {
    file_put_contents($file, $response->getContent());
}

$book = PhpOffice\PhpSpreadsheet\IOFactory::load($file);
$titles = collect($book->getAllSheets())->map(fn ($s) => $s->getTitle());

foreach (['Summary', 'Action Required', 'Configuration', 'Programmes',
          'Semester Matrix', 'Teaching Staff', 'Results Readiness', 'Finance'] as $expected) {
    check("it has a {$expected} sheet", $titles->contains($expected));
}

$summary = $book->getSheetByName('Summary')->toArray();
$flatSummary = collect($summary)->flatten()->filter()->implode(' | ');

// The school is the audience, so each sheet has to say whose report it is and
// when it was produced — a page pulled out of context is otherwise unreadable.
check('every sheet carries the institution and the date',
    collect($book->getAllSheets())->every(function ($sheet) {
        $rows = $sheet->toArray();

        return str_contains((string) ($rows[0][0] ?? ''), strtoupper(institution_name()))
            && str_contains((string) ($rows[3][0] ?? ''), 'Produced');
    }));

check('the summary states a verdict in words', str_contains($flatSummary, 'VERDICT'));
check('the verdict matches the findings',
    count($data['diagnostic_errors']) > 0
        ? str_contains($flatSummary, 'NOT READY')
        : str_contains($flatSummary, 'READY'));
check('the score is shown with its working',
    str_contains($flatSummary, 'Configuration readiness')
        && str_contains($flatSummary, 'Less: errors')
        && str_contains($flatSummary, 'Overall score'));
check('the workbook says Courses, not Subjects',
    str_contains($flatSummary, 'Courses') && !preg_match('/\bSubjects\b/', $flatSummary));

$findings = collect($book->getSheetByName('Action Required')->toArray())->flatten()->filter()->implode(' | ');

check('the findings sheet lists what must be corrected',
    str_contains($findings, 'MUST BE CORRECTED'));
check('and what was checked and found correct',
    str_contains($findings, 'CHECKED AND CORRECT'));
check('every current error appears in it',
    collect($data['diagnostic_errors'])->every(fn ($e) => str_contains($findings, $e['title'])));
check('and every current warning',
    collect($data['warnings'])->every(fn ($w) => str_contains($findings, $w['title'])));

echo "\n== The course catalogue lists the courses, not just how many ==\n";

$catalogue = $book->getSheetByName('Course Catalogue');
check('the workbook has a course catalogue', $catalogue !== null);

$catalogueRows = $catalogue->toArray();
$catalogueText = collect($catalogueRows)->flatten()->filter()->implode(' | ');

// A count cannot be checked. "15 courses" does not tell a head of department
// whether the fifteen are the right fifteen — only the codes and titles do.
check('it prints course codes and titles',
    str_contains($catalogueText, 'Course Code') && str_contains($catalogueText, 'Course Title'));

$sampleCourse = App\Models\Subject::where('status', '1')->whereNotNull('code')->first();
check('a real course appears in it',
    $sampleCourse === null || str_contains($catalogueText, $sampleCourse->code),
    'looked for ' . ($sampleCourse->code ?? '—'));

// Every programme must appear for every semester the school runs, present or
// absent. Listing only the semesters that happen to have an offering would hide
// exactly the gap this sheet exists to reveal.
$expectedBlocks = $data['kpis']['total_programs'] * $data['active_semesters']->count();
$blocks = count(array_filter(
    $catalogueRows,
    fn ($r) => str_contains((string) ($r[1] ?? ''), 'course(s)')
));

check('every programme is shown for every active semester',
    $blocks >= $expectedBlocks,
    $blocks . ' blocks for ' . $expectedBlocks . ' expected');

check('it totals what it listed',
    str_contains($catalogueText, 'Course offerings listed')
        && str_contains($catalogueText, 'Total credits offered'));

// The catalogue is where an empty semester becomes visible before anyone is
// enrolled into it.
$matrixGaps = collect($data['semester_matrix'])
    ->flatMap(fn ($row) => collect($row['semesters'])->filter(fn ($c) => $c['courses'] == 0))
    ->count();

check('a semester with no courses is called out',
    $matrixGaps === 0 || str_contains($catalogueText, 'SEMESTERS WITH NO COURSES'),
    $matrixGaps . ' empty semesters in the matrix');

echo "\n== A semester with students but no courses is an error ==\n";

DB::beginTransaction();
try {
    // Find a programme-semester pair that has no courses, then put a student in
    // it. Nobody can be taught or graded there, so it has to be raised.
    $gap = null;

    foreach ($data['semester_matrix'] as $row) {
        foreach ($row['semesters'] as $semesterId => $cell) {
            if ($cell['courses'] == 0) {
                $gap = [$row['program'], $semesterId];
                break 2;
            }
        }
    }

    if ($gap === null) {
        echo "  SKIP  every semester has courses, nothing to simulate\n";
    } else {
        [$programmeTitle, $semesterId] = $gap;
        $programme = App\Models\Program::where('title', $programmeTitle)->first();

        $enrolment = App\Models\StudentEnroll::where('program_id', $programme->id)->first();
        DB::table('student_enrolls')->where('id', $enrolment->id)
            ->update(['semester_id' => $semesterId]);

        check('it is raised as an error',
            collect($report()['diagnostic_errors'])->contains(fn ($e) => $e['category'] === 'Courses'));
    }
} finally {
    DB::rollBack();
}

check('and clears when nobody is enrolled there',
    collect($report()['diagnostic_errors'])->where('category', 'Courses')->isEmpty());

$programmes = $book->getSheetByName('Programmes')->toArray();
check('the programme sheet has a row per programme',
    count(array_filter($programmes, fn ($r) => is_numeric($r[0] ?? null)))
        === $data['kpis']['total_programs'],
    count(array_filter($programmes, fn ($r) => is_numeric($r[0] ?? null))) . ' rows');

@unlink($file);

echo "\n$passed passed, $failed failed\n";
