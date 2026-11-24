<?php

/**
 * Running payroll for a whole staff: bulk generate, approval, bulk pay, the
 * bank file, emailed payslips, staff seeing their own payslips, loans and
 * advances repaid through payroll, part-month pay, and the settings page that
 * governs all of it.
 *
 * Everything goes through the real routes. Expected figures are worked out
 * here (rates from the breakdown service, days counted by hand, CSV rows
 * rebuilt from the records), not read back from the code under test.
 *
 * Mail goes to an in-memory mailer: nothing is sent. Everything runs inside
 * one transaction that is rolled back.
 *
 *   php scripts/payroll_operations_test.php
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\JournalEntry;
use App\Models\Payroll;
use App\Models\PayrollSetting;
use App\Models\StaffBankAccount;
use App\Models\StaffLoan;
use App\Services\PayrollCalculator;
use App\Services\PayrollTaxBreakdownService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

$passed = 0;
$failed = 0;

function check(string $what, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    $ok ? $passed++ : $failed++;
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $what . (!$ok && $detail !== '' ? " — $detail" : '') . "\n";
}

function section(string $title): void
{
    echo "\n$title\n";
}

// Mail to memory, never out.
config(['mail.default' => 'array', 'mail.driver' => 'array', 'mail.mailers.array' => ['transport' => 'array'], 'mail.from.address' => 'payroll@example.invalid', 'mail.from.name' => 'Payroll']);
app('mail.manager')->forgetMailers();
Illuminate\Support\Facades\Mail::clearResolvedInstances();
$sent = fn () => app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();

app('session.store')->start();
$admin = App\User::whereHas('roles', fn ($q) => $q->where('name', 'Super Admin'))->first();
Auth::guard('web')->login($admin);

$call = function (string $method, string $path, array $data = []) use ($kernel) {
    app('flasher.storage_manager')->clear();
    if ($method !== 'GET') {
        $data['_token'] = csrf_token();
    }
    $request = Request::create($path, $method, $data);
    $request->setLaravelSession(app('session.store'));
    $request->headers->set('referer', 'http://localhost/admin/staff/payroll');

    return $kernel->handle($request);
};
// Flasher keeps its messages in memory when run from the command line.
$flash = fn () => collect(app('flasher.storage_manager')->all())->map(fn ($e) => method_exists($e, 'getMessage') ? $e->getMessage() : '')->implode(' | ');
$bulk = fn () => session('payroll_bulk_result');
$settings = function (array $values) {
    PayrollSetting::current()->fill($values)->save();
    PayrollSetting::flush();
};
$close = fn ($a, $b, $tol = 0.01) => abs((float) $a - (float) $b) <= $tol;

$logFile = storage_path('logs/laravel-' . date('Y-m-d') . '.log');
$logBefore = is_file($logFile) ? filesize($logFile) : 0;

DB::beginTransaction();
$level = DB::transactionLevel();

try {
    $nov = '2026-11-01';
    $calc = app(PayrollCalculator::class);
    $breakdown = app(PayrollTaxBreakdownService::class);

    // --- Fixtures: staff of our own, in a department of our own -------------
    $dept = App\Models\Department::create(['title' => 'Ops Test', 'slug' => 'ops-test-' . uniqid(), 'status' => 1]);
    $template = App\User::where('status', 1)->where('salary_type', 1)->where('basic_salary', '>', 0)
        ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['Super Admin', 'Admin']))->firstOrFail();
    $n = 0;
    $make = function (string $first, float $salary, bool $account = true, array $extra = []) use ($template, $dept, &$n) {
        $n++;
        $u = $template->replicate();
        $u->fill(['first_name' => $first, 'last_name' => 'Ops']);
        $u->staff_id = 'OPS-' . $n;
        $u->email = strtolower($first) . '.ops' . uniqid() . '@example.invalid';
        $u->department_id = $dept->id;
        $u->salary_type = 1;
        $u->basic_salary = $salary;
        $u->joining_date = '2020-01-01';
        $u->ending_date = null;
        $u->photo = null;
        foreach ($extra as $k => $v) {
            $u->$k = $v;
        }
        $u->save();
        if ($account) {
            StaffBankAccount::create(['user_id' => $u->id, 'bank_name' => 'Ops Bank', 'account_name' => "$first Ops", 'account_number' => '000-' . $u->id, 'branch' => 'Main', 'ifsc_code' => 'OB01', 'is_default' => 1, 'status' => 1]);
        }
        return $u->fresh();
    };
    $A = $make('Alice', 300000);
    $B = $make('Bruno', 250000);
    $C = $make('Chloe', 200000, false);
    $D = $make('Dina', 180000);
    $E = $make('Eric', 0);

    // ------------------------------------------------------------------------
    section('Settings');
    // ------------------------------------------------------------------------
    $s = PayrollSetting::current();
    check('the defaults change nothing: no approval, no proration, no emailing', !$s->require_approval && !$s->prorate_partial_months && !$s->email_payslip_on_pay);
    check('staff payslips are on and last month\'s lines carry over by default', $s->staff_payslips_enabled && $s->carry_over_lines);
    check('the settings page opens', $call('GET', '/admin/staff/payroll-setting')->getStatusCode() === 200);

    $form = [
        'require_approval' => 0, 'carry_over_lines' => 1, 'prorate_partial_months' => 0, 'proration_basis' => 'calendar_days',
        'staff_payslips_enabled' => 1, 'email_payslip_on_pay' => 0,
        'bank_file_columns' => ['amount', 'staff_id', 'account_number', 'name', 'reference'],
        'bank_file_order' => ['staff_id' => 1, 'name' => 2, 'account_number' => 3, 'amount' => 4, 'reference' => 5],
        'bank_file_delimiter' => ';', 'bank_file_header' => 1, 'bank_file_reference' => 'SAL {MONTH} {YEAR} {STAFF_ID}', 'bank_file_decimals' => 0,
        'advance_account_id' => '', 'advance_payment_account_id' => '',
    ];
    $call('POST', '/admin/staff/payroll-setting', $form);
    PayrollSetting::flush();
    $s = PayrollSetting::current();
    check('saving keeps the bank columns in the numbered order, not the ticked order', $s->bankColumns() === ['staff_id', 'name', 'account_number', 'amount', 'reference'], json_encode($s->bankColumns()));
    check('the separator and reference are saved', $s->bank_file_delimiter === ';' && $s->bank_file_reference === 'SAL {MONTH} {YEAR} {STAFF_ID}');

    $call('POST', '/admin/staff/payroll-setting', array_merge($form, ['bank_file_columns' => []]));
    PayrollSetting::flush();
    check('a bank file with no columns is refused', PayrollSetting::current()->bankColumns() === ['staff_id', 'name', 'account_number', 'amount', 'reference']);
    $call('POST', '/admin/staff/payroll-setting', array_merge($form, ['bank_file_columns' => ['amount', 'password']]));
    PayrollSetting::flush();
    check('a column that does not exist is refused', PayrollSetting::current()->bankColumns() === ['staff_id', 'name', 'account_number', 'amount', 'reference']);
    $call('POST', '/admin/staff/payroll-setting', array_merge($form, ['advance_account_id' => 151, 'advance_payment_account_id' => 151]));
    PayrollSetting::flush();
    check('loans cannot be booked to and paid from the same account', PayrollSetting::current()->advance_account_id === null);

    // ------------------------------------------------------------------------
    section('Bulk generate');
    // ------------------------------------------------------------------------
    // Alice's October payroll is what November carries over from.
    $oct = $calc->calculate($A, '2026-10-01', [['title' => 'Transport Allowance', 'amount' => 25000]], [['title' => 'Union dues', 'amount' => 2000]]);
    check('October can be worked out (the rates start in October)', empty($oct['problems']), implode(' ', $oct['problems']));
    app(App\Services\PayrollWriter::class)->save($A, '2026-10-01', $oct, $admin->id);

    $call('POST', '/admin/staff/payroll-bulk', ['action' => 'generate', 'month' => 11, 'year' => 2026, 'users' => [$A->id, $B->id, $C->id, $E->id]]);
    $r = $bulk();
    check('three are generated and one is skipped', $r && count($r['done']) === 3 && count($r['skipped']) === 1, json_encode($r));
    check('the one skipped is named, with the reason', $r && str_contains(implode(' ', $r['skipped']), 'Eric Ops') && str_contains(implode(' ', $r['skipped']), 'no salary set'), json_encode($r['skipped'] ?? null));

    $pA = Payroll::with('details')->where('user_id', $A->id)->where('salary_month', $nov)->first();
    check('Alice starts from October\'s lines', $pA && $pA->details->where('status', 1)->pluck('amount', 'title')->map(fn ($v) => (float) $v)->all() === ['Transport Allowance' => 25000.0]
        && $pA->details->where('status', 0)->pluck('amount', 'title')->map(fn ($v) => (float) $v)->all() === ['Union dues' => 2000.0]);
    $taxA = array_sum(array_map(fn ($l) => round($l['employee'], 2), $breakdown->forSalary(300000, Carbon::parse($nov), $breakdown->exemptionsFor($A->id, Carbon::parse($nov)))));
    check('her figures are worked out as the page would: gross = 300,000 + 25,000 - 2,000', $pA && $close($pA->gross_salary, 323000));
    check('tax on the basic only (transport is not taxed), net = gross - tax', $pA && $close($pA->tax, $taxA) && $close($pA->net_salary, 323000 - $taxA), $pA ? "tax {$pA->tax} expected $taxA" : '');
    check('her taxes are itemised like a payroll saved from the page', $pA && $close($pA->taxLines()->sum('employee_amount'), $pA->tax));
    $pB = Payroll::with('details')->where('user_id', $B->id)->where('salary_month', $nov)->first();
    check('Bruno, with no earlier payroll, starts with no lines', $pB && $pB->details->isEmpty() && $close($pB->gross_salary, 250000));
    check('Eric, with no salary, got no payroll', !Payroll::where('user_id', $E->id)->exists());

    $call('POST', '/admin/staff/payroll-bulk', ['action' => 'generate', 'month' => 11, 'year' => 2026, 'users' => [$A->id, $B->id]]);
    $r = $bulk();
    check('generating again skips those already generated, leaving them alone', $r && count($r['done']) === 0 && count($r['skipped']) === 2 && Payroll::where('user_id', $A->id)->where('salary_month', $nov)->value('id') === $pA->id);

    $settings(['carry_over_lines' => false]);
    $call('POST', '/admin/staff/payroll-bulk', ['action' => 'generate', 'month' => 12, 'year' => 2026, 'users' => [$A->id]]);
    $decA = Payroll::with('details')->where('user_id', $A->id)->where('salary_month', '2026-12-01')->first();
    check('with carry-over off, a new payroll starts empty', $decA && $decA->details->isEmpty());
    $settings(['carry_over_lines' => true]);
    $decA->details()->delete();
    $decA->taxLines()->delete();
    $decA->delete();

    $page = $call('GET', "/admin/staff/payroll-generate/{$D->id}/11/2026")->getContent();
    check('the single page shows no carry-over note when there is nothing to carry', !str_contains($page, 'Started from the allowances'));
    $page = $call('GET', "/admin/staff/payroll-generate/{$A->id}/12/2026")->getContent();
    check('the single page for a new month starts from last month\'s lines and says so', str_contains($page, 'Started from the allowances and deductions of November 2026') && str_contains($page, 'value="Union dues"'));

    $call('POST', '/admin/staff/payroll-bulk', ['action' => 'generate', 'month' => 11, 'year' => 2026, 'users' => []]);
    check('nothing ticked is refused', str_contains(json_encode(session('errors') ? session('errors')->all() : []), 'Tick at least one'));

    // ------------------------------------------------------------------------
    section('Approval');
    // ------------------------------------------------------------------------
    $call('POST', '/admin/staff/payroll-bulk', ['action' => 'generate', 'month' => 11, 'year' => 2026, 'users' => [$D->id]]);
    $pD = Payroll::where('user_id', $D->id)->where('salary_month', $nov)->first();
    $settings(['require_approval' => true]);

    $accountA = StaffBankAccount::where('user_id', $A->id)->value('id');
    $call('POST', "/admin/staff/payroll-pay/{$pA->id}", ['pay_date' => date('Y-m-d'), 'payment_method' => 4, 'bank_account_id' => $accountA]);
    check('with approval required, an unapproved payroll cannot be paid', Payroll::find($pA->id)->status == 0 && str_contains($flash(), 'not been approved'), $flash());

    $list = $call('GET', "/admin/staff/payroll?salary_type=1&department={$dept->id}&month=11&year=2026")->getContent();
    check('the list says "Awaiting approval" and offers Approve instead of Pay', str_contains($list, 'Awaiting approval') && str_contains($list, "payroll-approve/{$pA->id}"));

    $call('POST', "/admin/staff/payroll-approve/{$pA->id}");
    $pA->refresh();
    check('approving stamps who and when', $pA->approved_at && (int) $pA->approved_by === $admin->id);
    check('the approve route needs the payroll-approve permission', in_array('permission:payroll-approve', app('router')->getRoutes()->getByName('admin.payroll.approve')->gatherMiddleware()));

    $page = $call('GET', "/admin/staff/payroll-generate/{$A->id}/11/2026")->getContent();
    $preview = json_decode($call('POST', '/admin/staff/payroll-preview', ['user_id' => $A->id, 'salary_month' => $nov, 'allowance_titles' => ['Transport Allowance'], 'allowances' => [30000], 'deduction_titles' => ['Union dues'], 'deductions' => [2000], 'deduction_loans' => ['']])->getContent(), true);
    $call('POST', '/admin/staff/payroll', ['user_id' => $A->id, 'salary_month' => $nov, 'allowance_titles' => ['Transport Allowance'], 'allowances' => [30000], 'deduction_titles' => ['Union dues'], 'deductions' => [2000], 'deduction_loans' => [''], 'net_salary' => $preview['net_salary'], 'tax' => $preview['tax']]);
    $pA->refresh();
    check('changing the figures clears the approval', $close($pA->total_allowance, 30000) && $pA->approved_at === null, "allowance {$pA->total_allowance}");

    $call('POST', '/admin/staff/payroll-bulk', ['action' => 'approve', 'month' => 11, 'year' => 2026, 'users' => [$A->id, $B->id, $C->id, $E->id]]);
    $r = $bulk();
    check('bulk approve approves the generated and skips the rest by name', $r && count($r['done']) === 3 && count($r['skipped']) === 1 && str_contains($r['skipped'][0], 'not generated'), json_encode($r));

    // ------------------------------------------------------------------------
    section('Bank file');
    // ------------------------------------------------------------------------
    $res = $call('GET', '/admin/staff/payroll-bank-file?' . http_build_query(['month' => 11, 'year' => 2026, 'which' => 'unpaid', 'users' => [$A->id, $B->id, $C->id, $D->id]]));
    $csv = $res->getContent();
    $lines = array_values(array_filter(explode("\n", trim(str_replace("\r", '', $csv)))));
    check('it downloads as a CSV file', str_contains((string) $res->headers->get('Content-Disposition'), 'salaries-2026-11-unpaid.csv'), (string) $res->headers->get('Content-Disposition'));
    check('the header is the columns in the order set', ($lines[0] ?? '') === 'Staff ID;"Staff name";"Account number";"Amount (net pay)";"Payment reference"' || ($lines[0] ?? '') === '"Staff ID";"Staff name";"Account number";"Amount (net pay)";"Payment reference"', $lines[0] ?? '');
    $pA->refresh();
    $pB = Payroll::find($pB->id);
    $expectA = implode(';', ['OPS-1', '"Alice Ops"', '000-' . $A->id, number_format(round($pA->net_salary), 0, '.', ''), '"SAL NOV 2026 OPS-1"']);
    check('Alice\'s row: id, name, her account, net pay with no decimals, the reference filled in', in_array($expectA, $lines), json_encode($lines));
    check('only approved payrolls go in while approval is on (Dina is not approved)', !str_contains($csv, 'Dina'));
    check('Chloe has no account, so she is left out and reported', !str_contains($csv, 'Chloe') && str_contains(rawurldecode((string) $res->headers->get('X-Payroll-Missing')), 'Chloe Ops'));
    check('the row count is reported', $res->headers->get('X-Payroll-Rows') === '2');

    $settings(['require_approval' => false, 'bank_file_header' => false, 'bank_file_delimiter' => 'tab', 'bank_file_decimals' => 2]);
    StaffBankAccount::where('user_id', $B->id)->update(['account_name' => '=HYPERLINK("x")']);
    $settings(['bank_file_columns' => ['account_name', 'amount']]);
    $csv = $call('GET', '/admin/staff/payroll-bank-file?' . http_build_query(['month' => 11, 'year' => 2026, 'which' => 'all', 'users' => [$B->id]]))->getContent();
    check('no header, tab-separated, two decimals', str_replace("\r", '', trim($csv)) === "\"'=HYPERLINK(\"\"x\"\")\"\t" . number_format($pB->net_salary, 2, '.', '') || str_replace("\r", '', trim($csv)) === "'=HYPERLINK(\"x\")\t" . number_format($pB->net_salary, 2, '.', ''), json_encode($csv));
    check('a value a spreadsheet would run as a formula is defused', str_contains($csv, "'=HYPERLINK"));
    $settings(['bank_file_header' => true, 'bank_file_delimiter' => ',', 'bank_file_decimals' => 0, 'bank_file_columns' => PayrollSetting::DEFAULT_BANK_COLUMNS]);

    $call('GET', '/admin/staff/payroll-bank-file?' . http_build_query(['month' => 3, 'year' => 2020, 'which' => 'paid']));
    check('a month with nothing to pay gives a message, not an empty file', str_contains($flash(), 'nothing to put in a bank file'));

    // ------------------------------------------------------------------------
    section('Bulk pay');
    // ------------------------------------------------------------------------
    $settings(['require_approval' => true]);
    $entriesBefore = JournalEntry::where('reference_type', 'payroll')->count();
    $call('POST', '/admin/staff/payroll-bulk', ['action' => 'pay', 'month' => 11, 'year' => 2026, 'users' => [$A->id, $B->id, $C->id, $D->id, $E->id], 'pay_date' => date('Y-m-d'), 'payment_method' => 4, 'note' => 'November']);
    $r = $bulk();
    $paid = Payroll::where('salary_month', $nov)->whereIn('user_id', [$A->id, $B->id, $C->id, $D->id])->where('status', 1)->pluck('user_id')->all();
    check('Alice and Bruno are paid', in_array($A->id, $paid) && in_array($B->id, $paid));
    check('Chloe (no account), Dina (not approved) and Eric (not generated) are skipped by name', $r && count($r['skipped']) === 3
        && str_contains(implode(' ', $r['skipped']), 'Chloe Ops (OPS-3): no payment account')
        && str_contains(implode(' ', $r['skipped']), 'Dina Ops (OPS-4): This payroll has not been approved')
        && str_contains(implode(' ', $r['skipped']), 'Eric Ops (OPS-5): not generated'), json_encode($r['skipped'] ?? null));
    $pA->refresh();
    check('each is paid into the staff member\'s own default account, on the date given', (int) $pA->bank_account_id === $accountA && $pA->pay_date == date('Y-m-d') && $pA->note === 'November');
    check('each payment has its own journal entry, as a single payment would', JournalEntry::where('reference_type', 'payroll')->count() === $entriesBefore + 2);
    check('each paid payroll keeps its tax lines', $close($pA->taxLines()->sum('employee_amount'), $pA->tax));

    $call('POST', '/admin/staff/payroll-bulk', ['action' => 'pay', 'month' => 11, 'year' => 2026, 'users' => [$A->id], 'pay_date' => date('Y-m-d'), 'payment_method' => 4]);
    check('paying again skips the paid ones', ($bulk()['skipped'][0] ?? '') === 'Alice Ops (OPS-1): already paid' && JournalEntry::where('reference_type', 'payroll')->count() === $entriesBefore + 2);
    $call('POST', '/admin/staff/payroll-bulk', ['action' => 'pay', 'month' => 11, 'year' => 2026, 'users' => [$D->id], 'pay_date' => date('Y-m-d', strtotime('+3 days')), 'payment_method' => 4]);
    check('a pay date in the future is refused', Payroll::find($pD->id)->status == 0);
    $settings(['require_approval' => false]);

    // ------------------------------------------------------------------------
    section('Payslips by email');
    // ------------------------------------------------------------------------
    if (!App\Models\PrintSetting::where('slug', 'pay-slip')->exists()) {
        App\Models\PrintSetting::create(['slug' => 'pay-slip', 'title' => 'Pay Slip', 'width' => '800px', 'height' => 'auto', 'status' => 1]);
    }
    $settings(['email_payslip_on_pay' => true]);
    $before = count($sent());
    $accountD = StaffBankAccount::where('user_id', $D->id)->value('id');
    $call('POST', "/admin/staff/payroll-pay/{$pD->id}", ['pay_date' => date('Y-m-d'), 'payment_method' => 2, 'bank_account_id' => $accountD]);
    $messages = $sent();
    $mail = $messages[count($messages) - 1] ?? null;
    $email = $mail ? $mail->getOriginalMessage() : null;
    check('paying emails the payslip to the staff member', count($messages) === $before + 1 && $email && $email->getTo()[0]->getAddress() === $D->email, count($messages) . ' sent');
    $attachment = $email ? ($email->getAttachments()[0] ?? null) : null;
    check('the payslip is attached as a PDF', $attachment && str_starts_with($attachment->getBody(), '%PDF') && str_ends_with($attachment->getFilename(), '2026-11.pdf'), $attachment ? $attachment->getFilename() : 'no attachment');
    check('the subject names the month', $email && $email->getSubject() === 'Your payslip for November 2026');
    check('the payroll records that it was emailed', Payroll::find($pD->id)->payslip_emailed_at !== null);
    check('the screen says the payslip was emailed', str_contains($flash(), 'emailed'), $flash());

    $before = count($sent());
    $call('POST', "/admin/staff/payroll-email/{$pA->id}");
    check('a payslip can be emailed again from its row', count($sent()) === $before + 1);

    App\User::whereKey($B->id)->update(['email' => 'not-an-address']);
    $before = count($sent());
    $call('POST', "/admin/staff/payroll-email/{$pB->id}");
    check('no valid email: nothing sent, and the screen says why', count($sent()) === $before && str_contains($flash(), 'no valid email'), $flash());
    $call('POST', "/admin/staff/payroll-email/{$pD->id}");
    $settings(['email_payslip_on_pay' => false]);

    $html = app(App\Services\PayslipService::class)->data(Payroll::find($pA->id));
    $pdfHtml = view('admin.payroll.payslip-pdf', $html)->render();
    check('the PDF payslip carries the same net pay and every tax line as the printed one', str_contains($pdfHtml, number_format($pA->net_salary, 2)) && count($html['tax_lines']) === $pA->taxLines()->count());

    // ------------------------------------------------------------------------
    section('Staff see their own payslips');
    // ------------------------------------------------------------------------
    Auth::guard('web')->login($A);
    $mine = $call('GET', '/admin/my-payslips');
    check('a staff member opens My Payslips', $mine->getStatusCode() === 200, (string) $mine->getStatusCode());
    check('it lists their paid month', str_contains($mine->getContent(), 'November 2026') && str_contains($mine->getContent(), "my-payslips/{$pA->id}"));
    check('and nobody else\'s', !str_contains($mine->getContent(), "my-payslips/{$pB->id}"));
    check('they can open their own payslip', $call('GET', "/admin/my-payslips/{$pA->id}")->getStatusCode() === 200);
    $pdf = $call('GET', "/admin/my-payslips/{$pA->id}/pdf");
    check('and download it as a PDF', $pdf->getStatusCode() === 200 && str_starts_with($pdf->getContent(), '%PDF'));
    check('someone else\'s payslip is not found', $call('GET', "/admin/my-payslips/{$pB->id}")->getStatusCode() === 404 && $call('GET', "/admin/my-payslips/{$pB->id}/pdf")->getStatusCode() === 404);
    $octA = Payroll::where('user_id', $A->id)->where('salary_month', '2026-10-01')->first();
    check('an unpaid payroll of their own is not shown', $call('GET', "/admin/my-payslips/{$octA->id}")->getStatusCode() === 404);
    $settings(['staff_payslips_enabled' => false]);
    check('switched off in settings, the page is gone', $call('GET', '/admin/my-payslips')->getStatusCode() === 404);
    $settings(['staff_payslips_enabled' => true]);
    check('staff without payroll permission cannot open the payroll list', $call('GET', '/admin/staff/payroll')->getStatusCode() !== 200);
    Auth::guard('web')->login($admin);

    // ------------------------------------------------------------------------
    section('Loans and advances');
    // ------------------------------------------------------------------------
    $advance = App\Models\ChartOfAccount::where('account_code', '421')->first();
    $cash = App\Models\ChartOfAccount::where('account_code', '571')->first();
    $settings(['advance_account_id' => $advance->id, 'advance_payment_account_id' => $cash->id]);

    $call('POST', '/admin/staff/staff-loan', ['user_id' => $B->id, 'kind' => 'loan', 'title' => '', 'amount' => 50000, 'instalment' => 60000, 'start_month' => '2026-12', 'given_on' => date('Y-m-d')]);
    check('a repayment larger than the loan is refused', !StaffLoan::where('user_id', $B->id)->exists());
    $call('POST', '/admin/staff/staff-loan', ['user_id' => $B->id, 'kind' => 'loan', 'amount' => 50000, 'instalment' => 20000, 'start_month' => '2026-12', 'given_on' => date('Y-m-d', strtotime('+2 days'))]);
    check('a loan given in the future is refused', !StaffLoan::where('user_id', $B->id)->exists());

    $call('POST', '/admin/staff/staff-loan', ['user_id' => $B->id, 'kind' => 'advance', 'title' => 'Laptop loan', 'amount' => 50000, 'instalment' => 20000, 'start_month' => '2026-12', 'given_on' => date('Y-m-d')]);
    $loan = StaffLoan::where('user_id', $B->id)->first();
    check('a loan is recorded', $loan && $loan->title === 'Laptop loan' && $close($loan->balance(), 50000));
    $given = $loan ? JournalEntry::with('lines')->find($loan->journal_entry_id) : null;
    check('and booked: staff advances debited, cash credited, 50,000', $given && $given->is_posted
        && $close($given->lines->where('account_id', $advance->id)->sum('debit'), 50000)
        && $close($given->lines->where('account_id', $cash->id)->sum('credit'), 50000));

    $page = $call('GET', "/admin/staff/payroll-generate/{$B->id}/11/2026");
    check('nothing is taken before the first repayment month', $loan->dueFor($nov) == 0.0);

    $plan = [['2026-12-01', 20000.0], ['2027-01-01', 20000.0], ['2027-02-01', 10000.0], ['2027-03-01', 0.0]];
    $accountB = StaffBankAccount::where('user_id', $B->id)->value('id');
    foreach ($plan as [$month, $expected]) {
        $mm = Carbon::parse($month);
        $call('POST', '/admin/staff/payroll-bulk', ['action' => 'generate', 'month' => $mm->month, 'year' => $mm->year, 'users' => [$B->id]]);
        $p = Payroll::with('details')->where('user_id', $B->id)->where('salary_month', $month)->first();
        $line = $p ? $p->details->firstWhere('staff_loan_id', $loan->id) : null;
        if ($expected > 0) {
            check($mm->format('M Y') . ': the instalment of ' . number_format($expected) . ' is deducted', $line && $close($line->amount, $expected) && $line->title === 'Laptop loan', $p ? json_encode($p->details->toArray()) : 'no payroll: ' . json_encode($bulk()));
        } else {
            check($mm->format('M Y') . ': once repaid, nothing more is deducted', $p && !$line);
        }
        if ($p) {
            $entry = null;
            $call('POST', "/admin/staff/payroll-pay/{$p->id}", ['pay_date' => date('Y-m-d'), 'payment_method' => 2, 'bank_account_id' => $accountB]);
            if ($expected > 0) {
                $map = App\Models\TransactionMapping::where('transaction_type', 'payroll')->where('transaction_id', $p->id)->first();
                $entry = $map ? JournalEntry::with('lines')->find($map->journal_entry_id) : null;
                check($mm->format('M Y') . ': the repayment is credited to staff advances and the entry balances', $entry
                    && $close($entry->lines->where('account_id', $advance->id)->sum('credit'), $expected)
                    && $close($entry->lines->sum('debit'), $entry->lines->sum('credit')));
            }
        }
    }
    check('the loan is repaid in full', $loan->isCleared() && $close($loan->repaid(), 50000));

    // Reversing a payment puts its instalment back.
    $jan = Payroll::where('user_id', $B->id)->where('salary_month', '2027-01-01')->first();
    $call('POST', "/admin/staff/payroll-unpay/{$jan->id}");
    check('reversing January\'s payment puts 20,000 back on the loan', $close($loan->balance(), 20000), (string) $loan->balance());
    $call('POST', "/admin/staff/payroll-pay/{$jan->id}", ['pay_date' => date('Y-m-d'), 'payment_method' => 2, 'bank_account_id' => $accountB]);
    check('paying it again clears the loan again', $loan->isCleared());

    // The page and the calculator guard the amounts.
    $call('POST', '/admin/staff/staff-loan', ['user_id' => $C->id, 'kind' => 'advance', 'amount' => 30000, 'instalment' => 30000, 'start_month' => '2026-11', 'given_on' => date('Y-m-d')]);
    $loanC = StaffLoan::where('user_id', $C->id)->first();
    $over = $calc->calculate($C, $nov, [], [['title' => 'x', 'amount' => 40000, 'loan_id' => $loanC->id]], Payroll::where('user_id', $C->id)->where('salary_month', $nov)->first());
    check('taking more than is owed is refused', str_contains(implode(' ', $over['problems']), 'left to repay'), implode(' ', $over['problems']));
    $foreign = $calc->calculate($A, '2027-02-01', [], [['title' => 'x', 'amount' => 1000, 'loan_id' => $loanC->id]]);
    check('someone else\'s loan cannot be deducted', str_contains(implode(' ', $foreign['problems']), 'not an open loan of this staff member'));
    $page = $call('GET', "/admin/staff/payroll-generate/{$C->id}/11/2026")->getContent();
    check('the generate page shows the repayment as a fixed line carrying its loan', str_contains($page, 'name="deduction_loans[]" value="' . $loanC->id . '"') && str_contains($page, 'Loan repayment'));

    // Two unpaid payrolls cannot both take the last instalment.
    $call('POST', '/admin/staff/staff-loan', ['user_id' => $D->id, 'kind' => 'advance', 'amount' => 15000, 'instalment' => 15000, 'start_month' => '2026-12', 'given_on' => date('Y-m-d')]);
    $loanD = StaffLoan::where('user_id', $D->id)->first();
    $call('POST', '/admin/staff/payroll-bulk', ['action' => 'generate', 'month' => 12, 'year' => 2026, 'users' => [$D->id]]);
    $call('POST', '/admin/staff/payroll-bulk', ['action' => 'generate', 'month' => 1, 'year' => 2027, 'users' => [$D->id]]);
    $janD = Payroll::with('details')->where('user_id', $D->id)->where('salary_month', '2027-01-01')->first();
    check('an instalment already on an unpaid payroll is not taken twice', $janD && !$janD->details->firstWhere('staff_loan_id', $loanD->id));

    $call('POST', "/admin/staff/staff-loan/{$loanD->id}/cancel");
    check('a loan with a repayment on a payroll cannot be cancelled', StaffLoan::find($loanD->id)->status === 'active' && str_contains($flash(), 'cannot be cancelled'));
    $call('POST', '/admin/staff/staff-loan', ['user_id' => $A->id, 'kind' => 'loan', 'title' => 'Mistake', 'amount' => 5000, 'instalment' => 5000, 'start_month' => '2027-06', 'given_on' => date('Y-m-d')]);
    $mistake = StaffLoan::where('title', 'Mistake')->first();
    $call('POST', "/admin/staff/staff-loan/{$mistake->id}/cancel");
    $mistake->refresh();
    $rev = JournalEntry::with('lines')->find($mistake->reversal_entry_id);
    check('a loan entered by mistake is cancelled and its entry reversed', $mistake->status === 'cancelled' && $rev && $close($rev->lines->where('account_id', $cash->id)->sum('debit'), 5000));
    check('a cancelled loan is no longer deducted', $mistake->dueFor('2027-07-01') == 0.0);
    check('the loans page lists them', str_contains($call('GET', '/admin/staff/staff-loan?status=all')->getContent(), 'Laptop loan'));

    $settings(['advance_account_id' => null, 'advance_payment_account_id' => null]);
    $call('POST', '/admin/staff/staff-loan', ['user_id' => $A->id, 'kind' => 'loan', 'title' => 'Off books', 'amount' => 5000, 'instalment' => 5000, 'start_month' => '2027-06', 'given_on' => date('Y-m-d')]);
    check('with no accounts set, a loan is recorded without a journal entry', ($l = StaffLoan::where('title', 'Off books')->first()) && $l->journal_entry_id === null);

    // ------------------------------------------------------------------------
    section('Part-month pay');
    // ------------------------------------------------------------------------
    $F = $make('Fiona', 300000, true, ['joining_date' => '2026-11-16']);
    $G = $make('Gabe', 300000, true, ['ending_date' => '2026-11-10']);
    $full = $calc->calculate($F, $nov);
    check('with proration off, a starter is paid the full month', $close($full['total_earning'], 300000) && $full['paid_days'] === null);

    $settings(['prorate_partial_months' => true, 'proration_basis' => 'calendar_days']);
    $f = $calc->calculate($F, $nov);
    check('joined 16 Nov: paid 15 of 30 days = 150,000', $close($f['total_earning'], 150000) && $f['paid_days'] == 15.0 && $f['period_days'] == 30.0, json_encode([$f['total_earning'], $f['paid_days'], $f['period_days']]));
    check('and taxed on 150,000', $close($f['taxable_salary'], 150000));
    $g = $calc->calculate($G, $nov);
    check('left 10 Nov: paid 10 of 30 days = 100,000', $close($g['total_earning'], 100000) && $g['paid_days'] == 10.0);
    check('the page says why', str_contains(implode(' ', $f['notes']), 'Paid for 15 of 30 days (16 Nov to 30 Nov)'), implode(' ', $f['notes']));
    check('a full month is unaffected', $calc->calculate($A, '2027-04-01')['paid_days'] === null);

    $settings(['proration_basis' => 'working_days']);
    $weekdays = fn ($from, $to) => collect(\Carbon\CarbonPeriod::create($from, $to))->filter(fn ($d) => !$d->isWeekend())->count();
    $paidW = $weekdays('2026-11-16', '2026-11-30');
    $allW = $weekdays('2026-11-01', '2026-11-30');
    $fw = $calc->calculate($F, $nov);
    check("on working days: $paidW of $allW", $fw['paid_days'] == $paidW && $fw['period_days'] == $allW && $close($fw['total_earning'], round(300000 * $paidW / $allW, 2)), json_encode([$fw['paid_days'], $fw['period_days'], $fw['total_earning']]));

    $call('POST', '/admin/staff/payroll-bulk', ['action' => 'generate', 'month' => 11, 'year' => 2026, 'users' => [$F->id]]);
    $pF = Payroll::where('user_id', $F->id)->where('salary_month', $nov)->first();
    check('the payroll keeps the days it paid for', $pF && $pF->paid_days == $paidW && $pF->period_days == $allW);
    $slip = $call('GET', "/admin/staff/payroll-print/{$pF->id}")->getContent();
    check('and the payslip shows them', str_contains($slip, "Basic Salary ($paidW of $allW days)"));
    $settings(['prorate_partial_months' => false, 'proration_basis' => 'calendar_days']);

    // ------------------------------------------------------------------------
    section('Loose ends');
    // ------------------------------------------------------------------------
    $list = $call('GET', "/admin/staff/payroll?salary_type=1&department={$dept->id}&month=11&year=2026");
    check('the list renders with the bulk toolbar', $list->getStatusCode() === 200 && str_contains($list->getContent(), 'Generate ticked') && str_contains($list->getContent(), 'payrollBulkForm'));
    check('paid rows offer to email the payslip', str_contains($list->getContent(), "payroll-email/{$pA->id}"));
    check('the loans and settings pages open', $call('GET', '/admin/staff/staff-loan')->getStatusCode() === 200 && $call('GET', '/admin/staff/payroll-setting')->getStatusCode() === 200);
    check('no transaction left open', DB::transactionLevel() === $level, 'level ' . DB::transactionLevel());

    clearstatcache();
    $log = is_file($logFile) ? substr(file_get_contents($logFile), $logBefore) : '';
    $errors = array_filter(explode("\n", $log), fn ($l) => str_contains($l, '.ERROR:') && !str_contains($l, 'Payslip email failed'));
    check('nothing was logged as an error', empty($errors), implode(' / ', array_map(fn ($l) => substr($l, 0, 200), $errors)));
} catch (\Throwable $e) {
    check('the suite ran to the end', false, get_class($e) . ': ' . $e->getMessage() . ' at ' . basename($e->getFile()) . ':' . $e->getLine());
} finally {
    while (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    PayrollSetting::flush();
}

check('nothing left behind', !App\Models\Department::where('title', 'Ops Test')->exists() && !App\User::where('staff_id', 'like', 'OPS-%')->exists() && !StaffLoan::exists() && !PayrollSetting::query()->where('require_approval', true)->exists());

echo "\npayroll_operations_test: passed $passed, failed $failed\n";
exit($failed ? 1 : 0);
