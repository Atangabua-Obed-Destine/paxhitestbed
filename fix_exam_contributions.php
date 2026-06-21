<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Exam;
use App\Models\ExamType;
use App\Services\ResultContributionService;

echo "\n=== Fixing Exam Contributions ===\n\n";

// Get exams with marks but no contribution
$examsNeedingFix = Exam::whereNotNull('achieve_marks')
    ->where(function($query) {
        $query->whereNull('contribution')
              ->orWhere('contribution', 0);
    })
    ->with(['type', 'subject'])
    ->get();

echo "Found {$examsNeedingFix->count()} exam records needing contribution fix\n\n";

$fixedCount = 0;
$errorCount = 0;

foreach ($examsNeedingFix as $exam) {
    try {
        // Get contribution from service
        $contribution = ResultContributionService::getExamTypeContribution(
            $exam->subject_id,
            $exam->exam_type_id
        );
        
        if ($contribution > 0) {
            $exam->contribution = $contribution;
            $exam->save();
            
            $fixedCount++;
            $matricule = $exam->studentEnroll ? $exam->studentEnroll->matricule : 'N/A';
            $subjectCode = $exam->subject ? $exam->subject->code : 'N/A';
            $examTypeTitle = $exam->type ? $exam->type->title : 'N/A';
            echo "✓ Fixed: Student {$matricule} - " .
                 "Subject {$subjectCode} - " .
                 "Exam Type {$examTypeTitle} - " .
                 "Contribution: {$contribution}%\n";
        } else {
            $errorCount++;
            $subjectCode = $exam->subject ? $exam->subject->code : 'N/A';
            $examTypeTitle = $exam->type ? $exam->type->title : 'N/A';
            echo "⚠ Skipped (no contribution configured): " .
                 "Subject {$subjectCode} - " .
                 "Exam Type {$examTypeTitle}\n";
        }
        
    } catch (\Exception $e) {
        $errorCount++;
        echo "❌ Error: {$e->getMessage()}\n";
    }
}

echo "\n=== SUMMARY ===\n";
echo "✓ Fixed: {$fixedCount} records\n";
echo "⚠ Skipped/Errors: {$errorCount} records\n";

if ($fixedCount > 0) {
    echo "\n✅ Now refresh your subject-marking page - exam marks should appear!\n";
}

echo "\n";
