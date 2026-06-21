<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Flasher\Laravel\Facade\Flasher;
use App\Imports\AttendancesImport;
use App\Exports\AttendanceTemplateExport;
use App\Models\StudentAttendance;
use App\Models\StudentEnroll;
use Illuminate\Http\Request;
use App\Models\Semester;
use App\Models\Faculty;
use App\Services\StaffAssignmentService;
use App\Models\Program;
use App\Models\Session;
use App\Models\Section;
use App\Models\Subject;
use Carbon\Carbon;
use App\User;

class StudentAttendanceController extends Controller
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
        $this->title = trans_choice('module_student_subject_attendance', 1);
        $this->route = 'admin.student-attendance';
        $this->view = 'admin.student-attendance';
        $this->path = 'student-attendance';
        $this->access = 'student-attendance';


        $this->middleware('permission:'.$this->access.'-action', ['only' => ['index','store','scanner','scan','bulkMigration','bulkMigrationStore']]);
        $this->middleware('permission:'.$this->access.'-report', ['only' => ['report']]);
        $this->middleware('permission:'.$this->access.'-import', ['only' => ['index','import','importStore']]);
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

        $data['programs'] = collect();
        $data['sessions'] = collect();
        $data['semesters'] = collect();
        $data['sections'] = collect();
        $data['subjects'] = collect();
        $data['semesterOptions'] = [];
        $data['selected_semester_year'] = $selected_semester_year = $request->semester_year ?? '0';


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

        if(!empty($request->subject) || $request->subject != null){
            $data['selected_subject'] = $subject = $request->subject;
        }
        else{
            $data['selected_subject'] = $subject = '0';
        }

        if(!empty($request->date) || $request->date != null){
            $data['selected_date'] = $date = $request->date;
        }
        else{
            $data['selected_date'] = $date = date("Y-m-d", strtotime(Carbon::today()));
        }


        // Search Filter
        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();

        if($faculty !== '0'){
            $data['programs'] = Program::where('faculty_id', $faculty)->where('status', '1')->orderBy('title', 'asc')->get();
        }

        if($program !== '0'){
            $sessions = Session::where('status', 1);
            $sessions->with('programs')->whereHas('programs', function ($query) use ($program){
                $query->where('program_id', $program);
            });
            $data['sessions'] = $sessions->orderBy('id', 'desc')->get();

            $semesters = Semester::where('status', 1);
            $semesters->with('programs')->whereHas('programs', function ($query) use ($program){
                $query->where('program_id', $program);
            });
            $semesterCollection = $semesters->orderBy('year', 'asc')->orderBy('id', 'asc')->get();
            $data['semesters'] = $semesterCollection;
            $data['semesterOptions'] = $semesterCollection
                ->filter(function ($row) {
                    return !is_null($row->year);
                })
                ->groupBy('year')
                ->sortKeys()
                ->map(function ($items) {
                    return $items->map(function ($semesterItem) {
                        return [
                            'id' => $semesterItem->id,
                            'title' => $semesterItem->title,
                        ];
                    })->values();
                })
                ->toArray();
        }

        if($program !== '0' && $semester !== '0'){
            $sections = Section::where('status', 1);
            $sections->with('semesterPrograms')->whereHas('semesterPrograms', function ($query) use ($program, $semester){
                $query->where('program_id', $program);
                $query->where('semester_id', $semester);
            });
            $data['sections'] = $sections->orderBy('title', 'asc')->get();
        }

        $teacher_id = null;
        $superAdmin = false;
        $hasAssignments = false;

        if($program !== '0' && $session !== '0'){
            // Access Data
            $authUser = Auth::guard('web')->user();
            $teacher_id = $authUser->id;
            $superAdmin = $authUser->hasRole('Super Admin');
            
            // Check if user has staff assignments
            $hasAssignments = \App\Models\StaffAssignment::where('user_id', $teacher_id)->exists();

            // Filter Subject
            $subjects = Subject::where('status', '1');
            $subjects->with('classes')->whereHas('classes', function ($query) use ($teacher_id, $session, $superAdmin, $hasAssignments){
                if(isset($session)){
                    $query->where('session_id', $session);
                }
                // Only filter by teacher_id if user is NOT super admin AND has NO staff assignments
                if(!$superAdmin && !$hasAssignments){
                    $query->where('teacher_id', $teacher_id);
                }
            });
            $subjects->with('programs')->whereHas('programs', function ($query) use ($program){
                $query->where('program_id', $program);
            });
            
            // Apply staff assignment filter
            $subjects = StaffAssignmentService::filterCourses($subjects);
            
            $subjectsCollection = $subjects->orderBy('code', 'asc')->get();
            $data['subjects'] = $subjectsCollection;
        }


        // Student List
        if($program !== '0' && $session !== '0' && $subject !== '0'){

            // Check Subject Access
            $subject_check = Subject::where('id', $subject);
            $subject_check->with('classes')->whereHas('classes', function ($query) use ($teacher_id, $session, $superAdmin, $hasAssignments){
                if(isset($session)){
                    $query->where('session_id', $session);
                }
                // Only filter by teacher_id if user is NOT super admin AND has NO staff assignments
                if(!$superAdmin && !$hasAssignments){
                    $query->where('teacher_id', $teacher_id);
                }
            })->firstOrFail();


            // Enrolls
            $enrolls = StudentEnroll::where('status', '1');
            if(!empty($request->program) && $request->program != '0'){
                $enrolls->where('program_id', $program);
            }
            if(!empty($request->session) && $request->session != '0'){
                $enrolls->where('session_id', $session);
            }
            if(!empty($request->semester) && $request->semester != '0'){
                $enrolls->where('semester_id', $semester);
            }
            if(!empty($request->section) && $request->section != '0'){
                $enrolls->where('section_id', $section);
            }
            $enrolls->with('subjects')->whereHas('subjects', function ($query) use ($subject){
                $query->where('subject_id', $subject);
            });
            $enrolls->with('student')->whereHas('student', function ($query){
                $query->where('status', '1');
            })->orderBy('matricule', 'asc');

            $rows = $enrolls->get();

            // Group by unique matricule - keep only latest enrollment per matricule
            $uniqueMatricules = $rows->groupBy('matricule')->map(function($group) {
                return $group->sortByDesc('id')->first();
            })->values();

            // Array Sorting
            $data['rows'] = $uniqueMatricules->sortBy(function($query){

               return $query->matricule;

            })->all();
        }


        // Attendances
        if(!empty($request->date) && $subject !== '0'){
            $attendances = StudentAttendance::where('subject_id', $request->subject)->where('date', $date);

            if(!empty($request->program) && !empty($request->session)){
                $attendances->with('studentEnroll')->whereHas('studentEnroll', function ($query) use ($program, $session, $semester, $section){
                    if($program != '0'){
                        $query->where('program_id', $program);
                    }
                    if($session != '0'){
                        $query->where('session_id', $session);
                    }
                    if($semester != '0'){
                        $query->where('semester_id', $semester);
                    }
                    if($section != '0'){
                        $query->where('section_id', $section);
                    }
                });
            }

            $data['attendances'] = $attendances->orderBy('id', 'asc')->get();
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
            'students' => 'required',
            'subject' => 'required',
            'date' => 'required|date|before_or_equal:today',
            'attendances' => 'required',
        ]);

        // Block submission if mark distribution is not configured for this course
        if (!\App\Services\ResultContributionService::isConfigured($request->subject)) {
            Flasher::addError('Mark distribution has not been configured for this course. Please configure it before recording student attendance.', 'Error');
            return redirect()->back();
        }

        $attendances = explode(",",$request->attendances);

        // Insert Data
        foreach($request->students as $key => $student){

            // Insert Or Update Data
            $studentAttendance = StudentAttendance::updateOrCreate(
            [
                'student_enroll_id' => $student,
                'subject_id' => $request->subject,
                'date' => $request->date
            ],[
                'student_enroll_id' => $student,
                'subject_id' => $request->subject,
                'date' => $request->date,
                'attendance' => $attendances[$key],
                'note' => $request->notes[$key],
                'created_by' => Auth::guard('web')->user()->id
            ]);
        }


        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function report(Request $request)
    {
        //
        $data['title'] = trans_choice('module_student_subject_report', 1);
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;

        $data['programs'] = collect();
        $data['sessions'] = collect();
        $data['semesters'] = collect();
        $data['sections'] = collect();
        $data['subjects'] = collect();
        $data['semesterOptions'] = [];


        if(!empty($request->faculty) || $request->faculty != null){
            $data['selected_faculty'] = $faculty = $request->faculty;
        }
        else{
            $data['selected_faculty'] = '0';
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

        if(!empty($request->semester) || $request->semester != null){
            $data['selected_semester'] = $semester = $request->semester;
        }
        else{
            $data['selected_semester'] = '0';
        }

        if(!empty($request->section) || $request->section != null){
            $data['selected_section'] = $section = $request->section;
        }
        else{
            $data['selected_section'] = '0';
        }

        $data['selected_semester_year'] = $selected_semester_year = $request->semester_year ?? '0';

        if(!empty($request->subject) || $request->subject != null){
            $data['selected_subject'] = $subject = $request->subject;
        }
        else{
            $data['selected_subject'] = '0';
        }

        if(!empty($request->month) || $request->month != null){
            $data['selected_month'] = $month = $request->month;
        }
        else{
            $data['selected_month'] = date("m", strtotime(Carbon::today()));
        }

        if(!empty($request->year) || $request->year != null){
            $data['selected_year'] = $year = $request->year;
        }
        else{
            $data['selected_year'] = date("Y", strtotime(Carbon::today()));
        }


        // Search Filter
        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();

        if(!empty($request->faculty) && $request->faculty != '0'){
        $data['programs'] = Program::where('faculty_id', $faculty)->where('status', '1')->orderBy('title', 'asc')->get();}

        if(!empty($request->program) && $request->program != '0'){
        $sessions = Session::where('status', 1);
        $sessions->with('programs')->whereHas('programs', function ($query) use ($program){
            $query->where('program_id', $program);
        });
        $data['sessions'] = $sessions->orderBy('id', 'desc')->get();

        $semesters = Semester::where('status', 1);
        $semesters->with('programs')->whereHas('programs', function ($query) use ($program){
            $query->where('program_id', $program);
        });
        $semesterCollection = $semesters->orderBy('year', 'asc')->orderBy('id', 'asc')->get();
        $data['semesters'] = $semesterCollection;
        $data['semesterOptions'] = $semesterCollection
            ->filter(function ($row) {
                return !is_null($row->year);
            })
            ->groupBy('year')
            ->sortKeys()
            ->map(function ($items) {
                return $items->map(function ($semesterItem) {
                    return [
                        'id' => $semesterItem->id,
                        'title' => $semesterItem->title,
                    ];
                })->values();
            })
            ->toArray();
        }

        if(!empty($request->program) && $request->program != '0' && !empty($request->semester) && $request->semester != '0'){
        $sections = Section::where('status', 1);
        $sections->with('semesterPrograms')->whereHas('semesterPrograms', function ($query) use ($program, $semester){
            $query->where('program_id', $program);
            $query->where('semester_id', $semester);
        });
        $data['sections'] = $sections->orderBy('title', 'asc')->get();}

        if(!empty($request->program) && $request->program != '0' && !empty($request->session) && $request->session != '0'){
            // Access Data
            $authUser = Auth::guard('web')->user();
            $teacher_id = $authUser->id;
            $superAdmin = $authUser->hasRole('Super Admin');
            
            // Check if user has staff assignments
            $hasAssignments = \App\Models\StaffAssignment::where('user_id', $teacher_id)->exists();

            // Filter Subject
            $subjects = Subject::where('status', '1');
            $subjects->with('classes')->whereHas('classes', function ($query) use ($teacher_id, $session, $superAdmin, $hasAssignments){
                if(isset($session)){
                    $query->where('session_id', $session);
                }
                // Only filter by teacher_id if user is NOT super admin AND has NO staff assignments
                if(!$superAdmin && !$hasAssignments){
                    $query->where('teacher_id', $teacher_id);
                }
            });
            $subjects->with('programs')->whereHas('programs', function ($query) use ($program){
                $query->where('program_id', $program);
            });
            
            // Apply staff assignment filter
            $subjects = StaffAssignmentService::filterCourses($subjects);
            
            $data['subjects'] = $subjects->orderBy('code', 'asc')->get();
        }


        // Student List
        if(!empty($request->program) && !empty($request->session) && !empty($request->subject)){

            // Check Subject Access
            $subject_check = Subject::where('id', $subject);
            $subject_check->with('classes')->whereHas('classes', function ($query) use ($teacher_id, $session, $superAdmin, $hasAssignments){
                if(isset($session)){
                    $query->where('session_id', $session);
                }
                // Only filter by teacher_id if user is NOT super admin AND has NO staff assignments
                if(!$superAdmin && !$hasAssignments){
                    $query->where('teacher_id', $teacher_id);
                }
            })->firstOrFail();


            // Enrolls
            $enrolls = StudentEnroll::where('id', '!=', '0');
            if(!empty($request->program) && $request->program != '0'){
                $enrolls->where('program_id', $program);
            }
            if(!empty($request->session) && $request->session != '0'){
                $enrolls->where('session_id', $session);
            }
            if(!empty($request->semester) && $request->semester != '0'){
                $enrolls->where('semester_id', $semester);
            }
            if(!empty($request->section) && $request->section != '0'){
                $enrolls->where('section_id', $section);
            }
            $enrolls->with('subjects')->whereHas('subjects', function ($query) use ($subject){
                $query->where('subject_id', $subject);
            });
            $enrolls->with('student')->whereHas('student', function ($query){
                // $query->orderBy('student_id', 'asc');
            })->orderBy('matricule', 'asc');

            $rows = $enrolls->get();

            // Group by unique matricule - keep only latest enrollment per matricule
            $uniqueMatricules = $rows->groupBy('matricule')->map(function($group) {
                return $group->sortByDesc('id')->first();
            })->values();

            // Array Sorting
            $data['rows'] = $uniqueMatricules->sortBy(function($query){

               return $query->matricule;

            })->all();
        }


        // Attendances
        if(!empty($request->month) && !empty($request->year) && !empty($request->subject)){
            $attendances = StudentAttendance::where('subject_id', $request->subject)->whereYear('date', $year)->whereMonth('date', $month);

            if(!empty($request->program) && !empty($request->session)){
                $attendances->with('studentEnroll')->whereHas('studentEnroll', function ($query) use ($program, $session, $semester, $section){
                    if($program != '0'){
                        $query->where('program_id', $program);
                    }
                    if($session != '0'){
                        $query->where('session_id', $session);
                    }
                    if($semester != '0'){
                        $query->where('semester_id', $semester);
                    }
                    if($section != '0'){
                        $query->where('section_id', $section);
                    }
                });
            }

            $data['attendances'] = $attendances->orderBy('id', 'asc')->get();
        }


        return view($this->view.'.report', $data);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function import(Request $request)
    {
        //
        $data['title']     = $this->title;
        $data['route']     = $this->route;
        $data['view']      = $this->view;
        $data['access']    = $this->access;

        //
        $data['sessions'] = Session::where('status', '1')
                        ->orderBy('id', 'desc')->get();

        return view($this->view.'.import', $data);
    }

    /**
     * Download Class List for Attendance Import
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function downloadClassList(Request $request)
    {
        // Field Validation
        $request->validate([
            'session' => 'required',
            'subject' => 'required',
        ]);

        $session = $request->session;
        $subject = $request->subject;
        $program = $request->program ?? '0';
        $semester = $request->semester ?? '0';
        $section = $request->section ?? '0';

        // Enrolls
        $enrolls = StudentEnroll::where('status', '1');
        if(!empty($program) && $program != '0'){
            $enrolls->where('program_id', $program);
        }
        if(!empty($session) && $session != '0'){
            $enrolls->where('session_id', $session);
        }
        if(!empty($semester) && $semester != '0'){
            $enrolls->where('semester_id', $semester);
        }
        if(!empty($section) && $section != '0'){
            $enrolls->where('section_id', $section);
        }
        $enrolls->with('subjects')->whereHas('subjects', function ($query) use ($subject){
            $query->where('subject_id', $subject);
        });
        $enrolls->with('student')->whereHas('student', function ($query){
            $query->where('status', '1');
        })->orderBy('matricule', 'asc');

        $rows = $enrolls->get();

        // Group by unique matricule - keep only latest enrollment per matricule
        $uniqueMatricules = $rows->groupBy('matricule')->map(function($group) {
            return $group->sortByDesc('id')->first();
        })->values();

        $students = $uniqueMatricules->sortBy(function($query){
           return $query->matricule;
        });

        if($students->count() == 0){
            Flasher::addError(__('msg_no_data_found'), __('msg_error'));
            return redirect()->back();
        }

        return Excel::download(new AttendanceTemplateExport($students, $request->date), 'attendance-template.xlsx');
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function importStore(Request $request)
    {
        // Field Validation
        $request->validate([
            'session' => 'required',
            'subject' => 'required',
            'import' => 'required|file|mimes:xlsx',
        ]);


        // Passing Data
        $data['session'] = $request->session;
        $data['subject'] = $request->subject;

        Excel::import(new AttendancesImport($data), $request->file('import'));


        Flasher::addSuccess(__('msg_updated_successfully'), __('msg_success'));

        return redirect()->back();
    }

    /**
     * Display the scanner/kiosk interface.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function scanner(Request $request)
    {
        $data['title'] = 'Student Attendance Scanner';
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;

        // Get filter parameters from request
        $data['selected_faculty'] = $faculty = $request->faculty ?? '0';
        $data['selected_program'] = $program = $request->program ?? '0';
        $data['selected_session'] = $session = $request->session ?? '0';
        $data['selected_semester'] = $semester = $request->semester ?? '0';
        $data['selected_section'] = $section = $request->section ?? '0';
        $data['selected_subject'] = $subject = $request->subject ?? '0';
        $data['selected_date'] = $date = $request->date ?? date("Y-m-d");

        // Get subject details
        $data['subject_info'] = null;
        if ($subject !== '0') {
            $data['subject_info'] = Subject::find($subject);
        }

        // Statistics
        $data['today_date'] = date("l, d F Y", strtotime($date));
        
        // Total students enrolled in this subject
        $data['total_students'] = 0;
        $data['present_count'] = 0;
        $data['absent_count'] = 0;

        if ($program !== '0' && $session !== '0' && $subject !== '0') {
            // Count enrolled students
            $enrolls = StudentEnroll::where('status', '1');
            if ($program !== '0') {
                $enrolls->where('program_id', $program);
            }
            if ($session !== '0') {
                $enrolls->where('session_id', $session);
            }
            if ($semester !== '0') {
                $enrolls->where('semester_id', $semester);
            }
            if ($section !== '0') {
                $enrolls->where('section_id', $section);
            }
            $enrolls->with('subjects')->whereHas('subjects', function ($query) use ($subject) {
                $query->where('subject_id', $subject);
            });
            $enrolls->with('student')->whereHas('student', function ($query) {
                $query->where('status', '1');
            });

            $enrolledStudents = $enrolls->get();
            $uniqueMatricules = $enrolledStudents->groupBy('matricule')->count();
            $data['total_students'] = $uniqueMatricules;

            // Count attendance for today
            $attendances = StudentAttendance::where('subject_id', $subject)
                ->where('date', $date)
                ->with('studentEnroll')
                ->whereHas('studentEnroll', function ($query) use ($program, $session, $semester, $section) {
                    if ($program !== '0') {
                        $query->where('program_id', $program);
                    }
                    if ($session !== '0') {
                        $query->where('session_id', $session);
                    }
                    if ($semester !== '0') {
                        $query->where('semester_id', $semester);
                    }
                    if ($section !== '0') {
                        $query->where('section_id', $section);
                    }
                })
                ->get();

            $data['present_count'] = $attendances->where('attendance', 1)->count();
            $data['absent_count'] = $attendances->where('attendance', 2)->count();
        }

        return view($this->view.'.scanner', $data);
    }

    /**
     * Process the scanned QR code for student attendance.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function scan(Request $request)
    {
        $request->validate([
            'student_id' => 'required',
            'subject_id' => 'required',
            'date' => 'required|date',
        ]);

        $studentIdentifier = $request->student_id;
        $subjectId = $request->subject_id;
        $date = $request->date;
        $program = $request->program ?? '0';
        $session = $request->session ?? '0';
        $semester = $request->semester ?? '0';
        $section = $request->section ?? '0';

        // Find student enrollment by matricule or student_id
        $enroll = StudentEnroll::where('status', '1')
            ->where(function ($query) use ($studentIdentifier) {
                $query->where('matricule', $studentIdentifier)
                    ->orWhereHas('student', function ($q) use ($studentIdentifier) {
                        $q->where('student_id', $studentIdentifier);
                    });
            })
            ->with('subjects')
            ->whereHas('subjects', function ($query) use ($subjectId) {
                $query->where('subject_id', $subjectId);
            })
            ->with('student')
            ->whereHas('student', function ($query) {
                $query->where('status', '1');
            });

        // Apply filters if provided
        if ($program !== '0') {
            $enroll->where('program_id', $program);
        }
        if ($session !== '0') {
            $enroll->where('session_id', $session);
        }
        if ($semester !== '0') {
            $enroll->where('semester_id', $semester);
        }
        if ($section !== '0') {
            $enroll->where('section_id', $section);
        }

        $enrollment = $enroll->first();

        if (!$enrollment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Student not found or not enrolled in this course.'
            ], 404);
        }

        // Check for existing attendance
        $attendance = StudentAttendance::where('student_enroll_id', $enrollment->id)
            ->where('subject_id', $subjectId)
            ->where('date', $date)
            ->first();

        if ($attendance) {
            // Already marked
            $status_text = '';
            switch ($attendance->attendance) {
                case 1:
                    $status_text = 'Present';
                    break;
                case 2:
                    $status_text = 'Absent';
                    break;
                case 3:
                    $status_text = 'Leave';
                    break;
                case 4:
                    $status_text = 'Holiday';
                    break;
            }

            return response()->json([
                'status' => 'warning',
                'message' => 'Already marked as ' . $status_text
            ]);
        }

        // Create attendance record - Mark as Present
        StudentAttendance::create([
            'student_enroll_id' => $enrollment->id,
            'subject_id' => $subjectId,
            'date' => $date,
            'time' => date("H:i:s"),
            'attendance' => 1, // Present
            'created_by' => Auth::guard('web')->user()->id ?? 1,
        ]);

        // Get updated stats
        $attendances = StudentAttendance::where('subject_id', $subjectId)
            ->where('date', $date)
            ->with('studentEnroll')
            ->whereHas('studentEnroll', function ($query) use ($program, $session, $semester, $section) {
                if ($program !== '0') {
                    $query->where('program_id', $program);
                }
                if ($session !== '0') {
                    $query->where('session_id', $session);
                }
                if ($semester !== '0') {
                    $query->where('semester_id', $semester);
                }
                if ($section !== '0') {
                    $query->where('section_id', $section);
                }
            })
            ->get();

        $present_count = $attendances->where('attendance', 1)->count();
        $absent_count = $attendances->where('attendance', 2)->count();

        return response()->json([
            'status' => 'success',
            'message' => 'Marked Present Successfully',
            'student' => $enrollment->student->first_name . ' ' . $enrollment->student->last_name,
            'matricule' => $enrollment->matricule ?? $enrollment->student->student_id,
            'time' => date("h:i A"),
            'stats' => [
                'present' => $present_count,
                'absent' => $absent_count
            ]
        ]);
    }

    /**
     * Display the bulk migration interface for entering multiple dates at once.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function bulkMigration(Request $request)
    {
        $data['title'] = 'Bulk Attendance Migration';
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['path'] = $this->path;
        $data['access'] = $this->access;

        $data['programs'] = collect();
        $data['sessions'] = collect();
        $data['semesters'] = collect();
        $data['sections'] = collect();
        $data['subjects'] = collect();
        $data['semesterOptions'] = [];

        // Include All Programs toggle (check first to determine which session field to use)
        $data['include_all_programs'] = $includeAllPrograms = $request->has('include_all_programs') ? '1' : '0';
        
        // Get filter parameters
        $data['selected_faculty'] = $faculty = $request->faculty ?? '0';
        $data['selected_program'] = $program = $request->program ?? '0';
        // Use session_all when Include All Programs is enabled, otherwise use session from standard filters
        $data['selected_session'] = $session = ($includeAllPrograms === '1') 
            ? ($request->session_all ?? '0') 
            : ($request->session ?? '0');
        $data['selected_semester'] = $semester = $request->semester ?? '0';
        $data['selected_section'] = $section = $request->section ?? '0';
        $data['selected_subject'] = $subject = $request->subject ?? '0';
        $data['selected_semester_year'] = $selected_semester_year = $request->semester_year ?? '0';
        $data['start_date'] = $start_date = $request->start_date ?? '';
        $data['end_date'] = $end_date = $request->end_date ?? '';

        // Search Filter - Faculties
        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();

        // Always load all sessions for the "Include All Programs" dropdown
        $data['all_sessions'] = Session::where('status', 1)->orderBy('id', 'desc')->get();
        
        // When Include All Programs is ON, use all sessions as main sessions too
        if($includeAllPrograms === '1') {
            $data['sessions'] = $data['all_sessions'];
        }

        if($faculty !== '0'){
            $data['programs'] = Program::where('faculty_id', $faculty)->where('status', '1')->orderBy('title', 'asc')->get();
        }

        if($program !== '0' && $includeAllPrograms !== '1'){
            $sessions = Session::where('status', 1);
            $sessions->with('programs')->whereHas('programs', function ($query) use ($program){
                $query->where('program_id', $program);
            });
            $data['sessions'] = $sessions->orderBy('id', 'desc')->get();

            $semesters = Semester::where('status', 1);
            $semesters->with('programs')->whereHas('programs', function ($query) use ($program){
                $query->where('program_id', $program);
            });
            $semesterCollection = $semesters->orderBy('year', 'asc')->orderBy('id', 'asc')->get();
            $data['semesters'] = $semesterCollection;
            $data['semesterOptions'] = $semesterCollection
                ->filter(function ($row) {
                    return !is_null($row->year);
                })
                ->groupBy('year')
                ->sortKeys()
                ->map(function ($items) {
                    return $items->map(function ($semesterItem) {
                        return [
                            'id' => $semesterItem->id,
                            'title' => $semesterItem->title,
                        ];
                    })->values();
                })
                ->toArray();
        }

        if($program !== '0' && $semester !== '0' && $includeAllPrograms !== '1'){
            $sections = Section::where('status', 1);
            $sections->with('semesterPrograms')->whereHas('semesterPrograms', function ($query) use ($program, $semester){
                $query->where('program_id', $program);
                $query->where('semester_id', $semester);
            });
            $data['sections'] = $sections->orderBy('title', 'asc')->get();
        }

        // Get current user info for access control
        $authUser = Auth::guard('web')->user();
        $teacher_id = $authUser->id;
        $superAdmin = $authUser->hasRole('Super Admin');
        $hasAssignments = \App\Models\StaffAssignment::where('user_id', $teacher_id)->exists();

        // Load subjects based on mode
        if($includeAllPrograms === '1' && $session !== '0') {
            // Include All Programs mode - show all subjects for this session
            $subjects = Subject::where('status', '1');
            $subjects->with('classes')->whereHas('classes', function ($query) use ($teacher_id, $session, $superAdmin, $hasAssignments){
                $query->where('session_id', $session);
                if(!$superAdmin && !$hasAssignments){
                    $query->where('teacher_id', $teacher_id);
                }
            });
            
            $subjects = StaffAssignmentService::filterCourses($subjects);
            $data['subjects'] = $subjects->orderBy('code', 'asc')->get();
            
        } elseif($program !== '0' && $session !== '0'){
            // Standard mode - filter by program
            $subjects = Subject::where('status', '1');
            $subjects->with('classes')->whereHas('classes', function ($query) use ($teacher_id, $session, $superAdmin, $hasAssignments){
                $query->where('session_id', $session);
                if(!$superAdmin && !$hasAssignments){
                    $query->where('teacher_id', $teacher_id);
                }
            });
            $subjects->with('programs')->whereHas('programs', function ($query) use ($program){
                $query->where('program_id', $program);
            });
            
            $subjects = StaffAssignmentService::filterCourses($subjects);
            $data['subjects'] = $subjects->orderBy('code', 'asc')->get();
        }

        // Generate grid data when all required filters are selected
        $data['rows'] = [];
        $data['dates'] = [];
        $data['existingAttendances'] = [];

        // Condition for loading grid - either Include All + session + subject OR standard filters
        $canLoadGrid = ($includeAllPrograms === '1' && $session !== '0' && $subject !== '0' && !empty($start_date) && !empty($end_date))
                    || ($program !== '0' && $session !== '0' && $subject !== '0' && !empty($start_date) && !empty($end_date));

        if($canLoadGrid){
            
            // Parse date range
            $startDateCarbon = Carbon::parse($start_date);
            $endDateCarbon = Carbon::parse($end_date);

            // Validate subject access
            $subject_check = Subject::where('id', $subject);
            $subject_check->with('classes')->whereHas('classes', function ($query) use ($teacher_id, $session, $superAdmin, $hasAssignments){
                $query->where('session_id', $session);
                if(!$superAdmin && !$hasAssignments){
                    $query->where('teacher_id', $teacher_id);
                }
            });
            
            if(!$subject_check->first()) {
                Flasher::addError('You do not have access to this subject', 'Error');
                return redirect()->back();
            }
            
            // Limit to max 31 days for performance
            if($startDateCarbon->diffInDays($endDateCarbon) > 31) {
                $endDateCarbon = $startDateCarbon->copy()->addDays(31);
                $data['end_date'] = $endDateCarbon->format('Y-m-d');
                Flasher::addWarning('Date range limited to 31 days maximum', 'Warning');
            }

            $dates = [];
            $currentDate = $startDateCarbon->copy();
            while($currentDate->lte($endDateCarbon)) {
                $dates[] = $currentDate->format('Y-m-d');
                $currentDate->addDay();
            }
            $data['dates'] = $dates;

            // Get enrolled students
            $enrolls = StudentEnroll::where('status', '1');
            
            // Only filter by program if NOT in Include All mode
            if($includeAllPrograms !== '1' && $program !== '0'){
                $enrolls->where('program_id', $program);
            }
            if($session !== '0'){
                $enrolls->where('session_id', $session);
            }
            if($includeAllPrograms !== '1' && $semester !== '0'){
                $enrolls->where('semester_id', $semester);
            }
            if($includeAllPrograms !== '1' && $section !== '0'){
                $enrolls->where('section_id', $section);
            }
            $enrolls->with('subjects')->whereHas('subjects', function ($query) use ($subject){
                $query->where('subject_id', $subject);
            });
            $enrolls->with(['student', 'program'])->whereHas('student', function ($query){
                $query->where('status', '1');
            })->orderBy('matricule', 'asc');

            $rows = $enrolls->get();

            // Group by unique matricule
            $uniqueMatricules = $rows->groupBy('matricule')->map(function($group) {
                return $group->sortByDesc('id')->first();
            })->values();

            $data['rows'] = $uniqueMatricules->sortBy(function($query){
               return $query->matricule;
            })->values();

            // Get existing attendance records for the date range
            $existingAttendances = StudentAttendance::where('subject_id', $subject)
                ->whereBetween('date', [$start_date, $end_date])
                ->whereIn('student_enroll_id', $data['rows']->pluck('id'))
                ->get()
                ->groupBy(function($item) {
                    return $item->student_enroll_id . '_' . $item->date;
                });
            
            $data['existingAttendances'] = $existingAttendances;
        }

        return view($this->view.'.bulk-migration', $data);
    }

    /**
     * Store bulk migration attendance data.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function bulkMigrationStore(Request $request)
    {
        $request->validate([
            'subject' => 'required|exists:subjects,id',
        ]);

        $subject_id = $request->subject;
        $attendances = $request->attendances ?? [];
        $notes = $request->notes ?? [];
        $created_by = Auth::guard('web')->user()->id;
        
        if(empty($attendances)) {
            Flasher::addError('No attendance data submitted', 'Error');
            return redirect()->back();
        }
        
        $insertCount = 0;
        $updateCount = 0;

        foreach($attendances as $enroll_id => $dateData) {
            foreach($dateData as $date => $attendance_value) {
                if($attendance_value !== null && $attendance_value !== '') {
                    $result = StudentAttendance::updateOrCreate(
                        [
                            'student_enroll_id' => $enroll_id,
                            'subject_id' => $subject_id,
                            'date' => $date,
                        ],
                        [
                            'attendance' => $attendance_value,
                            'note' => $notes[$enroll_id][$date] ?? null,
                            'created_by' => $created_by,
                        ]
                    );
                    
                    if($result->wasRecentlyCreated) {
                        $insertCount++;
                    } else {
                        $updateCount++;
                    }
                }
            }
        }

        Flasher::addSuccess("Migration complete! Created: {$insertCount}, Updated: {$updateCount}", 'Success');

        return redirect()->back();
    }
}
