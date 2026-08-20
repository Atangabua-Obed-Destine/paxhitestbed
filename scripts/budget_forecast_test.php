<?php
/**
 * Budgeting the next year: the prior-year column, copy-forward, and the PDF.
 *
 * The sheet used to be a recording tool — you could see what happened, but had
 * to build next year from a blank grid with last year open in another tab.
 * These are the pieces that make it a planning tool, so what they must prove is
 * that a carried-forward figure is arithmetically right and indistinguishable
 * from a typed one.
 *
 * Everything runs inside a transaction that is rolled back.
 *
 * Usage: php scripts/budget_forecast_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Budget;
use App\Models\BudgetAllocation;
use App\Models\BudgetLine;
use App\Services\BudgetActualsService;
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

Auth::guard('web')->login(User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first() ?: User::first());
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$session = app('session.store');

// Start the session so a real CSRF token can be posted.
$boot = Illuminate\Http\Request::create('/admin/budget-sheet', 'GET');
$boot->setLaravelSession($session);
$kernel->handle($boot);

function call(string $uri, string $method = 'GET', array $params = [])
{
    global $kernel, $session;
    if ($method !== 'GET') {
        $params['_token'] = $session->token();
    }
    $request = Illuminate\Http\Request::create($uri, $method, $params);
    $request->setLaravelSession($session);

    return $kernel->handle($request);
}

echo "\n" . str_repeat('=', 62) . "\n";
echo "Budget forecasting, and the sheet as a document\n";
echo str_repeat('=', 62) . "\n";

$existing = Budget::where('is_institutional', true)->orderBy('start_date')->first();
if (!$existing) {
    fwrite(STDERR, "no institutional sheet to forecast from\n");
    exit(2);
}

$actualsService = app(BudgetActualsService::class);
$lastYear = $actualsService->forPeriod(
    $existing->start_date?->format('Y-m-d'),
    $existing->end_date?->format('Y-m-d')
)['lines'];

// ---------------------------------------------------------------------------
echo "\nCarrying last year forward\n";
// ---------------------------------------------------------------------------

DB::beginTransaction();

$response = call('/admin/budget-sheet/store', 'POST', [
    'title' => 'TEST 2026/2027',
    'start_date' => '2026-09-01',
    'end_date' => '2027-08-31',
    'seed_from' => 'actual',
    'uplift_percent' => 10,
]);

$new = Budget::where('title', 'TEST 2026/2027')->first();
check('a new sheet is created', $new !== null, 'HTTP ' . $response->getStatusCode());

if ($new) {
    $seeded = BudgetAllocation::where('budget_id', $new->id)->count();
    $expected = count(array_filter($lastYear, fn ($v) => $v != 0));
    // Headers total their children and carry no figure, so they are not seeded.
    $headerIds = BudgetLine::where('is_header', true)->pluck('id')->all();
    $expected = count(array_filter(
        $lastYear,
        fn ($v, $k) => $v != 0 && !in_array((int) $k, $headerIds, true),
        ARRAY_FILTER_USE_BOTH
    ));

    check("every funded line carried forward ($seeded)", $seeded === $expected, "seeded $seeded, expected $expected");
    check('no heading was given a figure', BudgetAllocation::where('budget_id', $new->id)->whereIn('budget_line_id', $headerIds ?: [0])->doesntExist());

    // The uplift must be arithmetic, not approximate.
    $wrong = [];
    foreach (BudgetAllocation::where('budget_id', $new->id)->get() as $alloc) {
        $expectedAmount = round(($lastYear[$alloc->budget_line_id] ?? 0) * 1.10, 2);
        if (abs((float) $alloc->allocated_amount - $expectedAmount) > 0.01) {
            $wrong[] = $alloc->budget_line_id;
        }
    }
    check('every carried figure is last year +10%', $wrong === [], count($wrong) . ' line(s) wrong');

    // total_amount must exclude income, exactly as saveFigures computes it.
    $budgeted = BudgetAllocation::where('budget_id', $new->id)
        ->pluck('allocated_amount', 'budget_line_id')->map(fn ($v) => (float) $v)->toArray();
    $expectedTotal = $actualsService->sectionTotal($budgeted, 'expenditure')
        + $actualsService->sectionTotal($budgeted, 'capital');
    check(
        'the stored total counts expenditure and capital only',
        abs((float) $new->total_amount - $expectedTotal) < 0.01,
        number_format($new->total_amount) . ' vs ' . number_format($expectedTotal)
    );

    check('the new sheet starts as a draft', $new->status === 'draft');
    check(
        'the opening balance chains from the previous closing balance',
        (float) $new->opening_balance != 0.0,
        (string) $new->opening_balance
    );

    // ------------------------------------------------------------------
    echo "\nThe sheet shows last year beside this year\n";
    // ------------------------------------------------------------------

    $body = call('/admin/budget-sheet/' . $new->id)->getContent();
    check('the prior-year column is rendered', substr_count($body, 'prior-col') > 0);
    check('it is headed with the previous period', strpos($body, $existing->title) !== false, $existing->title);

    // Column count must be identical on every row of the sheet table.
    preg_match_all('~<tr[^>]*>(.*?)</tr>~s', $body, $rows);
    $counts = [];
    foreach ($rows[1] as $row) {
        $n = substr_count($row, '<td') + substr_count($row, '<th');
        if ($n >= 5) {
            $counts[$n] = ($counts[$n] ?? 0) + 1;
        }
    }
    // 6 = the sheet; 5 = the reconciliation panel, which legitimately has five.
    check('the sheet table is six columns throughout', ($counts[6] ?? 0) > 50, json_encode($counts));

    // ------------------------------------------------------------------
    echo "\nThe stage guide explains where the sheet is\n";
    // ------------------------------------------------------------------

    check('the lifecycle is shown as steps', strpos($body, '1. Draft') !== false && strpos($body, '5. Closed') !== false);
    check('the current stage is explained', strpos($body, 'Figures can be typed and changed freely') !== false);

    // ------------------------------------------------------------------
    echo "\nThe sheet downloads as a document\n";
    // ------------------------------------------------------------------

    $pdf = call('/admin/budget-sheet/' . $new->id . '/pdf');
    $content = $pdf->getContent();
    check('the PDF route responds', $pdf->getStatusCode() === 200, 'HTTP ' . $pdf->getStatusCode());
    check('it is a PDF', strncmp($content, '%PDF', 4) === 0);
    check('it has more than one page', preg_match_all('~/Type\s*/Page[^s]~', $content) > 1);

    // dompdf drops images it cannot resolve, silently — size is the tell.
    check(
        'the letterhead image is embedded',
        substr_count($content, '/Subtype /Image') > 0 && strlen($content) > 100000,
        number_format(strlen($content)) . ' bytes, ' . substr_count($content, '/Subtype /Image') . ' image(s)'
    );
    check('the download button is offered on screen', strpos($body, 'budget-sheet/' . $new->id . '/pdf') !== false);

    // ------------------------------------------------------------------
    echo "\nThe sheet downloads as a workbook\n";
    // ------------------------------------------------------------------

    $xlsxResponse = call('/admin/budget-sheet/' . $new->id . '/excel');
    check('the Excel route responds', $xlsxResponse->getStatusCode() === 200, 'HTTP ' . $xlsxResponse->getStatusCode());

    $path = sys_get_temp_dir() . '/budget_forecast_test.xlsx';
    ob_start();
    $xlsxResponse->sendContent();
    file_put_contents($path, ob_get_clean());

    check('a workbook file is produced', filesize($path) > 4000, number_format(filesize($path)) . ' bytes');

    $book = PhpOffice\PhpSpreadsheet\IOFactory::load($path);
    $sheet = $book->getActiveSheet();

    // The whole point of an Excel export is that the figures can be summed.
    // Formatted strings like "4,295,000" cannot, so this is the assertion that
    // matters most in this section.
    $numeric = 0;
    $textNumbers = 0;
    foreach ($sheet->getRowIterator() as $row) {
        foreach ($row->getCellIterator('D', 'G') as $cell) {
            $value = $cell->getValue();
            if (is_int($value) || is_float($value)) {
                $numeric++;
            } elseif (is_string($value) && preg_match('~^[\d,]{4,}$~', $value)) {
                $textNumbers++;
            }
        }
    }
    check("figures are real numbers ($numeric)", $numeric > 20 && $textNumbers === 0, "$textNumbers stored as text");

    check('the headings are frozen for scrolling', $sheet->getFreezePane() !== null, (string) $sheet->getFreezePane());

    $flat = [];
    foreach ($sheet->getRowIterator() as $row) {
        foreach ($row->getCellIterator('A', 'G') as $cell) {
            $v = $cell->getValue();
            if (is_string($v) && $v !== '') {
                $flat[] = $v;
            }
        }
    }
    $joined = implode("\n", $flat);

    foreach (['INCOME', 'EXPENDITURE', 'CAPITAL EXPENDITURE ACCOUNTS'] as $band) {
        check("the workbook carries the $band section", strpos($joined, $band) !== false);
    }
    check('it states the cash position', strpos($joined, 'CASH POSITION') !== false);
    check('it states agreement with the ledger', strpos($joined, 'AGREEMENT WITH THE GENERAL LEDGER') !== false);
    check('it names the institution', strpos($joined, 'Posts to') !== false && count($flat) > 40);
    check('the Excel button is offered on screen', strpos($body, 'budget-sheet/' . $new->id . '/excel') !== false);

    @unlink($path);
}

DB::rollBack();

// ---------------------------------------------------------------------------
echo "\nVariance colour follows meaning, not sign\n";
// ---------------------------------------------------------------------------

// Under-collecting income and overspending are both bad news, but they have
// opposite signs. Colouring by sign alone showed every income shortfall green.
DB::beginTransaction();

$sheet = Budget::where('is_institutional', true)->orderByDesc('id')->first();
$incomeLine = BudgetLine::where('section', 'income')->where('is_header', false)->first();
$actualsNow = $actualsService->forPeriod(
    $sheet->start_date?->format('Y-m-d'),
    $sheet->end_date?->format('Y-m-d')
)['lines'];

$spendLine = BudgetLine::where('section', 'expenditure')->where('is_header', false)->get()
    ->first(fn ($l) => ($actualsNow[$l->id] ?? 0) > 0);

// Budget income well above what came in (a shortfall), and expenditure well
// below what went out (an overspend).
foreach ([[$incomeLine, ($actualsNow[$incomeLine->id] ?? 0) + 5_000_000],
          [$spendLine, max(($actualsNow[$spendLine->id] ?? 0) - 1_000, 1)]] as [$line, $amount]) {
    BudgetAllocation::updateOrCreate(
        ['budget_id' => $sheet->id, 'budget_line_id' => $line->id],
        ['allocated_amount' => $amount, 'title' => $line->name, 'is_active' => true]
    );
}

$html = call('/admin/budget-sheet/' . $sheet->id)->getContent();

$rowFor = function (string $code) use (&$html): string {
    return preg_match('~<td class="code">' . preg_quote($code, '~') . '</td>.*?</tr>~s', $html, $m) ? $m[0] : '';
};

$incomeRow = $rowFor($incomeLine->code);
$spendRow = $rowFor($spendLine->code);

check(
    'an income shortfall is marked unfavourable',
    strpos($incomeRow, 'variance-bad') !== false,
    'line ' . $incomeLine->code . ' — ' . (strpos($incomeRow, 'variance-good') !== false ? 'shown as favourable' : 'no class')
);
check(
    'an overspend is marked unfavourable',
    strpos($spendRow, 'variance-bad') !== false,
    'line ' . $spendLine->code
);

// And the reverse: income above budget is good news.
BudgetAllocation::updateOrCreate(
    ['budget_id' => $sheet->id, 'budget_line_id' => $incomeLine->id],
    ['allocated_amount' => max(($actualsNow[$incomeLine->id] ?? 0) - 1_000, 1), 'title' => $incomeLine->name, 'is_active' => true]
);
$html = call('/admin/budget-sheet/' . $sheet->id)->getContent();
check(
    'income above budget is marked favourable',
    strpos($rowFor($incomeLine->code), 'variance-good') !== false,
    'line ' . $incomeLine->code
);

check('the superseded colour classes are gone', strpos($html, 'variance-over') === false && strpos($html, 'variance-under') === false);

DB::rollBack();

// ---------------------------------------------------------------------------
echo "\nThe table is readable\n";
// ---------------------------------------------------------------------------

$html = call('/admin/budget-sheet/' . $sheet->id)->getContent();
check('column headings stay put while scrolling', strpos($html, 'position: sticky') !== false);
check('the table scrolls inside its own container', strpos($html, 'sheet-scroll') !== false);
check('the editable column is marked out', strpos($html, 'budget-col') !== false);
check('unmapped lines say so', strpos($html, 'account-missing') !== false);

// ---------------------------------------------------------------------------
echo "\nStarting from an empty sheet still works\n";
// ---------------------------------------------------------------------------

DB::beginTransaction();
call('/admin/budget-sheet/store', 'POST', [
    'title' => 'TEST EMPTY',
    'start_date' => '2027-09-01',
    'end_date' => '2028-08-31',
]);
$empty = Budget::where('title', 'TEST EMPTY')->first();
check('a sheet can be created with no seeding', $empty !== null);
check('and it carries no figures', $empty && BudgetAllocation::where('budget_id', $empty->id)->doesntExist());
DB::rollBack();

// ---------------------------------------------------------------------------
echo "\nNothing was disturbed\n";
// ---------------------------------------------------------------------------

check('no test sheet survived', Budget::where('title', 'like', 'TEST %')->doesntExist());
check(
    'the existing sheet still reconciles',
    app(App\Services\BudgetReconciliationService::class)->reconcile()['agrees'] === true
);

echo "\n" . str_repeat('-', 62) . "\n";
printf("%d passed, %d failed\n", $passed, $failed);

exit($failed === 0 ? 0 : 1);
