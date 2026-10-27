<?php

/**
 * The income and expenditure report.
 *
 * The screen could already list and total the money; what it could not do was
 * hand someone a document. Its export buttons build a file in the browser out
 * of the rows DataTables has rendered, which is why what came out was a page
 * of the table rather than the report.
 *
 * So the figures here are recomputed from raw SQL, never by asking the service
 * a second question, and the properties asserted are the ones a reader checks
 * before trusting a signed document:
 *
 *   - every row the filter selects is IN the document, not a page of them;
 *   - every breakdown adds to the same total as every other;
 *   - the document and the screen it was printed from cannot disagree;
 *   - a filtered report says, in words, that it is filtered.
 *
 * Nothing here writes. Run: php scripts/transaction_report_test.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Admin\ExpenseController;
use App\Http\Controllers\Admin\IncomeController;
use App\Services\Accounting\TransactionReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

$pass = 0;
$fail = 0;

function check(string $what, $expected, $actual): void
{
    global $pass, $fail;

    if ($expected === $actual) {
        $pass++;
        echo "  ok    {$what}\n";

        return;
    }

    $fail++;
    echo "  FAIL  {$what}\n";
    echo "          expected: " . var_export($expected, true) . "\n";
    echo "          actual:   " . var_export($actual, true) . "\n";
}

function ok(string $what, $condition): void { check($what, true, (bool) $condition); }

function money($value): string { return number_format((float) $value, 2, '.', ''); }

Auth::guard('web')->login(App\User::first());
View::share('errors', new Illuminate\Support\ViewErrorBag());

$svc = app(TransactionReport::class);

$opening = [
    'incomes' => DB::table('incomes')->count(),
    'expenses' => DB::table('expenses')->count(),
    'income_total' => money(DB::table('incomes')->sum('amount')),
    'expense_total' => money(DB::table('expenses')->sum('amount')),
];

/** The report for one filter, as the controller would build it. */
function reportFor(string $kind, array $query): array
{
    global $svc;

    $request = Request::create('/x', 'GET', $query);
    $filter = $svc->filter($request);
    $rows = $svc->rows($kind, $filter);

    return [$filter, $rows, $svc->build($kind, $filter, $rows)];
}

/** The document's HTML, which is what dompdf is handed. */
function documentHtml(string $kind, array $query): string
{
    global $svc;

    [$filter, $rows, $report] = reportFor($kind, $query);
    $letterhead = app(App\Services\LetterheadService::class);

    return view('admin.account.report-pdf', [
        'title' => $kind === 'income' ? 'Income' : 'Expense',
        'path' => $kind,
        'report' => $report,
        'cover' => $svc->describe($kind, $filter),
        'filtered' => $svc->isFiltered($filter),
        'accent' => '#2a78d6',
        'accentDark' => '#1856a4',
        'generatedAt' => now(),
        'generatedBy' => 'Test',
        'letterhead' => $letterhead->render(true),
        'letterheadStyles' => $letterhead->styles(),
    ])->render();
}

/** Rows in the itemised table of a rendered document. */
function itemRows(string $html): int
{
    // The last table in the document is the itemised one; its body rows each
    // carry a right-aligned amount and the total row is excluded by class.
    if (!preg_match_all('~<tr>\s*<td class="num">\d~', $html, $m)) {
        return 0;
    }

    return count($m[0]);
}

foreach (['income', 'expense'] as $kind) {
    $table = $kind === 'income' ? 'incomes' : 'expenses';
    $catTable = $kind === 'income' ? 'income_categories' : 'expense_categories';

    echo "\n== {$kind}: the document covers what the filter selected ==\n";

    /* Recomputed here, from SQL, so the test is not the service agreeing with
       itself. */
    $allCount = (int) DB::table($table)->count();
    $allTotal = money(DB::table($table)->sum('amount'));

    [$filter, $rows, $r] = reportFor($kind, ['all' => 1]);

    check("{$kind}: the report holds every record", $allCount, $rows->count());
    check("{$kind}: and totals what they come to", $allTotal, money($r['headline']['total']));
    check("{$kind}: the count it prints is the count it holds",
        $rows->count(), $r['headline']['count']);

    /* The average and the largest entry are printed side by side and read
       together, so both are recomputed here rather than trusted. Neither was
       asserted when this suite was first written: perturbing the average to
       return the raw total did not fail a single check. */
    check("{$kind}: the average is the total over the count",
        money((float) $allTotal / max(1, $allCount)), money($r['headline']['average']));

    check("{$kind}: the largest entry is the largest entry",
        money(DB::table($table)->max('amount')), money($r['headline']['largest']));

    /* The document itself, not just the data behind it. This is the assertion
       that "the export only has one page" is actually fixed: the rendered
       document has to carry every row, not a page of them. */
    $html = documentHtml($kind, ['all' => 1]);

    check("{$kind}: every record is IN the document, not a page of them",
        $allCount, itemRows($html));

    ok("{$kind}: the document states how many records it covers",
        strpos($html, number_format($allCount) . ' records') !== false
        || strpos($html, (string) $allCount) !== false);

    echo "\n== {$kind}: every breakdown adds to the same total ==\n";

    foreach (['by_category', 'by_method'] as $breakdown) {
        check("{$kind}: {$breakdown} adds to the headline total",
            money($r['headline']['total']),
            money(array_sum(array_column($r[$breakdown], 'total'))));

        check("{$kind}: {$breakdown} accounts for every record",
            $rows->count(), array_sum(array_column($r[$breakdown], 'count')));
    }

    check("{$kind}: by_month adds to the headline total",
        money($r['headline']['total']),
        money(array_sum(array_column($r['by_month'], 'total'))));

    /* Shares are what a reader checks first. Rounded to one decimal they can
       drift a little, but not by more than half a point per bucket. */
    $shares = array_sum(array_column($r['by_category'], 'share'));
    ok("{$kind}: category shares come to 100%", abs($shares - 100.0) <= 0.5);

    /* The month list is contiguous AND the quiet months are in it. A list that
       skipped them would draw a straight line through the month nothing came
       in, which on a collection report is the month worth asking about.

       This has to be checked on a filter that actually HAS quiet months.
       Across every category at once this install has none, so the first
       version of this check passed while doing nothing at all. */
    $contiguous = function (array $months) {
        for ($i = 1; $i < count($months); $i++) {
            $expected = (new DateTime($months[$i - 1]['key'] . '-01'))
                ->modify('+1 month')->format('Y-m');

            if ($expected !== $months[$i]['key']) {
                return $months[$i - 1]['key'] . ' -> ' . $months[$i]['key'];
            }
        }

        return null;
    };

    check("{$kind}: the months run without a gap", null, $contiguous($r['by_month']));

    /* Which category has quiet months, and WHICH months they are, is settled
       from SQL before the service is asked anything. The first version of
       this discovered the quiet months from the service's own output, so when
       the service dropped them the test concluded there were none and skipped
       itself - it reported 0 failures for the exact bug it was written to
       catch. An expectation must not be derived from the thing under test. */
    $quiet = null;

    foreach (DB::table($table)->whereNotNull('category_id')->distinct()->pluck('category_id') as $cid) {
        $dates = DB::table($table)->where('category_id', $cid)
            ->selectRaw('MIN(date) as lo, MAX(date) as hi')->first();

        if (!$dates->lo) {
            continue;
        }

        $present = DB::table($table)->where('category_id', $cid)
            ->selectRaw("DATE_FORMAT(date, '%Y-%m') as m")
            ->distinct()->pluck('m')->all();

        $span = [];
        $cursor = (new DateTime(substr($dates->lo, 0, 7) . '-01'));
        $last = (new DateTime(substr($dates->hi, 0, 7) . '-01'));

        while ($cursor <= $last) {
            $span[] = $cursor->format('Y-m');
            $cursor->modify('+1 month');
        }

        $missing = array_values(array_diff($span, $present));

        if ($missing !== []) {
            $quiet = [(int) $cid, $span, $missing];
            break;
        }
    }

    if ($quiet === null) {
        echo "  (no category on this data leaves a quiet month; that check cannot bite here)\n";
    } else {
        [$cid, $span, $missing] = $quiet;

        [, , $rc] = reportFor($kind, ['all' => 1, 'category' => [$cid]]);
        $printed = array_column($rc['by_month'], 'key');

        /* The whole span, in order, quiet months included - compared against a
           list built from SQL, not against anything the service returned. */
        check("{$kind}: category {$cid} lists every month of its span, quiet ones included",
            $span, $printed);

        foreach ($missing as $m) {
            $row = null;
            foreach ($rc['by_month'] as $candidate) {
                if ($candidate['key'] === $m) {
                    $row = $candidate;
                }
            }

            check("{$kind}: the quiet month {$m} is present and shown as empty",
                ['count' => 0, 'total' => 0.0],
                $row === null ? null : ['count' => $row['count'], 'total' => $row['total']]);
        }

        check("{$kind}: its months still run without a gap", null, $contiguous($rc['by_month']));

        check("{$kind}: filling the quiet months changed no total",
            money($rc['headline']['total']),
            money(array_sum(array_column($rc['by_month'], 'total'))));

        $quietHtml = documentHtml($kind, ['all' => 1, 'category' => [$cid]]);

        ok("{$kind}: the quiet months are printed, shown as having nothing",
            substr_count($quietHtml, '>—</td>') >= count($missing));
    }

    echo "\n== {$kind}: the filter means the same thing everywhere ==\n";

    $busiest = DB::table($table)->selectRaw('category_id, COUNT(*) as c')
        ->whereNotNull('category_id')->groupBy('category_id')
        ->orderByDesc('c')->limit(2)->pluck('category_id')->all();

    if (count($busiest) < 2) {
        echo "  (fewer than two categories carry records; nothing to compare)\n";
    } else {
        [$a, $b] = [(int) $busiest[0], (int) $busiest[1]];

        $expectA = (int) DB::table($table)->where('category_id', $a)->count();
        $expectB = (int) DB::table($table)->where('category_id', $b)->count();
        $expectAB = money(DB::table($table)->whereIn('category_id', [$a, $b])->sum('amount'));

        [, $rowsA, $rA] = reportFor($kind, ['all' => 1, 'category' => [$a]]);
        [, $rowsB, ] = reportFor($kind, ['all' => 1, 'category' => [$b]]);
        [, $rowsAB, $rAB] = reportFor($kind, ['all' => 1, 'category' => [$a, $b]]);

        check("{$kind}: one category returns that category", $expectA, $rowsA->count());
        check("{$kind}: two categories return both", $expectA + $expectB, $rowsAB->count());
        check("{$kind}: and the money with them", $expectAB, money($rAB['headline']['total']));

        /* The property that makes the document trustworthy: it is the screen,
           printed. Not a second query that agrees today. */
        $controller = app($kind === 'income' ? IncomeController::class : ExpenseController::class);
        $request = Request::create('/x', 'GET', ['all' => 1, 'category' => [$a]]);
        app()->instance('request', $request);
        $screen = $controller->index($request)->getData();

        check("{$kind}: the document lists what the screen lists",
            $screen['rows']->count(), $rowsA->count());
        check("{$kind}: and totals what the screen totals",
            money($screen['summary']['total']), money($rA['headline']['total']));

        /* A filtered document has to SAY it is filtered, and name what it
           covers. A total under a letterhead is read as all the money. */
        $cover = $svc->describe($kind, ['categories' => [$a], 'title' => null,
            'window' => ['start' => null, 'end' => null, 'label' => '']]);

        $names = DB::table($catTable)->whereIn('id', [$a])->pluck('title')->all();

        ok("{$kind}: the cover names the chosen category",
            strpos($cover[1]['value'], $names[0]) !== false);

        ok("{$kind}: a filtered report is marked as filtered",
            $svc->isFiltered(['categories' => [$a], 'title' => null]));

        ok("{$kind}: an unfiltered report is not",
            !$svc->isFiltered(['categories' => [], 'title' => null]));

        $filteredHtml = documentHtml($kind, ['all' => 1, 'category' => [$a]]);

        ok("{$kind}: and says so in the document",
            strpos($filteredHtml, __('This is a filtered report.')) !== false);

        ok("{$kind}: while an unfiltered one does not",
            strpos($html, __('This is a filtered report.')) === false);

        check("{$kind}: the filtered document carries its own rows",
            $expectA, itemRows($filteredHtml));
    }

    echo "\n== {$kind}: the edges ==\n";

    /* Somebody prints an empty report exactly when they are checking whether
       anything was recorded. It must not divide by zero or render a blank. */
    [, $none, $rNone] = reportFor($kind, ['all' => 1, 'title' => 'zzz-no-such-record-zzz']);

    check("{$kind}: an empty filter finds nothing", 0, $none->count());
    check("{$kind}: and averages zero rather than dividing by it", 0.0, $rNone['headline']['average']);
    check("{$kind}: and has no months to list", [], $rNone['by_month']);

    $emptyHtml = documentHtml($kind, ['all' => 1, 'title' => 'zzz-no-such-record-zzz']);

    ok("{$kind}: the empty document explains itself rather than printing a blank",
        strpos($emptyHtml, __('No records match this filter, so there is nothing to report. This is not an error: it means nothing was recorded under these terms in this period.')) !== false);

    check("{$kind}: and lists no records", 0, itemRows($emptyHtml));

    echo "\n== {$kind}: it is a real document ==\n";

    /* The letterhead, the signatures and the cover block are what make this a
       document rather than a dump of the table. */
    ok("{$kind}: it carries the letterhead's styles", strpos($html, '<style') !== false);
    ok("{$kind}: it states what it covers", strpos($html, __('Period')) !== false);
    ok("{$kind}: it says who generated it and when", strpos($html, 'Test') !== false);
    ok("{$kind}: it has somewhere to sign", strpos($html, __('Prepared by')) !== false);
    ok("{$kind}: it numbers its pages", strpos($html, 'counter(page)') !== false);
    ok("{$kind}: the itemised table repeats its header on every page",
        strpos($html, 'display: table-header-group') !== false);
}

/* ===================================================================
 |  The route actually produces a PDF
 |===================================================================*/

echo "\n== the route returns a real PDF ==\n";

foreach (['income', 'expense'] as $kind) {
    $request = Request::create("/admin/account/{$kind}/report", 'GET', ['all' => 1]);
    $request->setLaravelSession(app('session.store'));
    $response = $kernel->handle($request);
    $body = $response->getContent();

    check("{$kind}: the report route answers", 200, $response->getStatusCode());
    ok("{$kind}: with a real PDF", substr($body, 0, 5) === '%PDF-');
    ok("{$kind}: of a believable size", strlen($body) > 20000);
}

/* ===================================================================
 |  Nothing was left behind
 |===================================================================*/

echo "\n== nothing was left behind ==\n";

$closing = [
    'incomes' => DB::table('incomes')->count(),
    'expenses' => DB::table('expenses')->count(),
    'income_total' => money(DB::table('incomes')->sum('amount')),
    'expense_total' => money(DB::table('expenses')->sum('amount')),
];

foreach ($opening as $key => $value) {
    check("the {$key} figure is exactly as it was ({$value})", $value, $closing[$key]);
}

printf("\n%d passed, %d failed\n\n", $pass, $fail);

exit($fail > 0 ? 1 : 0);
