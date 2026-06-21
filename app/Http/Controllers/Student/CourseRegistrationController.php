<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\ProgramSessionMaxCredit;
use App\Models\Student;
use App\Models\StudentEnroll;
use App\Models\Subject;
use App\Http\Controllers\Student\FormA3Controller;
use Flasher\Laravel\Facade\Flasher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CourseRegistrationController extends Controller
{
    protected string $title;
    protected string $route;
    protected string $view;

    public function __construct()
    {
        $this->title = __('Course Registration');
        $this->route = 'student.course-registration';
        $this->view = 'student.course-registration';
    }

    public function index(Request $request)
    {
        /** @var Student|null $student */
        $student = Auth::guard('student')->user();
        if (!$student instanceof Student) {
            abort(403);
        }

        $student->load([
            'batch',
            'program',
            'studentEnrolls.subjectMarks.subject',
            'studentEnrolls.subjects',
            'studentEnrolls.session',
            'studentEnrolls.semester',
            'studentEnrolls.section',
        ]);

        // Get selected enrollment from session (set by SelectEnrollmentMiddleware)
        $selectedEnrollmentId = session('selected_enrollment_id');
        
        $currentEnroll = null;
        if($selectedEnrollmentId) {
            $currentEnroll = StudentEnroll::with([
                'subjects',
                'subjectMarks.subject',
                'session',
                'semester',
                'section',
                'program',
            ])->where('id', $selectedEnrollmentId)
              ->where('student_id', $student->id)
              ->first();
        }
        
        // Fallback to currentEnroll relationship if no selection
        if(!$currentEnroll) {
            $student->load([
                'currentEnroll.subjects',
                'currentEnroll.subjectMarks.subject',
                'currentEnroll.session',
                'currentEnroll.semester',
                'currentEnroll.section',
                'currentEnroll.program',
            ]);
            $currentEnroll = $student->currentEnroll;
        }

        // Check if current semester is a resit semester
        $isResitSemester = false;
        if ($currentEnroll && $currentEnroll->semester) {
            $isResitSemester = (bool) $currentEnroll->semester->is_resit;
        }

        $availableSubjects = collect();
        if ($currentEnroll && !$isResitSemester && $student->program_id) {
            $currentSemester = $currentEnroll->semester;
            $currentYear = $currentSemester->year;
            $currentSemesterType = $currentSemester->semester_type ?? 1;

            // Get subjects for current semester (program + semester + section)
            $enrollSubject = \App\Models\EnrollSubject::where('program_id', $currentEnroll->program_id)
                ->where('semester_id', $currentEnroll->semester_id)
                ->where('section_id', $currentEnroll->section_id)
                ->first();

            if ($enrollSubject) {
                // Get subjects assigned to this enrollment
                $currentSemesterSubjectIds = $enrollSubject->subjects()->pluck('subject_id')->toArray();
            } else {
                $currentSemesterSubjectIds = [];
            }

            // Get subjects from same semester type but previous years
            $previousYearsSemesterIds = \App\Models\Semester::where('status', 1)
                ->where('semester_type', $currentSemesterType)
                ->where('year', '<=', $currentYear)
                ->where('id', '!=', $currentEnroll->semester_id)
                ->pluck('id')
                ->toArray();

            $previousYearsSubjectIds = [];
            if (!empty($previousYearsSemesterIds)) {
                $previousEnrollSubjects = \App\Models\EnrollSubject::where('program_id', $currentEnroll->program_id)
                    ->whereIn('semester_id', $previousYearsSemesterIds)
                    ->where('section_id', $currentEnroll->section_id)
                    ->get();

                foreach ($previousEnrollSubjects as $enrollSub) {
                    $subjectIds = $enrollSub->subjects()->pluck('subject_id')->toArray();
                    $previousYearsSubjectIds = array_merge($previousYearsSubjectIds, $subjectIds);
                }
            }

            // Combine all eligible subject IDs
            $allEligibleSubjectIds = array_unique(array_merge($currentSemesterSubjectIds, $previousYearsSubjectIds));

            if (!empty($allEligibleSubjectIds)) {
                // Get all subjects
                $allSubjects = Subject::whereIn('id', $allEligibleSubjectIds)
                    ->where('status', '1')
                    ->orderBy('code', 'asc')
                    ->get();

                // Filter out subjects that have been validated (total_marks >= 50%)
                // Only check enrollments for the CURRENT PROGRAM and only count PUBLISHED marks
                $validatedSubjectIds = [];
                foreach ($student->studentEnrolls as $enroll) {
                    // Only check enrollments for the current program
                    if ($enroll->program_id != $currentEnroll->program_id) {
                        continue;
                    }
                    
                    if (isset($enroll->subjectMarks)) {
                        foreach ($enroll->subjectMarks as $mark) {
                            // Only count published marks
                            if ($mark->is_visible_to_student && $mark->total_marks >= 50) {
                                $validatedSubjectIds[] = $mark->subject_id;
                            }
                        }
                    }
                }
                $validatedSubjectIds = array_unique($validatedSubjectIds);

                // Filter available subjects (exclude validated ones)
                $availableSubjects = $allSubjects->reject(function ($subject) use ($validatedSubjectIds) {
                    return in_array($subject->id, $validatedSubjectIds);
                })->values();
            }
        }

        $grades = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();
        $selectedSubjectIds = $currentEnroll
            ? $currentEnroll->subjects->pluck('id')->map(fn ($id) => (string) $id)->toArray()
            : [];
        $assignedSubjectIds = array_map('intval', $selectedSubjectIds);
        $assignableSubjects = $availableSubjects->reject(function ($subject) use ($assignedSubjectIds) {
            return in_array((int) $subject->id, $assignedSubjectIds, true);
        })->values();


        $maxCreditLimit = null;
        if ($currentEnroll) {
            $facultyId = $currentEnroll->program->faculty_id ?? null;
            $maxCreditLimit = ProgramSessionMaxCredit::resolveLimit(
                (int) $currentEnroll->program_id,
                (int) $currentEnroll->session_id,
                $facultyId ? (int) $facultyId : null
            );
        }

        // Prepare performance summary and carry-over courses data
        $performanceSummary = $this->preparePerformanceSummary($student, $currentEnroll, $grades);
        $carryOverCourses = $this->prepareCarryOverCourses($student, $currentEnroll, $grades);

        // Get subjects enrolled for the current semester (from enroll_subject table)
        $currentSemesterEnrolledSubjectIds = [];
        if ($currentEnroll) {
            $enrollSubject = \App\Models\EnrollSubject::where('program_id', $currentEnroll->program_id)
                ->where('semester_id', $currentEnroll->semester_id)
                ->where('section_id', $currentEnroll->section_id)
                ->first();
            
            if ($enrollSubject) {
                $currentSemesterEnrolledSubjectIds = $enrollSubject->subjects()->pluck('subject_id')->toArray();
            }
        }

        return view($this->view . '.index', [
            'title' => $this->title,
            'route' => $this->route,
            'view' => $this->view,
            'student' => $student,
            'currentEnroll' => $currentEnroll,
            'assignableSubjects' => $assignableSubjects,
            'grades' => $grades,
            'selectedSubjectIds' => $selectedSubjectIds,
            'maxCreditLimit' => $maxCreditLimit,
            'isResitSemester' => $isResitSemester,
            'performanceSummary' => $performanceSummary,
            'carryOverCourses' => $carryOverCourses,
            'currentSemesterEnrolledSubjectIds' => $currentSemesterEnrolledSubjectIds,
        ]);
    }

    public function update(Request $request)
    {
        /** @var Student|null $student */
        $student = Auth::guard('student')->user();
        if (!$student instanceof Student) {
            abort(403);
        }

        $currentEnroll = StudentEnroll::where('student_id', $student->id)
            ->where('status', '1')
            ->orderByDesc('id')
            ->first();

        if (!$currentEnroll) {
            Flasher::addWarning(__('You are not currently enrolled in any semester.'), __('msg_warning'));
            return redirect()->route($this->route . '.index');
        }

        // Check if current semester is a resit semester
        $currentEnroll->load('semester');
        if ($currentEnroll->semester && $currentEnroll->semester->is_resit) {
            Flasher::addError(__('Course registration is not allowed during resit semesters.'), __('msg_error'));
            return redirect()->route($this->route . '.index');
        }

        $currentEnroll->loadMissing('program');

        $validated = $request->validate([
            'subjects' => ['required', 'array', 'min:1'],
            'subjects.*' => ['integer', 'distinct'],
        ]);

        $subjectIds = array_values(array_unique(array_map('intval', $validated['subjects'])));

        // Get allowed subjects based on semester type and validation status
        $currentSemester = $currentEnroll->semester;
        $currentYear = $currentSemester->year;
        $currentSemesterType = $currentSemester->semester_type ?? 1;

        // Get subjects for current semester enrollment
        $enrollSubject = \App\Models\EnrollSubject::where('program_id', $currentEnroll->program_id)
            ->where('semester_id', $currentEnroll->semester_id)
            ->where('section_id', $currentEnroll->section_id)
            ->first();

        $currentSemesterSubjectIds = $enrollSubject ? $enrollSubject->subjects()->pluck('subject_id')->toArray() : [];

        // Get subjects from same semester type but previous years
        $previousYearsSemesterIds = \App\Models\Semester::where('status', 1)
            ->where('semester_type', $currentSemesterType)
            ->where('year', '<=', $currentYear)
            ->where('id', '!=', $currentEnroll->semester_id)
            ->pluck('id')
            ->toArray();

        $previousYearsSubjectIds = [];
        if (!empty($previousYearsSemesterIds)) {
            $previousEnrollSubjects = \App\Models\EnrollSubject::where('program_id', $currentEnroll->program_id)
                ->whereIn('semester_id', $previousYearsSemesterIds)
                ->where('section_id', $currentEnroll->section_id)
                ->get();

            foreach ($previousEnrollSubjects as $enrollSub) {
                $subjectIdsFromEnroll = $enrollSub->subjects()->pluck('subject_id')->toArray();
                $previousYearsSubjectIds = array_merge($previousYearsSubjectIds, $subjectIdsFromEnroll);
            }
        }

        $allEligibleSubjectIds = array_unique(array_merge($currentSemesterSubjectIds, $previousYearsSubjectIds));

        // Filter out validated subjects (marks >= 50%)
        // Only check enrollments for the CURRENT PROGRAM and only count PUBLISHED marks
        $student->load('studentEnrolls.subjectMarks');
        $validatedSubjectIds = [];
        foreach ($student->studentEnrolls as $enroll) {
            // Only check enrollments for the current program
            if ($enroll->program_id != $currentEnroll->program_id) {
                continue;
            }
            
            if (isset($enroll->subjectMarks)) {
                foreach ($enroll->subjectMarks as $mark) {
                    // Only count published marks
                    if ($mark->is_visible_to_student && $mark->total_marks >= 50) {
                        $validatedSubjectIds[] = $mark->subject_id;
                    }
                }
            }
        }
        $validatedSubjectIds = array_unique($validatedSubjectIds);

        // Remove validated subjects from eligible list
        $allowedSubjectIds = array_diff($allEligibleSubjectIds, $validatedSubjectIds);

        $invalidSelections = array_diff($subjectIds, $allowedSubjectIds);
        if (!empty($invalidSelections)) {
            return redirect()->back()->withErrors([
                'subjects' => __('One or more selected subjects are not available for registration. Only subjects from your current semester type and unvalidated courses are allowed.'),
            ])->withInput();
        }

        $alreadyAssignedIds = $currentEnroll->subjects()->pluck('subject_id')->map(fn ($id) => (int) $id)->toArray();
        $newSubjectIds = array_values(array_diff($subjectIds, $alreadyAssignedIds));

        if (empty($newSubjectIds)) {
            Flasher::addInfo(__('You are already registered for all of the selected subjects.'));
            return redirect()->route($this->route . '.index');
        }

        $facultyId = $currentEnroll->program->faculty_id ?? null;
        $maxCreditLimit = ProgramSessionMaxCredit::resolveLimit(
            (int) $currentEnroll->program_id,
            (int) $currentEnroll->session_id,
            $facultyId ? (int) $facultyId : null
        );

        if ($maxCreditLimit !== null) {
            $currentCredits = (int) $currentEnroll->subjects()->sum('credit_hour');
            $newCredits = (int) Subject::whereIn('id', $newSubjectIds)->sum('credit_hour');
            if (($currentCredits + $newCredits) > $maxCreditLimit) {
                return redirect()->back()->withInput()->withErrors([
                    'subjects' => __('Adding the selected subjects would exceed the maximum of :limit Credits for this semester.', [
                        'limit' => $maxCreditLimit,
                    ]),
                ]);
            }
        }

        $currentEnroll->subjects()->attach($newSubjectIds);

        // Generate/Update Form A3 after course registration
        try {
            // Reload the enrollment with all subjects to get accurate list
            $currentEnroll->load('subjects');
            $allRegisteredSubjects = $currentEnroll->subjects;
            
            FormA3Controller::generateFormA3($student, $currentEnroll, $allRegisteredSubjects);
        } catch (\Exception $e) {
            // Log error but don't fail the registration
            Log::error('Form A3 generation failed: ' . $e->getMessage());
        }

        Flasher::addSuccess(__('Selected subjects assigned successfully.'), __('msg_success'));

        return redirect()->route($this->route . '.index');
    }

    public function drop(Request $request)
    {
        /** @var Student|null $student */
        $student = Auth::guard('student')->user();
        if (!$student instanceof Student) {
            abort(403);
        }

        $currentEnroll = StudentEnroll::where('student_id', $student->id)
            ->where('status', '1')
            ->orderByDesc('id')
            ->first();

        if (!$currentEnroll) {
            Flasher::addWarning(__('You are not currently enrolled in any semester.'), __('msg_warning'));
            return redirect()->route($this->route . '.index');
        }

        // Check if current semester is a resit semester
        $currentEnroll->load('semester');
        if ($currentEnroll->semester && $currentEnroll->semester->is_resit) {
            Flasher::addError(__('Course changes are not allowed during resit semesters.'), __('msg_error'));
            return redirect()->route($this->route . '.index');
        }

        $validated = $request->validate([
            'subject_id' => ['required', 'integer'],
        ]);

        $subjectId = (int) $validated['subject_id'];

        $isAssigned = $currentEnroll->subjects()
            ->where('subject_id', $subjectId)
            ->exists();

        if (!$isAssigned) {
            Flasher::addWarning(__('The selected subject is not part of your current registration.'), __('msg_warning'));
            return redirect()->route($this->route . '.index');
        }

        // Get the subject to check its type
        $subject = \App\Models\Subject::find($subjectId);
        
        if (!$subject) {
            Flasher::addWarning(__('Subject not found.'), __('msg_warning'));
            return redirect()->route($this->route . '.index');
        }

        // Check if subject is compulsory or university requirement AND is assigned to this semester
        if ($subject->subject_type == 1 || $subject->subject_type == 2) {
            // Check if this subject is part of the semester's enrolled subjects
            $isEnrolledForSemester = \App\Models\EnrollSubject::where('program_id', $student->program_id)
                ->where('semester_id', $currentEnroll->semester_id)
                ->where('section_id', $currentEnroll->section_id)
                ->whereHas('subjects', function($query) use ($subjectId) {
                    $query->where('subject_id', $subjectId);
                })
                ->exists();
            
            if ($isEnrolledForSemester) {
                $subjectTypeName = $subject->subject_type == 1 ? __('subject_type_compulsory') : __('subject_type_university_requirement');
                Flasher::addError(__('You cannot drop this course because it is a') . ' ' . $subjectTypeName . ' ' . __('assigned to your current semester. These courses are mandatory for your program.'), __('msg_error'));
                return redirect()->route($this->route . '.index');
            }
        }

        // Check if marks have been submitted for this course
        $hasMarks = \App\Models\SubjectMarking::where('student_enroll_id', $currentEnroll->id)
            ->where('subject_id', $subjectId)
            ->exists();

        if ($hasMarks) {
            Flasher::addError(__('You cannot drop this course because marks have already been submitted for it. Please contact your administrator for assistance.'), __('msg_error'));
            return redirect()->route($this->route . '.index');
        }

        $currentEnroll->subjects()->detach($subjectId);

        Flasher::addSuccess(__('Subject dropped successfully.'), __('msg_success'));

        return redirect()->route($this->route . '.index');
    }

    /**
     * Prepare performance summary for the student
     */
    private function preparePerformanceSummary($student, $currentEnroll, $grades)
    {
        if (!$currentEnroll) {
            return null;
        }

        // Use GraduationEligibilityService for accurate program-wide analysis
        // Pass the current enrollment's program_id to get program-specific data
        $graduationService = app(\App\Services\GraduationEligibilityService::class);
        $programId = $currentEnroll->program_id;
        $eligibility = $graduationService->checkEligibility($student, $programId);
        $courseBreakdown = $graduationService->getCourseBreakdown($student, $programId);

        // Calculate CGPA and credits for current enrollment's program only
        // IMPORTANT: Only count PUBLISHED marks to match transcript behavior
        $totalCgpa = 0;
        $totalCredits = 0;
        $totalCoursesAttempted = 0;
        $totalCoursesPassed = 0;
        
        foreach ($student->studentEnrolls as $enroll) {
            // Only include enrollments from the same program as current enrollment
            if ($enroll->program_id != $currentEnroll->program_id) {
                continue;
            }
            
            if (isset($enroll->subjectMarks)) {
                foreach ($enroll->subjectMarks as $mark) {
                    if (!isset($mark->subject)) continue;
                    
                    // CRITICAL: Only count published marks
                    if (!$this->isMarkPublished($mark)) {
                        continue;
                    }
                    
                    $marksPer = round($mark->total_marks);
                    $creditHour = (float) $mark->subject->credit_hour;
                    $totalCoursesAttempted++;
                    
                    // Find grade
                    foreach ($grades as $grade) {
                        if ($marksPer >= $grade->min_mark && $marksPer <= $grade->max_mark) {
                            $gradePoint = (float) $grade->point;
                            $totalCgpa += $gradePoint * $creditHour;
                            $totalCredits += $creditHour;
                            
                            if ($marksPer >= 50) {
                                $totalCoursesPassed++;
                            }
                            break;
                        }
                    }
                }
            }
        }

        $cgpa = $totalCredits > 0 ? $totalCgpa / $totalCredits : 0;
        $completionPercentage = $totalCoursesAttempted > 0 ? ($totalCoursesPassed / $totalCoursesAttempted) * 100 : 0;

        // Determine performance status based on CGPA
        if ($cgpa >= 3.5) {
            $performanceStatus = 'excellent';
        } elseif ($cgpa >= 3.0) {
            $performanceStatus = 'very_good';
        } elseif ($cgpa >= 2.5) {
            $performanceStatus = 'good';
        } elseif ($cgpa >= 2.0) {
            $performanceStatus = 'satisfactory';
        } else {
            $performanceStatus = 'needs_improvement';
        }

        $summary = [
            // Basic metrics
            'total_credits_attempted' => $totalCredits,
            'total_credits_earned' => $eligibility['completed_credits'],
            'cgpa' => $cgpa,
            'total_courses' => $totalCoursesAttempted,
            'passed_courses' => $totalCoursesPassed,
            'failed_courses' => $totalCoursesAttempted - $totalCoursesPassed,
            'completion_percentage' => $completionPercentage,
            'performance_status' => $performanceStatus,
            
            // Program requirements breakdown (from GraduationEligibilityService)
            'compulsory_total' => $eligibility['compulsory']['total_subjects'],
            'compulsory_passed' => $eligibility['compulsory']['passed_subjects'],
            'compulsory_failed' => $eligibility['compulsory']['failed_subjects'],
            'compulsory_missing' => $eligibility['compulsory']['missing_subjects'],
            'compulsory_credits' => $eligibility['compulsory']['total_credits'],
            'compulsory_credits_earned' => $eligibility['compulsory']['completed_credits'],
            
            'university_req_total' => $eligibility['university_requirement']['total_subjects'],
            'university_req_passed' => $eligibility['university_requirement']['passed_subjects'],
            'university_req_failed' => $eligibility['university_requirement']['failed_subjects'],
            'university_req_missing' => $eligibility['university_requirement']['missing_subjects'],
            'university_req_credits' => $eligibility['university_requirement']['total_credits'],
            'university_req_credits_earned' => $eligibility['university_requirement']['completed_credits'],
            
            'optional_total' => $eligibility['optional']['total_subjects'],
            'optional_passed' => $eligibility['optional']['passed_subjects'],
            'optional_failed' => $eligibility['optional']['failed_subjects'],
            'optional_missing' => $eligibility['optional']['missing_subjects'],
            'optional_credits' => $eligibility['optional']['total_credits'],
            'optional_credits_earned' => $eligibility['optional']['completed_credits'],
            
            // Graduation status
            'graduation_ready' => $eligibility['is_eligible'],
            'graduation_reasons' => $eligibility['reasons'],
            
            // Additional context
            'current_year' => $currentEnroll->semester->year ?? 0,
            'semesters_completed' => $student->studentEnrolls
                ->where('program_id', $currentEnroll->program_id)
                ->where('status', '!=', '1')
                ->count(),
            
            // Detailed breakdown for potential use
            'course_breakdown' => $courseBreakdown,
        ];

        return $summary;
    }

    /**
     * Prepare carry-over courses (courses from same semester type, previous years, not yet validated)
     */
    private function prepareCarryOverCourses($student, $currentEnroll, $grades)
    {
        if (!$currentEnroll || !$currentEnroll->semester) {
            return [];
        }

        $carryOverCourses = [];
        $passedSubjects = []; // Track subjects that have been passed (including resits)
        $currentSemester = $currentEnroll->semester;
        $currentYear = $currentSemester->year;
        $currentSemesterType = $currentSemester->semester_type ?? 1;

        // FIRST PASS: Identify all subjects that have been passed (>= 50%) in ANY enrollment
        // This includes resit semesters
        foreach ($student->studentEnrolls as $enroll) {
            // Only check enrollments from the same program
            if ($enroll->program_id != $currentEnroll->program_id) {
                continue;
            }

            if (isset($enroll->subjectMarks)) {
                foreach ($enroll->subjectMarks as $mark) {
                    if (!isset($mark->subject)) continue;

                    // CRITICAL: Only consider published marks
                    if (!$this->isMarkPublished($mark)) {
                        continue;
                    }

                    $marksPer = round($mark->total_marks);
                    $subjectId = $mark->subject_id;
                    
                    // If student passed this subject in ANY semester (including resit), mark it as passed
                    if ($marksPer >= 50) {
                        $passedSubjects[$subjectId] = true;
                    }
                }
            }
        }

        // Get all semesters with same type and year <= current year
        $relevantSemesters = \App\Models\Semester::where('status', 1)
            ->where('semester_type', $currentSemesterType)
            ->where('year', '<=', $currentYear)
            ->where('is_resit', '!=', 1)
            ->pluck('id')
            ->toArray();

        // SECOND PASS: Get failed courses that have NOT been passed later
        foreach ($student->studentEnrolls as $enroll) {
            // Only check enrollments from the same program
            if ($enroll->program_id != $currentEnroll->program_id) {
                continue;
            }

            if (!in_array($enroll->semester_id, $relevantSemesters)) {
                continue;
            }

            if (isset($enroll->subjectMarks)) {
                foreach ($enroll->subjectMarks as $mark) {
                    if (!isset($mark->subject)) continue;

                    // CRITICAL: Only consider published marks
                    if (!$this->isMarkPublished($mark)) {
                        continue;
                    }

                    $marksPer = round($mark->total_marks);
                    $subjectId = $mark->subject_id;
                    
                    // Only include courses that failed initially AND have NOT been passed later
                    if ($marksPer < 50 && !isset($passedSubjects[$subjectId])) {
                        
                        // If already in list, keep the best attempt
                        if (isset($carryOverCourses[$subjectId])) {
                            if ($marksPer > $carryOverCourses[$subjectId]['best_marks']) {
                                $carryOverCourses[$subjectId]['best_marks'] = $marksPer;
                            }
                            $carryOverCourses[$subjectId]['attempts']++;
                        } else {
                            $gradeTitle = '';
                            $gradePoint = 0;
                            foreach ($grades as $grade) {
                                if ($marksPer >= $grade->min_mark && $marksPer <= $grade->max_mark) {
                                    $gradeTitle = $grade->title;
                                    $gradePoint = $grade->point;
                                    break;
                                }
                            }

                            $carryOverCourses[$subjectId] = [
                                'subject' => $mark->subject,
                                'best_marks' => $marksPer,
                                'grade' => $gradeTitle,
                                'grade_point' => $gradePoint,
                                'attempts' => 1,
                                'semester_title' => $enroll->semester->title ?? '',
                                'session_title' => $enroll->session->title ?? '',
                                'subject_type' => $mark->subject->subject_type,
                                'credit_hours' => $mark->subject->credit_hour,
                            ];
                        }
                    }
                }
            }
        }

        // Sort by priority: Compulsory > University Requirement > Optional
        // Within each type, sort by attempts (highest first)
        usort($carryOverCourses, function($a, $b) {
            // First sort by subject type priority
            $typeOrder = [1 => 1, 2 => 2, 0 => 3]; // Compulsory, University Req, Optional
            $typeA = $typeOrder[$a['subject_type']] ?? 4;
            $typeB = $typeOrder[$b['subject_type']] ?? 4;
            
            if ($typeA != $typeB) {
                return $typeA - $typeB;
            }
            
            // Then by number of attempts (descending)
            return $b['attempts'] - $a['attempts'];
        });

        return $carryOverCourses;
    }

    /**
     * Check if subject marking is published and visible to students
     * Uses same logic as transcript to ensure consistency
     */
    private function isMarkPublished($mark)
    {
        // Use the model's is_visible_to_student accessor which handles:
        // - workflow_state check
        // - is_published_override check (null = follow workflow, true = force publish, false = force unpublish)
        if (!$mark->is_visible_to_student) {
            return false;
        }
        
        // Check if publish date/time has passed (same logic as transcript)
        $publishDate = $mark->publish_date instanceof \Carbon\Carbon ? 
                      $mark->publish_date->format('Y-m-d') : 
                      date('Y-m-d', strtotime($mark->publish_date));
        $publishTime = $mark->publish_time instanceof \Carbon\Carbon ? 
                      $mark->publish_time->format('H:i:s') : 
                      date('H:i:s', strtotime($mark->publish_time));
        $currentDate = date('Y-m-d');
        $currentTime = date('H:i:s');

        $isVisible = ($publishDate == $currentDate && $publishTime <= $currentTime) || 
                     $publishDate < $currentDate;
        
        return $isVisible;
    }
}
