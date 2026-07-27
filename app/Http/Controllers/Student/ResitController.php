<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ExamType;
use App\Models\FeesCategory;
use App\Models\Grade;
use App\Models\ResitRequest;
use App\Models\Session;
use App\Models\Semester;
use App\Models\StudentEnroll;
use App\Models\Subject;
use App\Models\SubjectMarking;
use App\Models\SubjectMarkingExamState;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Flasher\Laravel\Facade\Flasher;

class ResitController extends Controller
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
        $this->title    = __('Resit Requests');
        $this->route    = 'student.resit';
        $this->view     = 'student.resit';
        $this->path     = 'resit';
    }

    /**
     * Display a listing of failed courses
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $data['title']     = $this->title;
        $data['route']     = $this->route;
        $data['view']      = $this->view;
        $data['path']      = $this->path;

        $student = Auth::guard('student')->user();
        
        // Get selected enrollment from session (set by SelectEnrollmentMiddleware)
        $selectedEnrollmentId = session('selected_enrollment_id');
        
        $currentEnrollment = null;
        if($selectedEnrollmentId) {
            $currentEnrollment = StudentEnroll::where('id', $selectedEnrollmentId)
                            ->where('student_id', $student->id)
                            ->with(['program', 'session', 'semester'])
                            ->first();
        }
        
        // Fallback: If no enrollment found via session, try current session
        if(!$currentEnrollment) {
            $current_session = Session::where('status', '1')->where('current', '1')->first();
            if(isset($current_session)){
                $currentEnrollment = StudentEnroll::where('student_id', $student->id)
                                ->where('session_id', $current_session->id)
                                ->orderBy('id', 'desc')
                                ->first();
            }
        }
        
        $data['current_enrollment'] = $currentEnrollment;
        
        // Get sessions and semesters for dropdowns
        $data['sessions'] = Session::orderBy('id', 'desc')->get();
        $data['semesters'] = Semester::orderBy('id', 'asc')->get();
        
        // Determine which session/semester to show (from request or default to current enrollment)
        if (!empty($request->session_id) || !empty($request->semester_id)) {
            // User selected specific session/semester
            $data['selected_session'] = $request->session_id;
            $data['selected_semester'] = $request->semester_id;
        } elseif ($currentEnrollment) {
            // Check if current enrollment is a resit semester
            if ($currentEnrollment->semester && $currentEnrollment->semester->is_resit && $currentEnrollment->semester->parent_semester_id) {
                // Default to the parent semester's session and semester
                $parentEnrollment = StudentEnroll::where('student_id', $student->id)
                    ->where('program_id', $currentEnrollment->program_id)
                    ->where('semester_id', $currentEnrollment->semester->parent_semester_id)
                    ->orderBy('id', 'desc')
                    ->first();
                if ($parentEnrollment) {
                    $data['selected_session'] = $parentEnrollment->session_id;
                    $data['selected_semester'] = $parentEnrollment->semester_id;
                } else {
                    $data['selected_session'] = $currentEnrollment->session_id;
                    $data['selected_semester'] = $currentEnrollment->semester_id;
                }
            } else {
                // Default to current enrollment's session/semester and auto-load data
                $data['selected_session'] = $currentEnrollment->session_id;
                $data['selected_semester'] = $currentEnrollment->semester_id;
            }
        } else {
            $data['selected_session'] = null;
            $data['selected_semester'] = null;
        }
        
        $data['failed_courses'] = [];
        $data['grades'] = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();
        $data['is_resit_semester'] = false;
        $data['current_is_resit'] = $currentEnrollment && $currentEnrollment->semester && $currentEnrollment->semester->is_resit;
        $data['progression_info'] = null;
        $data['enrollment'] = null;
        
        // Get student's existing resit requests for THIS PROGRAM ONLY
        if ($currentEnrollment) {
            $data['existing_requests'] = ResitRequest::whereHas('studentEnroll', function($query) use ($student, $currentEnrollment) {
                    $query->where('student_id', $student->id)
                          ->where('program_id', $currentEnrollment->program_id);
                })
                ->with(['subject', 'session', 'resitSession', 'studentEnroll.semester'])
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            $data['existing_requests'] = collect();
        }
        
        // If session and semester are selected, get the enrollment and failed courses
        if ($data['selected_session'] && $data['selected_semester']) {
            // Get enrollment for selected session/semester (must be from current program)
            $enrollment = null;
            if ($currentEnrollment) {
                $enrollment = StudentEnroll::where('student_id', $student->id)
                    ->where('session_id', $data['selected_session'])
                    ->where('semester_id', $data['selected_semester'])
                    ->where('program_id', $currentEnrollment->program_id) // Only from current program
                    ->first();
            }
            
            if ($enrollment) {
                $data['enrollment'] = $enrollment;
                
                // Check if selected semester is a resit semester
                if ($enrollment->semester && $enrollment->semester->is_resit) {
                    $data['is_resit_semester'] = true;
                    // Resits cannot be requested from a resit semester.
                    // We purposefully leave $failed_courses as an empty array.
                } else {
                    // Regular semester - show failed courses
                    $failed_courses = $this->getFailedCourses($enrollment, $data['grades']);
                    $data['failed_courses'] = $failed_courses;
                    
                    // Check if student can progress to resit semester
                    if (!empty($failed_courses)) {
                        $progressionService = app(\App\Services\Academic\SemesterProgressionService::class);
                        $progressionInfo = $progressionService->checkResitSemesterProgression($enrollment);
                        $data['progression_info'] = $progressionInfo;
                    }
                }
            }
        }
        
        \Illuminate\Support\Facades\Log::info("ResitController@index for student {$student->id}: failed_courses count = " . count($data['failed_courses']));

        return view($this->view.'.index', $data);
    }

    /**
     * Get failed courses for an enrollment
     */
    protected function getFailedCourses($enrollment, $grades)
    {
        $failed = [];
        $student = $enrollment->student;
        
        // Get all subjects for this enrollment
        foreach ($enrollment->subjects as $subject) {
            // Get subject marking for this subject
            $marking = SubjectMarking::where('student_enroll_id', $enrollment->id)
                ->where('subject_id', $subject->id)
                ->first();
            
            if (!$marking) {
                continue;
            }
            
            // Check if marking is published and all exam types are published
            if (!$this->isFullyPublished($marking)) {
                continue;
            }
            
            // Check if student failed (< 50%)
            $total_marks = round($marking->total_marks);
            if ($total_marks < 50) {
                // CRITICAL FIX: Check if this course is currently registered in ANY active enrollment
                // BUT: If they failed in that other enrollment too (and marks are published), 
                // we should allow resit from the MOST RECENT enrollment only
                $otherEnrollments = StudentEnroll::where('student_id', $student->id)
                    ->where('status', 1) // Active enrollments only
                    ->where('id', '!=', $enrollment->id) // Exclude the enrollment we're checking
                    ->whereHas('subjects', function($query) use ($subject) {
                        $query->where('subjects.id', $subject->id);
                    })
                    ->get();
                
                $shouldSkip = false;
                
                foreach ($otherEnrollments as $otherEnroll) {
                    // Check if there's a published marking for this course in the other enrollment
                    $otherMarking = SubjectMarking::where('student_enroll_id', $otherEnroll->id)
                        ->where('subject_id', $subject->id)
                        ->first();
                    
                    if (!$otherMarking) {
                        // Course is registered but no marks yet - student is currently taking it
                        // Don't show resit for older enrollment
                        $shouldSkip = true;
                        break;
                    }
                    
                    if (!$this->isFullyPublished($otherMarking)) {
                        // Marks exist but not published yet - student is being graded
                        // Don't show resit for older enrollment
                        $shouldSkip = true;
                        break;
                    }
                    
                    // Marks are published - check if they passed or failed
                    $otherTotalMarks = round($otherMarking->total_marks);
                    
                    if ($otherTotalMarks >= 50) {
                        // Student PASSED in the newer enrollment - don't allow resit for older failure
                        $shouldSkip = true;
                        break;
                    }
                    
                    // Student FAILED in the newer enrollment too
                    // Only show resit for the MOST RECENT enrollment (higher ID)
                    if ($otherEnroll->id > $enrollment->id) {
                        // The other enrollment is more recent - skip this older one
                        $shouldSkip = true;
                        break;
                    }
                }
                
                if ($shouldSkip) {
                    continue;
                }
                
                // Find the grade
                $grade_title = null;
                foreach ($grades as $grade) {
                    if ($total_marks >= $grade->min_mark && $total_marks <= $grade->max_mark) {
                        $grade_title = $grade->title;
                        break;
                    }
                }
                
                // Check if resit request already exists
                // CRITICAL: Prioritize DECLINED requests over others
                // If student declined to resit, they should not be able to request again
                $existing_request = ResitRequest::where('student_enroll_id', $enrollment->id)
                    ->where('subject_id', $subject->id)
                    ->orderByRaw("CASE 
                        WHEN workflow_state = 'declined' THEN 1 
                        WHEN workflow_state = 'scheduled' THEN 2
                        WHEN workflow_state = 'approved' THEN 3
                        WHEN workflow_state = 'finance_review' THEN 4
                        WHEN workflow_state = 'awaiting_payment' THEN 5
                        WHEN workflow_state = 'requested' THEN 6
                        WHEN workflow_state = 'cancelled' THEN 7
                        WHEN workflow_state = 'rejected' THEN 8
                        ELSE 9 
                    END")
                    ->first();
                
                $failed[] = [
                    'subject' => $subject,
                    'marking' => $marking,
                    'total_marks' => $total_marks,
                    'grade' => $grade_title,
                    'existing_request' => $existing_request,
                ];
            }
        }
        
        return $failed;
    }

    /**
     * Check if subject marking is published and visible to students
     * Uses same logic as transcript to ensure consistency
     */
    protected function isFullyPublished($marking)
    {
        // Use the model's is_visible_to_student accessor which handles:
        // - workflow_state check
        // - is_published_override check (null = follow workflow, true = force publish, false = force unpublish)
        if (!$marking->is_visible_to_student) {
            return false;
        }
        
        // Check if publish date/time has passed (same logic as transcript)
        $publishDate = $marking->publish_date instanceof \Carbon\Carbon ? 
                      $marking->publish_date->format('Y-m-d') : 
                      date('Y-m-d', strtotime($marking->publish_date));
        $publishTime = $marking->publish_time instanceof \Carbon\Carbon ? 
                      $marking->publish_time->format('H:i:s') : 
                      date('H:i:s', strtotime($marking->publish_time));
        $currentDate = date('Y-m-d');
        $currentTime = date('H:i:s');

        $isVisible = ($publishDate == $currentDate && $publishTime <= $currentTime) || 
                     $publishDate < $currentDate;
        
        return $isVisible;
    }

    /**
     * Store a resit request
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'student_enroll_id' => 'required|exists:student_enrolls,id',
            'subject_id' => 'required|exists:subjects,id',
            'session_id' => 'required|exists:sessions,id',
        ]);

        $student = Auth::guard('student')->user();
        
        // Verify the enrollment belongs to this student
        $enrollment = StudentEnroll::where('id', $request->student_enroll_id)
            ->where('student_id', $student->id)
            ->first();
        
        if (!$enrollment) {
            Flasher::addError(__('Invalid enrollment'), __('Error'));
            return redirect()->back();
        }
        
        // Check if an active resit request already exists (ignore cancelled, rejected, declined)
        $existing = ResitRequest::where('student_enroll_id', $request->student_enroll_id)
            ->where('subject_id', $request->subject_id)
            ->whereNotIn('workflow_state', [
                ResitRequest::STATE_CANCELLED,
                ResitRequest::STATE_REJECTED,
                ResitRequest::STATE_DECLINED
            ])
            ->first();
        
        if ($existing) {
            Flasher::addWarning(__('You have already requested a resit for this course'), __('Warning'));
            return redirect()->back();
        }
        
        // Get subject marking to verify failed status
        $marking = SubjectMarking::where('student_enroll_id', $request->student_enroll_id)
            ->where('subject_id', $request->subject_id)
            ->first();
        
        if (!$marking || !$this->isFullyPublished($marking)) {
            Flasher::addError(__('Course marks are not yet published'), __('Error'));
            return redirect()->back();
        }
        
        if (round($marking->total_marks) >= 50) {
            Flasher::addError(__('You did not fail this course'), __('Error'));
            return redirect()->back();
        }
        
        // Get resit fee category
        $resit_category = FeesCategory::where('is_resit', 1)
            ->where('status', 1)
            ->first();
        
        if (!$resit_category) {
            Flasher::addError(__('Resit fee category not configured. Please contact administration.'), __('Error'));
            return redirect()->back();
        }
        
        // Create resit request
        $resitRequest = ResitRequest::create([
            'student_enroll_id' => $request->student_enroll_id,
            'subject_id' => $request->subject_id,
            'session_id' => $request->session_id,
            'workflow_state' => ResitRequest::STATE_REQUESTED,
            'payment_status' => ResitRequest::PAYMENT_PENDING,
            'fee_amount' => 0, // Will be set by ResitFeeService
        ]);
        
        Flasher::addSuccess(__('Resit request submitted! A fee has been assigned. Pay online via My Fees, pay cash at the Finance Office, or pay at the bank and bring your receipt to the Finance Office for verification.'), __('Success'));
        
        return redirect()->back();
    }

    /**
     * Show student's resit request history
     */
    public function history()
    {
        $data['title']     = __('My Resit Requests');
        $data['route']     = $this->route;
        $data['view']      = $this->view;
        $data['path']      = $this->path;

        $student = Auth::guard('student')->user();
        
        // Get selected enrollment from session
        $selectedEnrollmentId = session('selected_enrollment_id');
        
        $enrollment = null;
        if($selectedEnrollmentId) {
            $enrollment = StudentEnroll::where('id', $selectedEnrollmentId)
                            ->where('student_id', $student->id)
                            ->first();
        }
        
        // Fallback: If no enrollment found via session, try current session
        if(!$enrollment) {
            $current_session = Session::where('status', '1')->where('current', '1')->first();
            if(isset($current_session)){
                $enrollment = StudentEnroll::where('student_id', $student->id)
                                ->where('session_id', $current_session->id)
                                ->orderBy('id', 'desc')
                                ->first();
            }
        }
        
        // Filter resit requests by current program only
        if($enrollment) {
            $data['requests'] = ResitRequest::whereHas('studentEnroll', function($query) use ($student, $enrollment) {
                    $query->where('student_id', $student->id)
                          ->where('program_id', $enrollment->program_id);
                })
                ->with(['subject', 'session', 'resitSession', 'resitSemester', 'fee', 'studentEnroll.program', 'studentEnroll.semester'])
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            $data['requests'] = collect();
        }
        
        $data['enrollment'] = $enrollment;
        
        return view($this->view.'.history', $data);
    }

    /**
     * Cancel a resit request
     * Student can only cancel if request is in: requested, awaiting_payment states
     * 
     * @param ResitRequest $resitRequest
     * @return \Illuminate\Http\Response
     */
    public function cancel(Request $request, $id)
    {
        $student = Auth::guard('student')->user();
        
        $resitRequest = ResitRequest::findOrFail($id);
        
        // Verify the resit request belongs to this student
        $enrollment = $resitRequest->studentEnroll;
        if (!$enrollment || $enrollment->student_id !== $student->id) {
            Flasher::addError(__('Unauthorized action'), __('Error'));
            return redirect()->back();
        }
        
        // Only allow cancellation if in early states
        $cancellableStates = [
            ResitRequest::STATE_REQUESTED,
            ResitRequest::STATE_AWAITING_PAYMENT,
        ];
        
        if (!in_array($resitRequest->workflow_state, $cancellableStates)) {
            Flasher::addError(__('This resit request cannot be cancelled at its current stage'), __('Error'));
            return redirect()->back();
        }
        
        try {
            DB::beginTransaction();
            
            // Delete associated fee if exists and unpaid
            if ($resitRequest->fee && $resitRequest->fee->status == 0) {
                $feeToDelete = $resitRequest->fee;
                // Clear fee reference on the resit request first
                $resitRequest->fee_id = null;
                $resitRequest->fee_amount = 0;
                $resitRequest->save();
                // Now delete the fee record
                $feeToDelete->delete();
            }

            // Update resit request state
            $resitRequest->workflow_state = ResitRequest::STATE_CANCELLED;
            $resitRequest->payment_status = ResitRequest::PAYMENT_CANCELLED;
            $resitRequest->state_changed_at = now();
            $resitRequest->notes = 'Cancelled by student';
            $resitRequest->save();
            
            DB::commit();
            
            Flasher::addSuccess(__('Resit request cancelled successfully'), __('Success'));
            
            // Auto-progression disabled - students now progress manually via header button
            // $this->checkAndNotifyProgression($enrollment);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Flasher::addError(__('Failed to cancel resit request: ') . $e->getMessage(), __('Error'));
        }
        
        return redirect()->back();
    }

    /**
     * Decline to resit a failed course
     * Student explicitly chooses NOT to resit a course they failed
     * This creates a declined resit record so we know they made a conscious decision
     * 
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function decline(Request $request)
    {
        $student = Auth::guard('student')->user();
        
        $request->validate([
            'student_enroll_id' => 'required|exists:student_enrolls,id',
            'subject_id' => 'required|exists:subjects,id',
        ]);
        
        $enrollment = StudentEnroll::findOrFail($request->student_enroll_id);
        
        // Verify enrollment belongs to this student
        if ($enrollment->student_id !== $student->id) {
            Flasher::addError(__('Unauthorized action'), __('Error'));
            return redirect()->back();
        }
        
        // Check if a resit request already exists for this course
        $existingRequest = ResitRequest::where('student_enroll_id', $enrollment->id)
            ->where('subject_id', $request->subject_id)
            ->first();
        
        if ($existingRequest) {
            // If request exists and is already declined, do nothing
            if ($existingRequest->workflow_state === ResitRequest::STATE_DECLINED) {
                Flasher::addInfo(__('You have already declined to resit this course'), __('Info'));
                return redirect()->back();
            }
            
            // If request exists in active states (not cancelled/rejected), cannot decline directly
            // They should cancel first, then fee will be cancelled too
            if (in_array($existingRequest->workflow_state, [
                ResitRequest::STATE_REQUESTED,
                ResitRequest::STATE_AWAITING_PAYMENT,
                ResitRequest::STATE_FINANCE_REVIEW,
                ResitRequest::STATE_APPROVED,
                ResitRequest::STATE_SCHEDULED
            ])) {
                Flasher::addError(__('You must cancel your existing resit request before declining to resit'), __('Error'));
                return redirect()->back();
            }
        }
        
        try {
            DB::beginTransaction();
            
            if ($existingRequest) {
                // Update existing request to DECLINED state
                $existingRequest->workflow_state = ResitRequest::STATE_DECLINED;
                $existingRequest->payment_status = ResitRequest::PAYMENT_WAIVED;
                $existingRequest->state_changed_at = now();
                $existingRequest->notes = 'Student declined to resit this course';
                $existingRequest->save();
                
                // Delete associated fee if exists and unpaid
                if ($existingRequest->fee && $existingRequest->fee->status == 0) {
                    $feeToDelete = $existingRequest->fee;
                    $existingRequest->fee_id = null;
                    $existingRequest->fee_amount = 0;
                    $existingRequest->save();
                    $feeToDelete->delete();
                }
                
                $resitRequest = $existingRequest;
            } else {
                // Create a new declined resit request (no fee, just a record of the decision)
                $resitRequest = ResitRequest::create([
                    'student_enroll_id' => $enrollment->id,
                    'subject_id' => $request->subject_id,
                    'session_id' => $enrollment->session_id,
                    'resit_session_id' => null,
                    'resit_semester_id' => null,
                    'resit_enroll_id' => null,
                    'fee_amount' => 0,
                    'payment_status' => ResitRequest::PAYMENT_WAIVED,
                    'workflow_state' => ResitRequest::STATE_DECLINED,
                    'state_changed_at' => now(),
                    'notes' => 'Student declined to resit this course',
                ]);
            }
            
            DB::commit();
            
            Flasher::addSuccess(__('You have successfully declined to resit this course'), __('Success'));
            
            // Auto-progression disabled - students now progress manually via header button
            // Check if student is now eligible for semester progression
            // (if they've declined all failed courses or passed resits for others)
            // $this->checkAndNotifyProgression($enrollment);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Flasher::addError(__('Failed to record decision: ') . $e->getMessage(), __('Error'));
        }
        
        return redirect()->back();
    }

    /**
     * Undo a declined resit decision so the student can request a resit again
     */
    public function undoDecline($id)
    {
        $student = Auth::guard('student')->user();

        $resitRequest = ResitRequest::findOrFail($id);

        // Verify the request belongs to this student
        $enrollment = StudentEnroll::findOrFail($resitRequest->student_enroll_id);
        if ($enrollment->student_id !== $student->id) {
            Flasher::addError(__('Unauthorized action'), __('Error'));
            return redirect()->back();
        }

        if ($resitRequest->workflow_state !== ResitRequest::STATE_DECLINED) {
            Flasher::addError(__('This request is not in a declined state'), __('Error'));
            return redirect()->back();
        }

        try {
            DB::beginTransaction();

            // Delete the declined record so the student is back to a fresh state
            $resitRequest->delete();

            DB::commit();

            Flasher::addSuccess(__('Decline decision has been undone. You can now request a resit or decline again.'), __('Success'));
        } catch (\Exception $e) {
            DB::rollBack();
            Flasher::addError(__('Failed to undo decline: ') . $e->getMessage(), __('Error'));
        }

        return redirect()->back();
    }

    /**
     * Check if student is eligible for progression after cancelling/declining resit
     * Handles both regular semester progression AND resit semester progression
     */
    protected function checkAndNotifyProgression(StudentEnroll $enrollment)
    {
        $progressionService = app(\App\Services\Academic\SemesterProgressionService::class);
        
        // First, check if student can progress to RESIT SEMESTER
        $resitProgression = $progressionService->checkResitSemesterProgression($enrollment);
        
        if ($resitProgression['can_progress'] && $resitProgression['resit_semester']) {
            // Student has scheduled resits - progress to resit semester
            $resitEnrollment = $progressionService->progressToResitSemester(
                $enrollment,
                $resitProgression['resit_semester'],
                $resitProgression['resit_session_id'],
                $resitProgression['scheduled_courses']
            );
            
            if ($resitEnrollment) {
                // Store progression data in session for modal display
                session()->put('progression_modal', [
                    'old_semester' => $enrollment->semester->title ?? '',
                    'new_semester' => $resitEnrollment->semester->title ?? '',
                    'session' => $resitEnrollment->session->title ?? '',
                    'program' => $resitEnrollment->program->title ?? '',
                    'year' => $resitEnrollment->semester->year ?? '',
                    'semester_type' => $resitEnrollment->semester->semester_type ?? '',
                    'matricule' => $resitEnrollment->matricule ?? '',
                    'is_resit' => true,
                    'courses_count' => count($resitProgression['scheduled_courses']),
                    'scheduled_courses' => $resitProgression['scheduled_courses'],
                ]);
                
                // Show flash notification
                Flasher::addSuccess(
                    __('You have been enrolled in the resit semester to retake your failed courses!'),
                    __('Resit Semester Enrollment')
                );
            }
            
            return;
        }
        
        // Auto-progression disabled - students now progress manually via header button
        // $eligibility = $progressionService->checkProgressionEligibility($enrollment);
        // 
        // if ($eligibility['eligible']) {
        //     // Attempt automatic progression to next regular semester
        //     $result = $progressionService->attemptAutomaticProgression($enrollment);
        //     
        //     if ($result['progressed']) {
        //         // Get new enrollment details
        //         $newEnrollment = $result['new_enrollment'] ?? null;
        //         
        //         if ($newEnrollment) {
        //             // Store progression data in session for modal display
        //             session()->put('progression_modal', [
        //                 'old_semester' => $enrollment->semester->title ?? '',
        //                 'new_semester' => $newEnrollment->semester->title ?? $eligibility['next_semester']->title,
        //                 'session' => $newEnrollment->session->title ?? '',
        //                 'program' => $newEnrollment->program->title ?? '',
        //                 'year' => $newEnrollment->semester->year ?? '',
        //                 'semester_type' => $newEnrollment->semester->semester_type ?? '',
        //                 'matricule' => $newEnrollment->matricule ?? '',
        //                 'is_resit' => false,
        //             ]);
        //         }
        //         
        //         // Also show flash notification
        //         Flasher::addSuccess(
        //             __('Congratulations! You have been automatically progressed to ') . $eligibility['next_semester']->title,
        //             __('Semester Progression')
        //         );
        //     }
        // }
    }
}


