<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/', 'GET');
$response = $kernel->handle($request);
$kernel->bootstrap();

use App\Models\Subject;
use Illuminate\Support\Facades\DB;

echo "=== Checking Subject ID 58 ===\n\n";

$subject = Subject::find(58);
if($subject) {
    echo "Subject ID: {$subject->id}\n";
    echo "Code: {$subject->code}\n";
    echo "Title: {$subject->title}\n";
    echo "Status: {$subject->status}\n\n";
    
    echo "=== Programs for this subject ===\n";
    $programs = DB::table('program_subject')
        ->where('subject_id', 58)
        ->get();
    echo "Programs: " . $programs->count() . "\n";
    foreach($programs as $prog) {
        echo "  - Program ID: {$prog->program_id}\n";
    }
    
    echo "\n=== Class Routines for this subject ===\n";
    $routines = DB::table('class_routines')
        ->where('subject_id', 58)
        ->get();
    echo "Class routines: " . $routines->count() . "\n";
    foreach($routines as $routine) {
        echo "  - Session ID: {$routine->session_id}, Teacher ID: {$routine->teacher_id}\n";
    }
} else {
    echo "Subject not found\n";
}

echo "\n=== Let's check the courses that SHOULD show ===\n";
$subjects = Subject::where('status', '1')
    ->whereHas('classes', function($q) {
        $q->where('session_id', 1);
    })
    ->whereHas('programs', function($q) {
        $q->where('program_id', 32);
    })
    ->orderBy('code', 'asc')
    ->get();

echo "Courses with class routines for Program 32, Session 1:\n";
foreach($subjects as $subj) {
    echo "  - ID: {$subj->id}, Code: {$subj->code}, Title: {$subj->title}\n";
}
