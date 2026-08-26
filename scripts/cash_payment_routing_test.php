<?php
/**
 * Cash is paid at the Finance Office, never through the applicant portal.
 *
 * The payment step uploads PROOF of a payment already made somewhere else. A
 * cash payment produces no such proof: the money is handed over at the counter
 * and the finance officer records it against the fee from Admission Fees Report
 * > Record payment, which creates an already-approved receipt and settles the
 * fee. Offering "Cash" on the applicant's form invited them to claim a payment
 * nobody had received, and left them waiting for a verification that could
 * never come.
 *
 * What has to hold:
 *   - the applicant is not offered cash, in the form or at the endpoint;
 *   - every method they ARE offered is actually accepted by the server;
 *   - they are told plainly where to go to pay cash;
 *   - that notice is visible whichever payment tab is open;
 *   - the counter route still exists and still settles the fee.
 *
 * Usage: php scripts/cash_payment_routing_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

const CASH = '2';

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

echo "\n== The applicant's form ==\n";

$application = Application::whereNotNull('applicant_id')->whereNotNull('degree_type_id')->orderByDesc('id')->first();
if (!$application) {
    fwrite(STDERR, "no application to render\n");
    exit(2);
}

Auth::guard('applicant')->loginUsingId($application->applicant_id);

$kernel  = app(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/application/' . $application->id . '/edit', 'GET');
$request->setLaravelSession(app('session.store'));
$response = $kernel->handle($request);
check('the wizard renders', $response->getStatusCode() === 200, 'status ' . $response->getStatusCode());
$html = $response->getContent();

// Isolate the payment-method dropdown rather than searching the whole page,
// so an unrelated mention of the word "Cash" cannot mask a real regression.
$open  = strpos($html, 'id="pay_payment_method"');
$close = $open === false ? false : strpos($html, '</select>', $open);
$select = ($open !== false && $close !== false) ? substr($html, $open, $close - $open) : '';

check('the payment method dropdown is present', $select !== '');
check(
    'cash is not offered to the applicant',
    strpos($select, 'value="' . CASH . '"') === false,
    $select
);
check(
    'the other methods are still offered',
    substr_count($select, '<option') >= 6,
    substr_count($select, '<option') . ' options'
);

echo "\n== The endpoint agrees with the form ==\n";

$rules = null;
$ref = new ReflectionMethod(App\Http\Controllers\Web\ApplicationController::class, 'uploadAdmissionFeeReceipt');
$src = implode('', array_slice(
    file($ref->getFileName()),
    $ref->getStartLine() - 1,
    $ref->getEndLine() - $ref->getStartLine() + 1
));
if (preg_match("/'payment_method'\s*=>\s*'required\|in:([0-9,]+)'/", $src, $m)) {
    $rules = explode(',', $m[1]);
}

check('the endpoint constrains payment_method', is_array($rules), 'rule not found');

if (is_array($rules)) {
    check(
        'the endpoint rejects cash',
        !in_array(CASH, $rules, true),
        'accepts: ' . implode(',', $rules)
    );

    // A method offered but not accepted fails with no explanation the applicant
    // can act on, so the two lists must agree exactly.
    preg_match_all('/value="(\d+)"/', $select, $om);
    $offered   = $om[1];
    $unusable  = array_values(array_diff($offered, $rules));
    check(
        'every method offered is accepted by the server',
        $unusable === [],
        'offered but rejected: ' . implode(',', $unusable)
    );
}

echo "\n== The applicant is told where to pay cash ==\n";

check('a cash notice is shown', strpos($html, 'cash-notice') !== false);
check('it names the Finance Office', strpos($html, 'Finance Office') !== false);
check('it says not to use this page', stripos($html, 'Do not use this page') !== false);
check(
    'it says no upload is needed afterwards',
    stripos($html, 'do not need to upload anything here') !== false
);

// Placed outside the tab panes so it shows whichever payment tab is open.
// Match the markup, not the stylesheet rule of the same name, which appears
// far earlier in the document and would make this pass for the wrong reason.
$noticePos = strpos($html, 'class="cash-notice mt-4"');
$tabsEnd   = strpos($html, 'id="upload-receipt-btn"');
check(
    'the notice sits outside the payment tabs',
    $noticePos !== false && $tabsEnd !== false && $noticePos > $tabsEnd,
    "notice at $noticePos, last tab control at $tabsEnd"
);

echo "\n== The counter route still works ==\n";

$walkIn = Route::getRoutes()->getByName('admin.admission-fees-report.record-payment')
    ?: collect(Route::getRoutes())->first(fn ($r) => str_contains((string) $r->getActionName(), 'recordWalkIn'));

check('a walk-in payment route exists', $walkIn !== null);

$adminRef = new ReflectionMethod(App\Http\Controllers\Admin\AdmissionFeesReportController::class, 'recordWalkIn');
$adminSrc = implode('', array_slice(
    file($adminRef->getFileName()),
    $adminRef->getStartLine() - 1,
    $adminRef->getEndLine() - $adminRef->getStartLine() + 1
));

check(
    'the finance officer can still record cash',
    (bool) preg_match("/'payment_method'\s*=>\s*'required\|in:([0-9,]*2[0-9,]*)'/", $adminSrc)
);
check(
    'a counter payment is approved on the spot',
    strpos($adminSrc, "verification_status = 'approved'") !== false
);
check(
    'it settles the fee, which is what triggers auto-submission',
    strpos($adminSrc, '$fee->status') !== false && strpos($adminSrc, '$fee->save()') !== false
);

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
