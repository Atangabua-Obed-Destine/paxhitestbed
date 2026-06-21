<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\DegreeType;

class DegreeTypeController extends Controller
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
        $this->title = trans_choice('module_degree_type', 1);
        $this->route = 'admin.degree-type';
        $this->view = 'admin.degree-type';
        $this->path = 'degree-type';
        $this->access = 'degree-type';

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

        $data['rows'] = DegreeType::orderBy('sort_order', 'asc')->orderBy('title', 'asc')->get();

        return view($this->view.'.index', $data);
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
            'title' => 'required|max:100|unique:degree_types,title',
            'shortcode' => 'required|max:20|unique:degree_types,shortcode',
            'level' => 'required|max:50',
            'duration_years' => 'nullable|integer|min:1|max:10',
            'min_credits' => 'nullable|integer|min:0|max:500',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        // Insert Data
        $degreeType = new DegreeType;
        $degreeType->title = $request->title;
        $degreeType->shortcode = strtoupper($request->shortcode);
        $degreeType->code_append_to_student_matricule = $request->code_append_to_student_matricule ? strtoupper($request->code_append_to_student_matricule) : null;
        $degreeType->level = $request->level;
        $degreeType->duration_years = $request->duration_years;
        $degreeType->min_credits = $request->min_credits;
        $degreeType->slug = Str::slug($request->title, '-');
        $degreeType->description = $request->description;
        $degreeType->requirements = $request->requirements;
        $degreeType->sort_order = $request->sort_order ?? 0;
        $degreeType->status = $request->status;
        $degreeType->is_hnd = $request->has('is_hnd') ? 1 : 0;
        $degreeType->is_postgraduate = $request->has('is_postgraduate') ? 1 : 0;
        $degreeType->save();

        Flasher::addSuccess(__('msg_created_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(DegreeType $degreeType)
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
    public function update(Request $request, DegreeType $degreeType)
    {
        // Field Validation
        $request->validate([
            'title' => 'required|max:100|unique:degree_types,title,'.$degreeType->id,
            'shortcode' => 'required|max:20|unique:degree_types,shortcode,'.$degreeType->id,
            'level' => 'required|max:50',
            'duration_years' => 'nullable|integer|min:1|max:10',
            'min_credits' => 'nullable|integer|min:0|max:500',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        // Update Data
        $degreeType->title = $request->title;
        $degreeType->shortcode = strtoupper($request->shortcode);
        $degreeType->code_append_to_student_matricule = $request->code_append_to_student_matricule ? strtoupper($request->code_append_to_student_matricule) : null;
        $degreeType->level = $request->level;
        $degreeType->duration_years = $request->duration_years;
        $degreeType->min_credits = $request->min_credits;
        $degreeType->slug = Str::slug($request->title, '-');
        $degreeType->description = $request->description;
        $degreeType->requirements = $request->requirements;
        $degreeType->sort_order = $request->sort_order ?? 0;
        $degreeType->status = $request->status;
        $degreeType->is_hnd = $request->has('is_hnd') ? 1 : 0;
        $degreeType->is_postgraduate = $request->has('is_postgraduate') ? 1 : 0;
        $degreeType->save();

        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(DegreeType $degreeType)
    {
        // Check if degree type has programs
        if($degreeType->programs()->count() > 0)
        {
            Flasher::addError(__('msg_cannot_delete_has_programs'), __('msg_error'));
            return redirect()->back();
        }

        // Delete Data
        $degreeType->delete();

        Flasher::addSuccess(__('msg_deleted_successfully'), __('msg_success'));

        return redirect()->back();
    }
}
