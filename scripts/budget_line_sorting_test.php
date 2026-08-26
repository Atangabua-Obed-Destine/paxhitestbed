<?php
/**
 * Budget lines sort by their heading structure, and are ordered by dragging.
 *
 * sort_order was a single run of numbers across a whole section, typed by hand
 * into a modal. Nothing tied a line to the heading above it, so a line entered
 * with the wrong number sat above its own heading, and the finance director had
 * to keep the whole numbering in their head to add one row.
 *
 * What has to hold now:
 *   - the screen orders by the tree: heading, then the lines filed under it;
 *   - a line whose heading is missing still appears, at the top level;
 *   - dragging writes both position AND parentage, because they are one fact;
 *   - a heading can never be filed under another heading;
 *   - a line can never be filed under something that is not a heading;
 *   - reordering one section never touches another;
 *   - "Sort by code" restores the diocesan sequence without flattening headings.
 *
 * Usage: php scripts/budget_line_sorting_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Admin\BudgetLineController;
use App\Models\BudgetLine;
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

$controller = app(BudgetLineController::class);
$tree = new ReflectionMethod(BudgetLineController::class, 'treeOrdered');
$tree->setAccessible(true);

echo "\n== The tree ordering ==\n";

$expenditure = BudgetLine::where('section', 'expenditure')->orderBy('sort_order')->get();
$ordered = $tree->invoke($controller, $expenditure);

check('every top-level node is returned', $ordered->count() > 0);

$headingsFirst = true;
$orphanChildren = 0;
foreach ($ordered as $node) {
    foreach ($node['children'] as $child) {
        if ($child->parent_id !== $node['line']->id) {
            $orphanChildren++;
        }
        // A child of a node that is not a heading would be a third level.
        if (!$node['line']->is_header) {
            $headingsFirst = false;
        }
    }
}
check('children hang off their own heading', $orphanChildren === 0, "$orphanChildren misfiled");
check('nothing is nested under a non-heading', $headingsFirst);

$flat = [];
foreach ($ordered as $node) {
    $flat[] = $node['line']->id;
    foreach ($node['children'] as $child) {
        $flat[] = $child->id;
    }
}
check(
    'every line in the section appears exactly once',
    count($flat) === $expenditure->count() && count(array_unique($flat)) === count($flat),
    count($flat) . ' rendered vs ' . $expenditure->count() . ' in the section'
);

// A heading that is deleted or moved away must not take its lines off the
// screen with it: invisible is worse than out of place.
DB::beginTransaction();
try {
    $child = BudgetLine::where('section', 'expenditure')->whereNotNull('parent_id')->first();
    $child->parent_id = 999999;   // a heading that is not in this section
    $child->save();

    $rebuilt = $tree->invoke($controller, BudgetLine::where('section', 'expenditure')->get());
    $topIds = $rebuilt->map(fn ($n) => $n['line']->id)->all();

    check(
        'a line whose heading is missing surfaces at the top level',
        in_array($child->id, $topIds, true)
    );
} finally {
    DB::rollBack();
}

echo "\n== Dragging writes position and parentage together ==\n";

$reorder = new ReflectionMethod(BudgetLineController::class, 'reorder');

DB::beginTransaction();
try {
    $heading = BudgetLine::where('section', 'expenditure')->where('is_header', true)->first();
    $otherHeading = BudgetLine::where('section', 'expenditure')->where('is_header', true)
        ->where('id', '!=', $heading->id)->first();
    $line = BudgetLine::where('section', 'expenditure')->where('is_header', false)
        ->where('parent_id', $heading->id)->first();

    if (!$heading || !$otherHeading || !$line) {
        echo "  ..  not enough headings to test with\n";
    } else {
        // Drag the line into the other heading.
        $order = [
            ['id' => $heading->id, 'parent_id' => null],
            ['id' => $otherHeading->id, 'parent_id' => null],
            ['id' => $line->id, 'parent_id' => $otherHeading->id],
        ];

        $request = Illuminate\Http\Request::create('/admin/budget-line/reorder', 'POST', [
            'section' => 'expenditure',
            'order' => $order,
        ]);
        $response = $reorder->invoke($controller, $request);
        $payload = json_decode($response->getContent(), true);

        check('the reorder is accepted', !empty($payload['success']));
        check('it reports what it moved', ($payload['reordered'] ?? 0) === 3, json_encode($payload));

        $line->refresh();
        check('the line is refiled under the new heading', $line->parent_id === $otherHeading->id);
        check('the line takes its new position', (int) $line->sort_order === 30, 'sort_order ' . $line->sort_order);

        $heading->refresh();
        check('the heading is renumbered too', (int) $heading->sort_order === 10);

        // A heading dropped onto another heading must be refused, or the sheet
        // becomes three levels deep and the subtotals stop making sense.
        $request = Illuminate\Http\Request::create('/admin/budget-line/reorder', 'POST', [
            'section' => 'expenditure',
            'order' => [
                ['id' => $heading->id, 'parent_id' => null],
                ['id' => $otherHeading->id, 'parent_id' => $heading->id],
            ],
        ]);
        $reorder->invoke($controller, $request);
        $otherHeading->refresh();
        check('a heading cannot be filed under a heading', $otherHeading->parent_id === null);

        // Nor may a line file under something that is not a heading.
        $plainLine = BudgetLine::where('section', 'expenditure')->where('is_header', false)
            ->where('id', '!=', $line->id)->first();
        $request = Illuminate\Http\Request::create('/admin/budget-line/reorder', 'POST', [
            'section' => 'expenditure',
            'order' => [
                ['id' => $plainLine->id, 'parent_id' => null],
                ['id' => $line->id, 'parent_id' => $plainLine->id],
            ],
        ]);
        $reorder->invoke($controller, $request);
        $line->refresh();
        check('a line cannot be filed under a non-heading', $line->parent_id === null);
    }
} finally {
    DB::rollBack();
}

echo "\n== One section at a time ==\n";

DB::beginTransaction();
try {
    $incomeBefore = BudgetLine::where('section', 'income')
        ->orderBy('id')->pluck('sort_order', 'id')->all();

    $expLine = BudgetLine::where('section', 'expenditure')->first();
    $request = Illuminate\Http\Request::create('/admin/budget-line/reorder', 'POST', [
        'section' => 'expenditure',
        'order' => [['id' => $expLine->id, 'parent_id' => null]],
    ]);
    $reorder->invoke($controller, $request);

    $incomeAfter = BudgetLine::where('section', 'income')
        ->orderBy('id')->pluck('sort_order', 'id')->all();

    check('reordering expenditure leaves income untouched', $incomeBefore === $incomeAfter);

    // A payload naming a line from another section is a bug or a forged
    // request; it must not quietly move a sheet nobody was looking at.
    $incomeLine = BudgetLine::where('section', 'income')->first();
    $before = (int) $incomeLine->sort_order;
    $request = Illuminate\Http\Request::create('/admin/budget-line/reorder', 'POST', [
        'section' => 'expenditure',
        'order' => [['id' => $incomeLine->id, 'parent_id' => null]],
    ]);
    $reorder->invoke($controller, $request);
    $incomeLine->refresh();

    check(
        'a line from another section is ignored, not moved',
        (int) $incomeLine->sort_order === $before,
        "was $before, now {$incomeLine->sort_order}"
    );
} finally {
    DB::rollBack();
}

echo "\n== Sort by code ==\n";

$autoSort = new ReflectionMethod(BudgetLineController::class, 'autoSort');

DB::beginTransaction();
try {
    // Scramble, then restore from the codes.
    BudgetLine::where('section', 'expenditure')->update(['sort_order' => 0]);

    $request = Illuminate\Http\Request::create('/admin/budget-line/auto-sort', 'POST', [
        'section' => 'expenditure',
    ]);
    $autoSort->invoke($controller, $request);

    $after = $tree->invoke($controller, BudgetLine::where('section', 'expenditure')->get());

    $codes = [];
    $parentageKept = true;
    foreach ($after as $node) {
        $codes[] = $node['line']->code;
        foreach ($node['children'] as $child) {
            if ($child->parent_id !== $node['line']->id) {
                $parentageKept = false;
            }
        }
    }

    $sorted = $codes;
    natsort($sorted);
    check('headings come back in code order', array_values($sorted) === $codes, implode(',', array_slice($codes, 0, 6)));
    check('headings keep the lines filed under them', $parentageKept);

    $zeros = BudgetLine::where('section', 'expenditure')->where('sort_order', 0)->count();
    check('every line is renumbered', $zeros === 0, "$zeros still at 0");
} finally {
    DB::rollBack();
}

$stillScrambled = BudgetLine::where('section', 'expenditure')->where('sort_order', 0)->count();
check('the scrambling was rolled back', $stillScrambled === 0);

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
