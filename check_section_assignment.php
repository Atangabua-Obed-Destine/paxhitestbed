<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== SECTION ASSIGNMENT CHECK ===\n\n";

// 1. Find the program
$program = \App\Models\Program::where('title', 'LIKE', '%SOFTWARE ENGINEERING%')->first();
if (!$program) {
    echo "❌ Program 'HND SOFTWARE ENGINEERING' not found\n";
    exit;
}
echo "✓ Program Found: {$program->title} (ID: {$program->id})\n";
echo "  Faculty: {$program->faculty->title}\n\n";

// 2. Find the semester
$semester = \App\Models\Semester::where('title', 'LIKE', '%FIRST SEMESTER Y1%')->first();
if (!$semester) {
    echo "❌ Semester 'FIRST SEMESTER Y1' not found\n";
    exit;
}
echo "✓ Semester Found: {$semester->title} (ID: {$semester->id})\n\n";

// 3. Check all active sections
$allSections = \App\Models\Section::where('status', 1)->get();
echo "3. Total Active Sections: {$allSections->count()}\n";
if ($allSections->count() > 0) {
    foreach ($allSections as $section) {
        echo "   - {$section->title} (ID: {$section->id}, Seats: {$section->seat})\n";
    }
} else {
    echo "   ❌ No sections found in the system!\n";
}
echo "\n";

// 4. Check program_semester_sections for this combination
$associations = \DB::table('program_semester_sections')
    ->where('program_id', $program->id)
    ->where('semester_id', $semester->id)
    ->get();

echo "4. Program-Semester-Section Associations:\n";
echo "   Looking for Program ID: {$program->id} + Semester ID: {$semester->id}\n";
echo "   Found: {$associations->count()} associations\n\n";

if ($associations->count() > 0) {
    foreach ($associations as $assoc) {
        $section = \App\Models\Section::find($assoc->section_id);
        echo "   ✓ Section: {$section->title} (ID: {$section->id})\n";
    }
} else {
    echo "   ❌ No sections assigned to this Program-Semester combination!\n\n";
    
    // 5. Show what needs to be done
    echo "5. SOLUTION:\n";
    echo "   Go to: http://localhost/paxhitest/admin/academic/section\n";
    
    if ($allSections->count() > 0) {
        echo "   \n   You have existing sections that need to be assigned:\n";
        foreach ($allSections as $section) {
            echo "   - Edit '{$section->title}' and assign it to:\n";
            echo "     Program: {$program->title}\n";
            echo "     Semester: {$semester->title}\n";
        }
    } else {
        echo "   \n   You need to:\n";
        echo "   1. Create new sections (e.g., 'Section A', 'Section B')\n";
        echo "   2. Assign them to:\n";
        echo "      Program: {$program->title}\n";
        echo "      Semester: {$semester->title}\n";
    }
}

// 6. Check all program-semester combinations for this program
echo "\n6. All Semester-Section assignments for '{$program->title}':\n";
$allProgramSections = \DB::table('program_semester_sections')
    ->where('program_id', $program->id)
    ->get();

if ($allProgramSections->count() > 0) {
    $grouped = $allProgramSections->groupBy('semester_id');
    foreach ($grouped as $semId => $sections) {
        $sem = \App\Models\Semester::find($semId);
        echo "   Semester: {$sem->title}\n";
        foreach ($sections as $assoc) {
            $sec = \App\Models\Section::find($assoc->section_id);
            echo "      - {$sec->title}\n";
        }
    }
} else {
    echo "   ❌ This program has NO sections assigned to ANY semester\n";
}

echo "\n=== CHECK COMPLETE ===\n";
