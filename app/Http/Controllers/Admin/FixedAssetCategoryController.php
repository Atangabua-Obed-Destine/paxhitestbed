<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FixedAssetCategory;
use App\Models\ChartOfAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Exception;

class FixedAssetCategoryController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:fixed-asset-category-list')->only(['index', 'show']);
        $this->middleware('permission:fixed-asset-category-create')->only(['create', 'store']);
        $this->middleware('permission:fixed-asset-category-edit')->only(['edit', 'update']);
        $this->middleware('permission:fixed-asset-category-delete')->only(['destroy']);
    }

    /**
     * Display a listing of categories
     */
    public function index()
    {
        try {
            $categories = FixedAssetCategory::with(['assetAccount', 'accumulatedDepreciationAccount', 'depreciationExpenseAccount'])
                ->withCount('assets')
                ->orderBy('name')
                ->paginate(15);

            return view('admin.accounting.fixed-asset-categories.index', compact('categories'));
        } catch (Exception $e) {
            Log::error('Error loading fixed asset categories', ['exception' => $e->getMessage()]);
            return back()->with('error', __('error_loading_categories'));
        }
    }

    /**
     * Show form for creating a new category
     */
    public function create()
    {
        try {
            // Get relevant accounts
            // Asset accounts (Class 2 in OHADA)
            $assetAccounts = ChartOfAccount::where('account_code', 'like', '2%')
                ->where('is_active', true)
                ->orderBy('account_code')
                ->get();

            // Accumulated depreciation accounts (Class 28 in OHADA)
            $accumulatedDepreciationAccounts = ChartOfAccount::where('account_code', 'like', '28%')
                ->where('is_active', true)
                ->orderBy('account_code')
                ->get();

            // Depreciation expense accounts (Class 68 in OHADA)
            $depreciationExpenseAccounts = ChartOfAccount::where('account_code', 'like', '68%')
                ->where('is_active', true)
                ->orderBy('account_code')
                ->get();

            $depreciationMethods = FixedAssetCategory::getDepreciationMethods();

            return view('admin.accounting.fixed-asset-categories.create', compact(
                'assetAccounts',
                'accumulatedDepreciationAccounts',
                'depreciationExpenseAccounts',
                'depreciationMethods'
            ));
        } catch (Exception $e) {
            Log::error('Error loading category create form', ['exception' => $e->getMessage()]);
            return back()->with('error', __('error_loading_form'));
        }
    }

    /**
     * Store a newly created category
     */
    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:20|unique:fixed_asset_categories,code',
            'name' => 'required|string|max:255',
            'asset_account_id' => 'required|exists:chart_of_accounts,id',
            'accumulated_depreciation_account_id' => 'required|exists:chart_of_accounts,id',
            'depreciation_expense_account_id' => 'required|exists:chart_of_accounts,id',
            'default_depreciation_method' => 'required|in:straight_line,declining_balance,units_of_production,none',
            'default_useful_life_months' => 'required|integer|min:1',
        ]);

        try {
            $category = FixedAssetCategory::create([
                'code' => $request->code,
                'name' => $request->name,
                'name_fr' => $request->name_fr ?? $request->name,
                'description' => $request->description,
                'asset_account_id' => $request->asset_account_id,
                'accumulated_depreciation_account_id' => $request->accumulated_depreciation_account_id,
                'depreciation_expense_account_id' => $request->depreciation_expense_account_id,
                'default_depreciation_method' => $request->default_depreciation_method,
                'default_useful_life_months' => $request->default_useful_life_months,
                'default_salvage_value_percent' => $request->default_salvage_value_percent ?? 0,
                'is_active' => $request->boolean('is_active', true),
            ]);

            return redirect()->route('admin.fixed-asset-categories.index')
                ->with('success', __('category_created_successfully'));
        } catch (Exception $e) {
            Log::error('Error creating category', ['exception' => $e->getMessage()]);
            return back()->withInput()->with('error', __('error_creating_category'));
        }
    }

    /**
     * Display a specific category
     */
    public function show(FixedAssetCategory $fixedAssetCategory)
    {
        try {
            $fixedAssetCategory->load(['assetAccount', 'accumulatedDepreciationAccount', 'depreciationExpenseAccount', 'assets']);

            return view('admin.accounting.fixed-asset-categories.show', compact('fixedAssetCategory'));
        } catch (Exception $e) {
            Log::error('Error showing category', ['id' => $fixedAssetCategory->id, 'exception' => $e->getMessage()]);
            return back()->with('error', __('error_loading_category'));
        }
    }

    /**
     * Show form for editing a category
     */
    public function edit(FixedAssetCategory $fixedAssetCategory)
    {
        try {
            $assetAccounts = ChartOfAccount::where('account_code', 'like', '2%')
                ->where('is_active', true)
                ->orderBy('account_code')
                ->get();

            $accumulatedDepreciationAccounts = ChartOfAccount::where('account_code', 'like', '28%')
                ->where('is_active', true)
                ->orderBy('account_code')
                ->get();

            $depreciationExpenseAccounts = ChartOfAccount::where('account_code', 'like', '68%')
                ->where('is_active', true)
                ->orderBy('account_code')
                ->get();

            $depreciationMethods = FixedAssetCategory::getDepreciationMethods();

            return view('admin.accounting.fixed-asset-categories.edit', compact(
                'fixedAssetCategory',
                'assetAccounts',
                'accumulatedDepreciationAccounts',
                'depreciationExpenseAccounts',
                'depreciationMethods'
            ));
        } catch (Exception $e) {
            Log::error('Error loading category edit form', ['exception' => $e->getMessage()]);
            return back()->with('error', __('error_loading_form'));
        }
    }

    /**
     * Update a category
     */
    public function update(Request $request, FixedAssetCategory $fixedAssetCategory)
    {
        $request->validate([
            'code' => 'required|string|max:20|unique:fixed_asset_categories,code,' . $fixedAssetCategory->id,
            'name' => 'required|string|max:255',
            'asset_account_id' => 'required|exists:chart_of_accounts,id',
            'accumulated_depreciation_account_id' => 'required|exists:chart_of_accounts,id',
            'depreciation_expense_account_id' => 'required|exists:chart_of_accounts,id',
            'default_depreciation_method' => 'required|in:straight_line,declining_balance,units_of_production,none',
            'default_useful_life_months' => 'required|integer|min:1',
        ]);

        try {
            $fixedAssetCategory->update([
                'code' => $request->code,
                'name' => $request->name,
                'name_fr' => $request->name_fr ?? $request->name,
                'description' => $request->description,
                'asset_account_id' => $request->asset_account_id,
                'accumulated_depreciation_account_id' => $request->accumulated_depreciation_account_id,
                'depreciation_expense_account_id' => $request->depreciation_expense_account_id,
                'default_depreciation_method' => $request->default_depreciation_method,
                'default_useful_life_months' => $request->default_useful_life_months,
                'default_salvage_value_percent' => $request->default_salvage_value_percent ?? 0,
                'is_active' => $request->boolean('is_active', true),
            ]);

            return redirect()->route('admin.fixed-asset-categories.index')
                ->with('success', __('category_updated_successfully'));
        } catch (Exception $e) {
            Log::error('Error updating category', ['id' => $fixedAssetCategory->id, 'exception' => $e->getMessage()]);
            return back()->withInput()->with('error', __('error_updating_category'));
        }
    }

    /**
     * Delete a category
     */
    public function destroy(FixedAssetCategory $fixedAssetCategory)
    {
        try {
            // Check if category has assets
            if ($fixedAssetCategory->assets()->exists()) {
                return back()->with('error', __('cannot_delete_category_with_assets'));
            }

            $fixedAssetCategory->delete();

            return redirect()->route('admin.fixed-asset-categories.index')
                ->with('success', __('category_deleted_successfully'));
        } catch (Exception $e) {
            Log::error('Error deleting category', ['id' => $fixedAssetCategory->id, 'exception' => $e->getMessage()]);
            return back()->with('error', __('error_deleting_category'));
        }
    }
}
