<?php
/**
 * Card is retired as a fee payment method: forms no longer offer it, anything
 * sent as 1 is saved as cash, and the migration moves stored 1s to 2 (and
 * plan 5s to 1) without touching the ledger. Rolled back.
 *
 *   php scripts/fee_card_to_cash_test.php
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Support\FeesPaymentMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

$pass = 0; $fail = 0;
function check($label, $ok, $detail = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ok    $label\n"; }
    else { $fail++; echo "  FAIL  $label" . ($detail !== '' ? "   [$detail]" : '') . "\n"; }
}

check('the methods list has no Card and one Cash', !array_key_exists(1, FeesPaymentMethod::options())
    && count(array_keys(FeesPaymentMethod::options(), __('payment_method_cash'), true)) === 1, json_encode(FeesPaymentMethod::options()));
check('a 1 sent by an old form is saved as cash', FeesPaymentMethod::normalize('1') === 2 && FeesPaymentMethod::normalize(1) === 2);
check('other methods pass through unchanged', FeesPaymentMethod::normalize('4') === '4' && FeesPaymentMethod::normalize(8) === 8 && FeesPaymentMethod::normalize(null) === null);
check('a stray stored 1 still reads Cash', FeesPaymentMethod::label(1) === __('payment_method_cash'));
check('a plan card payment maps to fee cash', FeesPaymentMethod::fromPlan(5) === 2);

foreach (['admin/fees-student/pay', 'admin/fees-student/quick-received', 'student/manual-payment/create', 'admin/payroll/pay', 'admin/payroll/index', 'admin/item-stock/create', 'admin/item-stock/edit', 'admin/payment-account/reports/cashflow'] as $v) {
    $src = file_get_contents(resource_path('views/' . $v . '.blade.php'));
    check("$v offers no Card option", !preg_match('/<option value="1"[^>]*>\s*\{\{ __\(.payment_method_card.\) \}\}/', $src));
}

$migration = require database_path('migrations/2026_11_25_300000_fee_card_payments_to_cash.php');
$log = 'migrations/2026_11_25_fee_card_payments_to_cash.json';
$hadLog = Storage::exists($log) ? Storage::get($log) : null;

DB::beginTransaction();
try {
    // a fixture of each kind, so the test has teeth on any database
    $feeId = DB::table('fees')->whereNotNull('pay_date')->where('paid_amount', '>', 0)->value('id');
    DB::table('fees')->where('id', $feeId)->update(['payment_method' => 1]);
    $receiptId = DB::table('payment_receipts')->value('id');
    if ($receiptId) DB::table('payment_receipts')->where('id', $receiptId)->update(['payment_method' => 1]);

    $before = [
        'fees1' => DB::table('fees')->where('payment_method', 1)->count(),
        'fees2' => DB::table('fees')->where('payment_method', 2)->count(),
        'rec1' => DB::table('payment_receipts')->where('payment_method', 1)->count(),
        'rec2' => DB::table('payment_receipts')->where('payment_method', 2)->count(),
        'other' => DB::table('fees')->whereNotIn('payment_method', [1, 2])->count(),
        'paid' => (float) DB::table('fees')->sum('paid_amount'),
        'lines' => DB::table('journal_entry_lines')->selectRaw('count(*) n, sum(debit) d')->first(),
    ];
    $cashBefore = app(App\Services\Accounting\CashAccounts::class)->forSource('fee', $feeId);

    $migration->up();

    check('no fee is left as Card', DB::table('fees')->where('payment_method', 1)->count() === 0);
    check('every Card fee became Cash', DB::table('fees')->where('payment_method', 2)->count() === $before['fees2'] + $before['fees1'], json_encode($before));
    check('no receipt is left as Card', DB::table('payment_receipts')->where('payment_method', 1)->count() === 0);
    check('every Card receipt became Cash', DB::table('payment_receipts')->where('payment_method', 2)->count() === $before['rec2'] + $before['rec1']);
    check('no other method moved', DB::table('fees')->whereNotIn('payment_method', [1, 2])->count() === $before['other']);
    check('no amount changed', abs((float) DB::table('fees')->sum('paid_amount') - $before['paid']) < 0.001);
    $after = DB::table('journal_entry_lines')->selectRaw('count(*) n, sum(debit) d')->first();
    check('the ledger is untouched', $after->n == $before['lines']->n && (float) $after->d === (float) $before['lines']->d);
    check('the fee still posts to the same cash account', app(App\Services\Accounting\CashAccounts::class)->forSource('fee', $feeId) === $cashBefore, var_export($cashBefore, true));
    $logged = json_decode(Storage::get($log), true);
    check('the changed ids are logged for undo', count($logged['changes']['fees']['ids'] ?? []) === $before['fees1']);

    $migration->down();
    check('undo puts back exactly the Card fees', DB::table('fees')->where('payment_method', 1)->count() === $before['fees1']
        && DB::table('fees')->where('payment_method', 2)->count() === $before['fees2']);
    check('undo puts back exactly the Card receipts', DB::table('payment_receipts')->where('payment_method', 1)->count() === $before['rec1']);
} catch (\Throwable $e) {
    $fail++;
    echo '  FAIL  exception: ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
} finally {
    DB::rollBack();
    $hadLog === null ? Storage::delete($log) : Storage::put($log, $hadLog);
}
echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
