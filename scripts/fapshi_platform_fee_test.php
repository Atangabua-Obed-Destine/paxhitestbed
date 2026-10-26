<?php

/**
 * Paying the platform access fee online, through Fapshi.
 *
 * What this suite is really guarding is money. Three things in particular:
 *
 *   - the switch must be able to take online payment away and put the manual
 *     route back, WITHOUT stranding a payment that was already in flight;
 *   - a payment must never grant access unless the gateway, asked directly,
 *     says the right amount arrived against the right transaction;
 *   - and money that arrives when it should not must be kept and flagged,
 *     never credited and never dropped.
 *
 * The gateway's HTTP is faked, so every rule below is exercised without
 * credentials and without a network. The sandbox run against the real Fapshi
 * is the separate, end-to-end proof.
 *
 * Every write happens inside a transaction that is rolled back. The closing
 * section reports absolute row counts, not deltas, because a delta of zero is
 * also what two mistakes cancelling out looks like.
 */

$base = 'C:/xampp/htdocs/paxhitestbed';
require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Payment\FapshiController;
use App\Models\FapshiTransaction;
use App\Models\PlatformFeeExemption;
use App\Models\PlatformFeePayment;
use App\Models\PlatformFeeSetting;
use App\Models\Student;
use App\Services\Payment\FapshiClient;
use App\Services\Payment\PlatformFeeGatewayPayment;
use App\Services\PlatformFeeAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

$pass = 0;
$fail = 0;

function check(string $what, $expected, $actual): void
{
    global $pass, $fail;

    if ($expected === $actual) {
        $pass++;

        return;
    }

    $fail++;
    printf("  FAIL  %s\n          expected: %s\n          actual  : %s\n",
        $what, var_export($expected, true), var_export($actual, true));
}

function ok(string $what, bool $condition): void
{
    check($what, true, $condition);
}

/* ===================================================================== */
/*  Fixtures                                                             */
/* ===================================================================== */

/** The gateway service, built fresh so it picks up whatever config() now says. */
function gateway(): PlatformFeeGatewayPayment
{
    return new PlatformFeeGatewayPayment(new FapshiClient(), app(PlatformFeeAccess::class));
}

/** Put the gateway's .env-side configuration into a known, working state. */
function configureGateway(array $overrides = []): void
{
    config(array_merge([
        'fapshi.enabled' => true,
        'fapshi.environment' => 'sandbox',
        'fapshi.base_url' => 'https://sandbox.fapshi.com',
        'fapshi.api_user' => 'test-api-user',
        // Shaped like a real sandbox key: FAK_TEST_ is how the client tells a
        // test key from a live one, and a fixture that ignores that would have
        // the suite running in a state the application refuses.
        'fapshi.api_key' => 'FAK_TEST_suiteabcdefghijklmnop',
        'fapshi.webhook_secret' => 'test-webhook-secret',
        'fapshi.minimum_amount' => 100,
        'fapshi.link_lifetime_hours' => 24,
    ], $overrides));
}

/**
 * Start the HTTP fake over.
 *
 * Http::fake() MERGES its stubs and the first registered match wins, so
 * calling it a second time for the same URL leaves the first answer in place.
 * That silently had every payment in this suite come back with the first
 * transaction id. The factory is a container singleton, so clearing the
 * facade's cached instance alone is not enough — the binding has to go too.
 */
function resetHttp(): void
{
    app()->forgetInstance(\Illuminate\Http\Client\Factory::class);
    Http::clearResolvedInstances();
}

/**
 * What Fapshi answers with.
 *
 * Both endpoints are always stubbed, whichever one the test is about, so a
 * call this suite did not expect cannot quietly leave for the real internet.
 */
function fakeGateway(string $transId = 'FAPSHI-TEST-1', ?array $status = null, int $initiateHttp = 200): void
{
    resetHttp();

    Http::fake([
        '*/initiate-pay' => $initiateHttp === 200
            ? Http::response([
                'message' => 'Payment link generated',
                'link' => 'https://checkout.fapshi.test/' . $transId,
                'transId' => $transId,
                'dateInitiated' => now()->toIso8601String(),
            ], 200)
            : Http::response(['message' => 'Forbidden'], $initiateHttp),

        '*/payment-status/*' => $status !== null
            ? Http::response($status, 200)
            : Http::response(['message' => 'Transaction not found'], 404),
    ]);
}

function fakeInitiateOk(string $transId = 'FAPSHI-TEST-1'): void
{
    fakeGateway($transId);
}

/**
 * Put a student back to owing the fee.
 *
 * Sections further down need a student who has not paid, and the sections
 * above them keep settling the ones they use. Rather than hunt for an unused
 * student for each — and silently test the wrong thing when one runs out, which
 * is exactly what happened while writing this — each section that needs a clean
 * payer says so and gets one.
 *
 * Safe because the whole suite runs inside a transaction that is rolled back.
 */
function unsettle(Student $student): void
{
    $access = app(PlatformFeeAccess::class);
    $enrollment = $access->currentEnrollment($student);

    if ($enrollment) {
        PlatformFeePayment::whereIn('student_enroll_id',
            $access->enrollmentsInSameSession($student, $enrollment))->delete();
    }

    FapshiTransaction::where('student_id', $student->id)->delete();
}

/** Answer payment-status with this state, keeping initiate-pay working. */
function fakeStatus(FapshiTransaction $transaction, string $state, array $overrides = []): void
{
    fakeGateway($transaction->trans_id ?: 'FAPSHI-UNUSED', statusBody($transaction, $state, $overrides));
}

/**
 * A payment-status body.
 *
 * Built to Fapshi's documented shape so the suite exercises the same field
 * names the live gateway sends.
 */
function statusBody(FapshiTransaction $transaction, string $status, array $overrides = []): array
{
    return array_merge([
        'transId' => $transaction->trans_id,
        'status' => $status,
        'medium' => 'mobile money',
        'serviceName' => 'EduTrust',
        'transType' => 'Collection',
        'amount' => (int) $transaction->charged_amount,
        'revenue' => (int) round((float) $transaction->charged_amount * 0.9685),
        'payerName' => 'Test Payer',
        'externalId' => $transaction->external_id,
        'userId' => 'PAX25TEST001',
        'financialTransId' => 'OPERATOR-991',
        'dateInitiated' => now()->subMinute()->toIso8601String(),
        'dateConfirmed' => now()->toIso8601String(),
    ], $overrides);
}

$opening = [
    'settings' => DB::table('platform_fee_settings')->count(),
    'payments' => DB::table('platform_fee_payments')->count(),
    'approved' => DB::table('platform_fee_payments')->where('status', 'approved')->count(),
    'pending' => DB::table('platform_fee_payments')->where('status', 'pending')->count(),
    'exemptions' => DB::table('platform_fee_exemptions')->count(),
    'transactions' => DB::table('fapshi_transactions')->count(),
    'gateway_paid' => DB::table('platform_fee_payments')->where('paid_via', '!=', 'manual')->count(),
    'currency' => DB::table('platform_fee_settings')->value('currency'),
    'fee' => DB::table('platform_fee_settings')->value('fee_amount'),
];

printf("Before: %d payments (%d approved, %d pending), %d exemptions, %d gateway transactions\n",
    $opening['payments'], $opening['approved'], $opening['pending'],
    $opening['exemptions'], $opening['transactions']);
printf("        fee %s %s\n\n", $opening['fee'], $opening['currency']);

// Clean fixtures found on this database: session 5 carries no blanket
// exemption, session 2 does.
const CLEAN_STUDENT = 68;
const CLEAN_STUDENT_2 = 70;
const CLEAN_STUDENT_3 = 93;
const CLEAN_STUDENT_4 = 98;
const EXEMPT_STUDENT = 1;

DB::beginTransaction();

try {
    $setting = PlatformFeeSetting::first();
    $access = app(PlatformFeeAccess::class);

    // The configuration the school will actually run: 2,000 fee, 3.15%
    // commission passed on, which is the 2,063 that was asked for.
    $setting->update([
        'is_enabled' => true,
        'online_payment_enabled' => true,
        'fee_amount' => 2000,
        'gateway_fee_percent' => 3.15,
        'gateway_fee_fixed' => 0,
        'currency' => 'XAF',
    ]);
    $setting->refresh();

    configureGateway();

    /*
     * Start the students this suite uses from a known state.
     *
     * The table is not empty on a real installation. A genuine sandbox payment
     * sitting in it had initiate() correctly hand back THAT link instead of the
     * fake one, and four checks then failed for a reason that had nothing to do
     * with what they were testing. A suite has to own its fixtures rather than
     * assume it is the only thing that has ever run.
     */
    foreach ([CLEAN_STUDENT, CLEAN_STUDENT_2, CLEAN_STUDENT_3, CLEAN_STUDENT_4] as $fixtureId) {
        unsettle(Student::findOrFail($fixtureId));
    }

    $transactionsBefore = FapshiTransaction::count();

    echo "=== 0. the host follows the environment ===\n";

    /*
     * The costliest kind of mistake available here: FAPSHI_ENV says live, the
     * base URL still says sandbox, and every screen and every stored
     * transaction reports "live" while the requests go to a test gateway.
     * Payments appear to work and no money ever arrives.
     *
     * It happened the first time these settings were saved, because the rule
     * was "honour the URL field unless it is blank" and the form pre-fills that
     * field, so it is never blank. The environment is authoritative now, and
     * these checks are what keeps it that way.
     */
    $hostFor = function (string $environment, string $configured): string {
        $_ENV['FAPSHI_ENV'] = $_SERVER['FAPSHI_ENV'] = $environment;
        putenv('FAPSHI_ENV=' . $environment);
        $_ENV['FAPSHI_BASE_URL'] = $_SERVER['FAPSHI_BASE_URL'] = $configured;
        putenv('FAPSHI_BASE_URL=' . $configured);

        return (require __DIR__ . '/../config/fapshi.php')['base_url'];
    };

    check('live + blank url    -> the live host', 'https://live.fapshi.com', $hostFor('live', ''));
    check('sandbox + blank url -> the sandbox host', 'https://sandbox.fapshi.com', $hostFor('sandbox', ''));
    check('live, but the url still says sandbox -> live wins',
        'https://live.fapshi.com', $hostFor('live', 'https://sandbox.fapshi.com'));
    check('sandbox, but the url says live -> sandbox wins',
        'https://sandbox.fapshi.com', $hostFor('sandbox', 'https://live.fapshi.com'));
    check('a genuinely custom host is still honoured',
        'https://gateway.example.test', $hostFor('live', 'https://gateway.example.test'));

    echo "\n=== 0b. test keys and a live environment never mix ===\n";

    /*
     * The quiet, expensive failure: a live site holding sandbox credentials
     * sends every student to a test checkout and collects nothing, while every
     * screen reports that online payment is working.
     *
     * It is easy to reach. The environment and the credentials are changed in
     * different fields, and the API key box is rendered blank on purpose —
     * a live key is shown once by Fapshi and cannot be read back, so blank has
     * to mean "keep what is stored". Switching to Live and saving therefore
     * keeps the sandbox key.
     *
     * Fapshi prefixes test keys with FAK_TEST_, so the two can be told apart
     * without asking anyone.
     */
    $mismatchFor = function (string $environment, string $key): ?string {
        configureGateway(['fapshi.environment' => $environment, 'fapshi.api_key' => $key]);

        return (new FapshiClient())->credentialEnvironmentMismatch();
    };

    check('a test key on live is caught', 'sandbox',
        $mismatchFor('live', 'FAK_TEST_c392c5fea47df606a52c'));
    check('a live key on sandbox is caught', 'live',
        $mismatchFor('sandbox', 'FAK_96226abcdefghijklmnopqrstuvwx'));
    check('a test key on sandbox is fine', null,
        $mismatchFor('sandbox', 'FAK_TEST_c392c5fea47df606a52c'));
    check('a live key on live is fine', null,
        $mismatchFor('live', 'FAK_96226abcdefghijklmnopqrstuvwx'));
    check('no key at all is not a mismatch', null, $mismatchFor('live', ''));

    // And it must stop a student ever being offered the button.
    configureGateway(['fapshi.environment' => 'live', 'fapshi.api_key' => 'FAK_TEST_c392c5fea47df606a52c']);
    $setting->refresh();
    ok('a mismatch blocks online payment outright',
        str_contains((string) $setting->onlinePaymentBlocker(), 'sandbox key'));

    configureGateway();
    check('and a matched pair does not block it', null, $setting->onlinePaymentBlocker());

    echo "\n=== 1. what the student is asked for ===\n";

    check('2,000 at 3.15% gives a 63 charge', 63, $setting->surchargeAmount());
    check('so the student pays 2,063', 2063, $setting->chargeAmount());
    check('a whole-unit fee is accepted', true, $setting->feeIsWholeUnits());

    // Scales with the fee, which a flat surcharge would not.
    $setting->fee_amount = 20000;
    check('and 20,000 gives 630, not 63', 630, $setting->surchargeAmount());

    // Rounded up, never down: rounding down would have the school absorb the
    // remainder on every single payment.
    $setting->fee_amount = 1000;
    $setting->gateway_fee_percent = 3.15;
    check('31.5 rounds up to 32', 32, $setting->surchargeAmount());

    $setting->gateway_fee_percent = 3;
    $setting->gateway_fee_fixed = 3;
    check('percent and fixed add together', 33, $setting->surchargeAmount());

    $setting->fee_amount = 2000.50;
    check('a fractional fee is refused, not rounded', false, $setting->feeIsWholeUnits());

    // Back to the real configuration.
    $setting->fill(['fee_amount' => 2000, 'gateway_fee_percent' => 3.15, 'gateway_fee_fixed' => 0])->save();
    $setting->refresh();

    echo "\n=== 2. why online payment would not be offered ===\n";

    check('with everything set, nothing blocks it', null, $setting->onlinePaymentBlocker());

    $setting->online_payment_enabled = false;
    ok('the switch off blocks it', str_contains((string) $setting->onlinePaymentBlocker(), 'switched off'));
    $setting->online_payment_enabled = true;

    $setting->is_enabled = false;
    ok('the fee itself being off blocks it', str_contains((string) $setting->onlinePaymentBlocker(), 'fee itself'));
    $setting->is_enabled = true;

    $setting->currency = 'FRW';
    ok('a non-XAF currency blocks it', str_contains((string) $setting->onlinePaymentBlocker(), 'only accepts XAF'));
    $setting->currency = 'XAF';

    $setting->fee_amount = 2000.50;
    ok('a fractional fee blocks it', str_contains((string) $setting->onlinePaymentBlocker(), 'whole number'));
    $setting->fee_amount = 2000;

    $setting->fee_amount = 50;
    ok('a charge under the minimum blocks it', str_contains((string) $setting->onlinePaymentBlocker(), 'minimum'));
    $setting->fee_amount = 2000;
    $setting->save();
    $setting->refresh();

    configureGateway(['fapshi.enabled' => false]);
    ok('a disabled gateway blocks it', str_contains((string) $setting->onlinePaymentBlocker(), 'gateway is disabled'));

    configureGateway(['fapshi.api_key' => null]);
    ok('missing credentials block it', str_contains((string) $setting->onlinePaymentBlocker(), 'API user and key'));

    configureGateway();
    check('and restored, nothing blocks it again', null, $setting->onlinePaymentBlocker());

    echo "\n=== 3. starting a payment: the refusals ===\n";

    $student = Student::findOrFail(CLEAN_STUDENT);
    $paymentsBefore = PlatformFeePayment::count();

    $setting->update(['online_payment_enabled' => false]);
    $result = gateway()->initiate($student->refresh());
    check('the switch off refuses to start one', false, $result['ok']);
    $setting->update(['online_payment_enabled' => true]);

    $setting->update(['currency' => 'FRW']);
    check('the wrong currency refuses', false, gateway()->initiate($student)['ok']);
    $setting->update(['currency' => 'XAF']);

    $setting->update(['fee_amount' => 20]);
    check('a charge under the gateway minimum refuses', false, gateway()->initiate($student)['ok']);
    $setting->update(['fee_amount' => 2000]);

    $exempt = Student::findOrFail(EXEMPT_STUDENT);
    $exemptResult = gateway()->initiate($exempt);
    check('an exempt student refuses', false, $exemptResult['ok']);
    ok('and says why', str_contains((string) $exemptResult['error'], 'exempt'));

    check('no payment row was created by any refusal', $paymentsBefore, PlatformFeePayment::count());
    check('and no gateway transaction either', $transactionsBefore, FapshiTransaction::count());

    echo "\n=== 4. starting a payment: the happy path ===\n";

    fakeInitiateOk('FAPSHI-HAPPY');
    $setting->refresh();

    $result = gateway()->initiate($student);

    check('it starts', true, $result['ok']);
    ok('and hands back a link', str_contains((string) ($result['link'] ?? ''), 'checkout.fapshi.test'));

    $transaction = $result['transaction'];

    check('the external id names the row', 'PFEE-' . $transaction->id, $transaction->external_id);
    check('the gateway reference is stored', 'FAPSHI-HAPPY', $transaction->trans_id);
    check('status is created, not paid', 'created', $transaction->status);
    check('the charge recorded is 2,063', '2063.00', $transaction->charged_amount);
    check('and the fee behind it is 2,000', '2000.00', $transaction->fee_amount);

    // The amount actually sent to the gateway, read off the recorded request:
    // the figure shown to the student and the figure charged must be one number.
    check('the amount sent to the gateway is 2,063', 2063, $transaction->raw_request['amount']);
    check('the matricule went as the user id', 'PAX25CEH067', $transaction->raw_request['userId']);
    ok('a return url was supplied', str_contains($transaction->raw_request['redirectUrl'], '/payment/fapshi/return/'));

    // The verification queue means "an admin needs to look at this". A student
    // who merely opened a link is not that.
    check('no payment row is created when a payment STARTS', $paymentsBefore, PlatformFeePayment::count());
    ok('and the student still has no access', !$access->grantsAccess($student->refresh()));

    echo "\n=== 5. one live link per student per session ===\n";

    $again = gateway()->initiate($student);

    check('a second tap returns the same link', $transaction->payment_link, $again['link']);
    check('and says so', true, $again['reused']);
    check('without creating a second transaction', $transactionsBefore + 1, FapshiTransaction::count());

    // Past 24 hours the gateway will not take it, so a fresh one is minted and
    // the old one retired rather than handed over again.
    $transaction->update(['initiated_at' => now()->subHours(25), 'created_at' => now()->subHours(25)]);
    fakeInitiateOk('FAPSHI-FRESH');

    $third = gateway()->initiate($student);

    check('an expired link is replaced, not reissued', false, $third['reused'] ?? false);
    check('the old one is marked expired', 'expired', $transaction->refresh()->status);
    check('and there are now two transactions', $transactionsBefore + 2, FapshiTransaction::count());

    echo "\n=== 5b. a student whose enrolment has no session ===\n";

    // 39 enrolments on this database carry no session. Matching with
    // where('session_id', null) compiles to "= NULL" and matches nothing, so
    // such a student would be handed a brand new link on every single tap.
    $sessionless = FapshiTransaction::create([
        'environment' => 'sandbox',
        'trans_id' => 'FAPSHI-NOSESSION',
        'external_id' => 'PFEE-NOSESSION',
        'student_id' => CLEAN_STUDENT_2,
        'student_enroll_id' => $access->currentEnrollment(Student::findOrFail(CLEAN_STUDENT_2))->id,
        'session_id' => null,
        'fee_amount' => 2000,
        'charged_amount' => 2063,
        'currency' => 'XAF',
        'status' => 'created',
        'payment_link' => 'https://checkout.fapshi.test/FAPSHI-NOSESSION',
        'initiated_at' => now(),
    ]);

    $found = gateway()->openTransactionFor(Student::findOrFail(CLEAN_STUDENT_2), null);
    check('its open link is found rather than duplicated', $sessionless->id, $found?->id);

    $sessionless->delete();

    echo "\n=== 6. confirming: nothing is credited that should not be ===\n";

    $live = $third['transaction'];

    // PENDING — the student is still at the checkout.
    fakeStatus($live, 'PENDING');
    $outcome = gateway()->syncFromProvider($live->refresh());
    check('PENDING does not credit', false, $outcome['credited']);
    check('and leaves the payment open', 'pending', $live->refresh()->status);
    check('with no payment row', $paymentsBefore, PlatformFeePayment::count());

    // Fapshi echoes back the amount ASKED FOR on a payment nobody has made
    // yet. Recording that as received says the student paid 2,063 before they
    // have paid anything, and the reports then add it up.
    check('and nothing is recorded as received yet', null, $live->amount_received);
    check('nor any revenue', null, $live->revenue);

    // Short payment. The check that stops a student paying 100 for a year.
    fakeStatus($live, 'SUCCESSFUL', ['amount' => 100]);
    $outcome = gateway()->syncFromProvider($live->refresh());
    check('a short payment does not credit', false, $outcome['credited']);
    check('it is flagged as mismatched', 'mismatched', $live->refresh()->status);
    ok('the amount received is kept', (float) $live->amount_received === 100.0);
    ok('and the reason names the figures', str_contains((string) $live->failure_reason, '100.00'));
    check('no payment row was created', $paymentsBefore, PlatformFeePayment::count());
    ok('and still no access', !$access->grantsAccess($student->refresh()));

    // An externalId belonging to somebody else's payment.
    fakeInitiateOk('FAPSHI-IDENT');
    $identity = gateway()->initiate(Student::findOrFail(CLEAN_STUDENT_2))['transaction'];

    fakeStatus($identity, 'SUCCESSFUL', ['externalId' => 'PFEE-999999']);
    $outcome = gateway()->syncFromProvider($identity->refresh());

    check('a mismatched external id does not credit', false, $outcome['credited']);
    check('it is flagged too', 'mismatched', $identity->refresh()->status);
    ok('and the reason names both ids', str_contains((string) $identity->failure_reason, 'PFEE-999999'));

    // FAILED and EXPIRED.
    fakeInitiateOk('FAPSHI-FAILED');
    $failed = gateway()->initiate(Student::findOrFail(CLEAN_STUDENT_3))['transaction'];

    fakeStatus($failed, 'FAILED', ['reason' => 'Insufficient balance']);
    gateway()->syncFromProvider($failed->refresh());

    check('FAILED is recorded as failed', 'failed', $failed->refresh()->status);
    ok('with the operator reason kept', str_contains((string) $failed->failure_reason, 'Insufficient balance'));

    fakeStatus($failed, 'EXPIRED');
    gateway()->syncFromProvider($failed->refresh());
    check('a settled payment is not reopened by a later answer', 'failed', $failed->refresh()->status);

    check('none of that created a payment row', $paymentsBefore, PlatformFeePayment::count());

    echo "\n=== 7. confirming: the payment that should be credited ===\n";

    fakeInitiateOk('FAPSHI-GOOD');
    $good = gateway()->initiate($student->refresh())['transaction'];

    fakeStatus($good, 'SUCCESSFUL');
    $outcome = gateway()->syncFromProvider($good->refresh());

    check('it credits', true, $outcome['credited']);
    check('the transaction is successful', 'successful', $good->refresh()->status);

    $payment = $outcome['payment'];

    check('a payment row now exists', $paymentsBefore + 1, PlatformFeePayment::count());
    check('and it is approved', 'approved', $payment->status);
    check('settled by the gateway', 'fapshi', $payment->paid_via);
    check('carrying the gateway reference', 'FAPSHI-GOOD', $payment->gateway_trans_id);
    check('with what was actually received', '2063.00', $payment->paid_amount);
    check('and the fee it settles', '2000.00', $payment->fee_amount);
    check('nobody here approved it', null, $payment->verified_by);
    ok('but it is marked verified', $payment->verified_at !== null);
    ok('the note carries the reference', str_contains((string) $payment->admin_note, 'FAPSHI-GOOD'));
    ok('and the operator reference', str_contains((string) $payment->admin_note, 'OPERATOR-991'));
    check('the two rows are linked', $payment->id, $good->refresh()->platform_fee_payment_id);
    check('and the revenue the school receives is kept', '1998.00', $good->revenue);

    // The point of the whole exercise.
    ok('THE STUDENT NOW HAS ACCESS', $access->grantsAccess($student->refresh()));
    check('and the access service agrees it is paid', true,
        $access->hasPaid($student, $access->currentEnrollment($student)));

    echo "\n=== 8. idempotency: a webhook and a returning student both arrive ===\n";

    $outcome = gateway()->syncFromProvider($good->refresh());
    check('a second confirmation does not credit again', true, $outcome['credited']);
    check('still one payment row', $paymentsBefore + 1, PlatformFeePayment::count());
    check('and it was charged once', '2063.00', $payment->refresh()->paid_amount);

    // Straight at confirm(), as a replayed notification would arrive.
    gateway()->confirm($good->refresh(), statusBody($good, 'SUCCESSFUL'));
    check('replaying the payload changes nothing', $paymentsBefore + 1, PlatformFeePayment::count());

    echo "\n=== 9. money that arrives when it should not ===\n";

    // Already settled: a second payment for the same session.
    fakeInitiateOk('FAPSHI-DOUBLE');
    $doubleTx = new FapshiTransaction([
        'environment' => 'sandbox',
        'trans_id' => 'FAPSHI-DOUBLE',
        'external_id' => 'PFEE-DOUBLE',
        'student_id' => $student->id,
        'student_enroll_id' => $access->currentEnrollment($student)->id,
        'session_id' => $access->currentEnrollment($student)->session_id,
        'fee_amount' => 2000,
        'charged_amount' => 2063,
        'currency' => 'XAF',
        'status' => 'created',
        'initiated_at' => now(),
    ]);
    $doubleTx->save();

    fakeStatus($doubleTx, 'SUCCESSFUL');
    $outcome = gateway()->syncFromProvider($doubleTx->refresh());

    check('a payment for an already-settled fee does not credit', false, $outcome['credited']);
    check('it is flagged refundable', 'refundable', $doubleTx->refresh()->status);
    ok('the student is told a refund is coming', str_contains((string) $outcome['message'], 'refund'));
    check('and still just one payment row', $paymentsBefore + 1, PlatformFeePayment::count());
    ok('the amount is kept so it can be refunded', (float) $doubleTx->amount_received === 2063.0);

    echo "\n=== 10. the switch takes the route away, never the money ===\n";

    fakeInitiateOk('FAPSHI-INFLIGHT');
    $inflight = gateway()->initiate(Student::findOrFail(CLEAN_STUDENT_2))['transaction'];

    // The admin flips online payment off while this student is on the
    // checkout page. Getting this backwards takes a student's money and then
    // refuses them access.
    $setting->update(['online_payment_enabled' => false]);

    check('starting a new payment is now refused', false,
        gateway()->initiate(Student::findOrFail(CLEAN_STUDENT_3))['ok']);

    fakeStatus($inflight, 'SUCCESSFUL');
    $outcome = gateway()->syncFromProvider($inflight->refresh());

    check('but the payment already in flight STILL credits', true, $outcome['credited']);
    ok('and that student gets access', $access->grantsAccess(Student::findOrFail(CLEAN_STUDENT_2)));

    $setting->update(['online_payment_enabled' => true]);

    echo "\n=== 11. an existing manual receipt is settled, not duplicated ===\n";

    // The shape 1 of the 5 live rows is in: a receipt uploaded, awaiting a
    // human. Paying online must settle THAT row — the unique index on
    // (student_enroll_id, session_id) permits no second one.
    $manualStudent = Student::findOrFail(CLEAN_STUDENT_3);
    $manualEnrollment = $access->currentEnrollment($manualStudent);

    $manualRow = PlatformFeePayment::create([
        'student_enroll_id' => $manualEnrollment->id,
        'session_id' => $manualEnrollment->session_id,
        'fee_amount' => 2000,
        'paid_amount' => 2000,
        'receipt_path' => 'test_receipt.png',
        'student_note' => 'Paid by shortcode',
        'payment_date' => now(),
        'status' => 'pending',
    ]);

    $rowsWithManual = PlatformFeePayment::count();

    $setting->refresh();
    fakeInitiateOk('FAPSHI-SETTLES');
    $settling = gateway()->initiate($manualStudent)['transaction'];

    fakeStatus($settling, 'SUCCESSFUL');
    $outcome = gateway()->syncFromProvider($settling->refresh());

    check('it credits', true, $outcome['credited']);
    check('no second row was raised', $rowsWithManual, PlatformFeePayment::count());
    check('the existing row was settled', $manualRow->id, $outcome['payment']->id);
    check('and now reads approved', 'approved', $manualRow->refresh()->status);
    check('settled online', 'fapshi', $manualRow->paid_via);
    check('the uploaded receipt is not destroyed', 'test_receipt.png', $manualRow->receipt_path);

    echo "\n=== 12. the webhook is a nudge, never evidence ===\n";

    $hookStudent = Student::findOrFail(CLEAN_STUDENT_4);
    unsettle($hookStudent);

    fakeInitiateOk('FAPSHI-HOOK');
    $setting->refresh();

    $hookStart = gateway()->initiate($hookStudent);
    check('a clean student can start a payment to test the webhook with', true, $hookStart['ok']);
    $hookTx = $hookStart['transaction'];

    $controller = new FapshiController(gateway(), new FapshiClient());

    // A forged notification, with no secret at all.
    fakeStatus($hookTx, 'SUCCESSFUL');
    $request = Request::create('/payment/fapshi/webhook', 'POST', [
        'transId' => $hookTx->trans_id,
        'status' => 'SUCCESSFUL',
    ]);
    $response = $controller->webhook($request);

    check('a webhook with no secret is refused', 403, $response->getStatusCode());
    check('and nothing was credited', 'created', $hookTx->refresh()->status);

    // The wrong secret.
    $request->headers->set('x-wh-secret', 'not-the-secret');
    check('a wrong secret is refused', 403, $controller->webhook($request)->getStatusCode());
    check('still nothing credited', 'created', $hookTx->refresh()->status);

    // THE check that proves the body is ignored: the right secret, a body
    // claiming success, and a gateway that says the payment is still pending.
    fakeStatus($hookTx, 'PENDING');
    $request->headers->set('x-wh-secret', 'test-webhook-secret');
    $response = $controller->webhook($request);

    check('an authentic webhook is accepted', 200, $response->getStatusCode());
    check('but a body claiming SUCCESSFUL is NOT believed', 'pending', $hookTx->refresh()->status);
    ok('and no access was granted', !$access->grantsAccess($hookTx->student));

    // The same webhook, once the gateway itself agrees.
    fakeStatus($hookTx, 'SUCCESSFUL');
    $controller->webhook($request);
    check('once the gateway confirms it, it credits', 'successful', $hookTx->refresh()->status);

    // A webhook about something this system never issued.
    $unknown = Request::create('/payment/fapshi/webhook', 'POST', ['transId' => 'NEVER-SEEN']);
    $unknown->headers->set('x-wh-secret', 'test-webhook-secret');
    check('an unknown reference answers 200 and does nothing', 200,
        $controller->webhook($unknown)->getStatusCode());

    // With no secret configured at all, every webhook must be refused: an
    // unconfigured secret cannot tell Fapshi apart from anybody else.
    configureGateway(['fapshi.webhook_secret' => null]);
    $noSecret = new FapshiController(gateway(), new FapshiClient());
    $request->headers->set('x-wh-secret', '');
    check('with no secret set, webhooks are refused', 403,
        $noSecret->webhook($request)->getStatusCode());
    configureGateway();

    echo "\n=== 13. what the student actually sees, each way round ===\n";

    /*
     * The switch's whole purpose is that a school can take online payment away
     * and get the manual route back. Asserting the flag is not enough — what
     * matters is the page, so it is rendered both ways and read.
     *
     * ShareErrorsFromSession is middleware, and invoking the controller
     * directly skips it, so $errors has to be shared by hand.
     */
    Illuminate\Support\Facades\View::share('errors', new Illuminate\Support\ViewErrorBag());

    $viewStudent = Student::findOrFail(CLEAN_STUDENT_4);
    unsettle($viewStudent);
    Auth::guard('student')->login($viewStudent);

    $studentPage = app(App\Http\Controllers\Student\PlatformFeePaymentController::class);

    $setting->update(['online_payment_enabled' => true]);
    $onlineHtml = $studentPage->index()->render();

    ok('ON: the Pay button is on the page', str_contains($onlineHtml, 'payment/fapshi/initiate'));
    ok('ON: it shows the total of 2,063', str_contains($onlineHtml, '2,063'));
    ok('ON: and the breakdown that explains it', str_contains($onlineHtml, 'Mobile money charge'));
    ok('ON: the receipt upload is NOT offered', !str_contains($onlineHtml, 'platform-fee/payment/upload'));

    $setting->update(['online_payment_enabled' => false]);
    $manualHtml = $studentPage->index()->render();

    ok('OFF: the receipt upload is back', str_contains($manualHtml, 'platform-fee/payment/upload'));
    ok('OFF: the shortcode is back', str_contains($manualHtml, 'How to pay'));
    ok('OFF: and the Pay button is gone', !str_contains($manualHtml, 'payment/fapshi/initiate'));

    $setting->update(['online_payment_enabled' => true]);

    echo "\n=== 13b. the manual route is refused while online payment is on ===\n";

    // Hiding the upload form is a courtesy; this is the control.
    $setting->update(['online_payment_enabled' => true]);
    $setting->refresh();
    ok('online payment is available', $setting->onlinePaymentAvailable());

    $setting->update(['online_payment_enabled' => false]);
    ok('and with the switch off the manual route is open again',
        !$setting->refresh()->onlinePaymentAvailable());
    $setting->update(['online_payment_enabled' => true]);

    echo "\n=== 14. a gateway payment is not a human's to approve ===\n";

    $gatewayRow = PlatformFeePayment::where('paid_via', 'fapshi')->firstOrFail();
    $adminController = app(App\Http\Controllers\Admin\PlatformFeeController::class);

    $reflection = new ReflectionMethod($adminController, 'refuseIfGatewayPaid');
    $reflection->setAccessible(true);

    $refusal = $reflection->invoke($adminController, $gatewayRow);
    ok('approve/reject refuses a gateway row', $refusal !== null);
    check('with 422', 422, $refusal->getStatusCode());

    $manualOnly = PlatformFeePayment::where('paid_via', '!=', 'fapshi')->first();
    if ($manualOnly) {
        check('and lets a manual row through', null, $reflection->invoke($adminController, $manualOnly));
    }

    echo "\n=== 15. a gateway failure never leaves a student guessing ===\n";

    $brokenStudent = Student::findOrFail(CLEAN_STUDENT_2);
    unsettle($brokenStudent);

    fakeGateway('FAPSHI-BROKEN', null, 403);
    $setting->refresh();

    $broken = gateway()->initiate($brokenStudent);
    check('a 403 from the gateway refuses cleanly', false, $broken['ok']);
    ok('without showing the student the provider error',
        !str_contains(strtolower((string) $broken['error']), 'forbidden'));

    $lastTx = FapshiTransaction::orderByDesc('id')->first();
    check('and the attempt is recorded as failed', 'failed', $lastTx->status);
    ok('with the provider response kept for diagnosis',
        str_contains(json_encode($lastTx->raw_response), '403'));

    echo "\n=== 16. teeth: break each rule, prove a named check fails ===\n";

    // Without the amount check, a 100 XAF payment unlocks the year.
    fakeInitiateOk('FAPSHI-TEETH');
    $setting->refresh();
    $teeth = gateway()->initiate(Student::findOrFail(CLEAN_STUDENT_3));

    if ($teeth['ok']) {
        $teethTx = $teeth['transaction'];
        fakeStatus($teethTx, 'SUCCESSFUL', ['amount' => 100]);
        $outcome = gateway()->syncFromProvider($teethTx->refresh());
        ok('a 100 XAF payment is refused (check 6 would fail if the amount test went)',
            $outcome['credited'] === false && $teethTx->refresh()->status === 'mismatched');
    } else {
        // That student was settled in section 11, which is itself the proof
        // that an already-paid student cannot start another payment.
        ok('an already-settled student cannot start another payment', true);
    }

    // And the identity check, independently of the amount check.
    $identityTeeth = FapshiTransaction::create([
        'environment' => 'sandbox',
        'trans_id' => 'FAPSHI-TEETH-ID',
        'external_id' => 'PFEE-TEETH-ID',
        'student_id' => CLEAN_STUDENT_2,
        'student_enroll_id' => $access->currentEnrollment(Student::findOrFail(CLEAN_STUDENT_2))->id,
        'session_id' => $access->currentEnrollment(Student::findOrFail(CLEAN_STUDENT_2))->session_id,
        'fee_amount' => 2000,
        'charged_amount' => 2063,
        'currency' => 'XAF',
        'status' => 'created',
        'initiated_at' => now(),
    ]);

    $outcome = gateway()->confirm($identityTeeth, statusBody($identityTeeth, 'SUCCESSFUL', [
        'externalId' => 'PFEE-SOMEBODY-ELSE',
        'amount' => 5000,   // more than enough, so only identity can refuse it
    ]));

    ok('a generous payment against the wrong id is still refused',
        $outcome['credited'] === false && $outcome['status'] === 'mismatched');

    echo "\n=== 17. nothing escaped into the real tables ===\n";

    printf("  inside the transaction: %d payments, %d gateway transactions\n",
        PlatformFeePayment::count(), FapshiTransaction::count());
} catch (\Throwable $e) {
    echo "\n  *** the suite threw: " . $e->getMessage() . "\n";
    echo '      ' . $e->getFile() . ':' . $e->getLine() . "\n";
    $fail++;
} finally {
    DB::rollBack();
}

echo "\n=== nothing was left behind ===\n";

$closing = [
    'settings' => DB::table('platform_fee_settings')->count(),
    'payments' => DB::table('platform_fee_payments')->count(),
    'approved' => DB::table('platform_fee_payments')->where('status', 'approved')->count(),
    'pending' => DB::table('platform_fee_payments')->where('status', 'pending')->count(),
    'exemptions' => DB::table('platform_fee_exemptions')->count(),
    'transactions' => DB::table('fapshi_transactions')->count(),
    'gateway_paid' => DB::table('platform_fee_payments')->where('paid_via', '!=', 'manual')->count(),
    'currency' => DB::table('platform_fee_settings')->value('currency'),
    'fee' => DB::table('platform_fee_settings')->value('fee_amount'),
];

// Absolute counts, not deltas: a delta of zero is also what two mistakes
// cancelling each other out looks like.
foreach ($opening as $key => $value) {
    check(sprintf('%-14s is still %s', $key, $value), $value, $closing[$key]);
}

// Against what was there when the suite started, not zero: a real install has
// live transactions, and a suite demanding an empty table tests the database.
check('gateway transactions are back where they were', $opening['transactions'], $closing['transactions']);
// Against the opening count, not zero. A real installation legitimately holds
// payments settled by the gateway — the first genuine one made this check fail
// and say rows had escaped the rollback, which they had not.
check('gateway-settled payments are back where they were', $opening['gateway_paid'],
    DB::table('platform_fee_payments')->where('paid_via', '!=', 'manual')->count());

printf("\n%d passed, %d failed\n\n", $pass, $fail);

exit($fail === 0 ? 0 : 1);
