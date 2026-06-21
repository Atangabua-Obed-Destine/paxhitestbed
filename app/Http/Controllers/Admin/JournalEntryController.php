<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\AccountingPeriod;
use Illuminate\Support\Facades\Auth;
use Toastr;
use DB;

class JournalEntryController extends Controller
{
    /**
     * Constructor - Apply permissions middleware
     */
    public function __construct()
    {
        // Apply permissions middleware
        $this->middleware('permission:journal-entry-view', ['only' => ['index', 'show']]);
        $this->middleware('permission:journal-entry-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:journal-entry-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:journal-entry-delete', ['only' => ['destroy']]);
        $this->middleware('permission:journal-entry-post', ['only' => ['post']]);
        $this->middleware('permission:journal-entry-reverse', ['only' => ['reverse']]);
    }

    /**
     * Display a listing of journal entries
     */
    public function index(Request $request)
    {
        $data['title'] = __('journal_entries');
        
        $query = JournalEntry::with(['fiscalYear', 'accountingPeriod', 'lines']);
        
        // Filters
        if ($request->filled('fiscal_year')) {
            $query->where('fiscal_year_id', $request->fiscal_year);
        }
        
        if ($request->filled('journal_type')) {
            $query->where('journal_type', $request->journal_type);
        }
        
        if ($request->filled('is_posted')) {
            $query->where('is_posted', $request->is_posted);
        }
        
        if ($request->filled('start_date')) {
            $query->whereDate('entry_date', '>=', $request->start_date);
        }
        
        if ($request->filled('end_date')) {
            $query->whereDate('entry_date', '<=', $request->end_date);
        }
        
        $data['entries'] = $query->orderBy('entry_date', 'desc')
            ->orderBy('entry_number', 'desc')
            ->paginate(25);
        
        // Get fiscal years for filter
        $data['fiscalYears'] = FiscalYear::orderBy('start_date', 'desc')->get();
        
        return view('admin.journal-entries.index', $data);
    }

    /**
     * Show the form for creating a new journal entry
     */
    public function create()
    {
        $data['title'] = __('create_journal_entry');
        
        // Get all fiscal years
        $data['fiscalYears'] = FiscalYear::where('is_closed', false)
            ->orderBy('start_date', 'desc')
            ->get();
        
        // Get active fiscal year
        $data['activeFiscalYear'] = FiscalYear::where('is_active', true)->first();
        
        // Get postable accounts
        $data['accounts'] = ChartOfAccount::where('account_category', 'detail')
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get();
        
        return view('admin.journal-entries.create', $data);
    }

    /**
     * Store a newly created journal entry
     */
    public function store(Request $request)
    {
        // Log incoming request for debugging
        \Log::info('Journal Entry Store Request:', [
            'entry_date' => $request->entry_date,
            'journal_type' => $request->journal_type,
            'lines_count' => count($request->lines ?? []),
            'lines' => $request->lines
        ]);

        $request->validate([
            'entry_date' => 'required|date',
            'fiscal_year_id' => 'required|exists:fiscal_years,id',
            'accounting_period_id' => 'nullable|exists:accounting_periods,id',
            'journal_type' => 'required|in:general,sales,purchase,cash,bank,adjustment,opening,closing',
            'description' => 'required|string',
            'reference_number' => 'nullable|string|max:255',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:chart_of_accounts,id',
            'lines.*.description' => 'nullable|string',
            'lines.*.debit' => 'required|numeric|min:0',
            'lines.*.credit' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            // Calculate totals
            $totalDebit = collect($request->lines)->sum('debit');
            $totalCredit = collect($request->lines)->sum('credit');

            // Validate balance
            if (abs($totalDebit - $totalCredit) > 0.01) {
                throw new \Exception(__('journal_entry_not_balanced'));
            }

            // Validate each line follows accounting rules
            foreach ($request->lines as $index => $line) {
                // Skip empty lines
                if ($line['debit'] == 0 && $line['credit'] == 0) {
                    continue;
                }

                // Validate that a line doesn't have both debit AND credit
                if ($line['debit'] > 0 && $line['credit'] > 0) {
                    $account = ChartOfAccount::find($line['account_id']);
                    throw new \Exception("Line " . ($index + 1) . " for account '{$account->account_name}' cannot have both debit and credit. Please enter only one side.");
                }

                // Optional: Warn if entry goes against account's normal balance
                // (This is just a warning, not an error, as contra entries are valid)
                $account = ChartOfAccount::find($line['account_id']);
                if ($account) {
                    // Check if this is a contra entry (goes against normal balance)
                    if ($account->normal_balance === 'debit' && $line['credit'] > 0) {
                        \Log::warning("Journal Entry Line Warning: Account '{$account->account_name}' is a debit account but has a credit entry. This may be intentional (contra entry).");
                    } elseif ($account->normal_balance === 'credit' && $line['debit'] > 0) {
                        \Log::warning("Journal Entry Line Warning: Account '{$account->account_name}' is a credit account but has a debit entry. This may be intentional (contra entry).");
                    }
                }
            }

            // Automatically determine the accounting period based on entry date and fiscal year
            $accountingPeriodId = $request->accounting_period_id;
            if (!$accountingPeriodId && $request->fiscal_year_id && $request->entry_date) {
                $period = AccountingPeriod::where('fiscal_year_id', $request->fiscal_year_id)
                    ->whereDate('start_date', '<=', $request->entry_date)
                    ->whereDate('end_date', '>=', $request->entry_date)
                    ->first();
                
                if ($period) {
                    $accountingPeriodId = $period->id;
                }
            }

            // Create journal entry
            $entry = JournalEntry::create([
                'entry_number' => JournalEntry::generateEntryNumber(),
                'entry_date' => $request->entry_date,
                'fiscal_year_id' => $request->fiscal_year_id,
                'accounting_period_id' => $accountingPeriodId,
                'journal_type' => $request->journal_type,
                'description' => $request->description,
                'reference_number' => $request->reference_number,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'is_posted' => false,
                'is_system_generated' => false,
                'created_by' => Auth::id(),
            ]);

            // Create journal entry lines
            $lineNumber = 1;
            foreach ($request->lines as $line) {
                // Skip lines with both debit and credit as 0
                if ($line['debit'] == 0 && $line['credit'] == 0) {
                    continue;
                }

                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $line['account_id'],
                    'line_number' => $lineNumber,
                    'description' => $line['description'] ?? $request->description,
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                ]);

                $lineNumber++;
            }

            // Auto-post if requested
            if ($request->has('post_immediately')) {
                $entry->post(Auth::id());
            }

            DB::commit();
            
            Toastr::success(__('journal_entry_created_successfully'), __('msg_success'));
            return redirect()->route('admin.journal-entries.show', $entry->id);

        } catch (\Exception $e) {
            DB::rollBack();
            
            \Log::error('Journal Entry Store Error:', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('msg_created_error') . ': ' . $e->getMessage()
                ], 500);
            }
            
            Toastr::error(__('msg_created_error') . ': ' . $e->getMessage(), __('msg_error'));
            return redirect()->back()->withInput();
        }
    }

    /**
     * Display the specified journal entry
     */
    public function show($id)
    {
        $data['title'] = __('journal_entry_details');
        $data['entry'] = JournalEntry::with([
            'lines.account',
            'fiscalYear',
            'accountingPeriod',
            'postedBy'
        ])->findOrFail($id);
        
        return view('admin.journal-entries.show', $data);
    }

    /**
     * Show the form for editing the specified journal entry
     */
    public function edit($id)
    {
        $data['title'] = __('edit_journal_entry');
        $data['entry'] = JournalEntry::with('lines.account')->findOrFail($id);
        
        // Get postable accounts
        $data['accounts'] = ChartOfAccount::where('account_category', 'detail')
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get();
        
        return view('admin.journal-entries.edit', $data);
    }

    /**
     * Update the specified journal entry
     */
    public function update(Request $request, $id)
    {
        $entry = JournalEntry::findOrFail($id);
        
        // Prevent editing posted entries
        if ($entry->is_posted) {
            Toastr::error(__('cannot_edit_posted_entry'), __('msg_error'));
            return redirect()->route('admin.journal-entries.show', $id);
        }

        $request->validate([
            'entry_date' => 'required|date',
            'accounting_period_id' => 'nullable|exists:accounting_periods,id',
            'journal_type' => 'required|in:general,sales,purchase,cash,bank,adjustment,opening,closing',
            'description' => 'required|string',
            'reference_number' => 'nullable|string|max:255',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:chart_of_accounts,id',
            'lines.*.description' => 'nullable|string',
            'lines.*.debit' => 'required|numeric|min:0',
            'lines.*.credit' => 'required|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            // Calculate totals
            $totalDebit = collect($request->lines)->sum('debit');
            $totalCredit = collect($request->lines)->sum('credit');

            // Validate balance
            if (abs($totalDebit - $totalCredit) > 0.01) {
                throw new \Exception(__('journal_entry_not_balanced'));
            }

            // Validate each line follows accounting rules
            foreach ($request->lines as $index => $line) {
                // Skip empty lines
                if ($line['debit'] == 0 && $line['credit'] == 0) {
                    continue;
                }

                // Validate that a line doesn't have both debit AND credit
                if ($line['debit'] > 0 && $line['credit'] > 0) {
                    $account = ChartOfAccount::find($line['account_id']);
                    throw new \Exception("Line " . ($index + 1) . " for account '{$account->account_name}' cannot have both debit and credit. Please enter only one side.");
                }

                // Optional: Warn if entry goes against account's normal balance
                $account = ChartOfAccount::find($line['account_id']);
                if ($account) {
                    if ($account->normal_balance === 'debit' && $line['credit'] > 0) {
                        \Log::warning("Journal Entry Line Warning: Account '{$account->account_name}' is a debit account but has a credit entry. This may be intentional (contra entry).");
                    } elseif ($account->normal_balance === 'credit' && $line['debit'] > 0) {
                        \Log::warning("Journal Entry Line Warning: Account '{$account->account_name}' is a credit account but has a debit entry. This may be intentional (contra entry).");
                    }
                }
            }

            // Automatically determine the accounting period based on entry date and fiscal year
            $accountingPeriodId = $request->accounting_period_id;
            if (!$accountingPeriodId && $entry->fiscal_year_id && $request->entry_date) {
                $period = AccountingPeriod::where('fiscal_year_id', $entry->fiscal_year_id)
                    ->whereDate('start_date', '<=', $request->entry_date)
                    ->whereDate('end_date', '>=', $request->entry_date)
                    ->first();
                
                if ($period) {
                    $accountingPeriodId = $period->id;
                }
            }

            // Update journal entry
            $entry->update([
                'entry_date' => $request->entry_date,
                'accounting_period_id' => $accountingPeriodId,
                'journal_type' => $request->journal_type,
                'description' => $request->description,
                'reference_number' => $request->reference_number,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'updated_by' => Auth::id(),
            ]);

            // Delete existing lines
            $entry->lines()->delete();

            // Create new lines
            $lineNumber = 1;
            foreach ($request->lines as $line) {
                if ($line['debit'] == 0 && $line['credit'] == 0) {
                    continue;
                }

                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $line['account_id'],
                    'line_number' => $lineNumber,
                    'description' => $line['description'] ?? $request->description,
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                ]);

                $lineNumber++;
            }

            DB::commit();
            Toastr::success(__('journal_entry_updated_successfully'), __('msg_success'));
            return redirect()->route('admin.journal-entries.show', $entry->id);

        } catch (\Exception $e) {
            DB::rollBack();
            Toastr::error(__('msg_update_error') . ': ' . $e->getMessage(), __('msg_error'));
            return redirect()->back()->withInput();
        }
    }

    /**
     * Remove the specified journal entry
     */
    public function destroy($id)
    {
        try {
            $entry = JournalEntry::findOrFail($id);
            
            // Prevent deleting posted entries
            if ($entry->is_posted) {
                Toastr::error(__('cannot_delete_posted_entry'), __('msg_error'));
                return redirect()->route('admin.journal-entries.index');
            }
            
            // Prevent deleting system-generated entries
            if ($entry->is_system_generated) {
                Toastr::error(__('cannot_delete_system_entry'), __('msg_error'));
                return redirect()->route('admin.journal-entries.index');
            }
            
            $entry->delete();
            Toastr::success(__('journal_entry_deleted_successfully'), __('msg_success'));
            
        } catch (\Exception $e) {
            Toastr::error(__('msg_delete_error') . ': ' . $e->getMessage(), __('msg_error'));
        }
        
        return redirect()->route('admin.journal-entries.index');
    }

    /**
     * Post a journal entry
     */
    public function post($id)
    {
        try {
            $entry = JournalEntry::with('lines')->findOrFail($id);
            
            if ($entry->is_posted) {
                throw new \Exception(__('entry_already_posted'));
            }

            $entry->post(Auth::id());
            
            Toastr::success(__('journal_entry_posted_successfully'), __('msg_success'));
            
        } catch (\Exception $e) {
            Toastr::error($e->getMessage(), __('msg_error'));
        }
        
        return redirect()->route('admin.journal-entries.show', $id);
    }

    /**
     * Unpost a journal entry
     */
    public function unpost($id)
    {
        try {
            $entry = JournalEntry::with('lines')->findOrFail($id);
            
            if (!$entry->is_posted) {
                throw new \Exception(__('entry_not_posted'));
            }

            // Check if period is closed
            if ($entry->accountingPeriod && $entry->accountingPeriod->is_closed) {
                throw new \Exception(__('cannot_unpost_entry_in_closed_period'));
            }

            $entry->unpost();
            
            Toastr::success(__('journal_entry_unposted_successfully'), __('msg_success'));
            
        } catch (\Exception $e) {
            Toastr::error($e->getMessage(), __('msg_error'));
        }
        
        return redirect()->route('admin.journal-entries.show', $id);
    }

    /**
     * Duplicate a journal entry
     */
    public function duplicate($id)
    {
        try {
            DB::beginTransaction();

            $originalEntry = JournalEntry::with('lines')->findOrFail($id);
            
            // Create new entry
            $newEntry = JournalEntry::create([
                'entry_number' => JournalEntry::generateEntryNumber(),
                'entry_date' => now()->format('Y-m-d'),
                'fiscal_year_id' => FiscalYear::getActiveFiscalYear()->id,
                'accounting_period_id' => null,
                'journal_type' => $originalEntry->journal_type,
                'description' => $originalEntry->description . ' (Copy)',
                'reference_number' => $originalEntry->reference_number,
                'total_debit' => $originalEntry->total_debit,
                'total_credit' => $originalEntry->total_credit,
                'is_posted' => false,
                'is_system_generated' => false,
                'created_by' => Auth::id(),
            ]);

            // Copy lines
            foreach ($originalEntry->lines as $line) {
                JournalEntryLine::create([
                    'journal_entry_id' => $newEntry->id,
                    'account_id' => $line->account_id,
                    'line_number' => $line->line_number,
                    'description' => $line->description,
                    'debit' => $line->debit,
                    'credit' => $line->credit,
                ]);
            }

            DB::commit();
            Toastr::success(__('journal_entry_duplicated_successfully'), __('msg_success'));
            return redirect()->route('admin.journal-entries.edit', $newEntry->id);

        } catch (\Exception $e) {
            DB::rollBack();
            Toastr::error($e->getMessage(), __('msg_error'));
            return redirect()->back();
        }
    }

    /**
     * Display trash (soft-deleted journal entries)
     */
    public function trash()
    {
        $data['title'] = __('deleted_journal_entries');
        $data['entries'] = JournalEntry::onlyTrashed()
            ->with(['fiscalYear', 'accountingPeriod', 'lines', 'creator'])
            ->orderBy('deleted_at', 'desc')
            ->paginate(25);
        
        return view('admin.journal-entries.trash', $data);
    }

    /**
     * Restore a soft-deleted journal entry
     */
    public function restore($id)
    {
        try {
            $entry = JournalEntry::onlyTrashed()->findOrFail($id);
            
            // Restore the entry
            $entry->restore();
            
            Toastr::success(__('journal_entry_restored_successfully'), __('msg_success'));
            return redirect()->route('admin.journal-entries.trash');
            
        } catch (\Exception $e) {
            Toastr::error(__('msg_restore_error') . ': ' . $e->getMessage(), __('msg_error'));
            return redirect()->back();
        }
    }

    /**
     * Permanently delete a journal entry
     */
    public function forceDelete($id)
    {
        try {
            $entry = JournalEntry::onlyTrashed()->findOrFail($id);
            
            // Check if entry was posted (shouldn't be, but double-check)
            if ($entry->is_posted) {
                Toastr::error(__('cannot_permanently_delete_posted_entry'), __('msg_error'));
                return redirect()->back();
            }
            
            // Delete all related lines first
            $entry->lines()->forceDelete();
            
            // Permanently delete the entry
            $entry->forceDelete();
            
            Toastr::success(__('journal_entry_permanently_deleted'), __('msg_success'));
            return redirect()->route('admin.journal-entries.trash');
            
        } catch (\Exception $e) {
            Toastr::error(__('msg_delete_error') . ': ' . $e->getMessage(), __('msg_error'));
            return redirect()->back();
        }
    }
}

