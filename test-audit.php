<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;

echo "Creating test audit log entries...\n\n";

try {
    // Create a test audit log
    $log = new AuditLog();
    $log->user_id = null; // System action
    $log->event = 'test';
    $log->auditable_type = 'App\Models\Student';
    $log->auditable_id = 1;
    $log->description = 'Test audit log entry created via script';
    $log->ip_address = '127.0.0.1';
    $log->url = 'http://localhost/test';
    $log->user_agent = 'Test Script';
    $log->save();

    echo "✓ Test log created with ID: {$log->id}\n";

    // Create another test entry
    $log2 = new AuditLog();
    $log2->user_id = 1;
    $log2->event = 'created';
    $log2->auditable_type = 'App\Models\Fee';
    $log2->auditable_id = 100;
    $log2->description = 'Fee assigned to student for Tuition Fee';
    $log2->new_values = json_encode(['fee_amount' => 5000, 'status' => 0]);
    $log2->ip_address = '127.0.0.1';
    $log2->url = 'http://localhost/admin/fee';
    $log2->save();

    echo "✓ Test log created with ID: {$log2->id}\n";

    // Display count
    $count = AuditLog::count();
    echo "\n✓ Total audit logs in database: {$count}\n";
    echo "\n✓ Success! Now visit: http://localhost/admin/audit-log\n";

} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
