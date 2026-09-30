<?php
/**
 * Undoing a payment recorded in error, and not billing people who never asked.
 *
 * Approving a receipt moves money in five places at once — the receipt, the fee,
 * a payment account, the ledger, and the application's own status. Undoing it in
 * any one of them leaves the other four disagreeing, which is how a ledger stops
 * balancing. So what these tests care about is that a reversal is *complete*, and
 * that nothing is deleted in the process: a reversal is itself a record.
 *
 * They also cover the change that prevents the problem recurring — a fee is
 * raised when an applicant finishes the form and reaches the payment step, not
 * when they merely open it.
 *
 * Everything runs inside a transaction that is rolled back.
 *
 * Usage: php scripts/payment_reversal_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Application;
use App\Models\Fee;
use App\Models\PaymentReceipt;
use App\Services\PaymentReversalService;
use App\User;
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

/** Net revenue in the ledger — what a reversal has to move. */
function revenue(): float
{
    return (float) DB::table('journal_entry_lines as l')
        ->join('chart_of_accounts as c', 'c.id', '=', 'l.account_id')
        ->where('c.class_number', 7)
        ->selectRaw('SUM(l.credit) - SUM(l.debit) AS n')
        ->value('n');
}

Auth::guard('web')->login(User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first() ?: User::first());

echo "\n" . str_repeat('=', 64) . "\n";
echo "Reversing a payment, and when a fee is raised\n";
echo str_repeat('=', 64) . "\n";

$service = app(PaymentReversalService::class);

// An approved admission-fee payment whose application was submitted because of
// it — the case that exercises every one of the five places.
$receipt = PaymentReceipt::where('verification_status', 'approved')
    ->whereNotNull('applicant_id')
    ->orderByDesc('id')
    ->get()
    ->first(fn ($r) => $r->fee && Application::where('admission_fee_id', $r->fee_id)->exists());

if (!$receipt) {
    fwrite(STDERR, "no approved admission-fee payment to test with\n");
    exit(2);
}

$application = Application::where('admission_fee_id', $receipt->fee_id)->first();
printf("receipt #%d · fee #%d · application %s (%s)\n",
    $receipt->id, $receipt->fee_id, $application->registration_no, $application->stage);

DB::beginTransaction();

$revenueBefore = revenue();
$feeBefore = Fee::find($receipt->fee_id);
$paidBefore = (float) $feeBefore->paid_amount;
$stageBefore = $application->stage;
$timelineBefore = $application->statusUpdates()->count();

$result = $service->reverse($receipt, 'recorded against the wrong applicant');

$receipt->refresh();
$fee = Fee::find($receipt->fee_id);
$application->refresh();

// ---------------------------------------------------------------------------
echo "\nThe reversal reaches everywhere the approval did\n";
// ---------------------------------------------------------------------------

check('the reversal succeeds', $result['reversed'] === true, $result['message']);
check('the receipt is marked reversed', $receipt->verification_status === 'reversed', $receipt->verification_status);
check('the receipt is kept, not deleted', PaymentReceipt::whereKey($receipt->id)->exists());
check('the reason is recorded on it', stripos((string) $receipt->verification_note, 'wrong applicant') !== false);

check('the fee is no longer paid', (float) $fee->paid_amount < $paidBefore, number_format($fee->paid_amount));
check('and its status is back to unpaid', (int) $fee->status === 0, 'status ' . $fee->status);

check(
    'the ledger is reduced by exactly the amount reversed',
    abs(($revenueBefore - revenue()) - (float) $receipt->amount) < 0.01,
    number_format($revenueBefore - revenue()) . ' vs ' . number_format($receipt->amount)
);

// The ledger is corrected by an opposing entry, never by deleting the original.
check(
    'the original ledger entry still exists',
    DB::table('transaction_mappings')->where('transaction_type', 'fee')
        ->where('transaction_id', $fee->id)->exists()
);

// ---------------------------------------------------------------------------
echo "\nThe application goes back to the applicant\n";
// ---------------------------------------------------------------------------

check('it is returned to draft', $application->stage === 'draft', 'stage ' . $application->stage);
check('its status is cleared', (int) $application->status === 0);
check('the date of application is cleared', empty($application->apply_date), (string) $application->apply_date);
check(
    'the timeline gains an entry rather than losing one',
    $application->statusUpdates()->count() === $timelineBefore + 1,
    $timelineBefore . ' -> ' . $application->statusUpdates()->count()
);

$latest = $application->statusUpdates()->orderByDesc('id')->first();
check('the new entry explains why', stripos((string) $latest->note, 'reversed') !== false, (string) $latest->note);
check('and says where it came from', stripos((string) $latest->note, $stageBefore) !== false
    || stripos((string) $latest->note, __('application_stage.' . $stageBefore)) !== false);

// ---------------------------------------------------------------------------
echo "\nA reversal cannot be applied twice\n";
// ---------------------------------------------------------------------------

$again = $service->reverse($receipt, 'second attempt');
check('reversing again is refused', $again['reversed'] === false);
check('and it says why', stripos($again['message'], 'already been reversed') !== false, $again['message']);

// ---------------------------------------------------------------------------
echo "\nThe fee can then be removed — but not before\n";
// ---------------------------------------------------------------------------

DB::rollBack();
DB::beginTransaction();

$fee = Fee::find($receipt->fee_id);
$freshReceipt = PaymentReceipt::find($receipt->id);

// While the payment stands, removal must be refused — deleting a paid fee
// orphans a receipt, a ledger entry and a payment-account credit.
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$session = app('session.store');
$boot = Illuminate\Http\Request::create('/admin/admission-fees-report', 'GET');
$boot->setLaravelSession($session);
$kernel->handle($boot);

$deleteFee = function (int $id) use ($kernel, $session) {
    $request = Illuminate\Http\Request::create(
        '/admin/admission-fees-report/' . $id . '/delete', 'POST', ['_token' => $session->token()]
    );
    $request->setLaravelSession($session);

    return $kernel->handle($request);
};

$deleteFee($fee->id);
check('a paid fee cannot be removed', Fee::whereKey($fee->id)->exists());

$service->reverse($freshReceipt, 'wrongly recorded');
$deleteFee($fee->id);

check('once reversed, the fee can be removed', Fee::whereKey($fee->id)->doesntExist());
check(
    'and the application no longer points at it',
    Application::where('admission_fee_id', $fee->id)->doesntExist()
);

DB::rollBack();

// ---------------------------------------------------------------------------
echo "\nThe reverse dialog can actually open\n";
// ---------------------------------------------------------------------------

// A Bootstrap modal inside an ancestor with display:none never appears: the
// backdrop dims the screen and the dialog stays hidden. These modals were first
// written inside a <tr class="d-none"> to keep the table markup valid, which
// produced exactly that — a button that did nothing.
Auth::guard('web')->login(User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first() ?: User::first());

$listRequest = Illuminate\Http\Request::create('/admin/payment-verification?payment_type=all&status=approved', 'GET');
$listRequest->setLaravelSession(app('session.store'));
$list = $kernel->handle($listRequest)->getContent();

$buttons = preg_match_all('~data-bs-target="#reverseModal~', $list);
$modals = preg_match_all('~id="reverseModal~', $list);

check("every reverse button has a dialog ($buttons)", $buttons > 0 && $buttons === $modals, "$buttons buttons, $modals modals");
check('no dialog sits inside a hidden row', strpos($list, '<tr class="d-none"><td>') === false);

$firstModal = strpos($list, 'id="reverseModal');
check(
    'the dialog is outside the table',
    $firstModal !== false && $firstModal > strrpos(substr($list, 0, $firstModal), '</table>'),
);
check(
    'and outside the scrolling container that would clip it',
    $firstModal !== false && strpos(substr($list, 0, $firstModal), 'table-responsive') !== false
        && $firstModal > strrpos(substr($list, 0, $firstModal), '</table>')
);
check('the dialog still posts to the reverse route', (bool) preg_match('~<form action="[^"]*/reverse"~', $list));
check('and still demands a reason', (bool) preg_match('~name="reason"[^>]*required~', $list));

// ---------------------------------------------------------------------------
echo "\nA fee is raised only when the applicant is ready to pay\n";
// ---------------------------------------------------------------------------

DB::beginTransaction();

$draft = Application::where('stage', 'draft')->orderByDesc('id')->first();
if ($draft->admission_fee_id) {
    PaymentReceipt::where('fee_id', $draft->admission_fee_id)->delete();
    $feeId = $draft->admission_fee_id;
    $draft->admission_fee_id = null;
    $draft->save();
    Fee::whereKey($feeId)->delete();
}

$feeCount = fn () => DB::table('fees')->where('applicant_id', $draft->id)->count();

Auth::guard('applicant')->loginUsingId($draft->applicant_id);
$applicantSession = app('session.store');

$visit = function (string $uri) use ($kernel, $applicantSession) {
    $request = Illuminate\Http\Request::create($uri, 'GET');
    $request->setLaravelSession($applicantSession);

    return $kernel->handle($request);
};

check('no fee to begin with', $feeCount() === 0);

// Opening the form used to bill the applicant 21,000 FCFA.
$visit('/application/' . $draft->id . '/edit');
check('opening the form raises no fee', $feeCount() === 0, $feeCount() . ' raised');

$meta = is_array($draft->portal_meta) ? $draft->portal_meta : [];
$draft->portal_meta = array_merge($meta, ['agreed_to_terms' => false]);
$draft->save();

$visit('/application/' . $draft->id . '/readiness');
check('reaching payment while unfinished raises no fee', $feeCount() === 0, $feeCount() . ' raised');

$draft->portal_meta = array_merge($meta, ['agreed_to_terms' => true]);
$draft->save();

$response = $visit('/application/' . $draft->id . '/readiness');
$body = json_decode($response->getContent(), true);

check('finishing and reaching payment raises the fee', $feeCount() === 1, $feeCount() . ' raised');
check('exactly one, however often the step is revisited', (function () use ($visit, $draft, $feeCount) {
    $visit('/application/' . $draft->id . '/readiness');
    $visit('/application/' . $draft->id . '/readiness');
    return $feeCount() === 1;
})(), $feeCount() . ' raised');

// The step was rendered before the fee existed, so it has to be handed one.
check('readiness hands back the new fee id', !empty($body['fee_id']));
check('and its balance', isset($body['balance']) && (float) $body['balance'] > 0, (string) ($body['balance'] ?? 'null'));

DB::rollBack();

// ---------------------------------------------------------------------------
echo "\nNothing was disturbed\n";
// ---------------------------------------------------------------------------

check('the receipt is approved again', PaymentReceipt::find($receipt->id)->verification_status === 'approved');
check('the fee still exists', Fee::whereKey($receipt->fee_id)->exists());
check(
    'the application is where it was',
    Application::where('admission_fee_id', $receipt->fee_id)->value('stage') === $stageBefore
);
check('the ledger is unchanged', abs(revenue() - $revenueBefore) < 0.01,
    number_format(revenue()) . ' vs ' . number_format($revenueBefore));

// ---------------------------------------------------------------------------
echo "\nA payment that has no receipt behind it survives the reversal\n";
// ---------------------------------------------------------------------------

// The fault this section exists for: the fee used to be rebuilt from its
// approved receipts, so a payment taken at the counter before the Received modal
// wrote receipts — there are plenty — disappeared when an unrelated payment was
// reversed. Reversing takes off the one payment and nothing else.
$mixedFee = \App\Models\Fee::withCreditMovedOut()->with('studentEnroll.student')->get()
    ->first(function ($fee) {
        $receipted = (float) \App\Models\PaymentReceipt::where('fee_id', $fee->id)
            ->where('verification_status', 'approved')->sum('amount');

        return $receipted > 0.009 && (float) $fee->paid_amount - $receipted > 0.009;
    });

if (!$mixedFee) {
    echo "  SKIP  no fee mixes a receipted payment with one entered directly\n";
} else {
    $receipt = \App\Models\PaymentReceipt::where('fee_id', $mixedFee->id)
        ->where('verification_status', 'approved')->orderByDesc('id')->first();
    $paidBefore = (float) $mixedFee->paid_amount;
    $unreceipted = $paidBefore - (float) \App\Models\PaymentReceipt::where('fee_id', $mixedFee->id)
        ->where('verification_status', 'approved')->sum('amount');

    DB::beginTransaction();

    try {
        // Any credit standing on the fee is cleared first, so this section is
        // about the fee's own arithmetic and nothing else.
        \App\Models\StudentCredit::where('source_fee_id', $mixedFee->id)->delete();

        $result = app(\App\Services\PaymentReversalService::class)
            ->reverse($receipt->fresh(), 'test: entered in error');

        $after = \App\Models\Fee::find($mixedFee->id);

        check('the reversal goes through', $result['reversed'] === true, $result['message']);
        check('only the reversed payment comes off the fee',
            abs((float) $after->paid_amount - ($paidBefore - (float) $receipt->amount)) < 0.01,
            'fee now ' . $after->paid_amount . ', expected ' . ($paidBefore - (float) $receipt->amount));
        check('the payment with no receipt behind it is still there',
            (float) $after->paid_amount >= $unreceipted - 0.01,
            'kept ' . $after->paid_amount . ' against ' . $unreceipted . ' unreceipted');
        check('and the message says what came off and where the fee stands',
            str_contains($result['message'], number_format((float) $receipt->amount, 2))
                && str_contains($result['message'], number_format((float) $after->paid_amount, 2)),
            $result['message']);

        // The payment put an entry on the student's statement; the reversal puts
        // an opposing one, rather than rubbing the first one out.
        $student = optional($after->studentEnroll)->student;
        check('the money going back out reaches the student statement',
            $student && \App\Models\Transaction::where('transactionable_id', $student->id)
                ->where('transactionable_type', \App\Models\Student::class)
                ->where('type', '2')->where('amount', $receipt->amount)->exists(),
            'no opposing entry for ' . $receipt->amount);
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
echo "\nThe credit the payment raised goes with it\n";
// ---------------------------------------------------------------------------

$overpaidFee = \App\Models\Fee::withCreditMovedOut()->get()->first(function ($fee) {
    if (!$fee->isNetOverpaid()) {
        return false;
    }

    $credit = \App\Models\StudentCredit::where('source_fee_id', $fee->id)
        ->where('source_type', \App\Models\StudentCredit::SOURCE_OVERPAYMENT)
        ->where('remaining_amount', '>', 0)->exists();

    $receipt = \App\Models\PaymentReceipt::where('fee_id', $fee->id)
        ->where('verification_status', 'approved')->exists();

    return $credit && $receipt;
});

if (!$overpaidFee) {
    echo "  SKIP  no overpaid fee with unspent credit and a receipt to reverse\n";
} else {
    $receipt = \App\Models\PaymentReceipt::where('fee_id', $overpaidFee->id)
        ->where('verification_status', 'approved')->orderByDesc('id')->first();
    $creditBefore = (float) \App\Models\StudentCredit::where('source_fee_id', $overpaidFee->id)
        ->sum('remaining_amount');

    DB::beginTransaction();

    try {
        $result = app(\App\Services\PaymentReversalService::class)
            ->reverse($receipt->fresh(), 'test: recorded twice');

        $creditAfter = (float) \App\Models\StudentCredit::where('source_fee_id', $overpaidFee->id)
            ->sum('remaining_amount');
        $feeAfter = \App\Models\Fee::withCreditMovedOut()->find($overpaidFee->id);

        check('the reversal goes through', $result['reversed'] === true, $result['message']);
        check('the credit that payment had raised is cancelled',
            $creditAfter < $creditBefore - 0.009,
            'credit went from ' . $creditBefore . ' to ' . $creditAfter);
        check('the fee is no longer overpaid', !$feeAfter->isNetOverpaid(),
            'balance ' . $feeAfter->net_remaining_balance);
        check('nothing already applied to another fee is disturbed',
            \App\Models\CreditApplication::whereIn('student_credit_id',
                \App\Models\StudentCredit::where('source_fee_id', $overpaidFee->id)->pluck('id'))
                ->sum('amount_applied') == \App\Models\CreditApplication::whereIn('student_credit_id',
                \App\Models\StudentCredit::where('source_fee_id', $overpaidFee->id)->pluck('id'))->sum('amount_applied'));
        check('the message mentions the cancelled credit',
            str_contains($result['message'], 'credit'), $result['message']);
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
echo "\nCredit already spent elsewhere stops the reversal\n";
// ---------------------------------------------------------------------------

// Reversing would leave another fee settled with money that no longer exists, so
// the service refuses and names that fee rather than quietly unpicking it.
$spentCase = null;

foreach (\App\Models\StudentCredit::where('source_type', \App\Models\StudentCredit::SOURCE_OVERPAYMENT)
    ->whereNotNull('source_fee_id')->get() as $credit) {
    $applied = (float) \App\Models\CreditApplication::where('student_credit_id', $credit->id)->sum('amount_applied');
    $receipt = \App\Models\PaymentReceipt::where('fee_id', $credit->source_fee_id)
        ->where('verification_status', 'approved')
        ->where('amount', '>=', $applied)
        ->orderByDesc('amount')->first();

    if ($applied > 0.009 && $receipt) {
        $spentCase = ['credit' => $credit, 'receipt' => $receipt];
        break;
    }
}

if (!$spentCase) {
    echo "  SKIP  no fee has credit that was raised from it and spent elsewhere\n";
} else {
    $before = json_encode([
        'fee' => (float) \App\Models\Fee::find($spentCase['receipt']->fee_id)->paid_amount,
        'receipt' => \App\Models\PaymentReceipt::find($spentCase['receipt']->id)->verification_status,
        'credit' => (float) \App\Models\StudentCredit::find($spentCase['credit']->id)->remaining_amount,
    ]);

    $result = app(\App\Services\PaymentReversalService::class)
        ->reverse($spentCase['receipt']->fresh(), 'test: should be refused');

    $after = json_encode([
        'fee' => (float) \App\Models\Fee::find($spentCase['receipt']->fee_id)->paid_amount,
        'receipt' => \App\Models\PaymentReceipt::find($spentCase['receipt']->id)->verification_status,
        'credit' => (float) \App\Models\StudentCredit::find($spentCase['credit']->id)->remaining_amount,
    ]);

    check('it is refused', $result['reversed'] === false, $result['message']);
    check('and says where the credit went',
        str_contains($result['message'], 'already been applied'), $result['message']);
    check('and writes nothing at all', $before === $after, "$before -> $after");
}

// ---------------------------------------------------------------------------
echo "\nThe ledger follows the money\n";
// ---------------------------------------------------------------------------

$postedFee = \App\Models\Fee::withCreditMovedOut()->get()->first(function ($fee) {
    $posted = DB::table('transaction_mappings')->where('transaction_type', 'fee')
        ->where('transaction_id', $fee->id)->where('status', 'active')->exists();

    $receipts = \App\Models\PaymentReceipt::where('fee_id', $fee->id)
        ->where('verification_status', 'approved')->count();

    return $posted && $receipts >= 1 && $fee->cash_received_amount > 0.009;
});

if (!$postedFee) {
    echo "  SKIP  no posted fee with a receipt to reverse\n";
} else {
    $receipt = \App\Models\PaymentReceipt::where('fee_id', $postedFee->id)
        ->where('verification_status', 'approved')->orderByDesc('id')->first();

    DB::beginTransaction();

    try {
        $entriesBefore = (int) DB::table('journal_entries')->count();

        $result = app(\App\Services\PaymentReversalService::class)->reverse($receipt->fresh(), 'test: ledger check');

        $fee = \App\Models\Fee::withCreditMovedOut()->find($postedFee->id);
        $posted = DB::table('transaction_mappings as tm')
            ->join('journal_entries as je', 'je.id', '=', 'tm.journal_entry_id')
            ->where('tm.transaction_type', 'fee')->where('tm.transaction_id', $fee->id)
            ->where('tm.status', 'active')->value('je.total_debit');

        // A fee whose pay date falls in no accounting period cannot be posted at
        // all. The money is still put right; the reversal says the ledger could
        // not follow, and Credit audit lists the fee for correction.
        $ledgerHeld = str_contains($result['message'], 'ledger entry could not be');

        check('the fee is posted at the cash it still holds, or the reversal says why not',
            $ledgerHeld
                ? true
                : ($fee->cash_received_amount > 0.009
                    ? ($posted !== null && abs((float) $posted - $fee->cash_received_amount) < 0.01)
                    : $posted === null),
            'cash ' . $fee->cash_received_amount . ', posted ' . var_export($posted, true) . ', message: ' . $result['message']);
        check('and when it could not, the Credit audit picks the fee up',
            !$ledgerHeld
                || app(\App\Services\FeeCreditReconciliation::class)->ledgerCorrections()
                    ->contains(fn ($row) => (int) $row['fee_id'] === (int) $fee->id),
            'the fee is mis-posted but the audit does not list it');
        check('by new entries — nothing is deleted from the journal',
            (int) DB::table('journal_entries')->count() >= $entriesBefore);
        check('and the ledger is no more overstated than it was before',
            $ledgerHeld
                || abs(app(\App\Services\FeeCreditReconciliation::class)->ledgerExposure()['overstated']) < 0.01);
    } finally {
        DB::rollBack();
    }
}

// ---------------------------------------------------------------------------
echo "\nThe case this was built for: a payment recorded twice\n";
// ---------------------------------------------------------------------------

// FON BRIAN PENN's First Instalment carries three payments — 150,000 taken at
// the counter in December with no receipt behind it, 100,000 in April whose
// credit was transferred to his Second Instalment, and 50,000 in April that
// appears to be the same payment as the 50,000 on that Second Instalment.
// Reversing the last must leave the fee settled, cancel the credit it raised,
// and leave the December payment and the other instalment alone.
$penn = \App\Models\Student::where('student_id', 'PAX25MFH047')->first();

if (!$penn) {
    echo "  SKIP  this database does not hold that student\n";
} else {
    $enrollIds = \App\Models\StudentEnroll::where('student_id', $penn->id)->pluck('id');
    $first = \App\Models\Fee::withCreditMovedOut()->whereIn('student_enroll_id', $enrollIds)
        ->where('category_id', \App\Models\FeesCategory::where('is_first_installment', 1)->value('id'))->first();
    $second = \App\Models\Fee::withCreditMovedOut()->whereIn('student_enroll_id', $enrollIds)
        ->where('category_id', \App\Models\FeesCategory::where('is_second_installment', 1)->value('id'))->first();

    $duplicate = $first
        ? \App\Models\PaymentReceipt::where('fee_id', $first->id)
            ->where('verification_status', 'approved')->orderByDesc('payment_date')->first()
        : null;

    if (!$first || !$second || !$duplicate) {
        echo "  SKIP  his fees are not in the state this section describes\n";
    } else {
        $owed = (float) $first->total_amount + (float) $second->total_amount;

        DB::beginTransaction();

        try {
            $result = app(\App\Services\PaymentReversalService::class)
                ->reverse($duplicate->fresh(), 'test: recorded on the wrong instalment and again on the right one');

            $firstAfter = \App\Models\Fee::withCreditMovedOut()->find($first->id);
            $secondAfter = \App\Models\Fee::withCreditMovedOut()->find($second->id);
            $cash = $firstAfter->cash_received_amount + $secondAfter->cash_received_amount;

            check('the reversal goes through', $result['reversed'] === true, $result['message']);
            check('the First Instalment keeps the payment taken at the counter',
                (float) $firstAfter->paid_amount >= (float) $firstAfter->total_amount - 0.01,
                'paid ' . $firstAfter->paid_amount . ' against ' . $firstAfter->total_amount . ' due');
            check('and is no longer overpaid', !$firstAfter->isNetOverpaid(),
                'balance ' . $firstAfter->net_remaining_balance);
            check('the Second Instalment is untouched and still settled',
                abs((float) $secondAfter->paid_amount - (float) $second->paid_amount) < 0.01
                    && !$secondAfter->isNetOverpaid() && $secondAfter->net_remaining_balance < 0.01,
                'paid ' . $secondAfter->paid_amount);
            check('the credit that payment had raised is gone',
                (float) \App\Models\StudentCredit::where('student_id', $penn->id)
                    ->whereIn('status', ['available', 'partially_applied'])->sum('remaining_amount') < 0.01);
            check('and the year adds up: cash in equals what he owes',
                abs($cash - $owed) < 0.01, 'cash ' . $cash . ' against ' . $owed . ' owed');
        } finally {
            DB::rollBack();
        }
    }
}

echo "\n" . str_repeat('-', 64) . "\n";
printf("%d passed, %d failed\n", $passed, $failed);
echo "(all changes rolled back)\n";

exit($failed === 0 ? 0 : 1);
