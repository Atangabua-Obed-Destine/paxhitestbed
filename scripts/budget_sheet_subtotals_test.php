<?php
/**
 * Every heading group is totalled at its foot, in all three renderings.
 *
 * The heading row already carried the group's figure at the TOP of the group.
 * That is not where a reader following a column of numbers downwards looks, and
 * it is not where the diocesan form rules a line — a subtotal belongs where the
 * group actually ends. It now appears there on screen, in the signed PDF, and
 * as a real row in the workbook.
 *
 * Placement depends on the group being unbroken, which flat sort_order never
 * guaranteed, so BudgetLine::sheet() now orders by the heading tree.
 *
 * Usage: php scripts/budget_sheet_subtotals_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Exports\BudgetSheetExport;
use App\Http\Controllers\Admin\BudgetSheetController;
use App\Models\Budget;
use App\Models\BudgetLine;
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

echo "\n== Groups are contiguous, so a foot exists to total ==\n";

$lines = BudgetLine::sheet();
check('the sheet returns lines', $lines->isNotEmpty());

// Walk the flat list: once a heading opens, its children must run without
// anything from another group interrupting them.
$broken = [];
$currentHeader = null;
foreach ($lines as $line) {
    if ($line->is_header) {
        $currentHeader = $line;
        continue;
    }
    if ($line->parent_id && (!$currentHeader || $line->parent_id !== $currentHeader->id)) {
        $broken[] = $line->code;
    }
}
check('no line is separated from its heading', $broken === [], 'stranded: ' . implode(',', $broken));

$sectionRuns = [];
foreach ($lines as $line) {
    if (empty($sectionRuns) || end($sectionRuns) !== $line->section) {
        $sectionRuns[] = $line->section;
    }
}
check(
    'each section appears as one run, not scattered',
    count($sectionRuns) === count(array_unique($sectionRuns)),
    implode(' > ', $sectionRuns)
);

echo "\n== Where the subtotals fall ==\n";

$ends = BudgetLine::groupEnds($lines);
check('at least one group is marked for a subtotal', count($ends) > 0, count($ends) . ' groups');

$headersWithChildren = $lines->filter(function ($l) use ($lines) {
    return $l->is_header && $lines->where('parent_id', $l->id)->isNotEmpty();
});
check(
    'every heading that has lines gets one (' . $headersWithChildren->count() . ')',
    count($ends) === $headersWithChildren->count(),
    count($ends) . ' subtotals for ' . $headersWithChildren->count() . ' populated headings'
);

// A heading with nothing under it must NOT produce a subtotal directly beneath
// its own zero — that is noise, not information.
$emptyHeaders = $lines->filter(fn ($l) => $l->is_header)->reject(fn ($l) => $headersWithChildren->contains('id', $l->id));
$emptyProduced = collect($ends)->filter(fn ($h) => $emptyHeaders->contains('id', $h->id));
check('an empty heading produces no subtotal', $emptyProduced->isEmpty());

// Each marker must sit on the LAST child of its group, never a middle one.
$misplaced = [];
foreach ($ends as $lineId => $header) {
    $children = $lines->where('parent_id', $header->id)->values();
    if ($children->isEmpty() || $children->last()->id !== $lineId) {
        $misplaced[] = $header->code;
    }
}
check('each subtotal sits on the group\'s last line', $misplaced === [], 'misplaced: ' . implode(',', $misplaced));

echo "\n== The three renderings ==\n";

$budget = Budget::where('is_institutional', true)->orderByDesc('id')->first();
if (!$budget) {
    fwrite(STDERR, "no institutional budget to render\n");
    exit(2);
}

$controller = app(BudgetSheetController::class);
$sheetData = new ReflectionMethod(BudgetSheetController::class, 'sheetData');
$sheetData->setAccessible(true);
$data = $sheetData->invoke($controller, $budget->id);

check('sheetData carries the group ends', !empty($data['groupEnds']));
check(
    'it agrees with the model',
    array_keys($data['groupEnds']) === array_keys($ends),
    count($data['groupEnds']) . ' vs ' . count($ends)
);

// --- screen -------------------------------------------------------------
$admin = User::whereHas('roles')->first() ?: User::first();
Auth::guard('web')->login($admin);

$kernel = app(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/admin/budget-sheet/' . $budget->id, 'GET');
$request->setLaravelSession(app('session.store'));
$response = $kernel->handle($request);

check('the sheet renders', $response->getStatusCode() === 200, 'status ' . $response->getStatusCode());
$html = $response->getContent();

check(
    'the screen draws a subtotal row per group',
    substr_count($html, 'class="sheet-subtotal"') === count($ends),
    substr_count($html, 'class="sheet-subtotal"') . ' rows for ' . count($ends) . ' groups'
);

$sampleHeader = collect($ends)->first();
check(
    'a subtotal is labelled with its group (' . $sampleHeader->name . ')',
    strpos($html, 'Total') !== false && stripos($html, $sampleHeader->name) !== false
);

// --- workbook -----------------------------------------------------------
$export = new BudgetSheetExport($data);
$rows = $export->array();

$subtotalRows = array_values(array_filter($rows, function ($row) {
    return isset($row[1]) && is_string($row[1]) && str_starts_with(trim($row[1]), 'Total ')
        && trim($row[1]) !== 'Total' ;
}));
check(
    'the workbook carries a subtotal row per group',
    count($subtotalRows) === count($ends),
    count($subtotalRows) . ' rows for ' . count($ends) . ' groups'
);

// The whole point of the workbook is that the Bursar can re-total it, so the
// subtotal cells must be numbers, not formatted text.
$textFigures = [];
foreach ($subtotalRows as $row) {
    foreach ([3, 4, 5] as $col) {
        if (isset($row[$col]) && $row[$col] !== null && !is_numeric($row[$col])) {
            $textFigures[] = $row[1];
        }
    }
}
check('workbook subtotals are numbers, not text', $textFigures === [], implode('; ', array_unique($textFigures)));

echo "\n== The arithmetic ==\n";

// A subtotal must equal the sum of the lines it closes, or the sheet lies.
$wrong = [];
foreach ($ends as $lineId => $header) {
    $children = $lines->where('parent_id', $header->id);

    foreach (['budgeted', 'actual', 'priorActual'] as $series) {
        $sum = 0.0;
        foreach ($children as $child) {
            $sum += (float) ($data[$series][$child->id] ?? 0);
        }
        $shown = (float) ($data[$series][$header->id] ?? 0);

        if (abs($sum - $shown) > 0.01) {
            $wrong[] = $header->code . " $series: sum $sum vs shown $shown";
        }
    }
}
check('every subtotal equals the lines it closes', $wrong === [], implode(' | ', array_slice($wrong, 0, 3)));

// Subtotals must not be double-counted into the section totals.
$sectionSum = 0.0;
foreach ($lines->where('section', 'expenditure')->where('is_header', false) as $line) {
    $sectionSum += (float) ($data['actual'][$line->id] ?? 0);
}
check(
    'section totals still exclude headings (no double count)',
    abs($sectionSum - (float) $data['totals']['actual']['expenditure']) < 0.01,
    "lines sum $sectionSum vs reported " . $data['totals']['actual']['expenditure']
);

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
