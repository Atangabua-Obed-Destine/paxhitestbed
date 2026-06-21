<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FiscalYear;
use App\Models\AccountingPeriod;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Toastr;
use DB;

class FiscalYearController extends Controller
{
    /**
     * Constructor - Apply permissions middleware
     */
    public function __construct()
    {
        // Apply permissions middleware
        $this->middleware('permission:accounting-period-view', ['only' => ['index', 'show']]);
        $this->middleware('permission:accounting-period-create', ['only' => ['create', 'store', 'generatePeriods']]);
        $this->middleware('permission:accounting-period-edit', ['only' => ['edit', 'update']]);
        $this->middleware('permission:accounting-period-delete', ['only' => ['destroy']]);
        $this->middleware('permission:accounting-period-close', ['only' => ['closePeriod']]);
        $this->middleware('permission:accounting-period-reopen', ['only' => ['reopenPeriod']]);
    }

    /**
     * Display a listing of fiscal years
     */
    public function index()
    {
        $data['title'] = __('fiscal_years');
        $data['fiscalYears'] = FiscalYear::with(['accountingPeriods', 'journalEntries'])
            ->orderBy('start_date', 'desc')
            ->get();
        
        return view('admin.fiscal-years.index', $data);
    }

    /**
     * Show the form for creating a new fiscal year
     */
    public function create()
    {
        $data['title'] = __('create_fiscal_year');
        
        return view('admin.fiscal-years.create', $data);
    }

    /**
     * Store a newly created fiscal year
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_active' => 'boolean',
        ]);

        try {
            DB::beginTransaction();

            // Get the is_active value (default to 0 if not provided)
            $isActive = $request->input('is_active', 0);

            // If this is to be active, deactivate all other fiscal years
            if ($isActive == 1) {
                FiscalYear::where('is_active', true)->update(['is_active' => false]);
            }

            // Create fiscal year
            $fiscalYear = FiscalYear::create([
                'name' => $request->name,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'is_active' => $isActive,
                'created_by' => Auth::id(),
            ]);

            // Automatically create accounting periods (monthly)
            if ($request->has('create_periods')) {
                $this->createMonthlyPeriods($fiscalYear);
            }

            DB::commit();
            Toastr::success(__('fiscal_year_created_successfully'), __('msg_success'));
            return redirect()->route('admin.fiscal-years.index');

        } catch (\Exception $e) {
            DB::rollBack();
            Toastr::error(__('msg_created_error') . ': ' . $e->getMessage(), __('msg_error'));
            return redirect()->back()->withInput();
        }
    }

    /**
     * Display the specified fiscal year
     */
    public function show($id)
    {
        $data['title'] = __('fiscal_year_details');
        $data['fiscalYear'] = FiscalYear::with(['accountingPeriods.journalEntries', 'journalEntries'])
            ->findOrFail($id);
        
        return view('admin.fiscal-years.show', $data);
    }

    /**
     * Show the form for editing the specified fiscal year
     */
    public function edit($id)
    {
        $data['title'] = __('edit_fiscal_year');
        $data['fiscalYear'] = FiscalYear::findOrFail($id);
        
        return view('admin.fiscal-years.edit', $data);
    }

    /**
     * Update the specified fiscal year
     */
    public function update(Request $request, $id)
    {
        $fiscalYear = FiscalYear::findOrFail($id);
        
        // Prevent editing closed fiscal years
        if ($fiscalYear->is_closed) {
            Toastr::error(__('cannot_edit_closed_fiscal_year'), __('msg_error'));
            return redirect()->route('admin.fiscal-years.index');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        try {
            DB::beginTransaction();

            // Get the is_active value (default to 0 if not provided)
            $isActive = $request->input('is_active', 0);

            // If this is to be active, deactivate all other fiscal years (except this one)
            if ($isActive == 1) {
                FiscalYear::where('is_active', true)
                    ->where('id', '!=', $id)
                    ->update(['is_active' => false]);
            }

            $fiscalYear->update([
                'name' => $request->name,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'is_active' => $isActive,
                'updated_by' => Auth::id(),
            ]);

            DB::commit();
            Toastr::success(__('fiscal_year_updated_successfully'), __('msg_success'));
            return redirect()->route('admin.fiscal-years.index');

        } catch (\Exception $e) {
            DB::rollBack();
            Toastr::error(__('msg_update_error') . ': ' . $e->getMessage(), __('msg_error'));
            return redirect()->back()->withInput();
        }
    }

    /**
     * Close the fiscal year
     */
    public function close(Request $request, $id)
    {
        $request->validate([
            'closing_note' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $fiscalYear = FiscalYear::findOrFail($id);
            
            // Prevent closing already closed year
            if ($fiscalYear->is_closed) {
                throw new \Exception(__('fiscal_year_already_closed'));
            }

            // Check if all periods are closed
            $openPeriods = $fiscalYear->accountingPeriods()->where('is_closed', false)->count();
            if ($openPeriods > 0) {
                throw new \Exception(__('close_all_periods_first'));
            }

            // Check if all entries are posted
            $unpostedEntries = $fiscalYear->journalEntries()->where('is_posted', false)->count();
            if ($unpostedEntries > 0) {
                throw new \Exception(__('post_all_entries_first'));
            }

            // Close the fiscal year
            $fiscalYear->update([
                'is_closed' => true,
                'is_active' => false,
                'closed_by' => Auth::id(),
                'closed_at' => now(),
                'closing_note' => $request->closing_note,
            ]);

            DB::commit();
            Toastr::success(__('fiscal_year_closed_successfully'), __('msg_success'));
            
        } catch (\Exception $e) {
            DB::rollBack();
            Toastr::error($e->getMessage(), __('msg_error'));
        }
        
        return redirect()->route('admin.fiscal-years.show', $id);
    }

    /**
     * Reopen a closed fiscal year
     */
    public function reopen($id)
    {
        try {
            DB::beginTransaction();

            $fiscalYear = FiscalYear::findOrFail($id);
            
            if (!$fiscalYear->is_closed) {
                throw new \Exception(__('fiscal_year_not_closed'));
            }

            $fiscalYear->update([
                'is_closed' => false,
                'closed_by' => null,
                'closed_at' => null,
                'closing_note' => null,
            ]);

            DB::commit();
            Toastr::success(__('fiscal_year_reopened_successfully'), __('msg_success'));
            
        } catch (\Exception $e) {
            DB::rollBack();
            Toastr::error($e->getMessage(), __('msg_error'));
        }
        
        return redirect()->route('admin.fiscal-years.show', $id);
    }

    /**
     * Set fiscal year as active
     */
    public function setActive($id)
    {
        try {
            DB::beginTransaction();

            $fiscalYear = FiscalYear::findOrFail($id);
            
            if ($fiscalYear->is_closed) {
                throw new \Exception(__('cannot_activate_closed_fiscal_year'));
            }

            // Deactivate all other fiscal years
            FiscalYear::where('is_active', true)->update(['is_active' => false]);

            // Activate this one
            $fiscalYear->update(['is_active' => true]);

            DB::commit();
            Toastr::success(__('fiscal_year_activated_successfully'), __('msg_success'));
            
        } catch (\Exception $e) {
            DB::rollBack();
            Toastr::error($e->getMessage(), __('msg_error'));
        }
        
        return redirect()->route('admin.fiscal-years.index');
    }

    /**
     * Create monthly accounting periods for a fiscal year
     */
    private function createMonthlyPeriods(FiscalYear $fiscalYear)
    {
        $startDate = Carbon::parse($fiscalYear->start_date);
        $endDate = Carbon::parse($fiscalYear->end_date);
        
        $currentDate = $startDate->copy();
        $periodNumber = 1;
        
        $monthsFrench = [
            1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
            5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
            9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
        ];
        
        $monthsEnglish = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        ];
        
        while ($currentDate <= $endDate) {
            $periodStart = $currentDate->copy()->startOfMonth();
            $periodEnd = $currentDate->copy()->endOfMonth();
            
            // Adjust for fiscal year boundaries
            if ($periodStart < $startDate) {
                $periodStart = $startDate->copy();
            }
            if ($periodEnd > $endDate) {
                $periodEnd = $endDate->copy();
            }
            
            AccountingPeriod::create([
                'fiscal_year_id' => $fiscalYear->id,
                'name' => $monthsEnglish[$currentDate->month] . ' ' . $currentDate->year,
                'french_name' => $monthsFrench[$currentDate->month] . ' ' . $currentDate->year,
                'period_number' => $periodNumber,
                'start_date' => $periodStart,
                'end_date' => $periodEnd,
                'created_by' => Auth::id(),
            ]);
            
            $currentDate->addMonth();
            $periodNumber++;
        }
    }

    /**
     * Generate periods for an existing fiscal year
     */
    public function generatePeriods($id)
    {
        try {
            DB::beginTransaction();

            $fiscalYear = FiscalYear::findOrFail($id);
            
            // Check if periods already exist
            if ($fiscalYear->accountingPeriods()->count() > 0) {
                throw new \Exception(__('periods_already_exist'));
            }

            $this->createMonthlyPeriods($fiscalYear);

            DB::commit();
            Toastr::success(__('periods_generated_successfully'), __('msg_success'));
            
        } catch (\Exception $e) {
            DB::rollBack();
            Toastr::error($e->getMessage(), __('msg_error'));
        }
        
        return redirect()->route('admin.fiscal-years.show', $id);
    }
}
