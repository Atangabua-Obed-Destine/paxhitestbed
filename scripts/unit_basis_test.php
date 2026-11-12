<?php
/**
 * Budgeting an ordinary line as a quantity at a unit amount.
 *
 * "Twelve months at 85,000" is how most of a sheet is reasoned; the sheet used
 * to keep only the 1,020,000. The modal writes the product into the Budget
 * box on the page and the two figures into hidden inputs beside it; the
 * sheet's own Save button stores both. This suite holds the rules that make
 * the working trustworthy: the product is what is budgeted, the working is
 * kept only while it still explains the figure in the box, and the lines
 * driven by student or applicant numbers are never given one — two workings
 * for one figure would contradict.
 *
 * Usage: php scripts/unit_basis_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Admin\BudgetSheetController;
use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\BudgetLine;
use App\Models\BudgetLineForecast;
use App\Services\TuitionForecastService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
$controller = app(BudgetSheetController::class);

$admin = User::whereHas('roles')->first() ?: User::first();
Auth::guard('web')->login($admin);

$budget = Budget::where('is_institutional', true)->where('status', 'draft')->orderByDesc('id')->first();
if (!$budget) {
    echo "No draft institutional sheet to test on.\n";
    exit(1);
}

// What the page posts: the figures, and for each ordinary line the working
// the modal wrote into its hidden inputs (blank when the box was typed).
$save = function (array $amounts, array $workings = []) use ($controller, $budget) {
    return $controller->saveFigures(Request::create('/x', 'POST', [
        'opening_balance' => $budget->opening_balance,
        'amounts' => $amounts,
        'workings' => $workings,
    ]), $budget->id);
};
$unit = fn ($q, $r, $note = '') => ['quantity' => (string) $q, 'rate' => (string) $r, 'note' => $note];
$working = fn (int $lineId) => BudgetLineForecast::where('budget_id', $budget->id)->where('budget_line_id', $lineId)->first();
$figure = fn (int $lineId) => (float) (BudgetAllocation::where('budget_id', $budget->id)->where('budget_line_id', $lineId)->value('allocated_amount') ?? 0);

echo "\n== Ground ==\n";

check('the forecasts table can hold a quantity', Schema::hasColumn('budget_line_forecasts', 'quantity'));

$expense = BudgetLine::where('section', BudgetLine::SECTION_EXPENDITURE)->where('is_header', false)->orderBy('id')->first();
$otherIncome = BudgetLine::where('section', BudgetLine::SECTION_INCOME)->where('is_header', false)
    ->get()->first(fn ($l) => !$service->isForecastable($l));
$header = BudgetLine::where('is_header', true)->first();
$forecastable = $service->forecastableLines()->first();

check('an expenditure line to work on', $expense !== null, $expense ? $expense->label : '');
check('an income line that is not driven by headcount', $otherIncome !== null, $otherIncome ? $otherIncome->label : '');
check('a header and a forecastable line to be refused on', $header !== null && $forecastable !== null);

echo "\n== Quantity × amount lands on the sheet ==\n";

DB::beginTransaction();
try {
    $save([$expense->id => 1020000], [$expense->id => $unit(12, 85000, 'ZZ monthly bill')]);

    $w = $working($expense->id);
    check('the working is kept', $w !== null);
    check('as a unit basis', $w && $w->isUnitBasis());
    check('with the quantity', $w && abs((float) $w->quantity - 12) < 0.001);
    check('and the amount each', $w && abs((float) $w->rate - 85000) < 0.001);
    check('and the product', $w && abs((float) $w->computed_amount - 1020000) < 0.001);
    check('and the note', $w && $w->note === 'ZZ monthly bill');
    check('the product is the budgeted figure', abs($figure($expense->id) - 1020000) < 0.001, number_format($figure($expense->id)));

    // The quantity need not be whole.
    $save([$expense->id => 300000], [$expense->id => $unit(2.5, 120000)]);
    $w = $working($expense->id);
    check('a fractional quantity is kept as typed', $w && abs((float) $w->quantity - 2.5) < 0.001);
    check('and its product is the figure', abs($figure($expense->id) - 300000) < 0.001, number_format($figure($expense->id)));
    check('saving again replaces, never duplicates', BudgetLineForecast::where('budget_id', $budget->id)->where('budget_line_id', $expense->id)->count() === 1);
    check('a note left blank clears the old one', $w && $w->note === null);

    // The browser rounds the product to the franc's hundredth; the form
    // echoes it back as a string.
    $save([$expense->id => '1020000.00'], [$expense->id => $unit('12', '85000')]);
    check('the figure as the form echoes it still matches its working', $working($expense->id) !== null);

    // Income that is not tuition or registration: beds, rent, hall hire.
    $save([$expense->id => 1020000, $otherIncome->id => 12000000], [
        $expense->id => $unit(12, 85000),
        $otherIncome->id => $unit(80, 150000, 'ZZ beds'),
    ]);
    check('an ordinary income line takes the same reasoning', $working($otherIncome->id) !== null && abs($figure($otherIncome->id) - 12000000) < 0.001);
    check('the other line keeps its working', $working($expense->id) !== null);

    // Blank boxes on lines never touched write nothing.
    check('lines with empty boxes get no working', BudgetLineForecast::where('budget_id', $budget->id)->where('rate_basis', 'unit')->count() === 2);
} finally {
    DB::rollBack();
}

echo "\n== Use this figure saves at once, without a reload ==\n";

$useFigure = function (array $data, ?int $budgetId = null) use ($controller, $budget) {
    $request = Request::create('/x', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], json_encode($data));
    return $controller->unitBasis($request, $budgetId ?? $budget->id);
};

DB::beginTransaction();
try {
    $response = $useFigure(['budget_line_id' => $expense->id, 'quantity' => '12', 'rate' => '85000', 'note' => 'ZZ monthly bill']);
    $body = json_decode($response->getContent(), true);

    check('the modal\'s save answers JSON, not a redirect', $response->getStatusCode() === 200 && ($body['ok'] ?? false) === true, $response->getContent());
    check('with the product the row should show', ($body['amount'] ?? null) == 1020000);
    check('and the figures as stored', ($body['quantity'] ?? null) == 12 && ($body['rate'] ?? null) == 85000 && ($body['note'] ?? null) === 'ZZ monthly bill');
    check('the figure is on the sheet already', abs($figure($expense->id) - 1020000) < 0.001);
    $w = $working($expense->id);
    check('and so is the working', $w && $w->isUnitBasis() && abs((float) $w->computed_amount - 1020000) < 0.001);
    $budget->refresh();
    check('the sheet total is recomputed', (float) $budget->total_amount >= 1020000);

    $response = $useFigure(['budget_line_id' => $expense->id, 'quantity' => '0', 'rate' => '85000']);
    check('a zero quantity takes the figure off the sheet', $figure($expense->id) == 0.0 && json_decode($response->getContent(), true)['amount'] == 0);

    $response = $useFigure(['budget_line_id' => $header->id, 'quantity' => '5', 'rate' => '1000']);
    check('a header is refused with a message', $response->getStatusCode() === 422 && !empty(json_decode($response->getContent(), true)['message']));

    $response = $useFigure(['budget_line_id' => $forecastable->id, 'quantity' => '5', 'rate' => '1000']);
    check('a headcount-driven line is refused', $response->getStatusCode() === 422 && $working($forecastable->id) === null);

    $response = $useFigure(['budget_line_id' => $expense->id, 'quantity' => '-1', 'rate' => '1000']);
    check('a negative quantity is refused with the reason', $response->getStatusCode() === 422 && !empty(json_decode($response->getContent(), true)['errors']));

    $response = $useFigure(['budget_line_id' => $expense->id, 'quantity' => 'twelve', 'rate' => '1000']);
    check('and so is a quantity that is not a number', $response->getStatusCode() === 422);
} finally {
    DB::rollBack();
}

$locked = Budget::where('is_institutional', true)->where('status', '!=', 'draft')->first();
if ($locked) {
    DB::beginTransaction();
    try {
        $before = BudgetAllocation::where('budget_id', $locked->id)->sum('allocated_amount');
        $response = $useFigure(['budget_line_id' => $expense->id, 'quantity' => '3', 'rate' => '1000'], $locked->id);
        $after = BudgetAllocation::where('budget_id', $locked->id)->sum('allocated_amount');
        check('a sheet that is no longer a draft refuses the modal\'s save', $response->getStatusCode() === 422 && (float) $before === (float) $after);
    } finally {
        DB::rollBack();
    }
} else {
    echo "  ..  no non-draft sheet to test the lock against\n";
}

echo "\n== Typing over a worked-out figure ==\n";

DB::beginTransaction();
try {
    $save([$expense->id => 1020000], [$expense->id => $unit(12, 85000)]);

    // The hidden inputs still say 12 × 85,000, but the box now says
    // something else: the figure typed by hand wins, and the working goes.
    $save([$expense->id => 999000], [$expense->id => $unit(12, 85000)]);
    check('a different figure typed by hand clears the working', $working($expense->id) === null);
    check('and is the figure that stays', abs($figure($expense->id) - 999000) < 0.001);

    $save([$expense->id => 1020000], [$expense->id => $unit(12, 85000)]);
    $save([$expense->id => ''], [$expense->id => $unit(12, 85000)]);
    check('blanking the figure clears the working too', $working($expense->id) === null);
    check('and takes the figure off the sheet', $figure($expense->id) == 0.0);

    $save([$expense->id => 1020000], [$expense->id => $unit(12, 85000)]);
    $save([$expense->id => 1020000], [$expense->id => $unit('', '')]);
    check('a saved working survives a save whose boxes are empty, as long as the figure still matches', $working($expense->id) !== null);

    $save([$expense->id => 1020000]);
    check('and a save that carries no workings at all', $working($expense->id) !== null);
} finally {
    DB::rollBack();
}

// The same rule protects the student forecasts, which were previously able to
// print "37 students × 255,405" under a figure somebody had typed over.
if ($forecastable) {
    DB::beginTransaction();
    try {
        $controller->forecast(Request::create('/x', 'POST', [
            'budget_line_id' => $forecastable->id, 'student_count' => 10, 'rate' => 100000,
        ]), $budget->id);
        check('a student forecast is on record', $working($forecastable->id) !== null);
        $save([$forecastable->id => 1000000]);
        check('it survives a save that keeps its figure', $working($forecastable->id) !== null);
        $save([$forecastable->id => 1500000]);
        check('and goes when its figure is typed over', $working($forecastable->id) === null);
    } finally {
        DB::rollBack();
    }
}

echo "\n== What must be refused ==\n";

DB::beginTransaction();
try {
    $before = BudgetLineForecast::count();

    $save([$header->id => 5000], [$header->id => $unit(5, 1000)]);
    check('a header gets neither a figure nor a working', BudgetLineForecast::count() === $before && $figure($header->id) == 0.0);

    $save([$forecastable->id => 5000], [$forecastable->id => $unit(5, 1000)]);
    check('a line forecast from students or applicants is never given a unit working', BudgetLineForecast::count() === $before, $forecastable->label);
    check('though its figure is still saved', abs($figure($forecastable->id) - 5000) < 0.001);

    $refused = false;
    try {
        $save([$expense->id => 1000], [$expense->id => $unit(-1, 1000)]);
    } catch (Illuminate\Validation\ValidationException $e) {
        $refused = true;
    }
    check('a negative quantity is refused', $refused && BudgetLineForecast::count() === $before);

    $refused = false;
    try {
        $save([$expense->id => 1000], [$expense->id => $unit('twelve', 1000)]);
    } catch (Illuminate\Validation\ValidationException $e) {
        $refused = true;
    }
    check('a quantity that is not a number is refused', $refused);
} finally {
    DB::rollBack();
}

echo "\n== The screen ==\n";

$kernel = app(Illuminate\Contracts\Http\Kernel::class);
$render = function () use ($kernel, $budget) {
    $request = Request::create('/admin/budget-sheet/' . $budget->id, 'GET');
    $request->setLaravelSession(app('session.store'));
    return $kernel->handle($request)->getContent();
};

DB::beginTransaction();
try {
    $save([$expense->id => 1020000], [$expense->id => $unit(12, 85000, 'ZZ monthly bill')]);
    $html = $render();

    $ordinary = BudgetLine::sheet()->filter(fn ($l) => !$l->is_header && !$service->isForecastable($l))->count();
    $driven = $service->forecastableLines()->count();

    check('the sheet renders', strpos($html, 'id="unitModal"') !== false);
    check(
        'every ordinary line offers Quantity × amount',
        substr_count($html, 'data-bs-target="#unitModal"') === $ordinary,
        substr_count($html, 'data-bs-target="#unitModal"') . ' for ' . $ordinary . ' lines'
    );
    check(
        'and the headcount lines keep their own forecast instead',
        substr_count($html, 'data-bs-target="#forecastModal"') === $driven,
        substr_count($html, 'data-bs-target="#forecastModal"') . ' for ' . $driven . ' lines'
    );
    check(
        'each ordinary line carries its working inside the figures form',
        substr_count($html, 'name="workings[') === $ordinary * 3
    );
    check('a saved working is in its hidden inputs', preg_match('/id="un_q_' . $expense->id . '"\s+value="12"/', $html) === 1
        && preg_match('/id="un_r_' . $expense->id . '"\s+value="85000"/', $html) === 1
        && preg_match('/id="un_n_' . $expense->id . '"\s+value="ZZ monthly bill"/', $html) === 1);
    check('the working is printed under the figure', preg_match('/12\s*&times;\s*85,000/', $html) === 1);
    check('the budget box carries the product', preg_match('/amounts\[' . $expense->id . '\]"\s+value="1020000(\.00)?"/', $html) === 1);
    check('the line now says Edit working', strpos($html, __('Edit working')) !== false);
    check('the modal does not post as a form', strpos($html, 'id="unitForm" onsubmit="return false;"') !== false);
    check('Use this figure saves in the background', strpos($html, "fetch('" . route('admin.budget-sheet.unit-basis', $budget->id) . "'") !== false);
    check('with the CSRF token', strpos($html, "'X-CSRF-TOKEN': '" . csrf_token() . "'") !== false);
    check('and only updates the row from what the server confirmed', strpos($html, 'applyWorking(id, result.data)') !== false);
    check('with a place to show a refusal', strpos($html, 'id="un_error"') !== false);
    check('a unit working is never described in students', preg_match('/0 students\s*&times;/', $html) === 0);
} finally {
    DB::rollBack();
}

echo "\n== Nothing left behind ==\n";

check('no test working survived', BudgetLineForecast::where('note', 'like', 'ZZ %')->doesntExist());
check('no transaction left open', DB::transactionLevel() === 0);

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
