<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Subject;
use App\Models\ClassRoutine;
use App\User;

echo "=== Checking Subject and Class Routines ===\n\n";

$staff = User::where('staff_id', 'LIKE', '%0003%')->first();
$programId = 32; // HND ACCOUNTANCY
$sessionId = 1; // OCTOBER-2025

echo "Staff: {$staff->first_name} {$staff->last_name} (ID: {$staff->id})\n";
echo "Program ID: {$programId}\n";
echo "Session ID: {$sessionId}\n\n";

// Check if there are any class routines for this program and session
echo "=== Class Routines Check ===\n";
$classRoutines = ClassRoutine::where('session_id', $sessionId)
    ->whereHas('program', function($q) use ($programId) {
        $q->where('id', $programId);
    })
    ->with('subject')
    ->get();

echo "Total Class Routines for Program {$programId} and Session {$sessionId}: " . $classRoutines->count() . "\n\n";

if ($classRoutines->count() > 0) {
    echo "Class Routines Found:\n";
    foreach ($classRoutines->take(10) as $routine) {
        echo "  - Subject: [{$routine->subject->code}] {$routine->subject->title}\n";
        echo "    Teacher: {$routine->teacher->first_name} {$routine->teacher->last_name}\n";
        echo "    Day: {$routine->day}, Time: {$routine->start_time} - {$routine->end_time}\n\n";
    }
} else {
    echo "❌ NO CLASS ROUTINES FOUND!\n";
    echo "This is the problem - there are no class routines for:\n";
    echo "  - Program ID: {$programId}\n";
    echo "  - Session ID: {$sessionId}\n\n";
    echo "The filterTecherSubject and SubjectMarkingController both require\n";
    echo "subjects to have class routines (whereHas('classes')).\n\n";
    echo "Solution:\n";
    echo "1. Create class routines for courses in HND ACCOUNTANCY program\n";
    echo "2. OR modify the filters to not require class routines for staff with assignments\n";
}

// Check subjects in the program
echo "\n=== Subjects in Program ===\n";
$subjects = Subject::where('status', '1')
    ->with('programs')->whereHas('programs', function ($query) use ($programId){
        $query->where('program_id', $programId);
    })
    ->get();

echo "Total Subjects in Program {$programId}: " . $subjects->count() . "\n";

if ($subjects->count() > 0) {
    echo "\nChecking which subjects have class routines:\n";
    foreach ($subjects->take(15) as $subject) {
        $hasRoutine = ClassRoutine::where('subject_id', $subject->id)
            ->where('session_id', $sessionId)
            ->exists();
        
        $status = $hasRoutine ? '✅' : '❌';
        echo "  {$status} [{$subject->code}] {$subject->title}\n";
    }
}

echo "\n";
