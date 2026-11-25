<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationItem;
use App\Models\ChartOfAccount;
use App\Services\Accounting\BankReconciliationService;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Bank reconciliation screens. Every rule is in BankReconciliationService;
 * posting a charge or interest and completing go through the review dialog,
 * and the server refuses them without the review tick (reviewed=1).
 */
class BankReconciliationController extends Controller
{
    public function __construct(protected BankReconciliationService $service)
    {
        $this->middleware('auth');
        $this->middleware('permission:bank-reconciliation-list')->only(['index', 'show', 'summary']);
        $this->middleware('permission:bank-reconciliation-create')->only(['create', 'store']);
        $this->middleware('permission:bank-reconciliation-edit')->only(['clearItem', 'unclearItem', 'saveTicks', 'addAdjustment', 'previewAdjustment', 'updateStatementBalance', 'recalculate']);
        $this->middleware('permission:bank-reconciliation-delete')->only(['destroy']);
        $this->middleware('permission:bank-reconciliation-complete')->only(['complete', 'previewComplete']);
    }

    public function index(Request $request)
    {
        $rows = BankReconciliation::with('chartOfAccount')
            ->when($request->filled('account_id'), fn ($q) => $q->where('chart_of_account_id', $request->account_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderByDesc('statement_date')->orderByDesc('id')
            ->paginate(20)->withQueryString();

        $accounts = $this->service->accounts();
        $last = $accounts->mapWithKeys(fn ($a) => [$a->id => [
            'last' => $this->service->lastCompleted($a->id),
            'book' => $this->service->bookBalance($a->id, now()->toDateString()),
        ]]);

        return view('admin.accounting.bank-reconciliation.index', [
            'title' => __('Bank reconciliation'),
            'rows' => $rows,
            'accounts' => $accounts,
            'last' => $last,
        ]);
    }

    public function summary(Request $request)
    {
        return redirect()->route('admin.bank-reconciliation.index', $request->query());
    }

    public function create(Request $request)
    {
        return view('admin.accounting.bank-reconciliation.create', [
            'title' => __('New bank reconciliation'),
            'accounts' => $this->service->accounts(),
            'selected' => (int) $request->input('account_id'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'chart_of_account_id' => 'required|integer|exists:chart_of_accounts,id',
            'statement_date' => 'required|date|before_or_equal:today',
            'statement_closing_balance' => 'required|numeric',
            'statement_opening_balance' => 'nullable|numeric',
            'statement_reference' => 'nullable|string|max:190',
            'notes' => 'nullable|string|max:2000',
        ]);

        try {
            $rec = $this->service->create($data, Auth::id());
        } catch (\RuntimeException $e) {
            Flasher::addError($e->getMessage(), __('msg_error'));

            return back()->withInput();
        }

        Flasher::addSuccess(__('Reconciliation started. Tick each movement that appears on the bank statement.'), __('msg_success'));

        return redirect()->route('admin.bank-reconciliation.show', $rec->id);
    }

    public function show(BankReconciliation $bankReconciliation)
    {
        $rec = $bankReconciliation->load(['chartOfAccount', 'items' => fn ($q) => $q->orderBy('transaction_date')->orderBy('id')]);

        return view('admin.accounting.bank-reconciliation.show', [
            'title' => __('Bank reconciliation'),
            'rec' => $rec,
            'position' => $this->service->position($rec),
            'editable' => in_array($rec->status, [BankReconciliation::STATUS_DRAFT, BankReconciliation::STATUS_IN_PROGRESS], true),
            'chargeCategories' => (new \App\Services\Accounting\BudgetLineChoices())->categories('expense_category')->values(),
            'interestCategories' => (new \App\Services\Accounting\BudgetLineChoices())->categories('income_category')->values(),
        ]);
    }

    public function saveTicks(Request $request, BankReconciliation $bankReconciliation)
    {
        $ids = array_map('intval', (array) $request->input('cleared', []));

        return $this->attempt(fn () => $this->service->saveTicks($bankReconciliation, $ids), $bankReconciliation, __('Ticks saved.'));
    }

    public function clearItem(Request $request, BankReconciliationItem $item)
    {
        $rec = BankReconciliation::findOrFail($item->bank_reconciliation_id);

        return $this->attempt(fn () => $this->service->setCleared($rec, [$item->id], true), $rec);
    }

    public function unclearItem(BankReconciliationItem $item)
    {
        $rec = BankReconciliation::findOrFail($item->bank_reconciliation_id);

        return $this->attempt(fn () => $this->service->setCleared($rec, [$item->id], false), $rec);
    }

    public function updateStatementBalance(Request $request, BankReconciliation $bankReconciliation)
    {
        $data = $request->validate([
            'statement_closing_balance' => 'required|numeric',
            'statement_reference' => 'nullable|string|max:190',
        ]);

        return $this->attempt(fn () => $this->service->updateStatement($bankReconciliation,
            (float) $data['statement_closing_balance'], $data['statement_reference'] ?? null), $bankReconciliation, __('Statement balance updated.'));
    }

    public function recalculate(BankReconciliation $bankReconciliation)
    {
        return $this->attempt(fn () => $this->service->recalculate($bankReconciliation), $bankReconciliation);
    }

    public function previewAdjustment(Request $request, BankReconciliation $bankReconciliation)
    {
        $data = $this->adjustmentInput($request);

        return view('admin.supplier-bill.partials.preview', ['p' => $this->service->previewAdjustment($bankReconciliation, $data, Auth::id())]);
    }

    public function addAdjustment(Request $request, BankReconciliation $bankReconciliation)
    {
        if (!$request->boolean('reviewed')) {
            Flasher::addError(__('Review it first: open the review, check the details and tick the confirmation box.'), __('msg_error'));

            return back()->withInput();
        }

        $data = $this->adjustmentInput($request);

        return $this->attempt(fn () => $this->service->addAdjustment($bankReconciliation, $data, Auth::id()), $bankReconciliation, __('Posted to the ledger and ticked.'));
    }

    public function previewComplete(BankReconciliation $bankReconciliation)
    {
        return view('admin.supplier-bill.partials.preview', ['p' => $this->service->previewComplete($bankReconciliation, Auth::id())]);
    }

    public function complete(Request $request, BankReconciliation $bankReconciliation)
    {
        if (!$request->boolean('reviewed')) {
            Flasher::addError(__('Review it first: open the review, check the details and tick the confirmation box.'), __('msg_error'));

            return back();
        }

        return $this->attempt(fn () => $this->service->complete($bankReconciliation, Auth::id()), $bankReconciliation, __('Reconciliation completed.'));
    }

    public function destroy(BankReconciliation $bankReconciliation)
    {
        try {
            $this->service->delete($bankReconciliation);
        } catch (\RuntimeException $e) {
            Flasher::addError($e->getMessage(), __('msg_error'));

            return back();
        }

        Flasher::addSuccess(__('Reconciliation deleted.'), __('msg_success'));

        return redirect()->route('admin.bank-reconciliation.index');
    }

    protected function adjustmentInput(Request $request): array
    {
        return $request->validate([
            'kind' => 'required|in:bank_charge,bank_interest',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'category_id' => 'required|integer',
            'description' => 'nullable|string|max:150',
        ]);
    }

    protected function attempt(callable $action, BankReconciliation $rec, ?string $success = null)
    {
        try {
            $action();
        } catch (\RuntimeException $e) {
            Flasher::addError($e->getMessage(), __('msg_error'));

            return redirect()->route('admin.bank-reconciliation.show', $rec->id);
        }

        if ($success) {
            Flasher::addSuccess($success, __('msg_success'));
        }

        return redirect()->route('admin.bank-reconciliation.show', $rec->id);
    }
}
