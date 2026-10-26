<?php

namespace App\Observers;

use App\Models\Income;
use App\Services\TransactionAutoMapService;

class IncomeObserver
{
    protected $autoMapService;

    public function __construct(TransactionAutoMapService $autoMapService)
    {
        $this->autoMapService = $autoMapService;
    }

    /**
     * Handle the Income "created" event.
     */
    public function created(Income $income): void
    {
        $this->autoMapService->autoMap(
            'income',
            $income->id,
            $income->category_id,
            $this->payload($income)
        );
    }

    /**
     * Handle the Income "updated" event — keep the ledger in sync.
     *
     * The date belongs in this list and was missing from it.
     *
     * Everything that reads the sheet — the Income & Expenditure statement,
     * the daybook, every report — takes the date from this row, so correcting
     * one moved the money to the new month immediately. The ledger took its
     * date from the journal entry, which nothing updated: the entry kept the
     * old date, the old accounting period and the old fiscal year.
     *
     * Measured: moving one income from 29 May 2026 to 15 November 2025 put
     * 3,781,449 into November on the sheet and left it in May in the ledger —
     * in a different exercice. The two then disagree in both months, and the
     * reconciliation reports drift that no entry accounts for.
     *
     * Nothing else needed changing: remap() reverses and re-posts from the
     * payload below, which already carried the date, so the replacement entry
     * lands on the right day in the right exercice. FeeObserver has always
     * included pay_date here; this was the same rule, missed.
     *
     * The title is deliberately NOT in this list. remap() reverses and
     * re-posts, so triggering on it would churn the ledger over a typo.
     */
    public function updated(Income $income): void
    {
        if ($income->wasChanged(['amount', 'category_id', 'date'])) {
            $this->autoMapService->remap('income', $income->id, $income->category_id, $this->payload($income));
        }
    }

    /**
     * Handle the Income "deleted" event — reverse the ledger posting.
     */
    public function deleted(Income $income): void
    {
        $this->autoMapService->reverse('income', $income->id);
    }

    private function payload(Income $income): array
    {
        return [
            'amount' => $income->amount,
            'date' => $income->date,
            'description' => ($income->category->title ?? 'Income') . ' - ' . ($income->title ?? ''),
        ];
    }

    /**
     * Handle the Income "restored" event.
     */
    public function restored(Income $income): void
    {
        //
    }

    /**
     * Handle the Income "force deleted" event.
     */
    public function forceDeleted(Income $income): void
    {
        //
    }
}

