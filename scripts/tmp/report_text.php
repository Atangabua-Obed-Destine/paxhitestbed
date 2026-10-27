<?php
require __DIR__.'/../../vendor/autoload.php';
$app = require_once __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use App\Services\Accounting\TransactionReport;

$svc = app(TransactionReport::class);

foreach (['income' => 'incomes', 'expense' => 'expenses'] as $mod => $table) {
    echo "\n================ {$mod} ================\n";

    $cats = DB::table($table)->selectRaw('category_id, COUNT(*) c')
        ->whereNotNull('category_id')->groupBy('category_id')
        ->orderByDesc('c')->limit(2)->pluck('category_id')->all();

    $make = function (array $query) use ($svc, $mod) {
        $request = Illuminate\Http\Request::create('/x', 'GET', $query);
        $filter = $svc->filter($request);
        $rows = $svc->rows($mod, $filter);
        return [$filter, $rows, $svc->build($mod, $filter, $rows)];
    };

    // ---- everything -------------------------------------------------------
    [$f, $rows, $r] = $make(['all' => 1]);
    $dbCount = DB::table($table)->count();
    $dbTotal = (float) DB::table($table)->sum('amount');

    printf("  rows in report / in table        %d / %d   %s\n", $rows->count(), $dbCount,
        $rows->count() === $dbCount ? 'OK' : 'MISMATCH');
    printf("  total in report / in table       %s / %s   %s\n",
        number_format($r['headline']['total'], 2), number_format($dbTotal, 2),
        abs($r['headline']['total'] - $dbTotal) < 0.005 ? 'OK' : 'MISMATCH');

    // every breakdown must add to the same total
    foreach (['by_category', 'by_method'] as $b) {
        $sum = array_sum(array_column($r[$b], 'total'));
        $cnt = array_sum(array_column($r[$b], 'count'));
        printf("  %-32s %s / %s   %s\n", $b . ' adds up',
            number_format($sum, 2), number_format($cnt),
            abs($sum - $r['headline']['total']) < 0.005 && $cnt === $rows->count() ? 'OK' : 'MISMATCH');
    }
    $msum = array_sum(array_column($r['by_month'], 'total'));
    printf("  %-32s %s   %s\n", 'by_month adds up', number_format($msum, 2),
        abs($msum - $r['headline']['total']) < 0.005 ? 'OK' : 'MISMATCH');

    $shares = array_sum(array_column($r['by_category'], 'share'));
    printf("  %-32s %.1f%%   %s\n", 'category shares total 100%', $shares,
        abs($shares - 100) < 0.5 ? 'OK' : 'off (rounding)');

    // months are contiguous
    $gap = false;
    for ($i = 1; $i < count($r['by_month']); $i++) {
        $prev = new DateTime($r['by_month'][$i-1]['key'] . '-01');
        $here = new DateTime($r['by_month'][$i]['key'] . '-01');
        if ($prev->modify('+1 month')->format('Y-m') !== $here->format('Y-m')) $gap = true;
    }
    printf("  %-32s %s\n", 'month list has no gaps', $gap ? 'GAP FOUND' : 'OK');

    // ---- filtered ---------------------------------------------------------
    [, $rowsA, $rA] = $make(['all' => 1, 'category' => [$cats[0]]]);
    [, $rowsB, $rB] = $make(['all' => 1, 'category' => [$cats[1]]]);
    [, $rowsAB, $rAB] = $make(['all' => 1, 'category' => [$cats[0], $cats[1]]]);

    printf("  %-32s %d + %d = %d   %s\n", 'two categories = each added',
        $rowsA->count(), $rowsB->count(), $rowsAB->count(),
        $rowsA->count() + $rowsB->count() === $rowsAB->count() ? 'OK' : 'MISMATCH');
    printf("  %-32s %s   %s\n", 'and so does the money',
        number_format($rAB['headline']['total'], 2),
        abs($rA['headline']['total'] + $rB['headline']['total'] - $rAB['headline']['total']) < 0.005
            ? 'OK' : 'MISMATCH');

    // the report must match the SCREEN for the same filter
    $ctrl = app($mod === 'income'
        ? App\Http\Controllers\Admin\IncomeController::class
        : App\Http\Controllers\Admin\ExpenseController::class);
    $req = Illuminate\Http\Request::create('/x', 'GET', ['all' => 1, 'category' => [$cats[0]]]);
    app()->instance('request', $req);
    $screen = $ctrl->index($req)->getData();
    printf("  %-32s %d / %d   %s\n", 'report matches the screen',
        $screen['rows']->count(), $rowsA->count(),
        $screen['rows']->count() === $rowsA->count()
        && abs((float) $screen['summary']['total'] - $rA['headline']['total']) < 0.005 ? 'OK' : 'MISMATCH');

    // ---- the cover states the filter --------------------------------------
    $cover = $svc->describe($mod, $f);
    printf("  %-32s %s\n", 'cover names the period', $cover[0]['value']);
    [$f2] = $make(['all' => 1, 'category' => [$cats[0]]]);
    $cover2 = $svc->describe($mod, $f2);
    printf("  %-32s %s\n", 'cover names the categories', $cover2[1]['value']);
    printf("  %-32s %s / %s\n", 'isFiltered: plain / filtered',
        $svc->isFiltered($f) ? 'yes' : 'no', $svc->isFiltered($f2) ? 'yes' : 'no');

    // ---- empty ------------------------------------------------------------
    [, $none, $rNone] = $make(['all' => 1, 'title' => 'zzz-no-such-thing']);
    printf("  %-32s rows=%d total=%s avg=%s   %s\n", 'empty report does not divide by 0',
        $none->count(), $rNone['headline']['total'], $rNone['headline']['average'],
        $none->count() === 0 && $rNone['headline']['average'] === 0.0 ? 'OK' : 'PROBLEM');
}
