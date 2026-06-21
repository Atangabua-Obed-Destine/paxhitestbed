<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\StudentEnroll;
use App\Models\SubjectMarking;
use App\Models\Subject;
use App\Models\Grade;

echo "=== Testing Fixed Resit Logic ===\n\n";

// Simulate the updated getFailedCourses logic
function testGetFailedCourses($enrollmentId) {
    $enrollment = StudentEnroll::with('student', 'semester', 'subjects')->find($enrollmentId);
    $grades = Grade::where('status', 1)->orderBy('min_mark', 'desc')->get();
    
    $failed = [];
    $student = $enrollment->student;
    
    echo "Testing enrollment {$enrollmentId} ({$enrollment->semester->title}):\n";
    
    foreach ($enrollment->subjects as $subject) {
        $marking = SubjectMarking::where('student_enroll_id', $enrollment->id)
            ->where('subject_id', $subject->id)
            ->first();
        
        if (!$marking) {
            continue;
        }
        
        // Check if published (simplified - using is_visible_to_student)
        if (!$marking->is_visible_to_student) {
            continue;
        }
        
        $total_marks = round($marking->total_marks);
        
        if ($total_marks < 50) {
            echo "  - Found failed course: {$subject->title} ({$total_marks}%)\n";
            
            // NEW LOGIC: Check other enrollments
            $otherEnrollments = StudentEnroll::where('student_id', $student->id)
                ->where('status', 1)
                ->where('id', '!=', $enrollment->id)
                ->whereHas('subjects', function($query) use ($subject) {
                    $query->where('subjects.id', $subject->id);
                })
                ->get();
            
            $shouldSkip = false;
            
            foreach ($otherEnrollments as $otherEnroll) {
                echo "    * Found in enrollment {$otherEnroll->id} ({$otherEnroll->semester->title})\n";
                
                $otherMarking = SubjectMarking::where('student_enroll_id', $otherEnroll->id)
                    ->where('subject_id', $subject->id)
                    ->first();
                
                if (!$otherMarking) {
                    echo "      → No marks yet - CURRENTLY TAKING IT\n";
                    echo "      → SKIP this older enrollment\n";
                    $shouldSkip = true;
                    break;
                }
                
                if (!$otherMarking->is_visible_to_student) {
                    echo "      → Marks not published yet - BEING GRADED\n";
                    echo "      → SKIP this older enrollment\n";
                    $shouldSkip = true;
                    break;
                }
                
                $otherTotalMarks = round($otherMarking->total_marks);
                echo "      → Published marks: {$otherTotalMarks}%\n";
                
                if ($otherTotalMarks >= 50) {
                    echo "      → PASSED in newer enrollment\n";
                    echo "      → SKIP this older enrollment\n";
                    $shouldSkip = true;
                    break;
                }
                
                echo "      → FAILED in newer enrollment too\n";
                
                if ($otherEnroll->id > $enrollment->id) {
                    echo "      → Other enrollment is MORE RECENT (ID {$otherEnroll->id} > {$enrollment->id})\n";
                    echo "      → SKIP this older enrollment\n";
                    $shouldSkip = true;
                    break;
                } else {
                    echo "      → This enrollment is MORE RECENT (ID {$enrollment->id} > {$otherEnroll->id})\n";
                    echo "      → KEEP this enrollment for resit\n";
                }
            }
            
            if (!$shouldSkip) {
                echo "    ✅ SHOW IN RESIT LIST\n";
                $failed[] = $subject->title;
            } else {
                echo "    ❌ HIDE FROM RESIT LIST\n";
            }
            
            echo "\n";
        }
    }
    
    return $failed;
}

echo "=== Test Case: Student PAX25TSC002H ===\n\n";

echo "--- Enrollment 68 (Y1 - FIRST SEMESTER Y1) ---\n";
$failed68 = testGetFailedCourses(68);

echo "\n--- Enrollment 75 (Y2 - FIRST SEMESTER Y2) ---\n";
$failed75 = testGetFailedCourses(75);

echo "\n=== RESULTS ===\n";
echo "Enrollment 68 - Resit eligible courses: " . count($failed68) . "\n";
if (count($failed68) > 0) {
    foreach ($failed68 as $course) {
        echo "  - {$course}\n";
    }
}

echo "\nEnrollment 75 - Resit eligible courses: " . count($failed75) . "\n";
if (count($failed75) > 0) {
    foreach ($failed75 as $course) {
        echo "  - {$course}\n";
    }
}

echo "\n✅ EXPECTED BEHAVIOR:\n";
echo "  - Enrollment 68: Should have 0 courses (older enrollment, failed again in 75)\n";
echo "  - Enrollment 75: Should show failed courses (most recent enrollment)\n";

echo "\n=== END ===\n";
