<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamType;
use App\Models\Grade;
use App\Models\ResultContribution;
use App\Models\Semester;
use App\Models\Session;
use App\Models\Setting;
use App\Models\StudentAttendance;
use App\Models\StudentEnroll;
use App\Models\SubjectMarking;
use App\Services\ResultContributionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExamResultController extends Controller
{
    protected string $title;
    protected string $route;
    protected string $view;
    protected string $path;

    public function __construct()
    {
        $this->title = __('Exam Results');
        $this->route = 'student.exam-results.index';
        $this->view = 'student.exam-results';
        $this->path = 'exam-results';
    }

    public function index(Request $request)
    {
        $student = Auth::guard('student')->user();

        $data = [
            'title' => $this->title,
            'route' => $this->route,
            'view' => $this->view,
            'path' => $this->path,
            'rows' => collect(),
            'coursesData' => [],
            'totalCreditsRegistered' => 0,
            'totalCreditsEarned' => 0,
            'gpa' => 0,
            'selected_session' => $request->input('session', ''),
            'selected_semester' => $request->input('semester', ''),
            'selected_exam_type' => $request->input('exam_type', ''),
            'selected_semester_year' => $request->input('semester_year', ''),
            'filtersApplied' => false,
        ];

        $selectedEnrollmentId = session('selected_enrollment_id');
        $selectedProgramId = null;
        
        if ($selectedEnrollmentId) {
            $currentEnroll = StudentEnroll::find($selectedEnrollmentId);
            $selectedProgramId = $currentEnroll ? $currentEnroll->program_id : null;
        }

        $enrollsQuery = StudentEnroll::query()
            ->where('student_id', $student->id)
            ->with(['session:id,title', 'semester:id,title']);
            
        // Filter by selected program if available
        if ($selectedProgramId) {
            $enrollsQuery->where('program_id', $selectedProgramId);
        }
            
        $enrolls = $enrollsQuery->get();

        $sessionIds = $enrolls->pluck('session_id')->filter()->unique();
        $semesterIds = $enrolls->pluck('semester_id')->filter()->unique();
        $data['sessions'] = $sessionIds->isNotEmpty()
            ? Session::whereIn('id', $sessionIds)->orderBy('title', 'asc')->get()
            : collect();

        $data['examTypes'] = ExamType::where('status', '1')->orderBy('contribution', 'desc')->get();

        $selectedSessionId = $this->normalizeFilterValue($data['selected_session']);
        $selectedSemesterId = $this->normalizeFilterValue($data['selected_semester']);
        $selectedExamTypeId = $this->normalizeFilterValue($data['selected_exam_type']);

        $semesterPool = $enrolls->filter(function ($enroll) use ($selectedSessionId) {
            return $selectedSessionId ? (int) $enroll->session_id === $selectedSessionId : true;
        })->pluck('semester_id')->filter()->unique();

        $semesterCollection = $semesterPool->isNotEmpty()
            ? Semester::whereIn('id', $semesterPool)->orderBy('year', 'asc')->orderBy('id', 'asc')->get()
            : ($semesterIds->isNotEmpty() ? Semester::whereIn('id', $semesterIds)->orderBy('year', 'asc')->orderBy('id', 'asc')->get() : collect());

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

        if (($data['selected_semester_year'] === '' || $data['selected_semester_year'] === '0') && $selectedSemesterId) {
            $matchedSemester = $semesterCollection->firstWhere('id', $selectedSemesterId);
            if ($matchedSemester && !is_null($matchedSemester->year)) {
                $data['selected_semester_year'] = (string) $matchedSemester->year;
            }
        }

        $data['selected_exam_type_model'] = $selectedExamTypeId
            ? ($data['examTypes']->firstWhere('id', $selectedExamTypeId) ?: ExamType::find($selectedExamTypeId))
            : null;

        $grades = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();
        $data['grades'] = $grades;
        // Note: Result contributions are now course-specific, so we don't fetch a single global record
        // Each subject's contributions will be retrieved individually in the view if needed
        $data['resultContribution'] = null;

        if ($selectedSessionId && $selectedSemesterId && $selectedExamTypeId) {
            $data['filtersApplied'] = true;

            // Result-access block check: if an admin has blocked results for
            // this (student, program, session, semester) combination, short-circuit
            // and render a withheld notice instead of the marks.
            $resolvedProgramId = $selectedProgramId;
            if (!$resolvedProgramId) {
                $matchingEnroll = $enrolls->first(function ($enroll) use ($selectedSessionId, $selectedSemesterId) {
                    return (int) $enroll->session_id === $selectedSessionId
                        && (int) $enroll->semester_id === $selectedSemesterId;
                });
                $resolvedProgramId = $matchingEnroll ? $matchingEnroll->program_id : null;
            }
            if ($resolvedProgramId) {
                $activeBlock = \App\Models\ResultAccessBlock::activeBlockFor(
                    $student->id,
                    $resolvedProgramId,
                    $selectedSessionId,
                    $selectedSemesterId
                );
                if ($activeBlock) {
                    $data['result_blocked'] = true;
                    $data['result_block'] = $activeBlock;
                    return view($this->view . '.index', $data);
                }
            }

            $matchingEnrollIds = $enrolls
                ->filter(function ($enroll) use ($selectedSessionId, $selectedSemesterId) {
                    return (int) $enroll->session_id === $selectedSessionId
                        && (int) $enroll->semester_id === $selectedSemesterId;
                })
                ->pluck('id');

            if ($matchingEnrollIds->isNotEmpty()) {
                $rows = SubjectMarking::query()
                    ->whereIn('student_enroll_id', $matchingEnrollIds)
                    ->with([
                        'subject:id,title,code,credit_hour',
                        'studentEnroll.session:id,title',
                        'studentEnroll.semester:id,title',
                        'studentEnroll.section:id,title',
                        'studentEnroll.exams' => function ($query) {
                            // Load ALL exams (all exam types) to compute CA + Final breakdown
                            $query->with('type:id,title,is_final,contribution');
                        },
                        // Load ALL exam states so we can check publication per exam type
                        'examStates',
                    ])
                    ->whereHas('examStates', function ($query) use ($selectedExamTypeId) {
                        $query->where('exam_type_id', $selectedExamTypeId)
                            ->where('workflow_state', SubjectMarking::STATE_PUBLISHED)
                            // Check that publish_date/time has passed
                            ->where(function ($q) {
                                $q->whereNull('publish_date') // No date set = show immediately
                                    ->orWhere(function ($sub) {
                                        // Date is in the past
                                        $sub->whereDate('publish_date', '<', now()->toDateString());
                                    })
                                    ->orWhere(function ($sub) {
                                        // Date is today AND time has passed (or no time set)
                                        $sub->whereDate('publish_date', '=', now()->toDateString())
                                            ->where(function ($timeCheck) {
                                                $timeCheck->whereNull('publish_time')
                                                    ->orWhereTime('publish_time', '<=', now()->format('H:i:s'));
                                            });
                                    });
                            });
                    })
                    ->orderBy('subject_id')
                    ->get();

                // Build enriched course data with full marks breakdown
                $coursesData = [];
                $totalCreditsRegistered = 0;
                $totalCreditsEarned = 0;
                $totalGradePoints = 0;

                // Pre-fetch exam type classifications (outside loop for efficiency)
                $caExamTypeIds = ExamType::where('status', '1')->where('is_final', false)->pluck('id')->toArray();
                $finalExamTypeIds = ExamType::where('status', '1')->where('is_final', true)->pluck('id')->toArray();

                foreach ($rows as $row) {
                    $subject = $row->subject;
                    $creditHour = (float) ($subject->credit_hour ?? 0);
                    $totalCreditsRegistered += $creditHour;

                    // Determine which exam types are published for this subject
                    $nowDate = now()->toDateString();
                    $nowTime = now()->format('H:i:s');
                    $publishedExamTypeIds = $row->examStates
                        ->where('workflow_state', SubjectMarking::STATE_PUBLISHED)
                        ->filter(function ($state) use ($nowDate, $nowTime) {
                            if (is_null($state->publish_date)) return true;
                            $dateStr = $state->publish_date instanceof \DateTimeInterface
                                ? $state->publish_date->format('Y-m-d')
                                : (string) $state->publish_date;
                            // Normalize to date-only for comparison
                            $dateOnly = substr($dateStr, 0, 10);
                            if ($dateOnly < $nowDate) return true;
                            if ($dateOnly === $nowDate) {
                                if (is_null($state->publish_time)) return true;
                                $timeStr = $state->publish_time instanceof \DateTimeInterface
                                    ? $state->publish_time->format('H:i:s')
                                    : substr((string) $state->publish_time, 0, 8);
                                return $timeStr <= $nowTime;
                            }
                            return false;
                        })
                        ->pluck('exam_type_id')
                        ->toArray();

                    // Check if CA and Final exam types are published
                    $caPublished = !empty(array_intersect($caExamTypeIds, $publishedExamTypeIds));
                    $finalPublished = !empty(array_intersect($finalExamTypeIds, $publishedExamTypeIds));

                    // Get all exam records for this subject (present students only)
                    $subjectExams = $row->studentEnroll->exams
                        ->where('subject_id', $row->subject_id)
                        ->where('attendance', 1);

                    // Split into CA and Final exam marks
                    // Only compute marks for exam types that have been published
                    $caExamMarks = 0;
                    $finalExamMarks = 0;
                    $rawCaMarks = 0;
                    $rawFinalMarks = 0;
                    $hasZeroContribution = false;

                    foreach ($subjectExams as $exam) {
                        $examContribution = (float) $exam->contribution;
                        $isFinalType = $exam->type && $exam->type->is_final;

                        // Skip marks from unpublished exam types
                        if ($isFinalType && !$finalPublished) continue;
                        if (!$isFinalType && !$caPublished) continue;

                        if ($exam->achieve_marks !== null) {
                            if ($isFinalType) {
                                $rawFinalMarks += (float) $exam->achieve_marks;
                            } else {
                                $rawCaMarks += (float) $exam->achieve_marks;
                            }
                        }

                        if ($examContribution <= 0) {
                            $hasZeroContribution = true;
                        }

                        if ($examContribution > 0 && $exam->marks > 0 && $exam->achieve_marks !== null) {
                            $percentOfMarks = ($exam->achieve_marks / $exam->marks) * 100;
                            $contributedMarks = ($percentOfMarks / 100) * $examContribution;

                            if ($isFinalType) {
                                $finalExamMarks += $contributedMarks;
                            } else {
                                $caExamMarks += $contributedMarks;
                            }
                        }
                    }

                    // When contribution weights are missing, use raw achieve_marks as fallback
                    $effectiveCaMarks = ($caExamMarks == 0 && $rawCaMarks > 0 && $hasZeroContribution)
                        ? $rawCaMarks : $caExamMarks;
                    $effectiveFinalMarks = ($finalExamMarks == 0 && $rawFinalMarks > 0 && $hasZeroContribution)
                        ? $rawFinalMarks : $finalExamMarks;

                    // Calculate attendance mark from StudentAttendance records
                    // (matches admin ExamPublishingController draft preview logic)
                    $subjectContributions = ResultContributionService::getSubjectContributions($row->subject_id);
                    $attendanceContribution = $subjectContributions['attendance'] ?? 0;

                    $studentAttendance = StudentAttendance::where('student_enroll_id', $row->student_enroll_id)
                        ->where('subject_id', $row->subject_id)
                        ->get();

                    $present = $studentAttendance->where('attendance', 1)->count();
                    $absent = $studentAttendance->where('attendance', 2)->count();
                    $leave = $studentAttendance->where('attendance', 3)->count();
                    $totalPresent = $present + $leave;
                    $totalAttendanceCount = $totalPresent + $absent;

                    $calculatedAttendance = 0;
                    if (!empty($totalAttendanceCount) && $attendanceContribution > 0) {
                        $calculatedAttendance = ($attendanceContribution / $totalAttendanceCount) * $totalPresent;
                    }

                    $storedAttendance = (float) ($row->attendances ?? 0);
                    $attendanceMark = round($calculatedAttendance, 2) ?: round($storedAttendance, 2);

                    // Assignment + Activity marks from SubjectMarking
                    $assignmentMark = (float) ($row->assignments ?? 0);
                    $activityMark = (float) ($row->activities ?? 0);

                    // Only include ATT/CA components if CA is published
                    $displayCA = $caPublished ? round($assignmentMark + $activityMark + $effectiveCaMarks, 2) : null;
                    $displayATT = $caPublished ? round($attendanceMark, 2) : null;
                    $examMarks = $finalPublished ? round($effectiveFinalMarks, 2) : null;

                    // Total = only sum published components
                    $totalCAInternal = ($caPublished ? ($attendanceMark + $assignmentMark + $activityMark + $effectiveCaMarks) : 0);
                    $totalMarks = round($totalCAInternal + ($finalPublished ? $effectiveFinalMarks : 0), 2);

                    // Grade/GPA only when ALL exam types are published
                    $allPublished = $caPublished && $finalPublished;
                    $letterGrade = '--';
                    $gradePoint = 0;
                    $remark = '';
                    $isPassed = false;

                    if ($allPublished) {
                        foreach ($grades as $grade) {
                            if ($totalMarks >= $grade->min_mark && $totalMarks <= $grade->max_mark) {
                                $letterGrade = $grade->title;
                                $gradePoint = (float) $grade->point;
                                $remark = $grade->remark ?? ($totalMarks >= 50 ? 'Pass' : 'Fail');
                                break;
                            }
                        }
                        $isPassed = $totalMarks >= 50;
                    }

                    if ($isPassed) {
                        $totalCreditsEarned += $creditHour;
                    }
                    $totalGradePoints += $gradePoint * $creditHour;

                    // Get publish date from exam state for the selected exam type
                    $examState = $row->examStates->where('exam_type_id', $selectedExamTypeId)->first();

                    $coursesData[] = [
                        'subject' => $subject,
                        'credit_hour' => $creditHour,
                        'attendance_mark' => $displayATT,
                        'ca_marks' => $displayCA,
                        'exam_marks' => $examMarks,
                        'total_marks' => $totalMarks,
                        'letter_grade' => $letterGrade,
                        'grade_point' => $gradePoint,
                        'remark' => $remark,
                        'is_passed' => $isPassed,
                        'ca_published' => $caPublished,
                        'final_published' => $finalPublished,
                        'all_published' => $allPublished,
                        'exam_state' => $examState,
                        'publish_date' => $row->publish_date,
                        'publish_time' => $row->publish_time,
                    ];
                }

                $gpa = $totalCreditsRegistered > 0
                    ? round($totalGradePoints / $totalCreditsRegistered, 2)
                    : 0;

                $data['rows'] = $rows;
                $data['coursesData'] = $coursesData;
                $data['totalCreditsRegistered'] = $totalCreditsRegistered;
                $data['totalCreditsEarned'] = $totalCreditsEarned;
                $data['gpa'] = $gpa;
            }
        }

        return view($this->view . '.index', $data);
    }

    public function downloadPdf(Request $request)
    {
        $student = Auth::guard('student')->user();

        $selectedSessionId = $this->normalizeFilterValue($request->input('session', ''));
        $selectedSemesterId = $this->normalizeFilterValue($request->input('semester', ''));
        $selectedExamTypeId = $this->normalizeFilterValue($request->input('exam_type', ''));

        if (!$selectedSessionId || !$selectedSemesterId || !$selectedExamTypeId) {
            return redirect()->route('student.exam-results.index')
                ->with('error', __('Please select session, semester, and exam type to download results.'));
        }

        $selectedEnrollmentId = session('selected_enrollment_id');
        $selectedProgramId = null;

        if ($selectedEnrollmentId) {
            $currentEnroll = StudentEnroll::with(['program', 'semester'])->find($selectedEnrollmentId);
            $selectedProgramId = $currentEnroll ? $currentEnroll->program_id : null;
        }

        $enrollsQuery = StudentEnroll::query()
            ->where('student_id', $student->id)
            ->with(['session:id,title', 'semester:id,title,year', 'program']);

        if ($selectedProgramId) {
            $enrollsQuery->where('program_id', $selectedProgramId);
        }

        $enrolls = $enrollsQuery->get();

        $matchingEnrollIds = $enrolls
            ->filter(function ($enroll) use ($selectedSessionId, $selectedSemesterId) {
                return (int) $enroll->session_id === $selectedSessionId
                    && (int) $enroll->semester_id === $selectedSemesterId;
            })
            ->pluck('id');

        if ($matchingEnrollIds->isEmpty()) {
            return redirect()->route('student.exam-results.index')
                ->with('error', __('No results found for the selected filters.'));
        }

        // Result-access block check for PDF download
        $resolvedProgramIdPdf = $selectedProgramId;
        if (!$resolvedProgramIdPdf) {
            $matchPdf = $enrolls->first(function ($enroll) use ($selectedSessionId, $selectedSemesterId) {
                return (int) $enroll->session_id === $selectedSessionId
                    && (int) $enroll->semester_id === $selectedSemesterId;
            });
            $resolvedProgramIdPdf = $matchPdf ? $matchPdf->program_id : null;
        }
        if ($resolvedProgramIdPdf && \App\Models\ResultAccessBlock::isBlocked($student->id, $resolvedProgramIdPdf, $selectedSessionId, $selectedSemesterId)) {
            return redirect()->route('student.exam-results.index', $request->query())
                ->with('error', __('Your results for this semester have been withheld. Please contact administration.'));
        }

        // Get session, semester, exam type models
        $session = Session::find($selectedSessionId);
        $semester = Semester::find($selectedSemesterId);
        $examType = ExamType::find($selectedExamTypeId);

        // Get matching enrollment for student info
        $enrollment = $enrolls->first(function ($enroll) use ($selectedSessionId, $selectedSemesterId) {
            return (int) $enroll->session_id === $selectedSessionId
                && (int) $enroll->semester_id === $selectedSemesterId;
        });

        $grades = Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();

        $rows = SubjectMarking::query()
            ->whereIn('student_enroll_id', $matchingEnrollIds)
            ->with([
                'subject:id,title,code,credit_hour',
                'studentEnroll.exams' => function ($query) {
                    $query->with('type:id,title,is_final,contribution');
                },
                // Load ALL exam states so we can check publication per exam type
                'examStates',
            ])
            ->whereHas('examStates', function ($query) use ($selectedExamTypeId) {
                $query->where('exam_type_id', $selectedExamTypeId)
                    ->where('workflow_state', SubjectMarking::STATE_PUBLISHED)
                    ->where(function ($q) {
                        $q->whereNull('publish_date')
                            ->orWhere(function ($sub) {
                                $sub->whereDate('publish_date', '<', now()->toDateString());
                            })
                            ->orWhere(function ($sub) {
                                $sub->whereDate('publish_date', '=', now()->toDateString())
                                    ->where(function ($timeCheck) {
                                        $timeCheck->whereNull('publish_time')
                                            ->orWhereTime('publish_time', '<=', now()->format('H:i:s'));
                                    });
                            });
                    });
            })
            ->orderBy('subject_id')
            ->get();

        // Build enriched course data (same logic as index)
        $coursesData = [];
        $totalCreditsRegistered = 0;
        $totalCreditsEarned = 0;
        $totalGradePoints = 0;

        // Pre-fetch exam type classifications (outside loop for efficiency)
        $caExamTypeIds = ExamType::where('status', '1')->where('is_final', false)->pluck('id')->toArray();
        $finalExamTypeIds = ExamType::where('status', '1')->where('is_final', true)->pluck('id')->toArray();

        foreach ($rows as $row) {
            $subject = $row->subject;
            $creditHour = (float) ($subject->credit_hour ?? 0);
            $totalCreditsRegistered += $creditHour;

            // Determine which exam types are published for this subject
            $nowDate = now()->toDateString();
            $nowTime = now()->format('H:i:s');
            $publishedExamTypeIds = $row->examStates
                ->where('workflow_state', SubjectMarking::STATE_PUBLISHED)
                ->filter(function ($state) use ($nowDate, $nowTime) {
                    if (is_null($state->publish_date)) return true;
                    $dateStr = $state->publish_date instanceof \DateTimeInterface
                        ? $state->publish_date->format('Y-m-d')
                        : (string) $state->publish_date;
                    $dateOnly = substr($dateStr, 0, 10);
                    if ($dateOnly < $nowDate) return true;
                    if ($dateOnly === $nowDate) {
                        if (is_null($state->publish_time)) return true;
                        $timeStr = $state->publish_time instanceof \DateTimeInterface
                            ? $state->publish_time->format('H:i:s')
                            : substr((string) $state->publish_time, 0, 8);
                        return $timeStr <= $nowTime;
                    }
                    return false;
                })
                ->pluck('exam_type_id')
                ->toArray();

            $caPublished = !empty(array_intersect($caExamTypeIds, $publishedExamTypeIds));
            $finalPublished = !empty(array_intersect($finalExamTypeIds, $publishedExamTypeIds));

            $subjectExams = $row->studentEnroll->exams
                ->where('subject_id', $row->subject_id)
                ->where('attendance', 1);

            $caExamMarks = 0;
            $finalExamMarks = 0;
            $rawCaMarks = 0;
            $rawFinalMarks = 0;
            $hasZeroContribution = false;

            foreach ($subjectExams as $exam) {
                $examContribution = (float) $exam->contribution;
                $isFinalType = $exam->type && $exam->type->is_final;

                // Skip marks from unpublished exam types
                if ($isFinalType && !$finalPublished) continue;
                if (!$isFinalType && !$caPublished) continue;

                if ($exam->achieve_marks !== null) {
                    if ($isFinalType) {
                        $rawFinalMarks += (float) $exam->achieve_marks;
                    } else {
                        $rawCaMarks += (float) $exam->achieve_marks;
                    }
                }

                if ($examContribution <= 0) {
                    $hasZeroContribution = true;
                }

                if ($examContribution > 0 && $exam->marks > 0 && $exam->achieve_marks !== null) {
                    $percentOfMarks = ($exam->achieve_marks / $exam->marks) * 100;
                    $contributedMarks = ($percentOfMarks / 100) * $examContribution;

                    if ($isFinalType) {
                        $finalExamMarks += $contributedMarks;
                    } else {
                        $caExamMarks += $contributedMarks;
                    }
                }
            }

            $effectiveCaMarks = ($caExamMarks == 0 && $rawCaMarks > 0 && $hasZeroContribution)
                ? $rawCaMarks : $caExamMarks;
            $effectiveFinalMarks = ($finalExamMarks == 0 && $rawFinalMarks > 0 && $hasZeroContribution)
                ? $rawFinalMarks : $finalExamMarks;

            // Calculate attendance mark from StudentAttendance records
            $subjectContributions = ResultContributionService::getSubjectContributions($row->subject_id);
            $attendanceContribution = $subjectContributions['attendance'] ?? 0;

            $studentAttendance = StudentAttendance::where('student_enroll_id', $row->student_enroll_id)
                ->where('subject_id', $row->subject_id)
                ->get();

            $present = $studentAttendance->where('attendance', 1)->count();
            $absent = $studentAttendance->where('attendance', 2)->count();
            $leave = $studentAttendance->where('attendance', 3)->count();
            $totalPresent = $present + $leave;
            $totalAttendanceCount = $totalPresent + $absent;

            $calculatedAttendance = 0;
            if (!empty($totalAttendanceCount) && $attendanceContribution > 0) {
                $calculatedAttendance = ($attendanceContribution / $totalAttendanceCount) * $totalPresent;
            }

            $storedAttendance = (float) ($row->attendances ?? 0);
            $attendanceMark = round($calculatedAttendance, 2) ?: round($storedAttendance, 2);

            $assignmentMark = (float) ($row->assignments ?? 0);
            $activityMark = (float) ($row->activities ?? 0);

            $displayCA = $caPublished ? round($assignmentMark + $activityMark + $effectiveCaMarks, 2) : null;
            $displayATT = $caPublished ? round($attendanceMark, 2) : null;
            $examMarks = $finalPublished ? round($effectiveFinalMarks, 2) : null;

            $totalCAInternal = ($caPublished ? ($attendanceMark + $assignmentMark + $activityMark + $effectiveCaMarks) : 0);
            $totalMarks = round($totalCAInternal + ($finalPublished ? $effectiveFinalMarks : 0), 2);

            $allPublished = $caPublished && $finalPublished;
            $letterGrade = '--';
            $gradePoint = 0;
            $remark = '';
            $isPassed = false;

            if ($allPublished) {
                foreach ($grades as $grade) {
                    if ($totalMarks >= $grade->min_mark && $totalMarks <= $grade->max_mark) {
                        $letterGrade = $grade->title;
                        $gradePoint = (float) $grade->point;
                        $remark = $grade->remark ?? ($totalMarks >= 50 ? 'Pass' : 'Fail');
                        break;
                    }
                }
                $isPassed = $totalMarks >= 50;
            }

            if ($isPassed) {
                $totalCreditsEarned += $creditHour;
            }
            $totalGradePoints += $gradePoint * $creditHour;

            $coursesData[] = [
                'subject' => $subject,
                'credit_hour' => $creditHour,
                'attendance_mark' => $displayATT,
                'ca_marks' => $displayCA,
                'exam_marks' => $examMarks,
                'total_marks' => $totalMarks,
                'letter_grade' => $letterGrade,
                'grade_point' => $gradePoint,
                'remark' => $remark,
                'is_passed' => $isPassed,
                'ca_published' => $caPublished,
                'final_published' => $finalPublished,
                'all_published' => $allPublished,
            ];
        }

        $gpa = $totalCreditsRegistered > 0
            ? round($totalGradePoints / $totalCreditsRegistered, 2)
            : 0;

        $setting = Setting::first() ?? (object) ['title' => 'PAX HIGHER INSTITUTE', 'logo_path' => null, 'address' => '', 'phone' => '', 'email' => ''];

        $data = [
            'student' => $student,
            'enrollment' => $enrollment,
            'session' => $session,
            'semester' => $semester,
            'examType' => $examType,
            'coursesData' => $coursesData,
            'totalCreditsRegistered' => $totalCreditsRegistered,
            'totalCreditsEarned' => $totalCreditsEarned,
            'gpa' => $gpa,
            'grades' => $grades,
            'setting' => $setting,
            'generatedAt' => now(),
        ];

        $pdf = Pdf::loadView('student.exam-results.pdf', $data);
        $pdf->setPaper('A4', 'portrait');

        $filename = 'Exam_Results_' . ($student->student_id ?? $student->id)
            . '_' . ($session->title ?? 'session')
            . '_' . ($semester->title ?? 'semester')
            . '.pdf';

        $filename = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $filename);

        return $pdf->download($filename);
    }

    protected function normalizeFilterValue(string $value): ?int
    {
        if ($value === '' || $value === '0') {
            return null;
        }

        return (int) $value;
    }
}
