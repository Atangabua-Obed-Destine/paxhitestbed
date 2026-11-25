<?php
/**
 * accounts:syscohada: the chart moves to the standard SYSCOHADA numbering
 * without moving a single franc.
 *
 * Inside a rolled-back transaction the chart is first put back the way the
 * school started (old numbers), then the real command runs. Checks: the
 * preview writes nothing; --apply renumbers every account; no balance, line
 * or link moves; a second run changes nothing; an account renamed by hand is
 * refused rather than renumbered on a guess; code that looks accounts up by
 * number finds them on both sides of the change.
 *
 *   php scripts/chart_syscohada_test.php
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Console\Commands\ChartSyscohada;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$pass = 0; $fail = 0;
function check($label, $ok, $detail = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ok    $label\n"; }
    else { $fail++; echo "  FAIL  $label" . ($detail !== '' ? "   [$detail]" : '') . "\n"; }
}
$chart = fn () => DB::table('chart_of_accounts')->whereNull('deleted_at')->orderBy('id')->get(['id', 'account_code', 'parent_id', 'current_balance', 'account_category'])->keyBy('id');
$codeOf = fn () => DB::table('chart_of_accounts')->whereNull('deleted_at')->pluck('id', 'account_code');
$totals = fn () => DB::table('journal_entry_lines as l')->selectRaw('COUNT(*) n, ROUND(SUM(debit),2) d, ROUND(SUM(credit),2) c')->first();
$byAccount = fn () => DB::table('journal_entry_lines')->selectRaw('account_id, ROUND(SUM(debit - credit), 2) net')->groupBy('account_id')->pluck('net', 'account_id');
$run = function (bool $apply) {
    $code = Artisan::call('accounts:syscohada', $apply ? ['--apply' => true] : []);
    return [$code, Artisan::output()];
};

DB::beginTransaction();
try {
    // ---- 0. Put the chart back to the school's original numbering.
    $codes = $codeOf();
    $standard = isset($codes['7061']);
    if ($standard) {
        $moves = ChartSyscohada::MOVES;
        foreach ($moves as [$from, , $to]) {
            if (isset($codes[$to])) { DB::table('chart_of_accounts')->where('id', $codes[$to])->update(['account_code' => 'OLD' . $codes[$to]]); }
        }
        // Accounts the standard layout added go (nothing is posted on them); their children were moved accounts.
        foreach (ChartSyscohada::CREATES as [$code]) {
            if (isset($codes[$code])) {
                DB::table('chart_of_accounts')->where('parent_id', $codes[$code])->update(['parent_id' => null]);
                DB::table('chart_of_accounts')->where('id', $codes[$code])->delete();
            }
        }
        foreach ($moves as [$from, $words, $to]) {
            if (isset($codes[$to])) { DB::table('chart_of_accounts')->where('id', $codes[$to])->update(['account_code' => $from, 'account_name' => $from . ' ' . $words[0], 'account_name_fr' => $from . ' ' . $words[0]]); }
        }
        DB::table('chart_of_accounts')->where('account_code', '61')->update(['account_category' => 'detail']);
        DB::table('accounting_settings')->where('setting_key', 'chart_syscohada_applied_at')->delete();
    }
    $old = $codeOf();
    check('the chart is on the old numbering to start with', isset($old['711'], $old['443'], $old['53']) && !isset($old['7061']), $standard ? 'reverted' : 'already old');

    // On the old numbering, lookups and seeders stay on it.
    check('before renumbering, lookups use the old codes', ChartSyscohada::current('443') === '443' && ChartSyscohada::current('711') === '711');
    $seedCodes = array_column(ChartSyscohada::seederRows((fn () => $this->originalAccounts())->call(new Database\Seeders\OhadaChartOfAccountsSeeder())), 'account_code');
    check('seeding an old chart adds no standard twins (no 7061 beside 711)', in_array('711', $seedCodes, true) && !in_array('7061', $seedCodes, true));

    $beforeChart = $chart(); $beforeTotals = $totals(); $beforeNet = $byAccount();
    $settings = DB::table('accounting_settings')->where('setting_key', 'like', '%account_id')->pluck('setting_value', 'setting_key');
    $categoryLinks = DB::table('default_account_mappings')->orderBy('id')->get(['id', 'debit_account_id', 'credit_account_id'])->map(fn ($m) => (array) $m);

    // ---- 1. Preview writes nothing.
    [$exit, $out] = $run(false);
    check('the preview succeeds', $exit === 0, trim(substr($out, -300)));
    check('the preview lists the renumbering', str_contains($out, '7061') && str_contains($out, '447'));
    check('and writes nothing', $codeOf()->keys()->sort()->values()->all() === $old->keys()->sort()->values()->all());

    // ---- 2. Apply.
    [$exit, $out] = $run(true);
    $new = $codeOf();
    check('--apply succeeds', $exit === 0, trim(substr($out, -300)));
    foreach (ChartSyscohada::MOVES as [$from, , $to]) {
        if (isset($old[$from]) && (!isset($new[$to]) || $new[$to] !== $old[$from])) {
            check("$from became $to, the same account", false, ($new[$to] ?? 'missing') . ' vs ' . $old[$from]);
        }
    }
    check('every moved account kept its id', collect(ChartSyscohada::MOVES)->every(fn ($m) => !isset($old[$m[0]]) || ($new[$m[2]] ?? null) === $old[$m[0]]));
    check('tuition is on 7061, under 706', ($new['7061'] ?? null) === $old['711']
        && DB::table('chart_of_accounts')->where('id', $new['7061'])->value('parent_id') == ($new['706'] ?? -1));
    check('tax withheld is on 447, VAT on 443', ($new['447'] ?? null) === $old['443'] && ($new['443'] ?? null) === $old['441']);
    check('mobile money is on 552 under 55', ($new['552'] ?? null) === $old['53'] && DB::table('chart_of_accounts')->where('id', $new['552'])->value('parent_id') == ($new['55'] ?? -1));
    check('12 is retained earnings, 13 the year\'s result', isset($new['12']) && ($new['13'] ?? null) === $old['12']);
    check('the standard headings exist', collect(ChartSyscohada::CREATES)->every(fn ($c) => isset($new[$c[0]])));
    check('no account kept a temporary code', !$new->keys()->contains(fn ($c) => str_starts_with((string) $c, 'TMP')));

    // ---- 3. Not a franc moved.
    $afterTotals = $totals();
    check('the same lines, debits and credits', json_encode($afterTotals) === json_encode($beforeTotals), json_encode($afterTotals) . ' vs ' . json_encode($beforeTotals));
    check('every account carries the same net movement', $byAccount()->all() == $beforeNet->all());
    $afterChart = $chart();
    check('every stored balance is unchanged', $beforeChart->every(fn ($a, $id) => isset($afterChart[$id]) && (float) $afterChart[$id]->current_balance === (float) $a->current_balance));
    check('settings point at the same accounts', DB::table('accounting_settings')->where('setting_key', 'like', '%account_id')->pluck('setting_value', 'setting_key')->all() == $settings->all());
    check('categories point at the same accounts', DB::table('default_account_mappings')->orderBy('id')->get(['id', 'debit_account_id', 'credit_account_id'])->map(fn ($m) => (array) $m)->all() == $categoryLinks->all() && $categoryLinks->isNotEmpty());
    check('nothing is posted on a heading the change created', !DB::table('journal_entry_lines')->whereIn('account_id',
        collect(ChartSyscohada::CREATES)->map(fn ($c) => $new[$c[0]] ?? 0)->filter()->diff($old->values())->values())->exists());

    // ---- 4. Again: nothing to do.
    $snapshot = $chart();
    [$exit, $out] = $run(true);
    check('a second run changes nothing', $exit === 0 && $chart() == $snapshot && str_contains($out, '0 accounts renumbered'), trim(substr($out, -200)));

    // ---- 5. Lookups by number work after the change.
    check('the State withholding account is found as 447', ChartSyscohada::current('443') === '447');
    check('the supplier fallback is 401', ChartSyscohada::current('40') === '401');
    check('an unmoved code stays', ChartSyscohada::current('411') === '411');
    $seedCodes = array_column(ChartSyscohada::seederRows((fn () => $this->originalAccounts())->call(new Database\Seeders\OhadaChartOfAccountsSeeder())), 'account_code');
    check('once renumbered, the seeders define the standard chart', in_array('7061', $seedCodes, true) && !in_array('711', $seedCodes, true));

    // ---- 6. A fresh install defines the same chart.
    $defs = collect((new Database\Seeders\OhadaChartOfAccountsSeeder())->definedAccounts())->pluck('account_code');
    foreach (['OhadaDetailAccountsSeeder', 'OhadaMissingAccountsSeeder'] as $cls) {
        $s = new ('Database\\Seeders\\' . $cls)(); $m = new ReflectionMethod($s, 'accounts'); $m->setAccessible(true);
        $defs = $defs->merge(array_column(ChartSyscohada::standardiseTuples($m->invoke($s)), 0));
    }
    $missing = $defs->unique()->diff($new->keys()->map(fn ($c) => (string) $c));
    check('every account the seeders define exists after renumbering', $missing->isEmpty(), $missing->implode(' '));
    check('the seeders define no old code that moved', $defs->intersect(['711', '712', '713', '73', '53', '601'])->isEmpty());
} catch (\Throwable $e) {
    $fail++;
    echo '  FAIL  exception: ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
} finally {
    DB::rollBack();
}

// ---- 7. A hand-renamed account is refused (separate transaction).
DB::beginTransaction();
try {
    $codes = $codeOf();
    // Put tuition back on 711 with a name the command does not recognise.
    DB::table('chart_of_accounts')->where('id', $codes['7061'])->update(['account_code' => '711', 'account_name' => 'Pension', 'account_name_fr' => 'Pension']);
    [$exit, $out] = $run(true);
    check('an account renamed by hand stops the change', $exit !== 0 && str_contains($out, 'NAME DIFFERS'), trim(substr($out, -200)));
    check('and nothing is renumbered', DB::table('chart_of_accounts')->where('id', $codes['7061'])->value('account_code') === '711');
} finally {
    DB::rollBack();
}

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
