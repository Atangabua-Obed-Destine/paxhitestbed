<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing Journal Entry Creation ===\n\n";

try {
    // Test database connection
    DB::connection()->getPdo();
    echo "✓ Database connected successfully!\n";
    echo "  Database: " . DB::connection()->getDatabaseName() . "\n";
    echo "  User: " . config('database.connections.mysql.username') . "\n\n";
    
    // Get fiscal year
    $fiscalYear = \App\Models\FiscalYear::where('is_active', true)->first();
    if (!$fiscalYear) {
        echo "✗ No active fiscal year found!\n";
        exit(1);
    }
    echo "✓ Active Fiscal Year: {$fiscalYear->name}\n\n";
    
    // Get accounts
    $accounts = \App\Models\ChartOfAccount::where('account_category', 'detail')
        ->where('is_active', true)
        ->take(5)
        ->get();
    
    echo "✓ Found " . $accounts->count() . " active detail accounts\n";
    foreach($accounts as $acc) {
        echo "  - ID {$acc->id}: {$acc->account_code} - {$acc->account_name}\n";
    }
    echo "\n";
    
    if ($accounts->count() < 2) {
        echo "✗ Need at least 2 accounts to create a journal entry!\n";
        exit(1);
    }
    
    // Create journal entry
    echo "Creating test journal entry...\n";
    $entry = \App\Models\JournalEntry::create([
        'entry_number' => \App\Models\JournalEntry::generateEntryNumber(),
        'entry_date' => now(),
        'fiscal_year_id' => $fiscalYear->id,
        'journal_type' => 'general',
        'description' => 'Test Entry Created from PHP Script',
        'reference_number' => 'TEST-' . time(),
        'total_debit' => 500,
        'total_credit' => 500,
        'is_posted' => false,
        'is_system_generated' => false,
        'created_by' => 1,
    ]);
    
    echo "✓ Journal Entry Created!\n";
    echo "  ID: {$entry->id}\n";
    echo "  Entry Number: {$entry->entry_number}\n";
    echo "  Date: {$entry->entry_date->format('Y-m-d')}\n\n";
    
    // Create journal entry lines
    echo "Creating journal entry lines...\n";
    
    $line1 = \App\Models\JournalEntryLine::create([
        'journal_entry_id' => $entry->id,
        'account_id' => $accounts[0]->id,
        'line_number' => 1,
        'description' => 'Test Debit - ' . $accounts[0]->account_name,
        'debit' => 500,
        'credit' => 0,
    ]);
    echo "  ✓ Line 1 created: Debit {$accounts[0]->account_code} - 500 FCFA\n";
    
    $line2 = \App\Models\JournalEntryLine::create([
        'journal_entry_id' => $entry->id,
        'account_id' => $accounts[1]->id,
        'line_number' => 2,
        'description' => 'Test Credit - ' . $accounts[1]->account_name,
        'debit' => 0,
        'credit' => 500,
    ]);
    echo "  ✓ Line 2 created: Credit {$accounts[1]->account_code} - 500 FCFA\n\n";
    
    echo "=== SUCCESS! ===\n";
    echo "Journal Entry #{$entry->id} created successfully!\n";
    echo "View at: http://localhost/paxhi/admin/journal-entries/{$entry->id}\n\n";
    
    // Verify it's in the database
    $verify = \App\Models\JournalEntry::with('lines')->find($entry->id);
    echo "Verification:\n";
    echo "  - Entry found: " . ($verify ? "Yes" : "No") . "\n";
    echo "  - Lines count: " . $verify->lines->count() . "\n";
    echo "  - Is balanced: " . ($verify->isBalanced() ? "Yes" : "No") . "\n";
    
} catch (\Exception $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}
