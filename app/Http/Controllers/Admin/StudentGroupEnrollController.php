<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Support\Facades\DB;
use App\Models\StudentEnroll;
use Illuminate\Http\Request;
use App\Models\Semester;
use App\Models\Faculty;
use App\Services\StaffAssignmentService;
use App\Models\Program;
use App\Models\ProgramSessionMaxCredit;
use App\Models\Section;
use App\Models\Session;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Grade;

class StudentGroupEnrollController extends Controller
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
        $this->title = trans_choice('module_group_enroll', 1);
        $this->route = 'admin.group-enroll';
        $this->view = 'admin.group-enroll';
        $this->path = 'student';
        $this->access = 'student-enroll';


        $this->middleware('permission:'.$this->access.'-group');
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
            $data['selected_faculty'] = '0';
            $faculty = null;
        }

        if(!empty($request->program) || $request->program != null){
            $data['selected_program'] = $program = $request->program;
        }
        else{
            $data['selected_program'] = '0';
            $program = null;
        }

        if(!empty($request->session) || $request->session != null){
            $data['selected_session'] = $session = $request->session;
        }
        else{
            $data['selected_session'] = '0';
            $session = null;
        }

        if(!empty($request->semester) || $request->semester != null){
            $data['selected_semester'] = $semester = $request->semester;
        }
        else{
            $data['selected_semester'] = '0';
            $semester = null;
        }

        if(!empty($request->semester_year) || $request->semester_year != null){
            $data['selected_semester_year'] = $selected_semester_year = $request->semester_year;
        }
        else{
            $data['selected_semester_year'] = $selected_semester_year = '0';
        }

        if(!empty($request->section) || $request->section != null){
            $data['selected_section'] = $section = $request->section;
        }
        else{
            $data['selected_section'] = '0';
            $section = null;
        }


        // Search Filter
        $data['programs'] = collect();
        $data['sessions'] = collect();
        $data['semesters'] = collect();
        $data['semesterOptions'] = [];
        $data['sections'] = collect();
        $data['subjects'] = collect();
        $data['grades'] = collect();
        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();


        if(!empty($faculty)){
            $data['programs'] = Program::where('faculty_id', $faculty)->where('status', '1')->orderBy('title', 'asc')->get();
        }

        if(!empty($program)){
            $sessions = Session::where('status', 1);
            $sessions->with('programs')->whereHas('programs', function ($query) use ($program){
                $query->where('program_id', $program);
            });
            $data['sessions'] = $sessions->orderBy('id', 'desc')->get();

            $semesters = Semester::where('status', 1);
            $semesters->with('programs')->whereHas('programs', function ($query) use ($program){
                $query->where('program_id', $program);
            });
            $semesters->where('is_resit', '!=', 1);
            $semesterCollection = $semesters->orderBy('year', 'asc')->orderBy('id', 'asc')->get();
            $data['semesters'] = $semesterCollection;
            $data['semesterOptions'] = $semesterCollection
                ->filter(function ($semester) {
                    return !is_null($semester->year);
                })
                ->groupBy('year')
                ->sortKeys()
                ->map(function ($items) {
                    return $items->map(function ($semester) {
                        return [
                            'id' => $semester->id,
                            'title' => $semester->title,
                        ];
                    })->values();
                })
                ->toArray();

            $subjects = Subject::where('status', 1);
            $subjects->with('programs')->whereHas('programs', function ($query) use ($program){
                $query->where('program_id', $program);
            });
            $data['subjects'] = $subjects->orderBy('code', 'asc')->get();
        }

        if(!empty($program) && !empty($semester)){
            $sections = Section::where('status', 1);
            $sections->with('semesterPrograms')->whereHas('semesterPrograms', function ($query) use ($program, $semester){
                $query->where('program_id', $program);
                $query->where('semester_id', $semester);
            });
            $data['sections'] = $sections->orderBy('title', 'asc')->get();
        }

        if($data['grades']->isEmpty()){
            $data['grades'] = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();
        }


        // Student Filter
        if(!empty($request->faculty) && !empty($request->program) && !empty($request->session) && !empty($request->semester) && !empty($request->section)){

            $students = Student::where('status', '1');
            
            // Note: Faculty filter is applied via program selection (program belongs to faculty)
            // We filter by enrollment's program_id, not student's default program_id
            // This ensures students with multi-program enrollments are found correctly
            
            if(!empty($request->program) && !empty($request->session) && !empty($request->semester) && !empty($request->section)){
                // Filter by ANY enrollment matching criteria (not just currentEnroll)
                // This handles students with multiple program enrollments correctly
                $students->whereHas('studentEnrolls', function ($query) use ($program, $session, $semester, $section){
                    $query->where('program_id', $program);
                    $query->where('session_id', $session);
                    $query->where('semester_id', $semester);
                    $query->where('section_id', $section);
                    $query->where('status', '1');
                });
                
                // Eager load the specific enrollment that matches the filter criteria
                $students->with(['studentEnrolls' => function($query) use ($program, $session, $semester, $section){
                    $query->where('program_id', $program);
                    $query->where('session_id', $session);
                    $query->where('semester_id', $semester);
                    $query->where('section_id', $section);
                    $query->where('status', '1');
                }]);
            }

            $rows = $students->get();
            
            // Attach the filtered enrollment as a custom property for easy access in view
            foreach($rows as $student){
                $student->filteredEnroll = $student->studentEnrolls->first();
            }

            // Array Sorting - sort by the filtered enrollment's matricule
            $data['rows'] = $rows->sortBy(function($query){
               return $query->filteredEnroll->matricule ?? $query->student_id;
            })->all();
        }

        // Get max credit limit configuration if program and session are selected
        $data['maxCreditLimit'] = null;
        if(!empty($program) && !empty($session)){
            $programModel = Program::find($program);
            if($programModel){
                $facultyId = $programModel->faculty_id ? (int) $programModel->faculty_id : null;
                $data['maxCreditLimit'] = ProgramSessionMaxCredit::resolveLimit(
                    (int) $program,
                    (int) $session,
                    $facultyId
                );
            }
        }

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
            'semester' => 'required',
            'session' => 'required',
            'section' => 'required',
            'program' => 'required',
            'students' => 'required',
            'subjects' => 'required',
        ]);

        $program = Program::findOrFail($request->program);

        $facultyId = $program->faculty_id ? (int) $program->faculty_id : null;
        $maxCreditLimit = ProgramSessionMaxCredit::resolveLimit(
            (int) $request->program,
            (int) $request->session,
            $facultyId
        );

        if ($maxCreditLimit !== null) {
            $selectedCredits = (int) Subject::whereIn('id', (array) $request->subjects)->sum('credit_hour');
            if ($selectedCredits > $maxCreditLimit) {
                return redirect()->back()->withInput()->withErrors([
                    'subjects' => __('Selected subjects total :total Credits but the maximum allowed is :limit.', [
                        'total' => $selectedCredits,
                        'limit' => $maxCreditLimit,
                    ]),
                ]);
            }
        }


        try{
            DB::beginTransaction();

            foreach($request->students as $key => $student){
            if(!empty($student) || $student == ''){

                // Duplicate Enroll Check
                $duplicate_check = StudentEnroll::where('student_id', $student)
                    ->where('session_id', $request->session)
                    ->where('semester_id', $request->semester)
                    ->where('section_id', $request->section)
                    ->first();
                // $semester_check = StudentEnroll::where('student_id', $student)->where('semester_id', $request->semester)->first();

                if(!isset($duplicate_check)){
                    // Pre Enroll Update
                    // Close ONLY the enrollment for this specific program (Promotion within same program)
                    $pre_enroll = StudentEnroll::where('student_id', $student)
                                            ->where('program_id', $request->program)
                                            ->where('status', '1')
                                            ->first();
                    
                    if(isset($pre_enroll)){
                        $pre_enroll->status = '0';
                        $pre_enroll->save();
                    }

                    // Student New Enroll
                    $enroll = new StudentEnroll;
                    $enroll->student_id = $student;
                    $enroll->program_id = $request->program;
                    $enroll->session_id = $request->session;
                    $enroll->semester_id = $request->semester;
                    $enroll->section_id = $request->section;
                    $enroll->created_by = Auth::guard('web')->user()->id;

                    // Preserve Matricule from previous enrollment in same program
                    $previousEnrollment = StudentEnroll::where('student_id', $student)
                                            ->where('program_id', $request->program)
                                            ->whereNotNull('matricule')
                                            ->orderBy('id', 'desc')
                                            ->first();
                    
                    if ($previousEnrollment) {
                        $enroll->matricule = $previousEnrollment->matricule;
                    } else {
                         // Fallback to student_id if no previous matricule found
                         $studentModel = Student::find($student);
                         if($studentModel){
                             $enroll->matricule = $studentModel->student_id;
                         }
                    }

                    $enroll->save();

                    // Attach Subject
                    $enroll->subjects()->attach($request->subjects);

                    Flasher::addSuccess(__('msg_promoted_successfully'), __('msg_success'));
                }
                else{

                    Flasher::addError(__('msg_enroll_already_exists'), __('msg_error'));
                }
            }}
            DB::commit();

            return redirect()->back();
        }
        catch(\Exception $e){

            Flasher::addError(__('msg_created_error'), __('msg_error'));

            return redirect()->back();
        }
    }
}
