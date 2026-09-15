<?php
/**
 * The admissions board report.
 *
 * Built so the board can decide which programmes to open: every programme's
 * first, second and third choices, faculty totals, a signal against a minimum
 * class size, and — for programmes that fall short — where their applicants
 * would go instead.
 *
 * What matters most, and is tested hardest:
 *
 *  - It reports on exactly the applications the list shows for the same
 *    filters. The list's filters were moved into ApplicationListFilter so both
 *    read one definition; this checks the two agree application for
 *    application.
 *  - Its figures are the database's. Every programme's counts are checked
 *    against independent SQL.
 *  - A programme nobody chose still appears. That is the programme the board
 *    most needs to see.
 *  - In the workbook, a zero is a zero and not a blank cell.
 *
 * Nothing here writes to the database.
 *
 * Usage: php scripts/application_report_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Admin\ApplicationReportController;
use App\Services\ApplicationDemandReport;
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

$kernel = app(Illuminate\Contracts\Http\Kernel::class);
$controller = app(ApplicationReportController::class);
$superAdmin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))
    ->where('status', '1')
    ->first() ?: App\User::where('is_admin', 1)->where('status', '1')->first();

$start = '2000-01-01';
$end = now()->toDateString();
$range = "start_date={$start}&end_date={$end}";

$report = fn (string $query) => $controller->reportData(Request::create('/report?' . $query, 'GET'));

/** GET a URL through the full kernel, as the signed-in user. */
$get = function (string $path) use ($kernel) {
    $request = Request::create($path, 'GET');
    $request->setLaravelSession(app('session.store'));

    return $kernel->handle($request);
};

$submitted = fn () => DB::table('applications')
    ->where('stage', '!=', 'draft')
    ->whereRaw('DATE(COALESCE(apply_date, created_at)) BETWEEN ? AND ?', [$start, $end]);

$drafts = fn () => DB::table('applications')
    ->where('stage', 'draft')
    ->whereRaw('DATE(COALESCE(apply_date, created_at)) BETWEEN ? AND ?', [$start, $end]);

// ---------------------------------------------------------------------------

echo "\n== One definition of which applications ==\n";

$applicationController = file_get_contents(__DIR__ . '/../app/Http/Controllers/Admin/ApplicationController.php');

check('the list reads its filters from ApplicationListFilter',
    str_contains($applicationController, 'ApplicationListFilter::fromRequest'));
check('and no longer keeps a copy of its own',
    !str_contains($applicationController, "whereRaw('DATE(COALESCE(apply_date, created_at)) >= ?'"));

Auth::guard('web')->login($superAdmin);

foreach ([
    'all submitted' => "status=&{$range}&degree_type=0&session=0&program=0",
    'approved' => "status=2&{$range}",
    'drafts' => "status=draft&{$range}",
    'one programme' => "program=9&status=&{$range}",
    'an applicant search' => "applicant=a&{$range}",
] as $label => $query) {
    $html = $get('/admin/admission/application?' . $query)->getContent();
    preg_match_all('#<a href="[^"]*/admin/admission/application/(\d+)" class="btn btn-icon btn-success btn-sm">#', $html, $ids);

    $listed = DB::table('applications')->whereIn('id', $ids[1] ?: [0])->pluck('registration_no')->map(fn ($v) => (string) $v)->sort()->values()->all();
    $reported = collect($report($query)['register'])->pluck('registration_no')->map(fn ($v) => (string) $v)->sort()->values()->all();

    check("{$label}: the report covers exactly the applications the list shows",
        $listed === $reported,
        count($listed) . ' listed, ' . count($reported) . ' reported');
}

// ---------------------------------------------------------------------------

echo "\n== The figures are the database's ==\n";

$all = $report("status=&{$range}");

check('the application total matches the database',
    $all['totals']['applications'] === $submitted()->count(),
    $all['totals']['applications'] . ' vs ' . $submitted()->count());

$mismatches = [];

foreach ($all['programmes'] as $row) {
    $sql = [
        $submitted()->where('first_program_choice_id', $row['id'])->count(),
        $submitted()->where('second_program_choice_id', $row['id'])->count(),
        $submitted()->where('third_program_choice_id', $row['id'])->count(),
    ];

    if ($sql !== [$row['first'], $row['second'], $row['third']]) {
        $mismatches[] = $row['code'] . ' report ' . json_encode([$row['first'], $row['second'], $row['third']]) . ' sql ' . json_encode($sql);
    }
}

check('every programme\'s 1st, 2nd and 3rd choices match the database',
    $all['programmes'] !== [] && $mismatches === [], implode('; ', $mismatches));

if ($submitted()->whereNull('first_program_choice_id')->count() === 0) {
    check('first choices add up to the number of applications',
        array_sum(array_column($all['programmes'], 'first')) === $all['totals']['applications']);
}

$activeProgrammes = DB::table('programs')->where('status', 1)->pluck('id')->map(fn ($v) => (int) $v)->sort()->values()->all();
$reportedProgrammes = collect($all['programmes'])->pluck('id')->sort()->values()->all();

check('every active programme is in the report, whether or not anyone chose it',
    array_values(array_intersect($activeProgrammes, $reportedProgrammes)) === $activeProgrammes,
    'missing: ' . implode(',', array_diff($activeProgrammes, $reportedProgrammes)));

$unchosen = collect($all['programmes'])->where('mentions', 0);
check('a programme nobody chose is marked as having no applicants',
    $unchosen->every(fn ($row) => $row['signal'] === ApplicationDemandReport::NONE)
    && $unchosen->count() === count($all['signals'][ApplicationDemandReport::NONE]),
    $unchosen->pluck('code')->implode(', '));

foreach ($all['faculties'] as $faculty) {
    $programmeFirst = collect($all['programmes'])->where('faculty_id', $faculty['id'])->sum('first');

    if ($faculty['first'] !== $programmeFirst || $faculty['any'] < $faculty['first']) {
        check("faculty {$faculty['code']} adds up", false,
            "first {$faculty['first']} vs programmes {$programmeFirst}, any {$faculty['any']}");
        continue;
    }
}
check('each faculty\'s first choices are the sum of its programmes\', and "any choice" is never fewer',
    collect($all['faculties'])->every(function ($faculty) use ($all) {
        return $faculty['first'] === collect($all['programmes'])->where('faculty_id', $faculty['id'])->sum('first')
            && $faculty['any'] >= $faculty['first'];
    }));

check('unfinished drafts are counted beside the applications, not in them',
    $all['drafts_counted'] === true && $all['totals']['drafts'] === $drafts()->count(),
    $all['totals']['drafts'] . ' vs ' . $drafts()->count());

$draftMismatch = collect($all['programmes'])->first(
    fn ($row) => $row['drafts'] !== $drafts()->where('first_program_choice_id', $row['id'])->count()
);
check('and each programme\'s drafts match the database', $draftMismatch === null, (string) optional((object) $draftMismatch)->code);

$onlyDrafts = $report("status=draft&{$range}");
check('asked for drafts, it reports on drafts and does not count them twice',
    $onlyDrafts['drafts_counted'] === false && $onlyDrafts['totals']['applications'] === $drafts()->count());

// ---------------------------------------------------------------------------

echo "\n== The signal ==\n";

$signal = fn (int $first, int $second, int $mentions) => ApplicationDemandReport::signal(
    ['first' => $first, 'second' => $second, 'mentions' => $mentions],
    10
);

check('nobody chose it: no applicants', $signal(0, 0, 0) === ApplicationDemandReport::NONE);
check('exactly the minimum chose it first: enough', $signal(10, 0, 10) === ApplicationDemandReport::STRONG);
check('one short of the minimum, with second choices to spare: reachable', $signal(9, 1, 10) === ApplicationDemandReport::REACHABLE);
check('short even with second choices: below', $signal(5, 4, 12) === ApplicationDemandReport::BELOW);
check('chosen only third: below, not "no applicants"', $signal(0, 0, 1) === ApplicationDemandReport::BELOW);

check('the minimum defaults to 10', $all['min_class'] === ApplicationDemandReport::DEFAULT_MIN_CLASS);
check('a minimum of 0 is raised to 1', $report("status=&{$range}&min_class=0")['min_class'] === 1);
check('an absurd minimum is capped at 500', $report("status=&{$range}&min_class=99999")['min_class'] === 500);

$lower = $report("status=&{$range}&min_class=1");
check('lowering the minimum never leaves fewer programmes with enough',
    count($lower['signals'][ApplicationDemandReport::STRONG]) >= count($all['signals'][ApplicationDemandReport::STRONG]));

// ---------------------------------------------------------------------------

echo "\n== If a programme is not opened ==\n";

$redirected = collect($all['redirects'])->pluck('programme.id')->sort()->values()->all();
$shouldBe = collect($all['programmes'])
    ->filter(fn ($row) => $row['signal'] !== ApplicationDemandReport::STRONG && $row['first'] > 0)
    ->pluck('id')->sort()->values()->all();

check('every programme short of the minimum, with applicants, is covered — and none with enough',
    $redirected === $shouldBe, json_encode($redirected) . ' vs ' . json_encode($shouldBe));

$fallbacksRight = collect($all['redirects'])->every(function ($redirect) {
    $viable = collect($redirect['applicants'])->every(fn ($a) => $a['has_viable_fallback'] === (
        $a['second_signal'] === ApplicationDemandReport::STRONG || $a['third_signal'] === ApplicationDemandReport::STRONG
    ));

    return $viable
        && count($redirect['applicants']) === $redirect['programme']['first']
        && $redirect['stranded'] === collect($redirect['applicants'])->where('has_viable_fallback', false)->count();
});

check('each applicant is listed, and "has a fallback with enough" follows their 2nd and 3rd choices',
    $all['redirects'] === [] || $fallbacksRight);

// ---------------------------------------------------------------------------

echo "\n== The downloads ==\n";

$query = "status=&{$range}&min_class=10";

$pdf = $get('/admin/admission/application-report/pdf?' . $query);
check('the PDF downloads', $pdf->getStatusCode() === 200 && str_starts_with((string) $pdf->getContent(), '%PDF-'),
    (string) $pdf->getStatusCode());
check('as an attachment with a dated name',
    str_contains((string) $pdf->headers->get('Content-Disposition'), 'admissions-demand-report-' . now()->format('Y-m-d') . '.pdf'));

$excel = $get('/admin/admission/application-report/excel?' . $query);
$excelBytes = $excel instanceof Symfony\Component\HttpFoundation\BinaryFileResponse
    ? file_get_contents($excel->getFile()->getPathname())
    : (string) $excel->getContent();

check('the workbook downloads', $excel->getStatusCode() === 200 && str_starts_with($excelBytes, 'PK'),
    (string) $excel->getStatusCode());

$path = tempnam(sys_get_temp_dir(), 'report') . '.xlsx';
file_put_contents($path, $excelBytes);
$book = PhpOffice\PhpSpreadsheet\IOFactory::load($path);

check('it has one tab per subject',
    $book->getSheetNames() === ['Summary', 'Programmes', 'Faculties', 'If not opened', '1st vs 2nd choice', 'Register'],
    implode(', ', $book->getSheetNames()));

$register = $book->getSheetByName('Register');
check('the register has a row for every application',
    $register->getHighestDataRow() - 8 === $all['totals']['applications'],
    ($register->getHighestDataRow() - 8) . ' vs ' . $all['totals']['applications']);

// In the Programmes tab, column E is "1st choice". A programme with none must
// carry a 0 there, not an empty cell.
$programmesSheet = $book->getSheetByName('Programmes');
$zeroRow = null;

for ($row = 9; $row <= $programmesSheet->getHighestDataRow(); $row++) {
    if ((int) $programmesSheet->getCell('E' . $row)->getValue() === 0) {
        $zeroRow = $row;
        break;
    }
}

if ($zeroRow !== null) {
    check('a programme with no first choices shows 0, not a blank cell',
        $programmesSheet->getCell('E' . $zeroRow)->getValue() === 0,
        var_export($programmesSheet->getCell('E' . $zeroRow)->getValue(), true));
} else {
    check('there is a programme with no first choices to check the zero on', false);
}

check('the heading row stays in view and can be filtered',
    $programmesSheet->getFreezePane() === 'A9' && $programmesSheet->getAutoFilter()->getRange() !== '');

@unlink($path);

// ---------------------------------------------------------------------------

echo "\n== Who may export it ==\n";

$outsider = App\User::where('is_admin', 0)->where('status', '1')->get()->first(
    fn ($user) => !$user->hasAnyPermission(['application-view', 'application-create', 'application-edit', 'application-delete'])
);

if ($outsider) {
    Auth::guard('web')->login($outsider);

    $refusedPdf = $get('/admin/admission/application-report/pdf?' . $query);
    $refusedExcel = $get('/admin/admission/application-report/excel?' . $query);

    check('a member of staff who cannot see applications cannot export the PDF',
        $refusedPdf->getStatusCode() === 403, (string) $refusedPdf->getStatusCode());
    check('nor the workbook',
        $refusedExcel->getStatusCode() === 403, (string) $refusedExcel->getStatusCode());
} else {
    check('there is a member of staff without application permissions to test with', false);
}

Auth::guard('web')->login($superAdmin);

// ---------------------------------------------------------------------------

echo "\n== The list offers it ==\n";

$listHtml = $get('/admin/admission/application?' . $query)->getContent();

check('the PDF button submits the filter form to the report', str_contains($listHtml, 'formaction="') && str_contains($listHtml, 'application-report/pdf'));
check('so does the workbook button', str_contains($listHtml, 'application-report/excel'));
check('with the minimum class size on the form', str_contains($listHtml, 'name="min_class"'));

echo "\n$passed passed, $failed failed\n";

exit($failed > 0 ? 1 : 0);
