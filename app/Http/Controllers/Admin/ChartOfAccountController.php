<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\Auth;
use Toastr;
use DB;

class ChartOfAccountController extends Controller
{
    /**
     * Constructor - Apply permissions middleware
     */
    public function __construct()
    {
        // Apply permissions middleware
        $this->middleware('permission:chart-of-accounts-view', ['only' => ['index', 'show']]);
        $this->middleware('permission:chart-of-accounts-create', ['only' => ['create', 'store']]);
        $this->middleware('permission:chart-of-accounts-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:chart-of-accounts-delete', ['only' => ['destroy']]);
        $this->middleware('permission:chart-of-accounts-activate', ['only' => ['toggleStatus']]);
    }

    /**
     * Display chart of accounts in hierarchical tree view
     */
    public function index(Request $request)
    {
        $data['title'] = __('chart_of_accounts');
        
        // Build query with hierarchical structure
        $query = ChartOfAccount::with('parent', 'children');
        
        // Filter by class if requested
        if ($request->has('class') && $request->class) {
            $query->where('class_number', $request->class);
        }
        
        // Get all accounts ordered by code with hierarchy preserved
        $data['accounts'] = $query->orderBy('account_code')->get();
        
        // Add level attribute for indentation
        foreach ($data['accounts'] as $account) {
            $account->level = $this->getAccountLevel($account);
        }
        
        // Get statistics
        $data['statistics'] = [
            'total_accounts' => ChartOfAccount::count(),
            'active_accounts' => ChartOfAccount::where('is_active', true)->count(),
            'detail_accounts' => ChartOfAccount::where('account_category', 'detail')->count(),
            'total_balance' => ChartOfAccount::where('account_category', 'detail')->sum('current_balance'),
        ];
        
        return view('admin.chart-of-accounts.index', $data);
    }
    
    /**
     * Get the hierarchical level of an account
     */
    private function getAccountLevel($account, $level = 0)
    {
        if (!$account->parent_id) {
            return 0;
        }
        
        $parent = ChartOfAccount::find($account->parent_id);
        if ($parent) {
            return $this->getAccountLevel($parent, $level + 1) + 1;
        }
        
        return $level;
    }

    /**
     * Show the form for creating a new account
     */
    public function create()
    {
        $data['title'] = __('add_account');
        $data['parentAccounts'] = ChartOfAccount::where('is_active', true)
            ->orderBy('account_code')
            ->get();
        
        return view('admin.chart-of-accounts.create', $data);
    }

    /**
     * Store a newly created account
     */
    public function store(Request $request)
    {
        $request->validate([
            'account_code' => 'required|string|max:20|unique:chart_of_accounts,account_code',
            'account_name' => 'required|string|max:255',
            'account_name_fr' => 'nullable|string|max:255',
            'class_number' => 'required|integer|between:1,9',
            'account_type' => 'required|in:asset,liability,equity,revenue,expense,other,analytical',
            'account_category' => 'required|in:detail,heading,total,subtotal',
            'normal_balance' => 'required|in:debit,credit',
            'parent_id' => 'nullable|exists:chart_of_accounts,id',
            'description' => 'nullable|string',
            'opening_balance' => 'nullable|numeric|min:0',
        ]);

        try {
            DB::beginTransaction();

            $account = ChartOfAccount::create([
                'account_code' => $request->account_code,
                'account_name' => $request->account_name,
                'account_name_fr' => $request->account_name_fr,
                'description' => $request->description,
                'parent_id' => $request->parent_id,
                'class_number' => $request->class_number,
                'account_type' => $request->account_type,
                'account_category' => $request->account_category,
                'opening_balance' => $request->opening_balance ?? 0,
                'current_balance' => $request->opening_balance ?? 0,
                'normal_balance' => $request->normal_balance,
                'is_active' => $request->has('is_active') ? 1 : 0,
                'display_order' => $request->display_order ?? 0,
                'created_by' => Auth::id(),
            ]);

            DB::commit();
            Toastr::success(__('account_created_successfully'), __('msg_success'));
            return redirect()->route('admin.chart-of-accounts.index');

        } catch (\Exception $e) {
            DB::rollBack();
            Toastr::error(__('msg_created_error') . ': ' . $e->getMessage(), __('msg_error'));
            return redirect()->back()->withInput();
        }
    }

    /**
     * Display the specified account with transaction history
     */
    public function show($id)
    {
        $data['title'] = __('account_details');
        $data['account'] = ChartOfAccount::with(['parent', 'children', 'journalEntryLines.journalEntry'])
            ->findOrFail($id);
        
        // Get recent transaction history (last 10)
        $data['transactions'] = $data['account']->journalEntryLines()
            ->with('journalEntry')
            ->whereHas('journalEntry', function($q) {
                $q->where('is_posted', true);
            })
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        
        return view('admin.chart-of-accounts.show', $data);
    }

    /**
     * Show the form for editing the account
     */
    public function edit($id)
    {
        $data['title'] = __('edit_account');
        $data['account'] = ChartOfAccount::findOrFail($id);
        
        // System accounts can be edited but with restrictions
        
        $data['parentAccounts'] = ChartOfAccount::where('is_active', true)
            ->where('id', '!=', $id) // Exclude self
            ->orderBy('account_code')
            ->get();
        
        return view('admin.chart-of-accounts.edit', $data);
    }

    /**
     * Update the specified account
     */
    public function update(Request $request, $id)
    {
        $account = ChartOfAccount::findOrFail($id);
        
        // Prevent editing system accounts
        if ($account->is_system) {
            Toastr::error(__('cannot_edit_system_account'), __('msg_error'));
            return redirect()->route('admin.chart-of-accounts.index');
        }

        $request->validate([
            'account_code' => 'required|string|max:20|unique:chart_of_accounts,account_code,' . $id,
            'account_name' => 'required|string|max:255',
            'account_name_fr' => 'nullable|string|max:255',
            'class_number' => 'required|integer|between:1,9',
            'account_type' => 'required|in:asset,liability,equity,revenue,expense,other,analytical',
            'account_category' => 'required|in:detail,heading,total,subtotal',
            'normal_balance' => 'required|in:debit,credit',
            'parent_id' => 'nullable|exists:chart_of_accounts,id',
            'description' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // Prevent circular reference
            if ($request->parent_id && $this->wouldCreateCircularReference($id, $request->parent_id)) {
                throw new \Exception(__('circular_reference_error'));
            }

            $account->update([
                'account_code' => $request->account_code,
                'account_name' => $request->account_name,
                'account_name_fr' => $request->account_name_fr,
                'description' => $request->description,
                'parent_id' => $request->parent_id,
                'class_number' => $request->class_number,
                'account_type' => $request->account_type,
                'account_category' => $request->account_category,
                'normal_balance' => $request->normal_balance,
                'is_active' => $request->has('is_active') ? 1 : 0,
                'display_order' => $request->display_order ?? 0,
                'updated_by' => Auth::id(),
            ]);

            DB::commit();
            Toastr::success(__('account_updated_successfully'), __('msg_success'));
            return redirect()->route('admin.chart-of-accounts.index');

        } catch (\Exception $e) {
            DB::rollBack();
            Toastr::error(__('msg_update_error') . ': ' . $e->getMessage(), __('msg_error'));
            return redirect()->back()->withInput();
        }
    }

    /**
     * Remove the specified account
     */
    public function destroy($id)
    {
        try {
            $account = ChartOfAccount::findOrFail($id);
            
            // Prevent deleting system accounts
            if ($account->is_system) {
                Toastr::error(__('cannot_delete_system_account'), __('msg_error'));
                return redirect()->route('admin.chart-of-accounts.index');
            }
            
            // Check if account has children
            if ($account->children()->count() > 0) {
                Toastr::error(__('cannot_delete_account_with_children'), __('msg_error'));
                return redirect()->route('admin.chart-of-accounts.index');
            }
            
            // Check if account has transactions
            if ($account->journalEntryLines()->count() > 0) {
                Toastr::error(__('cannot_delete_account_with_transactions'), __('msg_error'));
                return redirect()->route('admin.chart-of-accounts.index');
            }
            
            $account->delete();
            Toastr::success(__('account_deleted_successfully'), __('msg_success'));
            
        } catch (\Exception $e) {
            Toastr::error(__('msg_delete_error') . ': ' . $e->getMessage(), __('msg_error'));
        }
        
        return redirect()->route('admin.chart-of-accounts.index');
    }

    /**
     * Toggle account status (active/inactive)
     */
    public function toggleStatus($id)
    {
        try {
            $account = ChartOfAccount::findOrFail($id);
            
            // Prevent deactivating system accounts
            if ($account->is_system && $account->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => __('cannot_deactivate_system_account')
                ]);
            }
            
            $account->is_active = !$account->is_active;
            $account->updated_by = Auth::id();
            $account->save();
            
            return response()->json([
                'success' => true,
                'message' => __('account_status_updated'),
                'is_active' => $account->is_active
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => __('msg_error') . ': ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get accounts by class (AJAX)
     */
    public function getByClass(Request $request)
    {
        $classNumber = $request->input('class_number');
        $postableOnly = $request->input('postable_only', false);
        
        $query = ChartOfAccount::where('class_number', $classNumber)
            ->where('is_active', true)
            ->orderBy('account_code');
        
        if ($postableOnly) {
            $query->where('account_category', 'detail');
        }
        
        $accounts = $query->get(['id', 'account_code', 'account_name', 'account_name_fr']);
        
        return response()->json($accounts);
    }

    /**
     * Check if setting parent would create circular reference
     */
    private function wouldCreateCircularReference($accountId, $parentId)
    {
        if (!$parentId) {
            return false;
        }
        
        $parent = ChartOfAccount::find($parentId);
        
        while ($parent) {
            if ($parent->id == $accountId) {
                return true;
            }
            $parent = $parent->parent;
        }
        
        return false;
    }
}
