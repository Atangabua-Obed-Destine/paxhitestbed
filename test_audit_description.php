<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Fee;

echo "=== TESTING AUDIT DESCRIPTION ===\n\n";

// Get a fee that was recently updated
$fee = Fee::find(10);

if ($fee) {
    echo "Fee ID: {$fee->id}\n";
    echo "Student Enroll ID: {$fee->student_enroll_id}\n\n";
    
    // Check if studentEnroll exists
    echo "Checking studentEnroll relationship:\n";
    $studentEnroll = $fee->studentEnroll;
    if ($studentEnroll) {
        echo "  ✓ StudentEnroll found: ID {$studentEnroll->id}\n";
        echo "  - Student ID: {$studentEnroll->student_id}\n";
        
        // Check if student exists
        echo "\nChecking student relationship:\n";
        $student = $studentEnroll->student;
        if ($student) {
            echo "  ✓ Student found: ID {$student->id}\n";
            echo "  - Student Name: {$student->name}\n";
        } else {
            echo "  ✗ Student NOT found (student_id: {$studentEnroll->student_id})\n";
        }
    } else {
        echo "  ✗ StudentEnroll NOT found (student_enroll_id: {$fee->student_enroll_id})\n";
    }
    
    echo "\nChecking category relationship:\n";
    $category = $fee->category;
    if ($category) {
        echo "  ✓ Category found: {$category->title}\n";
    } else {
        echo "  ✗ Category NOT found\n";
    }
    
    echo "\n--- Testing getAuditDescription ---\n";
    $description = $fee->getAuditDescription('updated');
    echo "Description: {$description}\n";
    
} else {
    echo "Fee #10 not found\n";
}

echo "\n=== TEST COMPLETE ===\n";
