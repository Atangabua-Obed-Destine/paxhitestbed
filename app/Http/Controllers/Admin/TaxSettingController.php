<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use App\Models\TaxSetting;
use App\Models\TaxGroup;

class TaxSettingController extends Controller
{
    protected $title, $route, $view, $path, $access;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Module Data
        $this->title = trans_choice('module_tax_setting', 1);
        $this->route = 'admin.tax-setting';
        $this->view = 'admin.tax-setting';
        $this->path = 'tax-setting';
        $this->access = 'tax-setting';


        $this->middleware('permission:'.$this->access.'-view|'.$this->access.'-create|'.$this->access.'-edit|'.$this->access.'-delete', ['only' => ['index','show']]);
        $this->middleware('permission:'.$this->access.'-create', ['only' => ['create','store']]);
        $this->middleware('permission:'.$this->access.'-edit', ['only' => ['edit','update']]);
        $this->middleware('permission:'.$this->access.'-delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;

        $data['rows'] = TaxSetting::with('taxGroup')->orderBy('bracket_order', 'asc')->orderBy('min_amount', 'asc')->get();
        $data['tax_groups'] = TaxGroup::active()->ordered()->get();
        $data['standalone_taxes'] = TaxSetting::active()->standalone()->ordered()->get();

        return view($this->view.'.index', $data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Percentage / fixed-amount fields are required only when the
        // matching side actually contributes. Employer-only brackets
        // don't need an employee `percentange`, and vice-versa.
        $employeePays = in_array($request->paid_by, ['employee', 'both'], true);
        $employerPays = in_array($request->paid_by, ['employer', 'both'], true);
        $isPercent    = (string) $request->tax_type === '1';
        $isFixed      = (string) $request->tax_type === '2';

        //Field Validation
        $request->validate([
            'title' => 'required|string|max:191',
            'tax_group_id' => 'nullable|exists:tax_groups,id',
            'is_dependent' => 'nullable|boolean',
            'depends_on_type' => 'required_if:is_dependent,1|nullable|in:tax_group,tax_setting',
            'depends_on_id' => 'required_if:is_dependent,1|nullable|integer',
            'bracket_order' => 'nullable|integer|min:0',
            'tax_type' => 'required|in:1,2',
            'paid_by' => 'required|in:employee,employer,both',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'required|numeric|gt:min_amount',
            'percentange'           => [\Illuminate\Validation\Rule::requiredIf($isPercent && $employeePays), 'nullable', 'numeric', 'min:0', 'max:100'],
            'employer_percentage'   => [\Illuminate\Validation\Rule::requiredIf($isPercent && $employerPays), 'nullable', 'numeric', 'min:0', 'max:100'],
            'fixed_amount'          => [\Illuminate\Validation\Rule::requiredIf($isFixed   && $employeePays), 'nullable', 'numeric'],
            'employer_fixed_amount' => [\Illuminate\Validation\Rule::requiredIf($isFixed   && $employerPays), 'nullable', 'numeric'],
            'max_no_taxable_amount' => 'nullable|numeric',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
        ]);


        // Duplicate/Overlap Checking - ONLY check within the same tax group
        // Standalone taxes (no group) can have any range - no overlap check needed
        if ($request->tax_group_id) {
            $pretaxs = TaxSetting::where('tax_group_id', $request->tax_group_id)
                ->orderBy('min_amount', 'asc')->get();
            
            foreach($pretaxs as $pretax){
                // Check if the new range overlaps with existing range
                // Two ranges overlap if: new_min < existing_max AND new_max > existing_min
                $newMin = (float) $request->min_amount;
                $newMax = (float) $request->max_amount;
                $existingMin = (float) $pretax->min_amount;
                $existingMax = (float) $pretax->max_amount;
                
                if ($newMin < $existingMax && $newMax > $existingMin) {
                    Flasher::addError(__('msg_data_already_exists') . ' - Overlaps with: ' . ($pretax->title ?? 'Tax ID ' . $pretax->id) . ' (' . number_format($existingMin) . ' - ' . number_format($existingMax) . ')', __('msg_error'));
                    return redirect()->back()->withInput();
                }
            }
        }

        // Auto-assign bracket order if not provided
        $bracketOrder = $request->bracket_order;
        if (is_null($bracketOrder)) {
            $maxOrder = TaxSetting::max('bracket_order') ?? 0;
            $bracketOrder = $maxOrder + 1;
        }

        // Insert Data
        $taxSetting = new TaxSetting;
        $taxSetting->title = $request->title;
        $taxSetting->tax_group_id = $request->tax_group_id;
        $taxSetting->is_dependent = $request->is_dependent ? true : false;
        $taxSetting->depends_on_type = $request->is_dependent ? $request->depends_on_type : null;
        $taxSetting->depends_on_id = $request->is_dependent ? $request->depends_on_id : null;
        $taxSetting->bracket_order = $bracketOrder;
        $taxSetting->tax_type = $request->tax_type;
        $taxSetting->paid_by = $request->paid_by;
        $taxSetting->is_shared = $request->paid_by === 'both';
        $taxSetting->min_amount = $request->min_amount;
        $taxSetting->max_amount = $request->max_amount;
        $taxSetting->percentange = $request->tax_type == 1 ? ($request->percentange ?? 0) : 0;
        $taxSetting->employer_percentage = $request->tax_type == 1 ? ($request->employer_percentage ?? 0) : 0;
        $taxSetting->fixed_amount = $request->tax_type == 2 ? ($request->fixed_amount ?? 0) : 0;
        $taxSetting->employer_fixed_amount = $request->tax_type == 2 ? ($request->employer_fixed_amount ?? 0) : 0;
        $taxSetting->max_no_taxable_amount = $request->max_no_taxable_amount ?? '0';
        $taxSetting->effective_from = $request->effective_from;
        $taxSetting->effective_to = $request->effective_to;
        $taxSetting->save();


        Flasher::addSuccess(__('msg_created_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\TaxSetting  $taxSetting
     * @return \Illuminate\Http\Response
     */
    public function show(TaxSetting $taxSetting)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\TaxSetting  $taxSetting
     * @return \Illuminate\Http\Response
     */
    public function edit(TaxSetting $taxSetting)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\TaxSetting  $taxSetting
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, TaxSetting $taxSetting)
    {
        $employeePays = in_array($request->paid_by, ['employee', 'both'], true);
        $employerPays = in_array($request->paid_by, ['employer', 'both'], true);
        $isPercent    = (string) $request->tax_type === '1';
        $isFixed      = (string) $request->tax_type === '2';

        // Field Validation
        $request->validate([
            'title' => 'required|string|max:191',
            'tax_group_id' => 'nullable|exists:tax_groups,id',
            'is_dependent' => 'nullable|boolean',
            'depends_on_type' => 'required_if:is_dependent,1|nullable|in:tax_group,tax_setting',
            'depends_on_id' => 'required_if:is_dependent,1|nullable|integer',
            'bracket_order' => 'nullable|integer|min:0',
            'tax_type' => 'required|in:1,2',
            'paid_by' => 'required|in:employee,employer,both',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'required|numeric|gt:min_amount',
            'percentange'           => [\Illuminate\Validation\Rule::requiredIf($isPercent && $employeePays), 'nullable', 'numeric', 'min:0', 'max:100'],
            'employer_percentage'   => [\Illuminate\Validation\Rule::requiredIf($isPercent && $employerPays), 'nullable', 'numeric', 'min:0', 'max:100'],
            'fixed_amount'          => [\Illuminate\Validation\Rule::requiredIf($isFixed   && $employeePays), 'nullable', 'numeric'],
            'employer_fixed_amount' => [\Illuminate\Validation\Rule::requiredIf($isFixed   && $employerPays), 'nullable', 'numeric'],
            'max_no_taxable_amount' => 'nullable|numeric',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
        ]);


        // Duplicate/Overlap Checking - ONLY check within the same tax group
        // Standalone taxes (no group) can have any range - no overlap check needed
        if ($request->tax_group_id) {
            $pretaxs = TaxSetting::where('id', '!=', $taxSetting->id)
                ->where('tax_group_id', $request->tax_group_id)
                ->orderBy('min_amount', 'asc')->get();
            
            foreach($pretaxs as $pretax){
                // Check if the new range overlaps with existing range
                // Two ranges overlap if: new_min < existing_max AND new_max > existing_min
                $newMin = (float) $request->min_amount;
                $newMax = (float) $request->max_amount;
                $existingMin = (float) $pretax->min_amount;
                $existingMax = (float) $pretax->max_amount;
                
                if ($newMin < $existingMax && $newMax > $existingMin) {
                    Flasher::addError(__('msg_data_already_exists') . ' - Overlaps with: ' . ($pretax->title ?? 'Tax ID ' . $pretax->id) . ' (' . number_format($existingMin) . ' - ' . number_format($existingMax) . ')', __('msg_error'));
                    return redirect()->back()->withInput();
                }
            }
        }

        // Update Data
        $taxSetting->title = $request->title;
        $taxSetting->tax_group_id = $request->tax_group_id;
        $taxSetting->is_dependent = $request->is_dependent ? true : false;
        $taxSetting->depends_on_type = $request->is_dependent ? $request->depends_on_type : null;
        $taxSetting->depends_on_id = $request->is_dependent ? $request->depends_on_id : null;
        $taxSetting->bracket_order = $request->bracket_order ?? $taxSetting->bracket_order;
        $taxSetting->tax_type = $request->tax_type;
        $taxSetting->paid_by = $request->paid_by;
        $taxSetting->is_shared = $request->paid_by === 'both';
        $taxSetting->min_amount = $request->min_amount;
        $taxSetting->max_amount = $request->max_amount;
        $taxSetting->percentange = $request->tax_type == 1 ? ($request->percentange ?? 0) : 0;
        $taxSetting->employer_percentage = $request->tax_type == 1 ? ($request->employer_percentage ?? 0) : 0;
        $taxSetting->fixed_amount = $request->tax_type == 2 ? ($request->fixed_amount ?? 0) : 0;
        $taxSetting->employer_fixed_amount = $request->tax_type == 2 ? ($request->employer_fixed_amount ?? 0) : 0;
        $taxSetting->max_no_taxable_amount = $request->max_no_taxable_amount ?? '0';
        $taxSetting->effective_from = $request->effective_from;
        $taxSetting->effective_to = $request->effective_to;
        $taxSetting->status = $request->status;
        $taxSetting->save();

        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\TaxSetting  $taxSetting
     * @return \Illuminate\Http\Response
     */
    public function destroy(TaxSetting $taxSetting)
    {
        //Delete Data
        $taxSetting->delete();

        Flasher::addSuccess(__('msg_deleted_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Store a new tax exemption.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function storeExemption(Request $request)
    {
        // Validation
        $request->validate([
            'tax_setting_id' => 'required|exists:tax_settings,id',
            'user_id' => 'required|exists:users,id',
            'reason' => 'nullable|string|max:500',
            'custom_percentage' => 'nullable|numeric|min:0|max:100',
            'custom_fixed_amount' => 'nullable|numeric|min:0',
            'expires_at' => 'nullable|date|after_or_equal:today',
        ]);

        // Check if exemption already exists
        $exists = \App\Models\StaffTaxExemption::where('tax_setting_id', $request->tax_setting_id)
                                              ->where('user_id', $request->user_id)
                                              ->exists();

        if ($exists) {
            Flasher::addError(__('msg_data_already_exists'), __('msg_error'));
            return redirect()->back();
        }

        // Create exemption
        \App\Models\StaffTaxExemption::create([
            'tax_setting_id' => $request->tax_setting_id,
            'user_id' => $request->user_id,
            'reason' => $request->reason,
            'custom_percentage' => $request->custom_percentage,
            'custom_fixed_amount' => $request->custom_fixed_amount,
            'expires_at' => $request->expires_at,
        ]);

        Flasher::addSuccess(__('exemption_added_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Remove a tax exemption.
     *
     * @param  int  $tax_setting_id
     * @param  int  $user_id
     * @return \Illuminate\Http\Response
     */
    public function destroyExemption($tax_setting_id, $user_id)
    {
        // Find and delete the exemption
        $exemption = \App\Models\StaffTaxExemption::where('tax_setting_id', $tax_setting_id)
                                                  ->where('user_id', $user_id)
                                                  ->first();

        if ($exemption) {
            $exemption->delete();
            Flasher::addSuccess(__('exemption_removed_successfully'), __('msg_success'));
        } else {
            Flasher::addError(__('msg_data_not_found'), __('msg_error'));
        }

        return redirect()->back();
    }
}
