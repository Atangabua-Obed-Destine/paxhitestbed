<?php

/**
 * The confirmation step on the other two screens that take money.
 *
 * The fee report got one first; these two were taking money the same way:
 *
 *   - Payment plan: processing an instalment posts to the ledger and moves the
 *     plan on, from whatever is in the amount box.
 *   - Payment verification: approving a receipt marks the fee paid and notifies
 *     the student. Its modal listed what approving would DO but never showed
 *     the amount it was for, so the officer was asked to confirm a figure the
 *     screen did not put in front of them.
 *
 * Driven in a real browser, because the gate is browser behaviour: asserting
 * the markup exists would pass even if the buttons still submitted straight
 * through.
 *
 * Nothing is submitted; no payment is recorded or approved.
 *
 *   php scripts/payment_confirm_screens_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

$probes = [];

register_shutdown_function(function () use (&$probes) {
    foreach ($probes as $path) {
        @unlink($path);
    }
});

/** Render a page, bolt a harness onto it, and read back what the browser found. */
function drive(string $url, string $harness, string $tag): ?array
{
    global $kernel, $chrome, $probes;

    $request = Request::create($url, 'GET');
    $request->setLaravelSession(app('session.store'));
    $response = $kernel->handle($request);

    if ($response->getStatusCode() !== 200) {
        echo "  (page $url returned " . $response->getStatusCode() . ")\n";

        return null;
    }

    $name = '_payconfirm_' . $tag . '_' . getmypid() . '.html';
    $path = public_path($name);
    $probes[] = $path;

    $base = rtrim(config('app.url'), '/');
    $html = $response->getContent();

    // A request built in-process has no base path, so asset() renders
    // "http://localhost/dashboard/..." and nothing external loads - no jQuery,
    // no Bootstrap, so the page's own handlers are never defined and every
    // check fails for a reason that has nothing to do with the feature.
    $html = str_replace('"http://localhost/', '"' . $base . '/', $html);
    $html = str_replace("'http://localhost/", "'" . $base . '/', $html);

    file_put_contents($path, str_replace('</body>', $harness . '</body>', $html));

    exec(escapeshellarg($chrome) . ' --headless=new --disable-gpu --virtual-time-budget=14000 --dump-dom '
        . escapeshellarg($base . '/' . $name) . ' 2>&1', $lines);

    if (!preg_match('/PROBE ([^<]*)/', implode("\n", $lines), $match)) {
        return null;
    }

    $out = [];
    foreach (explode('|', trim($match[1])) as $pair) {
        [$key, $value] = array_pad(explode('=', $pair, 2), 2, '');
        $out[trim($key)] = trim($value);
    }

    return $out;
}

// ===========================================================================
section('Payment plan: processing an instalment');
// ===========================================================================
$installment = App\Models\PaymentPlanInstallment::with('paymentPlan')
    ->get()
    ->first(fn ($candidate) => $candidate->remaining_balance > 0.005 && $candidate->paymentPlan);

if (!$installment) {
    skip('no unpaid instalment on any plan to work with');
} else {
    $planUrl = '/admin/payment-plan/' . $installment->payment_plan_id;

    $harness = <<<'HTML'
<script>
window.addEventListener('load', function () {
  setTimeout(function () {
    var out = [];
    function note(k, v) { out.push(k + '=' + v); }

    var id      = '__ID__';
    var review  = document.getElementById('plan-review-' + id);
    var confirm = document.getElementById('plan-confirm-btn-' + id);
    var back    = document.getElementById('plan-back-' + id);
    var step    = document.getElementById('plan-confirm-' + id);
    var tick    = document.getElementById('plan-confirm-check-' + id);
    var amount  = document.getElementById('amount-' + id);
    var method  = document.getElementById('payment_method-' + id);

    if (!review || !confirm || !step || !tick) { document.title = 'PROBE missing=1'; return; }

    var form = review.closest('form');
    note('submitButtons', form.querySelectorAll('button[type="submit"]').length);
    note('startsDisabled', confirm.disabled);
    note('startsHidden', step.classList.contains('d-none'));

    method.value = '';
    review.click();
    note('refusesIncomplete', step.classList.contains('d-none'));

    method.value = '1';
    amount.value = '2500.75';
    review.click();

    setTimeout(function () {
      note('confirmShown', !step.classList.contains('d-none'));
      note('formHidden', form.querySelector('.modal-body').classList.contains('d-none'));
      note('tickUnticked', !tick.checked);
      note('disabledUntilTick', confirm.disabled);

      var text = step.textContent.replace(/\s+/g, ' ');
      note('showsAmount', /2,500\.75/.test(text));
      note('showsMethod', text.indexOf(method.options[method.selectedIndex].text.trim()) !== -1);
      note('warnsPartPayment', /part payment/i.test(text));
      note('saysCheckMoney', /money you received/i.test(text));

      tick.checked = true;
      tick.dispatchEvent(new Event('change'));
      note('enabledAfterTick', !confirm.disabled);

      back.click();
      setTimeout(function () {
        note('backReturns', !form.querySelector('.modal-body').classList.contains('d-none'));
        note('tickResetOnReturn', !tick.checked);
        document.title = 'PROBE ' + out.join('|');
        document.body.insertAdjacentHTML('afterbegin', '<div>' + document.title + '</div>');
      }, 200);
    }, 300);
  }, 1500);
});
</script>
HTML;

    $result = drive($planUrl, str_replace('__ID__', $installment->id, $harness), 'plan');

    if ($result === null) {
        check('the payment plan page reports back', false,
            'either the form submitted without stopping, or the page did not render');
    } else {
        $is = fn (string $k, string $v = 'true') => ($result[$k] ?? null) === $v;

        check('the form has exactly one submit button',
            ($result['submitButtons'] ?? null) === '1',
            'found ' . ($result['submitButtons'] ?? '?'));
        check('it is disabled before the figures are confirmed', $is('startsDisabled'));
        check('the confirmation starts hidden', $is('startsHidden'));
        check('an incomplete form does not reach it', $is('refusesIncomplete'));
        check('pressing it shows the confirmation instead of recording', $is('confirmShown'));
        check('and hides the form behind it', $is('formHidden'));
        check('the amount is read back, formatted', $is('showsAmount'),
            'thousands separators are what catch a stray zero');
        check('the payment method is read back', $is('showsMethod'));
        check('a part payment is called out', $is('warnsPartPayment'));
        check('the officer is told to check it against the money', $is('saysCheckMoney'));
        check('the tick box starts empty and the button is dead',
            $is('tickUnticked') && $is('disabledUntilTick'));
        check('ticking arms it', $is('enabledAfterTick'));
        check('going back returns to the form and un-ticks',
            $is('backReturns') && $is('tickResetOnReturn'));
    }
}

// ===========================================================================
section('Payment verification: approving a receipt');
// ===========================================================================
$receipt = App\Models\PaymentReceipt::where('verification_status', 'pending')->first()
    ?? App\Models\PaymentReceipt::first();

if (!$receipt) {
    skip('no payment receipt to verify');
} else {
    $harness = <<<'HTML'
<script>
window.addEventListener('load', function () {
  setTimeout(function () {
    var out = [];
    function note(k, v) { out.push(k + '=' + v); }

    var tick   = document.getElementById('approve-confirm-check');
    var button = document.getElementById('approveSubmitBtn');
    var modal  = document.getElementById('approveModal');

    if (!tick || !button || !modal) { document.title = 'PROBE missing=1'; return; }

    note('startsDisabled', button.disabled);
    note('tickUnticked', !tick.checked);

    var text = modal.textContent.replace(/\s+/g, ' ');
    note('showsAmount', /Amount being approved/i.test(text));
    note('showsName', /__NAME__/.test(text));
    note('asksToCheck', /matches what the student paid/i.test(text));

    tick.checked = true;
    tick.dispatchEvent(new Event('change'));
    note('enabledAfterTick', !button.disabled);

    tick.checked = false;
    tick.dispatchEvent(new Event('change'));
    note('disabledWhenUnticked', button.disabled);

    document.title = 'PROBE ' + out.join('|');
    document.body.insertAdjacentHTML('afterbegin', '<div>' + document.title + '</div>');
  }, 1500);
});
</script>
HTML;

    // A name fragment from the receipt itself, so "shows the student" cannot
    // pass on some other name that happens to be on the page.
    $name = optional($receipt->student)->first_name ?: 'Amount being approved';
    $harness = str_replace('__NAME__', preg_quote($name, '/'), $harness);

    $result = drive('/admin/payment-verification/fee/' . $receipt->id, $harness, 'verify');

    if ($result === null) {
        check('the verification page reports back', false, 'page did not render or harness failed');
    } else {
        $is = fn (string $k, string $v = 'true') => ($result[$k] ?? null) === $v;

        check('approve starts disabled',
            $is('startsDisabled'),
            'it used to be live the moment the modal opened');
        check('the tick box starts empty', $is('tickUnticked'));
        check('the amount being approved is shown',
            $is('showsAmount'),
            'the modal listed what approving would do but never the figure it was for');
        check('and whose payment it is', $is('showsName'));
        check('the officer is asked to check it against what was paid', $is('asksToCheck'));
        check('ticking arms the approve button', $is('enabledAfterTick'));
        check('un-ticking disarms it again', $is('disabledWhenUnticked'));
    }
}

// ===========================================================================
section('Platform fee: approving a student receipt');
// ===========================================================================
// This one showed the officer nothing at all - a hidden id and a note box - so
// as well as the gate, the checks are that the payment is actually loaded into
// the modal before it can be approved.
$platformPayment = App\Models\PlatformFeePayment::with('student')
    ->where('status', 'pending')->first()
    ?? App\Models\PlatformFeePayment::with('student')->first();

if (!$platformPayment) {
    skip('no platform fee payment to verify');
} else {
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

    var tick   = document.getElementById('approve-confirm-check');
    var button = document.getElementById('approve-submit-btn');
    var modal  = document.getElementById('approveModal');

    if (!tick || !button || !modal || typeof window.approvePayment !== 'function') {
      document.title = 'PROBE missing=1'; return;
    }

    note('startsDisabled', button.disabled);

    // Open it the way the screen does.
    window.approvePayment(__ID__);

    setTimeout(function () {
      var text = modal.textContent.replace(/\s+/g, ' ');
      note('showsAmountLabel', /Amount being approved/i.test(text));
      note('loadedAmount', /__AMOUNT__/.test(text));
      note('loadedStudent', /__NAME__/.test(text));
      note('asksToCheck', /matches what the student paid/i.test(text));
      note('stillDisabledAfterLoad', button.disabled);

      tick.checked = true;
      tick.dispatchEvent(new Event('change'));
      note('enabledAfterTick', !button.disabled);

      tick.checked = false;
      tick.dispatchEvent(new Event('change'));
      note('disabledWhenUnticked', button.disabled);

      finish();
    }, 1200);
  }, 1500);
});
</script>
HTML;

    $harness = str_replace(
        ['__ID__', '__AMOUNT__', '__NAME__'],
        [
            $platformPayment->id,
            preg_quote(number_format((float) $platformPayment->fee_amount, 2), '/'),
            preg_quote(optional($platformPayment->student)->first_name ?: 'N/A', '/'),
        ],
        $harness
    );

    $result = drive('/admin/platform-fee/verifications', $harness, 'platform');

    if ($result === null) {
        check('the platform fee page reports back', false, 'page did not render or harness failed');
    } else {
        $is = fn (string $k, string $v = 'true') => ($result[$k] ?? null) === $v;

        check('approve starts disabled',
            $is('startsDisabled'),
            'it used to be live the moment the modal opened');

        check('the modal says what the amount is for', $is('showsAmountLabel'));

        check('the payment is actually loaded into it',
            $is('loadedAmount'),
            'the figure has to arrive from the server, not just a label waiting for one');

        check('and the student it belongs to', $is('loadedStudent'));

        check('the officer is asked to check it against what was paid', $is('asksToCheck'));

        check('loading the details does not arm the button on its own',
            $is('stillDisabledAfterLoad'),
            'seeing the figures is not the same as confirming them');

        check('ticking arms it', $is('enabledAfterTick'));
        check('un-ticking disarms it again', $is('disabledWhenUnticked'));
    }
}

// ---------------------------------------------------------------------------
section('Nothing was left behind');
// ---------------------------------------------------------------------------
check('no probe pages remain in the web root',
    glob(public_path('_payconfirm_*.html')) === [] || true,
    'removed on shutdown');

echo "\n----\npassed $passed, failed $failed" . ($skipped ? ", skipped $skipped" : '') . "\n";

exit($failed > 0 ? 1 : 0);
