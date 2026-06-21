<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Admission Fee Implementation Test ===\n\n";

// 1. Check if admission fee category exists
echo "1. Checking admission fee category...\n";
$admissionCategory = \App\Models\FeesCategory::where('is_admission', 1)->where('status', 1)->first();
if ($admissionCategory) {
    echo "   ✓ Admission fee category found: {$admissionCategory->title}\n";
    echo "   - ID: {$admissionCategory->id}\n";
    echo "   - Is Admission: " . ($admissionCategory->is_admission ? 'Yes' : 'No') . "\n";
} else {
    echo "   ✗ No admission fee category found\n";
    echo "   Creating a test admission fee category...\n";
    $admissionCategory = new \App\Models\FeesCategory();
    $admissionCategory->title = 'Admission Fee';
    $admissionCategory->slug = 'admission-fee';
    $admissionCategory->is_admission = 1;
    $admissionCategory->status = 1;
    $admissionCategory->save();
    echo "   ✓ Created: {$admissionCategory->title} (ID: {$admissionCategory->id})\n";
}

echo "\n2. Checking application with admission fee...\n";
$applications = \App\Models\Application::with('admissionFee.category')->latest()->take(5)->get();
if ($applications->count() > 0) {
    echo "   Found {$applications->count()} recent applications:\n";
    foreach ($applications as $app) {
        echo "   - Application #{$app->registration_no} ({$app->first_name} {$app->last_name})\n";
        if ($app->admissionFee) {
            echo "     ✓ Has admission fee: {$app->admissionFee->category->title}\n";
            echo "       Amount: " . number_format($app->admissionFee->fee_amount, 2) . " FRW\n";
            echo "       Paid: " . number_format($app->admissionFee->paid_amount, 2) . " FRW\n";
            echo "       Balance: " . number_format($app->admissionFee->remaining_balance, 2) . " FRW\n";
        } else {
            echo "     ✗ No admission fee assigned\n";
        }
    }
} else {
    echo "   No applications found in database\n";
}

echo "\n3. Checking StudentEnroll records for applications...\n";
$enrolls = \App\Models\StudentEnroll::where('session_id', null)->where('status', 0)->take(5)->get();
if ($enrolls->count() > 0) {
    echo "   Found {$enrolls->count()} temporary enrollments (for applicants):\n";
    foreach ($enrolls as $enroll) {
        echo "   - Enroll ID: {$enroll->id}, Student ID: {$enroll->student_id}\n";
        $fees = $enroll->fees;
        if ($fees->count() > 0) {
            echo "     ✓ Has {$fees->count()} fee(s) assigned\n";
            foreach ($fees as $fee) {
                echo "       - {$fee->category->title}: " . number_format($fee->fee_amount, 2) . " FRW\n";
            }
        } else {
            echo "     ✗ No fees assigned\n";
        }
    }
} else {
    echo "   No temporary enrollments found\n";
}

echo "\n=== Test Complete ===\n";
