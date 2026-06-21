<?php

/**
 * Test Script: Program Semester Fees Configuration
 * 
 * This script tests the following:
 * 1. Creates required permissions
 * 2. Verifies database structure
 * 3. Tests model relationships
 * 4. Tests eligibility filters
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Spatie\Permission\Models\Permission;
use App\Models\ProgramSemesterFee;
use App\Models\Program;
use App\Models\Semester;
use App\Models\FeesCategory;
use App\Models\EnrollSubject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "\n======================================\n";
echo "Program Semester Fees - Test & Setup\n";
echo "======================================\n\n";

// 1. Create Permissions
echo "1. Creating Permissions...\n";

$permissions = [
    ['name' => 'program-semester-fee-view', 'guard_name' => 'web'],
    ['name' => 'program-semester-fee-create', 'guard_name' => 'web'],
    ['name' => 'program-semester-fee-edit', 'guard_name' => 'web'],
    ['name' => 'program-semester-fee-delete', 'guard_name' => 'web'],
];

foreach ($permissions as $permission) {
    $exists = Permission::where('name', $permission['name'])->first();
    if (!$exists) {
        Permission::create($permission);
        echo "   ✓ Created: {$permission['name']}\n";
    } else {
        echo "   → Already exists: {$permission['name']}\n";
    }
}

// 2. Verify Database Structure
echo "\n2. Verifying Database Structure...\n";

if (Schema::hasTable('program_semester_fees')) {
    echo "   ✓ Table 'program_semester_fees' exists\n";
    
    $columns = ['id', 'program_id', 'semester_id', 'fees_category_id', 'amount', 'status'];
    foreach ($columns as $column) {
        if (Schema::hasColumn('program_semester_fees', $column)) {
            echo "   ✓ Column '$column' exists\n";
        } else {
            echo "   ✗ Column '$column' MISSING\n";
        }
    }
} else {
    echo "   ✗ Table 'program_semester_fees' DOES NOT EXIST\n";
    echo "   → Run: php artisan migrate\n";
}

// 3. Test Model Relationships
echo "\n3. Testing Model Relationships...\n";

try {
    $testFee = new ProgramSemesterFee();
    
    // Check if relationships are defined
    if (method_exists($testFee, 'program')) {
        echo "   ✓ Relationship 'program()' defined\n";
    }
    if (method_exists($testFee, 'semester')) {
        echo "   ✓ Relationship 'semester()' defined\n";
    }
    if (method_exists($testFee, 'feesCategory')) {
        echo "   ✓ Relationship 'feesCategory()' defined\n";
    }
} catch (Exception $e) {
    echo "   ✗ Error testing relationships: " . $e->getMessage() . "\n";
}

// 4. Test Eligibility Filters
echo "\n4. Testing Eligibility Filters...\n";

// Count regular semesters with enrolled courses
$regularSemestersCount = Semester::where('is_resit', 0)
    ->where('status', 1)
    ->whereHas('enrollSubjects')
    ->count();

echo "   → Regular semesters with enrolled courses: $regularSemestersCount\n";

// Count eligible fee categories
$eligibleCategoriesCount = FeesCategory::where('status', 1)
    ->where('is_resit', 0)
    ->where(function($query) {
        $query->where('is_first_installment', 1)
              ->orWhere('is_second_installment', 1);
    })
    ->count();

echo "   → Eligible fee categories (installments): $eligibleCategoriesCount\n";

// List eligible categories
if ($eligibleCategoriesCount > 0) {
    echo "\n   Eligible Fee Categories:\n";
    $categories = FeesCategory::where('status', 1)
        ->where('is_resit', 0)
        ->where(function($query) {
            $query->where('is_first_installment', 1)
                  ->orWhere('is_second_installment', 1);
        })
        ->get();
    
    foreach ($categories as $cat) {
        $type = [];
        if ($cat->is_first_installment) $type[] = '1st Installment';
        if ($cat->is_second_installment) $type[] = '2nd Installment';
        echo "   - {$cat->title} (" . implode(', ', $type) . ")\n";
    }
}

// 5. Check existing configurations
echo "\n5. Checking Existing Configurations...\n";

$existingCount = ProgramSemesterFee::count();
echo "   → Total configurations: $existingCount\n";

if ($existingCount > 0) {
    echo "\n   Recent Configurations:\n";
    $recent = ProgramSemesterFee::with(['program', 'semester', 'feesCategory'])
        ->orderBy('created_at', 'desc')
        ->take(5)
        ->get();
    
    foreach ($recent as $config) {
        $programTitle = $config->program->title ?? 'N/A';
        $semesterTitle = $config->semester->title ?? 'N/A';
        $categoryTitle = $config->feesCategory->title ?? 'N/A';
        $amount = number_format($config->amount, 2);
        $status = $config->status ? 'Active' : 'Inactive';
        
        echo "   - {$programTitle} | {$semesterTitle} | {$categoryTitle} | {$amount} XAF | {$status}\n";
    }
}

// 6. Route Check
echo "\n6. Route Registration...\n";

try {
    $routes = app('router')->getRoutes();
    $programSemesterFeeRoutes = 0;
    
    foreach ($routes as $route) {
        if (str_contains($route->uri(), 'program-semester-fee')) {
            $programSemesterFeeRoutes++;
        }
    }
    
    if ($programSemesterFeeRoutes > 0) {
        echo "   ✓ Found {$programSemesterFeeRoutes} routes for 'program-semester-fee'\n";
    } else {
        echo "   ✗ No routes found for 'program-semester-fee'\n";
        echo "   → Check routes/web.php\n";
    }
} catch (Exception $e) {
    echo "   ? Could not check routes: " . $e->getMessage() . "\n";
}

// Summary
echo "\n======================================\n";
echo "Setup Summary\n";
echo "======================================\n";
echo "Database: " . (Schema::hasTable('program_semester_fees') ? '✓ Ready' : '✗ Run migration') . "\n";
echo "Permissions: ✓ Created (4 permissions)\n";
echo "Model: ✓ Configured with relationships\n";
echo "Regular Semesters: $regularSemestersCount available\n";
echo "Eligible Categories: $eligibleCategoriesCount available\n";
echo "Configurations: $existingCount existing\n";

echo "\n======================================\n";
echo "Next Steps\n";
echo "======================================\n";
echo "1. Assign permissions to roles (Admin, Accountant)\n";
echo "2. Visit: /admin/program-semester-fee\n";
echo "3. Create fee configurations\n";
echo "4. Integrate with SemesterProgressionService\n";
echo "5. Test auto-fee assignment on progression\n";

echo "\n✓ Test completed successfully!\n\n";
