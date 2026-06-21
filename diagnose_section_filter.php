<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== SECTION FILTER DIAGNOSIS ===\n\n";

// 1. Check total sections
$totalSections = \App\Models\Section::where('status', 1)->count();
echo "1. Total Active Sections: {$totalSections}\n\n";

// 2. Check sections
if ($totalSections > 0) {
    $sections = \App\Models\Section::where('status', 1)->get();
    foreach ($sections as $section) {
        echo "   Section: {$section->title} (ID: {$section->id})\n";
    }
    echo "\n";
}

// 3. Check semester_programs table
$semesterPrograms = \DB::table('semester_programs')->count();
echo "2. Total Semester-Program Associations: {$semesterPrograms}\n\n";

// 4. Sample semester_program data
if ($semesterPrograms > 0) {
    $sample = \DB::table('semester_programs')->limit(5)->get();
    echo "   Sample semester_programs:\n";
    foreach ($sample as $sp) {
        echo "   Program ID: {$sp->program_id}, Semester ID: {$sp->semester_id}, Section ID: {$sp->section_id}\n";
    }
    echo "\n";
}

// 5. Test the filter query
echo "3. Testing Filter Query:\n";
$testProgramId = \App\Models\Program::where('status', 1)->first()->id ?? null;
$testSemesterId = \App\Models\Semester::where('status', 1)->first()->id ?? null;

if ($testProgramId && $testSemesterId) {
    echo "   Test Program ID: {$testProgramId}\n";
    echo "   Test Semester ID: {$testSemesterId}\n";
    
    $rows = \App\Models\Section::where('status', 1);
    $rows->with('semesterPrograms')->whereHas('semesterPrograms', function ($query) use ($testProgramId, $testSemesterId){
        $query->where('program_id', $testProgramId);
        $query->where('semester_id', $testSemesterId);
    });
    $sections = $rows->get();
    
    echo "   Matching Sections: {$sections->count()}\n";
    foreach ($sections as $section) {
        echo "      - {$section->title}\n";
    }
} else {
    echo "   No test data available\n";
}

echo "\n=== DIAGNOSIS COMPLETE ===\n";
