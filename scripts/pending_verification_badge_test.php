<?php

/**
 * The accountant is told when a payment receipt is waiting.
 *
 * A receipt a student uploads used to sit in a queue nobody was told about:
 * the only way to learn of one was to open the verification screen and look.
 * On this installation that produced a receipt pending since 22 September,
 * unseen for the better part of a month, while the student waited for their
 * payment to be recognised.
 *
 * The count now rides on the menu, so it is in front of whoever can act on it
 * on every page they open. What has to hold:
 *
 *  - It appears for somebody who may verify, and NOT for anyone else. A count
 *    shown to a person who cannot act on it is noise, and it leaks how much
 *    money is in flight to people with no business knowing.
 *  - It appears on the parent menu as well, because that submenu is collapsed
 *    most of the time and a badge nobody sees is no reminder.
 *  - It disappears by itself when the queue is empty, so it never becomes
 *    wallpaper.
 *  - It counts the same things the verification screen counts, or the badge
 *    and the page contradict each other.
 *  - Acting on a receipt updates it at once rather than on a timer.
 *
 * Writes happen inside transactions that are rolled back.
 */

$base = 'C:/xampp/htdocs/paxhitestbed';
require $base . '/vendor/autoload.php';
$app = require_once $base . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\PendingVerifications;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;

function section(string $title): void
{
    echo "\n", $title, "\n", str_repeat('-', strlen($title)), "\n";
}

function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;

    if ($ok) {
        $passed++;
        echo "  PASS  $label\n";
    } else {
        $failed++;
        echo "  FAIL  $label", $detail ? "  ($detail)" : '', "\n";
    }
}

$render = function ($user) use ($kernel) {
    Auth::guard('web')->logout();
    app('session.store')->start();
    Auth::guard('web')->login($user);

    $request = Illuminate\Http\Request::create('/admin/dashboard', 'GET');
    $request->setLaravelSession(app('session.store'));

    return $kernel->handle($request)->getContent();
};

$badges = fn (string $html) => substr_count($html, 'pax-pending-badge');

$canVerify = App\User::get()->first(fn ($u) => $u->can('payment-receipt-verify'));
$cannotVerify = App\User::get()->first(fn ($u) => !$u->can('payment-receipt-verify'));

printf("verifier: %s | non-verifier: %s | pending: %d\n",
    $canVerify->email ?? 'none', $cannotVerify->email ?? 'none', PendingVerifications::count());

// ---------------------------------------------------------------------------
section('The count matches what the verification screen counts');

$expected = DB::table('payment_receipts')->where('verification_status', 'pending')->count()
    + DB::table('installment_payment_receipts')->where('status', 'pending')->count()
    + DB::table('multi_payments')->where('status', 'pending')->count();

PendingVerifications::forget();

check('the badge counts all three kinds of receipt',
    PendingVerifications::count() === $expected,
    sprintf('badge %d, tables %d', PendingVerifications::count(), $expected));

$summary = PendingVerifications::summary();

check('the breakdown adds up to the total',
    $summary['fee'] + $summary['installment'] + $summary['multi'] === $summary['total']);

// ---------------------------------------------------------------------------
section('It is shown to whoever can act, and to nobody else');

check('there is a user who may verify', (bool) $canVerify);
check('there is a user who may not', (bool) $cannotVerify);

if ($canVerify && PendingVerifications::count() > 0) {
    $html = $render($canVerify);

    check('the verifier sees it on the menu', $badges($html) >= 1);
    check('and on the parent too, for when the submenu is collapsed',
        $badges($html) === 2, $badges($html) . ' badges');
    check('it carries the reason as a tooltip',
        str_contains($html, 'waiting to be verified'));
    check('and that reason is available to a screen reader',
        str_contains($html, 'visually-hidden'));
    check('it says how long the oldest has waited',
        PendingVerifications::oldestWaitingDays() === null
        || str_contains(PendingVerifications::describe(), 'waiting'));
}

if ($cannotVerify) {
    check('a user without the permission is shown no count',
        $badges($render($cannotVerify)) === 0,
        'the count leaked to somebody who cannot act on it');
}

// ---------------------------------------------------------------------------
section('It disappears by itself when there is nothing to do');

DB::beginTransaction();

try {
    DB::table('payment_receipts')->where('verification_status', 'pending')
        ->update(['verification_status' => 'approved']);
    DB::table('installment_payment_receipts')->where('status', 'pending')
        ->update(['status' => 'approved']);
    DB::table('multi_payments')->where('status', 'pending')->update(['status' => 'approved']);

    PendingVerifications::forget();

    check('an empty queue counts zero', PendingVerifications::count() === 0);
    check('and says so plainly',
        str_contains(PendingVerifications::describe(), 'No payments'));

    if ($canVerify) {
        check('no badge is drawn at all', $badges($render($canVerify)) === 0);
    }
} finally {
    DB::rollBack();
    PendingVerifications::forget();
}

check('the queue is back as it was after the rollback',
    PendingVerifications::count() === $expected);

// ---------------------------------------------------------------------------
section('Acting on a receipt updates it at once');

$source = file_get_contents($base . '/app/Http/Controllers/Admin/PaymentVerificationController.php');

check('approving drops the cached figure',
    preg_match('/public function approve\(.*?\)\s*\{\s*.*?PendingVerifications::forget\(\)/s', $source) === 1);
check('rejecting drops it too',
    preg_match('/public function reject\(.*?\)\s*\{\s*.*?PendingVerifications::forget\(\)/s', $source) === 1);

foreach ([
    'Student/ManualPaymentController.php' => 'a student uploading a receipt',
    'Student/InstallmentPaymentController.php' => 'a student uploading an instalment receipt',
    'Web/ApplicationController.php' => 'an applicant uploading a receipt',
] as $file => $what) {
    check($what . ' refreshes it',
        str_contains(file_get_contents($base . '/app/Http/Controllers/' . $file),
            'PendingVerifications::forget()'));
}

// The cache must actually be a cache, or every page load pays for it.
$reflection = new ReflectionClass(PendingVerifications::class);
$ttl = $reflection->getConstant('TTL_SECONDS');

check('the figure is cached, so it is not three queries per page load',
    $ttl > 0, 'no TTL');
check('but not for so long that it looks wrong',
    $ttl <= 300, $ttl . ' seconds');

DB::beginTransaction();

try {
    $before = PendingVerifications::count();

    DB::table('payment_receipts')->where('verification_status', 'pending')->limit(1)
        ->update(['verification_status' => 'approved']);

    PendingVerifications::forget();

    check('after forget(), the new figure is read',
        PendingVerifications::count() === max(0, $before - 1),
        sprintf('%d -> %d', $before, PendingVerifications::count()));
} finally {
    DB::rollBack();
    PendingVerifications::forget();
}

// ---------------------------------------------------------------------------
section('Nothing was left behind');

Auth::guard('web')->logout();

check('the pending count is exactly as it was found',
    PendingVerifications::count() === $expected,
    sprintf('%d vs %d', PendingVerifications::count(), $expected));
check('no receipt changed status',
    DB::table('payment_receipts')->where('verification_status', 'pending')->count()
        === $summary['fee']);

echo "\n", str_repeat('=', 60), "\n";
printf("passed %d, failed %d\n", $passed, $failed);

exit($failed > 0 ? 1 : 0);
