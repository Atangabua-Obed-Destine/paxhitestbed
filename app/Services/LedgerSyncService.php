<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\DefaultAccountMapping;
use App\Models\Expense;
use App\Models\Fee;
use App\Models\Income;
use App\Models\PaymentPlanPayment;
use App\Models\TransactionMapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Which recorded transactions may be posted to the ledger, and posting them.
 *
 * Every transaction normally posts itself the moment it is recorded, through the
 * model observers. This is the catch-up path for the ones that did not: posted
 * later, by hand, from the transaction mappings screen — one at a time or a
 * selection at once.
 *
 * THE RULE HERE MUST MATCH THE OBSERVERS. A transaction synced from this screen
 * has to post exactly what would have posted had the observer caught it: the
 * same amount, the same date, the same category, the same accounts. So the
 * amount and date each type uses are copied from its observer, and the posting
 * itself is done by TransactionAutoMapService::autoMap() — the very code the
 * observers and `ledger:backfill` already use. This class decides; it never
 * builds a journal entry of its own.
 *
 * Anything that cannot be posted is refused with a reason a bursar can act on,
 * never skipped silently. A transaction with no default mapping for its
 * category is refused rather than posted to a guessed account.
 */
class LedgerSyncService
{
    /** Types this screen can post. Payroll is listed so it can be refused by name. */
    public const TYPES = ['fee', 'income', 'expense', 'payment_plan_payment', 'payroll'];

    /**
     * A ceiling per request. Every row is its own transaction and its own
     * journal entry, so a large selection is slow, not dangerous — but a
     * request that runs past the web server's limit dies half way, and the
     * person who clicked sees an error for work that partly happened.
     */
    public const MAX_PER_REQUEST = 200;

    public function __construct(protected TransactionAutoMapService $mapper)
    {
    }

    /**
     * May this transaction be posted, and if so with what?
     *
     * @return array{eligible:bool, code:string, reason:?string, category_id:?int, payload:?array}
     */
    public function eligibility(string $type, int $id): array
    {
        if (!in_array($type, self::TYPES, true)) {
            return $this->refuse('unsupported', __('This kind of transaction cannot be posted from here.'));
        }

        if ($type === 'payroll') {
            return $this->decide('payroll', null, false);
        }

        $row = $this->load($type, $id);

        $alreadyPosted = TransactionMapping::where('transaction_type', $type)
            ->where('transaction_id', $id)
            ->where('status', 'active')
            ->exists();

        return $this->decide($type, $row, $alreadyPosted);
    }

    /**
     * The rule itself, on a row that is already loaded.
     *
     * Split from eligibility() so the listing can classify hundreds of rows
     * from data it has fetched once, while still applying the one rule that
     * the sync applies. There is no second copy of it anywhere.
     *
     * @param  array<string,\App\Models\DefaultAccountMapping>|null $defaults  from defaultIndex()
     * @param  \Illuminate\Support\Collection|null                  $closed    from closedPeriods()
     */
    public function decide(string $type, $row, bool $alreadyPosted, ?array $defaults = null, $closed = null): array
    {
        // Payroll is not refused for lack of anything. It posts from the payroll
        // screen as one entry carrying gross pay, employer charges, each
        // withholding and the net — a two-line entry from a default mapping
        // would post only the net, and post it a second time.
        if ($type === 'payroll') {
            return $this->refuse('payroll', __('Payroll posts from the payroll screen, not from here.'));
        }

        if (!$row) {
            return $this->refuse('not_found', __('This transaction no longer exists.'));
        }

        if ($alreadyPosted) {
            return $this->refuse('already_posted', __('Already posted to the ledger.'));
        }

        [$categoryId, $payload, $refusal] = $this->source($type, $row);

        if ($refusal) {
            return $refusal;
        }

        $defaults ??= $this->defaultIndex();

        if (!isset($defaults[$this->mappingType($type) . ':' . ($categoryId ?? 'null')])) {
            $name = $this->categoryName($type, $categoryId);

            return $this->refuse('no_mapping', $categoryId
                ? __(':category has no default mapping. Set one in Mapping Settings.', ['category' => $name])
                : __('It has no category, and there is no default mapping for uncategorised transactions.'));
        }

        // A closed period's figures have been reported. Adding an entry to it
        // would change a set of accounts somebody has already signed.
        $closed ??= $this->closedPeriods();
        $date = substr((string) $payload['date'], 0, 10);

        foreach ($closed as $period) {
            if ($date >= substr((string) $period->start_date, 0, 10) && $date <= substr((string) $period->end_date, 0, 10)) {
                return $this->refuse('closed_period', __('It falls in :period, which is closed.', ['period' => $period->name ?? $date]));
            }
        }

        return [
            'eligible' => true,
            'code' => 'eligible',
            'reason' => null,
            'category_id' => $categoryId,
            'payload' => $payload,
        ];
    }

    /**
     * Post a selection. Each row is decided again at the moment of posting —
     * never trusted from the page the person was looking at, which may be
     * minutes old — and each row stands or falls alone.
     *
     * @param  array<int,array{type:string,id:int|string}> $items
     * @return array{posted:array, refused:array, failed:array, totals:array, truncated:bool}
     */
    public function sync(array $items): array
    {
        $result = [
            'posted' => [],
            'refused' => [],
            'failed' => [],
            'totals' => ['posted' => 0, 'refused' => 0, 'failed' => 0, 'amount' => 0.0],
            'truncated' => false,
        ];

        // The same row selected twice is one row.
        $unique = [];
        foreach ($items as $item) {
            $type = (string) ($item['type'] ?? '');
            $id = (int) ($item['id'] ?? 0);

            if ($id > 0) {
                $unique[$type . ':' . $id] = ['type' => $type, 'id' => $id];
            }
        }

        if (count($unique) > self::MAX_PER_REQUEST) {
            $result['truncated'] = true;
        }

        foreach (array_slice(array_values($unique), 0, self::MAX_PER_REQUEST) as ['type' => $type, 'id' => $id]) {
            try {
                // One transaction per row, so a failure on row 40 does not undo
                // the 39 before it. Inside it the source row is locked before it
                // is judged: two people syncing the same selection at the same
                // moment would otherwise both see "not yet posted", both post,
                // and leave two journal entries for one receipt.
                $outcome = DB::transaction(function () use ($type, $id) {
                    $this->lockSource($type, $id);

                    $decision = $this->eligibility($type, $id);

                    if (!$decision['eligible']) {
                        return ['refused', $decision, null];
                    }

                    $mapping = $this->mapper->autoMap($type, $id, $decision['category_id'], $decision['payload']);

                    return [$mapping ? 'posted' : 'failed', $decision, $mapping];
                });
            } catch (\Throwable $e) {
                Log::error("LedgerSyncService: {$type}#{$id} failed — " . $e->getMessage());
                $outcome = ['failed', null, null];
            }

            [$status, $decision, $mapping] = $outcome;

            if ($status === 'posted') {
                $amount = (float) $decision['payload']['amount'];

                $result['posted'][] = [
                    'type' => $type,
                    'id' => $id,
                    'amount' => $amount,
                    'journal_entry_id' => $mapping->journal_entry_id,
                ];
                $result['totals']['posted']++;
                $result['totals']['amount'] += $amount;
            } elseif ($status === 'refused') {
                $result['refused'][] = [
                    'type' => $type,
                    'id' => $id,
                    'code' => $decision['code'],
                    'reason' => $decision['reason'],
                ];
                $result['totals']['refused']++;
            } else {
                // autoMap() logs its own reason and returns false. The row was
                // eligible a moment earlier, so this is an error, not a refusal.
                $result['failed'][] = [
                    'type' => $type,
                    'id' => $id,
                    'reason' => __('Posting failed. The error has been logged.'),
                ];
                $result['totals']['failed']++;
            }
        }

        $result['totals']['amount'] = round($result['totals']['amount'], 2);

        return $result;
    }

    /**
     * Active default mappings, keyed "mapping_type:category_id". Loaded once
     * per request so the listing does not ask the database per row.
     *
     * @return array<string,\App\Models\DefaultAccountMapping>
     */
    public function defaultIndex(): array
    {
        $index = [];

        foreach (DefaultAccountMapping::where('status', 'active')
            ->whereNotNull('debit_account_id')
            ->whereNotNull('credit_account_id')
            ->get() as $mapping) {
            $index[$mapping->mapping_type . ':' . ($mapping->category_id ?? 'null')] = $mapping;
        }

        return $index;
    }

    public function closedPeriods()
    {
        return AccountingPeriod::where('is_closed', true)->get(['name', 'start_date', 'end_date']);
    }

    /**
     * Category, amount, date and description — copied from each type's observer
     * so a synced row posts what the observer would have posted.
     *
     * @return array{0:?int, 1:?array, 2:?array} [category id, payload, refusal]
     */
    private function source(string $type, $row): array
    {
        switch ($type) {
            case 'fee':
                // A fee on a payment plan posts per instalment, as a
                // payment_plan_payment. Posting the fee as well would count the
                // same money twice. FeeObserver skips it for the same reason.
                if ($row->payment_plan_id) {
                    return [null, null, $this->refuse('payment_plan', __('Paid through a payment plan. Each instalment posts on its own.'))];
                }

                // An assigned fee that nobody has paid is a bill, not money
                // received. There is nothing to post.
                if ((float) $row->paid_amount <= 0 || !$row->pay_date) {
                    return [null, null, $this->refuse('not_paid', __('Not paid yet, so there is nothing to post.'))];
                }

                return [$row->category_id, [
                    'amount' => $row->paid_amount,
                    'date' => $row->pay_date,
                    'description' => 'Fee Payment - ' . ($row->category->title ?? $row->category->name ?? 'Student Fee'),
                ], null];

            case 'income':
                return [$row->category_id, [
                    'amount' => $row->amount,
                    'date' => $row->date,
                    'description' => ($row->category->title ?? 'Income') . ' - ' . ($row->title ?? ''),
                ], null];

            case 'expense':
                return [$row->category_id, [
                    'amount' => $row->amount,
                    'date' => $row->date,
                    'description' => ($row->category->title ?? 'Expense') . ' - ' . ($row->title ?? ''),
                ], null];

            case 'payment_plan_payment':
                $installment = $row->installment;
                $fee = $installment?->paymentPlan?->fee;

                if (!$installment || !$fee) {
                    return [null, null, $this->refuse('not_found', __('Its instalment or fee no longer exists.'))];
                }

                return [$fee->category_id, [
                    'amount' => $row->amount,
                    'date' => $row->payment_date,
                    'description' => sprintf(
                        'Payment Plan - Installment #%d - %s',
                        $installment->installment_number,
                        $fee->category->title ?? $fee->category->name ?? 'Student Fee'
                    ),
                ], null];
        }

        return [null, null, $this->refuse('unsupported', __('This kind of transaction cannot be posted from here.'))];
    }

    private function load(string $type, int $id)
    {
        switch ($type) {
            case 'fee':
                return Fee::with('category')->find($id);
            case 'income':
                return Income::with('category')->find($id);
            case 'expense':
                return Expense::with('category')->find($id);
            case 'payment_plan_payment':
                return PaymentPlanPayment::with('installment.paymentPlan.fee.category')->find($id);
        }

        return null;
    }

    /** Hold the source row until this transaction ends. */
    private function lockSource(string $type, int $id): void
    {
        $table = [
            'fee' => 'fees',
            'income' => 'incomes',
            'expense' => 'expenses',
            'payment_plan_payment' => 'payment_plan_payments',
        ][$type] ?? null;

        if ($table) {
            DB::table($table)->where('id', $id)->lockForUpdate()->first();
        }
    }

    private function mappingType(string $type): string
    {
        return [
            'fee' => 'fee_category',
            'payment_plan_payment' => 'fee_category',
            'income' => 'income_category',
            'expense' => 'expense_category',
        ][$type] ?? 'unknown';
    }

    private function categoryName(string $type, $categoryId): string
    {
        $table = [
            'fee' => 'fees_categories',
            'payment_plan_payment' => 'fees_categories',
            'income' => 'income_categories',
            'expense' => 'expense_categories',
        ][$type] ?? null;

        $name = $table && $categoryId ? DB::table($table)->where('id', $categoryId)->value('title') : null;

        return $name ?: __('Category :id', ['id' => $categoryId]);
    }

    private function refuse(string $code, string $reason): array
    {
        return [
            'eligible' => false,
            'code' => $code,
            'reason' => $reason,
            'category_id' => null,
            'payload' => null,
        ];
    }
}
