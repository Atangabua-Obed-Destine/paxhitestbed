<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;

echo "Attempting to recreate grades table...\n";

try {
    // First try to drop if exists
    DB::statement('DROP TABLE IF EXISTS grades');
    echo "Dropped existing grades table reference.\n";
} catch (\Exception $e) {
    echo "Note: " . $e->getMessage() . "\n";
}

try {
    // Recreate the table
    Schema::create('grades', function (Blueprint $table) {
        $table->increments('id');
        $table->string('title')->unique();
        $table->decimal('point', 5, 2);
        $table->decimal('min_mark', 5, 2);
        $table->decimal('max_mark', 5, 2);
        $table->text('remark')->nullable();
        $table->boolean('status')->default('1');
        $table->timestamps();
    });
    
    echo "Grades table created successfully!\n";
    
    // Insert default grades
    $now = now();
    $grades = [
        ['title' => 'A', 'point' => 4.00, 'min_mark' => 80.00, 'max_mark' => 100.00, 'remark' => 'Excellent', 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
        ['title' => 'B+', 'point' => 3.50, 'min_mark' => 75.00, 'max_mark' => 79.99, 'remark' => 'Very Good', 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
        ['title' => 'B', 'point' => 3.00, 'min_mark' => 70.00, 'max_mark' => 74.99, 'remark' => 'Good', 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
        ['title' => 'C+', 'point' => 2.50, 'min_mark' => 65.00, 'max_mark' => 69.99, 'remark' => 'Above Average', 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
        ['title' => 'C', 'point' => 2.00, 'min_mark' => 60.00, 'max_mark' => 64.99, 'remark' => 'Average', 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
        ['title' => 'D+', 'point' => 1.50, 'min_mark' => 55.00, 'max_mark' => 59.99, 'remark' => 'Below Average', 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
        ['title' => 'D', 'point' => 1.00, 'min_mark' => 50.00, 'max_mark' => 54.99, 'remark' => 'Pass', 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
        ['title' => 'F', 'point' => 0.00, 'min_mark' => 0.00, 'max_mark' => 49.99, 'remark' => 'Fail', 'status' => 1, 'created_at' => $now, 'updated_at' => $now],
    ];
    
    DB::table('grades')->insert($grades);
    echo "Inserted " . count($grades) . " default grade records.\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "Done!\n";
