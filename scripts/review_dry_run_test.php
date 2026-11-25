<?php
/**
 * The review dialog for every action that posts money (ReviewController +
 * RequireReview).
 *
 * Through the real routes, as a logged-in admin:
 *   - the dry run shows the ledger entry the action would write, and saves
 *     nothing;
 *   - a refusal from the action comes back as a problem, and the tick box
 *     stays greyed;
 *   - the action itself is refused without reviewed=1, and goes through
 *     with it, writing exactly the entry the review showed;
 *   - only listed routes can be dry-run;
 *   - every money-posting form carries its review.
 *
 * Usage: php scripts/review_dry_run_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $label . (!$ok && $detail !== '' ? "\n          $detail" : '') . "\n";
}

config(['mail.default' => 'array']);
app('session.store')->start();
$admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->where('status', '1')->first();
Auth::guard('web')->login($admin);

$call = function (string $method, string $path, array $data = []) use ($kernel) {
    app('flasher.storage_manager')->clear();
    app('session.store')->forget('errors');
    if ($method !== 'GET') {
        $data['_token'] = csrf_token();
    }
    $request = Illuminate\Http\Request::create($path, $method, $data);
    $request->setLaravelSession(app('session.store'));
    $request->headers->set('referer', 'http://localhost/admin/dashboard');

    return $kernel->handle($request);
};
$counts = fn () => [
    'entries' => DB::table('journal_entries')->count(),
    'loans' => DB::table('staff_loans')->count(),
    'remittances' => DB::table('tax_remittances')->count(),
];

DB::beginTransaction();
try {
    $staff = App\User::where('status', 1)->whereNotNull('staff_id')->where('id', '!=', $admin->id)->orderByDesc('id')->first();
    $kind = array_key_first(App\Models\StaffLoan::KINDS);
    $loan = ['user_id' => $staff->id, 'kind' => $kind, 'title' => 'Review test advance', 'amount' => 90000, 'instalment' => 30000,
        'start_month' => date('Y-m'), 'given_on' => date('Y-m-d'), 'note' => 'review test'];

    echo "\n== A staff advance, reviewed ==\n";
    $before = $counts();
    $res = $call('POST', '/admin/review/dry-run', $loan + ['_review_target' => '/admin/staff/staff-loan', '_review_method' => 'POST']);
    $html = (string) $res->getContent();
    check('the review opens', $res->getStatusCode() === 200, (string) $res->getStatusCode());
    check('it shows the ledger entry the advance would write', str_contains($html, 'Ledger entry to be posted') && str_contains($html, format_amount(90000)), strip_tags(substr($html, 0, 500)));
    check('and allows confirming', str_contains($html, 'data-ok="1"'));
    check('the dry run saved nothing', $counts() == $before, json_encode([$before, $counts()]));

    $call('POST', '/admin/staff/staff-loan', $loan);
    check('the advance is refused without the review tick', $counts() == $before);

    $call('POST', '/admin/staff/staff-loan', $loan + ['reviewed' => 1]);
    $after = $counts();
    check('with it, the advance is given', $after['loans'] === $before['loans'] + 1);
    $entry = App\Models\JournalEntry::with('lines')->orderByDesc('id')->first();
    check('and it posts the entry the review showed (90,000)', $after['entries'] > $before['entries'] && abs((float) $entry->total_debit - 90000) < 0.01);

    echo "\n== A refusal shows as a problem ==\n";
    $res = $call('POST', '/admin/review/dry-run', ['user_id' => $staff->id, 'kind' => $kind, 'amount' => 1000, 'instalment' => 5000,
        'start_month' => date('Y-m'), 'given_on' => date('Y-m-d'), '_review_target' => '/admin/staff/staff-loan', '_review_method' => 'POST']);
    $html = (string) $res->getContent();
    check('an instalment larger than the loan is refused in the review', str_contains($html, 'data-ok="0"'), strip_tags(substr($html, 0, 400)));

    echo "\n== Paying tax over, reviewed ==\n";
    $liability = (int) DB::table('chart_of_accounts')->where('account_code', 'like', '44%')->where('account_category', 'detail')->orderBy('account_code')->value('id');
    $bank = (int) DB::table('chart_of_accounts')->where('account_code', '521')->value('id');
    $remit = ['liability_account_id' => $liability, 'salary_month' => date('Y-m-01'), 'amount' => 1000, 'payment_date' => date('Y-m-d'),
        'source_account_id' => $bank, 'reference' => 'REVIEW-TEST'];
    $before = $counts();
    $html = (string) $call('POST', '/admin/review/dry-run', $remit + ['_review_target' => '/admin/staff/tax-remittance', '_review_method' => 'POST'])->getContent();
    check('the tax payment review answers (an entry, or the rule that refuses it)', str_contains($html, 'ap-preview'));
    check('and saves nothing', $counts() == $before);
    $call('POST', '/admin/staff/tax-remittance', $remit);
    check('the tax payment is refused without the review tick', $counts() == $before);

    echo "\n== Only listed actions can be dry-run ==\n";
    $html = (string) $call('POST', '/admin/review/dry-run', ['_review_target' => '/admin/fees-student-pay', '_review_method' => 'POST'])->getContent();
    check('a route outside the list is refused', str_contains($html, 'data-ok="0"') && str_contains($html, 'cannot be reviewed'));

    echo "\n== Every money-posting form carries its review ==\n";
    $views = [
        'payroll/pay', 'payroll/unpay', 'payroll/index', 'tax-remittance/index', 'staff-loan/index',
        'payment-account/transfers/create', 'payment-account/deposit', 'payment-account/withdraw',
        'accounting/fixed-assets/index', 'accounting/recurring-entries/index', 'accounting/year-end-closing/show',
        'journal-entries/show', 'student-credits/show',
    ];
    foreach ($views as $v) {
        $src = file_get_contents(__DIR__ . '/../resources/views/admin/' . $v . '.blade.php');
        check("$v has a review on its posting form", str_contains($src, 'data-review='));
    }
    check('the layout renders the review dialog on every screen',
        str_contains(file_get_contents(__DIR__ . '/../resources/views/admin/layouts/master.blade.php'), 'partials.review-modal'));
} finally {
    DB::rollBack();
}

echo "\n$passed passed, $failed failed\n";
exit($failed ? 1 : 0);
