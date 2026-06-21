<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RecurringJournalEntry;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Services\Accounting\RecurringEntryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class RecurringEntryController extends Controller
{
    protected $recurringService;

    public function __construct(RecurringEntryService $recurringService)
    {
        $this->recurringService = $recurringService;
        
        $this->middleware('auth');
        $this->middleware('permission:recurring-entry-list')->only(['index', 'show']);
        $this->middleware('permission:recurring-entry-create')->only(['create', 'store']);
        $this->middleware('permission:recurring-entry-edit')->only(['edit', 'update', 'pause', 'resume', 'skipNext']);
        $this->middleware('permission:recurring-entry-delete')->only(['destroy']);
        $this->middleware('permission:recurring-entry-process')->only(['process', 'processAll']);
    }

    /**
     * Display a listing of recurring entries
     */
    public function index(Request $request)
    {
        try {
            $query = RecurringJournalEntry::with(['lines', 'creator']);

            if ($request->filled('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->filled('frequency')) {
                $query->where('frequency', $request->frequency);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $entries = $query->orderBy('next_run_date')->paginate(20);

            // Get summary
            $summary = $this->recurringService->getSummary();

            // Get upcoming entries
            $upcoming = $this->recurringService->getUpcoming(7);

            return view('admin.accounting.recurring-entries.index', compact('entries', 'summary', 'upcoming'));
        } catch (Exception $e) {
            Log::error('Error loading recurring entries', ['exception' => $e->getMessage()]);
            return back()->with('error', __('error_loading_entries'));
        }
    }

    /**
     * Show form for creating a new recurring entry
     */
    public function create()
    {
        try {
            $accounts = ChartOfAccount::active()->orderBy('account_code')->get();
            $fiscalYears = FiscalYear::orderBy('start_date', 'desc')->get();
            $activeFiscalYear = FiscalYear::getActiveFiscalYear();
            $frequencies = RecurringJournalEntry::getFrequencies();

            return view('admin.accounting.recurring-entries.create', compact('accounts', 'fiscalYears', 'activeFiscalYear', 'frequencies'));
        } catch (Exception $e) {
            Log::error('Error loading recurring entry create form', ['exception' => $e->getMessage()]);
            return back()->with('error', __('error_loading_form'));
        }
    }

    /**
     * Store a newly created recurring entry
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'frequency' => 'required|in:daily,weekly,biweekly,monthly,quarterly,semiannually,annually',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:chart_of_accounts,id',
            'lines.*.debit' => 'required_without:lines.*.credit|nullable|numeric|min:0',
            'lines.*.credit' => 'required_without:lines.*.debit|nullable|numeric|min:0',
        ]);

        try {
            $entry = $this->recurringService->createTemplate($request->all());

            return redirect()->route('admin.recurring-entries.show', $entry)
                ->with('success', __('recurring_entry_created_successfully'));
        } catch (Exception $e) {
            Log::error('Error creating recurring entry', ['exception' => $e->getMessage()]);
            return back()->withInput()->with('error', __('error_creating_entry') . ': ' . $e->getMessage());
        }
    }

    /**
     * Display a specific recurring entry
     */
    public function show(RecurringJournalEntry $recurringEntry)
    {
        try {
            $recurringEntry->load(['lines.account', 'journalEntries' => function ($q) {
                $q->orderBy('entry_date', 'desc')->limit(10);
            }, 'fiscalYear', 'creator']);

            return view('admin.accounting.recurring-entries.show', compact('recurringEntry'));
        } catch (Exception $e) {
            Log::error('Error showing recurring entry', ['id' => $recurringEntry->id, 'exception' => $e->getMessage()]);
            return back()->with('error', __('error_loading_entry'));
        }
    }

    /**
     * Show form for editing a recurring entry
     */
    public function edit(RecurringJournalEntry $recurringEntry)
    {
        try {
            $recurringEntry->load('lines.account');
            $accounts = ChartOfAccount::active()->orderBy('account_code')->get();
            $fiscalYears = FiscalYear::orderBy('start_date', 'desc')->get();
            $frequencies = RecurringJournalEntry::getFrequencies();

            return view('admin.accounting.recurring-entries.edit', compact('recurringEntry', 'accounts', 'fiscalYears', 'frequencies'));
        } catch (Exception $e) {
            Log::error('Error loading recurring entry edit form', ['exception' => $e->getMessage()]);
            return back()->with('error', __('error_loading_form'));
        }
    }

    /**
     * Update a recurring entry
     */
    public function update(Request $request, RecurringJournalEntry $recurringEntry)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'frequency' => 'required|in:daily,weekly,biweekly,monthly,quarterly,semiannually,annually',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:chart_of_accounts,id',
        ]);

        try {
            $entry = $this->recurringService->updateTemplate($recurringEntry, $request->all());

            return redirect()->route('admin.recurring-entries.show', $entry)
                ->with('success', __('recurring_entry_updated_successfully'));
        } catch (Exception $e) {
            Log::error('Error updating recurring entry', ['id' => $recurringEntry->id, 'exception' => $e->getMessage()]);
            return back()->withInput()->with('error', __('error_updating_entry') . ': ' . $e->getMessage());
        }
    }

    /**
     * Delete a recurring entry
     */
    public function destroy(RecurringJournalEntry $recurringEntry)
    {
        try {
            $recurringEntry->lines()->delete();
            $recurringEntry->delete();

            return redirect()->route('admin.recurring-entries.index')
                ->with('success', __('recurring_entry_deleted_successfully'));
        } catch (Exception $e) {
            Log::error('Error deleting recurring entry', ['id' => $recurringEntry->id, 'exception' => $e->getMessage()]);
            return back()->with('error', __('error_deleting_entry'));
        }
    }

    /**
     * Pause a recurring entry
     */
    public function pause(RecurringJournalEntry $recurringEntry)
    {
        try {
            $this->recurringService->pause($recurringEntry);

            return response()->json([
                'success' => true,
                'message' => __('recurring_entry_paused'),
            ]);
        } catch (Exception $e) {
            Log::error('Error pausing recurring entry', ['id' => $recurringEntry->id, 'exception' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => __('error_pausing_entry'),
            ], 500);
        }
    }

    /**
     * Resume a recurring entry
     */
    public function resume(Request $request, RecurringJournalEntry $recurringEntry)
    {
        try {
            $this->recurringService->resume($recurringEntry, $request->next_run_date);

            return response()->json([
                'success' => true,
                'message' => __('recurring_entry_resumed'),
            ]);
        } catch (Exception $e) {
            Log::error('Error resuming recurring entry', ['id' => $recurringEntry->id, 'exception' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => __('error_resuming_entry'),
            ], 500);
        }
    }

    /**
     * Skip next occurrence
     */
    public function skipNext(RecurringJournalEntry $recurringEntry)
    {
        try {
            $this->recurringService->skipNext($recurringEntry);

            return response()->json([
                'success' => true,
                'message' => __('next_occurrence_skipped'),
                'next_run_date' => $recurringEntry->next_run_date->format('Y-m-d'),
            ]);
        } catch (Exception $e) {
            Log::error('Error skipping next occurrence', ['id' => $recurringEntry->id, 'exception' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => __('error_skipping_occurrence'),
            ], 500);
        }
    }

    /**
     * Process a specific recurring entry
     */
    public function process(Request $request, RecurringJournalEntry $recurringEntry)
    {
        try {
            $journalEntry = $this->recurringService->processEntry($recurringEntry, $request->date);

            if ($journalEntry) {
                return response()->json([
                    'success' => true,
                    'message' => __('entry_processed_successfully'),
                    'journal_entry' => $journalEntry,
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => __('entry_not_due_for_processing'),
            ], 400);
        } catch (Exception $e) {
            Log::error('Error processing recurring entry', ['id' => $recurringEntry->id, 'exception' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => __('error_processing_entry') . ': ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process all due recurring entries
     */
    public function processAll(Request $request)
    {
        try {
            $results = $this->recurringService->processDueEntries($request->date);

            return response()->json([
                'success' => true,
                'message' => __('entries_processed_successfully'),
                'data' => $results,
            ]);
        } catch (Exception $e) {
            Log::error('Error processing recurring entries', ['exception' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => __('error_processing_entries') . ': ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Duplicate a recurring entry
     */
    public function duplicate(Request $request, RecurringJournalEntry $recurringEntry)
    {
        try {
            $newEntry = $this->recurringService->duplicate($recurringEntry, $request->all());

            return redirect()->route('admin.recurring-entries.edit', $newEntry)
                ->with('success', __('recurring_entry_duplicated'));
        } catch (Exception $e) {
            Log::error('Error duplicating recurring entry', ['id' => $recurringEntry->id, 'exception' => $e->getMessage()]);
            return back()->with('error', __('error_duplicating_entry'));
        }
    }

    /**
     * Get upcoming recurring entries (AJAX)
     */
    public function upcoming(Request $request)
    {
        try {
            $days = $request->input('days', 30);
            $upcoming = $this->recurringService->getUpcoming($days);

            return response()->json([
                'success' => true,
                'data' => $upcoming,
            ]);
        } catch (Exception $e) {
            Log::error('Error loading upcoming entries', ['exception' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => __('error_loading_entries'),
            ], 500);
        }
    }
}
