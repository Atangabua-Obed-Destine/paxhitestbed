<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use App\Models\Semester;
use App\Models\Program;

class SemesterController extends Controller
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
        $this->title = trans_choice('module_semester', 1);
        $this->route = 'admin.semester';
        $this->view = 'admin.semester';
        $this->path = 'semester';
        $this->access = 'semester';


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

        $data['programs'] = Program::where('status', '1')
                            ->orderBy('title', 'asc')->get();
        $data['rows'] = Semester::orderBy('id', 'asc')->get();
        $data['regular_semesters'] = Semester::where('is_resit', false)
                                     ->orderBy('year', 'asc')
                                     ->orderBy('semester_type', 'asc')
                                     ->get();

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
            'title' => 'required|max:191|unique:semesters,title',
            'year' => 'required',
            'semester_type' => 'required|in:1,2',
            'programs' => 'required',
            'is_resit' => 'nullable|boolean',
            'parent_semester_id' => 'nullable|exists:semesters,id',
        ]);

        // Insert Data
        $semester = new Semester;
        $semester->title = $request->title;
        $semester->year = $request->year;
        $semester->semester_type = $request->semester_type;
        $semester->is_resit = $request->boolean('is_resit');
        $semester->parent_semester_id = $request->is_resit && $request->parent_semester_id ? $request->parent_semester_id : null;
        $semester->save();

        $semester->programs()->attach($request->programs);


        Flasher::addSuccess(__('msg_created_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Semester $semester)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Semester $semester)
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
    public function update(Request $request, Semester $semester)
    {
        // Field Validation
        $request->validate([
            'title' => 'required|max:191|unique:semesters,title,'.$semester->id,
            'year' => 'required',
            'semester_type' => 'required|in:1,2',
            'programs' => 'required',
            'is_resit' => 'nullable|boolean',
            'parent_semester_id' => 'nullable|exists:semesters,id',
        ]);

        // Update Data
        $semester->title = $request->title;
        $semester->year = $request->year;
        $semester->semester_type = $request->semester_type;
        $semester->status = $request->status;
        $semester->is_resit = $request->boolean('is_resit');
        $semester->parent_semester_id = $request->is_resit && $request->parent_semester_id ? $request->parent_semester_id : null;
        $semester->save();

        $semester->programs()->sync($request->programs);


        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Semester $semester)
    {
        // Delete Data
        $semester->programs()->detach();
        $semester->delete();

        Flasher::addSuccess(__('msg_deleted_successfully'), __('msg_success'));

        return redirect()->back();
    }
}
