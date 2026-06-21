<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeductionType;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeductionTypeController extends Controller
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
        $this->title = trans_choice('module_deduction_type', 2);
        $this->route = 'admin.deduction-type';
        $this->view = 'admin.deduction-type';
        $this->path = 'deduction-type';
        $this->access = 'deduction-type';

        $this->middleware('permission:'.$this->access.'-view|'.$this->access.'-create|'.$this->access.'-edit|'.$this->access.'-delete', ['only' => ['index','show']]);
        $this->middleware('permission:'.$this->access.'-create', ['only' => ['create','store']]);
        $this->middleware('permission:'.$this->access.'-edit', ['only' => ['edit','update']]);
        $this->middleware('permission:'.$this->access.'-delete', ['only' => ['destroy']]);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;

        $data['rows'] = DeductionType::orderBy('title', 'asc')->get();

        return view($this->view.'.index', $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Field Validation
        $request->validate([
            'title' => 'required|max:191|unique:deduction_types,title',
            'description' => 'nullable',
        ]);

        // Insert Data
        $deductionType = new DeductionType;
        $deductionType->title = $request->title;
        $deductionType->description = $request->description;
        $deductionType->status = $request->status;
        $deductionType->save();

        Flasher::addSuccess(__('msg_created_successfully'), __('msg_success'));

        return redirect()->route($this->route.'.index');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        // Field Validation
        $request->validate([
            'title' => 'required|max:191|unique:deduction_types,title,'.$id,
            'description' => 'nullable',
        ]);

        // Update Data
        $deductionType = DeductionType::findOrFail($id);
        $deductionType->title = $request->title;
        $deductionType->description = $request->description;
        $deductionType->status = $request->status;
        $deductionType->save();

        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->route($this->route.'.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        // Delete Data
        $deductionType = DeductionType::findOrFail($id);
        $deductionType->delete();

        Flasher::addSuccess(__('msg_deleted_successfully'), __('msg_success'));

        return redirect()->route($this->route.'.index');
    }
}
