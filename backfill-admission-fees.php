<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Backfill Admission Fees for Existing Applications ===\n\n";

// Get admission fee category
$admissionCategory = \App\Models\FeesCategory::where('is_admission', 1)->where('status', 1)->first();
if (!$admissionCategory) {
    echo "✗ No admission fee category found. Please create one first.\n";
    exit(1);
}

echo "✓ Using admission fee category: {$admissionCategory->title} (ID: {$admissionCategory->id})\n\n";

// Get applications without admission fees
$applications = \App\Models\Application::whereNull('admission_fee_id')->get();

echo "Found {$applications->count()} applications without admission fees\n\n";

if ($applications->count() == 0) {
    echo "No applications to process.\n";
    exit(0);
}

$processed = 0;
$errors = 0;

foreach ($applications as $application) {
    try {
        echo "Processing Application #{$application->registration_no} ({$application->first_name} {$application->last_name})...\n";
        
        // Check if student enroll exists
        $tempEnroll = \App\Models\StudentEnroll::where('student_id', $application->id)
            ->where('status', 0)
            ->whereNull('session_id')
            ->first();
        
        if (!$tempEnroll) {
            // Create temporary StudentEnroll
            $tempEnroll = new \App\Models\StudentEnroll();
            $tempEnroll->student_id = $application->id;
            $tempEnroll->program_id = $application->program_id;
            $tempEnroll->session_id = null;
            $tempEnroll->semester_id = null;
            $tempEnroll->section_id = null;
            $tempEnroll->status = 0;
            $tempEnroll->save();
            echo "  - Created temporary enrollment (ID: {$tempEnroll->id})\n";
        } else {
            echo "  - Using existing temporary enrollment (ID: {$tempEnroll->id})\n";
        }
        
        // Create admission fee
        $admissionFee = new \App\Models\Fee();
        $admissionFee->student_enroll_id = $tempEnroll->id;
        $admissionFee->category_id = $admissionCategory->id;
        $admissionFee->fee_amount = 15000;
        $admissionFee->discount_amount = 0;
        $admissionFee->fine_amount = 0;
        $admissionFee->paid_amount = 0;
        $admissionFee->assign_date = now();
        $admissionFee->due_date = now()->addDays(30);
        $admissionFee->status = 0;
        $admissionFee->note = 'Admission fee - Backfilled';
        $admissionFee->save();
        echo "  - Created admission fee (ID: {$admissionFee->id}, Amount: 15,000 FRW)\n";
        
        // Link to application
        $application->admission_fee_id = $admissionFee->id;
        $application->save();
        echo "  ✓ Successfully processed\n\n";
        
        $processed++;
    } catch (\Exception $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n\n";
        $errors++;
    }
}

echo "\n=== Summary ===\n";
echo "Processed: {$processed}\n";
echo "Errors: {$errors}\n";
echo "Total: " . ($processed + $errors) . "\n";
