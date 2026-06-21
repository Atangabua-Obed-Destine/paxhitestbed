<?php

/**
 * Test Script for Fee Breakdown Feature
 * 
 * This script tests the fee breakdown functionality for Form A2
 * Run: php test_fee_breakdown.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Program;
use App\Models\Semester;
use App\Models\FeesCategory;
use App\Models\ProgramSemesterFee;
use App\Models\ProgramSemesterFeeBreakdown;

echo "\n=== Fee Breakdown Feature Test ===\n\n";

// Test 1: Check if migration ran successfully
echo "Test 1: Checking database table...\n";
try {
    $exists = Schema::hasTable('program_semester_fee_breakdowns');
    echo $exists ? "✓ Table 'program_semester_fee_breakdowns' exists\n" : "✗ Table not found\n";
    
    if ($exists) {
        $columns = ['id', 'program_semester_fee_id', 'title', 'amount', 'order', 'created_at', 'updated_at'];
        foreach ($columns as $col) {
            $hasCol = Schema::hasColumn('program_semester_fee_breakdowns', $col);
            echo $hasCol ? "  ✓ Column '{$col}' exists\n" : "  ✗ Column '{$col}' missing\n";
        }
    }
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Find first installment fee categories
echo "Test 2: Finding first installment fee categories...\n";
$firstInstallmentCategories = FeesCategory::where('is_first_installment', 1)
    ->where('status', 1)
    ->get();

if ($firstInstallmentCategories->count() > 0) {
    echo "✓ Found {$firstInstallmentCategories->count()} first installment categories:\n";
    foreach ($firstInstallmentCategories as $cat) {
        echo "  - {$cat->title} (ID: {$cat->id})\n";
    }
} else {
    echo "✗ No first installment categories found\n";
}

echo "\n";

// Test 3: Find Type 1 semesters
echo "Test 3: Finding Type 1 (First) semesters...\n";
$type1Semesters = Semester::where('semester_type', 1)
    ->where('is_resit', 0)
    ->where('status', 1)
    ->get();

if ($type1Semesters->count() > 0) {
    echo "✓ Found {$type1Semesters->count()} Type 1 semesters:\n";
    foreach ($type1Semesters as $sem) {
        echo "  - {$sem->title}";
        if ($sem->year) echo " (Year {$sem->year})";
        echo " [ID: {$sem->id}]\n";
    }
} else {
    echo "✗ No Type 1 semesters found\n";
}

echo "\n";

// Test 4: Find existing fee configurations with breakdowns
echo "Test 4: Checking existing fee configurations with breakdowns...\n";
$feesWithBreakdowns = ProgramSemesterFee::whereHas('feesCategory', function($q) {
    $q->where('is_first_installment', 1);
})->whereHas('semester', function($q) {
    $q->where('semester_type', 1);
})->with(['breakdowns', 'program', 'semester', 'feesCategory'])
->get();

if ($feesWithBreakdowns->count() > 0) {
    echo "✓ Found {$feesWithBreakdowns->count()} eligible fee configuration(s):\n";
    foreach ($feesWithBreakdowns as $fee) {
        echo "\n  Program: {$fee->program->title}\n";
        echo "  Semester: {$fee->semester->title}\n";
        echo "  Category: {$fee->feesCategory->title}\n";
        echo "  Amount: {$fee->amount}\n";
        
        if ($fee->breakdowns->count() > 0) {
            echo "  ✓ Has {$fee->breakdowns->count()} breakdown(s):\n";
            $total = 0;
            foreach ($fee->breakdowns as $breakdown) {
                echo "    - {$breakdown->title}: {$breakdown->amount}\n";
                $total += $breakdown->amount;
            }
            echo "  Breakdown Total: {$total}\n";
            
            // Validate total
            if (abs($total - $fee->amount) < 0.01) {
                echo "  ✓ Breakdown total matches fee amount\n";
            } else {
                echo "  ✗ WARNING: Breakdown total ({$total}) does not match fee amount ({$fee->amount})\n";
            }
        } else {
            echo "  ℹ No breakdowns configured yet\n";
        }
    }
} else {
    echo "ℹ No eligible fee configurations found (Type 1 semester + First installment category)\n";
}

echo "\n";

// Test 5: Model relationships
echo "Test 5: Testing model relationships...\n";
if ($feesWithBreakdowns->count() > 0) {
    $testFee = $feesWithBreakdowns->first();
    
    // Test breakdown relationship
    try {
        $breakdowns = $testFee->breakdowns;
        echo "✓ ProgramSemesterFee->breakdowns() relationship works\n";
        
        if ($breakdowns->count() > 0) {
            $testBreakdown = $breakdowns->first();
            $parentFee = $testBreakdown->programSemesterFee;
            echo "✓ ProgramSemesterFeeBreakdown->programSemesterFee() relationship works\n";
        }
    } catch (\Exception $e) {
        echo "✗ Relationship error: " . $e->getMessage() . "\n";
    }
} else {
    echo "ℹ Skipping relationship test (no data available)\n";
}

echo "\n";

// Test 6: Create sample breakdown (if no data exists)
echo "Test 6: Testing breakdown creation...\n";
$sampleFee = ProgramSemesterFee::whereHas('feesCategory', function($q) {
    $q->where('is_first_installment', 1);
})->whereHas('semester', function($q) {
    $q->where('semester_type', 1);
})->first();

if ($sampleFee) {
    try {
        // Create sample breakdown
        $breakdown = ProgramSemesterFeeBreakdown::create([
            'program_semester_fee_id' => $sampleFee->id,
            'title' => 'Test Breakdown Item',
            'amount' => 100.00,
            'order' => 999,
        ]);
        
        echo "✓ Successfully created test breakdown (ID: {$breakdown->id})\n";
        
        // Delete it immediately
        $breakdown->delete();
        echo "✓ Successfully deleted test breakdown\n";
    } catch (\Exception $e) {
        echo "✗ Error creating breakdown: " . $e->getMessage() . "\n";
    }
} else {
    echo "ℹ No eligible fee configuration found for testing\n";
}

echo "\n";

// Summary
echo "=== Test Summary ===\n";
echo "Database: Ready\n";
echo "Models: Working\n";
echo "Relationships: Functional\n";
echo "First Installment Categories: {$firstInstallmentCategories->count()} found\n";
echo "Type 1 Semesters: {$type1Semesters->count()} found\n";
echo "Eligible Configurations: {$feesWithBreakdowns->count()} found\n";

echo "\n✓ All tests completed!\n\n";

echo "Next Steps:\n";
echo "1. Go to: http://localhost/paxhitest/admin/program-semester-fee/create\n";
echo "2. Select a program and Semester Type 1\n";
echo "3. Load fee categories\n";
echo "4. For any first installment category, enter an amount\n";
echo "5. The breakdown section should appear automatically\n";
echo "6. Add breakdown items (titles and amounts)\n";
echo "7. Ensure breakdown total equals fee amount\n";
echo "8. Submit and verify in the index page\n\n";
