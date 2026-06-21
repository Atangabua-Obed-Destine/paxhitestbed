<?php

/**
 * Test Script for Course Registration Logic
 * 
 * This script verifies the new course registration filtering logic:
 * 1. Shows only courses for current semester
 * 2. Shows courses from same semester type but previous years
 * 3. Excludes validated courses (marks >= 50%)
 * 4. Blocks registration during resit semesters
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Student;
use App\Models\StudentEnroll;
use App\Models\Semester;
use App\Models\EnrollSubject;
use App\Models\Subject;

echo "=== Course Registration Logic Test ===\n\n";

// Test 1: Find a student with active enrollment
echo "Test 1: Finding student with active enrollment...\n";
$student = Student::whereHas('currentEnroll')->first();

if (!$student) {
    echo "❌ No student found with active enrollment\n";
    exit;
}

echo "✓ Found student: {$student->first_name} {$student->last_name} (ID: {$student->student_id})\n";
$student->load('currentEnroll.semester', 'currentEnroll.program', 'currentEnroll.section', 'studentEnrolls.subjectMarks');

$currentEnroll = $student->currentEnroll;
$currentSemester = $currentEnroll->semester;

echo "\nCurrent Enrollment Details:\n";
echo "- Program: {$currentEnroll->program->title}\n";
echo "- Semester: {$currentSemester->title}\n";
echo "- Year: {$currentSemester->year}\n";
echo "- Semester Type: " . ($currentSemester->semester_type == 1 ? 'First Semester' : 'Second Semester') . "\n";
echo "- Is Resit: " . ($currentSemester->is_resit ? 'Yes' : 'No') . "\n";
echo "- Section: {$currentEnroll->section->title}\n\n";

// Test 2: Check if resit semester
echo "Test 2: Checking resit semester status...\n";
$isResitSemester = (bool) $currentSemester->is_resit;
if ($isResitSemester) {
    echo "⚠️  This is a RESIT semester - course registration should be BLOCKED\n";
} else {
    echo "✓ Not a resit semester - course registration is allowed\n";
}
echo "\n";

// Test 3: Get eligible subjects
echo "Test 3: Finding eligible subjects...\n";
$currentYear = $currentSemester->year;
$currentSemesterType = $currentSemester->semester_type ?? 1;

// Get subjects for current semester
$enrollSubject = EnrollSubject::where('program_id', $currentEnroll->program_id)
    ->where('semester_id', $currentEnroll->semester_id)
    ->where('section_id', $currentEnroll->section_id)
    ->first();

$currentSemesterSubjectIds = $enrollSubject ? $enrollSubject->subjects()->pluck('subject_id')->toArray() : [];
echo "✓ Found " . count($currentSemesterSubjectIds) . " subjects in current semester\n";

// Get subjects from same semester type but previous years
$previousYearsSemesters = Semester::where('status', 1)
    ->where('semester_type', $currentSemesterType)
    ->where('year', '<=', $currentYear)
    ->where('id', '!=', $currentEnroll->semester_id)
    ->get();

echo "✓ Found " . $previousYearsSemesters->count() . " semesters of same type (type {$currentSemesterType}) from previous years\n";

$previousYearsSubjectIds = [];
foreach ($previousYearsSemesters as $semester) {
    $prevEnrollSubjects = EnrollSubject::where('program_id', $currentEnroll->program_id)
        ->where('semester_id', $semester->id)
        ->where('section_id', $currentEnroll->section_id)
        ->get();
    
    foreach ($prevEnrollSubjects as $enrollSub) {
        $subjectIds = $enrollSub->subjects()->pluck('subject_id')->toArray();
        $previousYearsSubjectIds = array_merge($previousYearsSubjectIds, $subjectIds);
        echo "  - Semester: {$semester->title} (Year {$semester->year}) has " . count($subjectIds) . " subjects\n";
    }
}

$previousYearsSubjectIds = array_unique($previousYearsSubjectIds);
echo "✓ Total unique subjects from previous years: " . count($previousYearsSubjectIds) . "\n\n";

// Test 4: Find validated subjects
echo "Test 4: Finding validated subjects (marks >= 50%)...\n";
$validatedSubjectIds = [];
foreach ($student->studentEnrolls as $enroll) {
    if (isset($enroll->subjectMarks)) {
        foreach ($enroll->subjectMarks as $mark) {
            if ($mark->total_marks >= 50) {
                $validatedSubjectIds[] = $mark->subject_id;
                $subject = Subject::find($mark->subject_id);
                if ($subject) {
                    echo "  ✓ Validated: {$subject->code} - {$subject->title} (Marks: {$mark->total_marks}%)\n";
                }
            }
        }
    }
}
$validatedSubjectIds = array_unique($validatedSubjectIds);
echo "Total validated subjects: " . count($validatedSubjectIds) . "\n\n";

// Test 5: Calculate available subjects
echo "Test 5: Calculating available subjects for registration...\n";
$allEligibleSubjectIds = array_unique(array_merge($currentSemesterSubjectIds, $previousYearsSubjectIds));
$availableSubjectIds = array_diff($allEligibleSubjectIds, $validatedSubjectIds);

echo "- Total eligible subjects (current + previous years): " . count($allEligibleSubjectIds) . "\n";
echo "- Minus validated subjects: " . count($validatedSubjectIds) . "\n";
echo "- Available for registration: " . count($availableSubjectIds) . "\n\n";

if (count($availableSubjectIds) > 0) {
    echo "Available subjects list:\n";
    $subjects = Subject::whereIn('id', $availableSubjectIds)->orderBy('code')->get();
    foreach ($subjects as $subject) {
        $typeLabel = $subject->subject_type == 0 ? 'Optional' : ($subject->subject_type == 1 ? 'Compulsory' : 'University Req');
        echo "  - {$subject->code}: {$subject->title} ({$typeLabel}, {$subject->credit_hour} credits)\n";
    }
} else {
    echo "⚠️  No subjects available for registration\n";
}

echo "\n=== Test Complete ===\n";
