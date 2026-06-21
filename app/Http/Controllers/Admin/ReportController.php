<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudentAttendance;
use App\Models\TransportVehicle;
use App\Models\TransportMember;
use App\Models\ExpenseCategory;
use App\Models\IncomeCategory;
use App\Models\TransportRoute;
use App\Models\StudentEnroll;
use App\Models\WorkShiftType;
use App\Models\LibraryMember;
use App\Models\HostelMember;
use App\Models\FeesCategory;
use Illuminate\Http\Request;
use App\Models\IssueReturn;
use App\Models\Designation;
use App\Models\Department;
use App\Models\StatusType;
use App\Models\LeaveType;
use App\Models\ItemIssue;
use App\Models\Semester;
use App\Models\Session;
use App\Models\Program;
use App\Models\Section;
use App\Models\Student;
use App\Models\Faculty;
use App\Models\Subject;
use App\Models\Payroll;
use App\Services\StaffAssignmentService;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Hostel;
use App\Models\Leave;
use App\Models\Grade;
use App\Models\Item;
use App\Models\Book;
use App\Models\Fee;
use Carbon\Carbon;
use App\User;

class ReportController extends Controller
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
        $this->title = trans_choice('module_report', 1);
        $this->route = 'admin.report';
        $this->view = 'admin.report';
        $this->access = 'report';


        $this->middleware('permission:'.$this->access.'-student-progress', ['only' => ['student']]);
        $this->middleware('permission:'.$this->access.'-subject-students', ['only' => ['subject']]);
        $this->middleware('permission:'.$this->access.'-student-attendance', ['only' => ['studentAttendance']]);
        $this->middleware('permission:'.$this->access.'-subject-attendance', ['only' => ['subjectAttendance']]);
        $this->middleware('permission:'.$this->access.'-collected-fees', ['only' => ['fees']]);
        $this->middleware('permission:'.$this->access.'-student-fees', ['only' => ['studentFees']]);
        $this->middleware('permission:'.$this->access.'-salary-paid', ['only' => ['payroll']]);
        $this->middleware('permission:'.$this->access.'-staff-leaves', ['only' => ['leave']]);
        $this->middleware('permission:'.$this->access.'-income', ['only' => ['income']]);
        $this->middleware('permission:'.$this->access.'-expense', ['only' => ['expense']]);
        $this->middleware('permission:'.$this->access.'-library', ['only' => ['library']]);
        $this->middleware('permission:'.$this->access.'-book-return', ['only' => ['bookReturn']]);
        $this->middleware('permission:'.$this->access.'-inventory', ['only' => ['inventory']]);
        $this->middleware('permission:'.$this->access.'-hostel', ['only' => ['hostel']]);
        $this->middleware('permission:'.$this->access.'-transport', ['only' => ['transport']]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function student(Request $request)
    {
        //
        $data['title'] = trans_choice('module_student_progress', 1).' '.trans_choice('module_report', 1);
        $data['route'] = $this->route;
        $data['view'] = $this->view;
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

        if(!empty($request->status) || $request->status != null){
            $data['selected_status'] = $status = $request->status;
        }
        else{
            $data['selected_status'] = '0';
        }


        // Search Filter
        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();
        $data['statuses'] = StatusType::where('status', '1')->orderBy('title', 'asc')->get();
        $data['grades'] = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();


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


        // Student Filter
        $students = Student::where('status', '1');
        if($faculty != 0){
            $students->with('program')->whereHas('program', function ($query) use ($faculty){
                $query->where('faculty_id', $faculty);
            });
        }
        $students->with('currentEnroll')->whereHas('currentEnroll', function ($query) use ($program, $session, $semester, $section){
            if($program != 0){
            $query->where('program_id', $program);
            }
            if($session != 0){
            $query->where('session_id', $session);
            }
            if($semester != 0){
            $query->where('semester_id', $semester);
            }
            if($section != 0){
            $query->where('section_id', $section);
            }
        });
        if(!empty($request->status)){
            $students->with('statuses')->whereHas('statuses', function ($query) use ($status){
                $query->where('status_type_id', $status);
            });
        }
        $rows = $students->orderBy('student_id', 'desc')->get();

        // Array Sorting
        $data['rows'] = $rows->sortByDesc(function($query){

           return $query->currentEnroll->matricule ?? $query->student_id;

        })->all();


        return view($this->view.'.student', $data);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function subject(Request $request)
    {
        //
        $data['title'] = trans_choice('module_course_students', 1).' '.trans_choice('module_report', 1);
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;


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

        if(!empty($request->subject) || $request->subject != null){
            $data['selected_subject'] = $subject = $request->subject;
        }
        else{
            $data['selected_subject'] = '0';
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

        if(!empty($request->program) && $request->program != '0' && !empty($request->session) && $request->session != '0'){
            // Filter Subject
            $subjects = Subject::where('status', '1');
            $subjects->with('classes')->whereHas('classes', function ($query) use ($session){
                if(isset($session)){
                    $query->where('session_id', $session);
                }
            });
            $subjects->with('programs')->whereHas('programs', function ($query) use ($program){
                $query->where('program_id', $program);
            });
            $data['subjects'] = $subjects->orderBy('code', 'asc')->get();
        }


        // Student List
        if(!empty($request->program) && !empty($request->session) && !empty($request->subject)){

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


        return view($this->view.'.subject', $data);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function studentAttendance(Request $request)
    {
        //
        $data['title']     = trans_choice('module_student_attendance', 1);
        $data['route']     = $this->route;
        $data['view']      = $this->view;
        $data['access']    = $this->access;


        if(!empty($request->student) || $request->student != null){
            $data['selected_student'] = $student = $request->student;
        }
        else{
            $data['selected_student'] = $student = null;
        }

        if(!empty($request->session) || $request->session != null){
            $data['selected_session'] = $session = $request->session;
        }
        else{
            $data['selected_session'] = '0';
        }


        $data['students'] = Student::where('status', '1')->orderBy('student_id', 'asc')->get();
        $data['sessions'] = Session::where('status', '1')->orderBy('id', 'desc')->get();


        if(!empty($request->student) && !empty($request->session)){
        $enroll = $data['studentEnroll'] = StudentEnroll::where('student_id', $student)
                            ->where('session_id', $session)
                            ->first();
        }

        // Attendances
        if(isset($enroll) && !empty($request->session)){
        $data['attendances'] = StudentAttendance::where('student_enroll_id', $enroll->id)
                            ->orderBy('id', 'asc')
                            ->get();
        }


        return view($this->view.'.student-attendance', $data);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function subjectAttendance(Request $request)
    {
        //
        $data['title']     = trans_choice('module_student_subject_attendance', 1);
        $data['route']     = $this->route;
        $data['view']      = $this->view;
        $data['access']    = $this->access;


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

        $data['selected_semester_year'] = $selected_semester_year = $request->semester_year ?? '0';
        $data['selected_semester'] = $semester = $request->semester ?? '0';
        $data['selected_section'] = $section = $request->section ?? '0';

        if(!empty($request->subject) || $request->subject != null){
            $data['selected_subject'] = $subject = $request->subject;
        }
        else{
            $data['selected_subject'] = '0';
            $subject = null;
        }


        // Search Filter
    $data['programs'] = collect();
    $data['sessions'] = collect();
    $data['semesters'] = collect();
    $data['sections'] = collect();
    $data['semesterOptions'] = [];
    $data['subjects'] = collect();
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

            $subjects = Subject::where('status', '1');
            $subjects->with('classes')->whereHas('classes', function ($query) use ($session){
                if(!empty($session) && $session !== '0'){
                    $query->where('session_id', $session);
                }
            });
            $subjects->with('programs')->whereHas('programs', function ($query) use ($program){
                $query->where('program_id', $program);
            });
            $data['subjects'] = $subjects->orderBy('code', 'asc')->get();
        }

        if(!empty($program) && $program !== '0' && !empty($semester) && $semester !== '0'){
            $sections = Section::where('status', 1);
            $sections->with('semesterPrograms')->whereHas('semesterPrograms', function ($query) use ($program, $semester){
                $query->where('program_id', $program);
                $query->where('semester_id', $semester);
            });
            $data['sections'] = $sections->orderBy('title', 'asc')->get();
        }


        // Student List
    if(!empty($program) && $program !== '0' && !empty($session) && $session !== '0' && !empty($semester) && $semester !== '0' && !empty($section) && $section !== '0' && !empty($subject) && $subject !== '0'){

            // Enrolls
            $enrolls = StudentEnroll::where('id', '!=', '0');
            if(!empty($program)){
                $enrolls->where('program_id', $program);
            }
            if(!empty($session)){
                $enrolls->where('session_id', $session);
            }
            if(!empty($semester)){
                $enrolls->where('semester_id', $semester);
            }
            if(!empty($section)){
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
        if(!empty($program) && $program !== '0' && !empty($session) && $session !== '0' && !empty($semester) && $semester !== '0' && !empty($section) && $section !== '0' && !empty($subject) && $subject !== '0'){
            $attendances = StudentAttendance::where('subject_id', $subject);

            $attendances->with('studentEnroll')->whereHas('studentEnroll', function ($query) use ($program, $session, $semester, $section){
                        $query->where('program_id', $program);
                        $query->where('session_id', $session);
                        $query->where('semester_id', $semester);
                        $query->where('section_id', $section);
                    });
            $data['attendances'] = $attendances->orderBy('id', 'asc')
                                ->get();
        }


        return view($this->view.'.subject-attendance', $data);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function fees(Request $request)
    {
        //
        $data['title'] = trans_choice('module_collected_fees', 1).' '.trans_choice('module_report', 1);
        $data['route'] = $this->route;
        $data['view'] = $this->view;
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

        if(!empty($request->category) || $request->category != null){
            $data['selected_category'] = $category = $request->category;
        }
        else{
            $data['selected_category'] = $category = '0';
        }

        if(!empty($request->start_date) || $request->start_date != null){
            $data['selected_start_date'] = $start_date = $request->start_date;
        }
        else{
            $data['selected_start_date'] = $start_date = date('Y-m-d', strtotime(Carbon::now()->subMonth()));
        }

        if(!empty($request->end_date) || $request->end_date != null){
            $data['selected_end_date'] = $end_date = $request->end_date;
        }
        else{
            $data['selected_end_date'] = $end_date = date('Y-m-d', strtotime(Carbon::today()));
        }


        $facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');
        $data['faculties'] = StaffAssignmentService::filterFaculties($facultyQuery)->get();
        $data['categories'] = FeesCategory::where('status', '1')->orderBy('title', 'asc')->get();


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


        // Payment status filter
        if(!empty($request->payment_status) || $request->payment_status != null){
            $data['selected_payment_status'] = $payment_status = $request->payment_status;
        }
        else{
            $data['selected_payment_status'] = $payment_status = 'all';
        }

        // Filter Fees
        $fees = Fee::with(['studentEnroll.student', 'studentEnroll.program', 'studentEnroll.session', 'studentEnroll.semester', 'category', 'approvedReceipts']);
        
        // Date filter - for paid fees only
        if($payment_status == '1'){
            $fees->whereDate('pay_date', '>=', $start_date)
                 ->whereDate('pay_date', '<=', $end_date);
        }

        if(!empty($request->faculty) || !empty($request->program) || !empty($request->session) || !empty($request->semester) || !empty($request->section)){
            $fees->whereHas('studentEnroll.program', function ($query) use ($faculty){
                if($faculty != 0){
                $query->where('faculty_id', $faculty);
                }
            });

            $fees->whereHas('studentEnroll', function ($query) use ($program, $session, $semester, $section){
                if($program != 0){
                $query->where('program_id', $program);
                }
                if($session != 0){
                $query->where('session_id', $session);
                }
                if($semester != 0){
                $query->where('semester_id', $semester);
                }
                if($section != 0){
                $query->where('section_id', $section);
                }
            });
        }
        if($category != 0){
            $fees->where('category_id', $category);
        }

        // Payment status filter
        if($payment_status == '0'){
            // Unpaid only
            $fees->where('status', 0);
        } elseif($payment_status == '1'){
            // Fully paid only
            $fees->where('status', 1);
        } elseif($payment_status == '2'){
            // Partially paid only
            $fees->where('status', 2);
        } elseif($payment_status == 'due'){
            // Fees due (unpaid + partially paid)
            $fees->whereIn('status', [0, 2]);
        } else {
            // All except cancelled
            $fees->whereIn('status', [0, 1, 2]);
        }

        $fees->whereHas('studentEnroll.student', function ($query){
            // $query->orderBy('student_id', 'asc');
        });

        $data['rows'] = $fees->orderBy('due_date', 'asc')->orderBy('updated_at', 'desc')->get();
        
        // Calculate statistics
        $all_fees = Fee::with(['studentEnroll.student', 'studentEnroll.program', 'category']);
        
        // Apply same filters to statistics
        if(!empty($request->faculty) || !empty($request->program) || !empty($request->session) || !empty($request->semester) || !empty($request->section)){
            $all_fees->whereHas('studentEnroll.program', function ($query) use ($faculty){
                if($faculty != 0){
                $query->where('faculty_id', $faculty);
                }
            });
            $all_fees->whereHas('studentEnroll', function ($query) use ($program, $session, $semester, $section){
                if($program != 0){
                $query->where('program_id', $program);
                }
                if($session != 0){
                $query->where('session_id', $session);
                }
                if($semester != 0){
                $query->where('semester_id', $semester);
                }
                if($section != 0){
                $query->where('section_id', $section);
                }
            });
        }
        if($category != 0){
            $all_fees->where('category_id', $category);
        }
        
        $all_fees = $all_fees->whereIn('status', [0, 1, 2])->get();
        
        $data['stats'] = [
            'total_fees' => $all_fees->count(),
            'unpaid_count' => $all_fees->where('status', 0)->count(),
            'partially_paid_count' => $all_fees->where('status', 2)->count(),
            'fully_paid_count' => $all_fees->where('status', 1)->count(),
            'total_amount_due' => $all_fees->sum(function($fee) {
                return $fee->fee_amount + $fee->fine_amount - $fee->discount_amount;
            }),
            // Cap per-fee paid at total_amount to avoid double-counting overpayments
            // (which are already extracted as StudentCredits).
            'total_paid' => $all_fees->sum(function($fee) {
                return min((float) $fee->paid_amount, (float) $fee->total_amount);
            }),
            'total_remaining' => $all_fees->sum(function($fee) {
                return max(0, (float) $fee->remaining_balance);
            }),
        ];


        return view($this->view.'.fees', $data);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function studentFees(Request $request)
    {
        //
        $data['title']     = trans_choice('module_student_fees', 1).' '.trans_choice('module_report', 1);
        $data['route']     = $this->route;
        $data['view']      = $this->view;
        $data['access']    = $this->access;


        if(!empty($request->student) || $request->student != null){
            $data['selected_student'] = $student = $request->student;
        }
        else{
            $data['selected_student'] = $student = null;
        }

        if(!empty($request->category) || $request->category != null){
            $data['selected_category'] = $category = $request->category;
        }
        else{
            $data['selected_category'] = '0';
        }

        if(!empty($request->payment_status) || $request->payment_status != null){
            $data['selected_payment_status'] = $payment_status = $request->payment_status;
        }
        else{
            $data['selected_payment_status'] = $payment_status = 'all';
        }


        $data['students'] = Student::where('status', '!=', '0')->orderBy('student_id', 'asc')->get();
        $data['categories'] = FeesCategory::where('status', '1')->orderBy('title', 'asc')->get();


        // Filter Fees
        $fees = Fee::with(['studentEnroll.student', 'studentEnroll.session', 'studentEnroll.semester', 'studentEnroll.program', 'category', 'approvedReceipts', 'generatedCredits'])
                    ->whereHas('studentEnroll', function ($query) use ($student){
                        $query->where('student_id', $student);
                    });
        
        if(!empty($request->category) && $category != '0'){
            $fees->where('category_id', $category);
        }
        
        // Payment status filter
        if($payment_status == '0'){
            $fees->where('status', 0); // Unpaid only
        } elseif($payment_status == '1'){
            $fees->where('status', 1); // Fully paid only
        } elseif($payment_status == '2'){
            $fees->where('status', 2); // Partially paid only
        } elseif($payment_status == 'due'){
            $fees->whereIn('status', [0, 2]); // Fees due (unpaid + partially paid)
        } else {
            $fees->whereIn('status', [0, 1, 2]); // All except cancelled
        }
        
        $data['rows'] = $fees->orderBy('due_date', 'asc')->orderBy('assign_date', 'asc')->get();
        
        // Calculate statistics if student is selected
        if($student){
            $all_student_fees = Fee::with(['studentEnroll', 'category'])
                                    ->whereHas('studentEnroll', function ($query) use ($student){
                                        $query->where('student_id', $student);
                                    });
            
            if(!empty($request->category) && $category != '0'){
                $all_student_fees->where('category_id', $category);
            }
            
            $all_student_fees = $all_student_fees->whereIn('status', [0, 1, 2])->get();
            
            $data['stats'] = [
                'total_fees' => $all_student_fees->count(),
                'unpaid_count' => $all_student_fees->where('status', 0)->count(),
                'partially_paid_count' => $all_student_fees->where('status', 2)->count(),
                'fully_paid_count' => $all_student_fees->where('status', 1)->count(),
                'total_amount_due' => $all_student_fees->sum(function($fee) {
                    return $fee->fee_amount + $fee->fine_amount - $fee->discount_amount;
                }),
                // Cap per-fee paid at total_amount so overpayments (already
                // extracted as StudentCredits) are not double-counted.
                'total_paid' => $all_student_fees->sum(function($fee) {
                    return min((float) $fee->paid_amount, (float) $fee->total_amount);
                }),
                'total_remaining' => $all_student_fees->sum(function($fee) {
                    return max(0, (float) $fee->remaining_balance);
                }),
            ];
        }


        return view($this->view.'.student-fees', $data);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function payroll(Request $request)
    {
        //
        $data['title'] = trans_choice('module_salary_paid', 1).' '.trans_choice('module_report', 1);
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;


        if(!empty($request->salary_type) || $request->salary_type != null){
            $data['selected_salary_type'] = $salary_type = $request->salary_type;
        }
        else{
            $data['selected_salary_type'] = '0';
        }

        if(!empty($request->department) || $request->department != null){
            $data['selected_department'] = $department = $request->department;
        }
        else{
            $data['selected_department'] = '0';
        }

        if(!empty($request->designation) || $request->designation != null){
            $data['selected_designation'] = $designation = $request->designation;
        }
        else{
            $data['selected_designation'] = '0';
        }

        if(!empty($request->shift) || $request->shift != null){
            $data['selected_shift'] = $shift = $request->shift;
        }
        else{
            $data['selected_shift'] = '0';
        }

        if(!empty($request->contract_type) || $request->contract_type != null){
            $data['selected_contract'] = $contract_type = $request->contract_type;
        }
        else{
            $data['selected_contract'] = '0';
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


        $data['departments'] = Department::where('status', '1')->orderBy('title', 'asc')->get();
        $data['designations'] = Designation::where('status', '1')->orderBy('title', 'asc')->get();
        $data['work_shifts'] = WorkShiftType::where('status', '1')->orderBy('title', 'asc')->get();


        // Filter Payrolls
        if(!empty($request->month) && !empty($request->year)){

            $payrolls = Payroll::whereYear('salary_month', $year)->whereMonth('salary_month', $month);

            if(!empty($request->salary_type)){
                $payrolls->where('salary_type', $salary_type);
            }
            if(!empty($request->department)){
                $payrolls->with('user')->whereHas('user', function ($query) use ($department){
                    $query->where('department_id', $department);
                });
            }
            if(!empty($request->designation)){
                $payrolls->with('user')->whereHas('user', function ($query) use ($designation){
                    $query->where('designation_id', $designation);
                });
            }
            if(!empty($request->shift)){
                $payrolls->with('user')->whereHas('user', function ($query) use ($shift){
                    $query->where('work_shift', $shift);
                });
            }
            if(!empty($request->contract_type)){
                $payrolls->with('user')->whereHas('user', function ($query) use ($contract_type){
                    $query->where('contract_type', $contract_type);
                });
            }

            $data['rows'] = $payrolls->where('status', '1')->orderBy('id', 'asc')->get();
        }


        return view($this->view.'.payroll', $data);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function leave(Request $request)
    {
        //
        $data['title'] = trans_choice('module_staff_leaves', 1).' '.trans_choice('module_report', 1);
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;


        if(!empty($request->user) || $request->user != null){
            $data['selected_user'] = $user = $request->user;
        }
        else{
            $data['selected_user'] = $user = '0';
        }

        if(!empty($request->pay_type) || $request->pay_type != null){
            $data['selected_pay_type'] = $pay_type = $request->pay_type;
        }
        else{
            $data['selected_pay_type'] = $pay_type = '0';
        }

        if(!empty($request->type) || $request->type != null){
            $data['selected_type'] = $type = $request->type;
        }
        else{
            $data['selected_type'] = $type = '0';
        }

        if(!empty($request->duration) || $request->duration != null){
            $data['selected_duration'] = $duration = $request->duration;
        }
        else{
            $data['selected_duration'] = $duration = '0';
        }



        // Start Date
        if($duration != 0){
            $data['start_date'] = $start_date = date("Y-m-d", strtotime(Carbon::today()->subMonths($duration)));
        }
        else{
            $data['start_date'] = $start_date = '2001-01-01';
        }

        // End Date
        $data['end_date'] = $end_date = date("Y-m-d", strtotime(Carbon::today()));



        // Search Filter
        $data['users'] = User::where('status', '1')
                            ->orderBy('staff_id', 'asc')->get();
        $data['types'] = LeaveType::where('status', '1')
                            ->orderBy('title', 'asc')->get();

        $rows = Leave::whereDate('apply_date', '>=', $start_date)
                    ->whereDate('apply_date', '<=', $end_date);
                    if(!empty($request->user) || $request->user != null){
                        $rows->where('user_id', $user);
                    }
                    if(!empty($request->pay_type) || $request->pay_type != null){
                        $rows->where('pay_type', $pay_type);
                    }
                    if(!empty($request->type) || $request->type != null){
                        $rows->where('type_id', $type);
                    }
        $data['rows'] = $rows->where('status', '1')->orderBy('apply_date', 'desc')->get();


        return view($this->view.'.leave', $data);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function income(Request $request)
    {
        //
        $data['title'] = trans_choice('module_total_income', 1).' '.trans_choice('module_report', 1);
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;


        if(!empty($request->category) || $request->category != null){
            $data['selected_category'] = $category = $request->category;
        }
        else{
            $data['selected_category'] = $category = '0';
        }

        if(!empty($request->duration) || $request->duration != null){
            $data['selected_duration'] = $duration = $request->duration;
        }
        else{
            $data['selected_duration'] = $duration = '0';
        }



        // Start Date
        if($duration != 0){
            $data['start_date'] = $start_date = date("Y-m-d", strtotime(Carbon::today()->subMonths($duration)));
        }
        else{
            $data['start_date'] = $start_date = '2001-01-01';
        }

        // End Date
        $data['end_date'] = $end_date = date("Y-m-d", strtotime(Carbon::today()));



        // Search Filter
        $data['categories'] = IncomeCategory::where('status', '1')
                            ->orderBy('title', 'asc')->get();

        $rows = Income::whereDate('date', '>=', $start_date)
                    ->whereDate('date', '<=', $end_date);
                    if(!empty($request->category) || $request->category != null){
                        $rows->where('category_id', $category);
                    }
        $data['rows'] = $rows->orderBy('date', 'asc')->get();

        return view($this->view.'.income', $data);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function expense(Request $request)
    {
        //
        $data['title'] = trans_choice('module_total_expense', 1).' '.trans_choice('module_report', 1);
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;


        if(!empty($request->category) || $request->category != null){
            $data['selected_category'] = $category = $request->category;
        }
        else{
            $data['selected_category'] = $category = '0';
        }

        if(!empty($request->duration) || $request->duration != null){
            $data['selected_duration'] = $duration = $request->duration;
        }
        else{
            $data['selected_duration'] = $duration = '0';
        }



        // Start Date
        if($duration != 0){
            $data['start_date'] = $start_date = date("Y-m-d", strtotime(Carbon::today()->subMonths($duration)));
        }
        else{
            $data['start_date'] = $start_date = '2001-01-01';
        }

        // End Date
        $data['end_date'] = $end_date = date("Y-m-d", strtotime(Carbon::today()));



        // Search Filter
        $data['categories'] = ExpenseCategory::where('status', '1')
                            ->orderBy('title', 'asc')->get();

        $rows = Expense::whereDate('date', '>=', $start_date)
                    ->whereDate('date', '<=', $end_date);
                    if(!empty($request->category) || $request->category != null){
                        $rows->where('category_id', $category);
                    }
        $data['rows'] = $rows->orderBy('date', 'asc')->get();

        return view($this->view.'.expense', $data);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function library(Request $request)
    {
        //
        $data['title'] = trans_choice('module_library_history', 1).' '.trans_choice('module_report', 1);
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;


        if(!empty($request->member) || $request->member != null){
            $data['selected_member'] = $member = $request->member;
        }
        else{
            $data['selected_member'] = $member = '0';
        }

        if(!empty($request->book) || $request->book != null){
            $data['selected_book'] = $book = $request->book;
        }
        else{
            $data['selected_book'] = $book = '0';
        }

        if(!empty($request->start_date) || $request->start_date != null){
            $data['selected_start_date'] = $start_date = $request->start_date;
        }
        else{
            $data['selected_start_date'] = $start_date = date('Y-m-d', strtotime(Carbon::now()->subYear()));
        }

        if(!empty($request->end_date) || $request->end_date != null){
            $data['selected_end_date'] = $end_date = $request->end_date;
        }
        else{
            $data['selected_end_date'] = $end_date = date('Y-m-d', strtotime(Carbon::today()));
        }


        // Search Filter
        $data['books'] = Book::where('status', '1')
                        ->orderBy('isbn', 'asc')->get();

        $data['members'] = LibraryMember::where('status', '1')
                        ->orderBy('library_id', 'asc')->get();

        $rows = IssueReturn::whereDate('issue_date', '>=', $start_date)
                    ->whereDate('issue_date', '<=', $end_date);
                    if(!empty($request->member) || $request->member != null){
                        $rows->where('member_id', $member);
                    }
                    if(!empty($request->book) || $request->book != null){
                        $rows->where('book_id', $book);
                    }
        $data['rows'] = $rows->orderBy('id', 'desc')->get();

        return view($this->view.'.library', $data);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function bookReturn()
    {
        //
        $data['title'] = trans_choice('module_book_return_due', 1).' '.trans_choice('module_report', 1);
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;

        $data['rows'] = IssueReturn::where('return_date', null)
                ->where('due_date', '<', Carbon::today())
                ->where('status', '1')
                ->orderBy('id', 'desc')
                ->get();

        return view($this->view.'.book-return', $data);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function inventory(Request $request)
    {
        //
        $data['title'] = trans_choice('module_inventory_history', 1).' '.trans_choice('module_report', 1);
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;


        if(!empty($request->item) || $request->item != null){
            $data['selected_item'] = $item = $request->item;
        }
        else{
            $data['selected_item'] = $item = '0';
        }

        if(!empty($request->user) || $request->user != null){
            $data['selected_user'] = $user = $request->user;
        }
        else{
            $data['selected_user'] = $user = '0';
        }

        if(!empty($request->start_date) || $request->start_date != null){
            $data['selected_start_date'] = $start_date = $request->start_date;
        }
        else{
            $data['selected_start_date'] = $start_date = date('Y-m-d', strtotime(Carbon::now()->subYear()));
        }

        if(!empty($request->end_date) || $request->end_date != null){
            $data['selected_end_date'] = $end_date = $request->end_date;
        }
        else{
            $data['selected_end_date'] = $end_date = date('Y-m-d', strtotime(Carbon::today()));
        }


        $data['items'] = Item::where('status', '1')
                            ->orderBy('name', 'asc')->get();
        $data['users'] = User::where('status', '1')
                            ->orderBy('staff_id', 'asc')->get();


        // Search Filter
        $rows = ItemIssue::whereDate('issue_date', '>=', $start_date)
                    ->whereDate('issue_date', '<=', $end_date);
                    if(!empty($request->item) || $request->item != null){
                        $rows->where('item_id', $item);
                    }
                    if(!empty($request->user) || $request->user != null){
                        $rows->where('user_id', $user);
                    }
        $data['rows'] = $rows->orderBy('id', 'desc')->get();

        return view($this->view.'.inventory', $data);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function hostel(Request $request)
    {
        //
        $data['title'] = trans_choice('module_hostel_members', 1).' '.trans_choice('module_report', 1);
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;


        if(!empty($request->hostel) || $request->hostel != null){
            $data['selected_hostel'] = $hostel = $request->hostel;
        }
        else{
            $data['selected_hostel'] = $hostel = '0';
        }

        if(!empty($request->member) || $request->member != null){
            $data['selected_member'] = $member = $request->member;
        }
        else{
            $data['selected_member'] = $member = '0';
        }


        // Search Filter
        $data['hostels'] = Hostel::where('status', '1')
                        ->orderBy('name', 'asc')->get();


        $rows = HostelMember::where('status', '1');
                    if(!empty($request->hostel) || $request->hostel != null){
                        $rows->with('room')->whereHas('room', function ($query) use ($hostel){
                            $query->where('hostel_id', $hostel);
                        });
                    }
                    if($request->member == 1){
                        $rows->where('hostelable_type', 'App\Models\Student');
                    }
                    elseif($request->member == 2){
                        $rows->where('hostelable_type', 'App\User');
                    }
        $data['rows'] = $rows->orderBy('hostel_room_id', 'asc')->get();

        return view($this->view.'.hostel', $data);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function transport(Request $request)
    {
        //
        $data['title'] = trans_choice('module_transport_members', 1).' '.trans_choice('module_report', 1);
        $data['route'] = $this->route;
        $data['view'] = $this->view;
        $data['access'] = $this->access;


        if(!empty($request->route) || $request->route != null){
            $data['selected_route'] = $route = $request->route;
        }
        else{
            $data['selected_route'] = $route = '0';
        }

        if(!empty($request->vehicle) || $request->vehicle != null){
            $data['selected_vehicle'] = $vehicle = $request->vehicle;
        }
        else{
            $data['selected_vehicle'] = $vehicle = '0';
        }

        if(!empty($request->member) || $request->member != null){
            $data['selected_member'] = $member = $request->member;
        }
        else{
            $data['selected_member'] = $member = '0';
        }


        // Search Filter
        $data['transportRoutes'] = TransportRoute::where('status', '1')
                        ->orderBy('title', 'asc')->get();
        $data['transportVehicles'] = TransportVehicle::where('status', '1')
                        ->orderBy('number', 'asc')->get();


        $rows = TransportMember::where('status', '1');
                    if(!empty($request->route) || $request->route != null){
                        $rows->where('transport_route_id', $route);
                    }
                    if(!empty($request->vehicle) || $request->vehicle != null){
                        $rows->where('transport_vehicle_id', $vehicle);
                    }
                    if($request->member == 1){
                        $rows->where('transportable_type', 'App\Models\Student');
                    }
                    elseif($request->member == 2){
                        $rows->where('transportable_type', 'App\User');
                    }
        $data['rows'] = $rows->orderBy('transport_route_id', 'asc')->get();

        return view($this->view.'.transport', $data);
    }
}
