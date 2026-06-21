<?php

/**
 * This script will update all controllers to apply staff assignment filtering for faculties
 */

$controllersToFix = [
    'app/Http/Controllers/Admin/ReportController.php',
    'app/Http/Controllers/Admin/StudentAttendanceController.php',
    'app/Http/Controllers/Admin/FeesStudentController.php',
    'app/Http/Controllers/Admin/ExamMarkingController.php',
    'app/Http/Controllers/Admin/SubjectMarkingController.php',
    'app/Http/Controllers/Admin/StudentGroupEnrollController.php',
    'app/Http/Controllers/Admin/CourseCompleteController.php',
    'app/Http/Controllers/Admin/ExamRoutineController.php',
    'app/Http/Controllers/Admin/StudentExamConfigController.php',
    'app/Http/Controllers/Admin/MaxCreditConfigController.php',
    'app/Http/Controllers/Admin/AdmitCardController.php',
    'app/Http/Controllers/Admin/AssignmentController.php',
    'app/Http/Controllers/Admin/ContentController.php',
    'app/Http/Controllers/Admin/EmailNotifyController.php',
    'app/Http/Controllers/Admin/EnrollSubjectController.php',
    'app/Http/Controllers/Admin/ExamAttendanceController.php',
    'app/Http/Controllers/Admin/FeesMasterController.php',
    'app/Http/Controllers/Admin/HostelStudentController.php',
    'app/Http/Controllers/Admin/LibraryStudentController.php',
    'app/Http/Controllers/Admin/NoticeController.php',
    'app/Http/Controllers/Admin/SMSNotifyController.php',
    'app/Http/Controllers/Admin/StudentIdCardController.php',
    'app/Http/Controllers/Admin/TransportStudentController.php',
];

$searchPattern = "/\\\$data\['faculties'\] = Faculty::where\('status', '1'\)->orderBy\('title', 'asc'\)->get\(\);/";
$replacement = "\$facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');\n        \$data['faculties'] = StaffAssignmentService::filterFaculties(\$facultyQuery)->get();";

$searchPattern2 = "/\\\$faculties = Faculty::where\('status', '1'\)->orderBy\('title', 'asc'\)->get\(\);/";
$replacement2 = "\$facultyQuery = Faculty::where('status', '1')->orderBy('title', 'asc');\n        \$faculties = StaffAssignmentService::filterFaculties(\$facultyQuery)->get();";

$importSearch = "use App\Models\Faculty;";
$importAdd = "use App\Services\StaffAssignmentService;";

$fixed = 0;
$skipped = 0;

foreach ($controllersToFix as $file) {
    if (!file_exists($file)) {
        echo "⚠️  Skipped (not found): $file\n";
        $skipped++;
        continue;
    }
    
    $content = file_get_contents($file);
    $originalContent = $content;
    
    // Add import if not exists
    if (strpos($content, 'StaffAssignmentService') === false) {
        if (strpos($content, $importSearch) !== false) {
            $content = str_replace($importSearch, $importSearch . "\n" . $importAdd, $content);
        }
    }
    
    // Replace Faculty queries
    $content = preg_replace($searchPattern, $replacement, $content);
    $content = preg_replace($searchPattern2, $replacement2, $content);
    
    if ($content !== $originalContent) {
        file_put_contents($file, $content);
        echo "✅ Fixed: $file\n";
        $fixed++;
    } else {
        echo "⏭️  No changes needed: $file\n";
        $skipped++;
    }
}

echo "\n=== Summary ===\n";
echo "Fixed: $fixed controllers\n";
echo "Skipped: $skipped controllers\n";
echo "\n✅ All controllers have been updated!\n";
