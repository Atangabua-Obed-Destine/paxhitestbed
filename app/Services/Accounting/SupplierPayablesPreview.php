<?php

namespace App\Services\Accounting;

use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\JournalEntry;
use App\Models\PaymentAccount;
use App\Models\SupplierBill;
use App\Models\SupplierPayment;
use App\Services\BudgetActualsService;
use Illuminate\Support\Facades\DB;

/**
 * What an action will do, shown before it is done.
 *
 * Each preview runs the real action — the same code, the same checks — inside
 * a database transaction and then rolls it back, reading what it wrote on the
 * way. So the preview cannot disagree with what confirming does: there is no
 * second copy of the rules to drift. A refusal comes back as a problem to show,
 * and the confirm button is withheld until it is fixed.
 *
 * Nothing written in a preview survives it. The ledger, the bills, the cash
 * boxes and the audit log are all in the one rolled-back transaction.
 *
 * Every preview returns the same shape, rendered by one partial:
 *   ok        bool, false when the action would be refused
 *   problems  reasons it would be refused (block confirming)
 *   warnings  things worth a second look that do not block it
 *   summary   [label, value] pairs
 *   entries   the journal entries it would post, line by line
 *   effects   tables of what changes elsewhere (bills owed, budget lines)
 */
class SupplierPayablesPreview
{
    public function __construct(protected SupplierPayables $payables)
    {
    }

    /** Approving a draft bill. */
    public function approve(SupplierBill $bill, int $userId): array
    {
        $out = $this->blank(__('Approve bill') . ' ' . $bill->number);
        $out['summary'] = [
            [__('Supplier'), $bill->supplier->title ?? '—'],
            [__('Their invoice'), $bill->supplier_reference ?: '—'],
            [__('Bill date'), format_date($bill->bill_date)],
            [__('Due date'), format_date($bill->due_date)],
            [__('Total'), format_amount($bill->total)],
        ];

        if (!$bill->supplier_reference) {
            $out['warnings'][] = __('No supplier invoice number is entered, so a second copy of this invoice could not be detected.');
        }

        $budget = $bill->isOpeningBalance() ? null : Budget::where('is_institutional', true)
            ->whereDate('start_date', '<=', $bill->bill_date)->whereDate('end_date', '>=', $bill->bill_date)->first();

        $this->dryRun($out, function () use ($bill, $userId, $budget, &$out) {
            $spentBefore = $budget ? app(BudgetActualsService::class)
                ->forPeriod(\Carbon\Carbon::parse($budget->start_date)->toDateString(), \Carbon\Carbon::parse($budget->end_date)->toDateString())['lines'] : [];

            $approved = $this->payables->approve($bill, $userId);
            $out['entries'][] = $this->entry($approved->journal_entry_id);

            if ($bill->isOpeningBalance()) {
                $out['notes'][] = __('An opening balance is last year\'s cost: it is set against equity and does not touch this year\'s budget.');

                return;
            }

            $added = SupplierPayables::budgetLedgerLines()
                ->where('je.id', $approved->journal_entry_id)
                ->selectRaw('m.budget_line_id as line_id, SUM(jl.debit - jl.credit) as amount')
                ->groupBy('line_id')->pluck('amount', 'line_id');

            $rows = [];
            foreach ($added as $lineId => $amount) {
                $line = BudgetLine::find($lineId);
                $budgeted = $budget ? (float) DB::table('budget_allocations')->where('budget_id', $budget->id)
                    ->where('budget_line_id', $lineId)->sum('allocated_amount') : null;
                $before = (float) ($spentBefore[$lineId] ?? 0);
                $after = $before + (float) $amount;

                $rows[] = [
                    ($line ? $line->code . ' — ' . $line->name : '#' . $lineId),
                    format_amount($budgeted),
                    format_amount($before),
                    format_amount($amount),
                    format_amount($after),
                    $budgeted !== null && $budgeted > 0 ? format_amount($budgeted - $after) : '—',
                ];

                if ($budgeted !== null && $after > $budgeted + 0.005) {
                    $out['warnings'][] = __('Budget line :line goes over its budget by :amount with this bill.', [
                        'line' => $line ? $line->code . ' ' . $line->name : '#' . $lineId,
                        'amount' => format_amount($after - $budgeted),
                    ]);
                }
            }

            $out['effects'][] = [
                'title' => $budget ? __('Budget lines (sheet :budget)', ['budget' => $budget->title]) : __('Budget lines'),
                'columns' => [__('Budget line'), __('Budgeted'), __('Spent so far'), __('This bill'), __('Spent after'), __('Left')],
                'numeric' => [1, 2, 3, 4, 5],
                'rows' => $rows,
            ];

            if (!$budget) {
                $out['notes'][] = __('No institutional budget sheet covers the bill date, so there is no budgeted amount to compare against.');
            }
        });

        return $out;
    }

    /** Voiding an approved bill. */
    public function voidBill(SupplierBill $bill, int $userId): array
    {
        $out = $this->blank(__('Void bill') . ' ' . $bill->number);
        $out['summary'] = [
            [__('Supplier'), $bill->supplier->title ?? '—'],
            [__('Total'), format_amount($bill->total)],
        ];

        $this->dryRun($out, function () use ($bill, $userId, &$out) {
            $voided = $this->payables->voidBill($bill, $userId, 'preview');
            $out['entries'][] = $this->entry($voided->void_journal_entry_id, __('Reversing entry to be posted'));
            $out['notes'][] = $this->reversalDateNote($voided->void_journal_entry_id);
            $out['notes'][] = __('The cost comes off its budget line and the debt off the supplier account. The bill stays on record, marked void.');
        });

        return $out;
    }

    /**
     * Recording a payment, from the form as it stands.
     *
     * Shows each bill before and after, the entry, and the cash box balance
     * before and after when one is used.
     */
    public function pay(array $data, int $userId): array
    {
        $out = $this->blank(__('Record payment'));

        $before = $this->payables->openBills((int) ($data['supplier_id'] ?? 0))->keyBy('id');
        $box = !empty($data['payment_account_id']) ? PaymentAccount::find($data['payment_account_id']) : null;
        $boxBefore = $box ? (float) $box->current_balance : null;

        $this->dryRun($out, function () use ($data, $userId, $before, $box, $boxBefore, &$out) {
            $payment = $this->payables->pay($data, $userId);

            $out['summary'] = [
                [__('Paid to'), $payment->supplier->title ?? '—'],
                [__('Date'), format_date($payment->payment_date)],
                [__('Amount'), format_amount($payment->amount)],
                [__('Method'), \App\Support\PaymentMethod::label($payment->payment_method)],
                [__('Paid from'), $payment->cashAccount ? $payment->cashAccount->account_code . ' ' . $payment->cashAccount->account_name : '—'],
                [__('Cheque or transfer number'), $payment->reference ?: '—'],
            ];

            $out['entries'][] = $this->entry($payment->journal_entry_id);

            if ($mismatch = $this->methodMismatch($payment)) {
                $out['warnings'][] = $mismatch;
            }

            $after = $this->payables->openBills($payment->supplier_id)->keyBy('id');
            $rows = [];
            foreach ($payment->allocations as $a) {
                $b = $before[$a->supplier_bill_id] ?? null;
                $left = (float) ($after[$a->supplier_bill_id]->owed ?? 0);
                $rows[] = [
                    $b->number ?? '#' . $a->supplier_bill_id,
                    $b->supplier_reference ?? '—',
                    format_amount($b->owed ?? 0),
                    format_amount($a->amount),
                    format_amount($left),
                    $left <= 0.005 ? __('Paid') : __('Part paid'),
                ];
            }
            $out['effects'][] = [
                'title' => __('Bills'),
                'columns' => [__('Bill'), __('Their invoice'), __('Owed before'), __('This payment'), __('Owed after'), __('Will read')],
                'numeric' => [2, 3, 4],
                'rows' => $rows,
            ];

            if ($box) {
                $out['effects'][] = [
                    'title' => __('Cash box or bank'),
                    'columns' => [__('Account'), __('Balance before'), __('Balance after')],
                    'numeric' => [1, 2],
                    'rows' => [[$box->title, format_amount($boxBefore), format_amount($box->fresh()->current_balance)]],
                ];
            }

            $stillOwed = round($after->sum('owed'), 2);
            $out['notes'][] = __('Still owed to this supplier after this payment: :amount.', ['amount' => format_amount($stillOwed)]);
        });

        return $out;
    }

    /** Voiding a payment. */
    public function voidPayment(SupplierPayment $payment, int $userId): array
    {
        $out = $this->blank(__('Void payment') . ' ' . $payment->number);
        $out['summary'] = [
            [__('Paid to'), $payment->supplier->title ?? '—'],
            [__('Date'), format_date($payment->payment_date)],
            [__('Amount'), format_amount($payment->amount)],
        ];

        $this->dryRun($out, function () use ($payment, $userId, &$out) {
            $voided = $this->payables->voidPayment($payment, $userId, 'preview');
            $out['entries'][] = $this->entry($voided->void_journal_entry_id, __('Reversing entry to be posted'));
            $out['notes'][] = $this->reversalDateNote($voided->void_journal_entry_id);

            $rows = [];
            foreach ($payment->allocations()->with('bill')->get() as $a) {
                $rows[] = [$a->bill->number ?? '#', format_amount($a->amount), format_amount($this->payables->owedOn($a->bill->fresh()))];
            }
            $out['effects'][] = [
                'title' => __('Bills owed again'),
                'columns' => [__('Bill'), __('Paid by this payment'), __('Owed after voiding')],
                'numeric' => [1, 2],
                'rows' => $rows,
            ];

            if ($payment->payment_account_id && $box = PaymentAccount::find($payment->payment_account_id)) {
                $out['notes'][] = __('The money goes back into :box.', ['box' => $box->title]);
            }
        });

        return $out;
    }

    /* ------------------------------------------------------------------ */

    private function blank(string $heading): array
    {
        return ['heading' => $heading, 'ok' => true, 'problems' => [], 'warnings' => [], 'summary' => [],
            'entries' => [], 'effects' => [], 'notes' => []];
    }

    /** Run $fn and roll everything back; a refusal becomes a problem. */
    private function dryRun(array &$out, callable $fn): void
    {
        DB::beginTransaction();

        try {
            $fn();
        } catch (\RuntimeException $e) {
            $out['ok'] = false;
            $out['problems'][] = $e->getMessage();
        } catch (\Throwable $e) {
            $out['ok'] = false;
            $out['problems'][] = $e->getMessage();
        } finally {
            DB::rollBack();
        }

        // A problem leaves nothing meaningful to show of half an action.
        if (!$out['ok']) {
            $out['entries'] = [];
            $out['effects'] = [];
        }
    }

    /**
     * A method that does not match the account the money leaves: "bank
     * transfer" taken out of the cash box is usually a slip, and it would put
     * the bank and the cash box both wrong until someone reconciles them.
     */
    private function methodMismatch(SupplierPayment $payment): ?string
    {
        $code = (string) ($payment->cashAccount->account_code ?? '');
        $kinds = [
            \App\Support\PaymentMethod::CASH => ['57', __('a cash account')],
            \App\Support\PaymentMethod::BANK => ['52', __('a bank account')],
            \App\Support\PaymentMethod::MTN_MOMO => ['53', __('a mobile money account')],
            \App\Support\PaymentMethod::ORANGE_MONEY => ['53', __('a mobile money account')],
        ];
        $expected = $kinds[(int) $payment->payment_method] ?? null;

        if (!$expected || $code === '' || str_starts_with($code, $expected[0])) {
            return null;
        }

        return __('The method is :method, but the money is taken from :account, which is not :kind. Check the method and the account match.', [
            'method' => \App\Support\PaymentMethod::label($payment->payment_method),
            'account' => $code . ' ' . ($payment->cashAccount->account_name ?? ''),
            'kind' => $expected[1],
        ]);
    }

    /** Why a reversal carries the date it does. */
    private function reversalDateNote(?int $reversalId): string
    {
        $reversal = JournalEntry::find($reversalId);
        $original = $reversal ? JournalEntry::find($reversal->reversed_entry_id) : null;

        if ($reversal && $original && $reversal->entry_date->toDateString() === $original->entry_date->toDateString()) {
            return __('Dated :date, the same day as the original, because that month is still open: the month will read as if the original had never been posted.',
                ['date' => format_date($reversal->entry_date)]);
        }

        return __('Dated today, because the month of the original is closed: its figures stay as reported and the correction appears in this month.');
    }

    /** A journal entry as the preview shows it. */
    private function entry(?int $id, ?string $title = null): array
    {
        $e = JournalEntry::with(['lines.account', 'accountingPeriod', 'fiscalYear'])->find($id);
        if (!$e) {
            return [];
        }

        return [
            'title' => $title,
            'date' => format_date($e->entry_date),
            'period' => trim(($e->accountingPeriod->name ?? '') . ' · ' . ($e->fiscalYear->name ?? ''), ' ·'),
            'description' => $e->description,
            'type' => $e->journal_type,
            'lines' => $e->lines->map(fn ($l) => [
                'account' => ($l->account->account_code ?? '') . ' ' . ($l->account->account_name ?? ''),
                'description' => $l->description,
                'debit' => (float) $l->debit,
                'credit' => (float) $l->credit,
            ])->all(),
            'debit' => (float) $e->total_debit,
            'credit' => (float) $e->total_credit,
        ];
    }
}
