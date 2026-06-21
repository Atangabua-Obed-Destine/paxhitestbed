<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AcademicDepartment;
use App\Models\Faculty;
use App\User;
use Illuminate\Support\Str;
use Flasher;

class AcademicDepartmentController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Module Data
        $this->title = 'Academic Department';
        $this->route = 'admin.academic-department';
        $this->view = 'admin.academic-department';
        $this->path = 'academic-department';
        $this->access = 'academic-department';

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

        $data['faculties'] = Faculty::where('status', '1')->orderBy('title', 'asc')->get();
        $data['staff'] = User::where('status', '1')->orderBy('first_name', 'asc')->get();
        $data['rows'] = AcademicDepartment::with('faculty', 'headOfDepartment')->orderBy('faculty_id', 'asc')->orderBy('sort_order', 'asc')->get();

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
        // Validation
        $request->validate([
            'faculty' => 'required|integer',
            'title' => 'required|string|max:255|unique:academic_departments,title',
            'shortcode' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'head_of_department' => 'nullable|integer',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        // Insert Data
        $department = new AcademicDepartment;
        $department->faculty_id = $request->faculty;
        $department->title = $request->title;
        $department->slug = Str::slug($request->title, '-');
        $department->shortcode = $request->shortcode ? strtoupper($request->shortcode) : null;
        $department->description = $request->description;
        $department->head_of_department_id = $request->head_of_department;
        $department->email = $request->email;
        $department->phone = $request->phone;
        $department->sort_order = $request->sort_order ?? 0;
        $department->status = $request->status ?? 1;
        $department->save();

        Flasher::addSuccess('Academic Department has been added successfully!');

        return back();
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
        // Validation
        $request->validate([
            'faculty' => 'required|integer',
            'title' => 'required|string|max:255|unique:academic_departments,title,'.$id,
            'shortcode' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'head_of_department' => 'nullable|integer',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        // Update Data
        $department = AcademicDepartment::findOrFail($id);
        $department->faculty_id = $request->faculty;
        $department->title = $request->title;
        $department->slug = Str::slug($request->title, '-');
        $department->shortcode = $request->shortcode ? strtoupper($request->shortcode) : null;
        $department->description = $request->description;
        $department->head_of_department_id = $request->head_of_department;
        $department->email = $request->email;
        $department->phone = $request->phone;
        $department->sort_order = $request->sort_order ?? 0;
        $department->status = $request->status;
        $department->save();

        Flasher::addSuccess('Academic Department has been updated successfully!');

        return back();
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        // Check if department has programs
        $department = AcademicDepartment::findOrFail($id);
        
        if ($department->programs()->count() > 0) {
            Flasher::addError('Cannot delete! This department has associated programs.');
            return back();
        }

        $department->delete();

        Flasher::addSuccess('Academic Department has been deleted successfully!');

        return back();
    }
}
