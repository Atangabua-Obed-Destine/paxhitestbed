<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== STUDENT LIFECYCLE STATUS TRACKING ===\n\n";

// Check students table for status columns
echo "1. STUDENTS TABLE STRUCTURE:\n";
$studentColumns = DB::select("DESCRIBE students");
foreach($studentColumns as $col) {
    if (stripos($col->Field, 'status') !== false || 
        stripos($col->Field, 'graduate') !== false || 
        stripos($col->Field, 'alumni') !== false ||
        stripos($col->Field, 'active') !== false ||
        stripos($col->Field, 'complete') !== false) {
        echo "  - {$col->Field} ({$col->Type}) - Nullable: {$col->Null} - Default: {$col->Default}\n";
    }
}

// Check for status_types or similar tables
echo "\n2. CHECKING FOR STATUS TRACKING TABLES:\n";
$tables = DB::select("SHOW TABLES");
foreach($tables as $table) {
    $tableArray = (array)$table;
    $tableName = reset($tableArray);
    if (stripos($tableName, 'status') !== false || 
        stripos($tableName, 'alumni') !== false ||
        stripos($tableName, 'graduate') !== false) {
        echo "  - Table found: {$tableName}\n";
        
        // Get column structure
        $cols = DB::select("DESCRIBE {$tableName}");
        echo "    Columns: ";
        $colNames = array_map(fn($c) => $c->Field, $cols);
        echo implode(', ', $colNames) . "\n";
    }
}

// Check student_status relationship table if exists
if (DB::getSchemaBuilder()->hasTable('student_status')) {
    echo "\n3. STUDENT_STATUS TABLE:\n";
    $statuses = DB::table('student_status')
        ->join('students', 'student_status.student_id', '=', 'students.id')
        ->join('status_types', 'student_status.status_type_id', '=', 'status_types.id')
        ->select('students.first_name', 'students.last_name', 'status_types.title')
        ->limit(10)
        ->get();
    
    foreach($statuses as $s) {
        echo "  {$s->first_name} {$s->last_name}: {$s->title}\n";
    }
}

// Check status_types table
if (DB::getSchemaBuilder()->hasTable('status_types')) {
    echo "\n4. STATUS_TYPES TABLE (Available Student Statuses):\n";
    $statusTypes = DB::table('status_types')
        ->where('status', '1')
        ->orderBy('title')
        ->get();
    
    foreach($statusTypes as $type) {
        echo "  - ID: {$type->id}, Title: {$type->title}\n";
    }
    
    echo "\n5. STUDENT STATUS DISTRIBUTION (via status_type_student):\n";
    $distribution = DB::table('status_type_student')
        ->join('status_types', 'status_type_student.status_type_id', '=', 'status_types.id')
        ->select('status_types.title', DB::raw('count(*) as total'))
        ->groupBy('status_types.id', 'status_types.title')
        ->orderByDesc('total')
        ->get();
    
    foreach($distribution as $dist) {
        echo "  {$dist->title}: {$dist->total} students\n";
    }
}

// Check for graduation/alumni tracking
if (DB::getSchemaBuilder()->hasTable('course_completes')) {
    echo "\n6. COURSE_COMPLETES TABLE (Graduation Tracking):\n";
    $graduates = DB::table('course_completes')
        ->join('students', 'course_completes.student_id', '=', 'students.id')
        ->select('students.first_name', 'students.last_name', 'course_completes.complete_date')
        ->limit(5)
        ->get();
    
    echo "  Total graduates: " . DB::table('course_completes')->count() . "\n";
    echo "  Recent graduates:\n";
    foreach($graduates as $grad) {
        echo "    - {$grad->first_name} {$grad->last_name} (Completed: {$grad->complete_date})\n";
    }
}

// Check students table status field
echo "\n7. STUDENT STATUS FIELD VALUES:\n";
$studentStatuses = DB::table('students')
    ->select('status', DB::raw('count(*) as total'))
    ->groupBy('status')
    ->get();

foreach($studentStatuses as $st) {
    echo "  Status {$st->status}: {$st->total} students\n";
}
