<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationItem;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Services\Accounting\BankReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class BankReconciliationController extends Controller
{
    protected $reconciliationService;

    public function __construct(BankReconciliationService $reconciliationService)
    {
        $this->reconciliationService = $reconciliationService;
        
        $this->middleware('auth');
        $this->middleware('permission:bank-reconciliation-list')->only(['index', 'show']);
        $this->middleware('permission:bank-reconciliation-create')->only(['create', 'store']);
        $this->middleware('permission:bank-reconciliation-edit')->only(['edit', 'update', 'clearItem', 'unclearItem', 'addAdjustment']);
        $this->middleware('permission:bank-reconciliation-delete')->only(['destroy']);
        $this->middleware('permission:bank-reconciliation-approve')->only(['complete', 'approve']);
    }

    /**
     * Display a listing of bank reconciliations
     */
    public function index(Request $request)
    {
        try {
            $query = BankReconciliation::with(['bankAccount', 'creator']);

            if ($request->filled('bank_account_id')) {
                $query->where('bank_account_id', $request->bank_account_id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('fiscal_year_id')) {
                $query->where('fiscal_year_id', $request->fiscal_year_id);
            }

            $reconciliations = $query->orderBy('statement_date', 'desc')->paginate(20);

            // Get bank accounts (Class 52 in OHADA - Bank accounts)
            $bankAccounts = ChartOfAccount::where('account_code', 'like', '52%')
                ->where('is_active', true)
                ->orderBy('account_code')
                ->get();

            $fiscalYears = FiscalYear::orderBy('start_date', 'desc')->get();

            return view('admin.accounting.bank-reconciliation.index', compact('reconciliations', 'bankAccounts', 'fiscalYears'));
        } catch (Exception $e) {
            Log::error('Error loading bank reconciliations', ['exception' => $e->getMessage()]);
            return back()->with('error', __('error_loading_reconciliations'));
        }
    }

    /**
     * Show form for creating a new reconciliation
     */
    public function create(Request $request)
    {
        try {
            // Get bank accounts
            $bankAccounts = ChartOfAccount::where('account_code', 'like', '52%')
                ->where('is_active', true)
                ->orderBy('account_code')
                ->get();

            $fiscalYears = FiscalYear::orderBy('start_date', 'desc')->get();
            $activeFiscalYear = FiscalYear::getActiveFiscalYear();

            $selectedBankAccountId = $request->bank_account_id;
            $summary = null;

            if ($selectedBankAccountId) {
                $summary = $this->reconciliationService->getSummary($selectedBankAccountId);
            }

            return view('admin.accounting.bank-reconciliation.create', compact('bankAccounts', 'fiscalYears', 'activeFiscalYear', 'selectedBankAccountId', 'summary'));
        } catch (Exception $e) {
            Log::error('Error loading reconciliation create form', ['exception' => $e->getMessage()]);
            return back()->with('error', __('error_loading_form'));
        }
    }

    /**
     * Store a newly created reconciliation
     */
    public function store(Request $request)
    {
        $request->validate([
            'bank_account_id' => 'required|exists:chart_of_accounts,id',
            'statement_date' => 'required|date',
            'statement_ending_balance' => 'required|numeric',
            'statement_beginning_balance' => 'nullable|numeric',
        ]);

        try {
            $reconciliation = $this->reconciliationService->create($request->all());

            return redirect()->route('admin.bank-reconciliation.show', $reconciliation)
                ->with('success', __('reconciliation_created_successfully'));
        } catch (Exception $e) {
            Log::error('Error creating reconciliation', ['exception' => $e->getMessage()]);
            return back()->withInput()->with('error', __('error_creating_reconciliation') . ': ' . $e->getMessage());
        }
    }

    /**
     * Display a specific reconciliation
     */
    public function show(BankReconciliation $bankReconciliation)
    {
        try {
            $bankReconciliation->load(['bankAccount', 'items' => function ($q) {
                $q->orderBy('transaction_date', 'desc');
            }, 'creator', 'approver']);

            // Group items by type
            $itemsByType = $bankReconciliation->items->groupBy('item_type');

            // Get reconciliation status
            $isBalanced = $bankReconciliation->isReconciled();

            return view('admin.accounting.bank-reconciliation.show', compact('bankReconciliation', 'itemsByType', 'isBalanced'));
        } catch (Exception $e) {
            Log::error('Error showing reconciliation', ['id' => $bankReconciliation->id, 'exception' => $e->getMessage()]);
            return back()->with('error', __('error_loading_reconciliation'));
        }
    }

    /**
     * Clear an item
     */
    public function clearItem(Request $request, BankReconciliationItem $item)
    {
        try {
            $this->reconciliationService->clearItem($item, $request->cleared_date);

            return response()->json([
                'success' => true,
                'message' => __('item_cleared_successfully'),
                'reconciliation' => $item->bankReconciliation->fresh(),
            ]);
        } catch (Exception $e) {
            Log::error('Error clearing item', ['item_id' => $item->id, 'exception' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => __('error_clearing_item') . ': ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Unclear an item
     */
    public function unclearItem(BankReconciliationItem $item)
    {
        try {
            $this->reconciliationService->unclearItem($item);

            return response()->json([
                'success' => true,
                'message' => __('item_uncleared_successfully'),
                'reconciliation' => $item->bankReconciliation->fresh(),
            ]);
        } catch (Exception $e) {
            Log::error('Error unclearing item', ['item_id' => $item->id, 'exception' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => __('error_unclearing_item') . ': ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add adjustment item
     */
    public function addAdjustment(Request $request, BankReconciliation $bankReconciliation)
    {
        $request->validate([
            'item_type' => 'required|in:bank_charge,interest_earned,nsf_check,other',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'effect' => 'required|in:add,subtract',
        ]);

        try {
            $item = $this->reconciliationService->addAdjustment($bankReconciliation, $request->all());

            return response()->json([
                'success' => true,
                'message' => __('adjustment_added_successfully'),
                'item' => $item,
                'reconciliation' => $bankReconciliation->fresh(),
            ]);
        } catch (Exception $e) {
            Log::error('Error adding adjustment', ['exception' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => __('error_adding_adjustment') . ': ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Complete the reconciliation
     */
    public function complete(Request $request, BankReconciliation $bankReconciliation)
    {
        try {
            $this->reconciliationService->complete(
                $bankReconciliation,
                $request->boolean('create_adjusting_entries', true)
            );

            return redirect()->route('admin.bank-reconciliation.show', $bankReconciliation)
                ->with('success', __('reconciliation_completed_successfully'));
        } catch (Exception $e) {
            Log::error('Error completing reconciliation', ['id' => $bankReconciliation->id, 'exception' => $e->getMessage()]);
            return back()->with('error', __('error_completing_reconciliation') . ': ' . $e->getMessage());
        }
    }

    /**
     * Delete a reconciliation
     */
    public function destroy(BankReconciliation $bankReconciliation)
    {
        try {
            $this->reconciliationService->delete($bankReconciliation);

            return redirect()->route('admin.bank-reconciliation.index')
                ->with('success', __('reconciliation_deleted_successfully'));
        } catch (Exception $e) {
            Log::error('Error deleting reconciliation', ['id' => $bankReconciliation->id, 'exception' => $e->getMessage()]);
            return back()->with('error', __('error_deleting_reconciliation') . ': ' . $e->getMessage());
        }
    }

    /**
     * Get reconciliation summary for a bank account
     */
    public function summary(Request $request)
    {
        $request->validate([
            'bank_account_id' => 'required|exists:chart_of_accounts,id',
        ]);

        try {
            $summary = $this->reconciliationService->getSummary($request->bank_account_id);

            return response()->json([
                'success' => true,
                'data' => $summary,
            ]);
        } catch (Exception $e) {
            Log::error('Error getting reconciliation summary', ['exception' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => __('error_loading_summary'),
            ], 500);
        }
    }

    /**
     * Recalculate reconciliation balances
     */
    public function recalculate(BankReconciliation $bankReconciliation)
    {
        try {
            $bankReconciliation->calculateBalances();

            return response()->json([
                'success' => true,
                'message' => __('balances_recalculated'),
                'reconciliation' => $bankReconciliation->fresh(),
            ]);
        } catch (Exception $e) {
            Log::error('Error recalculating balances', ['exception' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => __('error_recalculating_balances'),
            ], 500);
        }
    }

    /**
     * Update statement balance
     */
    public function updateStatementBalance(Request $request, BankReconciliation $bankReconciliation)
    {
        $request->validate([
            'statement_ending_balance' => 'required|numeric',
        ]);

        try {
            $bankReconciliation->update([
                'statement_ending_balance' => $request->statement_ending_balance,
            ]);

            $bankReconciliation->calculateBalances();

            return response()->json([
                'success' => true,
                'message' => __('statement_balance_updated'),
                'reconciliation' => $bankReconciliation->fresh(),
            ]);
        } catch (Exception $e) {
            Log::error('Error updating statement balance', ['exception' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => __('error_updating_balance'),
            ], 500);
        }
    }
}
