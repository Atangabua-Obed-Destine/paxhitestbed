<?php
/**
 * Test Script for Course Registration Performance Summary and Carry-Over Courses
 * Tests the logic for calculating student performance and identifying courses to retake
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Course Registration Summary Test ===\n\n";

// Test with a student (adjust ID as needed)
$studentId = 6;

try {
    $student = \App\Models\Student::with([
        'batch',
        'program',
        'studentEnrolls.subjectMarks.subject',
        'studentEnrolls.subjects',
        'studentEnrolls.session',
        'studentEnrolls.semester',
        'studentEnrolls.section',
    ])->find($studentId);

    if (!$student) {
        echo "❌ Student with ID {$studentId} not found.\n";
        echo "Please update the \$studentId variable in this test script.\n";
        exit(1);
    }

    echo "✓ Testing for: {$student->first_name} {$student->last_name} (ID: {$student->student_id})\n";
    echo "✓ Program: " . ($student->program->title ?? 'N/A') . "\n\n";

    // Get current enrollment
    $currentEnroll = $student->studentEnrolls()
        ->where('status', '1')
        ->orderByDesc('id')
        ->first();

    if (!$currentEnroll) {
        echo "⚠️  Student has no current enrollment.\n";
        exit(0);
    }

    $currentEnroll->load('semester', 'session', 'section');
    echo "✓ Current Enrollment: {$currentEnroll->session->title} | {$currentEnroll->semester->title}\n";
    echo "✓ Semester Type: " . ($currentEnroll->semester->semester_type ?? 'N/A') . "\n";
    echo "✓ Year: " . ($currentEnroll->semester->year ?? 'N/A') . "\n\n";

    // Get grades for calculations
    $grades = \App\Models\Grade::where('status', '1')->orderBy('min_mark', 'desc')->get();

    echo "=== PERFORMANCE SUMMARY TEST ===\n\n";

    // Calculate performance metrics
    $totalCreditsAttempted = 0;
    $totalCreditsEarned = 0;
    $totalQualityPoints = 0;
    $totalCourses = 0;
    $passedCourses = 0;
    $failedCourses = 0;
    $compulsoryPassed = 0;
    $compulsoryTotal = 0;
    $universityReqPassed = 0;
    $universityReqTotal = 0;

    foreach ($student->studentEnrolls as $enroll) {
        if (isset($enroll->subjectMarks)) {
            foreach ($enroll->subjectMarks as $mark) {
                if (!isset($mark->subject)) continue;

                $marksPer = round($mark->total_marks);
                $creditHour = (float) $mark->subject->credit_hour;
                $subjectType = $mark->subject->subject_type;

                $totalCourses++;
                $totalCreditsAttempted += $creditHour;

                if ($subjectType == 1) {
                    $compulsoryTotal++;
                } elseif ($subjectType == 2) {
                    $universityReqTotal++;
                }

                foreach ($grades as $grade) {
                    if ($marksPer >= $grade->min_mark && $marksPer <= $grade->max_mark) {
                        $gradePoint = (float) $grade->point;
                        $totalQualityPoints += $gradePoint * $creditHour;

                        if ($marksPer >= 50) {
                            $passedCourses++;
                            $totalCreditsEarned += $creditHour;

                            if ($subjectType == 1) {
                                $compulsoryPassed++;
                            } elseif ($subjectType == 2) {
                                $universityReqPassed++;
                            }
                        } else {
                            $failedCourses++;
                        }
                        break;
                    }
                }
            }
        }
    }

    $cgpa = $totalCreditsAttempted > 0 ? $totalQualityPoints / $totalCreditsAttempted : 0;
    $completionPercentage = $totalCourses > 0 ? ($passedCourses / $totalCourses) * 100 : 0;

    echo "📊 CGPA: " . number_format($cgpa, 2) . "\n";
    echo "📊 Credits: " . number_format($totalCreditsEarned, 1) . " / " . number_format($totalCreditsAttempted, 1) . "\n";
    echo "📊 Courses: {$passedCourses} passed, {$failedCourses} failed (Total: {$totalCourses})\n";
    echo "📊 Completion: " . number_format($completionPercentage, 1) . "%\n";
    echo "📊 Compulsory: {$compulsoryPassed}/{$compulsoryTotal}\n";
    echo "📊 University Req: {$universityReqPassed}/{$universityReqTotal}\n";

    $graduationReady = ($compulsoryTotal > 0 && $compulsoryPassed == $compulsoryTotal && 
                       $universityReqTotal > 0 && $universityReqPassed == $universityReqTotal);
    echo "🎓 Graduation Ready: " . ($graduationReady ? "✓ YES" : "✗ NO") . "\n\n";

    echo "=== CARRY-OVER COURSES TEST ===\n\n";

    if (!$currentEnroll->semester) {
        echo "⚠️  Cannot determine carry-over courses (no semester info).\n";
        exit(0);
    }

    $currentSemesterType = $currentEnroll->semester->semester_type ?? 1;
    $currentYear = $currentEnroll->semester->year;

    echo "✓ Looking for carry-over courses:\n";
    echo "  - Semester Type: {$currentSemesterType}\n";
    echo "  - Years: ≤ {$currentYear}\n";
    echo "  - Validation Threshold: < 50%\n\n";

    // Get relevant semesters
    $relevantSemesters = \App\Models\Semester::where('status', 1)
        ->where('semester_type', $currentSemesterType)
        ->where('year', '<=', $currentYear)
        ->where('is_resit', '!=', 1)
        ->get();

    echo "✓ Found " . $relevantSemesters->count() . " relevant semester(s) with matching type:\n";
    foreach ($relevantSemesters as $sem) {
        echo "  - {$sem->title} (Year {$sem->year}, Type {$sem->semester_type})\n";
    }
    echo "\n";

    $carryOverCourses = [];
    $relevantSemesterIds = $relevantSemesters->pluck('id')->toArray();

    foreach ($student->studentEnrolls as $enroll) {
        if (!in_array($enroll->semester_id, $relevantSemesterIds)) {
            continue;
        }

        if (isset($enroll->subjectMarks)) {
            foreach ($enroll->subjectMarks as $mark) {
                if (!isset($mark->subject)) continue;

                $marksPer = round($mark->total_marks);
                
                if ($marksPer < 50) {
                    $subjectId = $mark->subject_id;
                    
                    if (isset($carryOverCourses[$subjectId])) {
                        if ($marksPer > $carryOverCourses[$subjectId]['best_marks']) {
                            $carryOverCourses[$subjectId]['best_marks'] = $marksPer;
                        }
                        $carryOverCourses[$subjectId]['attempts']++;
                    } else {
                        $gradeTitle = '';
                        foreach ($grades as $grade) {
                            if ($marksPer >= $grade->min_mark && $marksPer <= $grade->max_mark) {
                                $gradeTitle = $grade->title;
                                break;
                            }
                        }

                        $subjectTypeLabel = $mark->subject->subject_type == 1 ? 'COMPULSORY' : 
                                           ($mark->subject->subject_type == 2 ? 'UNIV REQ' : 'OPTIONAL');

                        $carryOverCourses[$subjectId] = [
                            'code' => $mark->subject->code,
                            'title' => $mark->subject->title,
                            'best_marks' => $marksPer,
                            'grade' => $gradeTitle,
                            'attempts' => 1,
                            'semester' => $enroll->semester->title ?? '',
                            'subject_type' => $mark->subject->subject_type,
                            'type_label' => $subjectTypeLabel,
                            'credit_hours' => $mark->subject->credit_hour,
                        ];
                    }
                }
            }
        }
    }

    if (empty($carryOverCourses)) {
        echo "✓ No carry-over courses found! Student has validated all courses from matching semester types.\n";
    } else {
        echo "⚠️  Found " . count($carryOverCourses) . " carry-over course(s) that need to be retaken:\n\n";
        
        usort($carryOverCourses, function($a, $b) {
            $typeOrder = [1 => 1, 2 => 2, 0 => 3];
            $typeA = $typeOrder[$a['subject_type']] ?? 4;
            $typeB = $typeOrder[$b['subject_type']] ?? 4;
            
            if ($typeA != $typeB) {
                return $typeA - $typeB;
            }
            
            return $b['attempts'] - $a['attempts'];
        });

        foreach ($carryOverCourses as $course) {
            $priority = ($course['subject_type'] == 1 || $course['subject_type'] == 2) ? '🔴 HIGH' : '⚪ NORMAL';
            echo "{$priority} | {$course['code']} - {$course['title']}\n";
            echo "       Type: {$course['type_label']} | Best Score: {$course['best_marks']}% ({$course['grade']}) | Attempts: {$course['attempts']}x\n";
            echo "       Last taken: {$course['semester']} | Credits: {$course['credit_hours']}\n\n";
        }

        $compulsoryCarryOver = collect($carryOverCourses)->where('subject_type', 1)->count();
        $universityReqCarryOver = collect($carryOverCourses)->where('subject_type', 2)->count();
        $optionalCarryOver = collect($carryOverCourses)->where('subject_type', 0)->count();

        echo "📌 Summary:\n";
        echo "   - Compulsory: {$compulsoryCarryOver}\n";
        echo "   - University Req: {$universityReqCarryOver}\n";
        echo "   - Optional: {$optionalCarryOver}\n";
        echo "   - Total: " . count($carryOverCourses) . "\n\n";

        if ($compulsoryCarryOver > 0 || $universityReqCarryOver > 0) {
            echo "⚠️  IMPORTANT: You must validate all compulsory and university requirement\n";
            echo "   carry-over courses to be eligible for graduation!\n";
        }
    }

    echo "\n=== TEST COMPLETED SUCCESSFULLY ===\n";

} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
