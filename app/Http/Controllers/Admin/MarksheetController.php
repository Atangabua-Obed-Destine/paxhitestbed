<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarksheetSetting;
use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\StudentEnroll;
use App\Models\Program;
use App\Models\Session;
use App\Models\Batch;
use App\Models\Grade;
use App\Models\Setting;

class MarksheetController extends Controller
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
        $this->title = trans_choice('module_marksheet_total', 1);
        $this->route = 'admin.marksheet';
        $this->view = 'admin.marksheet';
        $this->path = 'marksheet-setting';
        $this->access = 'marksheet';


        $this->middleware('permission:'.$this->access.'-view|'.$this->access.'-print|'.$this->access.'-download', ['only' => ['index','show','semester']]);
        $this->middleware('permission:'.$this->access.'-print', ['only' => ['print','semesterPrint','multiPrint']]);
        $this->middleware('permission:'.$this->access.'-download', ['only' => ['download','semesterDownload']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        //
        $data['title']     = $this->title;
        $data['route']     = $this->route;
        $data['path']      = $this->path;
        $data['access']    = $this->access;


        if(!empty($request->batch) || $request->batch != null){
            $data['selected_batch'] = $batch = $request->batch;
        }
        else{
            $data['selected_batch'] = '0';
        }

        if(!empty($request->program) || $request->program != null){
            $data['selected_program'] = $program = $request->program;
        }
        else{
            $data['selected_program'] = '0';
        }

        if(!empty($request->session) || $request->session != null){
            $data['selected_session'] = $session = $request->session;
        }
        else{
            $data['selected_session'] = '0';
        }

        if(!empty($request->student_id) || $request->student_id != null){
            $data['selected_student_id'] = $student_id = $request->student_id;
        }
        else{
            $data['selected_student_id'] = null;
        }


        $data['batchs'] = Batch::where('status', '1')
                        ->orderBy('id', 'desc')->get();
        $data['programs'] = Program::where('status', '1')
                        ->orderBy('title', 'asc')->get();
        $data['sessions'] = Session::where('status', '1')
                        ->orderBy('id', 'desc')->get();
        $data['print'] = MarksheetSetting::where('status', '1')->first();


        // Student Enrollment List (shows each enrollment separately for multi-enrolled students)
        if(isset($request->batch) || isset($request->program) || isset($request->session) || !empty($request->student_id)){

            $enrollments = StudentEnroll::with(['student', 'program', 'session', 'semester', 'section'])
                ->whereIn('status', [1, 2])
                ->whereHas('student', function($query) {
                    $query->where('status', '1');
                });

            if(!empty($request->batch) && $request->batch != '0'){
                $enrollments->whereHas('student', function($query) use ($batch) {
                    $query->where('batch_id', $batch);
                });
            }
            if(!empty($request->program) && $request->program != '0'){
                $enrollments->where('program_id', $program);
            }
            if(!empty($request->session) && $request->session != '0'){
                $enrollments->where('session_id', $session);
            }
            if(!empty($request->student_id)){
                // Search by student_id or matricule
                $enrollments->where(function($query) use ($student_id) {
                    $query->whereHas('student', function($q) use ($student_id) {
                        $q->where('student_id', 'LIKE', '%'.$student_id.'%');
                    })
                    ->orWhere('matricule', 'LIKE', '%'.$student_id.'%');
                });
            }
            
            // Get all matching enrollments, then group by unique matricule
            // For each unique matricule, keep only the latest enrollment (highest id)
            $allEnrollments = $enrollments->orderBy('id', 'desc')->get();
            $uniqueMatricules = $allEnrollments->groupBy('matricule')->map(function($group) {
                // Return the first item (latest enrollment) for each matricule group
                return $group->first();
            })->values();
            
            $data['rows'] = $uniqueMatricules->sortBy('matricule');
        }

        return view($this->view.'.index', $data);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
        $data['title']     = $this->title;
        $data['route']     = $this->route;
        $data['path']      = $this->path;
        $data['access']    = $this->access;

        $student = Student::with([
            'studentEnrolls.session',
            'studentEnrolls.semester', 
            'studentEnrolls.section',
            'studentEnrolls.subjects',
            'studentEnrolls.subjectMarks.subject'
        ])->findOrFail($id);
        
        $data['row'] = $student;
        $data['setting'] = Setting::first() ?? (object)['title' => 'School Name', 'date_format' => 'd-m-Y'];
        $data['grades'] = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();

        // Get selected enrollment from request (for multi-enrollment students)
        $selectedEnrollmentId = request()->get('enrollment_id');
        $selectedProgramId = null;
        if ($selectedEnrollmentId) {
            $selectedEnrollment = $student->studentEnrolls->firstWhere('id', $selectedEnrollmentId);
            $selectedProgramId = $selectedEnrollment ? $selectedEnrollment->program_id : null;
        }
        
        // If no enrollment selected, use the first enrollment's program
        if (!$selectedProgramId && $student->studentEnrolls->isNotEmpty()) {
            $latestEnrollment = $student->studentEnrolls->sortByDesc('id')->first();
            $selectedProgramId = $latestEnrollment->program_id;
        }

        // Prepare GPA trend data for selected program only
        $data['gpa_trend'] = $this->prepareGPATrendData($student, $data['grades'], $selectedProgramId);

        return view($this->view.'.show', $data);
    }

    /**
     * Prepare GPA trend data for Chart.js visualization
     */
    private function prepareGPATrendData($student, $grades, $programId = null)
    {
        $trend_data = [];
        $cumulative_quality_points = 0;
        $cumulative_credits = 0;

        // Filter enrollments by program if specified
        $enrollments = $programId 
            ? $student->studentEnrolls->where('program_id', $programId)
            : $student->studentEnrolls;

        // Get unique semester combinations
        $semester_items = [];
        $semester_keys = [];

        foreach ($enrollments as $enroll) {
            if (isset($enroll->session) && isset($enroll->semester)) {
                $semester_key = $enroll->session->title . '|' . $enroll->semester->title;
                if (!in_array($semester_key, $semester_keys)) {
                    $semester_items[] = [
                        'session' => $enroll->session->title,
                        'semester' => $enroll->semester->title,
                        'key' => $semester_key
                    ];
                    $semester_keys[] = $semester_key;
                }
            }
        }

        // Calculate GPA for each semester
        foreach ($semester_items as $semester_item) {
            $semester_credits = 0;
            $semester_quality_points = 0;

            foreach ($enrollments as $enroll) {
                if (isset($enroll->semester) && isset($enroll->session) &&
                    $semester_item['semester'] == $enroll->semester->title &&
                    $semester_item['session'] == $enroll->session->title) {

                    if (isset($enroll->subjectMarks)) {
                        foreach ($enroll->subjectMarks as $mark) {
                            // Check if marks are published and visible
                            $publishDate = $mark->publish_date instanceof \Carbon\Carbon ? 
                                          $mark->publish_date->format('Y-m-d') : 
                                          date('Y-m-d', strtotime($mark->publish_date));
                            $publishTime = $mark->publish_time instanceof \Carbon\Carbon ? 
                                          $mark->publish_time->format('H:i:s') : 
                                          date('H:i:s', strtotime($mark->publish_time));
                            $currentDate = date('Y-m-d');
                            $currentTime = date('H:i:s');

                            $isVisible = $mark->is_visible_to_student && 
                                        (($publishDate == $currentDate && $publishTime <= $currentTime) || 
                                         $publishDate < $currentDate);

                            if ($isVisible && isset($mark->subject)) {
                                $marks_per = round($mark->total_marks);
                                $credit_hour = (float) $mark->subject->credit_hour;

                                foreach ($grades as $grade) {
                                    if ($marks_per >= $grade->min_mark && $marks_per <= $grade->max_mark) {
                                        $grade_point = (float) $grade->point;
                                        $quality_points = $grade_point * $credit_hour;

                                        $semester_credits += $credit_hour;
                                        $semester_quality_points += $quality_points;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Calculate semester GPA
            $semester_gpa = $semester_credits > 0 ? $semester_quality_points / $semester_credits : 0;

            // Update cumulative values
            $cumulative_credits += $semester_credits;
            $cumulative_quality_points += $semester_quality_points;
            $cumulative_gpa = $cumulative_credits > 0 ? $cumulative_quality_points / $cumulative_credits : 0;

            // Store data point (only if semester has credits to avoid empty semesters in chart)
            if ($semester_credits > 0) {
                $trend_data[] = [
                    'label' => $semester_item['session'] . ' - ' . $semester_item['semester'],
                    'semester_gpa' => round($semester_gpa, 2),
                    'cumulative_gpa' => round($cumulative_gpa, 2),
                    'credits' => round($semester_credits, 2),
                    'cumulative_credits' => round($cumulative_credits, 2)
                ];
            }
        }

        return $trend_data;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function print(Request $request, $id)
    {
        //
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;

        // View
        $data['setting'] = Setting::first() ?? (object)['title' => 'School Name', 'date_format' => 'd-m-Y'];
        $data['grades'] = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();
        $data['marksheet'] = MarksheetSetting::where('status', '1')->firstOrFail();
        $data['row'] = Student::with([
            'studentEnrolls' => function($query) {
                $query->whereNotNull('session_id')
                      ->whereNotNull('semester_id')
                      ->whereNotNull('section_id');
            },
            'studentEnrolls.session',
            'studentEnrolls.semester', 
            'studentEnrolls.section',
            'studentEnrolls.subjects',
            'studentEnrolls.subjectMarks.subject'
        ])->findOrFail($id);

        // Get enrollment selection from query parameter
        $enrollmentId = $request->get('enrollment_id');
        if ($enrollmentId) {
            $data['currentEnroll'] = StudentEnroll::with('program')->find($enrollmentId);
            $data['selectedProgramId'] = $data['currentEnroll'] ? $data['currentEnroll']->program_id : $data['row']->program_id;
        } else {
            // Fallback to latest enrollment
            $data['selectedProgramId'] = $data['row']->program_id;
        }

        return view($this->view.'.print', $data);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function download(Request $request, $id)
    {
        //
        $data['title'] = $this->title;
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;

        // View
        $data['setting'] = Setting::first() ?? (object)['title' => 'School Name', 'date_format' => 'd-m-Y'];
        $data['grades'] = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();
        $data['marksheet'] = MarksheetSetting::where('status', '1')->firstOrFail();
        $data['row'] = Student::with([
            'studentEnrolls' => function($query) {
                $query->whereNotNull('session_id')
                      ->whereNotNull('semester_id')
                      ->whereNotNull('section_id');
            },
            'studentEnrolls.session',
            'studentEnrolls.semester', 
            'studentEnrolls.section',
            'studentEnrolls.subjects',
            'studentEnrolls.subjectMarks.subject'
        ])->findOrFail($id);

        // Get enrollment selection from query parameter
        $enrollmentId = $request->get('enrollment_id');
        if ($enrollmentId) {
            $data['currentEnroll'] = StudentEnroll::with('program')->find($enrollmentId);
            $data['selectedProgramId'] = $data['currentEnroll'] ? $data['currentEnroll']->program_id : $data['row']->program_id;
        } else {
            // Fallback to latest enrollment
            $data['selectedProgramId'] = $data['row']->program_id;
        }

        return view($this->view.'.download', $data);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function semester(Request $request)
    {
        //
        $data['title']     = trans_choice('module_marksheet_semester', 1);
        $data['route']     = $this->route;
        $data['path']      = $this->path;
        $data['access']    = $this->access;


        if(!empty($request->program) || $request->program != null){
            $data['selected_program'] = $program = $request->program;
        }
        else{
            $data['selected_program'] = '0';
        }

        if(!empty($request->session) || $request->session != null){
            $data['selected_session'] = $session = $request->session;
        }
        else{
            $data['selected_session'] = '0';
        }

        if(!empty($request->student_id) || $request->student_id != null){
            $data['selected_student_id'] = $student_id = $request->student_id;
        }
        else{
            $data['selected_student_id'] = null;
        }


        // Search Filter
        $data['programs'] = Program::where('status', '1')
                        ->orderBy('title', 'asc')->get();
        $data['print'] = MarksheetSetting::where('status', '1')->first();


        if(!empty($request->program) && $request->program != '0'){
        $sessions = Session::where('status', 1);
        $sessions->with('programs')->whereHas('programs', function ($query) use ($program){
            $query->where('program_id', $program);
        });
        $data['sessions'] = $sessions->orderBy('id', 'desc')->get();}


        // Student Enrollment List (shows each enrollment separately for multi-enrolled students)
        if(isset($request->program) || isset($request->session) || !empty($request->student_id)){

            $enrollments = StudentEnroll::with(['student', 'program', 'session', 'semester', 'section'])
                ->whereIn('status', [1, 2])
                ->whereHas('student', function($query) {
                    $query->where('status', '1');
                });

            if(!empty($request->program) && $request->program != '0'){
                $enrollments->where('program_id', $program);
            }
            if(!empty($request->session) && $request->session != '0'){
                $enrollments->where('session_id', $session);
            }
            if(!empty($request->student_id)){
                // Search by student_id or matricule
                $enrollments->where(function($query) use ($student_id) {
                    $query->whereHas('student', function($q) use ($student_id) {
                        $q->where('student_id', 'LIKE', '%'.$student_id.'%');
                    })
                    ->orWhere('matricule', 'LIKE', '%'.$student_id.'%');
                });
            }
            
            // Get all matching enrollments, then group by unique matricule
            // For each unique matricule, keep only the latest enrollment (highest id)
            $allEnrollments = $enrollments->orderBy('id', 'desc')->get();
            $uniqueMatricules = $allEnrollments->groupBy('matricule')->map(function($group) {
                // Return the first item (latest enrollment) for each matricule group
                return $group->first();
            })->values();
            
            $data['rows'] = $uniqueMatricules->sortBy('matricule');
        }

        return view($this->view.'.semester', $data);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function semesterPrint(Request $request, $id, $session)
    {
        //
        $data['title'] = trans_choice('module_marksheet_semester', 1);
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;

        // View
        $data['setting'] = Setting::first() ?? (object)['title' => 'School Name', 'date_format' => 'd-m-Y'];
        $data['grades'] = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();
        $data['marksheet'] = MarksheetSetting::where('status', '1')->firstOrFail();
        $data['row'] = Student::findOrFail($id);
        $data['session'] = $session;

        // Get enrollment selection from query parameter
        $enrollmentId = $request->get('enrollment_id');
        if ($enrollmentId) {
            $data['currentEnroll'] = StudentEnroll::with('program')->find($enrollmentId);
            $data['selectedProgramId'] = $data['currentEnroll'] ? $data['currentEnroll']->program_id : $data['row']->program_id;
        } else {
            // Fallback to latest enrollment
            $data['selectedProgramId'] = $data['row']->program_id;
        }

        return view($this->view.'.session-print', $data);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function semesterDownload(Request $request, $id, $session)
    {
        //
        $data['title'] = trans_choice('module_marksheet_semester', 1);
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;

        // View
        $data['setting'] = Setting::first() ?? (object)['title' => 'School Name', 'date_format' => 'd-m-Y'];
        $data['grades'] = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();
        $data['marksheet'] = MarksheetSetting::where('status', '1')->firstOrFail();
        $data['row'] = Student::findOrFail($id);
        $data['session'] = $session;

        // Get enrollment selection from query parameter
        $enrollmentId = $request->get('enrollment_id');
        if ($enrollmentId) {
            $data['currentEnroll'] = StudentEnroll::with('program')->find($enrollmentId);
            $data['selectedProgramId'] = $data['currentEnroll'] ? $data['currentEnroll']->program_id : $data['row']->program_id;
        } else {
            // Fallback to latest enrollment
            $data['selectedProgramId'] = $data['row']->program_id;
        }

        return view($this->view.'.session-download', $data);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function multiPrint(Request $request)
    {
        //
        $data['title'] = trans_choice('module_marksheet_semester', 1);
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;

        $students = explode(",",$request->students);

        // View
        $data['setting'] = Setting::first() ?? (object)['title' => 'School Name', 'date_format' => 'd-m-Y'];
        $data['grades'] = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();
        $data['marksheet'] = MarksheetSetting::where('status', '1')->firstOrFail();
        $data['rows'] = Student::whereIn('id', $students)->orderBy('id', 'asc')->get();
        $data['session'] = $request->session;

        return view($this->view.'.multi-print', $data);
    }
}
