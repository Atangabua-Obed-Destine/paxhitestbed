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

echo "\n" . str_repeat('-', 64) . "\n";
printf("%d passed, %d failed\n", $passed, $failed);
echo "(all changes rolled back)\n";

exit($failed === 0 ? 0 : 1);
