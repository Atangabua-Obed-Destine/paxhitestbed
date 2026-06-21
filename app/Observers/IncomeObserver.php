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
     * Handle the Income "updated" event — keep the ledger in sync on amount/category change.
     */
    public function updated(Income $income): void
    {
        if ($income->wasChanged(['amount', 'category_id'])) {
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

