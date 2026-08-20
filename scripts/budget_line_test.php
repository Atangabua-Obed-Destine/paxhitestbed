<?php
/**
 * Structural checks on the seeded Income & Expenditure sheet.
 *
 * These assert the shape of the sheet, not its figures — a wrong parent quietly
 * double counts a whole group, which is exactly the kind of error nobody spots
 * until the totals are questioned in a board meeting.
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BudgetLine;

$results = [];
function check($n, $p, $d = '') { global $results; $results[] = [$n, $p, $d]; }

$all = BudgetLine::all()->keyBy('code');

check('sheet is seeded', $all->count() >= 65, $all->count() . ' lines');

// Every code from the paper must exist.
$paper = [
    '600', '610', '611', '620', '621', '622',
    '400', '401', '402', '403', '404', '409',
    '410', '411', '412', '413', '414', '415',
    '420', '421', '422', '423', '424', '425', '426',
    '430', '441', '442', '443', '445', '446', '447',
    '450', '451', '452', '453', '454',
    '460', '461', '462', '463',
    '470', '471', '472', '473', '474', '475', '476', '477', '478', '479',
    '500', '501', '502', '503',
    '510', '511', '512', '520', '530',
    '540', '541', '542',
];
$missing = array_values(array_filter($paper, fn ($c) => !$all->has($c)));
check('every code on the paper sheet exists', empty($missing), implode(',', $missing));

// Sections must be right — capital is NOT expenditure.
check('capital lines are in the capital section',
    collect(['540', '541', '542'])->every(fn ($c) => $all[$c]->section === 'capital'));
check('income lines are in the income section',
    collect(['600', '610', '611', '621', '622'])->every(fn ($c) => $all[$c]->section === 'income'));

// Headers carry no figure; detail lines do.
foreach (['400', '410', '420', '430', '450', '460', '470', '500', '510', '540', '620'] as $code) {
    check("$code is a header", $all[$code]->is_header, $all[$code]->name);
}

// The bug this file exists to catch: a standalone line nested under the
// previous group would be counted inside that group's total as well as its own.
foreach (['520', '530'] as $code) {
    check("$code sits at group level, not inside 510",
        $all[$code]->parent_id === null,
        'parent_id=' . var_export($all[$code]->parent_id, true));
}

// Group membership must match the paper.
$expectedParents = [
    '401' => '400', '409' => '400',
    '411' => '410', '415' => '410',
    '421' => '420', '426' => '420',
    '441' => '430', '447' => '430',   // the sheet's own 430/44x quirk
    '451' => '450', '454' => '450',
    '461' => '460', '463' => '460',
    '471' => '470', '479' => '470',
    '501' => '500', '503' => '500',
    '511' => '510', '512' => '510',
    '541' => '540', '542' => '540',
    '621' => '620', '622' => '620',
];
foreach ($expectedParents as $child => $parent) {
    check("$child belongs to $parent",
        $all[$child]->parent_id === $all[$parent]->id,
        'got ' . optional(BudgetLine::find($all[$child]->parent_id))->code);
}

// Top-level income detail lines have no parent.
foreach (['600', '610', '611'] as $code) {
    check("$code is a top-level income line", $all[$code]->parent_id === null);
}

// No line may be its own ancestor, and no orphan may point at a missing parent.
$broken = BudgetLine::whereNotNull('parent_id')
    ->whereNotIn('parent_id', BudgetLine::pluck('id'))->count();
check('no line points at a missing parent', $broken === 0, $broken . ' broken');
check('no line is its own parent',
    BudgetLine::whereColumn('parent_id', 'id')->count() === 0);

// Only headers may have children.
$nonHeaderParents = BudgetLine::whereIn('id', BudgetLine::whereNotNull('parent_id')->pluck('parent_id'))
    ->where('is_header', false)->pluck('code')->all();
check('only headers have children', empty($nonHeaderParents), implode(',', $nonHeaderParents));

// Codes unique, ordering stable.
check('codes are unique', BudgetLine::count() === BudgetLine::distinct('code')->count('code'));
check('sheet order is deterministic',
    BudgetLine::sheet()->pluck('code')->first() === '600',
    'first = ' . BudgetLine::sheet()->pluck('code')->first());

// The local extensions must be flagged, so an extension stays visible as one.
foreach (['480', '481', '482', '483', '489', '590'] as $code) {
    check("$code is flagged as a local addition", $all->has($code) && $all[$code]->is_local);
}
check('nothing from the diocesan template is flagged local',
    collect($paper)->every(fn ($c) => !$all[$c]->is_local));

$failed = 0;
foreach ($results as [$n, $p, $d]) {
    if (!$p) { $failed++; }
    printf("%s  %s%s\n", $p ? 'PASS' : 'FAIL', $n, (!$p && $d) ? "   [$d]" : '');
}
printf("\n%d/%d passed\n", count($results) - $failed, count($results));
exit($failed ? 1 : 0);
