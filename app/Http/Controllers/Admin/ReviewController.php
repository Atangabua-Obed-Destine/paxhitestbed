<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RequireReview;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

/**
 * The review dialog for any action that posts money.
 *
 * The form's own request is replayed against its own route, with the user's
 * own permissions, inside a database transaction that is always rolled back.
 * Whatever journal entries it wrote are shown, with any refusal or warning the
 * action gave. So the review is the real rule, not a copy of it, and cannot
 * drift from what confirming does. Mail, outbound HTTP and queued jobs are
 * faked for the dry run, and the user's session is put back as it was.
 *
 * Only the routes RequireReview lists can be dry-run.
 */
class ReviewController extends Controller
{
    protected const LABELS = [
        'admin.payroll.pay' => 'Pay salary',
        'admin.payroll.unpay' => 'Reverse a salary payment',
        'admin.payroll.bulk' => 'Payroll for the ticked staff',
        'admin.tax-remittance.store' => 'Pay tax or social charges over',
        'admin.tax-remittance.void' => 'Reverse a tax payment',
        'admin.staff-loan.store' => 'Give a staff advance or loan',
        'admin.staff-loan.cancel' => 'Cancel a staff advance or loan',
        'admin.payment-account-transfer.store' => 'Transfer between cash and bank accounts',
        'admin.payment-account.deposit.post' => 'Deposit into a cash box',
        'admin.payment-account.withdraw.post' => 'Withdraw from a cash box',
        'admin.recurring-entries.process' => 'Post a recurring entry',
        'admin.recurring-entries.process-all' => 'Post every recurring entry due',
        'admin.fixed-assets.post-depreciation' => 'Post depreciation',
        'admin.fixed-assets.dispose' => 'Dispose of an asset',
        'admin.journal-entries.post' => 'Post a journal entry',
        'admin.journal-entries.unpost' => 'Un-post a journal entry',
        'admin.student-credits.apply-to-fee' => 'Use a student\'s credit on a fee',
        'admin.student-credits.process-refund' => 'Refund a student\'s credit',
        'admin.year-end-closing.generate-entries' => 'Generate the year-end closing entries',
        'admin.year-end-closing.approve' => 'Approve the year-end closing',
        'admin.year-end-closing.reverse' => 'Reverse the year-end closing',
    ];

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function dryRun(Request $request)
    {
        $target = (string) $request->input('_review_target');
        $method = strtoupper((string) ($request->input('_method') ?: $request->input('_review_method') ?: 'POST'));
        $path = parse_url($target, PHP_URL_PATH) ?: '';

        // Under a sub-folder install the app's own base path comes first.
        $base = rtrim($request->getBaseUrl(), '/');
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }

        try {
            $route = app('router')->getRoutes()->match(Request::create($path, $method));
        } catch (\Throwable $e) {
            $route = null;
        }

        if (!$route || !in_array($route->getName(), RequireReview::ROUTES, true)) {
            return $this->render(['ok' => false, 'problems' => [__('This action cannot be reviewed here.')]]);
        }

        $fields = $request->except(['_review_target', '_review_method']);
        $fields['reviewed'] = 1;

        $session = $request->session();
        $sessionBefore = $session->all();
        $lastEntry = (int) JournalEntry::withTrashed()->max('id');

        Mail::fake();
        Notification::fake();
        Queue::fake();
        Http::fake();

        $p = ['ok' => true, 'problems' => [], 'warnings' => [], 'summary' => [], 'entries' => [], 'effects' => [], 'notes' => []];
        $p['summary'][] = [__('Action'), __(self::LABELS[$route->getName()] ?? 'Post to the ledger')];

        DB::beginTransaction();

        try {
            $sub = Request::create($request->getBaseUrl() . $path, $method, $fields, $request->cookies->all(), [], $request->server->all());
            $sub->setLaravelSession($session);
            $sub->headers->set('referer', (string) $request->headers->get('referer'));
            $sub->headers->set('accept', 'text/html');

            $response = app(\Illuminate\Contracts\Http\Kernel::class)->handle($sub);
            app()->instance('request', $request);

            if ($response->getStatusCode() >= 400) {
                $p['problems'][] = __('The action was refused (:status).', ['status' => $response->getStatusCode()]);
            }

            // What the action said, as it would have said it on the screen.
            // Validation refusals. ViewErrorBag answers all() through __call,
            // so method_exists() cannot be used to ask for it.
            $errors = $session->get('errors');
            if ($errors instanceof \Illuminate\Support\ViewErrorBag) {
                foreach ($errors->getBags() as $bag) {
                    foreach ($bag->all() as $message) {
                        $p['problems'][] = $message;
                    }
                }
            } elseif ($errors instanceof \Illuminate\Support\MessageBag) {
                foreach ($errors->all() as $message) {
                    $p['problems'][] = $message;
                }
            }

            foreach ($this->flashed() as [$type, $message]) {
                if ($type === 'error') {
                    $p['problems'][] = $message;
                } elseif ($type === 'warning') {
                    $p['warnings'][] = $message;
                } elseif ($message !== '') {
                    $p['notes'][] = __('It would report: :message', ['message' => $message]);
                }
            }

            $entries = JournalEntry::with('lines')->where('id', '>', $lastEntry)->orderBy('id')->get();
            foreach ($entries as $entry) {
                $p['entries'][] = $this->describe($entry);
            }
        } catch (\Throwable $e) {
            $p['problems'][] = $e->getMessage();
        } finally {
            DB::rollBack();
            app()->instance('request', $request);
            $session->flush();
            $session->put($sessionBefore);
        }

        $p['problems'] = array_values(array_unique($p['problems']));

        if ($p['problems']) {
            $p['ok'] = false;
            $p['entries'] = [];
        } elseif (!$p['entries']) {
            $p['notes'][] = __('This posts nothing to the ledger.');
        }

        $p['notes'][] = __('Nothing has been saved yet: this is what confirming will do.');

        return $this->render($p);
    }

    /** Flash messages the dry run left in the session, as [type, text]. */
    protected function flashed(): array
    {
        $out = [];

        try {
            foreach (app('flasher.storage_manager')->all() as $envelope) {
                $type = method_exists($envelope, 'getType') ? $envelope->getType() : 'info';
                $text = method_exists($envelope, 'getMessage') ? trim(strip_tags((string) $envelope->getMessage())) : '';
                $out[] = [$type, $text];
            }
        } catch (\Throwable $e) {
            // No flasher storage: nothing to read.
        }

        foreach (['error', 'success', 'warning'] as $key) {
            if (session()->has($key) && is_string(session($key))) {
                $out[] = [$key, session($key)];
            }
        }

        return $out;
    }

    protected function describe(JournalEntry $entry): array
    {
        $accounts = ChartOfAccount::whereIn('id', $entry->lines->pluck('account_id'))->get()->keyBy('id');

        return [
            'title' => __('Ledger entry to be posted'),
            'date' => $entry->entry_date ? $entry->entry_date->format('j F Y') : '',
            'period' => optional($entry->accountingPeriod)->name,
            'description' => $entry->description,
            'lines' => $entry->lines->map(fn ($l) => [
                'account' => optional($accounts->get($l->account_id))->account_code . ' ' . optional($accounts->get($l->account_id))->account_name,
                'description' => $l->description,
                'debit' => (float) $l->debit,
                'credit' => (float) $l->credit,
            ])->all(),
            'debit' => (float) $entry->lines->sum('debit'),
            'credit' => (float) $entry->lines->sum('credit'),
        ];
    }

    protected function render(array $p)
    {
        $p += ['ok' => true, 'problems' => [], 'warnings' => [], 'summary' => [], 'entries' => [], 'effects' => [], 'notes' => []];

        return view('admin.supplier-bill.partials.preview', ['p' => $p]);
    }
}
