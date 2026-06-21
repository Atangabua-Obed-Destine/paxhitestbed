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
        $request->validate([
            'disposal_date' => 'required|date',
            'disposal_value' => 'required|numeric|min:0',
            'disposal_reason' => 'nullable|string|max:500',
        ]);

        try {
            $journalEntry = $this->depreciationService->disposeAsset(
                $fixedAsset,
                $request->disposal_date,
                $request->disposal_value,
                $request->disposal_reason
            );

            return redirect()->route('admin.fixed-assets.show', $fixedAsset)
                ->with('success', __('asset_disposed_successfully'));
        } catch (Exception $e) {
            Log::error('Error disposing asset', ['asset_id' => $fixedAsset->id, 'exception' => $e->getMessage()]);
            return back()->withInput()->with('error', __('error_disposing_asset') . ': ' . $e->getMessage());
        }
    }

    /**
     * Calculate depreciation for all assets
     */
    public function calculateDepreciation(Request $request)
    {
        $request->validate([
            'depreciation_date' => 'required|date',
            'auto_post' => 'nullable|boolean',
        ]);

        try {
            $results = $this->depreciationService->calculateMonthlyDepreciation(
                $request->depreciation_date,
                $request->boolean('auto_post')
            );

            return response()->json([
                'success' => true,
                'message' => __('depreciation_calculated_successfully'),
                'data' => $results,
            ]);
        } catch (Exception $e) {
            Log::error('Error calculating depreciation', ['exception' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => __('error_calculating_depreciation') . ': ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Post pending depreciation entries
     */
    public function postDepreciation(Request $request)
    {
        try {
            $results = $this->depreciationService->postPendingDepreciation(
                $request->accounting_period_id
            );

            return response()->json([
                'success' => true,
                'message' => __('depreciation_posted_successfully'),
                'data' => $results,
            ]);
        } catch (Exception $e) {
            Log::error('Error posting depreciation', ['exception' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => __('error_posting_depreciation') . ': ' . $e->getMessage(),
            ], 500);
        }
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
            'fiscal_year_id' => FiscalYear::getActiveFiscalYear()?->id,
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
