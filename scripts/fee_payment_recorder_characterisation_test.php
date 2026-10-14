<?php
/**
 * What one ordinary fee payment writes.
 *
 * This suite exists to guard a refactor. `FeesStudentController::pay()` is a
 * single long method that writes a fee, a student transaction, a payment
 * receipt, a payment-account transaction, a journal posting and sometimes a
 * credit. Splitting a payment across instalments means lifting that sequence
 * into a service both paths call — and the one failure that would really matter
 * is a quiet change to how a NORMAL payment is recorded.
 *
 * So this is written against the behaviour as it stands BEFORE the refactor, and
 * must still pass, unchanged, after it. Every expectation here is reasoned from
 * what a payment means, not copied from the code, except the fingerprint — which
 * is a deliberate byte-for-byte capture for diffing.
 *
 * Run:  php scripts/fee_payment_recorder_characterisation_test.php
 * Capture a baseline to diff against later:
 *       php scripts/fee_payment_recorder_characterisation_test.php --capture <file>
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Fee;
use Illuminate\Support\Facades\DB;

$capturePath = null;

foreach ($argv as $i => $arg) {
    if ($arg === '--capture') {
        $capturePath = $argv[$i + 1] ?? null;
    }
}

$passed = 0;
$failed = 0;
$skipped = 0;

function section(string $title): void
{
    echo "\n" . $title . "\n" . str_repeat('-', strlen($title)) . "\n";
}

function check(string $what, bool $ok, string $why = ''): void
{
    global $passed, $failed;

    if ($ok) {
        $passed++;
        echo "  PASS  " . $what . "\n";
    } else {
        $failed++;
        echo "  FAIL  " . $what . ($why ? "\n        " . $why : '') . "\n";
    }
}

function skip(string $what): void
{
    global $skipped;
    $skipped++;
    echo "  SKIP  " . $what . "\n";
}

// ---------------------------------------------------------------------------
// The machinery: take a payment through the real route and photograph every
// row it touched.
// ---------------------------------------------------------------------------

/** Tables a payment is known to write. Photographed whole, before and after. */
const WATCHED = [
    'transactions',
    'payment_receipts',
    'payment_account_transactions',
    'student_credits',
    'credit_applications',
    'journal_entries',
];

function photograph(int $feeId): array
{
    $shot = ['fee' => (array) DB::table('fees')->where('id', $feeId)->first()];

    foreach (WATCHED as $table) {
        $shot[$table] = DB::table($table)->count();
    }

    return $shot;
}

/**
 * One row with everything that differs between two identical runs blanked out,
 * so the fingerprint compares equal run to run. If this list is wrong the whole
 * capture is worthless, so it was derived by running the suite twice and
 * diffing, not by guessing.
 */
function normalise(array $row): array
{
    $volatile = [
        'id', 'created_at', 'updated_at', 'verified_at', 'posted_at',
        'transaction_id', 'payment_reference', 'uuid', 'reference_no', 'entry_no',
    ];

    foreach ($volatile as $column) {
        if (array_key_exists($column, $row)) {
            $row[$column] = '<varies>';
        }
    }

    // The fee's note carries the new credit's auto-increment id.
    if (isset($row['note']) && is_string($row['note'])) {
        $row['note'] = preg_replace('/Credit ID: \d+/', 'Credit ID: <varies>', $row['note']);
    }

    ksort($row);

    return $row;
}

/** The rows a table GAINED, normalised. */
function newRows(string $table, array $idsBefore): array
{
    return DB::table($table)->whereNotIn('id', $idsBefore ?: [0])->get()
        ->map(fn ($row) => normalise((array) $row))
        ->values()->all();
}

$admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();
app('session.store')->start();

if (!$admin) {
    echo "No Super Admin to act as.\n";
    exit(1);
}

Illuminate\Support\Facades\Auth::guard('web')->login($admin);

$post = function (array $data) use ($kernel) {
    $request = Illuminate\Http\Request::create('/admin/fees-student-pay', 'POST',
        array_merge($data, ['_token' => csrf_token()]));
    $request->setLaravelSession(app('session.store'));

    return $kernel->handle($request);
};

/**
 * One payment, inside a transaction that is rolled back, returning everything it
 * wrote. The caller never sees the database change.
 */
function takePayment(callable $post, Fee $subject, array $data): array
{
    $idsBefore = [];

    DB::beginTransaction();

    try {
        foreach (WATCHED as $table) {
            $idsBefore[$table] = DB::table($table)->pluck('id')->all();
        }

        $response = $post(array_merge(['fee_id' => $subject->id], $data));

        $written = ['status' => $response->getStatusCode()];
        $written['fee'] = normalise((array) DB::table('fees')->where('id', $subject->id)->first());

        foreach (WATCHED as $table) {
            $written[$table] = newRows($table, $idsBefore[$table]);
        }

        return $written;
    } finally {
        DB::rollBack();
    }
}

// A fee nothing has been paid against, so the arithmetic is unambiguous.
$subject = Fee::with('category')
    ->where(function ($q) { $q->whereNull('paid_amount')->orWhere('paid_amount', 0); })
    ->whereNull('payment_plan_id')
    ->whereDoesntHave('resitRequest')
    ->whereDoesntHave('paymentReceipts')
    ->whereHas('studentEnroll.student')
    ->where('fee_amount', '>', 0)
    ->orderBy('id')
    ->first();

if (!$subject) {
    echo "No untouched fee to take a payment against.\n";
    exit(1);
}

echo "Subject: fee #{$subject->id}, {$subject->category->title}, "
    . number_format($subject->fee_amount, 2) . ", nothing paid\n";

$payDate = date('Y-m-d');
$half = round((float) $subject->fee_amount / 2, 2);
$full = (float) $subject->fee_amount;

// ---------------------------------------------------------------------------
section('A part payment');
// ---------------------------------------------------------------------------
$part = takePayment($post, $subject, [
    'paid_amount' => $half,
    'pay_date' => $payDate,
    'payment_method' => '1',
    'note' => 'characterisation part payment',
]);

check('the fee records exactly what was handed over',
    abs((float) $part['fee']['paid_amount'] - $half) < 0.005,
    'got ' . $part['fee']['paid_amount'] . ', paid ' . $half);

check('a part payment leaves the fee partly paid, not settled',
    (string) $part['fee']['status'] === '2',
    'status ' . var_export($part['fee']['status'], true) . ' — 2 means partial');

check('the pay date is the one submitted',
    substr((string) $part['fee']['pay_date'], 0, 10) === $payDate,
    'got ' . var_export($part['fee']['pay_date'], true));

check('one student ledger transaction is written', count($part['transactions']) === 1,
    count($part['transactions']) . ' written');

if (count($part['transactions']) === 1) {
    $ledger = $part['transactions'][0];
    check('and it carries the payment, not the fee total',
        abs((float) $ledger['amount'] - $half) < 0.005,
        'got ' . $ledger['amount']);
    check('and it is recorded as money in',
        (string) $ledger['type'] === '1',
        'type ' . var_export($ledger['type'], true));
}

check('one payment receipt is written', count($part['payment_receipts']) === 1,
    count($part['payment_receipts']) . ' written');

if (count($part['payment_receipts']) === 1) {
    $receipt = $part['payment_receipts'][0];
    check('the receipt is for this fee',
        (int) $receipt['fee_id'] === $subject->id);
    check('the receipt carries the amount received',
        abs((float) $receipt['amount'] - $half) < 0.005,
        'got ' . $receipt['amount']);
    check('an admin payment is already approved',
        (string) $receipt['verification_status'] === 'approved',
        'got ' . var_export($receipt['verification_status'], true)
            . ' — a payment taken at the counter is not waiting on anybody');
}

check('no credit is raised by a part payment',
    count($part['student_credits']) === 0,
    count($part['student_credits']) . ' raised');

// ---------------------------------------------------------------------------
section('Paying it off exactly');
// ---------------------------------------------------------------------------
$exact = takePayment($post, $subject, [
    'paid_amount' => $full,
    'pay_date' => $payDate,
    'payment_method' => '1',
    'note' => 'characterisation full payment',
]);

check('the fee reads as settled',
    (string) $exact['fee']['status'] === '1',
    'status ' . var_export($exact['fee']['status'], true));

check('and nothing is left owing',
    abs((float) $exact['fee']['paid_amount'] - $full) < 0.005);

check('paying exactly raises no credit',
    count($exact['student_credits']) === 0,
    count($exact['student_credits']) . ' raised — nothing was overpaid');

// ---------------------------------------------------------------------------
section('More than the fee is worth');
// ---------------------------------------------------------------------------
// This section CHANGED on purpose when the distribution work landed. Before it,
// the excess became a StudentCredit that somebody applied to the second
// instalment by hand. Now it goes straight there. The two checks that used to
// assert the old behaviour were not deleted quietly - they are rewritten below
// to assert the new one, and the refusal path above them is untouched.
$over = $full + 50000;

$refused = takePayment($post, $subject, [
    'paid_amount' => $over,
    'pay_date' => $payDate,
    'payment_method' => '1',
    'note' => 'characterisation overpayment without consent',
]);

check('an overpayment with no consent writes nothing to the fee',
    abs((float) $refused['fee']['paid_amount']) < 0.005,
    'paid_amount became ' . $refused['fee']['paid_amount']);

check('and raises no credit',
    count($refused['student_credits']) === 0,
    count($refused['student_credits']) . ' raised');

check('and writes no receipt',
    count($refused['payment_receipts']) === 0,
    count($refused['payment_receipts']) . ' written');

$allowed = takePayment($post, $subject, [
    'paid_amount' => $over,
    'pay_date' => $payDate,
    'payment_method' => '1',
    'allow_overpayment' => '1',
    'note' => 'characterisation overpayment with consent',
]);

check('the fee takes only what it is owed, never more',
    abs((float) $allowed['fee']['paid_amount'] - $full) < 0.005,
    'got ' . $allowed['fee']['paid_amount'] . ', the fee is worth ' . $full);

check('the excess reaches a second fee instead of becoming credit',
    count($allowed['payment_receipts']) === 2,
    count($allowed['payment_receipts']) . ' receipts — one handover should settle two instalments');

if (count($allowed['payment_receipts']) === 2) {
    $amounts = array_map(fn ($r) => round((float) $r['amount'], 2), $allowed['payment_receipts']);
    sort($amounts);

    check('and the two legs add up to exactly what was handed over',
        abs(array_sum($amounts) - $over) < 0.005,
        implode(' + ', $amounts) . ' against ' . $over);

    check('the fee opened is filled first, the rest spills to the next',
        abs($amounts[0] - 50000) < 0.005 && abs($amounts[1] - $full) < 0.005,
        'legs were ' . implode(', ', $amounts));

    $groups = array_unique(array_map(fn ($r) => $r['payment_group'], $allowed['payment_receipts']));
    check('both receipts belong to the same handover',
        count($groups) === 1 && $groups[array_key_first($groups)] !== null,
        'groups ' . json_encode($groups) . ' — without this the receipt cannot show both instalments');
}

check('no credit is raised when an instalment could take the money',
    count($allowed['student_credits']) === 0,
    count($allowed['student_credits']) . ' raised — this is the hand-work that overstated the ledger');

// ---------------------------------------------------------------------------
section('More than every instalment the student owes');
// ---------------------------------------------------------------------------
// A genuine prepayment. It still becomes credit, as it always did, but the
// bursar has to say so deliberately.
$distribution = app(App\Services\FeePaymentDistribution::class);
$recorder = app(App\Services\FeePaymentRecorder::class);

$everything = $distribution->targets($subject)
    ->sum(fn ($target) => $recorder->outstanding($target));
$beyond = round($everything + 50000, 2);

echo "  (every instalment this student owes comes to " . number_format($everything, 2) . ")
";

$unticked = takePayment($post, $subject, [
    'paid_amount' => $beyond,
    'pay_date' => $payDate,
    'payment_method' => '1',
    'allow_overpayment' => '1',
    'note' => 'characterisation prepayment, box not ticked',
]);

check('without the tick box nothing at all is written',
    abs((float) $unticked['fee']['paid_amount']) < 0.005
        && count($unticked['payment_receipts']) === 0
        && count($unticked['student_credits']) === 0,
    'paid ' . $unticked['fee']['paid_amount']
        . ', ' . count($unticked['payment_receipts']) . ' receipts, '
        . count($unticked['student_credits']) . ' credits');

$ticked = takePayment($post, $subject, [
    'paid_amount' => $beyond,
    'pay_date' => $payDate,
    'payment_method' => '1',
    'allow_overpayment' => '1',
    'allow_leftover_credit' => '1',
    'note' => 'characterisation prepayment, box ticked',
]);

check('with the tick box every instalment is settled',
    count($ticked['payment_receipts']) === 2,
    count($ticked['payment_receipts']) . ' receipts');

check('and exactly one credit is raised, for the remainder only',
    count($ticked['student_credits']) === 1
        && abs((float) $ticked['student_credits'][0]['original_amount'] - 50000) < 0.005,
    count($ticked['student_credits']) . ' credits, '
        . ($ticked['student_credits'] ? $ticked['student_credits'][0]['original_amount'] : '-')
        . ' — expected one of 50000.00');

// ---------------------------------------------------------------------------
section('The fingerprint');
// ---------------------------------------------------------------------------
// A byte-for-byte capture of every row a payment writes, with the values that
// differ every run blanked. Diff this across the refactor: if it changes, the
// refactor changed what a payment means.
$fingerprint = [
    'part' => $part,
    'exact' => $exact,
    'refused' => $refused,
    'allowed' => $allowed,
    'unticked' => $unticked,
    'ticked' => $ticked,
];

$json = json_encode($fingerprint, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

if ($capturePath) {
    file_put_contents($capturePath, $json);
    echo "  captured to " . $capturePath . "\n";
    echo "  sha1 " . sha1($json) . "\n";
} else {
    echo "  sha1 " . sha1($json) . "\n";
    echo "  (run with --capture <file> to save a baseline for diffing)\n";
}

// ---------------------------------------------------------------------------
section('Nothing was left behind');
// ---------------------------------------------------------------------------
$after = Fee::find($subject->id);

check('the subject fee is untouched',
    (float) ($after->paid_amount ?? 0) < 0.005 && (string) $after->status === (string) $subject->status,
    'paid ' . $after->paid_amount . ', status ' . $after->status);

echo "\n----\npassed $passed, failed $failed" . ($skipped ? ", skipped $skipped" : '') . "\n";
