<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\StudentAssignment;
use App\Models\StudentEnroll;
use App\Models\Session;
use App\Models\Event;
use App\Models\Student;
use App\Models\Grade;
use App\Models\Notice;
use App\Models\ClassRoutine;
use App\Models\ExamRoutine;
use App\Models\Fee;
use App\Models\PaymentPlan;
use App\Models\IssueReturn;
use App\Models\EBook;
use App\Models\StudentAttendance;
use App\Models\SubjectMarking;
use App\Services\GraduationEligibilityService;
use App\Services\Academic\SemesterProgressionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    protected $title, $route, $view, $path;
    
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // Module Data
        $this->title    = trans_choice('module_dashboard', 1);
        $this->route    = 'student.dashboard';
        $this->view     = 'student.dashboard';
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $data['title']  = $this->title;
        $data['route']  = $this->route;
        $data['view']   = $this->view;


        $student_id = Auth::guard('student')->user()->id;
        $student = Student::with(['studentEnrolls.semester', 'studentEnrolls.subjectMarks.subject'])->find($student_id);
        $data['student'] = $student;
        
        // Get selected enrollment from session (set by SelectEnrollmentMiddleware)
        $selectedEnrollmentId = session('selected_enrollment_id');
        
        // Use selected enrollment as current enrollment
        $enroll = null;
        if($selectedEnrollmentId) {
            $enroll = StudentEnroll::where('id', $selectedEnrollmentId)
                            ->where('student_id', $student_id)
                            ->first();
        }
        
        // Fallback: If no enrollment found via session, try current session (backward compatibility)
        if(!$enroll) {
            $current_session = Session::where('status', '1')->where('current', '1')->first();
            if(isset($current_session)){
                $enroll = StudentEnroll::where('student_id', $student_id)
                                ->where('session_id', $current_session->id)
                                ->orderBy('id', 'desc')
                                ->first();
            }
        }

        // Initialize variables
        $session = null;
        $semester = null;
        
        if(isset($enroll)){
            $session = $enroll->session_id;
            $semester = $enroll->semester_id;
            $data['currentEnroll'] = $enroll;
        } else {
            $data['currentEnroll'] = null;
        }


        // ========== ACADEMIC PERFORMANCE ==========
        $grades = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();
        $data['grades'] = $grades;
        
        // Calculate CGPA and credits for selected enrollment's program only
        // IMPORTANT: Match transcript calculation - count ALL courses with published grades
        $totalCgpa = 0;
        $totalCreditsAttempted = 0; // For CGPA calculation (all courses)
        $totalCreditsEarned = 0; // For display (only passed courses)
        $courseStatus = []; // Track unique courses: subject_id => ['passed' => bool, 'marks' => float]
        
        // Get program_id from selected enrollment
        $selectedProgramId = $enroll ? $enroll->program_id : null;
        
        if ($student && $selectedProgramId) {
            foreach ($student->studentEnrolls as $enrollRecord) {
                // Only include enrollments from the same program
                if ($enrollRecord->program_id != $selectedProgramId) {
                    continue;
                }
                
                $isResitSemester = $enrollRecord->semester && $enrollRecord->semester->is_resit;
                
                if (isset($enrollRecord->subjectMarks)) {
                    foreach ($enrollRecord->subjectMarks as $mark) {
                        // Only count published marks (same as transcript)
                        if (!$mark->is_visible_to_student) {
                            continue;
                        }
                        
                        // Check publish date/time (same logic as transcript)
                        $publishDate = $mark->publish_date instanceof \Carbon\Carbon ? 
                                      $mark->publish_date->format('Y-m-d') : 
                                      date('Y-m-d', strtotime($mark->publish_date));
                        $publishTime = $mark->publish_time instanceof \Carbon\Carbon ? 
                                      $mark->publish_time->format('H:i:s') : 
                                      date('H:i:s', strtotime($mark->publish_time));
                        $currentDate = date('Y-m-d');
                        $currentTime = date('H:i:s');
                        
                        $isPublished = ($publishDate == $currentDate && $publishTime <= $currentTime) || 
                                      $publishDate < $currentDate;
                        
                        if (!$isPublished || !isset($mark->subject)) {
                            continue;
                        }
                        
                        $subjectId = $mark->subject_id;
                        $marksPer = round($mark->total_marks);
                        $creditHour = (float) $mark->subject->credit_hour;
                        $isPassed = $marksPer >= 50;
                        
                        // Track unique courses for pass/fail status (for display metrics)
                        if (!isset($courseStatus[$subjectId])) {
                            // First time seeing this course
                            $courseStatus[$subjectId] = [
                                'passed' => $isPassed,
                                'marks' => $marksPer,
                                'credits' => $creditHour,
                                'is_resit' => $isResitSemester
                            ];
                        } else {
                            // Course already exists (this is a resit)
                            // Update if this attempt was better or passed
                            if ($isPassed && !$courseStatus[$subjectId]['passed']) {
                                // Student passed the resit - update status
                                $courseStatus[$subjectId]['passed'] = true;
                                $courseStatus[$subjectId]['marks'] = $marksPer;
                                $courseStatus[$subjectId]['is_resit'] = $isResitSemester;
                            }
                        }
                        
                        // For CGPA calculation: Include ALL courses (same as transcript)
                        // Count all courses including F grades - this matches transcript logic
                        foreach ($grades as $grade) {
                            if ($marksPer >= $grade->min_mark && $marksPer <= $grade->max_mark) {
                                $gradePoint = (float) $grade->point;
                                
                                // Count ALL courses (including failed) for CGPA - matches transcript
                                $totalCgpa += $gradePoint * $creditHour;
                                $totalCreditsAttempted += $creditHour;
                                break;
                            }
                        }
                    }
                }
            }
        }
        
        // Count total and passed courses from unique courses
        $totalCourses = count($courseStatus);
        $passedCourses = count(array_filter($courseStatus, function($course) {
            return $course['passed'];
        }));
        
        // Calculate credits earned (only passed courses)
        foreach ($courseStatus as $course) {
            if ($course['passed']) {
                $totalCreditsEarned += $course['credits'];
            }
        }
        
        $data['cgpa'] = $totalCreditsAttempted > 0 ? $totalCgpa / $totalCreditsAttempted : 0;
        $data['totalCredits'] = $totalCreditsEarned; // Display earned credits
        $data['totalCourses'] = $totalCourses;
        $data['passedCourses'] = $passedCourses;
        $data['failedCourses'] = $totalCourses - $passedCourses;
        
        // Graduation eligibility - use selected enrollment's program if available
        $programIdToCheck = ($enroll && $enroll->program_id) ? $enroll->program_id : $student->program_id;
        if ($student && $programIdToCheck) {
            $graduationService = new GraduationEligibilityService();
            $eligibility = $graduationService->checkEligibility($student, $programIdToCheck);
            $data['graduationEligibility'] = $eligibility;
        }

        // ========== COMPREHENSIVE COURSE BREAKDOWN ==========
        $courseBreakdown = [];
        if ($student && $selectedProgramId) {
            foreach ($student->studentEnrolls()->with(['semester', 'session', 'subjects', 'subjectMarks.subject', 'resitRequests'])->orderBy('semester_id')->get() as $enrollRecord) {
                if ($enrollRecord->program_id != $selectedProgramId) {
                    continue;
                }
                
                $semester = $enrollRecord->semester;
                if (!$semester) continue;
                
                $semesterKey = $semester->id;
                if (!isset($courseBreakdown[$semesterKey])) {
                    $courseBreakdown[$semesterKey] = [
                        'semester' => $semester,
                        'session' => $enrollRecord->session,
                        'is_resit' => $semester->is_resit,
                        'courses' => []
                    ];
                }
                
                foreach ($enrollRecord->subjects as $subject) {
                    $subjectId = $subject->id;
                    
                    // Get marks for this subject
                    $markData = null;
                    $isValidated = false;
                    $grade = null;
                    $gradePoint = null;
                    
                    foreach ($enrollRecord->subjectMarks as $mark) {
                        if ($mark->subject_id == $subjectId) {
                            // Only show marks that have been published (same check as CGPA section)
                            if (!$mark->is_visible_to_student) {
                                continue;
                            }
                            
                            $publishDate = $mark->publish_date instanceof \Carbon\Carbon ? 
                                          $mark->publish_date->format('Y-m-d') : 
                                          date('Y-m-d', strtotime($mark->publish_date));
                            $publishTime = $mark->publish_time instanceof \Carbon\Carbon ? 
                                          $mark->publish_time->format('H:i:s') : 
                                          date('H:i:s', strtotime($mark->publish_time));
                            $currentDate = date('Y-m-d');
                            $currentTime = date('H:i:s');
                            
                            $isPublished = ($publishDate == $currentDate && $publishTime <= $currentTime) || 
                                          $publishDate < $currentDate;
                            
                            if (!$isPublished) {
                                continue;
                            }
                            
                            $marksPer = round($mark->total_marks);
                            $isValidated = true;
                            
                            foreach ($grades as $g) {
                                if ($marksPer >= $g->min_mark && $marksPer <= $g->max_mark) {
                                    $grade = $g->title;
                                    $gradePoint = $g->point;
                                    break;
                                }
                            }
                            
                            $markData = [
                                'marks' => $marksPer,
                                'grade' => $grade,
                                'grade_point' => $gradePoint,
                                'passed' => $marksPer >= 50
                            ];
                            break;
                        }
                    }
                    
                    // Check for resit requests
                    $resitInfo = null;
                    $attempts = 1;
                    if ($enrollRecord->resitRequests) {
                        foreach ($enrollRecord->resitRequests as $resitRequest) {
                            if ($resitRequest->subject_id == $subjectId) {
                                $attempts++;
                                $resitInfo = [
                                    'status' => $resitRequest->status,
                                    'resit_semester_id' => $resitRequest->resit_semester_id
                                ];
                            }
                        }
                    }
                    
                    // Get class routine (scheduled classes) - query directly by matching criteria
                    $classRoutines = [];
                    $routines = ClassRoutine::where('subject_id', $subjectId)
                        ->where('semester_id', $enrollRecord->semester_id)
                        ->where('session_id', $enrollRecord->session_id)
                        ->where('program_id', $enrollRecord->program_id)
                        ->when($enrollRecord->section_id, function($query) use ($enrollRecord) {
                            return $query->where('section_id', $enrollRecord->section_id);
                        })
                        ->with(['teacher', 'room'])
                        ->get();
                    
                    foreach ($routines as $routine) {
                        $classRoutines[] = [
                            'day' => $routine->day,
                            'start_time' => $routine->start_time,
                            'end_time' => $routine->end_time,
                            'room' => $routine->room ? $routine->room->title : null,
                            'teacher' => $routine->teacher ? $routine->teacher->name : null
                        ];
                    }
                    
                    $courseBreakdown[$semesterKey]['courses'][] = [
                        'id' => $subjectId,
                        'code' => $subject->code,
                        'name' => $subject->title,
                        'credits' => $subject->credit_hour,
                        'type' => $subject->subject_type, // 0=Optional, 1=Compulsory, 2=University Requirement
                        'marks' => $markData,
                        'validated' => $isValidated,
                        'attempts' => $attempts,
                        'resit_info' => $resitInfo,
                        'class_routines' => $classRoutines
                    ];
                }
            }
        }
        $data['courseBreakdown'] = $courseBreakdown;

        // ========== ASSIGNMENTS ==========
        if(isset($enroll) && $session && $semester){
            $assignments = StudentAssignment::with('studentEnroll')->whereHas('studentEnroll', function ($query) use ($student_id, $session, $semester){
                $query->where('student_id', $student_id);
                $query->where('session_id', $session);
                $query->where('semester_id', $semester);
            });
            $assignments->with('assignment')->whereHas('assignment', function ($query){
                $query->where('start_date', '<=', Carbon::today());
            });

            $data['assignments'] = $assignments->orderBy('id', 'desc')->limit(5)->get();
            
            // Pending assignments count
            $data['pendingAssignments'] = StudentAssignment::with('studentEnroll')->whereHas('studentEnroll', function ($query) use ($student_id, $session, $semester){
                $query->where('student_id', $student_id);
                $query->where('session_id', $session);
                $query->where('semester_id', $semester);
            })->where('attendance', '!=', 1)->count();
        } else {
            $data['assignments'] = collect();
            $data['pendingAssignments'] = 0;
        }


        // ========== CLASS ROUTINE ==========
        if(isset($enroll)){
            $today = Carbon::now()->format('l');
            $data['todayClasses'] = ClassRoutine::where('semester_id', $enroll->semester_id)
                ->where('section_id', $enroll->section_id)
                ->where('day', strtolower($today))
                ->where('status', '1')
                ->orderBy('start_time', 'asc')
                ->limit(5)
                ->get();
        } else {
            $data['todayClasses'] = collect();
        }


        // ========== ATTENDANCE ==========
        if(isset($enroll) && $session && $semester){
            $totalAttendance = StudentAttendance::whereHas('studentEnroll', function($query) use ($student_id, $session, $semester){
                $query->where('student_id', $student_id);
                $query->where('session_id', $session);
                $query->where('semester_id', $semester);
            })->count();
            
            $presentAttendance = StudentAttendance::whereHas('studentEnroll', function($query) use ($student_id, $session, $semester){
                $query->where('student_id', $student_id);
                $query->where('session_id', $session);
                $query->where('semester_id', $semester);
            })->where('attendance', '1')->count();
            
            $data['attendancePercentage'] = $totalAttendance > 0 ? ($presentAttendance / $totalAttendance) * 100 : 0;
            $data['totalAttendance'] = $totalAttendance;
            $data['presentAttendance'] = $presentAttendance;
        } else {
            $data['attendancePercentage'] = 0;
            $data['totalAttendance'] = 0;
            $data['presentAttendance'] = 0;
        }


        // ========== EXAM RESULTS ==========
        if(isset($enroll)){
            $data['latestMarks'] = SubjectMarking::where('student_enroll_id', $enroll->id)
                ->where(function($query) {
                    $query->where(function($q) {
                        // Case 1: No override (NULL), follow workflow
                        $q->whereNull('is_published_override')
                          ->where('workflow_state', SubjectMarking::STATE_PUBLISHED);
                    })->orWhere(function($q) {
                        // Case 2: Override is TRUE (force published)
                        $q->where('is_published_override', true);
                    });
                    // Case 3: Override is FALSE (force unpublished) - excluded automatically
                })
                ->with('subject')
                ->orderBy('id', 'desc')
                ->limit(5)
                ->get();
        } else {
            $data['latestMarks'] = collect();
        }


        // ========== FEES & PAYMENTS ==========
        // Get all enrollment IDs for the student
        $enrollmentIds = StudentEnroll::where('student_id', $student_id)->pluck('id')->toArray();
        
        $data['totalFees'] = Fee::whereIn('student_enroll_id', $enrollmentIds)->sum('fee_amount');
        // Cap each fee's paid contribution at its fee_amount so an overpayment
        // (already extracted as a StudentCredit and possibly applied to another
        // fee) is not double-counted, which would make dueFees negative.
        $data['paidFees'] = (float) Fee::whereIn('student_enroll_id', $enrollmentIds)
            ->selectRaw('COALESCE(SUM(LEAST(paid_amount, fee_amount)), 0) AS s')
            ->value('s');
        $data['dueFees'] = max(0, $data['totalFees'] - $data['paidFees']);
        
        $data['upcomingPayments'] = PaymentPlan::where('student_id', $student_id)
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();


        // ========== NOTICES ==========
        $data['latestNotices'] = Notice::where('status', '1')
            ->where('date', '<=', Carbon::today())
            ->with('students')
            ->whereHas('students', function ($query) use ($student_id){
                $query->where('noticeable_id', $student_id);
                $query->where('noticeable_type', 'App\Models\Student');
            })
            ->orderBy('date', 'desc')
            ->limit(5)
            ->get();


        // ========== EXAM ROUTINE ==========
        if(isset($enroll)){
            $data['upcomingExams'] = ExamRoutine::where('semester_id', $enroll->semester_id)
                ->where('section_id', $enroll->section_id)
                ->where('date', '>=', Carbon::today())
                ->where('status', '1')
                ->orderBy('date', 'asc')
                ->limit(5)
                ->get();
        } else {
            $data['upcomingExams'] = collect();
        }


        // ========== LIBRARY ==========
        $student = Student::where('id', $student_id)->first();
        if(isset($student->member)){
            $data['borrowedBooks'] = IssueReturn::where('member_id', $student->member->id)
                ->where('return_date', null) // Books not yet returned
                ->count();
        } else {
            $data['borrowedBooks'] = 0;
        }


        // ========== E-LIBRARY ==========
        $data['recentEBooks'] = EBook::where('status', '1')
            ->orderBy('id', 'desc')
            ->limit(4)
            ->get();



        // Events
        $data['events'] = Event::where('status', '1')->orderBy('id', 'asc')->get();

        $data['latest_events'] = Event::where('status', '1')
                            ->where('end_date', '>=', Carbon::today())
                            ->orderBy('start_date', 'asc')
                            ->limit(10)
                            ->get();


        // ========== PROGRESSION STATUS ==========
        $data['progressionStatus'] = null;
        if (isset($enroll)) {
            $progressionService = new SemesterProgressionService();
            $eligibility = $progressionService->checkProgressionEligibility($enroll);
            
            if (!$eligibility['eligible'] && $eligibility['reason'] === 'Cannot progress to next Academic Year yet. Waiting for new Academic Session to be activated.') {
                $data['progressionStatus'] = [
                    'status' => 'waiting_for_session',
                    'message' => $eligibility['reason']
                ];
            }
        }

        return view($this->view.'.index', $data);
    }
}
