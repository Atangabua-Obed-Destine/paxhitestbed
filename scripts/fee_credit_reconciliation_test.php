<?php
/**
 * Fee figures, net of overpayment credit moved between fees.
 *
 * Last academic year First Instalments were overpaid before Second Instalments
 * were configured, and the excess was applied to the Second Instalments as
 * student credit. Applying a credit adds it to the target fee's paid_amount but
 * never took it off the source's, so every total built by summing paid_amount
 * counted that money twice — Total Collected on the fees report, the dashboard,
 * the budget sheet's fee actuals, the General Ledger's fee summary.
 *
 * The expected figures here are worked out from the credit tables directly, NOT
 * through Fee::netPaidSql or the Fee accessors, so a mistake in those cannot
 * agree with itself.
 *
 * Every write runs inside a transaction that is rolled back.
 *
 * Usage: php scripts/fee_credit_reconciliation_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Fee;
use App\Models\StudentCredit;
use App\Services\FeeCreditReconciliation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
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

$kernel = app(Illuminate\Contracts\Http\Kernel::class);
$audit = app(FeeCreditReconciliation::class);

$admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->where('status', '1')->first()
    ?: App\User::where('is_admin', 1)->where('status', '1')->first();
Auth::guard('web')->login($admin);

$render = function (string $url) use ($kernel) {
    $request = Request::create($url, 'GET');
    $request->setLaravelSession(app('session.store'));
    $response = $kernel->handle($request);

    return [$response->getStatusCode(), (string) $response->getContent()];
};

$number = fn (string $text) => (float) str_replace(',', '', $text);

/** Overpayment credit moved out of each fee, from the credit tables alone. */
$movedOut = function (): array {
    $moved = [];

    foreach (DB::table('credit_applications as ca')
        ->join('student_credits as sc', 'sc.id', '=', 'ca.student_credit_id')
        ->where('sc.source_type', 'overpayment')
        ->whereNotNull('sc.source_fee_id')
        ->select('sc.source_fee_id', 'ca.amount_applied')
        ->get() as $row) {
        $moved[$row->source_fee_id] = ($moved[$row->source_fee_id] ?? 0) + (float) $row->amount_applied;
    }

    return $moved;
};

$moved = $movedOut();
$due = fn ($fee) => (float) $fee->fee_amount + (float) $fee->fine_amount - (float) $fee->discount_amount;

// ---------------------------------------------------------------------------

echo "\n== Every total uses the same definition ==\n";

$source = fn (string $path) => file_get_contents(__DIR__ . '/../' . $path);

check('the fees report sums net paid, not paid_amount',
    !str_contains($source('app/Http/Controllers/Admin/FeesStudentController.php'), "\$all_fees->sum('paid_amount')")
    && str_contains($source('app/Http/Controllers/Admin/FeesStudentController.php'), 'net_paid_amount'));
// Two views of the same money. Settlement (net paid) puts moved credit on the
// fee it went to: right for whether a fee is paid. Cash puts money on the fee
// and in the month it actually arrived: right for anything dated. Undated
// totals are identical either way.
check('the dashboard total is net paid, and its dated charts are cash',
    !str_contains($source('app/Http/Controllers/Admin/DashboardController.php'), "->sum('paid_amount')")
    && str_contains($source('app/Http/Controllers/Admin/DashboardController.php'), 'net_paid_amount')
    && str_contains($source('app/Http/Controllers/Admin/DashboardController.php'), 'cashReceivedSql'));
check('the budget sheet\'s fee actuals are cash, dated when it arrived',
    str_contains($source('app/Services/BudgetActualsService.php'), 'cashReceivedSql')
    && !str_contains($source('app/Services/BudgetActualsService.php'), 'SUM(f.paid_amount)'));
check('the daybook books cash only',
    str_contains($source('app/Services/DaybookService.php'), 'cashReceivedSql')
    && str_contains($source('app/Services/DaybookService.php'), 'cash_received'));
check('the income statement\'s fee revenue is cash, dated when it arrived',
    str_contains($source('app/Http/Controllers/Admin/GeneralLedgerController.php'), 'cashReceivedSql')
    && !str_contains($source('app/Http/Controllers/Admin/GeneralLedgerController.php'), "->sum('paid_amount')")
    && !str_contains($source('app/Http/Controllers/Admin/GeneralLedgerController.php'), 'SUM(fees.paid_amount)'));
check('and Academic Health',
    str_contains($source('app/Http/Controllers/Admin/AcademicHealthController.php'), 'netPaidSql'));

// ---------------------------------------------------------------------------

echo "\n== A fee's net paid amount ==\n";

$sourceFeeId = collect($moved)->filter(fn ($amount) => $amount > 0)->keys()->first();

if (!$sourceFeeId) {
    echo "  SKIP  no fee has had overpayment credit moved out of it\n";
} else {
    $fee = Fee::find($sourceFeeId);
    $expectedNet = (float) $fee->paid_amount - $moved[$sourceFeeId];

    check('a fee whose overpayment was moved on counts only what stayed on it',
        abs($fee->net_paid_amount - $expectedNet) < 0.01,
        "net {$fee->net_paid_amount}, expected {$expectedNet}");
    check('loaded with the list, it gives the same answer',
        abs(Fee::withCreditMovedOut()->find($sourceFeeId)->net_paid_amount - $expectedNet) < 0.01);

    if (abs($expectedNet - $due($fee)) < 0.01) {
        check('once its whole excess has gone to another fee, it is fully paid and not overpaid',
            !$fee->isNetOverpaid() && $fee->isOverpaid());
    }
}

$allPaid = (float) DB::table('fees')->sum('paid_amount');
$netTotal = (float) Fee::withCreditMovedOut()->get()->sum(fn ($fee) => $fee->net_paid_amount);

check('across every fee, net paid is paid_amount less the credit moved between fees',
    abs($netTotal - ($allPaid - array_sum(array_intersect_key($moved, array_flip(DB::table('fees')->pluck('id')->all()))))) < 0.01,
    "net {$netTotal}, paid {$allPaid}");

// ---------------------------------------------------------------------------

echo "\n== Cash, in the month it arrived ==\n";

// Worked out from the credit tables, not through Fee::cashReceivedSql.
$existingFeeIds = DB::table('fees')->pluck('id')->map(fn ($id) => (int) $id)->all();
$creditIntoExisting = (float) DB::table('credit_applications')->whereIn('fee_id', $existingFeeIds)->sum('amount_applied');
$transferredOutOfExisting = (float) DB::table('student_credits')
    ->where('source_type', StudentCredit::SOURCE_TRANSFER)
    ->whereIn('source_fee_id', $existingFeeIds)
    ->sum('original_amount');
$cashTotal = (float) DB::table('fees')->sum(DB::raw(Fee::cashReceivedSql('fees')));

check('cash received is paid_amount, less credit brought in from other fees, plus anything transferred out',
    abs($cashTotal - ($allPaid - $creditIntoExisting + $transferredOutOfExisting)) < 0.01,
    'cash ' . number_format($cashTotal) . ', expected ' . number_format($allPaid - $creditIntoExisting + $transferredOutOfExisting));
check('across all fees it is the same money as net paid, only dated differently',
    abs($cashTotal - $netTotal) < 0.01,
    'cash ' . number_format($cashTotal) . ' vs net ' . number_format($netTotal));

$cashOf = fn (int $feeId) => (float) DB::table('fees')->where('id', $feeId)->value(DB::raw(Fee::cashReceivedSql('fees')));
$creditInto = fn (int $feeId) => (float) DB::table('credit_applications')->where('fee_id', $feeId)->sum('amount_applied');

// A fee settled entirely by credit received no cash, so it must not appear as
// cash in the month the credit was applied.
$creditOnlyFeeId = DB::table('fees')->where('paid_amount', '>', 0)->pluck('paid_amount', 'id')
    ->filter(fn ($paid, $id) => abs((float) $paid - $creditInto((int) $id)) < 0.01)
    ->keys()->first();

if ($creditOnlyFeeId) {
    check('a fee settled only by credit from another fee received no cash',
        abs($cashOf((int) $creditOnlyFeeId)) < 0.01,
        'fee #' . $creditOnlyFeeId . ' cash ' . $cashOf((int) $creditOnlyFeeId));
} else {
    echo "  SKIP  no fee is settled entirely by credit\n";
}

if ($sourceFeeId) {
    $sourceFee = Fee::find($sourceFeeId);
    $expectedCash = (float) $sourceFee->paid_amount - $creditInto((int) $sourceFeeId)
        + (float) DB::table('student_credits')->where('source_type', StudentCredit::SOURCE_TRANSFER)
            ->where('source_fee_id', $sourceFeeId)->sum('original_amount');

    check('the fee an overpayment was paid on keeps all the cash it received, in its own month',
        abs($cashOf((int) $sourceFeeId) - $expectedCash) < 0.01 && $cashOf((int) $sourceFeeId) > $sourceFee->net_paid_amount,
        'cash ' . $cashOf((int) $sourceFeeId) . ', net paid ' . $sourceFee->net_paid_amount);
}

echo "\n== The report's cards ==\n";

$reportUrl = '/admin/fees-student-report?faculty=0&program=0&session=0&semester=0&section=0&category=0&payment_status=all&student_id=';
[$status, $html] = $render($reportUrl);
$text = trim(preg_replace('/\s+/', ' ', strip_tags($html)));

check('the report renders', $status === 200, (string) $status);

// The fees the report covers: every status, and only fees of enrolled students.
$set = Fee::whereIn('status', [0, 1, 2, 3])->whereHas('studentEnroll.student')->get();

$expected = ['collected' => 0.0, 'outstanding' => 0.0, 'fully' => 0, 'overpaid' => 0, 'overpaid_amount' => 0.0, 'paid_amount' => 0.0];

foreach ($set as $fee) {
    $net = (float) $fee->paid_amount - ($moved[$fee->id] ?? 0);
    $owed = $due($fee);

    $expected['collected'] += $net;
    $expected['paid_amount'] += (float) $fee->paid_amount;
    $expected['outstanding'] += max(0, $owed - $net);

    if ((int) $fee->status === 1 && $net <= $owed + 0.005) {
        $expected['fully']++;
    }
    if ($net > $owed + 0.005) {
        $expected['overpaid']++;
        $expected['overpaid_amount'] += $net - $owed;
    }
}

preg_match('/Total Collected\s*([\d,]+(?:\.\d+)?)/', $text, $collected);
preg_match('/Outstanding Balance\s*([\d,]+(?:\.\d+)?)/', $text, $outstanding);
preg_match('/(\d+)\s*Total Fully Paid Fees/', $text, $fully);
// The count and the amount share one card: '3 fees - paid beyond what was billed'.
preg_match('/Overpaid Amount\s*([\d,]+(?:\.\d+)?)/', $text, $overpaidAmount);
preg_match('/(\d+)\s*fees?\s*[^\d]*paid beyond what was billed/', $text, $overpaid);

check('Total Collected is what was actually collected',
    isset($collected[1]) && abs($number($collected[1]) - $expected['collected']) < 0.01,
    ($collected[1] ?? 'not found') . ' vs ' . number_format($expected['collected']));
check('and no longer the double-counted sum of paid_amount',
    $expected['paid_amount'] - $expected['collected'] < 0.01
    || (isset($collected[1]) && abs($number($collected[1]) - $expected['paid_amount']) > 0.01));
check('Outstanding is unchanged in meaning: only what is still owed',
    isset($outstanding[1]) && abs($number($outstanding[1]) - $expected['outstanding']) < 0.01,
    ($outstanding[1] ?? 'not found') . ' vs ' . number_format($expected['outstanding']));
check('Fully Paid counts the instalments whose overpayment was moved on',
    isset($fully[1]) && (int) $fully[1] === $expected['fully'],
    ($fully[1] ?? 'not found') . ' vs ' . $expected['fully']);
check('Overpaid counts only fees still holding an excess',
    $expected['overpaid'] === 0
        ? !isset($overpaid[1])
        : (isset($overpaid[1]) && (int) $overpaid[1] === $expected['overpaid']),
    ($overpaid[1] ?? 'not shown') . ' vs ' . $expected['overpaid']);
check('the Overpaid card has a label, not a translation key', !str_contains($text, 'total_overpaid_fees'));
check('and it shows how much was overpaid, so Amount less Outstanding plus it equals Collected',
    $expected['overpaid'] === 0
        ? !isset($overpaidAmount[1])
        : (isset($overpaidAmount[1]) && abs($number($overpaidAmount[1]) - $expected['overpaid_amount']) < 0.01),
    ($overpaidAmount[1] ?? 'not shown') . ' vs ' . number_format($expected['overpaid_amount'], 2));

if ($sourceFeeId) {
    check('a fee whose overpayment moved on says so in its row', str_contains($text, 'moved to another fee'));
}

// ---------------------------------------------------------------------------

echo "\n== The payment status filters ==\n";

$netOverpaidIds = $set->filter(fn ($fee) => (float) $fee->paid_amount - ($moved[$fee->id] ?? 0) > $due($fee) + 0.005)
    ->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();

[, $overpaidHtml] = $render(str_replace('payment_status=all', 'payment_status=5', $reportUrl));
preg_match_all('/payModal-(\d+)"/', $overpaidHtml, $shown);
$shownIds = collect($shown[1])->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();

check('Overpaid lists exactly the fees still holding an excess',
    $shownIds === $netOverpaidIds,
    json_encode($shownIds) . ' vs ' . json_encode($netOverpaidIds));

if ($sourceFeeId && !Fee::find($sourceFeeId)->isNetOverpaid()) {
    [, $paidHtml] = $render(str_replace('payment_status=all', 'payment_status=1', $reportUrl));
    check('Fully Paid includes a fee whose overpayment was moved on',
        str_contains($paidHtml, 'payModal-' . $sourceFeeId . '"'));
}

// ---------------------------------------------------------------------------

echo "\n== Credit raised twice for one overpayment ==\n";

foreach ($audit->duplicateCredits() as $finding) {
    if (abs($finding['credited'] - $finding['genuine_overpayment'] - $finding['excess']) > 0.01
        || $finding['voidable'] + $finding['already_spent'] - $finding['excess'] > 0.01) {
        check("fee #{$finding['fee_id']} adds up", false, json_encode($finding));
    }
}
check('every finding adds up: credited less the genuine overpayment is the excess, and it is all accounted for', true);

$creditSnapshot = fn () => json_encode(DB::table('student_credits')
    ->selectRaw('COUNT(*) n, SUM(remaining_amount) remaining, SUM(original_amount) original')->first());
$applicationsSnapshot = fn () => json_encode(DB::table('credit_applications')
    ->selectRaw('COUNT(*) n, SUM(amount_applied) applied')->first());

// A duplicate made on purpose, so this is tested whatever the data holds.
$withCredits = StudentCredit::where('source_type', StudentCredit::SOURCE_OVERPAYMENT)->whereNotNull('source_fee_id')
    ->whereHas('sourceFee')->orderBy('id')->first();

if ($withCredits) {
    DB::beginTransaction();

    try {
        $studentId = $withCredits->student_id;
        $before = $audit->duplicateCredits()->firstWhere('fee_id', $withCredits->source_fee_id);
        $excessBefore = $before['excess'] ?? 0;
        $balanceBefore = (float) StudentCredit::where('student_id', $studentId)->available()->sum('remaining_amount');
        // Real duplicates this student already has; voiding clears those too.
        $studentVoidableBefore = (float) $audit->duplicateCredits()->where('student_id', $studentId)->sum('voidable');
        $applied = $applicationsSnapshot();

        $duplicate = StudentCredit::create([
            'student_id' => $studentId,
            'original_amount' => 12345,
            'remaining_amount' => 12345,
            'source_fee_id' => $withCredits->source_fee_id,
            'source_type' => StudentCredit::SOURCE_OVERPAYMENT,
            'status' => StudentCredit::STATUS_AVAILABLE,
            'note' => 'test duplicate',
        ]);

        $after = $audit->duplicateCredits()->firstWhere('fee_id', $withCredits->source_fee_id);

        check('a credit raised again for an overpayment already credited is found',
            $after && abs($after['excess'] - $excessBefore - 12345) < 0.01,
            json_encode($after));
        check('and the newest credit is the one taken',
            $after && ($after['plan'][0]['credit_id'] ?? null) === $duplicate->id);

        $result = $audit->voidDuplicateCredits($admin->id, 'test');
        $duplicate->refresh();

        check('voiding it leaves nothing to spend', (float) $duplicate->remaining_amount === 0.0);
        check('and marks an unapplied credit expired, not applied',
            $duplicate->status === StudentCredit::STATUS_EXPIRED, $duplicate->status);
        check('and says why on the credit itself', str_contains((string) $duplicate->note, 'Voided'));
        check('nothing already applied to a fee is touched', $applicationsSnapshot() === $applied);
        // Before: B. The test duplicate added 12,345; voiding removes it and the
        // student's own real duplicates. What is left is B less those.
        $balanceAfter = (float) StudentCredit::where('student_id', $studentId)->available()->sum('remaining_amount');
        check('the student can spend exactly what was backed by a payment, and no more',
            abs($balanceAfter - ($balanceBefore - $studentVoidableBefore)) < 0.01,
            "after {$balanceAfter}, expected " . ($balanceBefore - $studentVoidableBefore));
        check('run again, there is nothing left to void',
            $audit->duplicateCredits()->sum('voidable') < 0.01);
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------

echo "\n== The production command ==\n";

$creditsBefore = $creditSnapshot();

$exit = Artisan::call('fees:credit-audit', ['--show' => 3]);
$output = Artisan::output();

check('the audit runs', $exit === 0);
check('reports the collected figure net of moved credit', str_contains($output, 'collected ' . number_format($audit->collected()['net_paid'], 0)));
check('and says it changed nothing', str_contains($output, 'Nothing was changed.'));
check('and truly changed nothing', $creditSnapshot() === $creditsBefore);

$voidable = $audit->duplicateCredits()->sum('voidable');

DB::beginTransaction();

try {
    $exit = Artisan::call('fees:credit-audit', ['--void-duplicates' => true, '--force' => true, '--show' => 3]);

    check('with --void-duplicates --force it applies the correction', $exit === 0);
    check('leaving no unbacked credit to spend', $audit->duplicateCredits()->sum('voidable') < 0.01);
    check('having voided exactly what the preview said it would',
        $voidable < 0.01 || str_contains(Artisan::output(), 'Voided ' . number_format($voidable, 0)),
        Artisan::output());
} finally {
    DB::rollBack();
}

check('and the test left the credits exactly as it found them', $creditSnapshot() === $creditsBefore);

// ---------------------------------------------------------------------------

/** A fee's active posting, straight from the mapping and journal tables. */
$postingOf = fn (int $feeId) => DB::table('transaction_mappings as tm')
    ->join('journal_entries as je', 'je.id', '=', 'tm.journal_entry_id')
    ->where('tm.transaction_type', 'fee')->where('tm.transaction_id', $feeId)->where('tm.status', 'active')
    ->select('je.id', 'je.total_debit', 'je.entry_date', 'je.accounting_period_id')->first();

/** The cash a fee received, from the fee and credit tables alone. */
$cashOf = fn (int $feeId) => round((float) DB::table('fees')->where('id', $feeId)->value('paid_amount')
    - (float) DB::table('credit_applications')->where('fee_id', $feeId)->sum('amount_applied')
    + (float) DB::table('student_credits')->where('source_fee_id', $feeId)->where('source_type', 'transfer')->sum('original_amount'), 2);

/** Every posted fee whose posting differs from its cash, worked out independently. */
$mismatches = function () use ($cashOf) {
    $out = [];

    foreach (DB::table('transaction_mappings as tm')
        ->join('journal_entries as je', 'je.id', '=', 'tm.journal_entry_id')
        ->join('fees as f', 'f.id', '=', 'tm.transaction_id')
        ->whereNull('f.payment_plan_id')
        ->where('tm.transaction_type', 'fee')->where('tm.status', 'active')
        ->select('tm.transaction_id', 'je.total_debit')->get() as $row) {
        $difference = round((float) $row->total_debit - $cashOf((int) $row->transaction_id), 2);

        if (abs($difference) >= 0.01) {
            $out[(int) $row->transaction_id] = $difference;
        }
    }

    ksort($out);

    return $out;
};

$journalSnapshot = fn () => json_encode(DB::table('journal_entries')
    ->selectRaw('COUNT(*) n, SUM(total_debit) debit, SUM(is_reversed) reversed')->first());

echo "\n== The ledger correction: preview ==\n";

$expected = $mismatches();
$preview = $audit->ledgerCorrections();

check('the preview lists exactly the fees whose posting differs from the cash received',
    $preview->pluck('fee_id')->sort()->values()->all() === array_keys($expected),
    'preview ' . $preview->count() . ', expected ' . count($expected));
check('with the same amount for each, to the franc',
    $preview->every(fn ($row) => abs($row['difference'] - ($expected[$row['fee_id']] ?? INF)) < 0.01));

echo "\n== The ledger correction: applied ==\n";

if ($preview->isEmpty()) {
    echo "  SKIP  no fee posting needs correcting in this database\n";
} else {
    DB::beginTransaction();

    try {
        $journalBefore = (int) DB::table('journal_entries')->count();
        $originals = $preview->mapWithKeys(fn ($row) => [$row['fee_id'] => $postingOf($row['fee_id'])->id]);

        $result = $audit->correctLedger($admin->id);

        check('every fee in the preview is corrected, none fail',
            $result['corrected'] === $preview->count() && $result['failed'] === [], json_encode($result));
        check('by the amount the preview showed', abs($result['amount'] - $preview->sum('difference')) < 0.01,
            $result['amount'] . ' vs ' . $preview->sum('difference'));
        check('afterwards every posting equals the cash received', $mismatches() === [], json_encode(array_slice($mismatches(), 0, 5, true)));
        check('and the ledger is no longer overstated', abs($audit->ledgerExposure()['overstated']) < 0.01,
            (string) $audit->ledgerExposure()['overstated']);

        $reversals = DB::table('journal_entries')->whereIn('reversed_entry_id', $originals->values())->pluck('reversed_entry_id')->unique();
        check('each original entry is reversed, not deleted',
            DB::table('journal_entries')->whereIn('id', $originals->values())->where('is_reversed', 1)->count() === $originals->count()
                && $reversals->count() === $originals->count());
        check('so the journal only grows', (int) DB::table('journal_entries')->count() > $journalBefore);

        $journal = $journalSnapshot();
        $again = $audit->correctLedger($admin->id);
        check('run again, it finds nothing and writes nothing', $again['corrected'] === 0 && $journalSnapshot() === $journal);

        $sheet = app(App\Services\BudgetReconciliationService::class)->reconcile();
        check('the budget sheet then reconciles with no known difference',
            $sheet['agrees'] === true && empty($sheet['known_differences']),
            json_encode(['agrees' => $sheet['agrees'], 'known' => $sheet['known_differences'] ?? null, 'issues' => $sheet['issues']]));
    } finally {
        DB::rollBack();
    }

    echo "\n== A correction for a closed month ==\n";

    DB::beginTransaction();

    try {
        $row = $preview->first(fn ($r) => $postingOf($r['fee_id'])->accounting_period_id);

        if (!$row) {
            echo "  SKIP  no correction has a posting inside an accounting period\n";
        } else {
            $original = $postingOf($row['fee_id']);
            DB::table('accounting_periods')->where('id', $original->accounting_period_id)->update(['is_closed' => 1]);

            check('the preview says it will land today',
                $audit->ledgerCorrections()->firstWhere('fee_id', $row['fee_id'])['lands_today'] === true);

            app(App\Services\FeeLedgerPosting::class)->resync(Fee::find($row['fee_id']), $admin->id);
            $reversal = DB::table('journal_entries')->where('reversed_entry_id', $original->id)->first();

            check('its reversal is dated today, not in the closed month',
                $reversal && substr((string) $reversal->entry_date, 0, 10) === now()->toDateString(),
                json_encode($reversal ? ['date' => $reversal->entry_date, 'period' => $reversal->accounting_period_id] : null));
            check('and not filed in the closed period', $reversal && (int) $reversal->accounting_period_id !== (int) $original->accounting_period_id);
            // A fee that received no cash — settled wholly by credit carried
            // over from another fee — ends with no posting at all, which is the
            // correction: the money was posted on the fee it arrived at.
            $corrected = $postingOf($row['fee_id']);
            check('and the fee is posted at its cash received, or not at all when it received none',
                $cashOf($row['fee_id']) > 0.009
                    ? ($corrected && abs((float) $corrected->total_debit - $cashOf($row['fee_id'])) < 0.01)
                    : $corrected === null,
                'cash ' . $cashOf($row['fee_id']) . ', posted ' . json_encode($corrected));
        }
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------

echo "\n== New credit and transfers post only cash ==\n";

$posted = Fee::query()
    ->whereNull('payment_plan_id')->where('paid_amount', '>=', 20000)->whereNotNull('pay_date')
    ->whereNotIn('id', $preview->pluck('fee_id')->all() ?: [0])
    ->whereIn('id', DB::table('transaction_mappings')->where('transaction_type', 'fee')->where('status', 'active')->select('transaction_id'))
    ->whereHas('studentEnroll')
    ->orderByDesc('id')->first();

if (!$posted) {
    echo "  SKIP  no correctly posted, paid fee to test with\n";
} else {
    $credits = app(App\Services\StudentCreditService::class);
    $studentId = $posted->studentEnroll->student_id;

    $blankCopy = function () use ($posted) {
        $copy = $posted->replicate();
        $copy->paid_amount = 0;
        $copy->status = 0;
        $copy->pay_date = now()->toDateString();
        $copy->note = 'test copy';
        $copy->saveQuietly();

        return $copy->fresh();
    };

    DB::beginTransaction();

    try {
        // A fee part-paid in cash, then topped up from credit.
        $partPaid = $blankCopy();
        $partPaid->paid_amount = 10000;
        $partPaid->status = 2;
        $partPaid->save();
        $before = $postingOf($partPaid->id);
        $credits->createManualCredit($studentId, 7000, 'test credit', $admin->id);
        $credits->applyCreditsToFee($partPaid->fresh(), 3000, 'manual', $admin->id);
        $after = $postingOf($partPaid->id);

        check('credit applied to a fee part-paid in cash leaves its posting at the cash',
            $before && abs((float) $before->total_debit - 10000) < 0.01 && $after && $after->id === $before->id && (float) $partPaid->fresh()->paid_amount === 13000.0,
            json_encode(['before' => $before, 'after' => $after]));
        check('which is the cash it received', $after && abs((float) $after->total_debit - $cashOf($partPaid->id)) < 0.01);

        $settledByCredit = $blankCopy();
        $credits->applyCreditsToFee($settledByCredit, 4000, 'manual', $admin->id);

        check('a fee settled wholly by credit is not posted as cash at all',
            (float) $settledByCredit->fresh()->paid_amount > 0 && $postingOf($settledByCredit->id) === null);
    } catch (\Throwable $e) {
        check('applying credit runs', false, $e->getMessage());
    } finally {
        DB::rollBack();
    }

    DB::beginTransaction();

    try {
        $target = $blankCopy();
        $credits->transferBetweenFees($posted->fresh(), $target, 5000, 'test transfer', $admin->id);

        $source = $postingOf($posted->id);
        check('after a transfer the source is posted at its cash received',
            $source && abs((float) $source->total_debit - $cashOf($posted->id)) < 0.01,
            json_encode(['posted' => $source->total_debit ?? null, 'cash' => $cashOf($posted->id)]));
        check('and the target, which received no cash, is not posted',
            $postingOf($target->id) === null && abs($cashOf($target->id)) < 0.01);
        check('so the transfer adds nothing to the ledger', $mismatches() == $expected);
    } catch (\Throwable $e) {
        check('the transfer runs', false, $e->getMessage());
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------

echo "\n== Posting a fee by hand, or in bulk ==\n";

// The observer is not the only way a fee reaches the ledger: Accounting →
// Mappings lists unposted transactions and posts them, one at a time or in
// bulk, and `ledger:sync` does the same from the terminal. Those paths used
// paid_amount, so posting from there put the credit back into cash and undid
// the correction — found in use, after a correction had already been made.
$sync = app(App\Services\LedgerSyncService::class);

$creditSettled = Fee::withCreditMovedOut()->with('category')
    ->whereNull('payment_plan_id')->where('paid_amount', '>', 0)->whereNotNull('pay_date')
    ->get()->first(fn ($fee) => $fee->cash_received_amount <= 0.009);

$partlyCredit = Fee::withCreditMovedOut()->with('category')
    ->whereNull('payment_plan_id')->where('paid_amount', '>', 0)->whereNotNull('pay_date')
    ->get()->first(fn ($fee) => $fee->cash_received_amount > 0.009
        && $fee->cash_received_amount < (float) $fee->paid_amount - 0.009);

$source = new ReflectionMethod($sync, 'source');
$source->setAccessible(true);

if (!$creditSettled) {
    echo "  SKIP  no fee settled wholly by credit to test with\n";
} else {
    [, $payload, $refusal] = $source->invoke($sync, 'fee', $creditSettled);

    check('a fee settled wholly by credit is refused, not posted again',
        $payload === null && $refusal !== null, json_encode([$payload, $refusal]));
    check('and the refusal says why', $refusal && str_contains(json_encode($refusal), 'credit'), json_encode($refusal));
}

if (!$partlyCredit) {
    echo "  SKIP  no part-credit fee to test with\n";
} else {
    [, $payload] = $source->invoke($sync, 'fee', $partlyCredit);

    check('a fee part-settled by credit is posted at the cash it received',
        $payload && abs((float) $payload['amount'] - $partlyCredit->cash_received_amount) < 0.01,
        json_encode($payload) . ' cash ' . $partlyCredit->cash_received_amount);
    check('which is less than its paid_amount',
        $payload && (float) $payload['amount'] < (float) $partlyCredit->paid_amount);
}

// The screen that lists what is waiting to be posted decides through the same
// service, so a credit-settled fee must be refused there rather than offered.
if ($creditSettled) {
    $decision = $sync->decide('fee', $creditSettled, false);

    check('Accounting -> Mappings refuses to post a credit-settled fee',
        ($decision['can_sync'] ?? $decision['sync'] ?? true) === false || isset($decision['reason']),
        json_encode($decision));
    check('and gives the reason, rather than offering it at its full amount',
        str_contains(strtolower(json_encode($decision)), 'credit'), json_encode($decision));
}

// ---------------------------------------------------------------------------

echo "\n== The Credit audit page ==\n";

$post = function (string $url) use ($kernel) {
    $session = app('session.store');
    $request = Request::create($url, 'POST', ['_token' => $session->token()]);
    $request->headers->set('Accept', 'application/json');
    $request->headers->set('X-Requested-With', 'XMLHttpRequest');
    $request->setLaravelSession($session);
    $response = $kernel->handle($request);

    return [$response->getStatusCode(), (string) $response->getContent()];
};

[$status, $html] = $render(url('admin/fees-credit-audit'));
check('Super Admin can open it', $status === 200, "status $status");
check('with both corrections offered', str_contains($html, 'btn-void-duplicates') && str_contains($html, 'btn-correct-ledger'));
check('showing the amount posted twice', $preview->isEmpty() || str_contains($html, number_format($preview->sum('difference'), 0)));

[, $html] = $render(url('admin/fees-student-report'));
check('and finds it in the menu', str_contains($html, 'fees-credit-audit'));

$outsider = App\User::where('status', '1')->where('id', '!=', $admin->id)->get()
    ->first(fn ($u) => !$u->can('fee-credit-audit-view') && !$u->can('fee-credit-audit-correct'));

if (!$outsider) {
    echo "  SKIP  every user has the credit audit permissions\n";
} else {
    Auth::guard('web')->login($outsider);

    try {
        [$status] = $render(url('admin/fees-credit-audit'));
        check('a user without the permission is refused the page', $status === 403, "status $status");

        if ($outsider->can('fees-student-report')) {
            [, $html] = $render(url('admin/fees-student-report'));
            check('and is not shown it in the menu', !str_contains($html, 'fees-credit-audit'));
        }

        DB::beginTransaction();

        try {
            $journal = $journalSnapshot();
            $creditState = $creditSnapshot();

            [$voidStatus] = $post(url('admin/fees-credit-audit/void-duplicates'));
            [$ledgerStatus] = $post(url('admin/fees-credit-audit/correct-ledger'));

            check('and refused both corrections', $voidStatus === 403 && $ledgerStatus === 403, "void $voidStatus, ledger $ledgerStatus");
            check('which write nothing', $journalSnapshot() === $journal && $creditSnapshot() === $creditState);
        } finally {
            DB::rollBack();
        }
    } finally {
        Auth::guard('web')->login($admin);
    }
}

DB::beginTransaction();

try {
    [$status, $body] = $post(url('admin/fees-credit-audit/void-duplicates'));
    $json = json_decode($body, true);
    check('Super Admin: voiding from the page works', $status === 200 && ($json['success'] ?? false) === true, "status $status " . substr($body, 0, 200));
    check('and leaves no duplicated credit', $audit->duplicateCredits()->sum('voidable') < 0.01);

    [$status, $body] = $post(url('admin/fees-credit-audit/correct-ledger'));
    $json = json_decode($body, true);
    check('correcting the ledger from the page works', $status === 200 && ($json['success'] ?? false) === true, "status $status " . substr($body, 0, 300));
    check('and leaves every posting at the cash received', $mismatches() === []);
} finally {
    DB::rollBack();
}

check('and the test left the ledger and credits exactly as it found them',
    $mismatches() == $expected && $creditSnapshot() === $creditsBefore);

echo "\n$passed passed, $failed failed\n";

exit($failed > 0 ? 1 : 0);
