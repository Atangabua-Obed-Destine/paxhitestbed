<?php

/**
 * What the student is told after they upload, and where they are told it.
 *
 * Two things were confusing. The status sat below the fee details and the
 * payment steps, so a student who had already sent a receipt met instructions
 * to pay before any sign their receipt had arrived — and the page went on
 * offering "How to pay" and a Pay button, which invites paying twice.
 *
 * So: the status is the first thing on the page, the way to pay is only shown
 * while there is still something to pay, and an upload is confirmed outright.
 *
 * Writes happen inside transactions that are rolled back.
 *
 *   php scripts/platform_fee_status_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\PlatformFeeExemption;
use App\Models\PlatformFeePayment;
use App\Models\PlatformFeeSetting;
use App\Models\Student;
use App\Services\PlatformFeeAccess;

$passed = 0;
$failed = 0;
$skipped = 0;

function check(string $what, bool $ok, string $detail = ''): void
{
    global $passed, $failed;

    if ($ok) {
        $passed++;
        echo "  PASS  $what\n";
    } else {
        $failed++;
        echo "  FAIL  $what" . ($detail !== '' ? " — $detail" : '') . "\n";
    }
}

function skip(string $why): void
{
    global $skipped;
    $skipped++;
    echo "  SKIP  $why\n";
}

function section(string $title): void
{
    echo "\n" . $title . "\n";
}

$student = Student::whereHas('enrolls')->whereNotNull('student_id')->first();

if (!$student) {
    echo "no student to render the page for\n";
    exit(0);
}

$access = app(PlatformFeeAccess::class);
$enrollment = $access->currentEnrollment($student);
$enrollmentIds = $access->enrollmentsInSameSession($student, $enrollment);

/** Render the payment page for this student, with a payment in the given state. */
function pageWith(?string $status, $student, $enrollment, $enrollmentIds, $kernel, bool $withFlash = false): string
{
    PlatformFeeSetting::first()->update([
        'is_enabled' => 1,
        'ussd_template' => '*126*4*123456*{amount}#',
    ]);

    PlatformFeePayment::whereIn('student_enroll_id', $enrollmentIds)->delete();
    PlatformFeeExemption::whereIn('student_enroll_id', $enrollmentIds)->delete();

    if ($enrollment->session_id) {
        PlatformFeeExemption::where('exemption_type', 'session')
            ->where('session_id', $enrollment->session_id)->delete();
    }

    if ($status !== null) {
        PlatformFeePayment::create([
            'student_enroll_id' => $enrollment->id,
            'session_id' => $enrollment->session_id,
            'fee_amount' => 2000,
            'paid_amount' => 2000,
            'status' => $status,
            'payment_date' => now()->toDateString(),
            'admin_note' => $status === 'rejected' ? 'The receipt was unreadable.' : null,
        ]);
    }

    app('session.store')->start();

    if ($withFlash) {
        app('session.store')->flash('success', 'Payment receipt uploaded successfully! Awaiting verification from administration.');
    }

    $request = Request::create('/student/platform-fee/payment', 'GET');
    $request->setLaravelSession(app('session.store'));
    Auth::guard('student')->login($student);

    return $kernel->handle($request)->getContent();
}

/** Where something appears in the page, so "first" can be asserted rather than assumed. */
function at(string $body, string $needle): int
{
    $position = strpos($body, $needle);

    return $position === false ? PHP_INT_MAX : $position;
}

// ---------------------------------------------------------------------------
section('A receipt waiting to be checked');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    $body = pageWith('pending', $student, $enrollment, $enrollmentIds, $kernel);

    check('the page says the receipt is with the school',
        str_contains($body, 'Your receipt is with the school'));
    check('and that is above the fee details',
        at($body, 'class="status-banner') < at($body, 'Fee Details'));
    check('it tells them not to pay again',
        stripos($body, 'do not need to pay again') !== false);
    check('the way to pay is no longer pushed at them',
        !str_contains($body, 'How to pay'));
    // The class is defined in the stylesheet either way; what matters is that
    // no dial link is rendered.
    check('nor is the Pay button', !str_contains($body, 'href="tel:'));
    check('the payment status card is still there to read',
        str_contains($body, 'Payment Status'));
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('A receipt that was approved');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    $body = pageWith('approved', $student, $enrollment, $enrollmentIds, $kernel);

    check('the page says it was approved', str_contains($body, 'Payment approved'));
    check('and says nothing further is needed',
        stripos($body, 'Nothing further is needed') !== false);
    check('the way to pay is not shown', !str_contains($body, 'How to pay'));
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('A receipt that was turned down');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    $body = pageWith('rejected', $student, $enrollment, $enrollmentIds, $kernel);

    check('the page says it could not be accepted',
        str_contains($body, 'could not be accepted'));
    check('and gives the reason the school recorded',
        str_contains($body, 'The receipt was unreadable.'));
    check('the way to pay comes back, because there is still something to pay',
        str_contains($body, 'How to pay'));
    check('and so does the upload form',
        stripos($body, 'Reupload Payment Receipt') !== false);
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('Nothing uploaded yet');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    $body = pageWith(null, $student, $enrollment, $enrollmentIds, $kernel);

    check('no status banner is shown', !str_contains($body, 'class="status-banner'));
    check('the way to pay is shown', str_contains($body, 'How to pay'));
    check('and the upload form', stripos($body, 'Upload Payment Receipt') !== false);
} finally {
    DB::rollBack();
}

// ---------------------------------------------------------------------------
section('Straight after uploading');
// ---------------------------------------------------------------------------
DB::beginTransaction();

try {
    $body = pageWith('pending', $student, $enrollment, $enrollmentIds, $kernel, true);

    check('a confirmation is prepared', str_contains($body, 'uploadDoneModal'));
    check('and opened without being asked for',
        str_contains($body, "getOrCreateInstance(doneModal).show()"));
    check('it says the receipt was received', str_contains($body, 'Receipt received'));
    check('it says what happens next', str_contains($body, 'What happens next'));
    check('and closing it leaves them looking at their status',
        str_contains($body, "getElementById('statusBanner')")
            && str_contains($body, 'scrollIntoView'));

    // Without a fresh upload there is nothing to confirm.
    $quiet = pageWith('pending', $student, $enrollment, $enrollmentIds, $kernel, false);

    check('revisiting the page later does not pop the confirmation again',
        !str_contains($quiet, "getOrCreateInstance(doneModal).show()"));
    check('but the status is still there', str_contains($quiet, 'class="status-banner'));
} finally {
    DB::rollBack();
}

echo "\n----\npassed $passed, failed $failed" . ($skipped ? ", skipped $skipped" : '') . "\n";

exit($failed > 0 ? 1 : 0);
