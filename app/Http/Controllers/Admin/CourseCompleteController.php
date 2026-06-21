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
use App\Services\GraduationEligibilityService;
use App\Models\Program;
use App\Models\Section;
use App\Models\Session;
use App\Models\Student;
use App\Models\Grade;

class CourseCompleteController extends Controller
{
    protected $title, $route, $view, $path, $access;
    protected $graduationService;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(GraduationEligibilityService $graduationService)
    {
        $this->graduationService = $graduationService;
        
        // Module Data
        $this->title = trans_choice('module_course_complete', 1);
        $this->route = 'admin.course-complete';
        $this->view = 'admin.course-complete';
        $this->path = 'student';
        $this->access = 'student-enroll';


        $this->middleware('permission:'.$this->access.'-complete');
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
            $semesterCollection = $semesters->orderBy('year', 'asc')->orderBy('id', 'asc')->get();
            $data['semesters'] = $semesterCollection;
            $data['semesterOptions'] = $semesterCollection
                ->filter(function ($semesterItem) {
                    return !is_null($semesterItem->year);
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
        // Changed to match MarksheetController logic: query enrollments directly for multi-program support
        if(!empty($request->faculty) && !empty($request->program) && !empty($request->session) && !empty($request->semester) && !empty($request->section)){

            // Query enrollments that match the filters (allows multi-program students)
            $enrollments = StudentEnroll::with(['student', 'program', 'session', 'semester', 'section'])
                ->where('status', 1)
                ->whereHas('student', function($query) {
                    $query->where('status', '1');
                });

            if(!empty($request->faculty)){
                $enrollments->whereHas('program', function ($query) use ($faculty){
                    $query->where('faculty_id', $faculty);
                });
            }
            
            if(!empty($request->program)){
                $enrollments->where('program_id', $program);
            }
            if(!empty($request->session)){
                $enrollments->where('session_id', $session);
            }
            if(!empty($request->semester)){
                $enrollments->where('semester_id', $semester);
            }
            if(!empty($request->section)){
                $enrollments->where('section_id', $section);
            }

            // Get matching enrollments, then extract unique students
            // For multi-program students, this ensures we get students enrolled in THIS specific program/semester/section
            $matchingEnrollments = $enrollments->orderBy('id', 'desc')->get();
            $studentIds = $matchingEnrollments->pluck('student_id')->unique();
            
            // Get students with all their enrollments loaded
            $students = Student::whereIn('id', $studentIds)
                ->where('status', '1')
                ->with(['studentEnrolls.subjectMarks.subject', 'studentEnrolls.program'])
                ->orderBy('student_id', 'asc')
                ->get();

            // Array Sorting
            $data['rows'] = $students->sortBy(function($student) use ($program, $session, $semester, $section) {
                $matchingEnrollment = $student->studentEnrolls->first(function($enroll) use ($program, $session, $semester, $section) {
                    return $enroll->program_id == $program 
                        && $enroll->session_id == $session 
                        && $enroll->semester_id == $semester 
                        && $enroll->section_id == $section;
                });
                return $matchingEnrollment->matricule ?? $student->student_id;
            })->all();
            
            // Add graduation eligibility data for each student
            $studentsWithEligibility = [];
            foreach($data['rows'] as $student) {
                // Find the matching enrollment for this student based on the filters
                // This gives us the correct matricule for multi-program students
                $matchingEnrollment = $student->studentEnrolls->first(function($enroll) use ($program, $session, $semester, $section) {
                    return $enroll->program_id == $program 
                        && $enroll->session_id == $session 
                        && $enroll->semester_id == $semester 
                        && $enroll->section_id == $section;
                });
                
                // Use the filtered program for accurate eligibility check
                $eligibility = $this->graduationService->checkEligibility($student, $program);
                $courseBreakdown = $this->graduationService->getCourseBreakdown($student, $program);
                
                // Calculate CGPA - ONLY for the selected program
                // Multi-program students need their data filtered by program_id
                // IMPORTANT: CGPA includes ALL courses (passed AND failed)
                $total_cgpa = 0;
                $total_credits = 0;
                foreach($student->studentEnrolls as $item) {
                    // CRITICAL: Only include enrollments from the selected program
                    if($item->program_id != $program) {
                        continue;
                    }
                    
                    if(isset($item->subjectMarks)) {
                        foreach($item->subjectMarks as $mark) {
                            // Only count published marks
                            if(!$mark->is_visible_to_student) {
                                continue;
                            }
                            
                            if(!isset($mark->subject)) {
                                continue;
                            }
                            
                            $marks_per = round($mark->total_marks);
                            foreach($data['grades'] as $grade) {
                                if($marks_per >= $grade->min_mark && $marks_per <= $grade->max_mark) {
                                    // Count ALL courses including failed (F grade = 0 points)
                                    // This is correct CGPA calculation
                                    $total_cgpa += ($grade->point * $mark->subject->credit_hour);
                                    $total_credits += $mark->subject->credit_hour;
                                    break;
                                }
                            }
                        }
                    }
                }
                
                $cgpa = $total_credits > 0 ? number_format((float)($total_cgpa / $total_credits), 2, '.', '') : '0.00';
                
                $studentsWithEligibility[] = [
                    'student' => $student,
                    'matching_enrollment' => $matchingEnrollment, // Pass the specific enrollment
                    'eligibility' => $eligibility,
                    'course_breakdown' => $courseBreakdown,
                    'total_credits' => round($total_credits, 2),
                    'cgpa' => $cgpa,
                ];
            }
            
            $data['students_with_eligibility'] = $studentsWithEligibility;
            
            // Get batch statistics
            $data['batch_statistics'] = $this->graduationService->getBatchStatistics(collect($data['rows']));
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
            'program'  => 'required',
        ]);

        try{
            DB::beginTransaction();

            foreach($request->students as $key => $student_id){
            if(!empty($student_id) || $student_id == ''){

                // 1. Close ONLY the enrollment for this specific program
                // We look for any active enrollment in this program for this student
                $enrollment = StudentEnroll::where('student_id', $student_id)
                                        ->where('program_id', $request->program)
                                        ->where('status', '1')
                                        ->first();
                
                if(isset($enrollment)){
                    $enrollment->status = '2'; // 2 = Course Completed
                    $enrollment->save();
                }

                // 2. Check if student has ANY other active enrollments in ANY program
                // If they have other active enrollments, they are still an active student (just not in this program)
                $hasOtherActiveEnrollments = StudentEnroll::where('student_id', $student_id)
                                                        ->where('status', '1')
                                                        ->exists();

                // 3. Only mark Student Profile as "Alumni" if they have NO other active enrollments
                $student = Student::find($student_id);
                if (!$hasOtherActiveEnrollments) {
                    $student->status = '2'; // Alumni/Passed Out
                } else {
                    // Ensure they stay active if they have other courses
                    $student->status = '1'; 
                }
                
                $student->updated_by = Auth::guard('web')->user()->id;
                $student->save();

            }}
            DB::commit();


            Flasher::addSuccess(__('msg_promoted_successfully'), __('msg_success'));

            return redirect()->back();
        }
        catch(\Exception $e){

            Flasher::addError(__('msg_created_error'), __('msg_error'));

            return redirect()->back();
        }
    }
}
