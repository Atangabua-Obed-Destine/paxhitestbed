<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\StudentEnroll;
use Illuminate\Http\Request;
use App\Models\Semester;
use App\Models\Program;
use App\Models\ProgramSessionMaxCredit;
use App\Models\Section;
use App\Models\Session;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Grade;
use App\Services\ProgramSwapService;

class StudentSingleEnrollController extends Controller
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
        $this->title = trans_choice('module_single_enroll', 1);
        $this->route = 'admin.single-enroll';
        $this->view = 'admin.single-enroll';
        $this->path = 'student';
        $this->access = 'student-enroll';


        $this->middleware('permission:'.$this->access.'-single');
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
        })->values()->sortBy(function($enrollment) {
            return $enrollment->matricule ?? $enrollment->student->student_id;
        });
        
        $data['students'] = $uniqueStudents;

        if(!empty($request->student) && $request->student != Null){

            $data['selected_student'] = $request->student;

            // Support student_id field containing matricule or old numeric format
            // First try to find by student_id field directly (handles both cases)
            $data['row'] = $student = Student::where('student_id', $request->student)->first();
            $selectedEnrollment = null;
            
            // If not found and looks like a matricule, try finding by enrollment matricule
            if (!$student && strpos($request->student, 'PAX') === 0) {
                $selectedEnrollment = StudentEnroll::where('matricule', $request->student)
                    ->with(['program', 'session', 'semester', 'section', 'subjects', 'subjectMarks'])
                    ->orderBy('id', 'desc')
                    ->first();
                $data['row'] = $student = $selectedEnrollment ? Student::find($selectedEnrollment->student_id) : null;
            }
            
            // If student found by student_id but request looks like matricule, get specific enrollment
            if ($student && strpos($request->student, 'PAX') === 0 && !$selectedEnrollment) {
                $selectedEnrollment = StudentEnroll::where('matricule', $request->student)
                    ->with(['program', 'session', 'semester', 'section', 'subjects', 'subjectMarks'])
                    ->orderBy('id', 'desc')
                    ->first();
            }
            
            // If student not found, show empty form
            if (!$student) {
                $data['selected_student'] = Null;
                $data['semesters'] = collect();
                $data['semesterOptions'] = [];
                return view($this->view.'.index', $data);
            }

            // Use the enrollment's program_id if a specific matricule was selected
            // This ensures data is loaded based on the specific enrollment, not just the student's default program
            $targetProgramId = $selectedEnrollment ? $selectedEnrollment->program_id : $student->program_id;

            // Filter Enroll Data
            $data['programs'] = Program::where('status', '1')->orderBy('title', 'asc')->get();

            $data['sessions'] = Session::with('programs')->whereHas('programs', function ($query) use ($targetProgramId){
                $query->where('program_id', $targetProgramId);
            })->where('status', '1')->orderBy('id', 'desc')->get();

            $semesters = Semester::with('programs')->whereHas('programs', function ($query) use ($targetProgramId){
                $query->where('program_id', $targetProgramId);
            })->where('status', '1')->where('is_resit', '!=', 1)->orderBy('year', 'asc')->orderBy('id', 'asc')->get();

            $data['semesters'] = $semesters;
            $data['semesterOptions'] = $semesters
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

            $data['sections'] = Section::with('semesterPrograms')->whereHas('semesterPrograms', function ($query) use ($targetProgramId){
                $query->where('program_id', $targetProgramId);
            })->where('status', '1')->orderBy('title', 'asc')->get();

            $data['subjects'] = Subject::with('programs')->whereHas('programs', function ($query) use ($targetProgramId){
                $query->where('program_id', $targetProgramId);
            })->where('status', '1')->orderBy('code', 'asc')->get();

            $data['grades'] = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();
            
            // Get the specific enrollment for the selected matricule, or latest if no specific matricule
            $data['latestStudentEnroll'] = $selectedEnrollment ?: StudentEnroll::where('student_id', $student->id)
                ->with('program')
                ->orderBy('id', 'desc')
                ->first();
            
            // Store the selected enrollment for the view
            $data['selectedEnrollment'] = $selectedEnrollment;
            $data['targetProgramId'] = $targetProgramId;
            
            // Get max credit limit configuration - calculate for current enrollment context
            $data['maxCreditLimit'] = null;
            if(isset($student) && $targetProgramId){
                $enroll = $selectedEnrollment ?: \App\Models\Student::enroll($student->id);
                if($enroll && $enroll->session_id){
                    $program = Program::find($targetProgramId);
                    if($program){
                        $facultyId = $program->faculty_id ? (int) $program->faculty_id : null;
                        $data['maxCreditLimit'] = ProgramSessionMaxCredit::resolveLimit(
                            (int) $targetProgramId,
                            (int) $enroll->session_id,
                            $facultyId
                        );
                    }
                }
            }
        }
        else {
            $data['selected_student'] = Null;
            $data['semesters'] = collect();
            $data['semesterOptions'] = [];
        }

        return view($this->view.'.index', $data);
    }

    /**
     * Validate program swap (AJAX endpoint)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function validateProgramSwap(Request $request)
    {
        try {
            $swapService = new ProgramSwapService();
            $validation = $swapService->validateProgramSwap($request->student_id, $request->program_id);
            
            return response()->json([
                'success' => true,
                'validation' => $validation
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
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
            'program' => 'required',
            'semester' => 'required',
            'session' => 'required',
            'section' => 'required',
            'subjects' => 'required',
            'program_change_reason' => 'required_if:is_program_change,1|nullable|string|max:1000',
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
            
            // Get student and check program change
            $student = Student::findOrFail($request->student);
            $isProgramChange = $student->program_id != $request->program;
            
            // Program change detected - NEW enrollment will be created
            // No blocking validation - just log the change for audit trail
            
            // Duplicate Enroll Check - must match ALL fields including program
            $duplicate_check = StudentEnroll::where('student_id', $request->student)
                ->where('program_id', $request->program)
                ->where('session_id', $request->session)
                ->where('semester_id', $request->semester)
                ->where('section_id', $request->section)
                ->first();

            if(!isset($duplicate_check)){
                // Pre Enroll Update
                // Close ONLY the enrollment for this specific program (Promotion/Re-enrollment)
                // This prevents closing enrollments for OTHER programs (Double Degree support)
                $pre_enroll = StudentEnroll::where('student_id', $request->student)
                                        ->where('program_id', $request->program)
                                        ->where('status', '1')
                                        ->first();
                                        
                if(isset($pre_enroll)){
                    $pre_enroll->status = '0';
                    $pre_enroll->save();
                }

                // Student New Enroll
                $enroll = new StudentEnroll;
                $enroll->student_id = $request->student;
                $enroll->program_id = $request->program;
                $enroll->session_id = $request->session;
                $enroll->semester_id = $request->semester;
                $enroll->section_id = $request->section;
                $enroll->created_by = Auth::guard('web')->user()->id;
                
                // Track program change
                if ($isProgramChange) {
                    $enroll->previous_program_id = $student->program_id;
                    $enroll->is_program_change = true;
                    $enroll->program_change_reason = $request->program_change_reason ?? 'Program swap during enrollment';
                }
                
                // Generate matricule for this enrollment
                // Always generate NEW matricule on program change, maintain on same program
                try {
                    if ($isProgramChange) {
                        // Program change: ALWAYS generate new matricule
                        $enroll->matricule = Student::generateEnrollmentMatricule(
                            $request->student,
                            $request->program,
                            $student->batch_id
                        );
                    } else {
                        // Same program: maintain existing matricule if available
                        $previousEnrollment = StudentEnroll::where('student_id', $request->student)
                                                           ->whereNotNull('matricule')
                                                           ->orderBy('id', 'desc')
                                                           ->first();
                        
                        if ($previousEnrollment && $previousEnrollment->matricule && strpos($previousEnrollment->matricule, 'PAX') === 0) {
                            $enroll->matricule = $previousEnrollment->matricule;
                        } else {
                            // No proper matricule found - generate new one
                            $enroll->matricule = Student::generateEnrollmentMatricule(
                                $request->student,
                                $request->program,
                                $student->batch_id
                            );
                        }
                    }
                } catch (\Exception $e) {
                    \Log::error('Matricule generation error in enrollment: ' . $e->getMessage());
                    // Fallback to student_id
                    $enroll->matricule = $student->student_id;
                }
                
                $enroll->save();

                // Attach Subject
                $enroll->subjects()->attach($request->subjects);

                // Program Update
                $student->program_id = $request->program;
                $student->save();

                if ($isProgramChange) {
                    Flasher::addSuccess(__('Student successfully enrolled with program change'), __('msg_success'));
                } else {
                    Flasher::addSuccess(__('msg_promoted_successfully'), __('msg_success'));
                }
            }
            else{

                Flasher::addError(__('msg_enroll_already_exists'), __('msg_error'));
            }
            DB::commit();

            return redirect()->back();
        }
        catch(\Exception $e){
            DB::rollBack();
            
            Log::error('Single Enroll Error: ' . $e->getMessage());

            Flasher::addError(__('msg_created_error') . ': ' . $e->getMessage(), __('msg_error'));

            return redirect()->back();
        }
    }
}
