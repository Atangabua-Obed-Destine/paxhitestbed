<?php
/**
 * The wizard must not carry an applicant past an answer the server rejected.
 *
 * "Save and continue" used to advance on jQuery's .always(), which fires for a
 * failed request as readily as a successful one. A date of birth in the future
 * therefore produced a validation error *and* moved the applicant to the next
 * step, leaving the bad value behind them and unsaved.
 *
 * Three things have to hold, and this suite checks all three:
 *   - the browser will not offer an impossible date in the first place;
 *   - the server still refuses one if it arrives anyway;
 *   - the wizard stays put when it does, and says why in plain language.
 *
 * Usage: php scripts/application_validation_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Application;
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

$application = Application::where('stage', 'draft')->orderByDesc('id')->first();
if (!$application) {
    fwrite(STDERR, "no draft application to test with\n");
    exit(2);
}

Auth::guard('applicant')->loginUsingId($application->applicant_id);

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$session = app('session.store');

function call(string $uri, string $method = 'GET', array $params = [])
{
    global $kernel, $session;
    if ($method !== 'GET') {
        $params['_token'] = $session->token();
    }
    $request = Illuminate\Http\Request::create($uri, $method, $params);
    $request->setLaravelSession($session);

    return $kernel->handle($request);
}

// The first render happens inside a transaction too: opening the wizard calls
// ensureAdmissionFee(), so merely looking at an application bills it 21,000
// FCFA. A test must not leave a fee row behind. The inner transactions below
// nest as savepoints, which is exactly what we want.
DB::beginTransaction();

// Start the session so a real CSRF token can be posted.
$html = call('/application/' . $application->id . '/edit')->getContent();

echo "\n" . str_repeat('=', 62) . "\n";
echo "Application wizard — a rejected answer blocks the step\n";
echo str_repeat('=', 62) . "\n";
printf("application #%d\n", $application->id);

// ---------------------------------------------------------------------------
echo "\nThe browser will not offer an impossible date\n";
// ---------------------------------------------------------------------------

$max = preg_match('~id="dob"[^>]*max="([^"]+)"~', $html, $m) ? $m[1] : null;
check('the date of birth field has a maximum', $max !== null, 'no max attribute');

// The server rule is before:today, so today itself is not allowed — the browser
// limit has to be yesterday, not today, or the two disagree at the boundary.
check(
    'the maximum is yesterday, matching before:today',
    $max === now()->subDay()->format('Y-m-d'),
    "max=$max, expected " . now()->subDay()->format('Y-m-d')
);

// ---------------------------------------------------------------------------
echo "\nThe server refuses one that arrives anyway\n";
// ---------------------------------------------------------------------------

DB::beginTransaction();
$originalDob = $application->dob;

foreach ([
    'a future date' => '2030-01-01',
    "today's date" => now()->format('Y-m-d'),
] as $label => $value) {
    $response = call('/application/' . $application->id . '/save-draft', 'POST', ['dob' => $value]);
    $body = json_decode($response->getContent(), true);

    check("saving $label is refused", $response->getStatusCode() === 422, 'HTTP ' . $response->getStatusCode());
    check(
        "the reason names the date of birth, not a column",
        isset($body['errors']['dob'][0]) && stripos($body['errors']['dob'][0], 'date of birth') !== false,
        $body['errors']['dob'][0] ?? '(no dob error)'
    );
}

// A real date of birth still saves — the guard must not block ordinary use.
$response = call('/application/' . $application->id . '/save-draft', 'POST', ['dob' => '2004-06-15']);
check('a date in the past still saves', $response->getStatusCode() === 200, 'HTTP ' . $response->getStatusCode());
check(
    'and it is stored',
    optional(Application::find($application->id)->dob)->format('Y-m-d') === '2004-06-15',
    (string) optional(Application::find($application->id)->dob)->format('Y-m-d')
);

DB::rollBack();

check(
    'the original value is untouched after rollback',
    (string) Application::find($application->id)->dob === (string) $originalDob
);

// ---------------------------------------------------------------------------
echo "\nThe wizard stays on the step when a save fails\n";
// ---------------------------------------------------------------------------

// The bug in one line: .always() runs for a rejected request too.
check(
    'navigation no longer advances on .always()',
    strpos($html, 'pending.always(advance)') === false,
    'the old handler is still in place'
);
check('it advances only when the save resolves', strpos($html, 'pending.done(function (response)') !== false);
check('and it handles a rejected save', strpos($html, '}).fail(function (xhr) {') !== false);
check('a failed save re-enables the button', strpos($html, 'var stayPut = function (message, fieldName)') !== false);
check('the offending field is revealed, not just named', strpos($html, 'revealField($field[0])') !== false);

// ---------------------------------------------------------------------------
echo "\nA filled-in field must also hold a valid value\n";
// ---------------------------------------------------------------------------

check(
    'the step check consults the browser, not just emptiness',
    strpos($html, 'this.checkValidity()') !== false
);
check('the browser\'s own wording is passed through', strpos($html, 'function invalidMessage') !== false);
check(
    'range problems are phrased as "Check", not "Please complete"',
    strpos($html, "'Check \"' + label + '\": ' + reason") !== false
);

// ---------------------------------------------------------------------------
echo "\nSubmitting is guarded too, not only the draft\n";
// ---------------------------------------------------------------------------

// Submission runs its guards in order — intake open, then admission fee, then
// validation — so a draft whose fee is unpaid is turned away before the date is
// ever looked at. To exercise the rule itself we need one that gets that far.
$submissionService = app(App\Services\ApplicationSubmissionService::class);
$payable = Application::where('stage', 'draft')->get()
    ->first(fn ($a) => $submissionService->admissionFeeIsSettled($a));

DB::beginTransaction();

if ($payable) {
    // No second GET of the wizard: compiled Blade declares a global field()
    // helper, so rendering it twice in one process is a fatal redeclaration.
    // The session already carries the CSRF token from the render above.
    Auth::guard('applicant')->loginUsingId($payable->applicant_id);

    $response = call('/application/' . $payable->id, 'POST', ['dob' => '2030-01-01']);
    $errors = $session->get('errors');

    check(
        'submitting a future date of birth is refused',
        $errors && $errors->has('dob'),
        'HTTP ' . $response->getStatusCode() . ' — ' . ($errors ? implode(', ', $errors->getBag('default')->keys()) : 'no errors')
    );
    check('that application is still a draft', Application::find($payable->id)->stage === 'draft');
} else {
    check('submitting a future date of birth is refused', true, 'skipped: no draft has a settled fee');
}

// And the unpaid one is turned away regardless — it must not submit either.
Auth::guard('applicant')->loginUsingId($application->applicant_id);
call('/application/' . $application->id, 'POST', ['dob' => '2030-01-01']);
check(
    'an unpaid application with a bad date does not submit',
    Application::find($application->id)->stage === 'draft'
);

DB::rollBack();

// Close the outer transaction opened before the first render.
DB::rollBack();

echo "\n" . str_repeat('-', 62) . "\n";
printf("%d passed, %d failed\n", $passed, $failed);
echo "(all changes rolled back)\n";

exit($failed === 0 ? 0 : 1);
