<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\AcademicDepartment;
use App\Models\Semester;
use App\Models\Session;
use App\Models\Program;
use App\Models\Subject;
use App\Models\Section;
use App\User;
use App\Services\StaffAssignmentService;

class FilterController extends Controller
{
    // Filter Academic Department by Faculty
    public function filterAcademicDepartment(Request $request)
    {
        $data = $request->all();

        $departments = AcademicDepartment::where('faculty_id', $data['faculty'])
            ->where('status', 1)
            ->orderBy('title', 'asc')
            ->get();

        return response()->json($departments);
    }

    // Filter Batch by Program
    public function filterBatch(Request $request)
    {
        $data = $request->all();

        $rows = Program::where('status', 1);
        $rows->with('batches')->whereHas('batches', function ($query) use ($data){
            $query->where('batch_id', $data['batch']);
        });
        
        // Apply staff assignment filter
        $rows = StaffAssignmentService::filterPrograms($rows);
        
        $programs = $rows->orderBy('title', 'asc')->get();

        return response()->json($programs);
    }

    // Filter Program by Faculty
    public function filterProgram(Request $request)
    {
        //
        $data = $request->all();

        $programs = Program::where('faculty_id', $data['faculty'])->where('status', 1);
        
        // Apply staff assignment filter
        $programs = StaffAssignmentService::filterPrograms($programs);
        
        $programs = $programs->orderBy('title', 'asc')->get();

        return response()->json($programs);
    }

    // Filter Session by Program
    public function filterSession(Request $request)
    {
        //
        $data = $request->all();

        $rows = Session::where('status', 1);
        $rows->with('programs')->whereHas('programs', function ($query) use ($data){
            $query->where('program_id', $data['program']);
        });
        $sessions = $rows->orderBy('id', 'desc')->get();

        return response()->json($sessions);
    }

    // Filter Semester by Program
    public function filterSemester(Request $request)
    {
        //
        $data = $request->all();

        $rows = Semester::where('status', 1);
        $rows->with('programs')->whereHas('programs', function ($query) use ($data){
            $query->where('program_id', $data['program']);
        });
        $semesters = $rows->orderBy('id', 'asc')->get();

        return response()->json($semesters);
    }

    // Filter Section by Program and Semester
    public function filterSection(Request $request)
    {
        //
        $data = $request->all();

        $rows = Section::where('status', 1);
        $rows->with('semesterPrograms')->whereHas('semesterPrograms', function ($query) use ($data){
            $query->where('program_id', $data['program']);
            $query->where('semester_id', $data['semester']);
        });
        $sections = $rows->orderBy('title', 'asc')->get();

        return response()->json($sections);
    }

    // Filter Subject by Program
    public function filterSubject(Request $request)
    {
        //
        $data = $request->all();

        $rows = Subject::where('status', 1);
        $rows->with('programs')->whereHas('programs', function ($query) use ($data){
            $query->where('program_id', $data['program']);
        });
        
        // Apply staff assignment filter
        $rows = StaffAssignmentService::filterCourses($rows);
        
        $subjects = $rows->orderBy('code', 'asc')->get();

        return response()->json($subjects);
    }

    // Filter Subject by Program and Semester
    public function filterEnrollSubject(Request $request)
    {
        //
        $data = $request->all();

        $rows = Subject::where('status', 1);
        $rows->with('subjectEnrolls')->whereHas('subjectEnrolls', function ($query) use ($data){
            $query->where('program_id', $data['program']);
            $query->where('semester_id', $data['semester']);
            $query->where('section_id', $data['section']);
        });
        
        // Apply staff assignment filter
        $rows = StaffAssignmentService::filterCourses($rows);
        
        $subjects = $rows->orderBy('code', 'asc')->get();

        return response()->json($subjects);
    }

    // Filter Subject by Program, Semester, Section and Session
    public function filterStudentSubject(Request $request)
    {
        //
        $data = $request->all();

        $subjects = DB::table('subjects')->select('subjects.*')->join('student_enroll_subject', 'student_enroll_subject.subject_id', 'subjects.id')->join('student_enrolls', 'student_enrolls.id', 'student_enroll_subject.student_enroll_id')->where('student_enrolls.program_id', $data['program'])->where('student_enrolls.session_id', $data['session'])->where('student_enrolls.semester_id', $data['semester'])->where('student_enrolls.section_id', $data['section'])->where('student_enrolls.status', '1')->where('subjects.status', '1')->orderBy('subjects.code', 'asc')->get();

        return response()->json($subjects);
    }

    // Filter Subject by Teacher
    public function filterTecherSubject(Request $request)
    {
        //
        $data = $request->all();

        // Access Data — use empty() to catch both null and empty strings
        $session = !empty($data['session']) ? $data['session'] : null;

        $authUser = Auth::guard('web')->user();
        $teacher_id = $authUser->id;
        
        // Use Spatie's hasRole() for reliable role detection (avoids morph type issues)
        $superAdmin = $authUser->hasRole('Super Admin');


        // Filter Subject
        $rows = Subject::where('status', '1');
        
        // Check if user has staff assignments
        $hasAssignments = \App\Models\StaffAssignment::where('user_id', $teacher_id)->exists();
        
        // Filter by class schedules (routines)
        if ($hasAssignments || $superAdmin) {
            // Staff with assignments OR Super Admin: Show all courses with class schedules (any teacher)
            $rows->with('classes')->whereHas('classes', function ($query) use ($session){
                if(isset($session)){
                    $query->where('session_id', $session);
                }
                // Don't filter by teacher_id - show all courses with schedules
            });
        } else {
            // Regular staff without assignments: Show only their own courses
            $rows->with('classes')->whereHas('classes', function ($query) use ($teacher_id, $session){
                if(isset($session)){
                    $query->where('session_id', $session);
                }
                $query->where('teacher_id', $teacher_id);
            });
        }
        
        if(isset($data['program'])){
            $rows->with('programs')->whereHas('programs', function ($query) use ($data){
                $query->where('program_id', $data['program']);
            });
        }
        
        // Apply staff assignment filter (restricts by faculty/program)
        $rows = StaffAssignmentService::filterCourses($rows);
        
        $subjects = $rows->orderBy('code', 'asc')->get();

        return response()->json($subjects);
    }
    
    /**
     * Get semester years for a given session
     */
    public function sessionSemesterYears($sessionId)
    {
        $semesters = Semester::where('status', 1)
            ->whereHas('programs', function($q) use ($sessionId) {
                $q->whereHas('sessions', function($sq) use ($sessionId) {
                    $sq->where('session_id', $sessionId);
                });
            })
            ->whereNotNull('year')
            ->select('year')
            ->distinct()
            ->orderBy('year', 'asc')
            ->pluck('year');
            
        return response()->json(['years' => $semesters]);
    }
    
    /**
     * Get semesters for a given session and year
     */
    public function sessionYearSemesters($sessionId, $year)
    {
        $semesters = Semester::where('status', 1)
            ->where('year', $year)
            ->whereHas('programs', function($q) use ($sessionId) {
                $q->whereHas('sessions', function($sq) use ($sessionId) {
                    $sq->where('session_id', $sessionId);
                });
            })
            ->orderBy('id', 'asc')
            ->get(['id', 'title']);
            
        return response()->json(['semesters' => $semesters]);
    }
    
    /**
     * Get programs for a given faculty
     */
    public function facultyPrograms($facultyId)
    {
        $programs = Program::where('faculty_id', $facultyId)
            ->where('status', 1)
            ->orderBy('title', 'asc')
            ->get(['id', 'title', 'shortcode']);
            
        return response()->json(['programs' => $programs]);
    }
}
