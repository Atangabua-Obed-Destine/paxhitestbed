<?php
/**
 * Paying for an application: reaching it, and what may be changed on the way.
 *
 * Three faults this covers, all reported from real use:
 *
 *   1. The payment step decided what was outstanding when the page was rendered.
 *      The wizard saves over AJAX and never reloads, so that list went stale and
 *      kept naming details the applicant had just supplied — only a manual
 *      refresh let them pay.
 *   2. The amount was an editable field, so an applicant could declare their own
 *      figure against a receipt.
 *   3. The dashboard opened a payment box of its own, letting someone pay
 *      without going through the form — and approval submits the application
 *      outright, so whatever was unfinished would be submitted unfinished.
 *
 * The wizard is rendered once only: compiled Blade declares a global field()
 * helper, so a second render in the same process is a fatal redeclaration.
 *
 * Usage: php scripts/application_payment_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Application;
use App\Services\ApplicationCompleteness;
use App\Services\ApplicationSubmissionService;
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

$service = app(ApplicationSubmissionService::class);

// An unpaid draft, so the payment step renders its gate rather than "settled".
$application = Application::where('stage', 'draft')->orderByDesc('id')->get()
    ->first(fn ($a) => !$service->admissionFeeIsSettled($a));

if (!$application) {
    fwrite(STDERR, "no unpaid draft to test with\n");
    exit(2);
}

Auth::guard('applicant')->loginUsingId($application->applicant_id);

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$session = app('session.store');

function call(string $uri, string $method = 'GET', array $params = [], array $files = [])
{
    global $kernel, $session;
    if ($method !== 'GET') {
        $params['_token'] = $session->token();
    }
    $request = Illuminate\Http\Request::create($uri, $method, $params, [], $files);
    $request->setLaravelSession($session);

    return $kernel->handle($request);
}

echo "\n" . str_repeat('=', 64) . "\n";
echo "Application payment — reaching it, and what it will accept\n";
echo str_repeat('=', 64) . "\n";
printf("unpaid draft #%d\n", $application->id);

// Everything runs inside a transaction: opening the wizard bills the applicant
// via ensureAdmissionFee(), and the tests below complete an application.
DB::beginTransaction();

$dashboard = call('/application/dashboard')->getContent();

// ---------------------------------------------------------------------------
echo "\nThe dashboard sends the applicant to the form\n";
// ---------------------------------------------------------------------------

check('the payment modal is gone', strpos($dashboard, 'momo-fee-modal') === false);
check('nothing auto-opens a fee modal', strpos($dashboard, 'openModalFromHash') === false);
check(
    'the button links to the application form',
    (bool) preg_match('~href="[^"]*application/\d+/edit"~', $dashboard)
);
check('and it reads as continuing, not paying separately', strpos($dashboard, 'Continue and pay') !== false);

// The amount field lived in the modal that has just been removed.
check('the dashboard offers no editable amount', strpos($dashboard, 'name="amount"') === false);

// ---------------------------------------------------------------------------
echo "\nThe payment step can change its mind without a reload\n";
// ---------------------------------------------------------------------------

$wizard = call('/application/' . $application->id . '/edit')->getContent();

check('both states are rendered, not one chosen at render time',
    strpos($wizard, 'id="payment-blocked"') !== false && strpos($wizard, 'id="payment-controls"') !== false);
check('the outstanding list can be rebuilt', strpos($wizard, 'id="payment-blockers-list"') !== false);
check('the step re-asks the server', strpos($wizard, 'function refreshPaymentReadiness') !== false);
check('it re-asks on arriving at the step', strpos($wizard, "stepId === 'step-8'") !== false);
check('and again after every save', strpos($wizard, "application:draft-saved") !== false);

// ---------------------------------------------------------------------------
echo "\nReadiness reports the truth as it stands now\n";
// ---------------------------------------------------------------------------

$readiness = fn () => json_decode(call('/application/' . $application->id . '/readiness')->getContent(), true);

$before = $readiness();
check('the endpoint answers', is_array($before) && array_key_exists('complete', $before));
check(
    'it lists what is outstanding',
    $before['complete'] === false ? !empty($before['missing']) : true,
    json_encode($before['missing'] ?? [])
);
check(
    'each item names the step that fixes it',
    $before['complete'] === true || (isset($before['missing'][0]['step']) && isset($before['missing'][0]['label']))
);

// Now finish the application and ask again — without re-rendering anything.
// This is the exact sequence that used to require a manual refresh.
$outstanding = ApplicationCompleteness::missing($application);
$meta = is_array($application->portal_meta) ? $application->portal_meta : [];
$application->portal_meta = array_merge($meta, ['agreed_to_terms' => true]);
$application->save();

$after = $readiness();
check(
    'completing a field is reflected immediately',
    count($after['missing'] ?? []) < count($before['missing'] ?? []) || $after['complete'] === true,
    'before ' . count($before['missing'] ?? []) . ', after ' . count($after['missing'] ?? [])
);

if ($after['complete'] === true) {
    check('a finished application reports complete', true);
} else {
    check(
        'a finished application reports complete',
        false,
        'still outstanding: ' . implode(', ', array_column($after['missing'], 'label'))
    );
}

// ---------------------------------------------------------------------------
echo "\nThe fee is the institution's figure, not the applicant's\n";
// ---------------------------------------------------------------------------

check(
    'the amount field is not editable on screen',
    (bool) preg_match('~id="pay_amount"[^>]*readonly~', $wizard)
);

$fee = $application->admissionFee;
$balance = (float) $fee->fee_amount + (float) $fee->fine_amount - (float) $fee->discount_amount - (float) $fee->paid_amount;

$tmp = tempnam(sys_get_temp_dir(), 'rc') . '.png';
copy(public_path('dashboard/images/user.jpg'), $tmp);
$upload = new Illuminate\Http\UploadedFile($tmp, 'receipt.png', 'image/png', null, true);

call('/application/' . $application->id . '/admission-fee/payment/upload', 'POST', [
    'payment_date' => now()->toDateString(),
    'amount' => '1',                       // an applicant declaring their own figure
    'payment_reference' => 'PAYMENT-TEST',
    'payment_method' => '4',
], ['receipt_file' => $upload]);

$receipt = DB::table('payment_receipts')->where('payment_reference', 'PAYMENT-TEST')->first();
check('the receipt was accepted', $receipt !== null);
check(
    'a posted amount is ignored in favour of the balance',
    $receipt && abs((float) $receipt->amount - $balance) < 0.01,
    'posted 1, stored ' . ($receipt ? number_format($receipt->amount) : 'nothing') . ', balance ' . number_format($balance)
);

@unlink($tmp);

// ---------------------------------------------------------------------------
echo "\nAn unfinished application still cannot pay\n";
// ---------------------------------------------------------------------------

// Undo the declaration to make it incomplete again.
$application->portal_meta = array_merge($meta, ['agreed_to_terms' => false]);
$application->save();

$tmp2 = tempnam(sys_get_temp_dir(), 'rc') . '.png';
copy(public_path('dashboard/images/user.jpg'), $tmp2);
$upload2 = new Illuminate\Http\UploadedFile($tmp2, 'receipt2.png', 'image/png', null, true);

call('/application/' . $application->id . '/admission-fee/payment/upload', 'POST', [
    'payment_date' => now()->toDateString(),
    'amount' => (string) $balance,
    'payment_reference' => 'BLOCKED-TEST',
    'payment_method' => '4',
], ['receipt_file' => $upload2]);

check(
    'an incomplete application cannot upload a receipt',
    DB::table('payment_receipts')->where('payment_reference', 'BLOCKED-TEST')->doesntExist()
);

@unlink($tmp2);

DB::rollBack();

// ---------------------------------------------------------------------------
echo "\nNothing was left behind\n";
// ---------------------------------------------------------------------------

check('no test receipt survived',
    DB::table('payment_receipts')->whereIn('payment_reference', ['PAYMENT-TEST', 'BLOCKED-TEST'])->doesntExist());
check('the application is still a draft', Application::find($application->id)->stage === 'draft');

echo "\n" . str_repeat('-', 64) . "\n";
printf("%d passed, %d failed\n", $passed, $failed);
echo "(all changes rolled back)\n";

exit($failed === 0 ? 0 : 1);
