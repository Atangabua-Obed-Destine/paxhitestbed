<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Flasher\Laravel\Facade\Flasher;
use App\Models\FeesCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FeesCategoryController extends Controller
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
        $this->title = trans_choice('module_fees_category', 1);
        $this->route = 'admin.fees-category';
        $this->view = 'admin.fees-category';
        $this->path = 'fees-category';
        $this->access = 'fees-category';


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
        //
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;

        $data['rows'] = FeesCategory::orderBy('title', 'asc')->get();

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
        // Field Validation
        $request->validate([
            'title' => 'required|max:191|unique:fees_categories,title',
        ]);

        // Insert Data
        $feesCategory = new FeesCategory;
        $feesCategory->title = $request->title;
        $feesCategory->slug = Str::slug($request->title, '-');
        $feesCategory->description = $request->description;
        
        // Set fee type flags based on radio button selection
        $feesCategory->is_resit = ($request->fee_type == 'resit') ? 1 : 0;
        $feesCategory->is_admission = ($request->fee_type == 'admission') ? 1 : 0;
        $feesCategory->is_first_installment = ($request->fee_type == 'first_installment') ? 1 : 0;
        $feesCategory->is_second_installment = ($request->fee_type == 'second_installment') ? 1 : 0;
        
        $feesCategory->save();


        Flasher::addSuccess(__('msg_created_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(FeesCategory $feesCategory)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(FeesCategory $feesCategory)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, FeesCategory $feesCategory)
    {
        // Field Validation
        $request->validate([
            'title' => 'required|max:191|unique:fees_categories,title,'.$feesCategory->id,
        ]);

        // Update Data
        $feesCategory->title = $request->title;
        $feesCategory->slug = Str::slug($request->title, '-');
        $feesCategory->description = $request->description;
        $feesCategory->status = $request->status;
        
        // Set fee type flags based on radio button selection
        $feesCategory->is_resit = ($request->fee_type == 'resit') ? 1 : 0;
        $feesCategory->is_admission = ($request->fee_type == 'admission') ? 1 : 0;
        $feesCategory->is_first_installment = ($request->fee_type == 'first_installment') ? 1 : 0;
        $feesCategory->is_second_installment = ($request->fee_type == 'second_installment') ? 1 : 0;
        
        $feesCategory->save();


        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(FeesCategory $feesCategory)
    {
        // Delete Data
        $feesCategory->delete();

        Flasher::addSuccess(__('msg_deleted_successfully'), __('msg_success'));

        return redirect()->back();
    }
}
