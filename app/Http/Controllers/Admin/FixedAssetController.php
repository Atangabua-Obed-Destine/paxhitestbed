<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use App\Models\DepreciationSchedule;
use App\Models\FiscalYear;
use App\Models\ChartOfAccount;
use App\Services\Accounting\DepreciationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class FixedAssetController extends Controller
{
    protected $depreciationService;

    public function __construct(DepreciationService $depreciationService)
    {
        $this->depreciationService = $depreciationService;
        
        $this->middleware('auth');
        $this->middleware('permission:fixed-asset-list')->only(['index', 'show']);
        $this->middleware('permission:fixed-asset-create')->only(['create', 'store']);
        $this->middleware('permission:fixed-asset-edit')->only(['edit', 'update']);
        $this->middleware('permission:fixed-asset-delete')->only(['destroy']);
    }

    /**
     * Display a listing of fixed assets
     */
    public function index(Request $request)
    {
        try {
            $query = FixedAsset::with(['category', 'department', 'custodian']);

            // Filters
            if ($request->filled('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('department_id')) {
                $query->where('department_id', $request->department_id);
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('asset_code', 'like', "%{$search}%")
                      ->orWhere('name', 'like', "%{$search}%")
                      ->orWhere('serial_number', 'like', "%{$search}%");
                });
            }

            $assets = $query->orderBy('asset_code')->paginate(20);

            $categories = FixedAssetCategory::active()->orderBy('name')->get();
            $departments = \App\Models\Department::orderBy('title')->get();

            // Summary statistics
            $statistics = [
                'total_assets' => FixedAsset::count(),
                'active_assets' => FixedAsset::active()->count(),
                'total_cost' => FixedAsset::sum('acquisition_cost'),
                'total_depreciation' => FixedAsset::sum('accumulated_depreciation'),
                'total_book_value' => FixedAsset::sum('book_value'),
            ];

            return view('admin.accounting.fixed-assets.index', compact('assets', 'categories', 'departments', 'statistics'));
        } catch (Exception $e) {
            Log::error('Error loading fixed assets', ['exception' => $e->getMessage()]);
            return back()->with('error', __('error_loading_assets'));
        }
    }

    /**
     * Show form for creating a new asset
     */
    public function create()
    {
        try {
            $categories = FixedAssetCategory::active()->orderBy('name')->get();
            $departments = \App\Models\Department::orderBy('title')->get();
            $users = \App\User::orderBy('name')->get();
            $fiscalYears = FiscalYear::orderBy('start_date', 'desc')->get();

            return view('admin.accounting.fixed-assets.create', compact('categories', 'departments', 'users', 'fiscalYears'));
        } catch (Exception $e) {
            Log::error('Error loading asset create form', ['exception' => $e->getMessage()]);
            return back()->with('error', __('error_loading_form'));
        }
    }

    /**
     * Store a newly created asset
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:fixed_asset_categories,id',
            'acquisition_date' => 'required|date',
            'acquisition_cost' => 'required|numeric|min:0',
            'salvage_value' => 'nullable|numeric|min:0',
            'useful_life_months' => 'required|integer|min:1',
            'depreciation_method' => 'required|in:straight_line,declining_balance,units_of_production,none',
            'depreciation_start_date' => 'required|date|after_or_equal:acquisition_date',
        ]);

        DB::beginTransaction();
        try {
            $asset = FixedAsset::create([
                'name' => $request->name,
                'name_fr' => $request->name_fr ?? $request->name,
                'description' => $request->description,
                'category_id' => $request->category_id,
                'serial_number' => $request->serial_number,
                'model' => $request->model,
                'manufacturer' => $request->manufacturer,
                'location' => $request->location,
                'department_id' => $request->department_id,
                'custodian_id' => $request->custodian_id,
                'acquisition_date' => $request->acquisition_date,
                'acquisition_cost' => $request->acquisition_cost,
                'salvage_value' => $request->salvage_value ?? 0,
                'useful_life_months' => $request->useful_life_months,
                'depreciation_method' => $request->depreciation_method,
                'declining_balance_rate' => $request->declining_balance_rate,
                'depreciation_start_date' => $request->depreciation_start_date,
                'accumulated_depreciation' => 0,
                'book_value' => $request->acquisition_cost,
                'status' => FixedAsset::STATUS_ACTIVE,
                'warranty_expiry' => $request->warranty_expiry,
                'notes' => $request->notes,
                'image_path' => $request->image_path,
                'created_by' => auth()->id(),
            ]);

            // Create purchase journal entry if requested
            if ($request->create_journal_entry) {
                $this->createPurchaseJournalEntry($asset);
            }

            DB::commit();
            return redirect()->route('admin.fixed-assets.show', $asset)
                ->with('success', __('asset_created_successfully'));
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error creating asset', ['exception' => $e->getMessage()]);
            return back()->withInput()->with('error', __('error_creating_asset') . ': ' . $e->getMessage());
        }
    }

    /**
     * Display a specific asset
     */
    public function show(FixedAsset $fixedAsset)
    {
        try {
            $fixedAsset->load(['category', 'department', 'custodian', 'depreciationSchedules', 'purchaseJournalEntry', 'disposalJournalEntry']);
            
            // Generate depreciation forecast
            $forecast = $this->depreciationService->generateForecast($fixedAsset, 24);

            return view('admin.accounting.fixed-assets.show', compact('fixedAsset', 'forecast'));
        } catch (Exception $e) {
            Log::error('Error showing asset', ['asset_id' => $fixedAsset->id, 'exception' => $e->getMessage()]);
            return back()->with('error', __('error_loading_asset'));
        }
    }

    /**
     * Show form for editing an asset
     */
    public function edit(FixedAsset $fixedAsset)
    {
        try {
            $categories = FixedAssetCategory::active()->orderBy('name')->get();
            $departments = \App\Models\Department::orderBy('title')->get();
            $users = \App\User::orderBy('name')->get();

            return view('admin.accounting.fixed-assets.edit', compact('fixedAsset', 'categories', 'departments', 'users'));
        } catch (Exception $e) {
            Log::error('Error loading asset edit form', ['exception' => $e->getMessage()]);
            return back()->with('error', __('error_loading_form'));
        }
    }

    /**
     * Update an asset
     */
    public function update(Request $request, FixedAsset $fixedAsset)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:fixed_asset_categories,id',
        ]);

        try {
            $fixedAsset->update([
                'name' => $request->name,
                'name_fr' => $request->name_fr ?? $request->name,
                'description' => $request->description,
                'category_id' => $request->category_id,
                'serial_number' => $request->serial_number,
                'model' => $request->model,
                'manufacturer' => $request->manufacturer,
                'location' => $request->location,
                'department_id' => $request->department_id,
                'custodian_id' => $request->custodian_id,
                'warranty_expiry' => $request->warranty_expiry,
                'notes' => $request->notes,
                'updated_by' => auth()->id(),
            ]);

            return redirect()->route('admin.fixed-assets.show', $fixedAsset)
                ->with('success', __('asset_updated_successfully'));
        } catch (Exception $e) {
            Log::error('Error updating asset', ['asset_id' => $fixedAsset->id, 'exception' => $e->getMessage()]);
            return back()->withInput()->with('error', __('error_updating_asset'));
        }
    }

    /**
     * Delete an asset (soft delete)
     */
    public function destroy(FixedAsset $fixedAsset)
    {
        try {
            if ($fixedAsset->status !== FixedAsset::STATUS_DISPOSED) {
                return back()->with('error', __('dispose_asset_before_deleting'));
            }

            $fixedAsset->delete();

            return redirect()->route('admin.fixed-assets.index')
                ->with('success', __('asset_deleted_successfully'));
        } catch (Exception $e) {
            Log::error('Error deleting asset', ['asset_id' => $fixedAsset->id, 'exception' => $e->getMessage()]);
            return back()->with('error', __('error_deleting_asset'));
        }
    }

    /**
     * Dispose of an asset
     */
    public function dispose(Request $request, FixedAsset $fixedAsset)
    {
        // The screen's form sends disposal_proceeds, disposal_method and notes;
        // the older names are still accepted.
        $request->merge([
            'disposal_value' => $request->input('disposal_value', $request->input('disposal_proceeds', 0)),
        ]);
        $request->validate([
            'disposal_date' => 'required|date',
            'disposal_value' => 'required|numeric|min:0',
            'disposal_method' => 'nullable|string|max:30',
            'disposal_reason' => 'nullable|string|max:500',
            'notes' => 'nullable|string|max:500',
            'payment_account_id' => 'nullable|required_unless:disposal_value,0|exists:payment_accounts,id',
        ]);
        $reason = $request->disposal_reason
            ?? trim(($request->disposal_method ? __($request->disposal_method) : '') . ($request->notes ? ' — ' . $request->notes : ''), ' —');

        try {
            $journalEntry = $this->depreciationService->disposeAsset(
                $fixedAsset,
                $request->disposal_date,
                (float) $request->disposal_value,
                $reason ?: null,
                $request->filled('payment_account_id') ? (int) $request->payment_account_id : null
            );

            \Flasher\Laravel\Facade\Flasher::addSuccess(__('Asset disposed; entry :number posted.', ['number' => $journalEntry->entry_number]), __('msg_success'));

            return redirect()->route('admin.fixed-assets.index');
        } catch (Exception $e) {
            Log::error('Error disposing asset', ['asset_id' => $fixedAsset->id, 'exception' => $e->getMessage()]);
            \Flasher\Laravel\Facade\Flasher::addError($e->getMessage(), __('msg_error'));

            return back()->withInput();
        }
    }

    /**
     * Calculate depreciation for all assets
     */
    public function calculateDepreciation(Request $request)
    {
        $request->validate([
            'depreciation_date' => 'nullable|date',
            'month' => 'nullable|date_format:Y-m',
            'auto_post' => 'nullable|boolean',
        ]);

        return $this->runDepreciation($request, $request->boolean('auto_post'));
    }

    /**
     * Charge the month's depreciation and post it: one action on the screen.
     * Assets already charged for the month are left alone; schedules waiting
     * to be posted are posted.
     */
    public function postDepreciation(Request $request)
    {
        $request->validate([
            'depreciation_date' => 'nullable|date',
            'month' => 'nullable|date_format:Y-m',
            'accounting_period_id' => 'nullable|exists:accounting_periods,id',
        ]);

        return $this->runDepreciation($request, true);
    }

    private function runDepreciation(Request $request, bool $post)
    {
        $date = $request->filled('depreciation_date')
            ? \Carbon\Carbon::parse($request->depreciation_date)->endOfMonth()
            : ($request->filled('month') ? \Carbon\Carbon::createFromFormat('Y-m', $request->month)->endOfMonth() : now()->endOfMonth());

        try {
            $results = \Illuminate\Support\Facades\DB::transaction(function () use ($date, $post) {
                $results = $this->depreciationService->calculateMonthlyDepreciation($date, $post);
                if ($post && $results['errors']) {
                    // A month is charged whole or not at all.
                    throw new Exception(collect($results['errors'])->map(fn ($e) => ($e['asset_code'] ?? '') . ': ' . ($e['error'] ?? ''))->implode('; '));
                }

                return $results;
            });
        } catch (Exception $e) {
            Log::error('Error running depreciation', ['exception' => $e->getMessage()]);
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            \Flasher\Laravel\Facade\Flasher::addError($e->getMessage(), __('msg_error'));

            return redirect()->route('admin.fixed-assets.index');
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $results]);
        }

        $month = $date->format('F Y');
        $message = $post
            ? trans_choice('{0} Nothing to charge for :month.|{1} Depreciation for :month: one asset charged and posted.|[2,*] Depreciation for :month: :count assets charged and posted.', $results['posted'], ['count' => $results['posted'], 'month' => $month])
            : trans_choice('{0} Nothing to charge for :month.|{1} Depreciation for :month: one asset calculated, waiting to be posted.|[2,*] Depreciation for :month: :count assets calculated, waiting to be posted.', $results['processed'], ['count' => $results['processed'], 'month' => $month]);
        \Flasher\Laravel\Facade\Flasher::addSuccess($message, __('msg_success'));

        return redirect()->route('admin.fixed-assets.index');
    }

    /**
     * Asset register report
     */
    public function assetRegister(Request $request)
    {
        try {
            $assets = $this->depreciationService->getAssetRegister($request->all());
            $categories = FixedAssetCategory::active()->orderBy('name')->get();
            $departments = \App\Models\Department::orderBy('title')->get();

            // Calculate totals
            $totals = [
                'acquisition_cost' => $assets->sum('acquisition_cost'),
                'accumulated_depreciation' => $assets->sum('accumulated_depreciation'),
                'book_value' => $assets->sum('book_value'),
            ];

            return view('admin.accounting.fixed-assets.register', compact('assets', 'categories', 'departments', 'totals'));
        } catch (Exception $e) {
            Log::error('Error generating asset register', ['exception' => $e->getMessage()]);
            return back()->with('error', __('error_generating_report'));
        }
    }

    /**
     * Depreciation schedule report
     */
    public function depreciationScheduleReport(Request $request)
    {
        try {
            $schedules = $this->depreciationService->getDepreciationScheduleReport($request->all());
            $fiscalYears = FiscalYear::orderBy('start_date', 'desc')->get();
            $categories = FixedAssetCategory::active()->orderBy('name')->get();

            // Calculate totals
            $totals = [
                'total_depreciation' => $schedules->sum('depreciation_amount'),
                'pending_count' => $schedules->where('status', DepreciationSchedule::STATUS_PENDING)->count(),
                'posted_count' => $schedules->where('status', DepreciationSchedule::STATUS_POSTED)->count(),
            ];

            return view('admin.accounting.fixed-assets.depreciation-report', compact('schedules', 'fiscalYears', 'categories', 'totals'));
        } catch (Exception $e) {
            Log::error('Error generating depreciation report', ['exception' => $e->getMessage()]);
            return back()->with('error', __('error_generating_report'));
        }
    }

    /**
     * Depreciation summary by category
     */
    public function depreciationSummary(Request $request)
    {
        try {
            $fiscalYearId = $request->fiscal_year_id ?? FiscalYear::getActiveFiscalYear()?->id;
            
            if (!$fiscalYearId) {
                return back()->with('error', __('no_active_fiscal_year'));
            }

            $summary = $this->depreciationService->getSummaryByCategory($fiscalYearId);
            $fiscalYears = FiscalYear::orderBy('start_date', 'desc')->get();
            $currentFiscalYear = FiscalYear::find($fiscalYearId);

            return view('admin.accounting.fixed-assets.depreciation-summary', compact('summary', 'fiscalYears', 'currentFiscalYear'));
        } catch (Exception $e) {
            Log::error('Error generating depreciation summary', ['exception' => $e->getMessage()]);
            return back()->with('error', __('error_generating_report'));
        }
    }

    /**
     * Create purchase journal entry for asset
     */
    protected function createPurchaseJournalEntry(FixedAsset $asset)
    {
        $category = $asset->category;
        
        if (!$category || !$category->assetAccount) {
            throw new Exception(__('asset_account_not_configured'));
        }

        // Get cash/bank account
        $cashAccountId = \App\Models\AccountingSetting::getValue('default_cash_account_id')
            ?? ChartOfAccount::where('account_code', 'like', '52%')->first()?->id;

        if (!$cashAccountId) {
            throw new Exception(__('cash_account_not_configured'));
        }

        $journalEntry = \App\Models\JournalEntry::create([
            'entry_date' => $asset->acquisition_date,
            'reference_number' => 'FA-' . $asset->asset_code,
            'description' => __('purchase_of_fixed_asset') . ' - ' . $asset->name,
            'source_type' => 'fixed_asset',
            'source_id' => $asset->id,
            // An asset is routinely entered long after it was bought, so the
            // acquisition date decides the exercice, not the date of entry.
            'fiscal_year_id' => FiscalYear::forDate($asset->acquisition_date)?->id,
            'accounting_period_id' => FiscalYear::periodForDate($asset->acquisition_date)?->id,
            'status' => 'posted',
            'created_by' => auth()->id(),
        ]);

        // Debit: Asset Account
        \App\Models\JournalEntryLine::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $category->asset_account_id,
            'debit' => $asset->acquisition_cost,
            'credit' => 0,
            'description' => __('fixed_asset_acquisition'),
        ]);

        // Credit: Cash/Bank
        \App\Models\JournalEntryLine::create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $cashAccountId,
            'debit' => 0,
            'credit' => $asset->acquisition_cost,
            'description' => __('payment_for_fixed_asset'),
        ]);

        $asset->update(['purchase_journal_entry_id' => $journalEntry->id]);

        return $journalEntry;
    }
}
