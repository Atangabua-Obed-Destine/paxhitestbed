<?php
/**
 * Fixed assets end to end: an asset is bought, depreciated for a month,
 * the depreciation reversed, depreciated again, then disposed of at a gain.
 * Every step must leave a posted, balanced entry on the right accounts and
 * the asset's figures in step. Everything is rolled back.
 *
 *   php scripts/fixed_assets_test.php
 */
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\{ChartOfAccount, DepreciationSchedule, FixedAsset, FixedAssetCategory, FiscalYear, JournalEntry};
use App\User;
use App\Services\Accounting\DepreciationService;
use Illuminate\Support\Facades\DB;

$pass = 0; $fail = 0;
function check($label, $ok, $detail = '') {
    global $pass, $fail;
    if ($ok) { $pass++; echo "  ok    $label\n"; }
    else { $fail++; echo "  FAIL  $label" . ($detail !== '' ? "   [$detail]" : '') . "\n"; }
}
function acct($like) {
    return ChartOfAccount::where('account_code', 'like', $like)->where('account_category', 'detail')->orderBy('account_code')->first();
}
function bal($id) { return (float) ChartOfAccount::find($id)->current_balance; }

app('session.store')->start();
DB::beginTransaction();
try {
    $user = User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first() ?? User::first();
    \Auth::guard('web')->login($user);

    $asset24 = acct('24%');      // equipment
    $exp681 = acct('681%');      // depreciation expense
    $acc28 = acct('28%');        // accumulated depreciation
    check('the chart has asset, depreciation and accumulated depreciation accounts',
        $asset24 && $exp681 && $acc28, json_encode([$asset24?->account_code, $exp681?->account_code, $acc28?->account_code]));

    // A month whose period is open.
    $fy = FiscalYear::where('is_closed', 0)->orderByDesc('start_date')->first();
    $period = $fy?->accountingPeriods()->where('is_closed', 0)->orderBy('start_date')->first();
    check('an open month to post into', (bool) $period);
    $date = \Carbon\Carbon::parse($period->end_date)->toDateString();

    $cat = FixedAssetCategory::create([
        'name' => 'Test computers', 'depreciation_method' => 'straight_line', 'useful_life_years' => 3,
        'salvage_value_percent' => 0, 'asset_account_id' => $asset24->id,
        'depreciation_account_id' => $exp681->id, 'accumulated_depreciation_account_id' => $acc28->id, 'is_active' => 1,
    ]);
    $asset = FixedAsset::create([
        'asset_code' => FixedAsset::generateAssetCode(), 'name' => 'Test laptop', 'category_id' => $cat->id,
        'acquisition_date' => \Carbon\Carbon::parse($period->start_date)->subMonth()->toDateString(),
        'depreciation_start_date' => \Carbon\Carbon::parse($period->start_date)->subMonth()->toDateString(),
        'acquisition_cost' => 360000, 'salvage_value' => 0, 'useful_life_months' => 36,
        'depreciation_method' => 'straight_line', 'accumulated_depreciation' => 0, 'book_value' => 360000, 'status' => 'active',
    ]);

    $svc = app(DepreciationService::class);
    $before681 = bal($exp681->id); $before28 = bal($acc28->id);

    // 1. One month's depreciation.
    $schedule = DepreciationSchedule::create([
        'fixed_asset_id' => $asset->id, 'depreciation_date' => $date, 'depreciation_amount' => 10000,
        'accumulated_depreciation_before' => 0, 'accumulated_depreciation_after' => 10000,
        'book_value_before' => 360000, 'book_value_after' => 350000,
        'fiscal_year_id' => $fy->id, 'accounting_period_id' => $period->id, 'status' => DepreciationSchedule::STATUS_PENDING,
    ]);
    check('a schedule saves (the table now has the model\'s columns)', $schedule->exists);
    check('the original columns are kept in step', (float) $schedule->opening_book_value == 360000
        && (float) $schedule->closing_book_value == 350000 && (float) $schedule->accumulated_depreciation == 10000 && !$schedule->is_posted);

    $je = $schedule->post($user->id);
    $je->refresh();
    $schedule->refresh(); $asset->refresh();
    check('depreciation writes a posted entry', $je->is_posted && $je->entry_number && $je->reference_type === 'depreciation'
        && (int) $je->reference_id === $schedule->id, $je->entry_number);
    check('the entry is balanced at 10,000', $je->isBalanced() && (float) $je->total_debit == 10000);
    check('it is in the month of the depreciation date', (int) $je->accounting_period_id === $period->id && $je->entry_date->toDateString() === $date);
    check('Dr depreciation expense, Cr accumulated depreciation',
        $je->lines->firstWhere('account_id', $exp681->id)?->debit == 10000 && $je->lines->firstWhere('account_id', $acc28->id)?->credit == 10000);
    check('the expense account moved by 10,000', round(bal($exp681->id) - $before681, 2) == 10000);
    check('accumulated depreciation moved by 10,000', round(bal($acc28->id) - $before28, 2) == 10000);
    check('the schedule is posted, both ways of reading it', $schedule->status === 'posted' && $schedule->is_posted && (int) $schedule->journal_entry_id === $je->id);
    check('the asset carries it', (float) $asset->accumulated_depreciation == 10000 && (float) $asset->book_value == 350000);

    // 2. Reverse it.
    $schedule->reverse('test', $user->id);
    $schedule->refresh(); $asset->refresh();
    $rev = JournalEntry::where('reversed_entry_id', $je->id)->first();
    check('reversal writes a counter-entry', (bool) $rev, 'none found');
    check('the books are back where they were', round(bal($exp681->id) - $before681, 2) == 0 && round(bal($acc28->id) - $before28, 2) == 0);
    check('the schedule reads reversed', $schedule->status === 'reversed' && !$schedule->is_posted);
    check('the asset is back to cost', (float) $asset->accumulated_depreciation == 0 && (float) $asset->book_value == 360000);

    check('after the reversal the month is due again', $asset->isDepreciationDue($date));

    // 3. The month's run from the screen: the reversed month is charged again,
    //    on the same row, and a second run charges nothing more.
    // Other assets on the install are kept out of these counts.
    $others = FixedAsset::whereKeyNot($asset->id)->where('status', 'active')->pluck('id');
    FixedAsset::whereIn('id', $others)->update(['status' => 'fully_depreciated']);

    $run = $svc->calculateMonthlyDepreciation($date, true);
    $asset->refresh();
    check('the run charges the asset for the month', $run['posted'] === 1 && !$run['errors'], json_encode($run['errors']));
    check('on the reversed row, not a second one', DepreciationSchedule::where('fixed_asset_id', $asset->id)->count() === 1);
    check('10,000 for a 360,000 asset over 36 months', (float) $asset->accumulated_depreciation == 10000 && (float) $asset->book_value == 350000);
    $again = $svc->calculateMonthlyDepreciation($date, true);
    $asset->refresh();
    check('running the month again charges nothing more', $again['posted'] === 0 && (float) $asset->accumulated_depreciation == 10000);

    // February follows 31 January (addMonth() used to land on 3 March).
    $jan31 = \Carbon\Carbon::create(2027, 1, 31);
    $probe = $asset->replicate(); $probe->last_depreciation_date = $jan31;
    check('February is due after 31 January', $probe->isDepreciationDue(\Carbon\Carbon::create(2027, 2, 28)));
    check('January is not due twice', !$probe->isDepreciationDue(\Carbon\Carbon::create(2027, 1, 31)));

    // The screen's buttons answer with the screen, not JSON.
    $req = \Illuminate\Http\Request::create('/admin/fixed-assets/post-depreciation', 'POST',
        ['month' => \Carbon\Carbon::parse($date)->format('Y-m'), 'reviewed' => 1, '_token' => csrf_token()]);
    $req->setLaravelSession(app('session.store'));
    $res = app(\Illuminate\Contracts\Http\Kernel::class)->handle($req);
    check('posting from the screen returns to the screen', $res->getStatusCode() === 302
        && str_contains((string) $res->headers->get('location'), 'fixed-assets'), $res->getStatusCode() . ' ' . $res->headers->get('location'));
    FixedAsset::whereIn('id', $others)->update(['status' => 'active']);

    $cash = acct('521%') ?? acct('57%');
    $gain = acct('82%');
    $bank = \App\Models\PaymentAccount::create(['title' => 'Test bank', 'chart_of_account_id' => $cash->id,
        'opening_balance' => 0, 'current_balance' => 0, 'status' => 1,
        'account_type_id' => DB::table('payment_account_types')->value('id')]);

    // Proceeds with nowhere to go are refused, not booked to a guess.
    $refused = false;
    try { $svc->disposeAsset($asset, \Carbon\Carbon::parse($date), 380000, 'sold'); } catch (\Throwable $e) { $refused = true; }
    $asset->refresh();
    check('proceeds without a cash box or bank are refused (when no default is set)',
        $refused || \App\Models\AccountingSetting::getValue('default_cash_account_id'), 'it posted');
    check('and the asset is untouched', $asset->status !== 'disposed');

    $before24 = bal($asset24->id); $beforeCash = bal($cash->id); $beforeGain = bal($gain->id);
    $svc->disposeAsset($asset, \Carbon\Carbon::parse($date), 380000, 'sold', $bank->id);
    $asset->refresh();
    $dje = JournalEntry::with('lines')->find($asset->disposal_journal_entry_id);
    check('disposal writes a posted, balanced entry', $dje && $dje->is_posted && $dje->isBalanced(), $dje?->entry_number);
    check('the asset is disposed and points at it', $asset->status === 'disposed' && $dje && (int) $dje->reference_id === $asset->id);
    check('cash received 380,000', round(bal($cash->id) - $beforeCash, 2) == 380000);
    check('cost of 360,000 leaves the asset account', round($before24 - bal($asset24->id), 2) == 360000);
    check('accumulated depreciation is cleared', round(bal($acc28->id) - $before28, 2) == 0);
    check('the gain of 30,000 is recorded (380,000 received for a book value of 350,000)',
        round(bal($gain->id) - $beforeGain, 2) == 30000, round(bal($gain->id) - $beforeGain, 2));
} catch (\Throwable $e) {
    $fail++;
    echo "  FAIL  exception: " . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() . "\n";
} finally {
    DB::rollBack();
}

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
