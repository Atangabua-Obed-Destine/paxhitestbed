<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== FINDING PROGRAMS AND SEMESTERS ===\n\n";

// 1. List all programs
echo "1. All Active Programs:\n";
$programs = \App\Models\Program::where('status', 1)->get();
foreach ($programs as $prog) {
    echo "   ID: {$prog->id} - {$prog->title}\n";
}
echo "\n";

// 2. List all semesters
echo "2. All Active Semesters:\n";
$semesters = \App\Models\Semester::where('status', 1)->get();
foreach ($semesters as $sem) {
    echo "   ID: {$sem->id} - {$sem->title}\n";
}
echo "\n";

// 3. List all sections
echo "3. All Active Sections:\n";
$sections = \App\Models\Section::where('status', 1)->get();
if ($sections->count() > 0) {
    foreach ($sections as $sec) {
        echo "   ID: {$sec->id} - {$sec->title} (Seats: {$sec->seat})\n";
    }
} else {
    echo "   ❌ No sections found!\n";
}
echo "\n";

// 4. Check program_semester_sections table
echo "4. Program-Semester-Section Associations (All):\n";
$associations = \DB::table('program_semester_sections')->limit(20)->get();
if ($associations->count() > 0) {
    foreach ($associations as $assoc) {
        $prog = \App\Models\Program::find($assoc->program_id);
        $sem = \App\Models\Semester::find($assoc->semester_id);
        $sec = \App\Models\Section::find($assoc->section_id);
        echo "   Program: {$prog->title} | Semester: {$sem->title} | Section: {$sec->title}\n";
    }
} else {
    echo "   ❌ No associations found - sections need to be assigned!\n";
}

echo "\n=== COMPLETE ===\n";
