<?php

namespace App\Observers;

use App\Models\Expense;
use App\Services\TransactionAutoMapService;

class ExpenseObserver
{
    protected $autoMapService;

    public function __construct(TransactionAutoMapService $autoMapService)
    {
        $this->autoMapService = $autoMapService;
    }

    /**
     * Handle the Expense "created" event.
     */
    public function created(Expense $expense): void
    {
        $this->autoMapService->autoMap(
            'expense',
            $expense->id,
            $expense->category_id,
            $this->payload($expense)
        );
    }

    /**
     * Handle the Expense "updated" event — keep the ledger in sync on amount/category change.
     */
    public function updated(Expense $expense): void
    {
        if ($expense->wasChanged(['amount', 'category_id'])) {
            $this->autoMapService->remap('expense', $expense->id, $expense->category_id, $this->payload($expense));
        }
    }

    /**
     * Handle the Expense "deleted" event — reverse the ledger posting.
     */
    public function deleted(Expense $expense): void
    {
        $this->autoMapService->reverse('expense', $expense->id);
    }

    private function payload(Expense $expense): array
    {
        return [
            'amount' => $expense->amount,
            'date' => $expense->date,
            'description' => ($expense->category->title ?? 'Expense') . ' - ' . ($expense->title ?? ''),
        ];
    }

    /**
     * Handle the Expense "restored" event.
     */
    public function restored(Expense $expense): void
    {
        //
    }

    /**
     * Handle the Expense "force deleted" event.
     */
    public function forceDeleted(Expense $expense): void
    {
        //
    }
}

