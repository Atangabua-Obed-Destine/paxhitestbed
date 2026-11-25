<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ItemSupplier;
use App\Models\PaymentAccount;
use App\Models\SupplierPayment;
use App\Services\Accounting\CashAccounts;
use App\Services\Accounting\SupplierPayables;
use App\Services\Accounting\SupplierPayablesPreview;
use App\Support\PaymentMethod;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Paying suppliers. Every payment goes through SupplierPayables::pay(), the
 * one entry point, which checks it against what is owed and posts it.
 */
class SupplierPaymentController extends Controller
{
    private $route = 'admin.supplier-payment';
    private $view = 'admin.supplier-payment';

    public function __construct(protected SupplierPayables $payables)
    {
        $this->middleware('permission:supplier-payment-view', ['only' => ['index', 'show']]);
        $this->middleware('permission:supplier-payment-create', ['only' => ['create', 'store', 'preview']]);
        $this->middleware('permission:supplier-payment-void', ['only' => ['void', 'previewVoid']]);
    }

    public function index(Request $request)
    {
        $data = ['title' => __('Supplier Payments'), 'route' => $this->route, 'view' => $this->view];

        $data['rows'] = SupplierPayment::with(['supplier', 'cashAccount', 'allocations.bill'])
            ->when($request->supplier_id, fn ($q) => $q->where('supplier_id', $request->supplier_id))
            ->when($request->from, fn ($q) => $q->whereDate('payment_date', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('payment_date', '<=', $request->to))
            ->orderByDesc('payment_date')->orderByDesc('id')
            ->get();
        $data['suppliers'] = ItemSupplier::orderBy('title')->get(['id', 'title']);

        return view($this->view . '.index', $data);
    }

    public function create(Request $request)
    {
        $data = ['title' => __('Pay a Supplier'), 'route' => $this->route, 'view' => $this->view];

        // Only suppliers with something owed can be paid.
        $open = $this->payables->openBills();
        $data['suppliers'] = ItemSupplier::whereIn('id', $open->pluck('supplier_id')->unique())->orderBy('title')->get(['id', 'title']);
        $data['supplier'] = $request->supplier_id ? ItemSupplier::find($request->supplier_id) : null;
        $data['bills'] = $data['supplier'] ? $open->where('supplier_id', $data['supplier']->id)->values() : collect();
        $data['preselect_bill'] = (int) $request->bill_id;
        $data['cash_accounts'] = $this->payables->cashAccounts();
        $data['payment_accounts'] = PaymentAccount::where('status', 1)->whereNotNull('chart_of_account_id')->orderBy('title')->get();
        $data['methods'] = PaymentMethod::options();

        // Each payment method's usual ledger account, so choosing "Bank
        // transfer" preselects the bank.
        $cash = app(CashAccounts::class);
        $data['method_accounts'] = collect(array_keys($data['methods']))
            ->mapWithKeys(fn ($m) => [$m => $cash->forCurrentMethod($m)])
            ->all();

        return view($this->view . '.create', $data);
    }

    /** What recording this payment will do, from the form as it stands. */
    public function preview(Request $request, SupplierPayablesPreview $preview)
    {
        $this->validatePayment($request);

        return view('admin.supplier-bill.partials.preview', [
            'p' => $preview->pay($this->paymentInput($request), Auth::guard('web')->id()),
        ]);
    }

    /** What voiding will post, before it is voided. */
    public function previewVoid($id, SupplierPayablesPreview $preview)
    {
        $payment = SupplierPayment::with(['supplier', 'allocations.bill'])->findOrFail($id);

        return view('admin.supplier-bill.partials.preview', [
            'p' => $preview->voidPayment($payment, Auth::guard('web')->id()),
        ]);
    }

    private function validatePayment(Request $request): void
    {
        $request->validate([
            'supplier_id' => 'required|exists:item_suppliers,id',
            'payment_date' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => PaymentMethod::rule(),
            'cash_account_id' => 'nullable|exists:chart_of_accounts,id',
            'payment_account_id' => 'nullable|exists:payment_accounts,id',
            'reference' => 'nullable|string|max:100',
            'note' => 'nullable|string|max:2000',
            'allocations' => 'nullable|array',
            'allocations.*' => 'nullable|numeric|min:0',
        ]);
    }

    private function paymentInput(Request $request): array
    {
        $input = $request->only(['supplier_id', 'payment_date', 'amount', 'payment_method', 'cash_account_id',
            'payment_account_id', 'reference', 'note']);
        $allocations = array_filter((array) $request->input('allocations', []), fn ($v) => $v !== null && $v !== '' && (float) $v != 0.0);
        $input['allocations'] = $allocations ?: null;

        return $input;
    }

    public function store(Request $request)
    {
        $this->validatePayment($request);
        $input = $this->paymentInput($request);

        if (!$request->boolean('reviewed')) {
            Flasher::addError(__('Review it first: open the review, check the details and tick the confirmation box.'), __('msg_error'));

            return redirect()->back()->withInput();
        }


        try {
            $payment = $this->payables->pay($input, Auth::guard('web')->id());
        } catch (\RuntimeException $e) {
            Flasher::addError($e->getMessage(), __('msg_error'));

            return redirect()->back()->withInput();
        } catch (\Throwable $e) {
            Log::error('SupplierPaymentController::store — ' . $e->getMessage(), ['exception' => $e]);
            Flasher::addError(__('msg_error_occurred') . ': ' . $e->getMessage(), __('msg_error'));

            return redirect()->back()->withInput();
        }

        Flasher::addSuccess(__('Payment recorded and posted to the ledger.'), __('msg_success'));

        return redirect()->route($this->route . '.show', $payment->id);
    }

    public function show($id)
    {
        $payment = SupplierPayment::with(['supplier', 'cashAccount', 'paymentAccount', 'allocations.bill', 'journalEntry',
            'voidJournalEntry', 'creator', 'voider'])->findOrFail($id);

        return view($this->view . '.show', [
            'title' => __('Supplier Payment') . ' ' . $payment->number,
            'route' => $this->route,
            'view' => $this->view,
            'row' => $payment,
            'print' => request()->boolean('print'),
        ]);
    }

    public function void(Request $request, $id)
    {
        $request->validate(['void_reason' => 'required|string|max:1000']);
        $payment = SupplierPayment::findOrFail($id);

        if (!$request->boolean('reviewed')) {
            Flasher::addError(__('Review it first: open the review, check the details and tick the confirmation box.'), __('msg_error'));

            return redirect()->back()->withInput();
        }


        try {
            $this->payables->voidPayment($payment, Auth::guard('web')->id(), $request->void_reason);
            Flasher::addSuccess(__('The payment was voided and reversed in the ledger. The bills it paid are owed again.'), __('msg_success'));
        } catch (\RuntimeException $e) {
            Flasher::addError($e->getMessage(), __('msg_error'));
        } catch (\Throwable $e) {
            Log::error('SupplierPaymentController::void — ' . $e->getMessage(), ['payment' => $id, 'exception' => $e]);
            Flasher::addError(__('msg_error_occurred') . ': ' . $e->getMessage(), __('msg_error'));
        }

        return redirect()->route($this->route . '.show', $id);
    }
}
