<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Program;
use App\Models\Faculty;
use App\Services\StaffAssignmentService;

class ProgramController extends Controller
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
        $this->title = trans_choice('module_program', 1);
        $this->route = 'admin.program';
        $this->view = 'admin.program';
        $this->path = 'program';
        $this->access = 'program';


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

        // Apply staff assignment filter to faculties
        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $facultyQuery = StaffAssignmentService::filterFaculties($facultyQuery);
        $data['faculties'] = $facultyQuery->get();
        
        // Get all academic departments
        $data['academicDepartments'] = \App\Models\AcademicDepartment::where('status', '1')->orderBy('faculty_id', 'asc')->orderBy('sort_order', 'asc')->get();
        
        // Get all degree types
        $data['degreeTypes'] = \App\Models\DegreeType::where('status', '1')->orderBy('sort_order', 'asc')->get();
        
        // Apply staff assignment filter to programs
        $programQuery = Program::orderBy('title', 'asc');
        $programQuery = StaffAssignmentService::filterPrograms($programQuery);
        $data['rows'] = $programQuery->get();

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
            'faculty' => 'required',
            'title' => 'required|max:191|unique:programs,title',
            'shortcode' => 'required|max:191|unique:programs,shortcode',
            'featured_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'banner_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Registration status
        if($request->registration == null || $request->registration != 1){
            $registration = 0;
        }
        else {
            $registration = 1;
        }

        // Insert Data
        $program = new Program;
        $program->faculty_id = $request->faculty;
        $program->academic_department_id = $request->academic_department;
        $program->degree_type_id = $request->degree_type;
        $program->title = $request->title;
        $program->slug = Str::slug($request->title, '-');
        $program->shortcode = $request->shortcode;
        $program->registration = $registration;
        
        // CMS Fields
        $program->excerpt = $request->excerpt;
        $program->description = $request->description;
        $program->duration = $request->duration;
        $program->credit = $request->credit;
        $program->requirements = $request->requirements;
        $program->career_prospects = $request->career_prospects;
        
        // Handle Featured Image Upload
        if($request->hasFile('featured_image')){
            $file = $request->file('featured_image');
            $filename = time().'_featured_'.$file->getClientOriginalName();
            $file->move(public_path('uploads/programs'), $filename);
            $program->featured_image = $filename;
        }
        
        // Handle Banner Image Upload
        if($request->hasFile('banner_image')){
            $file = $request->file('banner_image');
            $filename = time().'_banner_'.$file->getClientOriginalName();
            $file->move(public_path('uploads/programs'), $filename);
            $program->banner_image = $filename;
        }
        
        $program->save();


        Flasher::addSuccess(__('msg_created_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Program $program)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Program $program)
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
    public function update(Request $request, Program $program)
    {
        // Field Validation
        $request->validate([
            'faculty' => 'required',
            'title' => 'required|max:191|unique:programs,title,'.$program->id,
            'shortcode' => 'required|max:191|unique:programs,shortcode,'.$program->id,
            'featured_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'banner_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Registration status
        if($request->registration == null || $request->registration != 1){
            $registration = 0;
        }
        else {
            $registration = 1;
        }

        // Update Data
        $program->faculty_id = $request->faculty;
        $program->academic_department_id = $request->academic_department;
        $program->degree_type_id = $request->degree_type;
        $program->title = $request->title;
        $program->slug = Str::slug($request->title, '-');
        $program->shortcode = $request->shortcode;
        $program->registration = $registration;
        $program->status = $request->status;
        
        // CMS Fields
        $program->excerpt = $request->excerpt;
        $program->description = $request->description;
        $program->duration = $request->duration;
        $program->credit = $request->credit;
        $program->validity_years = $request->validity_years ?? 4;
        $program->requirements = $request->requirements;
        $program->career_prospects = $request->career_prospects;
        
        // Handle Featured Image Upload
        if($request->hasFile('featured_image')){
            // Delete old image if exists
            if($program->featured_image && file_exists(public_path('uploads/programs/'.$program->featured_image))){
                unlink(public_path('uploads/programs/'.$program->featured_image));
            }
            
            $file = $request->file('featured_image');
            $filename = time().'_featured_'.$file->getClientOriginalName();
            $file->move(public_path('uploads/programs'), $filename);
            $program->featured_image = $filename;
        }
        
        // Handle Banner Image Upload
        if($request->hasFile('banner_image')){
            // Delete old image if exists
            if($program->banner_image && file_exists(public_path('uploads/programs/'.$program->banner_image))){
                unlink(public_path('uploads/programs/'.$program->banner_image));
            }
            
            $file = $request->file('banner_image');
            $filename = time().'_banner_'.$file->getClientOriginalName();
            $file->move(public_path('uploads/programs'), $filename);
            $program->banner_image = $filename;
        }
        
        $program->save();


        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Program $program)
    {
        // Delete Data
        $program->delete();

        Flasher::addSuccess(__('msg_deleted_successfully'), __('msg_success'));

        return redirect()->back();
    }
}
