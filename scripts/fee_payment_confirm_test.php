<?php

/**
 * The confirmation step before a fee payment is recorded.
 *
 * "Received" posted straight to the ledger: it credits the payment account,
 * issues a receipt number and marks the fee paid, from whatever number happened
 * to be in the amount box — which is pre-filled with the balance due, so a
 * student paying something else relies entirely on the officer retyping it. A
 * wrong figure can be reversed, but the reversal is itself a permanent entry,
 * so it is a correction on the record rather than a quiet edit.
 *
 * The figures are now read back and the officer has to confirm them against the
 * money in front of them. This is browser behaviour, so it is driven in a real
 * browser: asserting the markup exists would pass even if the button still
 * submitted straight through.
 *
 * Nothing is submitted and no payment is recorded.
 *
 *   php scripts/fee_payment_confirm_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Fee;

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
    echo "\n$title\n";
}

function findChrome(): ?string
{
    foreach ([
        'C:\Program Files\Google\Chrome\Application\chrome.exe',
        'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
        '/usr/bin/google-chrome',
        '/usr/bin/chromium',
        '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    ] as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    return null;
}

$chrome = findChrome();

if (!$chrome) {
    echo "needs headless Chrome: this is browser behaviour, and markup alone proves nothing\n";
    exit(0);
}

app('session.store')->start();
$admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();

if (!$admin) {
    echo "no Super Admin to render as\n";
    exit(0);
}

Auth::guard('web')->login($admin);

$request = Request::create('/admin/fees-student-report', 'GET', [
    'faculty' => 0, 'program' => 0, 'session' => 0, 'semester' => 0, 'section' => 0,
]);
$request->setLaravelSession(app('session.store'));
$response = $kernel->handle($request);

if ($response->getStatusCode() !== 200) {
    echo "the report page did not render (status " . $response->getStatusCode() . ")\n";
    exit(1);
}

// The subject is taken from the page the officer is actually looking at: the
// report is paginated, so a fee chosen from the database may have no modal
// rendered for it at all. Narrowed to one that still owes something, because
// the part-payment and overpayment notices need a balance to measure against.
preg_match_all('/id="pay-confirm-([0-9]+)"/', $response->getContent(), $onPage);

$fee = Fee::with('studentEnroll.student')
    ->whereIn('id', array_map('intval', $onPage[1]))
    ->get()
    ->first(fn ($candidate) => $candidate->remaining_balance > 0.005);

if (!$fee) {
    echo 'no fee on the first page of the report has an outstanding balance ('
        . count($onPage[1]) . " modals rendered)\n";
    exit(0);
}

echo 'fee #' . $fee->id . ' of ' . count($onPage[1]) . ' on the page, balance due '
    . number_format(max(0, $fee->remaining_balance), 2) . "\n";

$probeName = '_fee_confirm_probe_' . getmypid() . '.html';
$probePath = public_path($probeName);

register_shutdown_function(function () use ($probePath) {
    @unlink($probePath);
});

$harness = <<<'HTML'
<script>
window.addEventListener('load', function () {
  setTimeout(function () {
    var out = [];
    function note(k, v) { out.push(k + '=' + v); }
    function finish() {
      document.title = 'PROBE ' + out.join('|');
      document.body.insertAdjacentHTML('afterbegin', '<div>' + document.title + '</div>');
    }

    var id      = '__FEE__';
    var submit  = document.getElementById('submit-btn-' + id);
    var confirm = document.getElementById('pay-confirm-btn-' + id);
    var back    = document.getElementById('pay-back-' + id);
    var step    = document.getElementById('pay-confirm-' + id);
    var tick    = document.getElementById('pay-confirm-check-' + id);
    var amount  = document.getElementById('payment_amount-' + id);
    var method  = document.getElementById('payment_method-' + id);

    if (!submit || !confirm || !step || !tick) { document.title = 'PROBE missing=1'; return; }

    var form = submit.closest('form');
    note('submitButtonsInForm', form.querySelectorAll('button[type="submit"]').length);
    note('startsDisabled', confirm.disabled);
    note('startsHidden', step.classList.contains('d-none'));

    // The gate: no payment method chosen is an incomplete form.
    method.value = '';
    submit.click();
    note('refusesWhileIncomplete', step.classList.contains('d-none'));

    // Now a complete, ordinary part payment.
    method.value = '2';
    amount.value = '1234.5';
    amount.dispatchEvent(new Event('input'));
    submit.click();

    setTimeout(function () {
      note('confirmShown', !step.classList.contains('d-none'));
      note('formHidden', form.querySelector('.modal-body').classList.contains('d-none'));
      note('tickStartsUnticked', !tick.checked);
      note('confirmDisabledUntilTicked', confirm.disabled);

      var text = step.textContent.replace(/\s+/g, ' ');
      note('showsAmount', /1,234\.50/.test(text));
      note('showsMethod', text.indexOf(method.options[method.selectedIndex].text.trim()) !== -1);
      note('warnsPartPayment', /part payment/i.test(text));
      note('saysCheckTheMoney', /money you received/i.test(text));

      tick.checked = true;
      tick.dispatchEvent(new Event('change'));
      note('confirmEnabledAfterTick', !confirm.disabled);

      // An overpayment must be named, not silently accepted.
      back.click();
      amount.value = String(__MAX__ + 5000);
      amount.dispatchEvent(new Event('input'));
      submit.click();

      setTimeout(function () {
        var over = step.textContent.replace(/\s+/g, ' ');
        note('warnsOverpayment', /more than the balance due/i.test(over));
        note('tickResetOnReturn', !tick.checked);
        note('confirmDisabledAgain', confirm.disabled);
        finish();
      }, 250);
    }, 300);
  }, 1500);
});
</script>
HTML;

$harness = str_replace(
    ['__FEE__', '__MAX__'],
    [$fee->id, round(max(0, $fee->remaining_balance), 2)],
    $harness
);

file_put_contents($probePath, str_replace('</body>', $harness . '</body>', $response->getContent()));

$url = rtrim(config('app.url'), '/') . '/' . $probeName;
exec(escapeshellarg($chrome) . ' --headless=new --disable-gpu --virtual-time-budget=12000 --dump-dom '
    . escapeshellarg($url) . ' 2>&1', $lines);
$dom = implode("\n", $lines);

if (!preg_match('/PROBE ([^<]*)/', $dom, $match)) {
    echo "the browser never reported back.\n";
    echo "  Either the form submitted without stopping at the confirmation,\n";
    echo "  or the site is not served at " . config('app.url') . "\n";
    exit(1);
}

$result = [];
foreach (explode('|', trim($match[1])) as $pair) {
    [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
    $result[trim($key)] = trim($value);
}

$is = fn (string $key, string $expected = 'true') => ($result[$key] ?? null) === $expected;

// ---------------------------------------------------------------------------
section('Nothing is recorded without passing the confirmation');
// ---------------------------------------------------------------------------
check('the form has exactly one submit button',
    ($result['submitButtonsInForm'] ?? null) === '1',
    'found ' . ($result['submitButtonsInForm'] ?? '?') . '; a second is a way past the check');

check('it is disabled before the figures are confirmed',
    $is('startsDisabled'),
    'a disabled submit is also what stops Enter in a field recording a payment');

check('the confirmation panel starts hidden', $is('startsHidden'));

check('an incomplete form does not reach the confirmation',
    $is('refusesWhileIncomplete'),
    'no payment method chosen is not something to confirm');

// ---------------------------------------------------------------------------
section('The figures are read back');
// ---------------------------------------------------------------------------
check('pressing Received shows the confirmation instead of recording', $is('confirmShown'));
check('and hides the form behind it', $is('formHidden'));

check('the amount is shown back, formatted',
    $is('showsAmount'),
    'thousands separators are what catch a stray zero');

check('the payment method is shown back', $is('showsMethod'));
check('and the officer is told to check it against the money', $is('saysCheckTheMoney'));

// ---------------------------------------------------------------------------
section('Confirming is a deliberate act');
// ---------------------------------------------------------------------------
check('the tick box starts empty', $is('tickStartsUnticked'));

check('the confirm button is dead until it is ticked',
    $is('confirmDisabledUntilTicked'),
    'otherwise it is just a second click in the same place as the first');

check('and live once it is', $is('confirmEnabledAfterTick'));

check('going back un-ticks it again',
    $is('tickResetOnReturn') && $is('confirmDisabledAgain'),
    'a confirmation carried over from the previous look is not a confirmation');

// ---------------------------------------------------------------------------
section('Amounts that are not the balance are called out');
// ---------------------------------------------------------------------------
check('a part payment says so', $is('warnsPartPayment'));

check('an overpayment says so',
    $is('warnsOverpayment'),
    'taking more than is owed should be a decision, not a slip');

// ---------------------------------------------------------------------------
section('Nothing was recorded');
// ---------------------------------------------------------------------------
check('the fee is exactly as it was',
    abs((float) Fee::find($fee->id)->paid_amount - (float) $fee->paid_amount) < 0.005,
    'this suite must never take a payment');

echo "\n----\npassed $passed, failed $failed" . ($skipped ? ", skipped $skipped" : '') . "\n";

exit($failed > 0 ? 1 : 0);
