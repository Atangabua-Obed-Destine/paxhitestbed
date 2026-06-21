<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Flasher\Laravel\Facade\Flasher;
use App\Models\StudentEnroll;
use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\Subject;
use App\Models\ProgramSessionMaxCredit;
use App\Models\Grade;

class SubjectAddDropController extends Controller
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
        $this->title = trans_choice('module_subject_adddrop', 1);
        $this->route = 'admin.subject-adddrop';
        $this->view = 'admin.subject-adddrop';
        $this->path = 'student';
        $this->access = 'student-enroll';


        $this->middleware('permission:'.$this->access.'-adddrop');
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


        // Get unique matricules for dropdown (one per unique matricule)
        $allEnrollments = StudentEnroll::with(['student', 'program'])
            ->whereHas('student', function($query) {
                $query->where('status', '1');
            })
            ->where('status', '1')
            ->orderBy('id', 'desc')
            ->get();
        
        // Group by matricule only - each unique matricule appears once
        // Students with multiple programs (different matricules) will appear multiple times
        $uniqueStudents = $allEnrollments->groupBy('matricule')->map(function($group) {
            return $group->sortByDesc('id')->first();
        })->values();
        
        $data['students'] = $uniqueStudents;

        if(!empty($request->student) && $request->student != Null){

            $data['selected_student'] = $request->student;

            // Search by matricule first, then by student_id
            $enrollment = StudentEnroll::where('matricule', $request->student)
                ->with(['student', 'program'])
                ->where('status', '1')
                ->orderBy('id', 'desc')
                ->first();
            
            if (!$enrollment) {
                // Fallback to student_id search
                $student = Student::where('student_id', $request->student)->where('status', '1');
                $student->with('currentEnroll')->whereHas('currentEnroll', function ($query){
                    $query->where('status', '1');
                });
                $data['row'] = $row = $student->first();
                
                // Get the enrollment for this student
                if ($row) {
                    $enrollment = StudentEnroll::where('student_id', $row->id)
                        ->where('status', '1')
                        ->orderBy('id', 'desc')
                        ->first();
                    $data['selected_enrollment'] = $enrollment;
                    $data['selected_program_id'] = $enrollment ? $enrollment->program_id : $row->program_id;
                }
            } else {
                // Found by matricule
                $data['row'] = $row = $enrollment->student;
                $data['selected_enrollment'] = $enrollment;
                $data['selected_program_id'] = $enrollment->program_id;
            }

            // Use the selected enrollment's program_id for filtering, not student's default
            $targetProgramId = $data['selected_program_id'] ?? $row->program_id;

            // Subjects - filter by the specific enrollment's program
            $subjects = Subject::where('status', '1');
            $subjects->with('programs')->whereHas('programs', function ($query) use ($targetProgramId){
                $query->where('program_id', $targetProgramId);
            });
            $data['subjects'] = $subjects->orderBy('code', 'asc')->get();


            // Current Enroll - use the selected enrollment if available
            $data['curr_enr'] = $data['selected_enrollment'] ?? StudentEnroll::where('student_id', $row->id)
                        ->where('status', '1')
                        ->orderBy('id', 'desc')->first();
            
            // Load relationships for the current enrollment
            if ($data['curr_enr']) {
                $data['curr_enr']->loadMissing(['program', 'session', 'semester', 'section', 'subjects', 'subjectMarks']);
            }

            $data['grades'] = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();
            
            // Get max credit limit configuration
            $data['maxCreditLimit'] = null;
            if($data['curr_enr']){
                $data['curr_enr']->loadMissing('program');
                $facultyId = $data['curr_enr']->program->faculty_id ?? null;
                $data['maxCreditLimit'] = ProgramSessionMaxCredit::resolveLimit(
                    (int) $data['curr_enr']->program_id,
                    (int) $data['curr_enr']->session_id,
                    $facultyId ? (int) $facultyId : null
                );
            }
        }
        else {
            $data['selected_student'] = Null;
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
            'student' => 'required',
            'subjects' => 'required',
        ]);


        // Enroll Update - use specific enrollment ID if provided
        if ($request->enrollment_id) {
            $enroll = StudentEnroll::find($request->enrollment_id);
        } else {
            $enroll = StudentEnroll::where('student_id', $request->student)
                                    ->where('status', '1')
                                    ->orderBy('id', 'desc')->first();
        }

        if (!$enroll) {
            Flasher::addWarning(__('Unable to locate an active enrollment for the selected student.'), __('msg_warning'));
            return redirect()->back();
        }

        $enroll->loadMissing('program');

        $facultyId = $enroll->program->faculty_id ?? null;
        $maxCreditLimit = ProgramSessionMaxCredit::resolveLimit(
            (int) $enroll->program_id,
            (int) $enroll->session_id,
            $facultyId ? (int) $facultyId : null
        );

        if ($maxCreditLimit !== null) {
            // Calculate current enrolled credits
            $currentCredits = (float) $enroll->subjects()->sum('credit_hour');
            
            // Calculate new subjects credits
            $newCredits = (float) Subject::whereIn('id', (array) $request->subjects)->sum('credit_hour');
            
            // Total credits after adding new subjects
            $totalCredits = $currentCredits + $newCredits;
            
            if ($totalCredits > $maxCreditLimit) {
                return redirect()->back()->withInput()->withErrors([
                    'subjects' => __('Adding these subjects would result in :total Credits, but the maximum allowed is :limit. Current enrollment: :current credits.', [
                        'total' => $totalCredits,
                        'limit' => $maxCreditLimit,
                        'current' => $currentCredits,
                    ]),
                ]);
            }
        }

        // Add new subjects without removing existing ones
        $enroll->subjects()->syncWithoutDetaching($request->subjects);


        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Drop a subject from student enrollment.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function drop(Request $request)
    {
        // Field Validation
        $request->validate([
            'student_id' => 'required',
            'subject_id' => 'required',
        ]);

        // Get specific enrollment if ID provided, otherwise get current
        if ($request->enrollment_id) {
            $enroll = StudentEnroll::find($request->enrollment_id);
        } else {
            $enroll = StudentEnroll::where('student_id', $request->student_id)
                                    ->where('status', '1')
                                    ->orderBy('id', 'desc')->first();
        }

        if (!$enroll) {
            Flasher::addWarning(__('Unable to locate an active enrollment for the selected student.'), __('msg_warning'));
            return redirect()->back();
        }

        // Detach the subject
        $enroll->subjects()->detach($request->subject_id);

        Flasher::addSuccess(__('Subject dropped successfully.'), __('msg_success'));

        return redirect()->back();
    }
}

