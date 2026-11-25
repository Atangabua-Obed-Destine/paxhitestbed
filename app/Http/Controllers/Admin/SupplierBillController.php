<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Models\ItemSupplier;
use App\Models\SupplierBill;
use App\Services\Accounting\BudgetLineChoices;
use App\Services\Accounting\SupplierPayables;
use App\Services\Accounting\SupplierPayablesPreview;
use App\Traits\FileUploader;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Supplier bills: enter, approve, void; plus the payables aging and the
 * supplier statement. Every rule lives in SupplierPayables; this only reads
 * the form and shows the answer.
 */
class SupplierBillController extends Controller
{
    use FileUploader;

    private $route = 'admin.supplier-bill';
    private $view = 'admin.supplier-bill';
    private $path = 'supplier-bill';

    public function __construct(protected SupplierPayables $payables)
    {
        $this->middleware('permission:supplier-bill-view', ['only' => ['index', 'show', 'aging', 'statement']]);
        $this->middleware('permission:supplier-bill-create', ['only' => ['create', 'store', 'edit', 'update', 'destroy']]);
        $this->middleware('permission:supplier-bill-approve', ['only' => ['approve', 'previewApprove', 'settings']]);
        $this->middleware('permission:supplier-bill-void', ['only' => ['void', 'previewVoid']]);
    }

    private function base(string $title): array
    {
        return ['title' => $title, 'route' => $this->route, 'view' => $this->view, 'path' => $this->path];
    }

    public function index(Request $request)
    {
        $data = $this->base(__('Supplier Bills'));

        $bills = SupplierBill::with('supplier')
            ->when($request->supplier_id, fn ($q) => $q->where('supplier_id', $request->supplier_id))
            ->when($request->from, fn ($q) => $q->whereDate('bill_date', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('bill_date', '<=', $request->to))
            ->orderByDesc('bill_date')->orderByDesc('id')
            ->get();

        // What is owed on each, from the one definition.
        $owed = $this->payables->openBills()->keyBy('id');
        $today = now()->toDateString();

        foreach ($bills as $bill) {
            $bill->owed = $bill->isApproved() ? (float) ($owed[$bill->id]->owed ?? 0) : 0.0;
            $bill->state = $this->payables->paymentState($bill, $bill->owed);
            $bill->overdue = $bill->owed > 0.005 && $bill->due_date->toDateString() < $today;
        }

        $status = $request->status;
        if ($status) {
            $bills = $bills->filter(fn ($b) => $status === 'overdue' ? $b->overdue : $b->state === $status)->values();
        }

        $data['rows'] = $bills;
        $data['suppliers'] = ItemSupplier::orderBy('title')->get(['id', 'title']);
        $data['total_owed'] = round($owed->sum('owed'), 2);
        $data['overdue_owed'] = round($owed->filter(fn ($b) => $b->due_date < $today)->sum('owed'), 2);
        $data['drafts'] = SupplierBill::where('status', SupplierBill::DRAFT)->count();
        $data['payable_account_id'] = $this->payables->payableAccountId();
        $data['payable_accounts'] = $this->payables->payableAccounts();

        return view($this->view . '.index', $data);
    }

    public function create(Request $request)
    {
        $data = $this->base(__('New Supplier Bill'));
        $data['row'] = null;

        return view($this->view . '.form', array_merge($data, $this->formData($request->supplier_id)));
    }

    public function store(Request $request)
    {
        return $this->save($request, null);
    }

    public function show($id)
    {
        $bill = SupplierBill::with(['supplier', 'lines.category', 'lines.account', 'journalEntry', 'voidJournalEntry',
            'creator', 'approver', 'voider', 'allocations.payment', 'payableAccount'])->findOrFail($id);

        $data = $this->base(__('Supplier Bill') . ' ' . $bill->number);
        $data['row'] = $bill;
        $data['owed'] = $this->payables->owedOn($bill);
        $data['state'] = $this->payables->paymentState($bill, $data['owed']);
        $data['duplicate'] = $bill->isDraft() ? $this->payables->duplicateOf($bill) : null;

        return view($this->view . '.show', $data);
    }

    public function edit($id)
    {
        $bill = SupplierBill::with('lines')->findOrFail($id);

        if (!$bill->isDraft()) {
            Flasher::addError(__('Only a draft bill can be changed. Void an approved bill and enter it again.'), __('msg_error'));

            return redirect()->route($this->route . '.show', $bill->id);
        }

        $data = $this->base(__('Edit Supplier Bill') . ' ' . $bill->number);
        $data['row'] = $bill;

        return view($this->view . '.form', array_merge($data, $this->formData($bill->supplier_id, $bill)));
    }

    public function update(Request $request, $id)
    {
        return $this->save($request, SupplierBill::findOrFail($id));
    }

    public function destroy($id)
    {
        $bill = SupplierBill::findOrFail($id);

        try {
            $this->payables->deleteDraft($bill);
            Flasher::addSuccess(__('The draft was deleted.'), __('msg_success'));
        } catch (\RuntimeException $e) {
            Flasher::addError($e->getMessage(), __('msg_error'));

            return redirect()->back();
        }

        return redirect()->route($this->route . '.index');
    }

    public function approve(Request $request, $id)
    {
        $bill = SupplierBill::findOrFail($id);

        if (!$request->boolean('reviewed')) {
            Flasher::addError(__('Review it first: open the review, check the details and tick the confirmation box.'), __('msg_error'));

            return redirect()->back()->withInput();
        }


        try {
            $this->payables->approve($bill, Auth::guard('web')->id());
            Flasher::addSuccess(__('Approved and posted to the ledger.'), __('msg_success'));
        } catch (\RuntimeException $e) {
            Flasher::addError($e->getMessage(), __('msg_error'));
        } catch (\Throwable $e) {
            Log::error('SupplierBillController::approve — ' . $e->getMessage(), ['bill' => $id, 'exception' => $e]);
            Flasher::addError(__('msg_error_occurred') . ': ' . $e->getMessage(), __('msg_error'));
        }

        return redirect()->route($this->route . '.show', $id);
    }

    /** What approving will post, before it is approved. */
    public function previewApprove($id, SupplierPayablesPreview $preview)
    {
        $bill = SupplierBill::with(['supplier', 'lines.category'])->findOrFail($id);

        return view($this->view . '.partials.preview', ['p' => $preview->approve($bill, Auth::guard('web')->id())]);
    }

    /** What voiding will post, before it is voided. */
    public function previewVoid($id, SupplierPayablesPreview $preview)
    {
        $bill = SupplierBill::with('supplier')->findOrFail($id);

        return view($this->view . '.partials.preview', ['p' => $preview->voidBill($bill, Auth::guard('web')->id())]);
    }

    public function void(Request $request, $id)
    {
        $request->validate(['void_reason' => 'required|string|max:1000']);
        $bill = SupplierBill::findOrFail($id);

        if (!$request->boolean('reviewed')) {
            Flasher::addError(__('Review it first: open the review, check the details and tick the confirmation box.'), __('msg_error'));

            return redirect()->back()->withInput();
        }


        try {
            $this->payables->voidBill($bill, Auth::guard('web')->id(), $request->void_reason);
            Flasher::addSuccess(__('The bill was voided and reversed in the ledger.'), __('msg_success'));
        } catch (\RuntimeException $e) {
            Flasher::addError($e->getMessage(), __('msg_error'));
        } catch (\Throwable $e) {
            Log::error('SupplierBillController::void — ' . $e->getMessage(), ['bill' => $id, 'exception' => $e]);
            Flasher::addError(__('msg_error_occurred') . ': ' . $e->getMessage(), __('msg_error'));
        }

        return redirect()->route($this->route . '.show', $id);
    }

    public function settings(Request $request)
    {
        $request->validate(['payable_account_id' => 'required|exists:chart_of_accounts,id']);

        try {
            $this->payables->setPayableAccount((int) $request->payable_account_id);
            Flasher::addSuccess(__('Saved. New bills will be owed on that account.'), __('msg_success'));
        } catch (\RuntimeException $e) {
            Flasher::addError($e->getMessage(), __('msg_error'));
        }

        return redirect()->back();
    }

    public function aging(Request $request)
    {
        $request->validate(['as_of' => 'nullable|date']);

        $data = $this->base(__('Payables Aging'));
        $data['report'] = $this->payables->aging($request->as_of);
        $data['print'] = $request->boolean('print');

        return view($this->view . '.aging', $data);
    }

    public function statement(Request $request)
    {
        $request->validate([
            'supplier_id' => 'nullable|exists:item_suppliers,id',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $data = $this->base(__('Supplier Statement'));
        $data['suppliers'] = ItemSupplier::orderBy('title')->get(['id', 'title']);
        $data['supplier'] = $request->supplier_id ? ItemSupplier::find($request->supplier_id) : null;
        $data['statement'] = $data['supplier']
            ? $this->payables->statement($data['supplier']->id, $request->from, $request->to)
            : null;
        $data['print'] = $request->boolean('print');

        return view($this->view . '.statement', $data);
    }

    private function save(Request $request, ?SupplierBill $bill)
    {
        $request->validate([
            'supplier_id' => 'required|exists:item_suppliers,id',
            'kind' => 'nullable|in:bill,opening_balance',
            'supplier_reference' => 'nullable|string|max:100',
            'bill_date' => 'required|date|before_or_equal:today',
            'due_date' => 'nullable|date|after_or_equal:bill_date',
            'description' => 'nullable|string|max:255',
            'attach' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:20480',
            'lines' => 'nullable|array',
            'lines.*.description' => 'nullable|string|max:255',
            'lines.*.expense_category_id' => 'nullable|integer',
            'lines.*.amount' => 'nullable|numeric',
            'opening_account_id' => 'nullable|exists:chart_of_accounts,id',
            'opening_amount' => 'nullable|numeric',
        ]);

        $input = $request->only(['supplier_id', 'kind', 'supplier_reference', 'bill_date', 'due_date', 'description', 'opening_account_id']);
        $input['lines'] = $request->input('kind') === SupplierBill::KIND_OPENING
            ? [['description' => $request->description, 'amount' => $request->opening_amount]]
            : array_values($request->input('lines', []));
        $input['attach'] = $request->hasFile('attach') ? $this->uploadMedia($request, 'attach', $this->path) : null;

        $userId = Auth::guard('web')->id();

        try {
            $bill = $this->payables->saveDraft($input, $userId, $bill);
        } catch (\RuntimeException $e) {
            Flasher::addError($e->getMessage(), __('msg_error'));

            return redirect()->back()->withInput();
        }

        if ($request->input('after') === 'approve') {
            if (!Auth::guard('web')->user()->can('supplier-bill-approve')) {
                Flasher::addWarning(__('Saved as a draft. You are not allowed to approve bills; someone who is must approve it.'));

                return redirect()->route($this->route . '.show', $bill->id);
            }

            // Saved; approving is a separate, reviewed step. The bill page
            // opens with the review of what approval will post.
            Flasher::addSuccess(__('Saved as a draft. Review what approval will post, then confirm.'), __('msg_success'));

            return redirect()->route($this->route . '.show', ['id' => $bill->id, 'review' => 'approve']);
        }

        Flasher::addSuccess(__('Saved as a draft. It posts nothing until it is approved.'), __('msg_success'));

        return redirect()->route($this->route . '.show', $bill->id);
    }

    private function formData($supplierId = null, ?SupplierBill $bill = null): array
    {
        $choices = new BudgetLineChoices();
        $categories = $choices->categories(SupplierPayables::MAPPING_TYPE);

        // A draft saved against a category that has since been unfiled keeps
        // showing it, with the reason, rather than silently dropping the line.
        $kept = $bill ? $bill->lines->pluck('expense_category_id')->filter()
            ->reject(fn ($id) => $categories->contains('id', (int) $id))
            ->map(fn ($id) => ExpenseCategory::find($id))->filter()->values() : collect();

        return [
            'suppliers' => ItemSupplier::orderBy('title')->get(['id', 'title', 'payment_terms_days']),
            'selected_supplier' => $supplierId,
            'categories' => $categories->groupBy('heading_label'),
            'kept_categories' => $kept,
            'opening_accounts' => $this->payables->openingBalanceAccounts(),
        ];
    }
}
