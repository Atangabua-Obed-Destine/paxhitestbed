<?php
/**
 * Data-safety snapshot for the accounting / budget work.
 *
 * The finance tables hold real money — 480 expenses worth ~48.5M FCFA, 25
 * incomes, and student fee receipts. Activating the dormant accounting layer
 * must not move a single figure in them, so this records the totals before the
 * work starts and compares afterwards.
 *
 *   php scripts/finance_baseline.php save
 *   php scripts/finance_baseline.php compare
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$mode = $argv[1] ?? 'compare';
$file = __DIR__ . '/../storage/app/finance-baseline.json';

/** Tables whose contents must not change, with the column that carries money. */
$guarded = [
    'incomes' => 'amount',
    'expenses' => 'amount',
    'fees' => 'paid_amount',
    'payrolls' => null,
    'income_categories' => null,
    'expense_categories' => null,
    'fees_categories' => null,
    'transactions' => 'amount',
];

$snapshot = [];
foreach ($guarded as $table => $moneyColumn) {
    if (!Schema::hasTable($table)) {
        $snapshot[$table] = ['missing' => true];
        continue;
    }
    $snapshot[$table] = [
        'rows' => (int) DB::table($table)->count(),
        'sum' => $moneyColumn ? round((float) DB::table($table)->sum($moneyColumn), 2) : null,
    ];
}

// Per-category totals, so a mapping change that silently re-buckets spending
// is caught even when the grand total still matches.
$snapshot['expense_by_category'] = DB::table('expenses')
    ->selectRaw('category_id, count(*) c, round(sum(amount),2) total')
    ->groupBy('category_id')->orderBy('category_id')->get()
    ->mapWithKeys(fn ($r) => [(string) $r->category_id => [$r->c, (float) $r->total]])->toArray();

$snapshot['income_by_category'] = DB::table('incomes')
    ->selectRaw('category_id, count(*) c, round(sum(amount),2) total')
    ->groupBy('category_id')->orderBy('category_id')->get()
    ->mapWithKeys(fn ($r) => [(string) $r->category_id => [$r->c, (float) $r->total]])->toArray();

if ($mode === 'save') {
    file_put_contents($file, json_encode($snapshot, JSON_PRETTY_PRINT));
    echo "Baseline written to storage/app/finance-baseline.json\n\n";
}

foreach ($guarded as $table => $moneyColumn) {
    $s = $snapshot[$table];
    if (!empty($s['missing'])) {
        printf("  %-20s MISSING\n", $table);
        continue;
    }
    printf("  %-20s %5d rows%s\n", $table, $s['rows'],
        $s['sum'] !== null ? '   ' . number_format($s['sum']) : '');
}

if ($mode !== 'compare') {
    exit(0);
}

if (!is_file($file)) {
    echo "\nNo baseline saved yet — run with 'save' first.\n";
    exit(1);
}

$base = json_decode(file_get_contents($file), true);
$now = json_decode(json_encode($snapshot), true);
$problems = [];

foreach ($guarded as $table => $moneyColumn) {
    if (!isset($base[$table]) || !empty($base[$table]['missing'])) {
        continue;
    }
    if ($base[$table]['rows'] !== $now[$table]['rows']) {
        $problems[] = "$table row count changed: {$base[$table]['rows']} → {$now[$table]['rows']}";
    }
    if ($base[$table]['sum'] !== null && abs($base[$table]['sum'] - $now[$table]['sum']) > 0.01) {
        $problems[] = sprintf('%s total changed: %s → %s', $table,
            number_format($base[$table]['sum']), number_format($now[$table]['sum']));
    }
}

foreach (['expense_by_category', 'income_by_category'] as $breakdown) {
    foreach ($base[$breakdown] ?? [] as $categoryId => [$count, $total]) {
        $after = $now[$breakdown][$categoryId] ?? null;
        if (!$after) {
            $problems[] = "$breakdown: category $categoryId disappeared";
        } elseif ($after[0] !== $count || abs($after[1] - $total) > 0.01) {
            $problems[] = sprintf('%s: category %s moved from %d/%s to %d/%s',
                $breakdown, $categoryId, $count, number_format($total), $after[0], number_format($after[1]));
        }
    }
}

echo "\n";
foreach ($problems as $p) {
    echo "FAIL  $p\n";
}
echo $problems ? "\n" . count($problems) . " problem(s)\n" : "OK — no financial data changed\n";
exit($problems ? 1 : 0);
