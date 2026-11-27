<?php
/**
 * Payroll when no income tax is in force.
 *
 * By default (config payroll.allow_untaxed = false) a payroll for a month with
 * no active income-tax rates is REFUSED, so a deactivated tax table can never
 * silently produce a zero-tax payslip. With the flag on, the same payroll runs
 * untaxed (tax 0) and carries a visible note. A real set of bands is untouched
 * either way. All writes are rolled back.
 *
 *   php scripts/payroll_untaxed_test.php
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\User;
use App\Models\TaxGroup;
use App\Services\PayrollCalculator;
use Illuminate\Support\Facades\DB;

$pass = 0; $fail = 0;
function check($l, $ok, $d = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ok    $l\n"; }
    else { $fail++; echo "  FAIL  $l" . ($d !== '' ? "   [$d]" : '') . "\n"; }
}

$INCOME_TAX = 1; // PayrollCalculator::GROUP_INCOME_TAX
$month = '2026-11-01';
$calc = app(PayrollCalculator::class);

DB::beginTransaction();
try {
    $user = User::where('status', '1')->where('basic_salary', '>', 0)->orderBy('id')->first();
    if (!$user) { throw new RuntimeException('No active staff with a salary to test with.'); }

    // Make sure the income-tax group is inactive for this test (as SAHIK has it).
    $hadActive = TaxGroup::where('id', $INCOME_TAX)->where('status', 1)->exists();
    DB::table('tax_groups')->where('id', $INCOME_TAX)->update(['status' => 0]);

    echo "=== flag OFF: the guardrail still protects (default) ===\n";
    config(['payroll.allow_untaxed' => false]);
    $off = $calc->calculate($user, $month);
    check('blocked with "no income tax rates are in force"',
        collect($off['problems'])->contains(fn ($p) => str_contains($p, 'No income tax rates are in force')),
        implode(' | ', $off['problems']));

    echo "\n=== flag ON: deliberate no-tax payroll runs ===\n";
    config(['payroll.allow_untaxed' => true]);
    $on = $calc->calculate($user, $month);
    check('no problems — payroll can be saved', empty($on['problems']), implode(' | ', $on['problems']));
    check('income tax is 0.00', (float) $on['tax'] === 0.0, (string) $on['tax']);
    check('employer tax is 0.00', (float) $on['employer_tax'] === 0.0, (string) $on['employer_tax']);
    check('net equals gross (nothing withheld)', abs((float) $on['net_salary'] - (float) $on['gross_salary']) < 0.005, $on['net_salary'] . ' vs ' . $on['gross_salary']);
    check('a visible note says no tax was withheld',
        collect($on['notes'])->contains(fn ($n) => str_contains($n, 'withholds no income tax')),
        implode(' | ', $on['notes']));

    echo "\n=== the flag does NOT weaken the guardrail when rates DO exist ===\n";
    // A partly-configured table (a gap) must still block even with the flag on.
    // Reactivate the group with a single band from 0; a pay above a created gap
    // would be rejected. We only assert the active-rates path computes, not 0.
    DB::table('tax_groups')->where('id', $INCOME_TAX)->update(['status' => 1]);
    $activeBands = TaxGroup::getEffectiveGroups(\Carbon\Carbon::parse($month))
        ->first(fn ($g) => (int) $g->id === $INCOME_TAX);
    if ($activeBands && $activeBands->brackets->where('status', 1)->count() > 0) {
        config(['payroll.allow_untaxed' => true]);
        $live = $calc->calculate($user, $month);
        // With real active bands the untaxed note must NOT appear.
        check('with active rates, the untaxed note is absent (tax computed normally)',
            !collect($live['notes'])->contains(fn ($n) => str_contains($n, 'withholds no income tax')),
            implode(' | ', $live['notes']));
    } else {
        echo "  (skip: no active income-tax bands present to test the normal path)\n";
    }
} catch (\Throwable $e) {
    $fail++;
    echo '  FAIL  exception: ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
} finally {
    DB::rollBack();
}
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
