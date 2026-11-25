<?php
/**
 * Recurring entries, through the real routes: create a template from the
 * screen's own field names, see it listed, review and process it, and check
 * the entry it writes. Rolled back.
 *
 * Usage: php scripts/recurring_entries_test.php
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

app('session.store')->start();
Auth::guard('web')->login(App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->where('status', '1')->first());
$call = function (string $method, string $path, array $data = []) use ($kernel) {
    app('flasher.storage_manager')->clear();
    app('session.store')->forget('errors');
    if ($method !== 'GET') { $data['_token'] = csrf_token(); }
    $request = Illuminate\Http\Request::create($path, $method, $data);
    $request->setLaravelSession(app('session.store'));
    $request->headers->set('referer', 'http://localhost/admin/recurring-entries');
    return $kernel->handle($request);
};
$acct = DB::table('chart_of_accounts')->pluck('id', 'account_code');

DB::beginTransaction();
try {
    echo "\n== Creating a template from the screen ==\n";
    $call('POST', '/admin/recurring-entries', ['template_name' => 'Rent of the annex', 'description' => 'Monthly rent', 'journal_type' => 'general',
        'frequency' => 'monthly', 'day_of_month' => 1, 'start_date' => date('Y-m-01'), 'total_occurrences' => 12,
        'lines' => [['account_id' => $acct['622'], 'debit' => 250000, 'credit' => 0, 'description' => 'Rent'],
                    ['account_id' => $acct['521'], 'debit' => 0, 'credit' => 250000, 'description' => 'Standing order']]]);
    $t = App\Models\RecurringJournalEntry::where('name', 'Rent of the annex')->first();
    check('the template is saved (the form says template_name)', (bool) $t, json_encode(optional(session('errors'))->all()));
    check('with its two lines, numbered', $t && $t->lines()->count() === 2 && $t->lines()->whereNotNull('line_number')->count() === 2);
    check('and its 12 occurrences', $t && (int) $t->occurrences === 12);
    check('it reads as active, with a reference', $t && $t->status === 'active' && str_starts_with($t->template_reference, 'REC-'));

    echo "\n== The screens open ==\n";
    foreach (['/admin/recurring-entries', '/admin/recurring-entries/' . ($t->id ?? 0), '/admin/recurring-entries/create', '/admin/recurring-entries/' . ($t->id ?? 0) . '/edit'] as $u) {
        $r = $call('GET', $u);
        check("$u opens", $r->getStatusCode() === 200, $r->getStatusCode() . ' ' . $r->headers->get('location'));
    }
    $list = (string) $call('GET', '/admin/recurring-entries')->getContent();
    check('the list shows its name and counts it', str_contains($list, 'Rent of the annex'));

    echo "\n== Processing it, reviewed ==\n";
    $before = DB::table('journal_entries')->count();
    $html = (string) $call('POST', '/admin/review/dry-run', ['_review_target' => '/admin/recurring-entries/' . $t->id . '/process', '_review_method' => 'POST'])->getContent();
    check('the review shows the entry it would write, and saves nothing', str_contains($html, 'data-ok="1"') && str_contains($html, '622') && DB::table('journal_entries')->count() === $before,
        trim(preg_replace('/\s+/', ' ', strip_tags($html))));
    $call('POST', '/admin/recurring-entries/' . $t->id . '/process', []);
    check('processing without the review tick is refused', DB::table('journal_entries')->count() === $before);
    $call('POST', '/admin/recurring-entries/' . $t->id . '/process', ['reviewed' => 1]);
    $je = App\Models\JournalEntry::with('lines')->where('reference_type', 'recurring_entry')->where('reference_id', $t->id)->first();
    check('it writes a numbered, balanced entry for 250,000', $je && $je->entry_number && abs($je->total_debit - 250000) < 0.01 && $je->isBalanced());
    check('in the fiscal year and month of its date', $je && $je->fiscal_year_id === optional(App\Models\FiscalYear::forDate($je->entry_date))->id && $je->accounting_period_id);
    check('left for the accountant to post (auto-post is off)', $je && !$je->is_posted);
    $t->refresh();
    check('the template moves on a month and counts one occurrence', $t->occurrences_completed === 1 && $t->next_run_date->toDateString() === now()->startOfMonth()->addMonth()->toDateString());
    check('the template lists the entry it wrote', $t->journalEntries()->count() === 1);
} finally {
    DB::rollBack();
}

// Dates keep the entry's own day: 31 January is followed by 28 February, then
// 31 March (addMonth() used to land on 3 March and skip February, and a date
// cut to the 28th stayed there).
$walk = function (array $attrs, string $from, int $n) {
    $e = new App\Models\RecurringJournalEntry($attrs);
    $d = Carbon\Carbon::parse($from); $out = [];
    for ($i = 0; $i < $n; $i++) { $d = $e->calculateNextRunDate($d); $out[] = $d->toDateString(); }
    return implode(' ', $out);
};
$m = $walk(['frequency' => 'monthly', 'start_date' => '2027-01-31'], '2027-01-31', 3);
check('monthly from 31 January: 28 Feb, 31 Mar, 30 Apr', $m === '2027-02-28 2027-03-31 2027-04-30', $m);
$q = $walk(['frequency' => 'quarterly', 'start_date' => '2026-11-30'], '2026-11-30', 2);
check('quarterly from 30 November: 28 Feb, then back on the 30th', $q === '2027-02-28 2027-05-30', $q);
$f = $walk(['frequency' => 'monthly', 'start_date' => '2026-11-15', 'day_of_month' => 5], '2026-11-15', 1);
check('a set day of the month is kept', $f === '2026-12-05', $f);

echo "\n$passed passed, $failed failed\n";
exit($failed ? 1 : 0);
