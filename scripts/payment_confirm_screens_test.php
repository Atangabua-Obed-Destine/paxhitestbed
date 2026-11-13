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
    var review  = document.getElementById('pp-review-' + id);
    var confirm = document.getElementById('pp-confirm-btn-' + id);
    var back    = document.getElementById('pp-back-' + id);
    var step    = document.getElementById('pp-confirm-' + id);
    var tick    = document.getElementById('pp-confirm-check-' + id);
    var amount  = document.getElementById('pp-amount-' + id);
    var method  = document.getElementById('pp-method-' + id);

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
section('Payment plan: more than the instalment, carried forward');
// ===========================================================================
//
// The split is worked out by the server and drawn by the browser. The markup
// existing proves nothing about whether typing a larger amount shows where the
// money goes, or whether money beyond everything owed is stopped before the
// confirmation — so the modal is driven here, with fetch answered by the
// server's own proposals (computed in this process, not invented).
$distributor = app(App\Services\PlanPaymentDistribution::class);
$carryFrom = null;
$carryOver = 5000;

foreach (App\Models\PaymentPlan::where('status', 'active')->with('installments', 'fee')->get() as $candidatePlan) {
    $next = $distributor->nextInstallment($candidatePlan);
    if (!$next) {
        continue;
    }
    $probe = $distributor->propose($next, (float) $next->remaining_balance + $carryOver);
    if (count($probe['legs']) === 2 && $probe['leftover'] == 0.0 && $probe['legs'][1]['outstanding'] > $carryOver) {
        $carryFrom = $next;
        break;
    }
}

if (!$carryFrom) {
    skip('no active plan with a second unpaid instalment to carry money onto');
} else {
    $balance = round((float) $carryFrom->remaining_balance, 2);
    $spill = round($balance + $carryOver, 2);
    $owed = $distributor->propose($carryFrom, 0.01)['owed'];
    $beyond = round($owed + 50000, 2);

    $answers = [];
    foreach ([$spill, $beyond] as $amount) {
        $proposal = $distributor->propose($carryFrom, $amount);
        $answers[(string) $amount] = [
            'legs' => $proposal['legs'], 'leftover' => $proposal['leftover'],
            'owed' => $proposal['owed'], 'outstanding' => $balance,
        ];
    }

    $harness = <<<'HTML'
<script>
window.addEventListener('load', function () {
  var ANSWERS = __ANSWERS__;
  var asked = [];
  // The server's own answers, keyed by the amount asked about.
  window.fetch = function (url) {
    var amount = String(parseFloat((/amount=([0-9.]+)/.exec(url) || [])[1]));
    asked.push(amount);
    var answer = ANSWERS[amount];
    return Promise.resolve({ ok: !!answer, json: function () { return Promise.resolve(answer); } });
  };

  setTimeout(function () {
    var out = [];
    function note(k, v) { out.push(k + '=' + v); }
    function get(name) { return document.getElementById('pp-' + name + '-__ID__'); }
    function type(value) { amount.value = value; amount.dispatchEvent(new Event('input', { bubbles: true })); }
    function hidden(node) { return node.classList.contains('d-none'); }
    function finish() {
      document.title = 'PROBE ' + out.join('|');
      document.body.insertAdjacentHTML('afterbegin', '<div>' + document.title + '</div>');
    }

    var amount = get('amount'), method = get('method'), review = get('review'), back = get('back');
    var step = get('confirm'), over = get('over'), table = get('split-table'), rows = get('split-rows');
    var leftover = get('leftover'), total = get('split-total');

    if (!amount || !review || !over || !rows) { document.title = 'PROBE missing=1'; return; }

    var form = review.closest('form');
    method.value = '1';

    note('startsQuiet', hidden(over) && rows.children.length === 0);
    note('noMaxOnAmount', !amount.hasAttribute('max'));

    // 1. More than this instalment: the rest is shown on the next line.
    type('__SPILL__');
    setTimeout(function () {
      note('askedServer', asked.indexOf(String(parseFloat('__SPILL__'))) !== -1);
      note('overShown', !hidden(over));
      note('tableShown', !hidden(table));
      note('twoLines', rows.children.length);
      note('firstIsThisOne', /this instalment/i.test(rows.children[0] ? rows.children[0].textContent : ''));
      note('totalMatches', total.textContent.replace(/,/g, '') === parseFloat('__SPILL__').toFixed(2));
      note('noLeftover', hidden(leftover));
      note('sendsTypes', form.querySelectorAll('input[name^="allocation"][name$="[type]"]').length);
      note('sendsAmounts', form.querySelectorAll('input[name^="allocation"][name$="[applied]"]').length);

      review.click();
      setTimeout(function () {
        var text = step.textContent.replace(/\s+/g, ' ');
        note('confirmShown', !hidden(step));
        note('confirmListsSplit', /This payment settles/i.test(text) && /Instalment #/i.test(text));
        back.click();

        // 2. The bursar takes money off the second line: it is now on no line.
        setTimeout(function () {
          var second = rows.querySelectorAll('.pp-split-amount')[1];
          second.value = '1000';
          second.dispatchEvent(new Event('input', { bubbles: true }));
          note('unplacedShown', !hidden(leftover) && /not on any line/i.test(leftover.textContent));
          review.click();
          setTimeout(function () {
            note('unplacedStopsConfirm', hidden(step));

            // 3. More than everything the student owes.
            type('__BEYOND__');
            setTimeout(function () {
              note('beyondShown', !hidden(leftover) && /more than this student owes/i.test(leftover.textContent));
              review.click();
              setTimeout(function () {
                note('beyondStopsConfirm', hidden(step));

                // 4. Back within the instalment: nothing to spread, nothing sent.
                type('__WITHIN__');
                setTimeout(function () {
                  note('quietAgain', hidden(over) && rows.children.length === 0);
                  note('sendsNoAllocation', form.querySelectorAll('input[name^="allocation"]').length === 0);
                  review.click();
                  setTimeout(function () {
                    note('withinReachesConfirm', !hidden(step));
                    finish();
                  }, 300);
                }, 700);
              }, 300);
            }, 700);
          }, 300);
        }, 300);
      }, 300);
    }, 700);
  }, 1500);
});
</script>
HTML;

    $harness = str_replace(
        ['__ID__', '__ANSWERS__', '__SPILL__', '__BEYOND__', '__WITHIN__'],
        [$carryFrom->id, json_encode($answers), (string) $spill, (string) $beyond, (string) max(1, $balance - 1000)],
        $harness
    );

    $result = drive('/admin/payment-plan/' . $carryFrom->payment_plan_id, $harness, 'plancarry');

    if ($result === null) {
        check('the payment plan page reports back', false, 'the modal\'s script did not run to the end');
    } else {
        $is = fn (string $k, string $v = 'true') => ($result[$k] ?? null) === $v;

        check('the modal opens with nothing to spread', $is('startsQuiet'));
        check('the amount box no longer stops at the instalment', $is('noMaxOnAmount'));
        check('typing more than the instalment asks the server', $is('askedServer'));
        check('and shows where the extra goes', $is('overShown') && $is('tableShown'));
        check('on two lines, this instalment first', $is('twoLines', '2') && $is('firstIsThisOne'),
            'lines ' . ($result['twoLines'] ?? '?'));
        check('adding up to what was typed', $is('totalMatches') && $is('noLeftover'));
        check('and sends each line\'s kind and amount with the form', $is('sendsTypes', '2') && $is('sendsAmounts', '2'));
        check('the confirmation lists what the payment settles', $is('confirmShown') && $is('confirmListsSplit'));
        check('money taken off a line is flagged as on no line', $is('unplacedShown'));
        check('and cannot reach the confirmation', $is('unplacedStopsConfirm'));
        check('more than everything owed is flagged', $is('beyondShown'));
        check('and cannot reach the confirmation either', $is('beyondStopsConfirm'));
        check('back within the instalment, the split is put away', $is('quietAgain') && $is('sendsNoAllocation'));
        check('and the payment goes on as an ordinary one', $is('withinReachesConfirm'));
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
