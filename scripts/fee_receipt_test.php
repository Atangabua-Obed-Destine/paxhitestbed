<?php
/**
 * The fee receipt: which payment it is for, what it says, how it verifies,
 * and that it fits on half a sheet.
 *
 * Three faults are held shut here.
 *
 *  - A fee paid through its payment plan printed the last payment made BEFORE
 *    the plan: that payment's amount, date and cashier. Plan payments were
 *    never read.
 *  - The plan vanished from the receipt the moment it was paid off, because a
 *    completed plan is unlinked from its fee.
 *  - One handover that settled several lines printed as one of them.
 *
 * And the layout promise: the receipt fits in half of an A4 sheet's printable
 * area, proved by printing it at that size in headless Chrome.
 *
 * Every payment is made inside a transaction that is rolled back.
 *
 * Usage: php scripts/fee_receipt_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\Admin\FeesStudentController;
use App\Http\Controllers\Admin\PaymentPlanController;
use App\Models\Fee;
use App\Models\PaymentPlan;
use App\Models\PaymentPlanInstallment;
use App\Models\PaymentPlanPayment;
use App\Models\PaymentReceipt;
use App\Services\FeePaymentRecorder;
use App\Services\FeeReceipt;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$passed = 0;
$failed = 0;
$skipped = 0;

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

function skip(string $why): void
{
    global $skipped;
    $skipped++;
    echo "  SKIP  $why\n";
}

app('session.store')->start();
$admin = User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first() ?: User::first();
Auth::guard('web')->login($admin);

$service = app(FeeReceipt::class);
$recorder = app(FeePaymentRecorder::class);
$fees = app(FeesStudentController::class);
$plans = app(PaymentPlanController::class);

$get = function (string $path) use ($kernel) {
    $request = Request::create($path, 'GET');
    $request->setLaravelSession(app('session.store'));
    return $kernel->handle($request);
};
$receiptOf = fn (int $feeId) => $get('/admin/fees-student-print/' . $feeId)->getContent();
// route() builds from whatever request came last; only the path is wanted.
$pathOf = function (string $url) {
    $path = parse_url($url, PHP_URL_PATH);
    $base = parse_url(config('app.url'), PHP_URL_PATH) ?: '';
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
    }
    return $path . '?' . parse_url($url, PHP_URL_QUERY);
};
$verdict = function (string $url) use ($get, $pathOf) {
    $html = $get($pathOf($url))->getContent();
    return str_contains($html, __('This receipt is genuine')) ? 'genuine'
        : (str_contains($html, __('This receipt could not be verified')) ? 'refused' : 'unknown');
};

/** An unpaid first instalment whose student owes another instalment fee. */
$first = Fee::with(['category', 'studentEnroll.student'])
    ->whereHas('studentEnroll.student')
    ->whereHas('category', fn ($q) => $q->where('is_first_installment', 1))
    ->where('paid_amount', 0)->where('status', '0')->whereNull('payment_plan_id')
    ->orderByDesc('id')->get()
    ->first(fn (Fee $fee) => $recorder->outstanding($fee) >= 60000
        && app(\App\Services\FeePaymentDistribution::class)->targets($fee)->count() > 1);

echo "\n== Ground ==\n";
check('plan payments can carry a handover group', Schema::hasColumn('payment_plan_payments', 'payment_group'));
check('an unpaid first instalment with another instalment fee behind it', $first !== null);
if (!$first) {
    echo "\n$passed passed, $failed failed\n";
    exit(1);
}
$second = app(\App\Services\FeePaymentDistribution::class)->targets($first)->slice(1)->first();
$due = $recorder->outstanding($first);

/** Pay part of the fee directly, then put the rest on a three-instalment plan. */
$setUp = function () use ($first, $fees, $due, $admin): PaymentPlan {
    $fees->pay(Request::create('/x', 'POST', [
        'fee_id' => $first->id, 'paid_amount' => 30000, 'pay_date' => now()->subDays(3)->toDateString(),
        'payment_method' => '2', 'note' => 'ZZ receipt suite, before the plan',
    ]));
    // A receipt written now, then aged, so the plan payment that follows is
    // unmistakably the later of the two.
    PaymentReceipt::where('fee_id', $first->id)->update(['created_at' => now()->subDays(3)]);

    $rest = round($due - 30000, 2);
    $a = round($rest / 3, 2);
    $plan = PaymentPlan::create([
        'student_id' => $first->studentEnroll->student_id, 'fee_id' => $first->id, 'total_amount' => $rest,
        'installments_count' => 3, 'late_fee_percentage' => 0, 'grace_period_days' => 7,
        'created_by' => $admin->id, 'approved_by' => $admin->id, 'approved_at' => now(),
        'status' => 'active', 'notes' => 'ZZ receipt suite',
    ]);
    foreach ([$a, $a, round($rest - 2 * $a, 2)] as $i => $amount) {
        PaymentPlanInstallment::create([
            'payment_plan_id' => $plan->id, 'installment_number' => $i + 1, 'amount' => $amount,
            'due_date' => now()->addDays(30 * ($i + 1))->toDateString(), 'status' => 'pending',
        ]);
    }
    Fee::whereKey($first->id)->update(['payment_plan_id' => $plan->id]);

    return $plan->fresh('installments');
};
$payPlan = fn (PaymentPlan $plan, int $number, float $amount) => app(PaymentPlanController::class)->processPayment(Request::create('/x', 'POST', [
    'payment_plan_id' => $plan->id,
    'installment_id' => $plan->installments->firstWhere('installment_number', $number)->id,
    'amount' => $amount, 'payment_method' => 3, 'payment_date' => date('Y-m-d'), 'note' => 'ZZ receipt suite',
]));

echo "\n== A fee paid through its plan shows the plan payment ==\n";

DB::beginTransaction();
try {
    $plan = $setUp();
    $one = $plan->installments->firstWhere('installment_number', 1);
    $payPlan($plan, 1, (float) $one->amount);

    $h = $service->handoverFor(Fee::find($first->id));
    check('the later payment wins: the plan payment, not the receipt before the plan', $h && $h['kind'] === 'plan');
    check('for the amount the plan payment was', $h && abs($h['total'] - (float) $one->amount) < 0.005);
    check('dated today, not the day of the earlier payment', $h && $h['date']->toDateString() === date('Y-m-d'));
    check('named by the cashier who took it', $h && $h['cashier'] === $admin->name, (string) ($h['cashier'] ?? ''));

    $html = $receiptOf($first->id);
    check('the receipt prints that amount', str_contains($html, number_format((float) $one->amount, 2)));
    check('and not the 30,000 paid before the plan as the amount received',
        !preg_match('/class="v">[^<]*30,000\.00/', $html));
    check('it says the fee is on a payment plan', str_contains($html, 'class="tag plan"') && str_contains($html, __('status_active')));
    check('with the plan laid out under the fee', substr_count($html, 'class="sub"') === 3);
    check('a single payment is not listed as several', !str_contains($html, 'class="settled-line"'));
} finally {
    DB::rollBack();
}

echo "\n== One handover across the plan and onto the next fee ==\n";

DB::beginTransaction();
try {
    $plan = $setUp();
    $rest = (float) $plan->total_amount;
    $carry = min(10000, $recorder->outstanding($second));
    $handed = round($rest + $carry, 2);
    $payPlan($plan, 1, $handed);

    $planRows = PaymentPlanPayment::whereIn('installment_id', $plan->installments->pluck('id'))->get();
    $carried = PaymentReceipt::where('fee_id', $second->id)->orderByDesc('id')->first();
    $groups = $planRows->pluck('payment_group')->push(optional($carried)->payment_group)->unique();

    check('every row the handover wrote carries one group', $groups->count() === 1 && $groups->first() !== null,
        $groups->implode(', '));

    $h = $service->handoverFor(Fee::find($first->id));
    check('the plan fee\'s receipt is for the whole handover', $h && abs($h['total'] - $handed) < 0.005,
        ($h['total'] ?? '?') . ' vs ' . $handed);
    check('listing three instalments and the carried fee', $h && $h['legs']->count() === 4
        && $h['legs']->where('kind', 'plan')->count() === 3 && $h['legs']->where('kind', 'receipt')->count() === 1);
    check('the plan\'s instalments first, in order', $h && $h['legs']->take(3)->pluck('label')->every(fn ($l) => str_contains($l, 'plan instalment')));
    check('what landed on this fee is the plan\'s part', abs($service->onFee($h, $first->id) - $rest) < 0.005);

    $html = $receiptOf($first->id);
    check('the receipt prints the whole handover', str_contains($html, number_format($handed, 2)));
    check('and lists what it settled', str_contains($html, 'class="settled-line"') && substr_count($html, 'class="leg"') === 4);
    check('naming the fee the rest went to', str_contains($html, e(optional($second->category)->title)));

    $plan->refresh();
    check('the plan is completed and the fee unlinked from it', $plan->status === 'completed' && Fee::find($first->id)->payment_plan_id === null);
    check('yet the receipt still says it was paid by plan', str_contains($html, 'class="tag plan"') && str_contains($html, __('status_completed')));
    check('and still lays the plan out', substr_count($html, 'class="sub"') === 3);

    // The carried fee's own receipt is for the same handover.
    $other = $service->handoverFor(Fee::find($second->id));
    check('the next fee\'s receipt is for the same handover', $other && $other['kind'] === 'receipt'
        && abs($other['total'] - $handed) < 0.005 && $other['legs']->count() === 4);
    check('and carries the same code, since it describes the same money', $other && $other['code'] === $h['code']);
    check('but no plan tag: that fee is not on a plan', !str_contains($receiptOf($second->id), 'class="tag plan"'));

    echo "\n== Checking the handover publicly ==\n";
    check('the address names a handover, not a single receipt', str_contains($h['url'], '/verify-payment/plan/'));
    check('it verifies', $verdict($h['url']) === 'genuine');
    check('from the next fee\'s receipt too', str_contains($other['url'], '/verify-payment/receipt/') && $verdict($other['url']) === 'genuine');
    check('a wrong code is refused', $verdict(preg_replace('/code=\w+/', 'code=AAAAAAAAAAAAAAAA', $h['url'])) === 'refused');

    $page = $get($pathOf($h['url']))->getContent();
    check('the check lists every line', substr_count($page, '&nbsp;&nbsp;') === 4);
    check('and the whole amount', str_contains($page, number_format($handed, 2)));

    // Change one line after printing: the sheet must stop verifying.
    PaymentPlanPayment::whereKey($planRows->first()->id)->update(['amount' => DB::raw('amount - 1')]);
    check('a sheet whose figures no longer match the records is refused', $verdict($h['url']) === 'refused');

    $reversed = $planRows->last();
    PaymentPlanPayment::whereKey($reversed->id)->update(['status' => 'reversed']);
    check('a reversed payment cannot be looked up', $get('/verify-payment/plan/' . $reversed->id . '?code=X')->getStatusCode() === 404);
} finally {
    DB::rollBack();
}

echo "\n== Receipts printed before this change still verify ==\n";

$old = PaymentReceipt::where('verification_status', 'approved')->whereNull('payment_group')
    ->whereHas('fee.studentEnroll.student')->orderByDesc('id')->first();
if ($old) {
    $h = $service->handoverFromReceipt($old);
    check('a single receipt keeps its old code', $h['code'] === $service->verificationCodeFor($old));
    check('and its old address', str_contains($h['url'], '/verify-fee/' . $old->id . '?'));
    check('which still says genuine', $verdict($h['url']) === 'genuine');
} else {
    skip('no single approved receipt to check');
}

echo "\n== What is never shown as the payment ==\n";

DB::beginTransaction();
try {
    $plan = $setUp();
    $payPlan($plan, 1, 5000);
    $paid = PaymentPlanPayment::whereIn('installment_id', $plan->installments->pluck('id'))->first();

    PaymentPlanPayment::whereKey($paid->id)->update(['status' => 'reversed']);
    $h = $service->handoverFor(Fee::find($first->id));
    check('a reversed plan payment gives way to the payment before it', $h && $h['kind'] === 'receipt');

    PaymentReceipt::create([
        'fee_id' => $first->id, 'student_id' => $first->studentEnroll->student_id,
        'payment_reference' => 'ZZ-PENDING', 'payment_date' => date('Y-m-d'), 'amount' => 99999,
        'payment_method' => 2, 'verification_status' => 'pending', 'student_note' => 'ZZ receipt suite',
    ]);
    $h = $service->handoverFor(Fee::find($first->id));
    check('a student\'s upload still under review is not the payment', $h && $h['reference'] !== 'ZZ-PENDING');

    $plan->update(['status' => 'cancelled']);
    check('a cancelled plan is not shown', !str_contains($receiptOf($first->id), 'class="tag plan"'));
} finally {
    DB::rollBack();
}

echo "\n== The layout ==\n";

DB::beginTransaction();
try {
    $fees->pay(Request::create('/x', 'POST', [
        'fee_id' => $first->id, 'paid_amount' => 20000, 'pay_date' => date('Y-m-d'), 'payment_method' => '2',
    ]));
    $html = $receiptOf($first->id);

    // Once in the amount box. (20,000 also appears, rightly, as what this
    // fee has been paid in all: it had nothing paid before.)
    check('the amount received is printed once, not in figures, words and a box',
        substr_count($html, '<span class="v">') === 1 && !str_contains($html, e(__('Amount in Figures'))),
        substr_count($html, '<span class="v">') . ' amount figures');
    check('spelled out beside it', preg_match('/class="words">\s*Twenty thousand/i', $html) === 1);
    check('no "This fee" box restating the instalment row', !str_contains($html, '<caption>' . __('This fee')));
    check('this fee is the bold row of the instalments', str_contains($html, ' here"'));
    check('the old underlined fields are gone', !str_contains($html, 'class="field"'));
    check('the letterhead is the configured one, at receipt scale', str_contains($html, 'class="masthead"') && str_contains($html, 'doc-masthead'));
} finally {
    DB::rollBack();
}

$admission = Fee::whereHas('category', fn ($q) => $q->where('is_admission', 1))
    ->where('status', '1')->whereHas('studentEnroll.student')->first();
if ($admission) {
    $html = $receiptOf($admission->id);
    check('a fee that is not an instalment gets one line of its own', str_contains($html, '<caption>' . __('This fee')));
} else {
    skip('no paid admission fee to render');
}

echo "\n== On paper ==\n";

$chrome = collect([
    'C:\Program Files\Google\Chrome\Application\chrome.exe',
    'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
    '/usr/bin/google-chrome', '/usr/bin/chromium',
])->first(fn ($path) => is_file($path));

if (!$chrome) {
    skip('needs headless Chrome to print the receipt');
} else {
    $base = rtrim(config('app.url'), '/');
    $made = [];

    /** Print a page at half of an A4 sheet's printable height; count pages. */
    $pages = function (string $html, string $tag) use ($chrome, $base, &$made) {
        $html = str_replace(['"http://localhost/', "'http://localhost/"], ['"' . $base . '/', "'" . $base . '/'], $html);
        $half = str_replace('@page{size:A4;margin:10mm}', '@page{size:210mm 148.5mm;margin:5mm 10mm}', $html);
        if ($half === $html) {
            return -1;
        }
        $name = '_receipt_' . $tag . '_' . getmypid();
        $page = public_path($name . '.html');
        $pdf = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $name . '.pdf';
        file_put_contents($page, $half);
        $made[] = $page;
        $made[] = $pdf;
        exec(escapeshellarg($chrome) . ' --headless=new --disable-gpu --no-pdf-header-footer --virtual-time-budget=6000'
            . ' --print-to-pdf=' . escapeshellarg($pdf) . ' ' . escapeshellarg($base . '/' . $name . '.html') . ' 2>&1');
        return is_file($pdf) ? preg_match_all('~/Type\s*/Page[^s]~', file_get_contents($pdf)) : 0;
    };

    DB::beginTransaction();
    try {
        $plan = $setUp();
        $payPlan($plan, 1, round((float) $plan->total_amount + min(10000, $recorder->outstanding($second)), 2));
        $withPlan = $receiptOf($first->id);
    } finally {
        DB::rollBack();
    }

    $n = $pages($withPlan, 'plan');
    check('a receipt with a plan and a four-line handover fits on half a sheet', $n === 1, $n . ' pages');

    if ($admission) {
        $n = $pages($receiptOf($admission->id), 'plain');
        check('so does a plain one', $n === 1, $n . ' pages');
    }

    foreach ($made as $file) {
        @unlink($file);
    }
}

echo "\n== The student's own copy ==\n";

$own = Fee::whereIn('status', ['1', '2'])->whereHas('studentEnroll.student')->orderByDesc('id')->first();
if ($own) {
    Auth::guard('student')->login($own->studentEnroll->student);
    $view = app(\App\Http\Controllers\Student\FeesController::class)->print($own->id);
    $data = $view->getData();
    check('the portal renders the same receipt view', $view->name() === 'admin.fees-student.print');
    check('from the same handover the office sees', ($data['handover']['code'] ?? null) === ($service->handoverFor($own)['code'] ?? null));
    Auth::guard('student')->logout();
} else {
    skip('no paid fee to render as its student');
}

echo "\n== Nothing left behind ==\n";
check('no suite plan survived', PaymentPlan::where('notes', 'ZZ receipt suite')->doesntExist());
check('no suite receipt survived', PaymentReceipt::where('payment_reference', 'ZZ-PENDING')->doesntExist());
check('the fee is as it was', (float) Fee::find($first->id)->paid_amount == 0.0);
check('no printed pages left in public/', glob(public_path('_receipt_*')) === []);
check('no transaction left open', DB::transactionLevel() === 0);

echo "\n$passed passed, $failed failed, $skipped skipped\n";
exit($failed === 0 ? 0 : 1);
