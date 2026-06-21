<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\YearEndClosing;
use App\Models\FiscalYear;
use App\Models\ChartOfAccount;
use App\Models\AccountingSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class YearEndClosingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:year-end-closing-list')->only(['index', 'show']);
        $this->middleware('permission:year-end-closing-create')->only(['create', 'store']);
        $this->middleware('permission:year-end-closing-edit')->only(['edit', 'update', 'updateChecklist', 'start', 'generateClosingEntries']);
        $this->middleware('permission:year-end-closing-delete')->only(['destroy']);
        $this->middleware('permission:year-end-closing-approve')->only(['approve', 'reverse']);
    }

    /**
     * Display a listing of year-end closings
     */
    public function index(Request $request)
    {
        try {
            $query = YearEndClosing::with(['fiscalYear', 'creator', 'approver']);

            if ($request->filled('fiscal_year_id')) {
                $query->where('fiscal_year_id', $request->fiscal_year_id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $closings = $query->orderBy('closing_date', 'desc')->paginate(20);

            $fiscalYears = FiscalYear::orderBy('start_date', 'desc')->get();
            $activeFiscalYear = FiscalYear::getActiveFiscalYear();

            return view('admin.accounting.year-end-closing.index', compact('closings', 'fiscalYears', 'activeFiscalYear'));
        } catch (Exception $e) {
            Log::error('Error loading year-end closings', ['exception' => $e->getMessage()]);
            return back()->with('error', __('error_loading_closings'));
        }
    }

    /**
     * Show form for creating a new year-end closing
     */
    public function create()
    {
        try {
            // Get fiscal years that haven't been closed yet
            $fiscalYears = FiscalYear::where('is_closed', false)
                ->orderBy('start_date', 'desc')
                ->get();

            if ($fiscalYears->isEmpty()) {
                return back()->with('error', __('no_open_fiscal_years'));
            }

            // Get retained earnings account (Class 12 in OHADA)
            $retainedEarningsAccounts = ChartOfAccount::where('account_code', 'like', '12%')
                ->where('is_active', true)
                ->orderBy('account_code')
                ->get();

            // Get income summary account (temporary account)
            $incomeSummaryAccounts = ChartOfAccount::where('account_code', 'like', '13%')
                ->orWhere('account_name', 'like', '%income summary%')
                ->orWhere('account_name', 'like', '%résultat%')
                ->where('is_active', true)
                ->orderBy('account_code')
                ->get();

            return view('admin.accounting.year-end-closing.create', compact(
                'fiscalYears',
                'retainedEarningsAccounts',
                'incomeSummaryAccounts'
            ));
        } catch (Exception $e) {
            Log::error('Error loading year-end closing create form', ['exception' => $e->getMessage()]);
            return back()->with('error', __('error_loading_form'));
        }
    }

    /**
     * Store a newly created year-end closing
     */
    public function store(Request $request)
    {
        $request->validate([
            'fiscal_year_id' => 'required|exists:fiscal_years,id',
            'closing_date' => 'required|date',
            'retained_earnings_account_id' => 'required|exists:chart_of_accounts,id',
            'income_summary_account_id' => 'required|exists:chart_of_accounts,id',
        ]);

        try {
            // Check if closing already exists for this fiscal year
            $existing = YearEndClosing::where('fiscal_year_id', $request->fiscal_year_id)
                ->whereNotIn('status', [YearEndClosing::STATUS_REVERSED])
                ->first();

            if ($existing) {
                return back()->with('error', __('closing_already_exists_for_fiscal_year'));
            }

            $closing = YearEndClosing::create([
                'fiscal_year_id' => $request->fiscal_year_id,
                'closing_date' => $request->closing_date,
                'retained_earnings_account_id' => $request->retained_earnings_account_id,
                'income_summary_account_id' => $request->income_summary_account_id,
                'status' => YearEndClosing::STATUS_DRAFT,
                'notes' => $request->notes,
                'created_by' => auth()->id(),
            ]);

            return redirect()->route('admin.year-end-closing.show', $closing)
                ->with('success', __('year_end_closing_created_successfully'));
        } catch (Exception $e) {
            Log::error('Error creating year-end closing', ['exception' => $e->getMessage()]);
            return back()->withInput()->with('error', __('error_creating_closing') . ': ' . $e->getMessage());
        }
    }

    /**
     * Display a specific year-end closing
     */
    public function show(YearEndClosing $yearEndClosing)
    {
        try {
            $yearEndClosing->load([
                'fiscalYear',
                'retainedEarningsAccount',
                'incomeSummaryAccount',
                'closingJournalEntry.lines.account',
                'creator',
                'approver',
            ]);

            // Get checklist completion
            $checklistCompletion = $yearEndClosing->getChecklistCompletionPercentage();

            // If in progress, calculate current amounts
            if ($yearEndClosing->status === YearEndClosing::STATUS_IN_PROGRESS) {
                $yearEndClosing->calculateClosingAmounts();
            }

            return view('admin.accounting.year-end-closing.show', compact('yearEndClosing', 'checklistCompletion'));
        } catch (Exception $e) {
            Log::error('Error showing year-end closing', ['id' => $yearEndClosing->id, 'exception' => $e->getMessage()]);
            return back()->with('error', __('error_loading_closing'));
        }
    }

    /**
     * Start the closing process
     */
    public function start(YearEndClosing $yearEndClosing)
    {
        try {
            $yearEndClosing->start();

            return redirect()->route('admin.year-end-closing.show', $yearEndClosing)
                ->with('success', __('closing_process_started'));
        } catch (Exception $e) {
            Log::error('Error starting closing process', ['id' => $yearEndClosing->id, 'exception' => $e->getMessage()]);
            return back()->with('error', __('error_starting_process') . ': ' . $e->getMessage());
        }
    }

    /**
     * Update checklist item
     */
    public function updateChecklist(Request $request, YearEndClosing $yearEndClosing)
    {
        $request->validate([
            'item' => 'required|string',
            'value' => 'required|boolean',
        ]);

        try {
            $yearEndClosing->updateChecklistItem($request->item, $request->boolean('value'));

            return response()->json([
                'success' => true,
                'message' => __('checklist_updated'),
                'completion' => $yearEndClosing->getChecklistCompletionPercentage(),
                'is_complete' => $yearEndClosing->isChecklistComplete(),
            ]);
        } catch (Exception $e) {
            Log::error('Error updating checklist', ['exception' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => __('error_updating_checklist'),
            ], 500);
        }
    }

    /**
     * Generate closing entries
     */
    public function generateClosingEntries(YearEndClosing $yearEndClosing)
    {
        try {
            $journalEntry = $yearEndClosing->generateClosingEntries();

            return redirect()->route('admin.year-end-closing.show', $yearEndClosing)
                ->with('success', __('closing_entries_generated'));
        } catch (Exception $e) {
            Log::error('Error generating closing entries', ['id' => $yearEndClosing->id, 'exception' => $e->getMessage()]);
            return back()->with('error', __('error_generating_entries') . ': ' . $e->getMessage());
        }
    }

    /**
     * Approve the year-end closing
     */
    public function approve(YearEndClosing $yearEndClosing)
    {
        try {
            $yearEndClosing->approve(auth()->id());

            return redirect()->route('admin.year-end-closing.show', $yearEndClosing)
                ->with('success', __('closing_approved_successfully'));
        } catch (Exception $e) {
            Log::error('Error approving closing', ['id' => $yearEndClosing->id, 'exception' => $e->getMessage()]);
            return back()->with('error', __('error_approving_closing') . ': ' . $e->getMessage());
        }
    }

    /**
     * Reverse the year-end closing
     */
    public function reverse(Request $request, YearEndClosing $yearEndClosing)
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $yearEndClosing->reverse($request->reason);

            return redirect()->route('admin.year-end-closing.show', $yearEndClosing)
                ->with('success', __('closing_reversed_successfully'));
        } catch (Exception $e) {
            Log::error('Error reversing closing', ['id' => $yearEndClosing->id, 'exception' => $e->getMessage()]);
            return back()->with('error', __('error_reversing_closing') . ': ' . $e->getMessage());
        }
    }

    /**
     * Delete a year-end closing
     */
    public function destroy(YearEndClosing $yearEndClosing)
    {
        try {
            if (!in_array($yearEndClosing->status, [YearEndClosing::STATUS_DRAFT])) {
                return back()->with('error', __('only_draft_closings_can_be_deleted'));
            }

            $yearEndClosing->delete();

            return redirect()->route('admin.year-end-closing.index')
                ->with('success', __('closing_deleted_successfully'));
        } catch (Exception $e) {
            Log::error('Error deleting closing', ['id' => $yearEndClosing->id, 'exception' => $e->getMessage()]);
            return back()->with('error', __('error_deleting_closing'));
        }
    }

    /**
     * Preview closing amounts
     */
    public function preview(YearEndClosing $yearEndClosing)
    {
        try {
            $yearEndClosing->calculateClosingAmounts();

            // Get revenue accounts with balances
            $revenueAccounts = $this->getAccountsWithBalances('7%', $yearEndClosing->fiscal_year_id);
            
            // Get expense accounts with balances
            $expenseAccounts = $this->getAccountsWithBalances('6%', $yearEndClosing->fiscal_year_id);

            return response()->json([
                'success' => true,
                'data' => [
                    'total_revenue' => $yearEndClosing->total_revenue,
                    'total_expenses' => $yearEndClosing->total_expenses,
                    'net_income' => $yearEndClosing->net_income,
                    'revenue_accounts' => $revenueAccounts,
                    'expense_accounts' => $expenseAccounts,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Error previewing closing amounts', ['exception' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => __('error_loading_preview'),
            ], 500);
        }
    }

    /**
     * Get accounts with balances for a code pattern
     */
    protected function getAccountsWithBalances($codePattern, $fiscalYearId)
    {
        $accounts = ChartOfAccount::where('account_code', 'like', $codePattern)
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get();

        $result = [];
        foreach ($accounts as $account) {
            $balance = $this->getAccountBalance($account->id, $fiscalYearId);
            if ($balance != 0) {
                $result[] = [
                    'code' => $account->account_code,
                    'name' => $account->account_name,
                    'balance' => $balance,
                ];
            }
        }

        return $result;
    }

    /**
     * Get account balance for fiscal year
     */
    protected function getAccountBalance($accountId, $fiscalYearId)
    {
        $credits = \App\Models\JournalEntryLine::where('account_id', $accountId)
            ->whereHas('journalEntry', function ($q) use ($fiscalYearId) {
                $q->where('fiscal_year_id', $fiscalYearId)
                  ->where('status', 'posted');
            })
            ->sum('credit');

        $debits = \App\Models\JournalEntryLine::where('account_id', $accountId)
            ->whereHas('journalEntry', function ($q) use ($fiscalYearId) {
                $q->where('fiscal_year_id', $fiscalYearId)
                  ->where('status', 'posted');
            })
            ->sum('debit');

        return $credits - $debits;
    }
}
