<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Traits\FileUploader;
use App\Models\StudentEnroll;
use App\Models\StatusType;
use App\Models\Province;
use App\Models\District;
use App\Models\Semester;
use App\Models\Session;
use App\Models\Program;
use App\Models\Section;
use App\Models\Student;
use App\Models\Faculty;
use App\Services\StaffAssignmentService;
use App\Models\Batch;

class StudentArchiveController extends Controller
{
    use FileUploader;

    protected $title, $route, $view, $path, $access;

    public function __construct()
    {
        $this->title = 'Student Archives';
        $this->route = 'admin.student-archive';
        $this->view = 'admin.student-archive';
        $this->path = 'student';
        $this->access = 'student-archive';

        $this->middleware('permission:'.$this->access.'-view', ['only' => ['index','show']]);
        $this->middleware('permission:'.$this->access.'-edit', ['only' => ['edit','update']]);
        $this->middleware('permission:'.$this->access.'-password-change', ['only' => ['passwordChange']]);
    }

    /**
     * Display a listing of all students (archives)
     */
    public function index(Request $request)
    {
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;

        // Get filter values
        $data['search'] = $search = $request->search ?? null;

        // Query Logic - Simple Student Search
        $query = Student::query()->with('studentEnrolls');

        if(!empty($search)){
            $query->where(function($q) use ($search) {
                $q->where('student_id', 'LIKE', '%'.$search.'%')
                  ->orWhere('first_name', 'LIKE', '%'.$search.'%')
                  ->orWhere('last_name', 'LIKE', '%'.$search.'%')
                  ->orWhere('email', 'LIKE', '%'.$search.'%')
                  ->orWhereHas('studentEnrolls', function($q2) use ($search) {
                      $q2->where('matricule', 'LIKE', '%'.$search.'%');
                  });
            });
        }

        $data['rows'] = $query->orderBy('id', 'desc')->paginate(20);

        return view($this->view.'.index', $data);
    }

    /**
     * Display the specified student
     */
    public function show(Student $studentArchive)
    {
        return redirect()->route('admin.student.show', $studentArchive->id);
    }

    /**
     * Show the form for editing the specified student
     */
    public function edit(Student $studentArchive)
    {
        // Redirect to main student edit or keep separate? 
        // User said "view should instead go but to the particular student profile".
        // Assuming edit should also probably go to main student edit, but for now let's keep it or redirect.
        // Given the request "manage the student's portal... no need for all that filtering", 
        // and "view should instead go but to the particular student profile",
        // I will redirect show to admin.student.show.
        
        // For edit, I'll leave it as is for now unless requested, but the user said "student archives is only supposed to show the email, name, and action column".
        
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;

        $data['row'] = $studentArchive;
        $data['statuses'] = StatusType::where('status', '1')->orderBy('title', 'asc')->get();
        $data['provinces'] = Province::where('status', '1')->orderBy('name', 'asc')->get();
        
        if($studentArchive->present_province){
            $data['districts'] = District::where('province_id', $studentArchive->present_province)
                                        ->where('status', '1')
                                        ->orderBy('name', 'asc')
                                        ->get();
        } else {
            $data['districts'] = collect();
        }

        return view($this->view.'.edit', $data);
    }

    /**
     * Update the specified student
     */
    public function update(Request $request, Student $studentArchive)
    {
        // Validation
        $request->validate([
            'first_name' => 'required|string|max:191',
            'last_name' => 'required|string|max:191',
            'email' => 'required|email|max:191|unique:students,email,'.$studentArchive->id,
            'phone' => 'nullable|string|max:25',
            'dob' => 'nullable|date',
            'gender' => 'required|in:1,2,3',
            'address' => 'nullable|string|max:500',
            'province_id' => 'nullable|integer',
            'district_id' => 'nullable|integer',
            'status_id' => 'nullable|integer',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        try {
            DB::beginTransaction();

            // Update basic info
            $studentArchive->first_name = $request->first_name;
            $studentArchive->last_name = $request->last_name;
            $studentArchive->email = $request->email;
            $studentArchive->phone = $request->phone;
            $studentArchive->dob = $request->dob;
            $studentArchive->gender = $request->gender;
            $studentArchive->present_address = $request->address;
            $studentArchive->country = $request->country;
            $studentArchive->present_province = $request->province_id;
            $studentArchive->present_district = $request->district_id;

            // Handle photo upload
            if($request->hasFile('photo')){
                // Delete old photo
                $this->deleteMedia($this->path, $studentArchive->photo);
                // Upload new photo
                $studentArchive->photo = $this->uploadMedia($request, 'photo', $this->path);
            }

            $studentArchive->save();

            // Update status if provided
            if($request->status_id){
                $studentArchive->statuses()->sync([$request->status_id]);
            }

            DB::commit();

            Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));
            return redirect()->route($this->route.'.index');

        } catch(\Exception $e) {
            DB::rollBack();
            Flasher::addError(__('msg_updated_error') . ': ' . $e->getMessage(), __('msg_error'));
            return redirect()->back()->withInput();
        }
    }

    /**
     * Change student password
     */
    public function passwordChange(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'password' => 'required|string|min:6|confirmed',
        ]);

        try {
            $student = Student::findOrFail($request->id);
            $student->password = Hash::make($request->password);
            $student->save();

            Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));
            return redirect()->back();

        } catch(\Exception $e) {
            Flasher::addError(__('msg_error') . ': ' . $e->getMessage(), __('msg_error'));
            return redirect()->back();
        }
    }
}
