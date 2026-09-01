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

echo "\n== The rate is weighted, and that matters ==\n";

$fees = $service->programmeFees();
check('programme fees are configured', $fees !== [], count($fees) . ' programmes');

$divergent = [];
foreach ($lines as $line) {
    $rate = $service->facultyRate((int) $line->faculty_id);

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

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
