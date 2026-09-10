<?php
/**
 * Posting unmapped transactions from the mappings screen — and the three faults
 * found alongside it, each of which this guards.
 *
 *   1. Automatic posting was about to stop. TransactionAutoMapService numbered
 *      its entries from whichever entry was newest, and read the digits after
 *      "JE-". Once a payroll or remittance entry (JE-2026-0007) was newest, that
 *      read the year, 2026, and produced JE-002027 — already taken. The column
 *      is unique, so every automatic posting and every reversal would have
 *      failed inside a catch that only logs.
 *
 *   2. The ✨ auto-map button read the category from columns that do not exist,
 *      so it failed on every fee, income and expense it was shown on.
 *
 *   3. The controller's permission middleware named methods that do not
 *      exist, so it protected nothing — including save-default, which decides
 *      where every franc posts.
 *
 * Every write runs inside a transaction that is rolled back.
 *
 * Usage: php scripts/ledger_sync_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\DefaultAccountMapping;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\TransactionMapping;
use App\Services\LedgerSyncService;
use App\Services\TransactionAutoMapService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;

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

$admin = App\User::where('is_admin', 1)->orderBy('id')->firstOrFail();
Auth::guard('web')->login($admin);

$mapper = app(TransactionAutoMapService::class);
$sync = app(LedgerSyncService::class);

/**
 * A request through the whole HTTP stack, with a real CSRF token. Without the
 * token a POST dies as 419 before the permission middleware runs, and a refusal
 * would prove nothing about permissions.
 */
function http(string $method, string $uri, array $data = [])
{
    $kernel = app(Illuminate\Contracts\Http\Kernel::class);

    app('session.store')->regenerateToken();

    if ($method !== 'GET') {
        $data['_token'] = app('session.store')->token();
    }

    $request = Illuminate\Http\Request::create($uri, $method, $data);
    $request->headers->set('Accept', 'application/json');
    $request->headers->set('X-Requested-With', 'XMLHttpRequest');
    $request->setLaravelSession(app('session.store'));

    try {
        return $kernel->handle($request);
    } catch (Spatie\Permission\Exceptions\UnauthorizedException $e) {
        return new Illuminate\Http\Response('', 403);
    }
}

/** A fee that is paid, not on a plan, posted, and whose category has a mapping. */
function postedFee()
{
    return DB::table('fees as f')
        ->join('transaction_mappings as m', function ($j) {
            $j->on('m.transaction_id', '=', 'f.id')->where('m.transaction_type', 'fee')->where('m.status', 'active');
        })
        ->join('default_account_mappings as d', function ($j) {
            $j->on('d.category_id', '=', 'f.category_id')->where('d.mapping_type', 'fee_category')->where('d.status', 'active');
        })
        ->where('f.paid_amount', '>', 0)
        ->whereNotNull('f.pay_date')
        ->whereNull('f.payment_plan_id')
        ->orderBy('f.id')
        ->select('f.*', 'd.debit_account_id', 'd.credit_account_id', 'd.id as default_id')
        ->first();
}

echo "\n== The entry number no longer collides ==\n";

$generator = (new ReflectionClass($mapper))->getMethod('generateEntryNumber');
$generator->setAccessible(true);

DB::beginTransaction();
$next = $generator->invoke($mapper);
DB::rollBack();

check('the next automatic entry number is free', !JournalEntry::withTrashed()->where('entry_number', $next)->exists(), $next);
check('and in the service\'s own format', (bool) preg_match('/^JE-\d{6}$/', $next), $next);

$newest = JournalEntry::withTrashed()->orderByDesc('id')->value('entry_number');

if (preg_match('/^JE-\d{4}-/', (string) $newest)) {
    // Teeth, recorded as data rather than asserted about the fix: the formula
    // that was replaced lands on a number that is already taken.
    $old = 'JE-' . str_pad(intval(substr($newest, 3)) + 1, 6, '0', STR_PAD_LEFT);

    check("the replaced formula would have collided ({$old} after {$newest})",
        JournalEntry::withTrashed()->where('entry_number', $old)->exists());
} else {
    echo "  SKIP  the newest entry is not in the year format, so the old collision cannot be shown\n";
}

echo "\n== Unpaid fees, plan fees and payroll are refused ==\n";

$unpaid = DB::table('fees')->where(fn ($q) => $q->where('paid_amount', '<=', 0)->orWhereNull('pay_date'))->pluck('id');

if ($unpaid->isEmpty()) {
    echo "  SKIP  no unpaid fee in this database\n";
} else {
    $accepted = $unpaid->filter(fn ($id) => $sync->eligibility('fee', $id)['code'] !== 'not_paid');

    check("every unpaid fee is refused as not paid ({$unpaid->count()})", $accepted->isEmpty(),
        'accepted: ' . $accepted->implode(', '));
}

$planFee = $sync->decide('fee', (object) [
    'payment_plan_id' => 1, 'paid_amount' => 50000, 'pay_date' => '2026-03-01', 'category_id' => 1, 'category' => null,
], false);

check('a fee on a payment plan is refused — its instalments post instead', $planFee['code'] === 'payment_plan');
check('payroll is refused — it posts from the payroll screen', $sync->eligibility('payroll', 1)['code'] === 'payroll');
check('an unknown type is refused', $sync->eligibility('drop_table', 1)['code'] === 'unsupported');
check('a missing transaction is refused', $sync->eligibility('fee', 99999999)['code'] === 'not_found');

echo "\n== A transaction posts exactly as its mapping says ==\n";

$fee = postedFee();

if (!$fee) {
    echo "  SKIP  no posted, mapped, paid fee to work with\n";
} else {
    DB::beginTransaction();

    try {
        check('an already-posted fee is refused', $sync->eligibility('fee', $fee->id)['code'] === 'already_posted');

        // Reversing leaves it unposted — and exercises the reversal path, which
        // numbers its entry through the generator fixed above.
        check('it can be reversed', (bool) $mapper->reverse('fee', $fee->id));
        check('once reversed, it is postable again', $sync->eligibility('fee', $fee->id)['eligible']);

        $before = JournalEntry::withTrashed()->count();
        $result = $sync->sync([['type' => 'fee', 'id' => $fee->id]]);

        check('the sync posts it', $result['totals']['posted'] === 1,
            json_encode($result['refused'] ?: $result['failed']));

        $mapping = TransactionMapping::where('transaction_type', 'fee')->where('transaction_id', $fee->id)->first();
        $entry = $mapping ? JournalEntry::with('lines')->find($mapping->journal_entry_id) : null;

        check('the mapping is active and linked to its entry', $mapping && $mapping->status === 'active' && $entry);
        check('exactly one new journal entry was written', JournalEntry::withTrashed()->count() === $before + 1);

        if ($entry) {
            $debit = $entry->lines->firstWhere('debit', '>', 0);
            $credit = $entry->lines->firstWhere('credit', '>', 0);

            check('the entry balances', abs($entry->lines->sum('debit') - $entry->lines->sum('credit')) < 0.01);
            check('it debits the account the default mapping names', $debit && (int) $debit->account_id === (int) $fee->debit_account_id);
            check('it credits the account the default mapping names', $credit && (int) $credit->account_id === (int) $fee->credit_account_id);
            check('for the amount actually paid', abs((float) $debit->debit - (float) $fee->paid_amount) < 0.01,
                $debit->debit . ' vs ' . $fee->paid_amount);
            check('dated the day it was paid', substr((string) $entry->entry_date, 0, 10) === substr((string) $fee->pay_date, 0, 10));

            $year = FiscalYear::find($entry->fiscal_year_id);
            check('filed in the fiscal year that date falls in',
                $year && substr((string) $fee->pay_date, 0, 10) >= substr((string) $year->start_date, 0, 10)
                      && substr((string) $fee->pay_date, 0, 10) <= substr((string) $year->end_date, 0, 10));

            check('numbered in the service\'s own format', (bool) preg_match('/^JE-\d{6}$/', $entry->entry_number), $entry->entry_number);
            check('posted, not left in draft', (bool) $entry->is_posted);
        }

        // Syncing it again must do nothing.
        $count = JournalEntry::withTrashed()->count();
        $again = $sync->sync([['type' => 'fee', 'id' => $fee->id]]);

        check('syncing it again is refused as already posted',
            $again['totals']['posted'] === 0 && ($again['refused'][0]['code'] ?? '') === 'already_posted');
        check('and writes nothing', JournalEntry::withTrashed()->count() === $count);

        // Your rule: no configured mapping, no posting.
        $mapper->reverse('fee', $fee->id);
        DefaultAccountMapping::where('id', $fee->default_id)->update(['status' => 'inactive']);

        $unconfigured = $sync->sync([['type' => 'fee', 'id' => $fee->id]]);
        $category = DB::table('fees_categories')->where('id', $fee->category_id)->value('title');

        check('with its mapping switched off, it is refused', ($unconfigured['refused'][0]['code'] ?? '') === 'no_mapping');
        check('and the refusal names the category', str_contains($unconfigured['refused'][0]['reason'] ?? '', (string) $category),
            $unconfigured['refused'][0]['reason'] ?? '');
        check('and nothing is posted', !TransactionMapping::where('transaction_type', 'fee')
            ->where('transaction_id', $fee->id)->where('status', 'active')->exists());
    } finally {
        DB::rollBack();
    }
}

echo "\n== A mixed selection ==\n";

if ($fee && $unpaid->isNotEmpty()) {
    DB::beginTransaction();

    try {
        $mapper->reverse('fee', $fee->id);

        $mixed = $sync->sync([
            ['type' => 'fee', 'id' => $fee->id],          // postable
            ['type' => 'fee', 'id' => $unpaid->first()],  // unpaid
            ['type' => 'payroll', 'id' => 1],             // payroll
            ['type' => 'fee', 'id' => 99999999],          // gone
            ['type' => 'fee', 'id' => $fee->id],          // the same row twice
        ]);

        $t = $mixed['totals'];

        check('the postable one posts', $t['posted'] === 1);
        check('the other three are refused', $t['refused'] === 3, json_encode($mixed['refused']));
        check('nothing fails', $t['failed'] === 0);
        check('a row selected twice counts once', $t['posted'] + $t['refused'] + $t['failed'] === 4);
        check('every refusal carries a reason', collect($mixed['refused'])->every(fn ($r) => !empty($r['reason'])));
    } finally {
        DB::rollBack();
    }
} else {
    echo "  SKIP  need both a posted fee and an unpaid one\n";
}

echo "\n== Through the screen ==\n";

if ($fee) {
    // The ✨ button. Before the fix this read fees_category_id, which does not
    // exist, and refused every fee.
    DB::beginTransaction();

    try {
        $mapper->reverse('fee', $fee->id);

        $response = http('POST', '/admin/accounting/mappings/auto-map', [
            'transaction_type' => 'fee',
            'transaction_id' => $fee->id,
        ]);
        $body = json_decode($response->getContent(), true) ?: [];

        check('the ✨ auto-map button posts a categorised fee', $response->getStatusCode() === 200 && ($body['success'] ?? false),
            $response->getStatusCode() . ' ' . ($body['message'] ?? ''));
    } finally {
        DB::rollBack();
    }

    DB::beginTransaction();

    try {
        $mapper->reverse('fee', $fee->id);

        $response = http('POST', '/admin/accounting/mappings/bulk-sync', [
            'items' => [['type' => 'fee', 'id' => $fee->id]],
        ]);
        $body = json_decode($response->getContent(), true) ?: [];

        check('the Sync selected button posts through its route', ($body['totals']['posted'] ?? 0) === 1,
            $response->getStatusCode() . ' ' . ($body['message'] ?? ''));
    } finally {
        DB::rollBack();
    }
}

$bad = http('POST', '/admin/accounting/mappings/bulk-sync', ['items' => [['type' => 'drop_table', 'id' => 1]]]);
check('an invented type is rejected by validation', $bad->getStatusCode() === 422, 'status ' . $bad->getStatusCode());

echo "\n== Only the right people ==\n";

// Gate::before lets is_admin through regardless, so this has to be a real
// non-admin, and it has to go through the kernel.
$plain = App\User::where('is_admin', 0)->where('status', 1)->first();

if (!$plain || !$fee) {
    echo "  SKIP  need a non-admin user and a posted fee\n";
} else {
    DB::beginTransaction();

    try {
        $mapper->reverse('fee', $fee->id);

        $plain->roles()->detach();
        $plain->permissions()->detach();
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Auth::guard('web')->login($plain);

        $routes = [
            ['POST', '/admin/accounting/mappings/bulk-sync', ['items' => [['type' => 'fee', 'id' => $fee->id]]]],
            ['POST', '/admin/accounting/mappings/auto-map', ['transaction_type' => 'fee', 'transaction_id' => $fee->id]],
            ['POST', '/admin/accounting/mappings/map-transaction', ['transaction_type' => 'fee', 'transaction_id' => $fee->id,
                'debit_account_id' => $fee->debit_account_id, 'credit_account_id' => $fee->credit_account_id]],
            ['POST', '/admin/accounting/mappings/save-default', ['mappings' => []]],
            ['GET', '/admin/accounting/mappings/transactions', []],
        ];

        foreach ($routes as [$method, $uri, $data]) {
            $status = http($method, $uri, $data)->getStatusCode();

            check("{$method} " . basename($uri) . ' is refused without its permission', $status === 403,
                "status {$status}" . ($status === 419 ? ' — CSRF, which proves nothing' : ''));
        }

        // The point of the refusal: nothing got written.
        check('and nothing was posted by any of them', !TransactionMapping::where('transaction_type', 'fee')
            ->where('transaction_id', $fee->id)->where('status', 'active')->exists());
    } finally {
        DB::rollBack();
        Auth::guard('web')->login($admin);
        app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

echo "\n== The page ==\n";

// Filtered to unmapped fees. Unpaid fees have no pay date, so on an unfiltered
// page they sort to the very end — page 1 would not contain one, and every
// check below would pass without looking at anything.
$page = http('GET', '/admin/accounting/mappings/transactions?type=fee&status=unmapped');
$html = $page->getContent();

check('the transactions page renders', $page->getStatusCode() === 200, 'status ' . $page->getStatusCode());
check('it has the Sync selected button', str_contains($html, 'id="syncSelectedBtn"'));

if ($unpaid->isNotEmpty()) {
    // Prove the rows are actually on the page before judging them.
    $shown = $unpaid->filter(fn ($id) => preg_match('/data-transaction-id="' . $id . '"|value="fee:' . $id . '"|Not paid/', $html));
    check('the unpaid fees are on the page being checked', substr_count($html, 'badge-not-paid') >= $unpaid->count(),
        substr_count($html, 'badge-not-paid') . ' Not-paid badges for ' . $unpaid->count() . ' unpaid fees');

    check('unpaid fees are labelled Not paid', str_contains($html, 'Not paid'));

    $selectable = $unpaid->filter(fn ($id) => preg_match('/class="form-check-input sync-select"[^>]*value="fee:' . $id . '"/', $html));
    check('no unpaid fee can be selected for posting', $selectable->isEmpty(), 'selectable: ' . $selectable->implode(', '));

    // An unpaid bill must not offer the manual Map or ✨ button either —
    // mapping it by hand would post a zero-value entry.
    $offered = $unpaid->filter(fn ($id) => preg_match('/(map-transaction-btn|auto-map-btn)[^>]*data-transaction-id="' . $id . '"/', $html));
    check('and no map button is offered for one', $offered->isEmpty(), 'offered: ' . $offered->implode(', '));
}

// Bootstrap 5.2: a v4 attribute does not error, the control just does nothing.
check('no Bootstrap 4 data-toggle on the page', !str_contains($html, 'data-toggle='));
check('no Bootstrap 4 data-target on the page', !str_contains($html, 'data-target='));

echo "\n== The ledger ==\n";

$totals = DB::table('journal_entry_lines as l')
    ->join('journal_entries as e', 'e.id', '=', 'l.journal_entry_id')
    ->where('e.is_posted', 1)->whereNull('e.deleted_at')
    ->selectRaw('COALESCE(SUM(l.debit),0) d, COALESCE(SUM(l.credit),0) c')->first();

check('total debits equal total credits', abs($totals->d - $totals->c) < 0.01,
    number_format($totals->d, 2) . ' vs ' . number_format($totals->c, 2));

$oneSided = DB::table('journal_entries as e')
    ->where('e.is_posted', 1)->whereNull('e.deleted_at')
    ->whereRaw('(SELECT COALESCE(SUM(debit),0) FROM journal_entry_lines WHERE journal_entry_id = e.id)
              <> (SELECT COALESCE(SUM(credit),0) FROM journal_entry_lines WHERE journal_entry_id = e.id)')
    ->count();

check('no posted entry is one-sided', $oneSided === 0, "{$oneSided} unbalanced");

echo "\n$passed passed, $failed failed\n";

exit($failed > 0 ? 1 : 0);
