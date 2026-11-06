<?php
/**
 * Budgeting tuition from expected enrolment rather than a typed number.
 *
 * A Bursar plans by reasoning "about sixty students in Software Engineering at
 * 350,000 a year". Saving only the product throws that away: nobody can tell
 * later whether 21,450,000 was a forecast or a guess, and nothing recalculates
 * when fees change.
 *
 * The load-bearing decision is the RATE. Budget lines are per faculty, but fees
 * are per programme, so a faculty needs one number — and a plain average is
 * wrong wherever programmes differ in price. This suite exists mainly to keep
 * that from quietly reverting.
 *
 * Usage: php scripts/tuition_forecast_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\BudgetLine;
use App\Models\BudgetLineForecast;
use App\Models\Session;
use App\Services\TuitionForecastService;
use App\User;
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

$service = app(TuitionForecastService::class);

echo "\n== Which lines can be forecast ==\n";

$lines = $service->forecastableLines();
check('faculty-tagged income lines are offered', $lines->isNotEmpty(), $lines->count() . ' lines');
check(
    'and nothing else is',
    $lines->every(fn ($l) => $l->faculty_id && $l->section === 'income' && !$l->is_header)
);

// A stationery line has no student basis; offering a forecast there would be
// nonsense dressed as rigour.
$stationery = BudgetLine::where('section', 'expenditure')->where('is_header', false)->first();
check('an expenditure line is not forecastable', !$lines->contains('id', $stationery->id));

echo "\n== Who is counted ==\n";

// The same population as Admission > Student: one per active matricule, of a
// student still on the books, in the session the budget is for. Computed
// here from the tables, not by asking the service twice.
$listCount = function (int $facultyId, ?int $sessionId): int {
    if (!$sessionId) {
        return 0;
    }

    return (int) DB::table('student_enrolls as e')
        ->join('students as s', 's.id', '=', 'e.student_id')
        ->join('programs as p', 'p.id', '=', 'e.program_id')
        ->where('p.faculty_id', $facultyId)
        ->where('s.status', 1)
        ->where('e.status', 1)
        ->where('e.session_id', $sessionId)
        ->distinct()
        ->count('e.matricule');
};

$sheet = Budget::where('is_institutional', true)->orderByDesc('id')->first();
$sheetSession = $service->sessionForBudget($sheet);
$current = Session::where('current', 1)->where('status', 1)->first();

check(
    'a budget named after an academic year counts that session',
    $sheetSession !== null && $sheetSession->title === trim((string) $sheet->title),
    $sheet->title . ' -> ' . ($sheetSession->title ?? 'none')
);

// Any session a budget is for must be found by its dates too: a sheet whose
// title does not name a year falls back to the session its dates cover.
$untitled = new Budget(['title' => 'Operating budget', 'start_date' => $sheet->start_date, 'end_date' => $sheet->end_date]);
$byDates = $service->sessionForBudget($untitled);
check(
    'a budget not named after a year is placed by its dates',
    $byDates !== null && $sheetSession !== null && $byDates->id === $sheetSession->id,
    ($byDates->title ?? 'none') . ' for ' . $sheet->start_date . ' - ' . $sheet->end_date
);
check('and one with neither falls back to the current session',
    optional($service->sessionForBudget(null))->id === optional($current)->id);

$previous = $service->previousSession($sheetSession);
check(
    'the previous session is the one that started before it',
    $previous !== null && $sheetSession !== null && $previous->start_date < $sheetSession->start_date,
    ($previous->title ?? 'none') . ' before ' . ($sheetSession->title ?? 'none')
);

$byLine = $service->ratesByLine($sheet);
$allZero = true;
foreach ($lines as $line) {
    $rate = $byLine[$line->id];
    $expected = $listCount((int) $line->faculty_id, $sheetSession?->id);
    $expectedPrevious = $listCount((int) $line->faculty_id, $previous?->id);
    $allZero = $allZero && $expected === 0;

    check(
        $line->code . ' counts what the student list shows for ' . ($sheetSession->title ?? '?'),
        $rate['students'] === $expected,
        'forecast ' . $rate['students'] . ' vs list ' . $expected
    );
    check(
        $line->code . ' and for ' . ($previous->title ?? '?') . ' beside it',
        $rate['previous_students'] === $expectedPrevious,
        'forecast ' . $rate['previous_students'] . ' vs list ' . $expectedPrevious
    );
}
check('somebody is enrolled for the budget session, so the comparison meant something', !$allZero);

// Teeth: each kind of row that used to be counted now adds nothing, and a
// real enrolment adds exactly one. Inside a transaction, rolled back.
$outsider = null;
$fixtureLine = null;
foreach ($lines as $line) {
    if ($listCount((int) $line->faculty_id, $sheetSession?->id) > 0) {
        $fixtureLine = $line;
        break;
    }
}

if ($fixtureLine && $sheetSession) {
    $facultyId = (int) $fixtureLine->faculty_id;
    $programmeId = (int) DB::table('programs')->where('faculty_id', $facultyId)->value('id');
    $enrolled = DB::table('student_enrolls as e')
        ->join('students as s', 's.id', '=', 'e.student_id')
        ->join('programs as p', 'p.id', '=', 'e.program_id')
        ->where('p.faculty_id', $facultyId)->where('e.session_id', $sheetSession->id)
        ->where('e.status', 1)->where('s.status', 1)
        ->select('e.student_id', 'e.program_id', 'e.semester_id', 'e.section_id', 'e.matricule')
        ->first();
    $resitSemester = DB::table('semesters')->where('is_resit', 1)->value('id');
    $outsider = DB::table('students as s')
        ->where('s.status', 1)
        ->whereNotExists(function ($q) use ($facultyId) {
            $q->selectRaw('1')->from('student_enrolls as e')
                ->join('programs as p', 'p.id', '=', 'e.program_id')
                ->whereColumn('e.student_id', 's.id')->where('p.faculty_id', $facultyId);
        })
        ->value('s.id');

    $count = fn () => $service->facultyRate($facultyId, $sheetSession)['students'];
    $base = $count();

    $insert = function (array $row) use ($sheetSession) {
        DB::table('student_enrolls')->insert($row + [
            'session_id' => $sheetSession->id, 'semester_id' => 1, 'section_id' => 1,
            'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    };

    DB::beginTransaction();
    try {
        if ($enrolled && $resitSemester) {
            $insert(['student_id' => $enrolled->student_id, 'program_id' => $enrolled->program_id,
                'semester_id' => $resitSemester, 'matricule' => $enrolled->matricule]);
            check('a resit row of a student already counted adds nothing', $count() === $base, $count() . ' vs ' . $base);
        }

        $insert(['student_id' => null, 'program_id' => $programmeId, 'matricule' => 'ZZ-APPLICANT']);
        check('an applicant row with no student behind it adds nothing', $count() === $base, $count() . ' vs ' . $base);

        if ($outsider) {
            $insert(['student_id' => $outsider, 'program_id' => $programmeId, 'status' => 0, 'matricule' => 'ZZ-WITHDRAWN']);
            check('a withdrawn enrolment adds nothing', $count() === $base, $count() . ' vs ' . $base);

            $insert(['student_id' => $outsider, 'program_id' => $programmeId, 'status' => 2, 'matricule' => 'ZZ-CLOSED']);
            check('a closed enrolment adds nothing', $count() === $base, $count() . ' vs ' . $base);

            $insert(['student_id' => $outsider, 'program_id' => $programmeId, 'session_id' => ($previous->id ?? 0), 'matricule' => 'ZZ-LASTYEAR']);
            check('an enrolment in another session adds nothing', $count() === $base, $count() . ' vs ' . $base);

            $insert(['student_id' => $outsider, 'program_id' => $programmeId, 'matricule' => 'ZZ-REAL']);
            check('and a real enrolment in the session adds exactly one', $count() === $base + 1, $count() . ' vs ' . ($base + 1));

            $insert(['student_id' => $outsider, 'program_id' => $programmeId, 'semester_id' => 2, 'matricule' => 'ZZ-REAL']);
            check('the same student on a second semester is still one', $count() === $base + 1, $count() . ' vs ' . ($base + 1));
        } else {
            echo "  ..  no student outside this faculty to enrol as a fixture\n";
        }
    } finally {
        DB::rollBack();
    }

    check('the fixtures left nothing behind', DB::table('student_enrolls')->where('matricule', 'like', 'ZZ-%')->doesntExist());

    // A budget drawn up before intake: nobody on its session yet, so the
    // rate is weighted by the previous session's mix and says so.
    DB::beginTransaction();
    try {
        DB::table('student_enrolls')
            ->whereIn('program_id', DB::table('programs')->where('faculty_id', $facultyId)->pluck('id'))
            ->where('session_id', $sheetSession->id)
            ->update(['status' => 0]);

        $empty = $service->facultyRate($facultyId, $sheetSession, $previous);
        check('with nobody on the budget session the count offered is 0', $empty['students'] === 0);

        if ($empty['previous_students'] > 0) {
            $trueBefore = 0.0;
            foreach ($empty['programmes'] as $p) {
                $trueBefore += $p['previous_students'] * $p['fee'];
            }
            check('the rate is then weighted by the previous session', $empty['weighted_by'] === 'previous', $empty['weighted_by']);
            check(
                'and reproduces that session programme by programme',
                abs($empty['rate'] * $empty['previous_students'] - $trueBefore) < 1.0,
                number_format($empty['rate']) . ' x ' . $empty['previous_students'] . ' vs ' . number_format($trueBefore)
            );
        } else {
            echo "  ..  no previous-session enrolment in this faculty to weight by\n";
        }
    } finally {
        DB::rollBack();
    }
} else {
    echo "  ..  no faculty with students in the budget session to build fixtures on\n";
}

echo "\n== The rate is weighted, and that matters ==\n";

$fees = $service->programmeFees();
check('programme fees are configured', $fees !== [], count($fees) . ' programmes');

$divergent = [];
foreach ($lines as $line) {
    $rate = $byLine[$line->id];

    // The truth: sum over programmes of enrolment x that programme's own fee.
    $true = 0.0;
    foreach ($rate['programmes'] as $programme) {
        $true += $programme['students'] * $programme['fee'];
    }

    check(
        $line->code . ' weighted rate reproduces the programme-by-programme total',
        abs($service->forecast($rate['students'], $rate['rate']) - $true) < 1.0,
        'weighted ' . number_format($rate['rate']) . ' x ' . $rate['students']
            . ' = ' . number_format($rate['students'] * $rate['rate'])
            . ', true ' . number_format($true)
    );

    if (abs($rate['rate'] - $rate['simple_rate']) > 1) {
        $divergent[] = $line->code;

        // The whole reason for weighting. A refactor that quietly reverts to a
        // plain average would make these two equal and must fail here.
        $simpleTotal = $rate['students'] * $rate['simple_rate'];
        check(
            $line->code . ': a simple average would misstate this faculty by '
                . number_format(abs($simpleTotal - $true)),
            abs($simpleTotal - $true) > 1
        );
    }
}

check(
    'at least one faculty genuinely needs weighting',
    $divergent !== [],
    'weighted and simple agree everywhere — the test data may have flattened'
);

// The reason weighting exists, proved on a mix rather than hoped for: the
// programme most students are on is made three times the price of its
// siblings, inside a transaction. The weighted rate must still reproduce
// the total and a plain average must not, whatever the live data looks
// like. (The averages coincide only when every programme has the same
// number of students, which is checked for and skipped.)
$mixLine = null;
foreach ($lines as $line) {
    $priced = array_values(array_filter($byLine[$line->id]['programmes'], fn ($p) => $p['fee'] > 0));
    $counts = array_map(fn ($p) => $p['students'], $priced);
    if (count($priced) >= 2 && max($counts) > 0 && count(array_unique($counts)) > 1) {
        usort($priced, fn ($a, $b) => $b['students'] <=> $a['students']);
        $mixLine = [$line, $priced[0]];
        break;
    }
}
if ($mixLine && $sheetSession) {
    [$line, $busy] = $mixLine;
    DB::beginTransaction();
    try {
        DB::table('program_semester_fees')->where('program_id', $busy['id'])->where('status', 1)
            ->update(['amount' => DB::raw('amount * 3')]);
        $mixed = $service->facultyRate((int) $line->faculty_id, $sheetSession, $previous);
        $true = 0.0;
        foreach ($mixed['programmes'] as $p) {
            $true += $p['students'] * $p['fee'];
        }
        check(
            'with one programme three times the price, the weighted rate still reproduces the total',
            abs($mixed['rate'] * $mixed['students'] - $true) < 1.0
        );
        check(
            'and a plain average would not',
            abs($mixed['simple_rate'] * $mixed['students'] - $true) > 1.0,
            number_format($mixed['simple_rate'] * $mixed['students']) . ' vs ' . number_format($true)
        );
    } finally {
        DB::rollBack();
    }
} else {
    echo "  ..  no faculty with an uneven mix across priced programmes to prove weighting on\n";
}

echo "\n== Incomplete fee configuration is reported ==\n";

$warned = [];
foreach ($lines as $line) {
    foreach ($service->facultyRate((int) $line->faculty_id)['warnings'] as $warning) {
        $warned[] = $warning;
    }
}

// A programme configured for fewer terms than its siblings is priced low, and
// would silently halve its contribution.
$partial = [];
foreach ($service->forecastableLines() as $line) {
    foreach ($service->facultyRate((int) $line->faculty_id)['programmes'] as $programme) {
        if ($programme['incomplete']) {
            $partial[] = $programme['title'];
        }
    }
}

check(
    'a part-configured programme is flagged, not silently priced low',
    $partial === [] || $warned !== [],
    count($partial) . ' part-configured, ' . count($warned) . ' warnings'
);

if ($partial !== []) {
    echo "  ..  part-configured: " . implode('; ', array_map(fn ($t) => substr($t, 0, 42), $partial)) . "\n";
}

echo "\n== Saving a forecast ==\n";

$budget = Budget::where('is_institutional', true)->where('status', 'draft')->orderByDesc('id')->first()
    ?: Budget::where('is_institutional', true)->orderByDesc('id')->first();

$line = $lines->first();
$rate = $service->facultyRate((int) $line->faculty_id);

$admin = User::whereHas('roles')->first() ?: User::first();
Auth::guard('web')->login($admin);

$controller = app(App\Http\Controllers\Admin\BudgetSheetController::class);

DB::beginTransaction();
try {
    $request = Illuminate\Http\Request::create('/x', 'POST', [
        'budget_line_id' => $line->id,
        'student_count' => $rate['students'],
        'rate' => $rate['rate'],
        'rate_basis' => 'weighted',
        'note' => 'ZZ test forecast',
    ]);
    $controller->forecast($request, $budget->id);

    $saved = BudgetLineForecast::where('budget_id', $budget->id)
        ->where('budget_line_id', $line->id)->first();

    check('the assumption is stored', $saved !== null);

    if ($saved) {
        check('with the student count', (int) $saved->student_count === $rate['students']);
        check('and the rate it used', abs((float) $saved->rate - $rate['rate']) < 0.01);

        $expected = $service->forecast($rate['students'], $rate['rate']);
        check('and the amount they produce', abs((float) $saved->computed_amount - $expected) < 0.01);

        $allocation = BudgetAllocation::where('budget_id', $budget->id)
            ->where('budget_line_id', $line->id)->first();

        check('the figure reaches the sheet', $allocation !== null);
        check(
            'and matches the forecast',
            $allocation && abs((float) $allocation->allocated_amount - $expected) < 0.01,
            $allocation ? number_format($allocation->allocated_amount) . ' vs ' . number_format($expected) : ''
        );

        // Nothing recalculates on its own: a change to the fees is reported as a
        // difference, never applied to a figure somebody may have approved.
        check('a forecast on the current rate is not stale', !$saved->isStaleAgainst((float) $rate['rate']));
        check('a forecast on an old rate is stale', $saved->isStaleAgainst((float) $rate['rate'] + 5000));

        $saved->rate_basis = 'manual';
        check(
            'a hand-typed rate is never called stale',
            !$saved->isStaleAgainst((float) $rate['rate'] + 5000)
        );
    }
} finally {
    DB::rollBack();
}

check('the test forecast left nothing behind', BudgetLineForecast::where('note', 'ZZ test forecast')->doesntExist());

echo "\n== What must be refused ==\n";

DB::beginTransaction();
try {
    $before = BudgetLineForecast::count();
    $header = BudgetLine::where('is_header', true)->first();

    $request = Illuminate\Http\Request::create('/x', 'POST', [
        'budget_line_id' => $header->id,
        'student_count' => 50,
        'rate' => 300000,
    ]);
    $controller->forecast($request, $budget->id);

    check('a heading cannot be forecast', BudgetLineForecast::count() === $before);

    $request = Illuminate\Http\Request::create('/x', 'POST', [
        'budget_line_id' => $stationery->id,
        'student_count' => 50,
        'rate' => 300000,
    ]);
    $controller->forecast($request, $budget->id);

    check('an expenditure line cannot be forecast', BudgetLineForecast::count() === $before);
} finally {
    DB::rollBack();
}

// A sheet under review must not have its figures moved by a different form.
$locked = Budget::where('is_institutional', true)->where('status', '!=', 'draft')->first();
if ($locked) {
    DB::beginTransaction();
    try {
        $before = BudgetLineForecast::where('budget_id', $locked->id)->count();

        $request = Illuminate\Http\Request::create('/x', 'POST', [
            'budget_line_id' => $line->id,
            'student_count' => 99,
            'rate' => 300000,
        ]);
        $controller->forecast($request, $locked->id);

        check(
            'a sheet that is no longer a draft refuses a forecast (' . $locked->status . ')',
            BudgetLineForecast::where('budget_id', $locked->id)->count() === $before
        );
    } finally {
        DB::rollBack();
    }
} else {
    echo "  ..  no non-draft sheet to test the lock against\n";
}

echo "\n== The screen ==\n";

$kernel = app(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/admin/budget-sheet/' . $budget->id, 'GET');
$request->setLaravelSession(app('session.store'));
$response = $kernel->handle($request);
$html = $response->getContent();

check('the sheet renders', $response->getStatusCode() === 200, 'status ' . $response->getStatusCode());
check(
    'a forecast button appears on each tuition line',
    substr_count($html, 'class="forecast-btn"') === $lines->count(),
    substr_count($html, 'class="forecast-btn"') . ' for ' . $lines->count() . ' lines'
);
check('the modal is present', strpos($html, 'id="forecastModal"') !== false);
check('the rates reach the browser', strpos($html, 'var RATES = {') !== false);
check('it shows its working, not just a rate', strpos($html, 'fc_programmes') !== false);
check('and names the session it counted', strpos($html, '"session":{"id":' . $sheetSession->id) !== false);
check('with last session beside it', strpos($html, 'fc_th_previous') !== false);

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
