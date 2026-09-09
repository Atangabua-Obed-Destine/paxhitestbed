<?php
/**
 * The payroll tax cycle: withhold, record, split by authority, remit.
 *
 * What this guards:
 *
 *   The breakdown was never recorded. `payrolls` held one figure and the
 *   payslip re-derived the parts from whatever the configuration said at the
 *   moment it printed, so a payslip reprinted after a rate change showed
 *   figures nobody was ever paid — and no declaration to DGI or CNPS could be
 *   filled from what the system stored.
 *
 *   Every franc withheld went to one account, 443 Etat - Retenue, including
 *   the employee's CNPS 4.2%, which is owed to CNPS. That made "what do we owe
 *   CNPS?" unanswerable from the ledger.
 *
 *   Nothing ever remitted. 443 and 431 grew every month with no screen saying
 *   the school was holding money that belonged to somebody else.
 *
 * Every write runs inside a transaction that is rolled back.
 *
 * Usage: php scripts/tax_remittance_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ChartOfAccount;
use App\Models\Payroll;
use App\Models\PayrollTaxLine;
use App\Models\TaxRemittance;
use App\Services\PayrollTaxBreakdownService;
use App\Services\TaxRemittanceService;
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

$admin = App\User::where('is_admin', 1)->orderBy('id')->firstOrFail();
Auth::guard('web')->login($admin);

$breakdown = app(PayrollTaxBreakdownService::class);
$remittance = app(TaxRemittanceService::class);

$state = ChartOfAccount::where('account_code', '443')->first();
$social = ChartOfAccount::where('account_code', '431')->first();
$bank = ChartOfAccount::where('account_code', '521')->first();

echo "\n== The itemised taxes add up to what the payroll charged ==\n";

$paid = Payroll::where('status', 1)->get();

if ($paid->isEmpty()) {
    echo "  SKIP  no paid payroll in this database\n";
} else {
    $wrong = [];

    foreach ($paid as $payroll) {
        $lines = $breakdown->forPayroll($payroll);

        if (!$breakdown->reconciles($payroll, $lines)) {
            $totals = $breakdown->totals($lines);
            $wrong[] = sprintf('#%d: %s/%s vs %s/%s', $payroll->id,
                $totals['employee'], $totals['employer'], $payroll->tax, $payroll->employer_tax);
        }
    }

    check('every paid payroll itemises to its own total', $wrong === [], implode(' | ', $wrong));

    // Recorded, not merely computable. The stored rows are the record.
    $missing = $paid->filter(fn ($p) => PayrollTaxLine::where('payroll_id', $p->id)->count() === 0);

    check('every paid payroll has its breakdown recorded', $missing->isEmpty(),
        'without lines: ' . $missing->pluck('id')->implode(', '));

    foreach ($paid as $payroll) {
        $stored = PayrollTaxLine::where('payroll_id', $payroll->id)->get();

        if ($stored->isEmpty()) {
            continue;
        }

        check("payroll #{$payroll->id}: stored lines sum to the payroll's employee tax",
            abs($stored->sum(fn ($l) => (float) $l->employee_amount) - (float) $payroll->tax) < 0.51,
            'lines ' . $stored->sum(fn ($l) => (float) $l->employee_amount) . ' vs ' . $payroll->tax);

        check("payroll #{$payroll->id}: stored lines sum to the payroll's employer tax",
            abs($stored->sum(fn ($l) => (float) $l->employer_amount) - (float) ($payroll->employer_tax ?? 0)) < 0.51);
    }
}

echo "\n== The three implementations still agree ==\n";

// The banded calculation lives in TaxGroup, the breakdown service walks it,
// and the distribution report has its own copy. They agree only because they
// were hand-synchronised, so a divergence has to fail here rather than show up
// as a payslip that disagrees with a declaration.
$controller = new ReflectionClass(App\Http\Controllers\Admin\StaffTaxReportController::class);
$calculate = $controller->getMethod('calculateStaffTaxes');
$calculate->setAccessible(true);
$reportController = $controller->newInstanceWithoutConstructor();

$today = Carbon\Carbon::today();
$groups = App\Models\TaxGroup::getEffectiveGroups($today);
$standalone = App\Models\TaxSetting::active()->standalone()->effectiveOn($today)->ordered()->get();

$staff = App\User::where('status', 1)->whereNotNull('basic_salary')->where('basic_salary', '>', 0)->get();
$diverged = [];

foreach ($staff as $member) {
    $exemptions = $breakdown->exemptionsFor($member->id, $today);

    $mine = $breakdown->totals($breakdown->forSalary((float) $member->basic_salary, $today, $exemptions));
    $theirs = $calculate->invoke($reportController, $member, $groups, $standalone, $exemptions);

    if (abs($mine['employee'] - $theirs['employee_tax_total']) > 0.02
        || abs($mine['employer'] - $theirs['employer_tax_total']) > 0.02) {
        $diverged[] = sprintf('%s: breakdown %s/%s, report %s/%s',
            $member->id, round($mine['employee'], 2), round($mine['employer'], 2),
            round($theirs['employee_tax_total'], 2), round($theirs['employer_tax_total'], 2));
    }
}

check('the breakdown service and the tax report agree for every staff member',
    $diverged === [], implode(' | ', array_slice($diverged, 0, 4)));

check('there was somebody to compare', $staff->count() > 0, $staff->count() . ' staff');

echo "\n== Withheld tax lands on the body it is owed to ==\n";

check('the chart has both a State and a social liability account',
    $state !== null && $social !== null);

if ($state && $social) {
    // The employee's CNPS share used to be credited to the State's account.
    $cnpsOnState = PayrollTaxLine::where('liability_account_id', $state->id)
        ->where(function ($q) {
            $q->where('label', 'LIKE', '%Insurance%')->orWhere('label', 'LIKE', '%CNPS%');
        })->count();

    check('no social insurance line accrues to the State account', $cnpsOnState === 0,
        "{$cnpsOnState} line(s) still point at 443");

    // And the posted entry has to agree with the recorded lines, not just the
    // lines with themselves.
    $ledgerState = $remittance->ledgerBalance($state->id);
    $ledgerSocial = $remittance->ledgerBalance($social->id);

    $linesState = round(PayrollTaxLine::where('liability_account_id', $state->id)
        ->sum(DB::raw('employee_amount + employer_amount')), 2);
    $linesSocial = round(PayrollTaxLine::where('liability_account_id', $social->id)
        ->sum(DB::raw('employee_amount + employer_amount')), 2);

    check('the State account balance matches its recorded lines',
        abs($ledgerState - $linesState) < 1.0, "ledger {$ledgerState} vs lines {$linesState}");

    check('the social account balance matches its recorded lines',
        abs($ledgerSocial - $linesSocial) < 1.0, "ledger {$ledgerSocial} vs lines {$linesSocial}");

    check('the social account carries a balance at all', $ledgerSocial > 0,
        'nothing is owed to CNPS, which would mean the split never happened');
}

echo "\n== The ledger balances ==\n";

function trialBalance(): array
{
    $row = DB::table('journal_entry_lines as l')
        ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
        ->where('e.is_posted', 1)->whereNull('e.deleted_at')
        ->selectRaw('COALESCE(SUM(l.debit),0) d, COALESCE(SUM(l.credit),0) c')->first();

    return [(float) $row->d, (float) $row->c];
}

[$debits, $credits] = trialBalance();

check('total debits equal total credits', abs($debits - $credits) < 0.01,
    number_format($debits, 2) . ' vs ' . number_format($credits, 2));

$oneSided = DB::table('journal_entries as e')
    ->where('e.is_posted', 1)->whereNull('e.deleted_at')
    ->whereRaw('(SELECT COALESCE(SUM(debit),0) FROM journal_entry_lines WHERE journal_entry_id = e.id)
              <> (SELECT COALESCE(SUM(credit),0) FROM journal_entry_lines WHERE journal_entry_id = e.id)')
    ->count();

check('no posted entry is one-sided', $oneSided === 0, "{$oneSided} unbalanced entries");

echo "\n== A remittance clears the month, and voiding brings it back ==\n";

$rows = $remittance->outstanding();
$owing = array_values(array_filter($rows, fn ($r) => $r['outstanding'] > 0.005));

if (!$owing || !$bank) {
    echo "  SKIP  nothing is owed, or there is no bank account to pay from\n";
} else {
    $target = $owing[0];

    DB::beginTransaction();

    try {
        $before = $remittance->ledgerBalance($target['account_id']);

        $record = $remittance->record([
            'liability_account_id' => $target['account_id'],
            'salary_month' => $target['month'],
            'amount' => $target['outstanding'],
            'payment_date' => date('Y-m-d'),
            'source_account_id' => $bank->id,
            'reference' => 'TEST-RECEIPT',
        ], $admin->id);

        $after = $remittance->ledgerBalance($target['account_id']);

        check('paying the outstanding empties the liability account',
            abs($after) < 0.01, "balance is {$after}, was {$before}");

        $now = collect($remittance->outstanding())
            ->firstWhere(fn ($r) => $r['account_id'] === $target['account_id'] && $r['month'] === $target['month']);

        check('the month reads as settled', $now === null || $now['status'] === 'settled',
            'status: ' . ($now['status'] ?? 'gone'));

        // Both sides, so the entry cannot be a one-legged posting that happens
        // to move the balance.
        $entry = $record->journalEntry;

        check('the payment posted a balanced entry',
            $entry && abs((float) $entry->total_debit - (float) $entry->total_credit) < 0.01);

        check('it debited the liability and credited the bank',
            $entry
            && $entry->lines->where('account_id', $target['account_id'])->sum('debit') > 0
            && $entry->lines->where('account_id', $bank->id)->sum('credit') > 0);

        check('the entry is posted, not left in draft', $entry && $entry->is_posted);

        // The daybook and the budget sheet read only classes 2 and 6. A
        // remittance touches 4 and 5, so it must move neither: the salary
        // became an expense when it was paid, and counting it again here
        // would double it.
        check('a remittance touches no expense or asset account',
            $entry && $entry->lines->every(function ($line) {
                $account = ChartOfAccount::find($line->account_id);
                return $account && !in_array((int) $account->class_number, [2, 6], true);
            }));

        // Paying the same month twice is the mistake this guards.
        $refused = false;

        try {
            $remittance->record([
                'liability_account_id' => $target['account_id'],
                'salary_month' => $target['month'],
                'amount' => 100,
                'payment_date' => date('Y-m-d'),
                'source_account_id' => $bank->id,
            ], $admin->id);
        } catch (RuntimeException $e) {
            $refused = true;
        }

        check('paying the same month twice is refused', $refused);

        // Voiding
        $remittance->void($record->fresh(), $admin->id, 'test');

        $restored = $remittance->ledgerBalance($target['account_id']);

        check('voiding puts the liability back',
            abs($restored - $before) < 0.01, "balance is {$restored}, was {$before}");

        check('the voided payment is kept, not deleted',
            TaxRemittance::find($record->id) !== null
            && TaxRemittance::find($record->id)->voided_at !== null);

        check('a voided payment stops counting as paid',
            TaxRemittance::live()->where('id', $record->id)->count() === 0);

        $again = collect($remittance->outstanding())
            ->firstWhere(fn ($r) => $r['account_id'] === $target['account_id'] && $r['month'] === $target['month']);

        check('the month is owed again after voiding',
            $again && abs($again['outstanding'] - $target['outstanding']) < 0.01);

        check('voiding a payment twice is refused', (function () use ($remittance, $record, $admin) {
            try {
                $remittance->void($record->fresh(), $admin->id);
                return false;
            } catch (RuntimeException $e) {
                return true;
            }
        })());
    } finally {
        DB::rollBack();
    }

    echo "\n== A remittance is refused when it should be ==\n";

    DB::beginTransaction();

    try {
        // Paying more than is owed, without saying so, turns the liability
        // negative and nobody notices.
        $tooMuch = false;

        try {
            $remittance->record([
                'liability_account_id' => $target['account_id'],
                'salary_month' => $target['month'],
                'amount' => $target['outstanding'] + 5000,
                'payment_date' => date('Y-m-d'),
                'source_account_id' => $bank->id,
            ], $admin->id);
        } catch (RuntimeException $e) {
            $tooMuch = true;
        }

        check('paying more than is owed is refused', $tooMuch);

        // ...unless it is deliberate.
        $allowed = true;

        try {
            $remittance->record([
                'liability_account_id' => $target['account_id'],
                'salary_month' => $target['month'],
                'amount' => $target['outstanding'] + 5000,
                'payment_date' => date('Y-m-d'),
                'source_account_id' => $bank->id,
                'allow_overpayment' => true,
            ], $admin->id);
        } catch (RuntimeException $e) {
            $allowed = false;
        }

        check('an overpayment is allowed when it is declared', $allowed);
    } finally {
        DB::rollBack();
    }

    DB::beginTransaction();

    try {
        // Paying tax out of an expense account would record the cost twice —
        // once when the salary was paid, again here.
        $expense = ChartOfAccount::where('class_number', 6)->where('account_category', 'detail')->first();
        $refusedExpense = false;

        if ($expense) {
            try {
                $remittance->record([
                    'liability_account_id' => $target['account_id'],
                    'salary_month' => $target['month'],
                    'amount' => 100,
                    'payment_date' => date('Y-m-d'),
                    'source_account_id' => $expense->id,
                ], $admin->id);
            } catch (RuntimeException $e) {
                $refusedExpense = true;
            }
        }

        check('paying tax from an expense account is refused', $expense === null || $refusedExpense);
        check('the form offers only cash and bank accounts',
            $remittance->sourceAccounts()->every(fn ($a) => (int) $a->class_number === 5));
    } finally {
        DB::rollBack();
    }
}

echo "\n== What is owed agrees with the ledger ==\n";

foreach ($remittance->reconciliation() as $row) {
    check("{$row['account_code']} {$row['account_name']}: months agree with the ledger",
        $row['agrees'],
        "months {$row['per_month']} vs ledger {$row['ledger']}");
}

echo "\n== Unpaying a payroll withdraws what it owed ==\n";

$payroll = Payroll::where('status', 1)->first();

if (!$payroll) {
    echo "  SKIP  no paid payroll\n";
} else {
    DB::beginTransaction();

    try {
        $breakdown->clear($payroll);

        check('clearing a payroll removes its tax lines',
            PayrollTaxLine::where('payroll_id', $payroll->id)->count() === 0);
    } finally {
        DB::rollBack();
    }

    check('and the rollback put them back',
        PayrollTaxLine::where('payroll_id', $payroll->id)->count() > 0);
}

echo "\n== The screen is reachable, and only by the right people ==\n";

$kernel = app(Illuminate\Contracts\Http\Kernel::class);

$request = Illuminate\Http\Request::create('/admin/staff/tax-remittance', 'GET');
$request->setLaravelSession(app('session.store'));
$response = $kernel->handle($request);

check('an admin can open the remittance screen', $response->getStatusCode() === 200,
    'status ' . $response->getStatusCode());

$body = $response->getContent();

check('the page names what is owed', str_contains($body, 'Outstanding') || str_contains($body, 'outstanding'));

// The theme is Bootstrap 5.2. Its data attributes are data-bs-*, and there is
// no jQuery .modal() plugin. A Bootstrap 4 spelling does not error — the
// button simply does nothing when clicked, which is how "Record a payment"
// and "Details" shipped dead the first time.
foreach ([
    'data-toggle="modal"' => 'the modal button uses the Bootstrap 4 attribute',
    'data-toggle="collapse"' => 'the details button uses the Bootstrap 4 attribute',
    'data-dismiss="modal"' => 'a dismiss control uses the Bootstrap 4 attribute',
    'data-target="#' => 'a control targets with the Bootstrap 4 attribute',
] as $needle => $problem) {
    check("the page carries no {$needle}", !str_contains($body, $needle), $problem);
}

check('the record button opens the modal through Bootstrap 5',
    str_contains($body, 'data-bs-toggle="modal"') && str_contains($body, 'data-bs-target="#recordRemittance"'));

check('the details button collapses through Bootstrap 5',
    str_contains($body, 'data-bs-toggle="collapse"'));

$source = file_get_contents(__DIR__ . '/../resources/views/admin/tax-remittance/index.blade.php');

check('the modal is shown through the Bootstrap 5 API, not the jQuery plugin',
    str_contains($source, 'bootstrap.Modal') && !str_contains($source, ".modal('show')"));

// Bootstrap 5 sets display:block on an open .collapse, which destroys a table
// row. The collapsing element has to be a div inside the cell.
check('the collapsing element is not a table row',
    !preg_match('/<tr[^>]*class="[^"]*\bcollapse\b/', $source),
    'a <tr class="collapse"> will break the table layout when it opens');

// Gate::before lets is_admin through regardless of permissions, so a
// permission test on an admin proves nothing. This has to be a real
// non-admin, and it has to go through the kernel — calling the controller
// directly skips the middleware that does the refusing.
$plain = App\User::where('is_admin', 0)->where('status', 1)->first();

if (!$plain) {
    echo "  SKIP  no non-admin user to test the permission with\n";
} else {
    DB::beginTransaction();

    try {
        $plain->roles()->detach();
        $plain->permissions()->detach();
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        Auth::guard('web')->login($plain);

        $denied = Illuminate\Http\Request::create('/admin/staff/tax-remittance', 'GET');
        $denied->setLaravelSession(app('session.store'));

        $status = 0;

        try {
            $status = $kernel->handle($denied)->getStatusCode();
        } catch (Spatie\Permission\Exceptions\UnauthorizedException $e) {
            $status = 403;
        }

        check('somebody without the permission is refused', $status === 403 || $status === 302,
            'status ' . $status);

        // The token has to be real. Without it the request is rejected as a
        // CSRF failure (419) BEFORE the permission middleware runs, and the
        // refusal would prove nothing about permissions at all — which is
        // exactly how an earlier version of this assertion passed.
        app('session.store')->regenerateToken();

        // The payload has to be VALID, and the check has to be that nothing
        // was written. An invalid payload redirects (302) from the validator,
        // which looks identical to a permission refusal — that is how an
        // earlier version of this assertion passed with the middleware
        // removed.
        $payable = collect($remittance->outstanding())->firstWhere(fn ($r) => $r['outstanding'] > 0.005);

        if (!$payable || !$bank) {
            echo "  SKIP  nothing payable to attempt\n";
        } else {
            $countBefore = TaxRemittance::count();

            $post = Illuminate\Http\Request::create('/admin/staff/tax-remittance', 'POST', [
                '_token' => app('session.store')->token(),
                'liability_account_id' => $payable['account_id'],
                'salary_month' => $payable['month'],
                'amount' => $payable['outstanding'],
                'payment_date' => date('Y-m-d'),
                'source_account_id' => $bank->id,
            ]);
            $post->setLaravelSession(app('session.store'));

            $postStatus = 0;

            try {
                $postStatus = $kernel->handle($post)->getStatusCode();
            } catch (Spatie\Permission\Exceptions\UnauthorizedException $e) {
                $postStatus = 403;
            }

            check('the refusal was about permission, not the CSRF token', $postStatus !== 419,
                'status ' . $postStatus);

            check('and a valid payment they are not allowed to make is not recorded',
                TaxRemittance::count() === $countBefore,
                'a remittance was written despite the permission being absent');
        }
    } finally {
        DB::rollBack();
        Auth::guard('web')->login($admin);
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

echo "\n== The journal can say it is a payroll entry ==\n";

// journal_type was an enum without 'payroll', so MySQL silently stored '' on
// every payroll entry and anything grouping the journal by type missed them.
$blank = DB::table('journal_entries')
    ->where(function ($q) { $q->where('journal_type', '')->orWhereNull('journal_type'); })
    ->count();

check('no posted entry has a blank journal type', $blank === 0, "{$blank} blank");

check('payroll entries are typed as payroll',
    DB::table('journal_entries')->where('reference_type', 'payroll')->where('journal_type', 'payroll')->count() > 0);

echo "\n$passed passed, $failed failed\n";

exit($failed > 0 ? 1 : 0);
