<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Flasher\Laravel\Facade\Flasher;
use App\Models\IdCardSetting;
use App\Models\LibraryMember;
use Illuminate\Http\Request;
use App\Models\Semester;
use App\Models\Program;
use App\Models\Section;
use App\Models\Session;
use App\Models\Faculty;
use App\Services\StaffAssignmentService;
use App\Models\Student;
use Carbon\Carbon;

class LibraryStudentController extends Controller
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
        $this->title = trans_choice('module_student', 1).' '.trans_choice('module_member', 1);
        $this->route = 'admin.library-student';
        $this->view = 'admin.library-student';
        $this->path = 'student';
        $this->access = 'library-member';


        $this->middleware('permission:'.$this->access.'-view|'.$this->access.'-create|'.$this->access.'-edit|'.$this->access.'-card', ['only' => ['index','show']]);
        $this->middleware('permission:'.$this->access.'-create', ['only' => ['create','store']]);
        $this->middleware('permission:'.$this->access.'-edit', ['only' => ['edit','update']]);
        $this->middleware('permission:'.$this->access.'-card', ['only' => ['libraryCard']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        //
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;


        if(!empty($request->faculty) || $request->faculty != null){
            $data['selected_faculty'] = $faculty = $request->faculty;
        }
        else{
            $data['selected_faculty'] = $faculty = '0';
        }

        if(!empty($request->program) || $request->program != null){
            $data['selected_program'] = $program = $request->program;
        }
        else{
            $data['selected_program'] = $program = '0';
        }

        if(!empty($request->session) || $request->session != null){
            $data['selected_session'] = $session = $request->session;
        }
        else{
            $data['selected_session'] = $session = '0';
        }

        if(!empty($request->semester) || $request->semester != null){
            $data['selected_semester'] = $semester = $request->semester;
        }
        else{
            $data['selected_semester'] = $semester = '0';
        }

        if(!empty($request->section) || $request->section != null){
            $data['selected_section'] = $section = $request->section;
        }
        else{
            $data['selected_section'] = $section = '0';
        }

        if(!empty($request->student_id) || $request->student_id != null){
            $data['selected_student_id'] = $student_id = $request->student_id;
        }
        else{
            $data['selected_student_id'] = Null;
        }


        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();


        // Filter Search
        if(!empty($request->faculty) && $request->faculty != '0'){
        $data['programs'] = Program::where('faculty_id', $faculty)->where('status', '1')->orderBy('title', 'asc')->get();}

        if(!empty($request->program) && $request->program != '0'){
        $sessions = Session::where('status', 1);
        $sessions->with('programs')->whereHas('programs', function ($query) use ($program){
            $query->where('program_id', $program);
        });
        $data['sessions'] = $sessions->orderBy('id', 'desc')->get();}

        if(!empty($request->program) && $request->program != '0'){
        $semesters = Semester::where('status', 1);
        $semesters->with('programs')->whereHas('programs', function ($query) use ($program){
            $query->where('program_id', $program);
        });
        $data['semesters'] = $semesters->orderBy('id', 'asc')->get();}

        if(!empty($request->program) && $request->program != '0' && !empty($request->semester) && $request->semester != '0'){
        $sections = Section::where('status', 1);
        $sections->with('semesterPrograms')->whereHas('semesterPrograms', function ($query) use ($program, $semester){
            $query->where('program_id', $program);
            $query->where('semester_id', $semester);
        });
        $data['sections'] = $sections->orderBy('title', 'asc')->get();}


        if(isset($request->faculty) || isset($request->program) || isset($request->session) || isset($request->semester) || isset($request->section) || isset($request->student_id)){
            // Student Filter
            $students = Student::where('id', '!=', '0');
            if($faculty != 0){
                $students->with('program')->whereHas('program', function ($query) use ($faculty){
                    $query->where('faculty_id', $faculty);
                });
            }
            if($program != 0){
                $students->where('program_id', $program);
            }
            if($session != 0 || $semester != 0 || $section != 0){
            $students->with('currentEnroll')->whereHas('currentEnroll', function ($query) use ($session, $semester, $section){
                if($session != 0){
                $query->where('session_id', $session);
                }
                if($semester != 0){
                $query->where('semester_id', $semester);
                }
                if($section != 0){
                $query->where('section_id', $section);
                }
                $query->where('status', '1');
            });
            }
            if(!empty($request->student_id)){
                $students->where(function($query) use ($student_id){
                    $query->where('student_id', 'LIKE', '%'.$student_id.'%');
                    $query->orWhereHas('studentEnrolls', function ($q) use ($student_id) {
                        $q->where('matricule', 'LIKE', '%'.$student_id.'%');
                    });
                });
            }
            $rows = $students->with('studentEnrolls')->get();

            // Array Sorting
            $data['rows'] = $rows->sortByDesc(function($query){

               return $query->studentEnrolls->sortByDesc('id')->first()->matricule ?? $query->student_id;

            })->all();
        }


        $data['print'] = IdCardSetting::where('slug', 'library-card')->first();

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
            'library_id' => 'required|unique:library_members,library_id',
            'member_id' => 'required',
        ]);

        $student = Student::findOrFail($request->member_id);
        $card_setting = IdCardSetting::where('slug', 'library-card')->first();


        // Insert Data
        $member = new LibraryMember;
        $member->library_id = $request->library_id;
        $member->date = Carbon::today()->addYears($card_setting->validity ?? 0)->format('Y-m-d');
        $member->created_by = Auth::guard('web')->user()->id;

        $student->member()->save($member);


        Flasher::addSuccess(__('msg_added_successfully'), __('msg_success'));

        return redirect()->back();
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
            'member_id' => 'required',
            'status' => 'required'
        ]);


        // Update Data
        $member = LibraryMember::findOrFail($request->member_id);
        $member->status = $request->status;
        $member->updated_by = Auth::guard('web')->user()->id;
        $member->save();

        if($request->status == 0){
            Flasher::addSuccess(__('msg_canceled_successfully'), __('msg_success'));
        }
        elseif($request->status == 1){
            Flasher::addSuccess(__('msg_approve_successfully'), __('msg_success'));
        }

        return redirect()->back();
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function libraryCard($id)
    {
        //
        $data['title']     = $this->title;
        $data['route']     = $this->route;
        $data['view']      = $this->view;
        $data['path']      = $this->path;

        $data['row'] = LibraryMember::findOrFail($id);

        $data['print'] = IdCardSetting::where('slug', 'library-card')->firstOrFail();

        return view($this->view.'.card', $data);
    }
}
