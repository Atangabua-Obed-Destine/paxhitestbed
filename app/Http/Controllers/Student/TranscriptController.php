<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\Student;
use App\Models\Grade;

class TranscriptController extends Controller
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
        $this->title    = trans_choice('module_transcript', 1);
        $this->route    = 'student.transcript';
        $this->view     = 'student.transcript';
        $this->path     = 'transcript';
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
        $data['title']     = $this->title;
        $data['route']     = $this->route;
        $data['view']      = $this->view;
        $data['path']      = $this->path;

        $data['row'] = Student::where('id', Auth::guard('student')->user()->id)
                        ->with([
                            'batch',
                            'program',
                            'studentEnrolls.session',
                            'studentEnrolls.semester',
                            'studentEnrolls.section',
                            'studentEnrolls.subjects',
                            'studentEnrolls.subjectMarks.subject'
                        ])
                        ->first();
        $data['grades'] = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();

        // Get selected program ID from session (set by SelectEnrollmentMiddleware)
        $selectedEnrollmentId = session('selected_enrollment_id');
        $selectedProgramId = null;
        
        if ($selectedEnrollmentId) {
            $currentEnroll = \App\Models\StudentEnroll::where('id', $selectedEnrollmentId)
                ->where('student_id', $data['row']->id)
                ->first();
            $selectedProgramId = $currentEnroll ? $currentEnroll->program_id : null;
        }
        
        // Fallback to student's main program if no selection
        if (!$selectedProgramId) {
            $selectedProgramId = $data['row']->program_id;
        }

        // Prepare GPA trend data for visualization (filtered by selected program)
        $data['gpa_trend'] = $this->prepareGPATrendData($data['row'], $data['grades'], $selectedProgramId, $blockedSemesters ?? []);

        // Build active result-access block lookup so the view + GPA trend can
        // exclude semesters whose results have been withheld by admin.
        $blockedSemesters = [];
        if ($data['row'] && $selectedProgramId) {
            $sids = $data['row']->studentEnrolls->where('program_id', $selectedProgramId)->pluck('session_id')->unique()->values();
            $semids = $data['row']->studentEnrolls->where('program_id', $selectedProgramId)->pluck('semester_id')->unique()->values();
            if ($sids->isNotEmpty() && $semids->isNotEmpty()) {
                $blocks = \App\Models\ResultAccessBlock::query()
                    ->where('student_id', $data['row']->id)
                    ->where('program_id', $selectedProgramId)
                    ->where('is_active', true)
                    ->whereIn('session_id', $sids)
                    ->whereIn('semester_id', $semids)
                    ->get();
                foreach ($blocks as $b) {
                    $blockedSemesters[$b->session_id . '|' . $b->semester_id] = $b;
                }
            }
        }
        $data['blockedSemesters'] = $blockedSemesters;
        $data['selectedProgramId'] = $selectedProgramId;

        // Recompute trend now that we know which semesters are blocked
        $data['gpa_trend'] = $this->prepareGPATrendData($data['row'], $data['grades'], $selectedProgramId, $blockedSemesters);

        return view($this->view.'.index', $data);
    }

    /**
     * Prepare GPA trend data for Chart.js visualization
     * Filters by selected program to show only relevant data
     */
    private function prepareGPATrendData($student, $grades, $selectedProgramId = null, array $blockedSemesters = [])
    {
        $trend_data = [];
        $cumulative_quality_points = 0;
        $cumulative_cgpa_credits = 0; // For CGPA calculation (includes all attempts/retakes)
        $cumulative_unique_courses = []; // Track unique courses ACROSS ALL semesters

        // Get unique semester combinations (filtered by program)
        $semester_items = [];
        $semester_keys = [];

        foreach ($student->studentEnrolls as $enroll) {
            // CRITICAL: Filter by selected program
            if ($selectedProgramId && $enroll->program_id != $selectedProgramId) {
                continue;
            }
            // Skip semesters whose results have been blocked by admin
            if (isset($blockedSemesters[$enroll->session_id . '|' . $enroll->semester_id])) {
                continue;
            }
            
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
            $semester_quality_points = 0;
            $semester_cgpa_credits = 0; // For CGPA calculation (includes retakes)
            $semester_unique_courses = []; // Track unique courses: subject_id => ['credits' => float, 'passed' => bool]

            foreach ($student->studentEnrolls as $enroll) {
                // CRITICAL: Filter by selected program
                if ($selectedProgramId && $enroll->program_id != $selectedProgramId) {
                    continue;
                }
                
                if (isset($enroll->semester) && isset($enroll->session) &&
                    $semester_item['semester'] == $enroll->semester->title &&
                    $semester_item['session'] == $enroll->session->title) {

                    if (isset($enroll->subjectMarks)) {
                        foreach ($enroll->subjectMarks as $mark) {
                            // Check if marks are published and visible (using model accessor)
                            $publishDate = $mark->publish_date instanceof \Carbon\Carbon ? 
                                          $mark->publish_date->format('Y-m-d') : 
                                          date('Y-m-d', strtotime($mark->publish_date));
                            $publishTime = $mark->publish_time instanceof \Carbon\Carbon ? 
                                          $mark->publish_time->format('H:i:s') : 
                                          date('H:i:s', strtotime($mark->publish_time));
                            $currentDate = date('Y-m-d');
                            $currentTime = date('H:i:s');

                            // Use is_visible_to_student accessor which handles is_published_override
                            $isVisible = $mark->is_visible_to_student && 
                                        (($publishDate == $currentDate && $publishTime <= $currentTime) || 
                                         $publishDate < $currentDate);

                            if ($isVisible && isset($mark->subject)) {
                                $marks_per = round($mark->total_marks);
                                $credit_hour = (float) $mark->subject->credit_hour;
                                $subject_id = $mark->subject_id;

                                foreach ($grades as $grade) {
                                    if ($marks_per >= $grade->min_mark && $marks_per <= $grade->max_mark) {
                                        $grade_point = (float) $grade->point;
                                        $quality_points = $grade_point * $credit_hour;
                                        $is_passed = $marks_per >= 50;

                                        // CGPA calculation: count ALL attempts including retakes
                                        $semester_cgpa_credits += $credit_hour;
                                        $semester_quality_points += $quality_points;
                                        
                                        // Track unique courses for Credits Attempted/Earned
                                        if (!isset($semester_unique_courses[$subject_id])) {
                                            // First time seeing this course in this semester
                                            $semester_unique_courses[$subject_id] = [
                                                'credits' => $credit_hour,
                                                'passed' => $is_passed
                                            ];
                                        } else {
                                            // Course retake in same semester - update pass status if passed
                                            if ($is_passed && !$semester_unique_courses[$subject_id]['passed']) {
                                                $semester_unique_courses[$subject_id]['passed'] = true;
                                            }
                                        }
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Calculate unique credits attempted and earned for this semester
            $semester_credits_attempted = 0;
            $semester_credits_earned = 0;
            foreach ($semester_unique_courses as $course) {
                $semester_credits_attempted += $course['credits'];
                if ($course['passed']) {
                    $semester_credits_earned += $course['credits'];
                }
            }

            // Calculate semester GPA (uses CGPA credits which includes retakes)
            $semester_gpa = $semester_cgpa_credits > 0 ? $semester_quality_points / $semester_cgpa_credits : 0;

            // Update cumulative values
            $cumulative_quality_points += $semester_quality_points;
            $cumulative_cgpa_credits += $semester_cgpa_credits;
            
            // Update cumulative unique courses ACROSS ALL semesters
            foreach ($semester_unique_courses as $subject_id => $course_data) {
                if (!isset($cumulative_unique_courses[$subject_id])) {
                    // First time seeing this course across all semesters
                    $cumulative_unique_courses[$subject_id] = [
                        'credits' => $course_data['credits'],
                        'passed' => $course_data['passed']
                    ];
                } else {
                    // Course was attempted in a previous semester - update pass status if passed
                    if ($course_data['passed'] && !$cumulative_unique_courses[$subject_id]['passed']) {
                        $cumulative_unique_courses[$subject_id]['passed'] = true;
                    }
                }
            }
            
            // Calculate cumulative unique credits attempted and earned
            $cumulative_credits_attempted = 0;
            $cumulative_credits_earned = 0;
            foreach ($cumulative_unique_courses as $course) {
                $cumulative_credits_attempted += $course['credits'];
                if ($course['passed']) {
                    $cumulative_credits_earned += $course['credits'];
                }
            }
            
            // Calculate cumulative GPA (uses CGPA credits which includes retakes)
            $cumulative_gpa = $cumulative_cgpa_credits > 0 ? $cumulative_quality_points / $cumulative_cgpa_credits : 0;

            // Store data point (only if semester has credits to avoid empty semesters in chart)
            if ($semester_credits_attempted > 0) {
                $trend_data[] = [
                    'label' => $semester_item['session'] . ' - ' . $semester_item['semester'],
                    'semester_gpa' => round($semester_gpa, 2),
                    'cumulative_gpa' => round($cumulative_gpa, 2),
                    'credits_attempted' => round($semester_credits_attempted, 2),
                    'credits_earned' => round($semester_credits_earned, 2),
                    'cumulative_credits_attempted' => round($cumulative_credits_attempted, 2),
                    'cumulative_credits_earned' => round($cumulative_credits_earned, 2)
                ];
            }
        }

        return $trend_data;
    }
}
