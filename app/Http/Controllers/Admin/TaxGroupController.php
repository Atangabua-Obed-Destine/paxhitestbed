<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use App\Models\TaxGroup;
use App\Models\TaxSetting;

class TaxGroupController extends Controller
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
        $this->title = trans_choice('module_tax_group', 1);
        $this->route = 'admin.tax-group';
        $this->view = 'admin.tax-group';
        $this->path = 'tax-group';
        $this->access = 'tax-setting'; // Share permissions with tax-setting

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

        $data['rows'] = TaxGroup::with('brackets')->ordered()->get();

        return view($this->view.'.index', $data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;

        return view($this->view.'.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Field Validation
        $request->validate([
            'title' => 'required|string|max:191',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
            'is_progressive' => 'required|boolean',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'display_order' => 'nullable|integer|min:0',
            // Which body this group's taxes are owed to. Individual brackets
            // may override it; left empty here and there, the posting code
            // falls back to the configured payroll tax account.
            'liability_account_id' => 'nullable|exists:chart_of_accounts,id',
        ]);

        // Auto-assign display order if not provided
        $displayOrder = $request->display_order;
        if (is_null($displayOrder)) {
            $maxOrder = TaxGroup::max('display_order') ?? 0;
            $displayOrder = $maxOrder + 1;
        }

        // Insert Data
        $taxGroup = TaxGroup::create([
            'title' => $request->title,
            'code' => $request->code,
            'description' => $request->description,
            'is_progressive' => $request->is_progressive,
            'effective_from' => $request->effective_from,
            'effective_to' => $request->effective_to,
            'display_order' => $displayOrder,
            'liability_account_id' => $request->liability_account_id ?: null,
            'status' => 1,
        ]);

        Flasher::addSuccess(__('msg_created_successfully'), __('msg_success'));

        return redirect()->route($this->route.'.show', $taxGroup->id);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;

        $data['row'] = TaxGroup::with(['brackets' => function($q) {
            $q->orderBy('bracket_order', 'asc')->orderBy('min_amount', 'asc');
        }])->findOrFail($id);

        return view($this->view.'.show', $data);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;

        $data['row'] = TaxGroup::findOrFail($id);

        return view($this->view.'.edit', $data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        // Field Validation
        $request->validate([
            'title' => 'required|string|max:191',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:500',
            'is_progressive' => 'required|boolean',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'display_order' => 'nullable|integer|min:0',
            'status' => 'required|boolean',
        ]);

        $taxGroup = TaxGroup::findOrFail($id);
        
        $taxGroup->update([
            'title' => $request->title,
            'code' => $request->code,
            'description' => $request->description,
            'is_progressive' => $request->is_progressive,
            'effective_from' => $request->effective_from,
            'effective_to' => $request->effective_to,
            'display_order' => $request->display_order ?? $taxGroup->display_order,
            'liability_account_id' => $request->liability_account_id ?: null,
            'status' => $request->status,
        ]);

        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->route($this->route.'.show', $taxGroup->id);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $taxGroup = TaxGroup::findOrFail($id);
        
        // Delete all brackets in this group (cascades via foreign key)
        $taxGroup->delete();

        Flasher::addSuccess(__('msg_deleted_successfully'), __('msg_success'));

        return redirect()->route($this->route.'.index');
    }

    /**
     * Store a new bracket in the group.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function storeBracket(Request $request, $id)
    {
        $taxGroup = TaxGroup::findOrFail($id);

        // Field Validation
        $request->validate([
            'title' => 'required|string|max:191',
            'bracket_order' => 'nullable|integer|min:0',
            'tax_type' => 'required|in:1,2',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'required|numeric|gt:min_amount',
            'percentange' => 'required_if:tax_type,1|nullable|numeric|min:0|max:100',
            'fixed_amount' => 'required_if:tax_type,2|nullable|numeric|min:0',
            'max_no_taxable_amount' => 'nullable|numeric|min:0',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
        ]);

        // Check for overlapping ranges within this group
        $overlapping = TaxSetting::where('tax_group_id', $id)
            ->where(function($q) use ($request) {
                $q->whereBetween('min_amount', [$request->min_amount, $request->max_amount])
                  ->orWhereBetween('max_amount', [$request->min_amount, $request->max_amount])
                  ->orWhere(function($q2) use ($request) {
                      $q2->where('min_amount', '<=', $request->min_amount)
                         ->where('max_amount', '>=', $request->max_amount);
                  });
            })
            ->exists();

        if ($overlapping) {
            Flasher::addError(__('bracket_range_overlap'), __('msg_error'));
            return redirect()->back()->withInput();
        }

        // Auto-assign bracket order if not provided
        $bracketOrder = $request->bracket_order;
        if (is_null($bracketOrder)) {
            $maxOrder = TaxSetting::where('tax_group_id', $id)->max('bracket_order') ?? 0;
            $bracketOrder = $maxOrder + 1;
        }

        // Create bracket
        TaxSetting::create([
            'tax_group_id' => $id,
            'title' => $request->title,
            'bracket_order' => $bracketOrder,
            'tax_type' => $request->tax_type,
            'min_amount' => $request->min_amount,
            'max_amount' => $request->max_amount,
            'percentange' => $request->tax_type == 1 ? $request->percentange : 0,
            'fixed_amount' => $request->tax_type == 2 ? $request->fixed_amount : 0,
            'max_no_taxable_amount' => $request->max_no_taxable_amount ?? 0,
            'effective_from' => $request->effective_from,
            'effective_to' => $request->effective_to,
            'status' => 1,
        ]);

        Flasher::addSuccess(__('bracket_added_successfully'), __('msg_success'));

        return redirect()->route($this->route.'.show', $id);
    }

    /**
     * Update a bracket in the group.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $groupId
     * @param  int  $bracketId
     * @return \Illuminate\Http\Response
     */
    public function updateBracket(Request $request, $groupId, $bracketId)
    {
        $taxGroup = TaxGroup::findOrFail($groupId);
        $bracket = TaxSetting::where('tax_group_id', $groupId)->findOrFail($bracketId);

        // Field Validation
        $request->validate([
            'title' => 'required|string|max:191',
            'bracket_order' => 'nullable|integer|min:0',
            'tax_type' => 'required|in:1,2',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'required|numeric|gt:min_amount',
            'percentange' => 'required_if:tax_type,1|nullable|numeric|min:0|max:100',
            'fixed_amount' => 'required_if:tax_type,2|nullable|numeric|min:0',
            'max_no_taxable_amount' => 'nullable|numeric|min:0',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'status' => 'required|boolean',
        ]);

        // Check for overlapping ranges (excluding this bracket)
        $overlapping = TaxSetting::where('tax_group_id', $groupId)
            ->where('id', '!=', $bracketId)
            ->where(function($q) use ($request) {
                $q->whereBetween('min_amount', [$request->min_amount, $request->max_amount])
                  ->orWhereBetween('max_amount', [$request->min_amount, $request->max_amount])
                  ->orWhere(function($q2) use ($request) {
                      $q2->where('min_amount', '<=', $request->min_amount)
                         ->where('max_amount', '>=', $request->max_amount);
                  });
            })
            ->exists();

        if ($overlapping) {
            Flasher::addError(__('bracket_range_overlap'), __('msg_error'));
            return redirect()->back()->withInput();
        }

        // Update bracket
        $bracket->update([
            'title' => $request->title,
            'bracket_order' => $request->bracket_order ?? $bracket->bracket_order,
            'tax_type' => $request->tax_type,
            'min_amount' => $request->min_amount,
            'max_amount' => $request->max_amount,
            'percentange' => $request->tax_type == 1 ? $request->percentange : 0,
            'fixed_amount' => $request->tax_type == 2 ? $request->fixed_amount : 0,
            'max_no_taxable_amount' => $request->max_no_taxable_amount ?? 0,
            'effective_from' => $request->effective_from,
            'effective_to' => $request->effective_to,
            'status' => $request->status,
        ]);

        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->route($this->route.'.show', $groupId);
    }

    /**
     * Delete a bracket from the group.
     *
     * @param  int  $groupId
     * @param  int  $bracketId
     * @return \Illuminate\Http\Response
     */
    public function destroyBracket($groupId, $bracketId)
    {
        $bracket = TaxSetting::where('tax_group_id', $groupId)->findOrFail($bracketId);
        $bracket->delete();

        Flasher::addSuccess(__('msg_deleted_successfully'), __('msg_success'));

        return redirect()->route($this->route.'.show', $groupId);
    }
}
