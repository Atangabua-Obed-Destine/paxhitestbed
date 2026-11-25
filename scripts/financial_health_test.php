<?php
/**
 * Financial health: every figure recomputed from raw journal lines, not from
 * the service, and the page opened. Read-only.
 *
 * Usage: php scripts/financial_health_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . (!$ok && $detail !== '' ? "\n          $detail" : '') . "\n";
}

$asOf = now()->toDateString();
$h = app(App\Services\Accounting\FinancialHealth::class)->summary($asOf);
$by = collect($h['indicators'])->keyBy('key');

$raw = function (string $where, string $signed, ?string $from, string $to) {
    return round((float) DB::selectOne("
        SELECT COALESCE(SUM($signed), 0) v FROM journal_entry_lines l
        JOIN journal_entries e ON e.id = l.journal_entry_id
        JOIN chart_of_accounts a ON a.id = l.account_id
        WHERE e.is_posted = 1 AND e.deleted_at IS NULL AND e.journal_type <> 'closing' AND $where
          AND DATE(e.entry_date) <= ?" . ($from ? ' AND DATE(e.entry_date) >= ?' : ''),
        $from ? [$to, $from] : [$to])->v, 2);
};

echo "\n== The figures, from raw lines ==\n";
$cashOpening = (float) DB::table('chart_of_accounts')->whereNull('deleted_at')->where('account_code', 'like', '5%')->where('account_code', 'not like', '59%')
    ->sum(DB::raw("CASE WHEN normal_balance = 'credit' THEN -opening_balance ELSE opening_balance END"));
$cash = round($cashOpening + (float) DB::selectOne("SELECT COALESCE(SUM(l.debit - l.credit),0) v FROM journal_entry_lines l JOIN journal_entries e ON e.id = l.journal_entry_id
    JOIN chart_of_accounts a ON a.id = l.account_id WHERE e.is_posted = 1 AND e.deleted_at IS NULL AND a.account_code LIKE '5%' AND a.account_code NOT LIKE '59%' AND DATE(e.entry_date) <= ?", [$asOf])->v, 2);
check('cash and bank = class 5 (not 59) to date', abs($by['cash']['value'] - $cash) < 0.01, $by['cash']['value'] . " vs $cash");

$income = $raw("a.account_code LIKE '7%'", 'l.credit - l.debit', $h['from'], $asOf);
$expenses = $raw("a.account_code LIKE '6%'", 'l.debit - l.credit', $h['from'], $asOf);
check('income for the year = class 7 since the year began', abs($h['income'] - $income) < 0.01);
check('expenses for the year = class 6 since the year began', abs($h['expenses'] - $expenses) < 0.01);
check('surplus = income − expenses', abs($by['surplus']['value'] - ($income - $expenses)) < 0.01);
$payroll = $raw("a.account_code LIKE '66%'", 'l.debit - l.credit', $h['from'], $asOf);
check('payroll share = account 66 / income', $income <= 0 || abs($by['payroll']['value'] - round($payroll / $income * 100, 1)) < 0.05);
$held = -round((float) DB::selectOne("SELECT COALESCE(SUM(l.debit - l.credit),0) v FROM journal_entry_lines l JOIN journal_entries e ON e.id = l.journal_entry_id
    JOIN chart_of_accounts a ON a.id = l.account_id WHERE e.is_posted = 1 AND e.deleted_at IS NULL AND a.account_code LIKE '419%' AND DATE(e.entry_date) <= ?", [$asOf])->v, 2);
check('held for students = credit balance of 419', abs($by['credit']['value'] - $held) < 0.01);

echo "\n== The twelve months ==\n";
check('twelve months are shown', count($h['months']) === 12);
$last = end($h['months']);
check('the last month closes on today\'s cash', abs($last['cash'] - $cash) < 0.01, $last['cash'] . " vs $cash");
$m = $h['months'][5];
$mFrom = $m['month'] . '-01';
$mTo = date('Y-m-t', strtotime($mFrom));
check("{$m['label']}: income and expenses match the lines of that month",
    abs($m['income'] - $raw("a.account_code LIKE '7%'", 'l.credit - l.debit', $mFrom, $mTo)) < 0.01
    && abs($m['expenses'] - $raw("a.account_code LIKE '6%'", 'l.debit - l.credit', $mFrom, $mTo)) < 0.01);
$prev = null;
$chain = true;
foreach ($h['months'] as $row) {
    if ($prev !== null) {
        $move = $raw("a.account_code LIKE '5%' AND a.account_code NOT LIKE '59%'", 'l.debit - l.credit', $row['month'] . '-01', min(date('Y-m-t', strtotime($row['month'] . '-01')), $asOf));
        $chain = $chain && abs($row['cash'] - ($prev + $move)) < 0.01;
    }
    $prev = $row['cash'];
}
check('each month\'s cash = the month before + that month\'s class 5 movement', $chain);

echo "\n== Verdicts ==\n";
foreach ($h['indicators'] as $i) {
    check("{$i['label']} has a verdict and an explanation", in_array($i['state'], ['ok', 'watch', 'act'], true) && $i['detail'] !== '');
}
check('everything that is not healthy is listed under "needs attention"',
    count($h['attention']) === collect($h['indicators'])->where('state', '!=', 'ok')->count());

echo "\n== The page ==\n";
$admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->where('status', '1')->first();
Illuminate\Support\Facades\Auth::guard('web')->login($admin);
$request = Illuminate\Http\Request::create('/admin/accounting-reports/financial-health', 'GET');
$request->setLaravelSession(app('session.store'));
$response = $kernel->handle($request);
check('it opens', $response->getStatusCode() === 200, (string) $response->getStatusCode());
$html = (string) $response->getContent();
check('it shows every indicator', collect($h['indicators'])->every(fn ($i) => str_contains($html, e($i['label']))));
check('verdicts are words, not colour alone', str_contains($html, 'Healthy') || str_contains($html, 'Watch') || str_contains($html, 'Act now'));

echo "\n$passed passed, $failed failed\n";
exit($failed ? 1 : 0);
