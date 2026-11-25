<?php
/**
 * Results and senate screens by semester type: "First Semester" of a session
 * is FIRST SEMESTER - Year 1 and Year 2 together. Each screen takes a type and
 * a multi-select of its semesters and shows one block per semester, with the
 * figures it always showed for that semester alone. Exports carry every
 * chosen semester. Old single-semester links still work. Rolled back.
 *
 *   php scripts/semester_type_results_test.php
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Semester;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$pass = 0; $fail = 0;
function check($label, $ok, $detail = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ok    $label\n"; }
    else { $fail++; echo "  FAIL  $label" . ($detail !== '' ? "   [$detail]" : '') . "\n"; }
}
app('session.store')->start();
$admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->where('status', '1')->first();
Auth::guard('web')->login($admin);

/** [status, body, response]; a file download's body is read from disk. */
$call = function (string $method, string $uri, array $params = []) use ($kernel) {
    if ($method !== 'GET') { $params['_token'] = csrf_token(); }
    $r = Illuminate\Http\Request::create($uri, $method, $params);
    $r->setLaravelSession(app('session.store'));
    $res = $kernel->handle($r);
    if ($res instanceof Symfony\Component\HttpFoundation\BinaryFileResponse) {
        $body = (string) file_get_contents($res->getFile()->getPathname());
    } elseif ($res instanceof Symfony\Component\HttpFoundation\StreamedResponse) {
        ob_start(); $res->sendContent(); $body = (string) ob_get_clean();
    } else {
        $body = (string) $res->getContent();
    }
    return [$res->getStatusCode(), $body, $res];
};

/** Sheet titles and all cell text of an .xlsx body. */
$xlsx = function (string $body): array {
    $tmp = tempnam(sys_get_temp_dir(), 'stx') . '.xlsx';
    file_put_contents($tmp, $body);
    try {
        $book = PhpOffice\PhpSpreadsheet\IOFactory::load($tmp);
    } catch (\Throwable $e) {
        @unlink($tmp);
        return [[], ''];
    }
    $titles = []; $text = '';
    foreach ($book->getAllSheets() as $sheet) {
        $titles[] = $sheet->getTitle();
        foreach ($sheet->toArray(null, false, false, false) as $row) {
            $text .= implode("\t", array_map('strval', $row)) . "\n";
        }
    }
    @unlink($tmp);
    return [$titles, $text];
};

$hasFilter = fn ($html) => str_contains($html, 'name="semester_type"') && str_contains($html, 'name="semester[]"');

$y1 = Semester::find(1);                        // FIRST SEMESTER - Y1 (session 2 has results)
$y2 = Semester::find(17);                       // First Semester - Y2 (results made below)
$second = Semester::find(2);                    // SECOND SEMESTER - Y1
$session = 2;
$program = 9;                                   // HND - SOFTWARE ENGINEERING
$faculty = (int) DB::table('programs')->where('id', $program)->value('faculty_id');
$y2Code = 'SWE246';                             // a course only the Year 2 block carries
$y2Subject = (int) DB::table('subjects')->where('code', $y2Code)->value('id');
$final = (int) DB::table('exam_types')->where('is_final', 1)->where('status', 1)->value('id');

DB::beginTransaction();
try {
    // ---- Year 2 results in session 2: three students of programme 9 -----
    $section = (int) DB::table('student_enrolls')->where(['session_id' => $session, 'semester_id' => 1, 'program_id' => $program])->value('section_id');
    $y2Enrolls = [];
    $es = DB::table('enroll_subjects')->where(['program_id' => $program, 'semester_id' => $y2->id, 'section_id' => $section])->value('id')
        ?? DB::table('enroll_subjects')->insertGetId(['program_id' => $program, 'semester_id' => $y2->id, 'section_id' => $section, 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('enroll_subject_subject')->insert(['enroll_subject_id' => $es, 'subject_id' => $y2Subject]);
    $marks = [72, 45, 61];
    foreach (DB::table('student_enrolls')->where(['session_id' => $session, 'semester_id' => 1, 'program_id' => $program])->orderBy('id')->limit(3)->get() as $i => $e) {
        $id = DB::table('student_enrolls')->insertGetId(['student_id' => $e->student_id, 'matricule' => $e->matricule, 'program_id' => $program,
            'session_id' => $session, 'semester_id' => $y2->id, 'section_id' => $section, 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $y2Enrolls[] = $id;
        DB::table('student_enroll_subject')->insert(['student_enroll_id' => $id, 'subject_id' => $y2Subject]);
        DB::table('exams')->insert(['student_enroll_id' => $id, 'subject_id' => $y2Subject, 'exam_type_id' => $final, 'date' => '2026-02-01',
            'attendance' => 1, 'marks' => 100, 'achieve_marks' => $marks[$i] * 0.7, 'contribution' => 70, 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('subject_markings')->insert(['student_enroll_id' => $id, 'subject_id' => $y2Subject, 'exam_marks' => $marks[$i] * 0.7,
            'assignments' => $marks[$i] * 0.3, 'total_marks' => $marks[$i], 'status' => 1, 'workflow_state' => 'published', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('academic_standings')->insert(['student_id' => $e->student_id, 'student_enroll_id' => $id, 'session_id' => $session, 'semester_id' => $y2->id,
            'program_id' => $program, 'faculty_id' => $faculty, 'gpa' => [3.6, 1.4, 2.7][$i], 'total_credits_registered' => 3, 'total_credits_earned' => $marks[$i] >= 50 ? 3 : 0,
            'courses_registered' => 1, 'courses_passed' => $marks[$i] >= 50 ? 1 : 0, 'courses_failed' => $marks[$i] >= 50 ? 0 : 1,
            'standing' => ['deans_list', 'academic_probation', 'good_standing'][$i], 'classified_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }
    check('fixture: three Year 2 results exist for session 2', count($y2Enrolls) === 3 && $y2Subject > 0);

    echo "\n== Results summary ==\n";
    $heads = fn ($html) => preg_match_all('/data-semester-block="(\d+)"/', $html, $m) ? array_map('intval', $m[1]) : [];
    $after = fn ($html, $id) => (string) substr($html, (int) strpos($html, 'data-semester-block="' . $id . '"'));
    $before = fn ($html, $id) => (string) substr($html, 0, (int) strpos($html, 'data-semester-block="' . $id . '"'));
    $rs = '/admin/exam/results-summary';
    $base = ['faculty' => $faculty, 'session' => $session];

    // ---- Dashboard (index) ----------------------------------------------
    [$st, $html] = $call('GET', $rs, $base);
    check('results dashboard opens with the semester type and semester[] multi-select', $st === 200 && $hasFilter($html), $st);
    check('results dashboard: no Year select is left in front of a single semester select', !str_contains($html, 'name="semester_year"') && !str_contains($html, 'name="semester" '));
    [$st, $html] = $call('GET', $rs, $base + ['semester_type' => 'FIRST SEMESTER']);
    check('results dashboard: "First Semester" shows a Year 1 and a Year 2 block', $st === 200 && $heads($html) === [$y1->id, $y2->id], json_encode($heads($html)));
    check('results dashboard: each block has its own charts', str_contains($html, 'id="gradeDistributionChart-s' . $y1->id . '"') && str_contains($html, 'id="gradeDistributionChart-s' . $y2->id . '"')
        && str_contains($html, "getElementById('passFailChart-s" . $y2->id . "')") && !str_contains($html, 'id="gradeDistributionChart"'));
    check('results dashboard: the Year 2 course is only in the Year 2 block', str_contains($after($html, $y2->id), $y2Code) && !str_contains($before($html, $y2->id), $y2Code));
    check('results dashboard: the Year 2 block counts its own 3 scripts', (bool) preg_match('/Total Scripts<\/h6>\s*<h3[^>]*>3<\/h3>/', $after($html, $y2->id)));
    check('results dashboard: both semesters are ticked in the filter', (bool) preg_match('/<option value="' . $y1->id . '"[^>]*selected/', $html) && (bool) preg_match('/<option value="' . $y2->id . '"[^>]*selected/', $html));
    [$st, $html] = $call('GET', $rs, $base + ['semester_type' => 'FIRST SEMESTER', 'semester' => [$y2->id]]);
    check('results dashboard: ticking Year 2 shows only it, laid out as a single semester', $st === 200 && $heads($html) === [] && str_contains($html, 'id="gradeDistributionChart"') && str_contains($html, $y2Code));
    [$st, $html] = $call('GET', $rs, $base + ['semester' => $y1->id]);
    $y1Scripts = DB::table('subject_markings')->join('student_enrolls', 'student_enrolls.id', '=', 'subject_markings.student_enroll_id')
        ->join('programs', 'programs.id', '=', 'student_enrolls.program_id')->where('programs.faculty_id', $faculty)
        ->where('student_enrolls.session_id', $session)->where('student_enrolls.semester_id', $y1->id)->where('subject_markings.workflow_state', 'published')->count();
    check('results dashboard: an old single-semester link renders as before', $st === 200 && $heads($html) === [] && str_contains($html, 'id="gradeDistributionChart"')
        && !str_contains($html, $y2Code) && (bool) preg_match('/Total Scripts<\/h6>\s*<h3[^>]*>' . number_format($y1Scripts) . '<\/h3>/', $html), $y1Scripts);
    [$st, $html] = $call('GET', $rs, ['faculty' => $faculty, 'session' => 5, 'semester' => [$y1->id, $second->id]]);
    check('results dashboard: of several semesters one with nothing to show is named, not drawn', $st === 200 && str_contains($html, 'semester-blocks-skipped') && str_contains($html, e($second->title))
        && !str_contains($html, 'data-semester-block="' . $second->id . '"'));

    // ---- Department report ----------------------------------------------
    [$st, $html] = $call('GET', $rs . '/department-report', $base);
    check('department report opens with the semester type and semester[] multi-select', $st === 200 && $hasFilter($html), $st);
    [$st, $html] = $call('GET', $rs . '/department-report', $base + ['semester_type' => 'FIRST SEMESTER']);
    check('department report: "First Semester" shows a Year 1 and a Year 2 block', $st === 200 && $heads($html) === [$y1->id, $y2->id], json_encode($heads($html)));
    check('department report: each block is its own printable report, headed by its semester', str_contains($html, 'id="results-table-s' . $y1->id . '"') && str_contains($html, 'id="results-table-s' . $y2->id . '"')
        && str_contains($after($html, $y2->id), '<strong>SEMESTER:</strong> ' . e($y2->title)) && str_contains($before($html, $y2->id), '<strong>SEMESTER:</strong> ' . e($y1->title)));
    check('department report: the Year 2 course is only in the Year 2 block', str_contains($after($html, $y2->id), $y2Code) && !str_contains($before($html, $y2->id), $y2Code));
    [$st, $html] = $call('GET', $rs . '/department-report', $base + ['semester_type' => 'FIRST SEMESTER', 'semester' => [$y2->id]]);
    check('department report: ticking Year 2 shows only it', $st === 200 && $heads($html) === [] && str_contains($html, 'id="results-table"') && str_contains($html, $y2Code)
        && !str_contains($html, '<strong>SEMESTER:</strong> ' . e($y1->title)));
    [$st, $html] = $call('GET', $rs . '/department-report', $base + ['semester' => $y1->id]);
    check('department report: an old single-semester link renders as before', $st === 200 && $heads($html) === [] && str_contains($html, 'id="results-table"')
        && str_contains($html, '<strong>SEMESTER:</strong> ' . e($y1->title)) && !str_contains($html, $y2Code) && str_contains($html, 'Print Report'));

    // ---- Faculty report --------------------------------------------------
    [$st, $html] = $call('GET', $rs . '/faculty-report', $base);
    check('faculty report opens with the semester type and semester[] multi-select', $st === 200 && $hasFilter($html), $st);
    [$st, $html] = $call('GET', $rs . '/faculty-report', $base + ['semester_type' => 'FIRST SEMESTER']);
    check('faculty report: "First Semester" shows a Year 1 and a Year 2 block', $st === 200 && $heads($html) === [$y1->id, $y2->id], json_encode($heads($html)));
    check('faculty report: each block has its own title and charts', str_contains($after($html, $y2->id), strtoupper($y2->title) . ' RESULTS')
        && str_contains($before($html, $y2->id), strtoupper($y1->title) . ' RESULTS')
        && str_contains($html, "getElementById('departmentPerformanceChart-s" . $y1->id . "')") && str_contains($html, "getElementById('departmentPerformanceChart-s" . $y2->id . "')"));
    [$st, $html] = $call('GET', $rs . '/faculty-report', $base + ['semester_type' => 'FIRST SEMESTER', 'semester' => [$y2->id]]);
    check('faculty report: ticking Year 2 shows only it', $st === 200 && $heads($html) === [] && str_contains($html, strtoupper($y2->title) . ' RESULTS')
        && !str_contains($html, strtoupper($y1->title) . ' RESULTS') && str_contains($html, "getElementById('departmentPerformanceChart')"));
    [$st, $html] = $call('GET', $rs . '/faculty-report', $base + ['semester' => $y1->id]);
    check('faculty report: an old single-semester link renders as before', $st === 200 && $heads($html) === [] && str_contains($html, strtoupper($y1->title) . ' RESULTS')
        && str_contains($html, 'id="faculty-results-table"') && !str_contains($html, strtoupper($y2->title) . ' RESULTS'));

    // ---- Student results summary + its Excel ----------------------------
    $y2Matric = DB::table('student_enrolls')->where('id', $y2Enrolls[0])->value('matricule');
    $sbase = ['faculty' => $faculty, 'program' => $program, 'session' => $session];
    $srs = $rs . '/student-results-summary';
    [$st, $html] = $call('GET', $srs, $sbase);
    check('student results summary opens with the semester type and semester[] multi-select', $st === 200 && $hasFilter($html), $st);
    [$st, $html] = $call('GET', $srs, $sbase + ['semester_type' => 'FIRST SEMESTER']);
    check('student results: "First Semester" shows a Year 1 and a Year 2 block', $st === 200 && $heads($html) === [$y1->id, $y2->id], json_encode($heads($html)));
    check('student results: each block is headed and laid out for its own semester', str_contains($html, 'id="results-matrix-s' . $y1->id . '"') && str_contains($html, 'id="results-matrix-s' . $y2->id . '"')
        && str_contains($after($html, $y2->id), e($y2->title) . ' Results') && str_contains($before($html, $y2->id), e($y1->title) . ' Results'));
    check('student results: the Year 2 block lists 3 students, each with its own export', substr_count($after($html, $y2->id), 'name="semester" value="' . $y2->id . '"') === 1
        && str_contains($after($html, $y2->id), $y2Matric) && substr_count($before($html, $y2->id), 'name="semester" value="' . $y1->id . '"') === 1);
    check('student results: one export carries every semester shown', str_contains($html, 'name="semester[]" value="' . $y1->id . '"') && str_contains($html, 'name="semester[]" value="' . $y2->id . '"'));
    [$st, $html] = $call('GET', $srs, $sbase + ['semester_type' => 'FIRST SEMESTER', 'semester' => [$y2->id]]);
    check('student results: ticking Year 2 shows only it', $st === 200 && $heads($html) === [] && str_contains($html, 'id="results-matrix"') && str_contains($html, e($y2->title) . ' Results')
        && !str_contains($html, 'name="semester[]" value='));
    [$st, $html] = $call('GET', $srs, $sbase + ['semester' => $y1->id]);
    check('student results: an old single-semester link renders as before', $st === 200 && $heads($html) === [] && str_contains($html, 'id="results-matrix"')
        && str_contains($html, e($y1->title) . ' Results') && str_contains($html, 'name="semester" value="' . $y1->id . '"') && !str_contains($html, 'name="semester[]" value='));

    $export = $rs . '/export-student-results-summary';
    [$st, $body, $res] = $call('POST', $export, $sbase + ['semester' => $y1->id, 'section' => '0']);
    [$titles, $text] = $xlsx($body);
    check('student results Excel: one semester gives the RESULTS sheet it always did', $st === 200 && $titles === ['RESULTS'] && str_contains($text, $y1->title)
        && !str_contains($text, $y2->title), $st . ' ' . json_encode($titles));
    check('student results Excel: and it is a download, not an exit()', str_contains((string) $res->headers->get('Content-Disposition'), 'Student_Results_Summary_'));
    [$st, $body] = $call('POST', $export, $sbase + ['semester_type' => 'FIRST SEMESTER', 'semester' => [$y1->id, $y2->id], 'section' => '0']);
    [$titles, $text] = $xlsx($body);
    check('student results Excel: Year 1 and Year 2 give a sheet each, named after the semester', $st === 200 && $titles === [$y1->title, $y2->title], $st . ' ' . json_encode($titles));
    check('student results Excel: and each semester\'s students are in it', str_contains($text, $y2->title) && str_contains($text, $y1->title) && str_contains($text, $y2Matric));
    [$st, $body] = $call('POST', $export, $sbase + ['semester_type' => 'FIRST SEMESTER', 'section' => '0']);
    [$titles] = $xlsx($body);
    check('student results Excel: a type alone exports every semester of it', $st === 200 && $titles === [$y1->title, $y2->title], json_encode($titles));


    echo "
== Senate deliberation ==
";
    // ---- Senate deliberation dashboard ---------------------------------
    $blockHeads = fn ($html) => preg_match_all('/data-semester-block="(\d+)"/', $html, $m) ? array_map('intval', $m[1]) : [];
    [$st, $html] = $call('GET', '/admin/exam/senate-deliberation', ['session' => $session]);
    check('senate dashboard opens with the semester type and semester[] multi-select', $st === 200 && $hasFilter($html), $st);
    check('and the type list leaves resit types out', !str_contains($html, '>First Resit Semester<'));
    [$st, $html] = $call('GET', '/admin/exam/senate-deliberation', ['session' => $session, 'semester_type' => 'FIRST SEMESTER']);
    check('dashboard: "First Semester" shows a Year 1 and a Year 2 block', $st === 200 && $blockHeads($html) === [$y1->id, $y2->id], json_encode($blockHeads($html)));
    check('dashboard: each block has its own charts and create-deliberation modal',
        str_contains($html, 'id="publishingChart-s' . $y2->id . '"') && str_contains($html, 'id="publishingChart-s' . $y1->id . '"')
        && str_contains($html, 'id="createDelibModal-s' . $y2->id . '"') && str_contains($html, 'name="semester_id" value="' . $y2->id . '"'));
    [$st, $html] = $call('GET', '/admin/exam/senate-deliberation', ['session' => $session, 'semester_type' => 'FIRST SEMESTER', 'semester' => [$y2->id]]);
    check('dashboard: ticking Year 2 shows only it, laid out as a single semester', $st === 200 && $blockHeads($html) === [] && str_contains($html, 'id="publishingChart"')
        && str_contains($html, 'name="semester" value="' . $y2->id . '"') && !str_contains($html, 'name="semester" value="' . $y1->id . '"'));
    [$st, $html] = $call('GET', '/admin/exam/senate-deliberation', ['session' => $session, 'semester' => $y1->id]);
    check('dashboard: an old single-semester link renders as before', $st === 200 && $blockHeads($html) === [] && str_contains($html, 'id="publishingChart"')
        && str_contains($html, 'name="semester" value="' . $y1->id . '"') && str_contains($html, 'Total Students'));

    // ---- Academic standings + its Excel ---------------------------------
    $y2Matricule = DB::table('student_enrolls')->where('id', $y2Enrolls[0])->value('matricule');
    [$st, $html] = $call('GET', '/admin/exam/senate-deliberation/academic-standings', ['session' => $session]);
    check('academic standings opens with the semester type and semester[] multi-select', $st === 200 && $hasFilter($html), $st);
    [$st, $html] = $call('GET', '/admin/exam/senate-deliberation/academic-standings', ['session' => $session, 'semester_type' => 'FIRST SEMESTER']);
    check('standings: "First Semester" shows a Year 1 and a Year 2 block', $st === 200 && $blockHeads($html) === [$y1->id, $y2->id], json_encode($blockHeads($html)));
    check('standings: each block keeps its own table and re-classify for its own semester',
        str_contains($html, 'id="standingsTable-s' . $y1->id . '"') && str_contains($html, 'id="standingsTable-s' . $y2->id . '"')
        && str_contains($html, 'name="semester" value="' . $y1->id . '"') && str_contains($html, 'name="semester" value="' . $y2->id . '"'));
    check('standings: the export form carries both semesters', str_contains($html, 'name="semester[]" value="' . $y1->id . '"') && str_contains($html, 'name="semester[]" value="' . $y2->id . '"'));
    $y2Block = substr($html, strpos($html, 'data-semester-block="' . $y2->id . '"'));
    check('standings: the Year 2 block lists the Year 2 students and only them', str_contains($y2Block, $y2Matricule) && str_contains($y2Block, 'of 3 students'));
    [$st, $html] = $call('GET', '/admin/exam/senate-deliberation/academic-standings', ['session' => $session, 'semester_type' => 'FIRST SEMESTER', 'semester' => [$y2->id]]);
    check('standings: ticking Year 2 shows only it', $st === 200 && $blockHeads($html) === [] && str_contains($html, 'id="standingsTable"')
        && str_contains($html, 'name="semester" value="' . $y2->id . '"') && !str_contains($html, 'name="semester" value="' . $y1->id . '"'));
    [$st, $html] = $call('GET', '/admin/exam/senate-deliberation/academic-standings', ['session' => $session, 'semester' => $y1->id]);
    $y1Count = DB::table('academic_standings')->where(['session_id' => $session, 'semester_id' => $y1->id])->count();
    check('standings: an old single-semester link renders as before', $st === 200 && $blockHeads($html) === [] && str_contains($html, 'id="standingsTable"')
        && str_contains($html, 'of ' . $y1Count . ' students') && !str_contains($html, 'name="semester[]" value='));

    [$st, $body] = $call('POST', '/admin/exam/senate-deliberation/export-excel', ['session' => $session, 'semester' => $y1->id, 'faculty' => '0', 'program' => '0', 'standing' => 'all']);
    [$titles, $text] = $xlsx($body);
    check('standings Excel: one semester gives the one sheet it always did', $st === 200 && $titles === ['Academic Standings'] && str_contains($text, $y1->title), json_encode($titles));
    [$st, $body] = $call('POST', '/admin/exam/senate-deliberation/export-excel', ['session' => $session, 'semester_type' => 'FIRST SEMESTER', 'semester' => [$y1->id, $y2->id], 'faculty' => '0', 'program' => '0', 'standing' => 'all']);
    [$titles, $text] = $xlsx($body);
    check('standings Excel: Year 1 and Year 2 give a sheet each, named after the semester', $st === 200 && $titles === [$y1->title, $y2->title], $st . ' ' . json_encode($titles));
    check('standings Excel: and each sheet holds its own semester\'s students', str_contains($text, $y2Matricule) && str_contains($text, $y2->title) && str_contains($text, $y1->title));
    [$st, $body] = $call('POST', '/admin/exam/senate-deliberation/export-excel', ['session' => $session, 'semester_type' => 'FIRST SEMESTER', 'faculty' => '0', 'program' => '0', 'standing' => 'all']);
    [$titles] = $xlsx($body);
    check('standings Excel: a type alone exports every semester of it', $st === 200 && $titles === [$y1->title, $y2->title], json_encode($titles));

    // ---- Comprehensive results preview + its Excel ----------------------
    [$st, $html] = $call('GET', '/admin/exam/senate-deliberation/results-preview', ['session' => $session]);
    check('results preview opens with the semester type and semester[] multi-select', $st === 200 && $hasFilter($html), $st);
    [$st, $html] = $call('GET', '/admin/exam/senate-deliberation/results-preview', ['session' => $session, 'semester_type' => 'FIRST SEMESTER']);
    check('preview: "First Semester" shows a Year 1 and a Year 2 block', $st === 200 && $blockHeads($html) === [$y1->id, $y2->id], json_encode($blockHeads($html)));
    check('preview: tabs, charts and modals are unique per block',
        substr_count($html, 'id="pane-overview-s' . $y1->id . '"') === 1 && substr_count($html, 'id="pane-overview-s' . $y2->id . '"') === 1
        && !str_contains($html, 'id="pane-overview"') && str_contains($html, "getElementById('caPublishingDonutChart-s" . $y2->id . "')")
        && str_contains($html, 'id="caPublishingDetailsModal-s' . $y2->id . '"'));
    $p1 = strpos($html, 'data-semester-block="' . $y1->id . '"');
    $p2 = strpos($html, 'data-semester-block="' . $y2->id . '"');
    $y1Preview = substr($html, $p1, $p2 - $p1);
    $y2Preview = substr($html, $p2);
    check('preview: the Year 2 course is in the Year 2 block and not in Year 1', str_contains($y2Preview, $y2Code) && !str_contains($y1Preview, $y2Code));
    check('preview: the Year 2 block counts its own 3 scripts', (bool) preg_match('/kpi-value">3<\/div>\s*<div class="kpi-label">Total Scripts/', $y2Preview));
    check('preview: the export link carries both semesters', str_contains($html, 'semester%5B0%5D=' . $y1->id) && str_contains($html, 'semester%5B1%5D=' . $y2->id));
    [$st, $html] = $call('GET', '/admin/exam/senate-deliberation/results-preview', ['session' => $session, 'semester_type' => 'FIRST SEMESTER', 'semester' => [$y2->id]]);
    check('preview: ticking Year 2 shows only it, laid out as before', $st === 200 && $blockHeads($html) === [] && str_contains($html, 'id="pane-overview"') && str_contains($html, $y2Code));
    [$st, $html] = $call('GET', '/admin/exam/senate-deliberation/results-preview', ['session' => $session, 'semester' => $y1->id]);
    check('preview: an old single-semester link renders as before', $st === 200 && $blockHeads($html) === [] && str_contains($html, 'id="pane-overview"')
        && !str_contains($html, $y2Code) && str_contains($html, 'semester=' . $y1->id));
    [$st, $json] = $call('GET', '/admin/exam/senate-deliberation/results-preview/student-matrix', ['session' => $session, 'semester' => $y2->id, 'program' => $program]);
    check('preview: the student-matrix call stays per semester', $st === 200 && str_contains($json, $y2Code) && str_contains($json, $y2Matricule), $st);

    [$st, $body] = $call('GET', '/admin/exam/senate-deliberation/results-preview/export-excel', ['session' => $session, 'semester' => $y1->id]);
    [$titles1, $text] = $xlsx($body);
    check('preview Excel: one semester gives the workbook it always did', $st === 200 && in_array('Overview', $titles1, true) && in_array('Reference & Legend', $titles1, true) && !str_contains($text, $y2Code), json_encode($titles1));
    [$st, $body] = $call('GET', '/admin/exam/senate-deliberation/results-preview/export-excel', ['session' => $session, 'semester_type' => 'FIRST SEMESTER', 'semester' => [$y1->id, $y2->id]]);
    [$titles, $text] = $xlsx($body);
    check('preview Excel: Year 1 and Year 2 each get their own set of sheets', $st === 200 && in_array('Y1 Overview', $titles, true) && in_array('Y2 Overview', $titles, true)
        && in_array('Y1 Course Results', $titles, true) && in_array('Y2 Course Results', $titles, true), json_encode($titles));
    check('preview Excel: and both semesters\' results are in it', str_contains($text, $y2Code) && str_contains($text, $y1->title) && str_contains($text, $y2->title));

    // ---- Deliberations list ---------------------------------------------
    $delib = DB::table('senate_deliberations')->where(['session_id' => $session, 'semester_id' => $y1->id])->first();
    [$st, $html] = $call('GET', '/admin/exam/senate-deliberation/deliberations', []);
    check('deliberations list opens with the semester type and semester[] multi-select', $st === 200 && $hasFilter($html), $st);
    check('deliberations: a new deliberation is still made for one semester', str_contains($html, 'name="semester_id"') && !str_contains($html, 'name="semester_id[]"'));
    if ($delib) {
        [$st, $html] = $call('GET', '/admin/exam/senate-deliberation/deliberations', ['semester_type' => 'FIRST SEMESTER']);
        check('deliberations: the "First Semester" filter keeps the Year 1 deliberation', $st === 200 && str_contains($html, $delib->meeting_number));
        [$st, $html] = $call('GET', '/admin/exam/senate-deliberation/deliberations', ['semester_type' => 'FIRST SEMESTER', 'semester' => [$y2->id]]);
        check('deliberations: ticking Year 2 leaves it out', $st === 200 && !str_contains($html, $delib->meeting_number));
        [$st, $html] = $call('GET', '/admin/exam/senate-deliberation/deliberations', ['semester' => $y1->id]);
        check('deliberations: an old single-semester link still filters', $st === 200 && str_contains($html, $delib->meeting_number));
        [$st, $html] = $call('GET', '/admin/exam/senate-deliberation/deliberations', ['semester_type' => 'SECOND SEMESTER']);
        check('deliberations: another type leaves it out', $st === 200 && !str_contains($html, $delib->meeting_number));
    }
    [$st, $html] = $call('GET', '/admin/exam/senate-deliberation', ['session' => 5, 'semester' => [$y1->id, $second->id]]);
    check('dashboard: of several semesters one without anything to show is named, not drawn', $st === 200 && str_contains($html, 'id="publishingChart"')
        && !str_contains($html, 'name="semester" value="' . $second->id . '"') && str_contains($html, 'semester-blocks-skipped') && str_contains($html, e($second->title)), json_encode($blockHeads($html)));


    echo "\n== Exam publishing (multi-programme mode) ==\n";
    $pubUri = '/admin/exam/exam-publishing';
    $pubIds = function (string $html, string $prefix) {
        preg_match_all('/id="(' . preg_quote($prefix, '/') . '[^"]*)"/', $html, $m);
        return $m[1];
    };
    $y1Code = 'GECC101';                         // a Year 1 course of programme 9
    // ($y2Code is also offered, without marks, by another programme's Year 1,
    // so which semester a listing holds is read from data-semester-id.)

    [$st, $html] = $call('GET', $pubUri, ['multi' => 1]);
    check('publishing: multi mode opens with a semester type and a multi-select of semesters', $st === 200 && $hasFilter($html), $st);
    check('publishing: single mode still has its one-semester picker', str_contains($html, 'name="semester" id="semester"'));

    [$st, $html] = $call('GET', $pubUri, ['multi' => 1, 'session' => $session, 'type' => $final, 'semester_type' => 'FIRST SEMESTER']);
    check('publishing: "First Semester" shows a block for Year 1 and one for Year 2', $st === 200
        && str_contains($html, 'data-semester-block="' . $y1->id . '"') && str_contains($html, 'data-semester-block="' . $y2->id . '"'), $st);
    check('publishing: each block is headed by its semester', str_contains($html, e($y1->title)) && str_contains($html, e($y2->title)));
    check('publishing: Year 1 and Year 2 courses are both listed', str_contains($html, $y1Code) && str_contains($html, $y2Code));
    check('publishing: the Second Semester is not part of it', !str_contains($html, 'data-semester-block="' . $second->id . '"'));
    $bodies = $pubIds($html, 'programBody');
    check('publishing: accordion ids are unique across the semester blocks', count($bodies) > 1 && count($bodies) === count(array_unique($bodies)), count($bodies));
    check('publishing: a checkbox knows its semester', str_contains($html, 'data-semester-id="' . $y2->id . '"') && str_contains($html, 'data-semester-id="' . $y1->id . '"'));
    check('publishing: both semesters\' semester types are ticked in the filter', (bool) preg_match('/<option value="' . $y2->id . '"[^>]*selected/', $html) && (bool) preg_match('/<option value="' . $y1->id . '"[^>]*selected/', $html));

    [$st, $html] = $call('GET', $pubUri, ['multi' => 1, 'session' => $session, 'type' => $final, 'semester_type' => 'FIRST SEMESTER', 'semester' => [$y2->id]]);
    check('publishing: ticking one semester shows only it, as a single listing', $st === 200 && !str_contains($html, 'data-semester-block=')
        && str_contains($html, $y2Code) && !str_contains($html, $y1Code) && !str_contains($html, 'data-semester-id="' . $y1->id . '"')
        && str_contains($html, 'id="facultyAccordion"'), $st);

    [$st, $html] = $call('GET', $pubUri, ['multi' => 1, 'session' => $session, 'type' => $final, 'semester' => $y1->id]);
    check('publishing: an old multi=1&semester=1 link still renders as before', $st === 200 && !str_contains($html, 'data-semester-block=')
        && str_contains($html, 'id="facultyAccordion"') && str_contains($html, $y1Code) && !str_contains($html, 'data-semester-id="' . $y2->id . '"')
        && str_contains($html, 'Multi-Program Publishing Mode'), $st);

    // Bulk transition across two semesters: states carry their own semester.
    $hasMarksFor = fn ($s) => DB::table('exams')->join('student_enrolls', 'student_enrolls.id', '=', 'exams.student_enroll_id')
        ->where('exams.subject_id', $s->subject_id)->where('exams.exam_type_id', $s->exam_type_id)->where('exams.attendance', 1)->whereNotNull('exams.achieve_marks')
        ->where('student_enrolls.program_id', $s->program_id)->where('student_enrolls.session_id', $s->session_id)->where('student_enrolls.semester_id', $s->semester_id)->exists();
    $y1State = DB::table('exam_publishing_states')->where(['session_id' => $session, 'semester_id' => $y1->id, 'exam_type_id' => $final, 'program_id' => $program])
        ->get()->first(fn ($s) => $hasMarksFor($s));
    $y2State = DB::table('exam_publishing_states')->where(['session_id' => $session, 'semester_id' => $y2->id, 'exam_type_id' => $final, 'subject_id' => $y2Subject])->first();
    if (!$y2State) {
        $id = DB::table('exam_publishing_states')->insertGetId(['program_id' => $program, 'session_id' => $session, 'semester_id' => $y2->id, 'section_id' => null,
            'subject_id' => $y2Subject, 'exam_type_id' => $final, 'workflow_state' => 'draft', 'created_at' => now(), 'updated_at' => now()]);
        $y2State = DB::table('exam_publishing_states')->find($id);
    }
    check('publishing: fixture has a Year 1 and a Year 2 state with marks', $y1State && $y2State && $hasMarksFor($y2State));
    if ($y1State && $y2State) {
        DB::table('exam_publishing_states')->whereIn('id', [$y1State->id, $y2State->id])->update(['workflow_state' => 'draft']);
        [$st] = $call('POST', '/admin/exam/exam-publishing/bulk-transition-multi', ['state' => 'submitted', 'state_ids' => [$y1State->id, $y2State->id],
            'session_id' => $session, 'semester_id' => $y1->id, 'exam_type_id' => $final]);
        $now = DB::table('exam_publishing_states')->whereIn('id', [$y1State->id, $y2State->id])->pluck('workflow_state')->unique()->values()->all();
        check('publishing: a bulk transition moves courses of both semesters', $st === 302 && $now === ['submitted'], $st . ' ' . implode(',', $now));

        DB::table('exam_publishing_states')->whereIn('id', [$y1State->id, $y2State->id])->update(['workflow_state' => 'draft']);
        [$st] = $call('POST', '/admin/exam/exam-publishing/bulk-transition-multi', ['state' => 'submitted', 'state_ids' => [$y1State->id, $y2State->id],
            'session_id' => $session, 'semester_id' => [$y1->id, $y2->id], 'exam_type_id' => $final]);
        $now = DB::table('exam_publishing_states')->whereIn('id', [$y1State->id, $y2State->id])->pluck('workflow_state')->unique()->values()->all();
        check('publishing: it accepts several semester ids (or none) without failing validation', $st === 302 && $now === ['submitted'], $st . ' ' . implode(',', $now));
    }
} catch (\Throwable $e) {
    $fail++;
    echo '  FAIL  exception: ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
} finally {
    DB::rollBack();
}
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
