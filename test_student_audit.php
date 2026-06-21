<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== TESTING STUDENT AUDIT TRAIL ===\n\n";

// Find the most recent audit logs
$audits = \App\Models\AuditLog::with('user')
    ->latest()
    ->take(10)
    ->get();

if ($audits->isEmpty()) {
    echo "No audit logs found.\n";
    exit;
}

echo "Recent Audit Logs:\n";
echo str_repeat('-', 100) . "\n";

foreach ($audits as $audit) {
    echo "Audit ID: {$audit->id}\n";
    echo "Event: {$audit->event}\n";
    echo "Description: {$audit->description}\n";
    echo "User Type: {$audit->user_type}\n";
    
    if ($audit->user) {
        echo "User Found: YES\n";
        echo "User Display Name: {$audit->user_name}\n";
        echo "User Identifier: {$audit->user_identifier}\n";
    } else {
        echo "User Found: NO (System action)\n";
    }
    
    echo "Created At: {$audit->created_at}\n";
    echo str_repeat('-', 100) . "\n";
}

echo "\n=== Checking Payment Receipt Audits ===\n";
$receiptAudits = \App\Models\AuditLog::where('auditable_type', 'App\Models\PaymentReceipt')
    ->with('user')
    ->latest()
    ->take(5)
    ->get();

if ($receiptAudits->isEmpty()) {
    echo "No payment receipt audit logs found.\n";
} else {
    foreach ($receiptAudits as $audit) {
        echo "\n";
        echo "Event: {$audit->event}\n";
        echo "Description: {$audit->description}\n";
        echo "User: {$audit->user_name}\n";
        echo "Identifier: {$audit->user_identifier}\n";
    }
}

echo "\n=== TEST COMPLETE ===\n";
